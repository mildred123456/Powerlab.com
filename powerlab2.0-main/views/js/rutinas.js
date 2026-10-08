/* =========================================================
   PowerLab · Formulario de rutina
   - Lista de ejercicios con series, reps o tiempo, peso, descanso y notas
   - Reordenar / quitar, resumen en vivo (ejercicios, series, minutos)
   - Selector de ejercicios de la biblioteca (buscador + grupos + "cargar más")
   Los campos se llaman items[i][campo] y se renumeran al mover o quitar.
   ========================================================= */
(function () {
  "use strict";

  const toast = (tipo, msg) => window.PowerLab && window.PowerLab.toast(tipo, msg);
  const TIPOS_POR_TIEMPO = ["Cardio", "Isométrico"];

  // Imagen del ejercicio (o la de su equipo / grupo) o, si no hay, el ícono sobre el color del grupo
  function pintarVisual(visual, ej) {
    visual.className = visual.className.replace(/\bpl-tono-\d\b/g, "").trim() + " pl-tono-" + (parseInt(ej.grupo_tono, 10) || 1);
    const img = visual.querySelector('[data-ej="img"]');
    const icono = visual.querySelector('[data-ej="icono"]');
    const src = ej.imagen || ej.respaldo || "";
    icono.className = "pl-ej-visual__icono bi " + (/^bi-[a-z0-9-]+$/.test(ej.grupo_icono || "") ? ej.grupo_icono : "bi-collection");
    img.hidden = !src;
    icono.hidden = !!src;
    if (src) img.src = src;
  }

  function iniciar() {
    const form = document.querySelector("[data-rutina-form]");
    if (!form) return;

    const lista = form.querySelector("[data-rutina-items]");
    const vacio = form.querySelector("[data-rutina-vacio]");
    const plantilla = document.getElementById("plPlantillaItemRutina");
    const descansoDefecto = parseInt(form.dataset.descansoDefecto, 10) || 90;
    const segPorRep = parseInt(form.dataset.segRep, 10) || 4;
    const maximo = parseInt(form.dataset.max, 10) || 30;
    let secuencia = 0; // para ids únicos de los campos

    /* ----- Un ejercicio de la rutina ----- */
    function crearItem(datos) {
      const nodo = plantilla.content.firstElementChild.cloneNode(true);
      const uid = "it" + ++secuencia;
      const ej = datos.ejercicio;
      nodo.dataset.idEjercicio = ej.id;

      pintarVisual(nodo.querySelector('[data-ej="visual"]'), ej);
      const grupo = nodo.querySelector("[data-item-grupo]");
      grupo.textContent = ej.grupo;
      grupo.classList.add("pl-tono-" + (parseInt(ej.grupo_tono, 10) || 1));
      nodo.querySelector("[data-item-nombre]").textContent = ej.nombre;
      nodo.querySelector("[data-item-equipo]").textContent = [ej.equipamiento, ej.musculo].filter(Boolean).join(" · ");
      if (datos.disponible === false) {
        nodo.classList.add("is-no-disponible");
        nodo.querySelector("[data-item-no-disponible]").hidden = false;
      }

      const campo = (c) => nodo.querySelector('[data-campo="' + c + '"]');
      // ids únicos y etiquetas asociadas
      ["series", "valor", "peso_kg", "descanso_seg", "notas"].forEach((c) => (campo(c).id = uid + "-" + c));
      const etiquetas = { series: "series", valor: "valor", peso: "peso_kg", descanso: "descanso_seg", notas: "notas" };
      Object.entries(etiquetas).forEach(([et, c]) => (nodo.querySelector('[data-etiqueta="' + et + '"]').htmlFor = uid + "-" + c));
      const radioReps = nodo.querySelector('[data-meta="reps"]');
      const radioTiempo = nodo.querySelector('[data-meta="tiempo"]');
      radioReps.id = uid + "-reps";
      radioTiempo.id = uid + "-tiempo";
      nodo.querySelector('[data-etiqueta="meta-reps"]').htmlFor = radioReps.id;
      nodo.querySelector('[data-etiqueta="meta-tiempo"]').htmlFor = radioTiempo.id;

      // Valores
      const porTiempo = datos.duracion_seg != null || (datos.repeticiones == null && TIPOS_POR_TIEMPO.includes(ej.tipo));
      campo("id_ejercicio").value = ej.id;
      campo("series").value = datos.series || 3;
      (porTiempo ? radioTiempo : radioReps).checked = true;
      campo("valor").value = porTiempo ? datos.duracion_seg || (ej.tipo === "Cardio" ? 60 : 30) : datos.repeticiones || 12;
      campo("peso_kg").value = datos.peso_kg != null ? datos.peso_kg : "";
      campo("descanso_seg").value = datos.descanso_seg != null ? datos.descanso_seg : "";
      campo("descanso_seg").placeholder = descansoDefecto + " (def.)";
      campo("notas").value = datos.notas || "";
      ajustarMeta(nodo);

      [radioReps, radioTiempo].forEach((r) =>
        r.addEventListener("change", () => {
          // Al cambiar de reps a tiempo (o al revés) se propone un valor razonable
          campo("valor").value = r.value === "tiempo" ? (ej.tipo === "Cardio" ? 60 : 30) : 12;
          ajustarMeta(nodo);
          actualizar();
        })
      );
      nodo.querySelectorAll("input").forEach((i) => i.addEventListener("input", actualizarResumen));
      nodo.querySelector("[data-item-subir]").addEventListener("click", () => mover(nodo, -1));
      nodo.querySelector("[data-item-bajar]").addEventListener("click", () => mover(nodo, 1));
      nodo.querySelector("[data-item-quitar]").addEventListener("click", () => {
        nodo.remove();
        actualizar();
        selector.marcar();
      });
      return nodo;
    }

    // Repeticiones (1-200) o segundos (5-3600): cambia límites y etiqueta
    function ajustarMeta(nodo) {
      const porTiempo = nodo.querySelector('[data-meta="tiempo"]').checked;
      const valor = nodo.querySelector('[data-campo="valor"]');
      const etiqueta = nodo.querySelector('[data-etiqueta="valor"]');
      valor.min = porTiempo ? 5 : 1;
      valor.max = porTiempo ? 3600 : 200;
      valor.step = porTiempo ? 5 : 1;
      etiqueta.textContent = porTiempo ? etiqueta.dataset.textoTiempo : etiqueta.dataset.textoReps;
    }

    function mover(nodo, direccion) {
      const destino = direccion < 0 ? nodo.previousElementSibling : nodo.nextElementSibling;
      if (!destino) return;
      direccion < 0 ? lista.insertBefore(nodo, destino) : lista.insertBefore(destino, nodo);
      actualizar();
      nodo.querySelector(direccion < 0 ? "[data-item-subir]" : "[data-item-bajar]").focus();
    }

    // Renumera los nombres items[i][campo], los números visibles y los botones de orden
    function actualizar() {
      const items = [...lista.children];
      items.forEach((nodo, i) => {
        nodo.querySelector("[data-item-numero]").textContent = i + 1;
        nodo.querySelectorAll("[data-campo]").forEach((c) => (c.name = "items[" + i + "][" + c.dataset.campo + "]"));
        nodo.querySelector("[data-item-subir]").disabled = i === 0;
        nodo.querySelector("[data-item-bajar]").disabled = i === items.length - 1;
      });
      vacio.hidden = items.length > 0;
      lista.hidden = items.length === 0;
      actualizarResumen();
    }

    function actualizarResumen() {
      let series = 0;
      let segundos = 0;
      [...lista.children].forEach((nodo) => {
        const s = parseInt(nodo.querySelector('[data-campo="series"]').value, 10) || 0;
        const valor = parseInt(nodo.querySelector('[data-campo="valor"]').value, 10) || 0;
        const descansoTxt = nodo.querySelector('[data-campo="descanso_seg"]').value;
        const descanso = descansoTxt === "" ? descansoDefecto : parseInt(descansoTxt, 10) || 0;
        const trabajo = nodo.querySelector('[data-meta="tiempo"]').checked ? valor : valor * segPorRep;
        series += s;
        segundos += s * (trabajo + descanso);
      });
      form.querySelector('[data-resumen="ejercicios"]').textContent = lista.children.length;
      form.querySelector('[data-resumen="series"]').textContent = series;
      form.querySelector('[data-resumen="minutos"]').textContent = (series ? Math.max(1, Math.round(segundos / 60)) : 0) + " min";
    }

    function agregar(ejercicio) {
      if (lista.children.length >= maximo) {
        toast("warning", "Una rutina puede tener como máximo " + maximo + " ejercicios.");
        return false;
      }
      const nodo = crearItem({ ejercicio: ejercicio, disponible: true });
      lista.appendChild(nodo);
      actualizar();
      return true;
    }

    // Antes de enviar: al menos un ejercicio
    form.addEventListener(
      "submit",
      (ev) => {
        if (!lista.children.length) {
          ev.preventDefault();
          ev.stopImmediatePropagation();
          toast("warning", "Agrega al menos un ejercicio a la rutina.");
        }
      },
      true
    );

    // Carga inicial
    let iniciales = [];
    try {
      iniciales = JSON.parse(form.dataset.items || "[]");
    } catch (e) {
      iniciales = [];
    }
    iniciales.forEach((d) => lista.appendChild(crearItem(d)));
    actualizar();

    const selector = iniciarSelector(agregar, () => [...lista.children].map((n) => n.dataset.idEjercicio));
    document.querySelectorAll("[data-rutina-agregar]").forEach((b) => b.addEventListener("click", () => selector.abrir()));
  }

  /* ---------- Selector de ejercicios (biblioteca, solo activos) ---------- */
  function iniciarSelector(agregar, idsEnRutina) {
    const modalEl = document.getElementById("modalSelector");
    const caja = modalEl.querySelector("[data-selector]");
    const listaEl = caja.querySelector("[data-selector-lista]");
    const buscar = caja.querySelector("[data-selector-buscar]");
    const chips = caja.querySelector("[data-selector-grupos]");
    const cargando = caja.querySelector("[data-selector-cargando]");
    const vacio = caja.querySelector("[data-selector-vacio]");
    const btnMas = caja.querySelector("[data-selector-mas]");
    const contador = caja.querySelector("[data-selector-contador]");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let grupo = "";
    let pagina = 1;
    let paginas = 1;
    let peticion = 0;
    let espera;
    let agregadosSesion = 0;
    let cargado = false;

    async function cargar(reiniciar) {
      if (reiniciar) {
        pagina = 1;
        listaEl.replaceChildren();
      }
      const n = ++peticion;
      cargando.hidden = false;
      vacio.hidden = true;
      btnMas.hidden = true;
      const params = new URLSearchParams({ q: buscar.value.trim(), grupo: grupo, pagina: pagina });
      try {
        const r = await fetch(caja.dataset.url + "?" + params, { headers: { Accept: "application/json" } });
        const json = await r.json();
        if (n !== peticion) return; // llegó una búsqueda más nueva
        if (!r.ok || !json.ok) throw new Error(json.mensaje || "No se pudo cargar la biblioteca.");
        json.items.forEach((ej) => listaEl.appendChild(fila(ej)));
        paginas = json.paginas;
        vacio.hidden = json.total > 0;
        btnMas.hidden = pagina >= paginas;
        marcar();
      } catch (e) {
        if (n === peticion) toast("danger", e.message);
      } finally {
        if (n === peticion) cargando.hidden = true;
      }
    }

    function fila(ej) {
      const li = document.createElement("li");
      li.className = "pl-selector__fila";
      li.dataset.id = ej.id;
      li.dataset.ejercicio = JSON.stringify(ej);

      const visual = document.createElement("div");
      visual.className = "pl-ej-visual pl-selector__miniatura pl-tono-" + (parseInt(ej.grupo_tono, 10) || 1);
      const src = ej.imagen || ej.respaldo;
      if (src) {
        const img = document.createElement("img");
        img.className = "pl-ej-visual__img";
        img.src = src;
        img.alt = "";
        img.loading = "lazy";
        visual.appendChild(img);
      } else {
        const i = document.createElement("i");
        i.className = "pl-ej-visual__icono bi " + (/^bi-[a-z0-9-]+$/.test(ej.grupo_icono || "") ? ej.grupo_icono : "bi-collection");
        visual.appendChild(i);
      }

      const info = document.createElement("div");
      info.className = "pl-selector__info";
      const nombre = document.createElement("div");
      nombre.className = "pl-selector__nombre";
      nombre.textContent = ej.nombre;
      const meta = document.createElement("div");
      meta.className = "pl-selector__meta";
      meta.textContent = [ej.grupo, ej.equipamiento, ej.nivel].filter(Boolean).join(" · ");
      info.append(nombre, meta);

      const boton = document.createElement("button");
      boton.type = "button";
      boton.className = "btn btn-sm btn-pl-outline pl-selector__boton";
      boton.addEventListener("click", () => {
        if (agregar(ej)) {
          agregadosSesion++;
          marcar();
        }
      });
      li.append(visual, info, boton);
      return li;
    }

    // Marca cuántas veces está cada ejercicio en la rutina (se puede repetir uno, p. ej. en circuitos)
    function marcar() {
      const ids = idsEnRutina();
      listaEl.querySelectorAll(".pl-selector__fila").forEach((li) => {
        const veces = ids.filter((id) => id === li.dataset.id).length;
        const boton = li.querySelector("button");
        boton.innerHTML = veces ? '<i class="bi bi-check2 me-1"></i>Agregado' + (veces > 1 ? " ×" + veces : "") : '<i class="bi bi-plus-lg me-1"></i>Agregar';
        boton.classList.toggle("is-agregado", veces > 0);
        boton.setAttribute("aria-label", (veces ? "Agregar otra vez " : "Agregar ") + JSON.parse(li.dataset.ejercicio).nombre);
      });
      contador.textContent = agregadosSesion ? agregadosSesion + " agregado" + (agregadosSesion === 1 ? "" : "s") : "";
    }

    buscar.addEventListener("input", () => {
      clearTimeout(espera);
      espera = setTimeout(() => cargar(true), 300);
    });
    chips.addEventListener("click", (ev) => {
      const chip = ev.target.closest(".pl-chip");
      if (!chip) return;
      chips.querySelectorAll(".pl-chip").forEach((c) => {
        c.classList.toggle("active", c === chip);
        c.setAttribute("aria-pressed", c === chip ? "true" : "false");
      });
      grupo = chip.dataset.valor || "";
      cargar(true);
    });
    btnMas.addEventListener("click", () => {
      pagina++;
      cargar(false);
    });
    modalEl.addEventListener("shown.bs.modal", () => buscar.focus());
    modalEl.addEventListener("hidden.bs.modal", () => {
      if (agregadosSesion) toast("success", agregadosSesion + " ejercicio" + (agregadosSesion === 1 ? " agregado" : "s agregados") + ". Ajusta series y repeticiones.");
      agregadosSesion = 0;
      contador.textContent = "";
    });

    return {
      abrir() {
        modal.show();
        if (!cargado) {
          cargado = true;
          cargar(true);
        } else {
          marcar();
        }
      },
      marcar,
    };
  }

  document.addEventListener("DOMContentLoaded", iniciar);
})();
