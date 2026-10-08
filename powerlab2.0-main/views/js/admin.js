/* =========================================================
   PowerLab · Panel del administrador
   - Filtros del listado que se aplican solos
   - Vista previa en vivo de la tarjeta del ejercicio
     (imagen propia > imagen del equipo > respaldo del grupo > color e ícono)
   - Modales de catálogos (nuevo / editar, con imagen opcional)
   ========================================================= */
(function () {
  "use strict";

  const toast = (tipo, msg) => window.PowerLab && window.PowerLab.toast(tipo, msg);

  /* ---------- Listado: filtros ---------- */
  function iniciarFiltros() {
    const form = document.querySelector("[data-admin-filtros]");
    if (!form) return;
    const enviar = () => (form.requestSubmit ? form.requestSubmit() : form.submit());
    form.querySelectorAll("[data-admin-auto]").forEach((s) => s.addEventListener("change", enviar));

    const buscar = form.querySelector("[data-admin-buscar]");
    let espera;
    buscar.addEventListener("input", () => {
      clearTimeout(espera);
      espera = setTimeout(enviar, 700);
    });
    // Tras recargar por una búsqueda, el cursor vuelve al final del texto
    if (new URLSearchParams(location.search).has("q")) {
      buscar.focus();
      buscar.setSelectionRange(buscar.value.length, buscar.value.length);
    }
  }

  /* ---------- Formulario de ejercicio ---------- */
  function iniciarFormulario() {
    const form = document.querySelector("[data-admin-form]");
    if (!form) return;

    const tarjeta = form.querySelector("[data-previa-tarjeta] .pl-ej-card");
    const campo = (nombre) => form.querySelector('[data-previa="' + nombre + '"]');
    const archivo = form.querySelector("[data-imagen-archivo]");
    const quitar = form.querySelector("[data-quitar-imagen]");
    const imagenActual = form.querySelector("[data-imagen-actual]");
    let urlArchivo = null; // objectURL de la imagen elegida

    function textoSeleccion(select, porDefecto) {
      const op = select.selectedOptions[0];
      return op && op.value ? op.textContent.replace(/\s*\(inactivo\)$/, "") : porDefecto;
    }

    function actualizarPrevia() {
      const grupo = campo("grupo").selectedOptions[0];
      const equipo = campo("equipo").selectedOptions[0];
      const nivel = campo("nivel").selectedOptions[0];
      const imagen = urlArchivo || (!imagenActual.hidden && !quitar.checked ? imagenActual.getAttribute("src") : null);
      window.PowerLab.llenarTarjetaEjercicio(
        tarjeta,
        {
          nombre: campo("nombre").value.trim() || "Nombre del ejercicio",
          grupo: textoSeleccion(campo("grupo"), "Grupo"),
          grupo_icono: (grupo && grupo.dataset.icono) || "bi-collection",
          grupo_tono: (grupo && grupo.dataset.tono) || 1,
          musculo: campo("musculo").value.trim() || "Músculo principal",
          equipamiento: textoSeleccion(campo("equipo"), "Equipamiento"),
          nivel: textoSeleccion(campo("nivel"), "Nivel"),
          nivel_clave: (nivel && nivel.dataset.clave) || "principiante",
          imagen: imagen,
          respaldo: (equipo && equipo.dataset.imagen) || (grupo && grupo.dataset.respaldo) || null,
          video: campo("video").value.trim() !== "" && campo("video").checkValidity(),
        },
        false
      );
    }

    form.querySelectorAll("[data-previa]").forEach((el) => el.addEventListener("input", actualizarPrevia));
    form.querySelectorAll("select[data-previa]").forEach((el) => el.addEventListener("change", actualizarPrevia));
    quitar.addEventListener("change", actualizarPrevia);

    // Imagen propia (opcional): vista previa inmediata y validación básica en el navegador
    archivo.addEventListener("change", () => {
      if (urlArchivo) URL.revokeObjectURL(urlArchivo);
      urlArchivo = null;
      const f = archivo.files[0];
      if (f) {
        if (!["image/jpeg", "image/png", "image/webp"].includes(f.type) || f.size > 2 * 1024 * 1024) {
          archivo.value = "";
          toast("warning", "Usa una imagen JPG, PNG o WEBP de máximo 2 MB.");
        } else {
          urlArchivo = URL.createObjectURL(f);
        }
      }
      actualizarPrevia();
    });

    // Contador de caracteres de la descripción
    form.querySelectorAll("[data-contador]").forEach((el) => {
      const salida = form.querySelector('[data-contador-de="' + el.dataset.contador + '"]');
      const pintar = () => salida && (salida.textContent = el.value.length + " / " + el.maxLength);
      el.addEventListener("input", pintar);
      pintar();
    });

    // Instrucciones: mínimo de pasos (líneas no vacías)
    form.querySelectorAll("[data-min-lineas]").forEach((el) => {
      const validar = () => {
        const lineas = el.value.split(/\r?\n/).filter((l) => l.trim()).length;
        el.setCustomValidity(lineas >= parseInt(el.dataset.minLineas, 10) ? "" : "Escribe al menos " + el.dataset.minLineas + " pasos.");
      };
      el.addEventListener("input", validar);
      validar();
    });

    actualizarPrevia();
  }

  /* ---------- Catálogos ---------- */
  function iniciarCatalogos() {
    const modales = { grupo: document.getElementById("modalGrupo"), equipo: document.getElementById("modalEquipo") };
    if (!modales.grupo && !modales.equipo) return;

    // Imagen actual del registro (respaldo del grupo o foto del equipo) y casilla «Quitar»
    function pintarImagen(form, src) {
      const actual = form.querySelector("[data-catalogo-imagen-actual]");
      actual.replaceChildren();
      if (src) {
        const img = document.createElement("img");
        img.src = src;
        img.alt = "Imagen actual";
        actual.appendChild(img);
      } else {
        const vacio = document.createElement("span");
        vacio.className = "text-body-tertiary small";
        vacio.textContent = actual.dataset.vacio;
        actual.appendChild(vacio);
      }
      form.querySelector("[data-catalogo-quitar]").hidden = !src;
    }

    function abrir(tipo, registro) {
      const modalEl = modales[tipo];
      const form = modalEl.querySelector("form");
      form.reset();
      form.classList.remove("was-validated");
      form.querySelector("[name=id]").value = registro ? registro.id : "";
      form.querySelector("[name=nombre]").value = registro ? registro.nombre : "";
      form.querySelector("[name=orden]").value = registro ? registro.orden : 0;
      modalEl.querySelector(".modal-title").textContent = (registro ? "Editar " : "Nuevo ") + (tipo === "grupo" ? "grupo muscular" : "equipamiento");
      pintarImagen(form, registro && registro.imagen);

      if (tipo === "grupo") {
        form.querySelector("[name=palabras_clave]").value = registro ? registro.palabras_clave || "" : "";
        const tono = form.querySelector('[name=tono][value="' + (registro ? registro.tono : 1) + '"]');
        if (tono) tono.checked = true;
        const icono = [...form.querySelectorAll("[name=icono]")].find((i) => i.value === (registro ? registro.icono : ""));
        (icono || form.querySelector("[name=icono]")).checked = true;
      }
      const boton = form.querySelector("[type=submit]");
      if (boton.dataset.textoOriginal) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.textoOriginal;
      }
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.querySelectorAll("[data-catalogo-nuevo]").forEach((b) => b.addEventListener("click", () => abrir(b.dataset.catalogoNuevo)));
    document.querySelectorAll("[data-catalogo-editar]").forEach((b) =>
      b.addEventListener("click", () => abrir(b.dataset.catalogoEditar, JSON.parse(b.dataset.registro)))
    );
  }

  document.addEventListener("DOMContentLoaded", () => {
    iniciarFiltros();
    iniciarFormulario();
    iniciarCatalogos();
  });
})();
