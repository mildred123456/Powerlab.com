/* =========================================================
   PowerLab · Modo entrenamiento
   - Serie a serie: repeticiones (con peso) o cronómetro para ejercicios por tiempo
   - Descanso con cuenta regresiva (±15 s, saltar), pitido y vibración al terminar
   - Recordatorio de hidratación cada N minutos (Configuración)
   - El progreso se guarda en este navegador: si recargas, se retoma (hasta 6 h)
   - Al terminar se envía al servidor (controllers/guardar-entrenamiento.php)
   También expone PowerLab.pitido() para el botón "Probar sonido" de Configuración.
   ========================================================= */
(function () {
  "use strict";

  const toast = (tipo, msg, duracion) => window.PowerLab && window.PowerLab.toast(tipo, msg, duracion);

  /* ---------- Sonido y vibración ---------- */
  let audio = null;
  function contextoAudio() {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      if (audio.state === "suspended") audio.resume();
      return audio;
    } catch (e) {
      return null;
    }
  }
  function pitido(veces) {
    const ctx = contextoAudio();
    if (!ctx) return;
    const n = veces || 3;
    for (let i = 0; i < n; i++) {
      const t = ctx.currentTime + i * 0.28;
      const osc = ctx.createOscillator();
      const ganancia = ctx.createGain();
      osc.type = "sine";
      osc.frequency.value = i === n - 1 ? 1175 : 880;
      ganancia.gain.setValueAtTime(0.0001, t);
      ganancia.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
      ganancia.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
      osc.connect(ganancia).connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.24);
    }
  }
  function vibrar(patron) {
    try {
      if (navigator.vibrate) navigator.vibrate(patron);
    } catch (e) {
      /* no compatible */
    }
  }
  window.PowerLab = Object.assign(window.PowerLab || {}, { pitido: pitido, vibrar: vibrar });
  // Los navegadores solo permiten sonido después de una interacción: se prepara en el primer toque
  document.addEventListener("pointerdown", contextoAudio, { once: true });

  /* ---------- Utilidades ---------- */
  const mmss = (s) => {
    s = Math.max(0, Math.round(s));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const seg = String(s % 60).padStart(2, "0");
    return h ? h + ":" + String(m).padStart(2, "0") + ":" + seg : m + ":" + seg;
  };
  const kilos = (v) => String(Math.round(v * 100) / 100).replace(".", ",");
  const tiempo = (s) => (s < 60 ? s + " s" : mmss(s) + " min");
  const meta = (it) => (it.duracion_seg != null ? tiempo(it.duracion_seg) : it.repeticiones + " reps");
  const fechaCorta = (texto) => {
    const d = new Date(String(texto).replace(" ", "T"));
    return isNaN(d) ? "" : d.toLocaleDateString("es", { day: "numeric", month: "short" });
  };

  // Confirmación con modal (devuelve una promesa)
  function confirmar(texto, textoBoton, peligro) {
    return new Promise((resolver) => {
      const modalEl = document.createElement("div");
      modalEl.className = "modal fade";
      modalEl.tabIndex = -1;
      modalEl.innerHTML =
        '<div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4">' +
        '<div class="modal-body p-4 text-center"><p class="fw-semibold mb-4" data-texto></p>' +
        '<div class="d-flex gap-2 justify-content-center">' +
        '<button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancelar</button>' +
        '<button type="button" class="btn px-4" data-aceptar></button></div></div></div></div>';
      modalEl.querySelector("[data-texto]").textContent = texto;
      const aceptar = modalEl.querySelector("[data-aceptar]");
      aceptar.textContent = textoBoton;
      aceptar.classList.add(peligro ? "btn-danger" : "btn-pl");
      let ok = false;
      aceptar.addEventListener("click", () => {
        ok = true;
        bootstrap.Modal.getInstance(modalEl).hide();
      });
      modalEl.addEventListener("hidden.bs.modal", () => {
        modalEl.remove();
        resolver(ok);
      });
      document.body.appendChild(modalEl);
      new bootstrap.Modal(modalEl).show();
    });
  }

  /* ---------- Sesión de entrenamiento ---------- */
  function iniciarEntreno() {
    const raiz = document.querySelector("[data-entreno]");
    if (!raiz) return;
    let cfg;
    try {
      cfg = JSON.parse(raiz.dataset.config);
    } catch (e) {
      return;
    }
    const items = cfg.items;
    const pref = cfg.preferencias;
    const clave = "pl-entreno-" + cfg.usuario + "-" + cfg.rutina.id;
    const totalSeries = items.reduce((a, i) => a + i.series, 0);
    const q = (sel) => raiz.querySelector(sel);

    const repsIn = q('[data-entrada="reps"]');
    const pesoIn = q('[data-entrada="peso"]');
    const anillo = q("[data-descanso-anillo]");
    const CIRCUNFERENCIA = 2 * Math.PI * 54;
    anillo.style.strokeDasharray = CIRCUNFERENCIA;

    // ----- Estado (persistido en localStorage) -----
    function nuevoEstado() {
      return { v: 1, inicio: Math.floor(Date.now() / 1000), idx: 0, serie: 1, fase: "ejercicio", descansoHasta: 0, descansoTotal: 0,
               registros: [], pesos: {}, ultimaHidratacion: Date.now(), fin: null };
    }
    function leerEstado() {
      try {
        const e = JSON.parse(localStorage.getItem(clave) || "null");
        if (e && e.v === 1 && Date.now() / 1000 - e.inicio < 6 * 3600 && e.idx <= items.length) return e;
      } catch (err) {
        /* almacenamiento no disponible */
      }
      return null;
    }
    function guardarEstado() {
      try {
        localStorage.setItem(clave, JSON.stringify(estado));
      } catch (e) {
        /* sin almacenamiento: la sesión sigue, solo que no se puede retomar */
      }
    }
    function borrarEstado() {
      try {
        localStorage.removeItem(clave);
      } catch (e) {
        /* nada */
      }
    }
    const previo = leerEstado();
    let estado = previo || nuevoEstado();

    // ----- Pantallas -----
    function mostrar(fase) {
      raiz.querySelectorAll("[data-pantalla]").forEach((p) => (p.hidden = p.dataset.pantalla !== fase));
      estado.fase = fase;
      guardarEstado();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function pintarProgreso() {
      const hechas = estado.registros.length;
      const pct = Math.min(100, Math.round((hechas / totalSeries) * 100));
      const barra = q("[data-entreno-progreso]");
      barra.setAttribute("aria-valuenow", pct);
      barra.querySelector(".progress-bar").style.width = pct + "%";
      q("[data-entreno-progreso-texto]").textContent =
        hechas + " de " + totalSeries + " series · Ejercicio " + Math.min(estado.idx + 1, items.length) + " de " + items.length;
    }

    function pintarVisual(visual, ej) {
      visual.className = visual.className.replace(/\bpl-tono-\d\b/g, "").trim() + " pl-tono-" + (parseInt(ej.grupo_tono, 10) || 1);
      const img = visual.querySelector('[data-ej="img"]');
      const icono = visual.querySelector('[data-ej="icono"]');
      const src = ej.imagen || ej.respaldo || "";
      icono.className = "pl-ej-visual__icono bi " + (/^bi-[a-z0-9-]+$/.test(ej.grupo_icono || "") ? ej.grupo_icono : "bi-collection");
      img.alt = ej.nombre;
      img.hidden = !src;
      icono.hidden = !!src;
      if (src) img.src = src;
    }

    function pintarEjercicio() {
      const it = items[estado.idx];
      const ej = it.ejercicio;
      const porTiempo = it.duracion_seg != null;
      pintarVisual(q('[data-ej="visual"]'), ej);
      const grupo = q("[data-entreno-grupo]");
      grupo.className = "pl-grupo pl-tono-" + (parseInt(ej.grupo_tono, 10) || 1);
      grupo.textContent = ej.grupo;
      q("[data-entreno-nombre]").textContent = ej.nombre;
      q("[data-entreno-serie]").textContent = "Serie " + estado.serie + " de " + it.series;
      q("[data-entreno-objetivo]").textContent = "Meta: " + meta(it) + (it.peso_kg ? " · " + kilos(it.peso_kg) + " kg" : "");

      const ultima = q("[data-entreno-ultima]");
      if (it.ultima) {
        const u = it.ultima;
        const hecho = u.duracion_seg != null ? tiempo(u.duracion_seg) : u.repeticiones + " reps";
        ultima.textContent = "Última vez (" + fechaCorta(u.fecha) + "): " + hecho + (u.peso_kg ? " × " + kilos(u.peso_kg) + " kg" : "");
        ultima.hidden = false;
      } else {
        ultima.hidden = true;
      }
      const nota = q("[data-entreno-nota]");
      nota.hidden = !it.notas;
      nota.querySelector("span").textContent = it.notas || "";

      const pasos = q("[data-entreno-pasos]");
      pasos.replaceChildren(...it.pasos.map((p) => Object.assign(document.createElement("li"), { textContent: p })));
      pasos.closest("details").hidden = !it.pasos.length;

      q('[data-registro="reps"]').hidden = porTiempo;
      q('[data-registro="tiempo"]').hidden = !porTiempo;
      repsIn.value = it.repeticiones != null ? it.repeticiones : "";
      const peso = estado.pesos[estado.idx] ?? it.peso_kg ?? (it.ultima && it.ultima.peso_kg) ?? "";
      pesoIn.value = peso === null ? "" : peso;
      reiniciarCrono(porTiempo ? it.duracion_seg : 0);

      const siguiente = items[estado.idx + 1];
      const quedan = it.series - estado.serie;
      q("[data-entreno-siguiente]").textContent = quedan > 0
        ? "Te quedan " + quedan + " serie" + (quedan === 1 ? "" : "s") + " de este ejercicio."
        : siguiente ? "Después: " + siguiente.ejercicio.nombre : "¡Último ejercicio!";
      pintarProgreso();
    }

    // ----- Registrar la serie -----
    function serieHecha() {
      const it = items[estado.idx];
      const pesoNum = parseFloat(String(pesoIn.value).replace(",", "."));
      const peso = isFinite(pesoNum) && pesoNum >= 0 ? Math.min(500, pesoNum) : null;
      const registro = { id_ejercicio: it.id_ejercicio, numero_serie: estado.serie, repeticiones: null, duracion_seg: null, peso_kg: peso };
      if (it.duracion_seg != null) {
        registro.duracion_seg = crono.usado ? Math.max(1, Math.min(3600, Math.round(crono.transcurrido()))) : it.duracion_seg;
        detenerCrono();
      } else {
        const reps = parseInt(repsIn.value, 10);
        if (!(reps >= 0 && reps <= 500)) {
          toast("warning", "Escribe cuántas repeticiones hiciste.");
          repsIn.focus();
          return;
        }
        registro.repeticiones = reps;
      }
      if (peso !== null) estado.pesos[estado.idx] = peso;
      estado.registros.push(registro);
      if (pref.vibracion) vibrar(60);
      siguientePaso(it);
    }

    function siguientePaso(hecho) {
      if (estado.serie < hecho.series) {
        estado.serie++;
      } else {
        estado.idx++;
        estado.serie = 1;
      }
      if (estado.idx >= items.length) {
        terminar();
      } else if (hecho.descanso_seg > 0) {
        iniciarDescanso(hecho.descanso_seg);
      } else {
        pintarEjercicio();
        mostrar("ejercicio");
      }
    }

    async function saltarEjercicio() {
      const it = items[estado.idx];
      const ok = await confirmar("¿Saltar «" + it.ejercicio.nombre + "»? Las series que ya hiciste se conservan.", "Saltar");
      if (!ok) return;
      detenerCrono();
      estado.idx++;
      estado.serie = 1;
      if (estado.idx >= items.length) {
        terminar();
      } else {
        pintarEjercicio();
        mostrar("ejercicio");
      }
    }

    // ----- Cronómetro (series por tiempo) -----
    const crono = { objetivo: 0, inicio: 0, acumulado: 0, corriendo: false, usado: false, timer: null,
                    transcurrido() { return this.acumulado + (this.corriendo ? (Date.now() - this.inicio) / 1000 : 0); } };
    const cronoEl = q("[data-crono]");
    const cronoBoton = q("[data-crono-boton]");
    function pintarCrono() {
      const restante = crono.objetivo - crono.transcurrido();
      cronoEl.textContent = mmss(Math.ceil(restante));
      cronoEl.classList.toggle("is-corriendo", crono.corriendo);
      cronoBoton.innerHTML = crono.corriendo ? '<i class="bi bi-pause-fill me-1"></i>Pausar' : '<i class="bi bi-play-fill me-1"></i>' + (crono.usado ? "Continuar" : "Iniciar");
      if (crono.corriendo && restante <= 0) {
        crono.acumulado = crono.objetivo;
        crono.corriendo = false;
        clearInterval(crono.timer);
        if (pref.sonido) pitido(3);
        if (pref.vibracion) vibrar([200, 100, 200]);
        serieHecha();
      }
    }
    function reiniciarCrono(segundos) {
      detenerCrono();
      crono.objetivo = segundos;
      crono.acumulado = 0;
      crono.usado = false;
      pintarCrono();
    }
    function detenerCrono() {
      clearInterval(crono.timer);
      if (crono.corriendo) crono.acumulado += (Date.now() - crono.inicio) / 1000;
      crono.corriendo = false;
    }
    cronoBoton.addEventListener("click", () => {
      if (crono.corriendo) {
        detenerCrono();
      } else {
        crono.inicio = Date.now();
        crono.corriendo = true;
        crono.usado = true;
        crono.timer = setInterval(pintarCrono, 200);
      }
      pintarCrono();
    });

    // ----- Descanso -----
    let tickDescanso = null;
    function iniciarDescanso(segundos) {
      estado.descansoTotal = segundos;
      estado.descansoHasta = Date.now() + segundos * 1000;
      const it = items[estado.idx];
      q("[data-descanso-siguiente]").textContent =
        "A continuación: " + it.ejercicio.nombre + " · serie " + estado.serie + " de " + it.series + " · " + meta(it);
      mostrar("descanso");
      bucleDescanso();
    }
    function bucleDescanso() {
      clearInterval(tickDescanso);
      const pintar = () => {
        const restante = Math.max(0, Math.ceil((estado.descansoHasta - Date.now()) / 1000));
        q("[data-descanso-tiempo]").textContent = mmss(restante);
        anillo.style.strokeDashoffset = CIRCUNFERENCIA * (1 - restante / Math.max(1, estado.descansoTotal));
        if (restante <= 0) finDescanso(true);
      };
      pintar();
      tickDescanso = setInterval(pintar, 250);
    }
    function finDescanso(avisar) {
      clearInterval(tickDescanso);
      estado.descansoHasta = 0;
      if (avisar) {
        if (pref.sonido) pitido(3);
        if (pref.vibracion) vibrar([200, 100, 200]);
      }
      pintarEjercicio();
      mostrar("ejercicio");
    }
    raiz.querySelectorAll("[data-descanso-ajuste]").forEach((b) =>
      b.addEventListener("click", () => {
        const delta = parseInt(b.dataset.descansoAjuste, 10);
        estado.descansoHasta += delta * 1000;
        estado.descansoTotal = Math.max(1, estado.descansoTotal + delta);
        guardarEstado();
        if (estado.descansoHasta <= Date.now()) finDescanso(false);
      })
    );
    q("[data-descanso-saltar]").addEventListener("click", () => finDescanso(false));

    // ----- Fin -----
    function terminar() {
      clearInterval(tickDescanso);
      detenerCrono();
      estado.fin = Math.floor(Date.now() / 1000);
      const series = estado.registros.length;
      const volumen = estado.registros.reduce((a, r) => a + (r.repeticiones || 0) * (r.peso_kg || 0), 0);
      q('[data-fin="duracion"]').textContent = Math.max(1, Math.round((estado.fin - estado.inicio) / 60)) + " min";
      q('[data-fin="series"]').textContent = series;
      q('[data-fin="volumen"]').textContent = volumen ? kilos(volumen) : "—";
      q("[data-fin-texto]").textContent = !series
        ? "No registraste ninguna serie, así que no hay nada que guardar."
        : series < totalSeries
          ? "Hiciste " + series + " de " + totalSeries + " series. ¡Cada serie cuenta!"
          : "Completaste todas las series de la rutina. ¡Excelente trabajo!";
      q("[data-entreno-guardar]").disabled = !series;
      q("[data-entreno-continuar]").hidden = estado.idx >= items.length;
      pintarProgreso();
      mostrar("fin");
    }

    q("[data-entreno-continuar]").addEventListener("click", () => {
      estado.fin = null;
      pintarEjercicio();
      mostrar("ejercicio");
    });

    q("[data-entreno-guardar]").addEventListener("click", async (ev) => {
      const boton = ev.currentTarget;
      boton.disabled = true;
      const textoOriginal = boton.innerHTML;
      boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando…';
      const datos = new FormData();
      datos.append("csrf_token", q("[name=csrf_token]").value);
      datos.append("datos", JSON.stringify({ id_rutina: cfg.rutina.id, inicio: estado.inicio, series: estado.registros }));
      try {
        const r = await fetch(cfg.url_guardar, { method: "POST", body: datos, headers: { Accept: "application/json" } });
        const json = await r.json();
        if (!r.ok || !json.ok) throw new Error(json.mensaje || "No se pudo guardar el entrenamiento.");
        borrarEstado();
        location.href = json.url;
      } catch (e) {
        toast("danger", (e.message || "No se pudo guardar.") + " Tu progreso sigue en este dispositivo; inténtalo de nuevo.", 8000);
        boton.disabled = false;
        boton.innerHTML = textoOriginal;
      }
    });

    q("[data-entreno-descartar]").addEventListener("click", async () => {
      if (await confirmar("¿Descartar este entrenamiento? No se guardará en tu historial.", "Descartar", true)) {
        borrarEstado();
        location.href = cfg.url_salir;
      }
    });

    q("[data-entreno-terminar]").addEventListener("click", async () => {
      if (estado.fase === "fin") return;
      if (await confirmar("¿Terminar el entrenamiento ahora? Podrás guardar las series que ya hiciste.", "Terminar")) terminar();
    });

    q("[data-entreno-salir]").addEventListener("click", async () => {
      if (!estado.registros.length) {
        borrarEstado();
        location.href = cfg.url_salir;
        return;
      }
      if (await confirmar("Tu progreso queda guardado en este dispositivo: si vuelves a esta rutina en las próximas 6 horas, retomas donde quedaste.", "Salir")) {
        location.href = cfg.url_salir;
      }
    });

    // ----- Controles de la serie -----
    q("[data-entreno-hecha]").addEventListener("click", serieHecha);
    q("[data-entreno-saltar]").addEventListener("click", saltarEjercicio);
    raiz.querySelectorAll("[data-paso]").forEach((b) =>
      b.addEventListener("click", () => {
        const entrada = b.dataset.paso === "reps" ? repsIn : pesoIn;
        const actual = parseFloat(String(entrada.value).replace(",", ".")) || 0;
        const nuevo = Math.min(parseFloat(entrada.max), Math.max(0, actual + parseFloat(b.dataset.delta)));
        entrada.value = Math.round(nuevo * 100) / 100;
      })
    );

    // ----- Reloj general -----
    const reloj = q("[data-entreno-reloj]");
    const pintarReloj = () => (reloj.textContent = mmss((estado.fin || Date.now() / 1000) - estado.inicio));
    pintarReloj();
    setInterval(pintarReloj, 1000);

    // ----- Hidratación -----
    const aviso = q("[data-hidratacion]");
    let ocultarAviso = null;
    q("[data-hidratacion-ok]").addEventListener("click", () => (aviso.hidden = true));
    if (pref.hidratacion_activa) {
      setInterval(() => {
        if (estado.fase === "fin" || Date.now() - estado.ultimaHidratacion < pref.hidratacion_min * 60000) return;
        estado.ultimaHidratacion = Date.now();
        guardarEstado();
        aviso.hidden = false;
        if (pref.sonido) pitido(1);
        if (pref.vibracion) vibrar(300);
        clearTimeout(ocultarAviso);
        ocultarAviso = setTimeout(() => (aviso.hidden = true), 20000);
      }, 10000);
    }

    // ----- Pantalla siempre encendida (si el navegador lo permite) -----
    let bloqueo = null;
    async function mantenerPantalla() {
      try {
        if ("wakeLock" in navigator && document.visibilityState === "visible") bloqueo = await navigator.wakeLock.request("screen");
      } catch (e) {
        bloqueo = null;
      }
    }
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible" && !bloqueo) mantenerPantalla();
      if (document.visibilityState === "visible" && estado.fase === "descanso") bucleDescanso();
    });
    mantenerPantalla();

    // ----- Arranque (nuevo o retomado) -----
    if (previo) toast("info", "Retomamos tu entrenamiento en curso.");
    if (cfg.omitidos > 0) toast("warning", cfg.omitidos + " ejercicio" + (cfg.omitidos === 1 ? "" : "s") + " de esta rutina ya no está disponible y se saltará.");
    if (estado.fase === "fin" && estado.fin) {
      terminar();
    } else if (estado.fase === "descanso" && estado.descansoHasta > Date.now() && estado.idx < items.length) {
      pintarEjercicio();
      mostrar("descanso");
      const it = items[estado.idx];
      q("[data-descanso-siguiente]").textContent = "A continuación: " + it.ejercicio.nombre + " · serie " + estado.serie + " de " + it.series + " · " + meta(it);
      bucleDescanso();
    } else if (estado.idx >= items.length) {
      terminar();
    } else {
      pintarEjercicio();
      mostrar("ejercicio");
    }
  }

  /* ---------- Configuración: probar el sonido ---------- */
  function iniciarPruebaSonido() {
    document.querySelectorAll("[data-probar-sonido]").forEach((b) => b.addEventListener("click", () => pitido(3)));
    document.querySelectorAll("[data-probar-vibracion]").forEach((b) =>
      b.addEventListener("click", () => {
        if (!navigator.vibrate) toast("info", "Tu dispositivo o navegador no admite vibración.");
        vibrar([200, 100, 200]);
      })
    );
  }

  document.addEventListener("DOMContentLoaded", () => {
    iniciarEntreno();
    iniciarPruebaSonido();
  });
})();
