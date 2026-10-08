/* =========================================================
   PowerLab · Biblioteca de ejercicios (deportista)
   - Buscador en vivo y filtros: consultan al servidor (JSON) con espera corta
   - Scroll infinito con botón "Cargar más" de respaldo
   - Los filtros se guardan en la URL (?q=&grupo=&equipo=&nivel=)
   ========================================================= */
(function () {
  "use strict";

  function iniciarBiblioteca() {
    const filtros = document.querySelector("[data-ej-filtros]");
    const lista = document.querySelector("[data-ej-lista]");
    const plantilla = document.getElementById("plPlantillaEjercicio");
    if (!filtros || !lista || !plantilla) return;

    const url = filtros.dataset.url;
    const buscar = filtros.querySelector("[data-ej-buscar]");
    const grupos = filtros.querySelector("[data-ej-grupos]");
    const equipo = filtros.querySelector("[data-ej-equipo]");
    const nivel = filtros.querySelector("[data-ej-nivel]");
    const contador = filtros.querySelector("[data-ej-contador]");
    const limpiarFiltros = filtros.querySelector("[data-ej-limpiar]");
    const vacio = document.querySelector("[data-ej-vacio]");
    const botonMas = document.querySelector("[data-ej-mas]");
    const cargando = document.querySelector("[data-ej-cargando]");
    const centinela = document.querySelector("[data-ej-centinela]");

    let pagina = parseInt(lista.dataset.pagina, 10) || 1;
    let paginas = parseInt(lista.dataset.paginas, 10) || 1;
    let grupoActivo = (grupos.querySelector(".pl-chip.active") || {}).dataset?.valor || "";
    let peticion = null; // AbortController de la búsqueda en curso
    let cargandoMas = false;

    function parametros(numPagina) {
      const p = new URLSearchParams();
      if (buscar.value.trim()) p.set("q", buscar.value.trim());
      if (grupoActivo) p.set("grupo", grupoActivo);
      if (equipo.value) p.set("equipo", equipo.value);
      if (nivel.value) p.set("nivel", nivel.value);
      if (numPagina) p.set("pagina", numPagina);
      return p;
    }

    function actualizarUrlYControles() {
      const p = parametros();
      history.replaceState(null, "", location.pathname + (p.toString() ? "?" + p : ""));
      limpiarFiltros.hidden = !p.toString();
    }

    function tarjeta(datos) {
      const col = document.createElement("div");
      col.className = "col-6 col-md-4 col-xl-3 pl-ej-nueva";
      const nodo = plantilla.content.firstElementChild.cloneNode(true);
      col.appendChild(window.PowerLab.llenarTarjetaEjercicio(nodo, datos));
      return col;
    }

    function pintar(respuesta, reemplazar) {
      if (reemplazar) lista.replaceChildren();
      respuesta.items.forEach((datos) => lista.appendChild(tarjeta(datos)));
      pagina = respuesta.pagina;
      paginas = respuesta.paginas;
      contador.textContent = respuesta.total;
      vacio.hidden = respuesta.total > 0;
      botonMas.hidden = pagina >= paginas;
    }

    async function consultar(numPagina, reemplazar) {
      if (reemplazar && peticion) peticion.abort();
      const control = new AbortController();
      if (reemplazar) peticion = control;
      lista.setAttribute("aria-busy", "true");
      cargando.hidden = reemplazar;
      try {
        const r = await fetch(url + "?" + parametros(numPagina), { signal: control.signal, headers: { Accept: "application/json" } });
        if (r.status === 401) {
          location.href = "inicio-de-sesion.php";
          return;
        }
        const datos = await r.json();
        if (!r.ok || !datos.ok) throw new Error(datos.mensaje || "Error");
        pintar(datos, reemplazar);
      } catch (e) {
        if (e.name !== "AbortError") window.PowerLab.toast("danger", "No pudimos cargar los ejercicios. Revisa tu conexión.");
      } finally {
        if (!reemplazar || peticion === control) {
          lista.setAttribute("aria-busy", "false");
          cargando.hidden = true;
        }
      }
    }

    function nuevaBusqueda() {
      actualizarUrlYControles();
      consultar(1, true);
    }

    async function cargarMas() {
      if (cargandoMas || pagina >= paginas) return;
      cargandoMas = true;
      await consultar(pagina + 1, false);
      cargandoMas = false;
    }

    function marcarGrupo(valor) {
      grupoActivo = valor;
      grupos.querySelectorAll(".pl-chip").forEach((chip) => {
        const activo = chip.dataset.valor === valor;
        chip.classList.toggle("active", activo);
        chip.setAttribute("aria-pressed", activo ? "true" : "false");
      });
    }

    let espera;
    buscar.addEventListener("input", () => {
      clearTimeout(espera);
      espera = setTimeout(nuevaBusqueda, 250);
    });
    grupos.addEventListener("click", (ev) => {
      const chip = ev.target.closest(".pl-chip");
      if (!chip) return;
      marcarGrupo(chip.dataset.valor);
      nuevaBusqueda();
    });
    equipo.addEventListener("change", nuevaBusqueda);
    nivel.addEventListener("change", nuevaBusqueda);
    document.querySelectorAll("[data-ej-limpiar]").forEach((b) =>
      b.addEventListener("click", () => {
        buscar.value = "";
        equipo.value = "";
        nivel.value = "";
        marcarGrupo("");
        nuevaBusqueda();
        buscar.focus();
      })
    );
    botonMas.addEventListener("click", cargarMas);

    // Scroll infinito: al acercarse al final se carga la siguiente página
    if ("IntersectionObserver" in window && centinela) {
      new IntersectionObserver((entradas) => entradas[0].isIntersecting && cargarMas(), { rootMargin: "600px 0px" }).observe(centinela);
    }

    limpiarFiltros.hidden = !parametros().toString();
  }

  // "← Ejercicios" en el detalle: si venimos de la biblioteca, volver conserva filtros y posición
  function iniciarVolver() {
    const volver = document.querySelector("[data-ej-volver]");
    if (!volver) return;
    volver.addEventListener("click", (ev) => {
      try {
        if (document.referrer && new URL(document.referrer).pathname.endsWith("/ejercicios.php")) {
          ev.preventDefault();
          history.back();
        }
      } catch (e) {
        /* referrer inválido: se sigue el enlace normal */
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    iniciarBiblioteca();
    iniciarVolver();
  });
})();
