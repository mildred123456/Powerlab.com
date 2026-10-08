/* =========================================================
   PowerLab · JS compartido del rol deportista
   - Toasts (incluye los mensajes flash del servidor)
   - Validación visual de formularios
   - Filtro en vivo + chips de categoría
   - Animación de entrada al hacer scroll
   - Confirmación con modal
   ========================================================= */
(function () {
  "use strict";

  const ICONOS = {
    success: "bi-check-circle-fill",
    danger: "bi-x-circle-fill",
    warning: "bi-exclamation-triangle-fill",
    info: "bi-info-circle-fill",
  };

  // Quita tildes y pasa a minúsculas para comparar textos
  function normalizar(texto) {
    return (texto || "")
      .toString()
      .normalize("NFD")
      .replace(/[̀-ͯ]/g, "")
      .toLowerCase()
      .trim();
  }

  // Muestra un toast. tipo: success | danger | warning | info
  function toast(tipo, mensaje, duracion) {
    const contenedor = document.getElementById("plToasts");
    if (!contenedor || !window.bootstrap) return;
    const tipoValido = ICONOS[tipo] ? tipo : "info";

    const el = document.createElement("div");
    el.className = "toast pl-toast pl-toast--" + tipoValido;
    el.setAttribute("role", tipoValido === "danger" ? "alert" : "status");

    const cuerpo = document.createElement("div");
    cuerpo.className = "toast-body";

    const icono = document.createElement("i");
    icono.className = "bi " + ICONOS[tipoValido];

    const texto = document.createElement("div");
    texto.className = "flex-grow-1";
    texto.textContent = mensaje; // textContent: nunca se interpreta como HTML

    const cerrar = document.createElement("button");
    cerrar.type = "button";
    cerrar.className = "btn-close btn-close-sm";
    cerrar.setAttribute("data-bs-dismiss", "toast");
    cerrar.setAttribute("aria-label", "Cerrar");

    cuerpo.append(icono, texto, cerrar);
    el.appendChild(cuerpo);
    contenedor.appendChild(el);

    const instancia = new bootstrap.Toast(el, { delay: duracion || 4500 });
    el.addEventListener("hidden.bs.toast", () => el.remove());
    instancia.show();
  }

  // Mensajes flash enviados por PHP
  function mostrarFlash() {
    const nodo = document.getElementById("plFlash");
    if (!nodo) return;
    try {
      const mensajes = JSON.parse(nodo.dataset.mensajes || "[]");
      mensajes.forEach((m, i) => setTimeout(() => toast(m.tipo, m.mensaje), i * 150));
    } catch (e) {
      /* sin mensajes */
    }
  }

  // Validación visual + evita doble envío
  function iniciarValidacion() {
    document.querySelectorAll("form.needs-validation").forEach((form) => {
      form.addEventListener("submit", (ev) => {
        if (!form.checkValidity()) {
          ev.preventDefault();
          ev.stopPropagation();
          form.classList.add("was-validated");
          const primero = form.querySelector(":invalid");
          if (primero) primero.focus();
          toast("warning", "Revisa los campos marcados en rojo.");
          return;
        }
        form.classList.add("was-validated");
        const boton = form.querySelector("[type=submit]");
        if (boton && !boton.dataset.noBloquear) {
          boton.disabled = true;
          boton.dataset.textoOriginal = boton.innerHTML;
          boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando…';
        }
      });
    });
  }

  // Filtro en vivo: <input data-pl-filtro="#lista"> + <div id="lista"><div data-pl-item data-pl-texto="..." data-pl-categoria="...">
  // Chips: <div data-pl-chips="#lista"><button class="pl-chip" data-valor="todas">…
  // Sin resultados: <div data-pl-sin-resultados="#lista">
  function iniciarFiltros() {
    const listas = new Set();
    document.querySelectorAll("[data-pl-filtro]").forEach((i) => listas.add(i.dataset.plFiltro));
    document.querySelectorAll("[data-pl-chips]").forEach((c) => listas.add(c.dataset.plChips));

    listas.forEach((selector) => {
      const lista = document.querySelector(selector);
      if (!lista) return;
      const entrada = document.querySelector('[data-pl-filtro="' + selector + '"]');
      const chips = document.querySelector('[data-pl-chips="' + selector + '"]');
      const vacio = document.querySelector('[data-pl-sin-resultados="' + selector + '"]');
      const contador = document.querySelector('[data-pl-contador="' + selector + '"]');
      let categoria = "todas";

      function aplicar() {
        const termino = normalizar(entrada ? entrada.value : "");
        const palabras = termino.split(/\s+/).filter(Boolean);
        let visibles = 0;
        lista.querySelectorAll("[data-pl-item]").forEach((item) => {
          const texto = normalizar(item.dataset.plTexto || item.textContent);
          const coincideTexto = palabras.every((p) => texto.includes(p));
          const coincideCategoria = categoria === "todas" || (item.dataset.plCategoria || "") === categoria;
          const mostrar = coincideTexto && coincideCategoria;
          item.classList.toggle("pl-oculto", !mostrar);
          if (mostrar) visibles++;
        });
        if (vacio) vacio.classList.toggle("pl-oculto", visibles > 0);
        if (contador) contador.textContent = visibles;
      }

      if (entrada) entrada.addEventListener("input", aplicar);
      if (chips) {
        chips.addEventListener("click", (ev) => {
          const chip = ev.target.closest(".pl-chip");
          if (!chip) return;
          chips.querySelectorAll(".pl-chip").forEach((c) => {
            c.classList.toggle("active", c === chip);
            c.setAttribute("aria-pressed", c === chip ? "true" : "false");
          });
          categoria = chip.dataset.valor || "todas";
          aplicar();
        });
      }
      aplicar();
    });
  }

  // Animación de entrada escalonada
  function iniciarReveal() {
    const elementos = document.querySelectorAll(".pl-reveal");
    if (!("IntersectionObserver" in window)) {
      elementos.forEach((el) => el.classList.add("is-visible"));
      return;
    }
    const observador = new IntersectionObserver(
      (entradas) => {
        entradas.forEach((entrada) => {
          if (entrada.isIntersecting) {
            entrada.target.classList.add("is-visible");
            observador.unobserve(entrada.target);
          }
        });
      },
      { threshold: 0.08 }
    );
    elementos.forEach((el, i) => {
      el.style.transitionDelay = Math.min(i * 60, 360) + "ms";
      observador.observe(el);
    });
  }

  // Confirmación con modal: <form data-pl-confirmar="¿Seguro?">
  function iniciarConfirmaciones() {
    let modalEl = null;
    let formPendiente = null;

    function obtenerModal() {
      if (modalEl) return modalEl;
      modalEl = document.createElement("div");
      modalEl.className = "modal fade";
      modalEl.tabIndex = -1;
      modalEl.innerHTML =
        '<div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 rounded-4">' +
        '<div class="modal-body p-4 text-center">' +
        '<div class="pl-vacio__icono mb-3"><i class="bi bi-question-lg"></i></div>' +
        '<p class="fw-semibold mb-4" data-pl-texto-confirmar></p>' +
        '<div class="d-flex gap-2 justify-content-center">' +
        '<button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancelar</button>' +
        '<button type="button" class="btn btn-pl px-4" data-pl-aceptar>Confirmar</button>' +
        "</div></div></div></div>";
      document.body.appendChild(modalEl);
      modalEl.querySelector("[data-pl-aceptar]").addEventListener("click", () => {
        if (formPendiente) {
          formPendiente.dataset.plConfirmado = "1";
          bootstrap.Modal.getInstance(modalEl).hide();
          formPendiente.requestSubmit ? formPendiente.requestSubmit() : formPendiente.submit();
        }
      });
      return modalEl;
    }

    document.addEventListener(
      "submit",
      (ev) => {
        const form = ev.target;
        if (!form.matches("[data-pl-confirmar]") || form.dataset.plConfirmado === "1") return;
        ev.preventDefault();
        ev.stopImmediatePropagation();
        formPendiente = form;
        const modal = obtenerModal();
        modal.querySelector("[data-pl-texto-confirmar]").textContent = form.dataset.plConfirmar;
        bootstrap.Modal.getOrCreateInstance(modal).show();
      },
      true
    );
  }

  // Rellena una tarjeta de ejercicio (clonada de <template id="plPlantillaEjercicio">) con los datos de
  // ejercicios::tarjeta(). Solo usa textContent / atributos: nunca interpreta HTML.
  function llenarTarjetaEjercicio(raiz, t, enlace) {
    const q = (campo) => raiz.querySelector('[data-ej="' + campo + '"]') || (raiz.matches('[data-ej="' + campo + '"]') ? raiz : null);
    const tono = "pl-tono-" + (parseInt(t.grupo_tono, 10) || 1);
    const enlaceEl = q("enlace");
    if (enlace !== false && enlaceEl && enlaceEl.tagName === "A") enlaceEl.href = "ejercicio-detalle.php?id=" + encodeURIComponent(t.id);

    const visual = q("visual");
    visual.className = visual.className.replace(/\bpl-tono-\d\b/, tono);
    const img = q("img");
    const icono = q("icono");
    const src = t.imagen || t.respaldo || "";
    img.alt = t.nombre || "";
    if (src) {
      img.src = src;
      img.hidden = false;
      icono.hidden = true;
    } else {
      img.removeAttribute("src");
      img.hidden = true;
      icono.hidden = false;
    }
    const claseIcono = /^bi-[a-z0-9-]+$/.test(t.grupo_icono || "") ? t.grupo_icono : "bi-collection";
    icono.className = "pl-ej-visual__icono bi " + claseIcono;
    q("video").hidden = !t.video;

    const grupo = q("grupo");
    grupo.className = grupo.className.replace(/\bpl-tono-\d\b/, tono);
    grupo.textContent = t.grupo || "";
    q("nombre").textContent = t.nombre || "";
    q("musculo").textContent = t.musculo || "";
    q("equipo").textContent = t.equipamiento || "";
    const nivel = q("nivel");
    nivel.className = "pl-nivel pl-nivel--" + String(t.nivel_clave || "").replace(/[^a-z]/g, "");
    nivel.textContent = t.nivel || "";
    return raiz;
  }

  // Si una imagen de ejercicio no carga (archivo borrado), se muestra el ícono del grupo
  function iniciarRespaldoImagenes() {
    document.addEventListener(
      "error",
      (ev) => {
        const img = ev.target;
        if (!(img instanceof HTMLImageElement) || !img.matches('[data-ej="img"]')) return;
        img.hidden = true;
        const icono = img.parentElement && img.parentElement.querySelector('[data-ej="icono"]');
        if (icono) icono.hidden = false;
      },
      true
    );
  }

  document.addEventListener("DOMContentLoaded", () => {
    mostrarFlash();
    iniciarValidacion();
    iniciarFiltros();
    iniciarReveal();
    iniciarConfirmaciones();
    iniciarRespaldoImagenes();
  });

  // API pública para los scripts de cada módulo
  window.PowerLab = { toast, normalizar, llenarTarjetaEjercicio };
})();
