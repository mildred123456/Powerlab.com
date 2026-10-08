/* =========================================================
   PowerLab · Mis medidas
   - Gráfica de evolución (Chart.js) con selector de medida
   - Modal para registrar una medición nueva o corregir una existente
   ========================================================= */
(function () {
  "use strict";

  const COLOR_LINEA = "#d9590f"; // naranja de marca oscurecido (contraste ≥ 3:1 sobre blanco)
  const formato = new Intl.NumberFormat("es-CO", { maximumFractionDigits: 2 });
  const sinAnimacion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function conSigno(valor, unidad) {
    if (Math.abs(valor) < 0.005) return "= 0 " + unidad;
    return (valor > 0 ? "+" : "−") + formato.format(Math.abs(valor)) + " " + unidad;
  }

  /* ---------- Gráfica ---------- */
  function iniciarGrafica() {
    const contenedor = document.querySelector("[data-grafica]");
    const canvas = document.getElementById("graficaMedidas");
    const selector = document.querySelector("[data-grafica-selector]");
    if (!contenedor || !canvas || !selector || !window.Chart) return;

    const datos = JSON.parse(contenedor.dataset.grafica);
    const resumen = document.querySelector("[data-grafica-resumen]");
    const aviso = document.querySelector("[data-grafica-aviso]");
    const fuente = getComputedStyle(document.body).fontFamily;

    Chart.defaults.font.family = fuente;
    Chart.defaults.color = "#6b7280";

    // Línea vertical que sigue al cursor (crosshair)
    const crosshair = {
      id: "plCrosshair",
      afterDatasetsDraw(chart) {
        const activos = chart.tooltip && chart.tooltip.getActiveElements();
        if (!activos || !activos.length) return;
        const x = activos[0].element.x;
        const { top, bottom } = chart.chartArea;
        const ctx = chart.ctx;
        ctx.save();
        ctx.strokeStyle = "rgba(21, 23, 28, 0.25)";
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.beginPath();
        ctx.moveTo(x, top);
        ctx.lineTo(x, bottom);
        ctx.stroke();
        ctx.restore();
      },
    };

    // Etiqueta directa solo en el último punto
    const etiquetaFinal = {
      id: "plEtiquetaFinal",
      afterDatasetsDraw(chart) {
        const meta = chart.getDatasetMeta(0);
        const ultimo = meta.data[meta.data.length - 1];
        if (!ultimo) return;
        const serie = chart.data.datasets[0];
        const texto = formato.format(serie.data[serie.data.length - 1]) + " " + serie.unidad;
        // Si la línea llega bajando, la etiqueta va debajo del punto para no taparla
        const previo = meta.data[meta.data.length - 2];
        const debajo = previo && previo.y < ultimo.y && ultimo.y + 28 < chart.chartArea.bottom;
        const ctx = chart.ctx;
        ctx.save();
        ctx.font = "700 12px " + fuente;
        ctx.fillStyle = "#15171c";
        ctx.textAlign = "right";
        ctx.textBaseline = debajo ? "top" : "bottom";
        const x = ultimo.x - 4;
        const y = debajo ? ultimo.y + 10 : ultimo.y - 10;
        // Fondo blanco detrás del texto para que se lea aunque cruce la línea
        const ancho = ctx.measureText(texto).width;
        ctx.fillStyle = "rgba(255, 255, 255, 0.9)";
        ctx.fillRect(x - ancho - 4, debajo ? y - 2 : y - 16, ancho + 8, 18);
        ctx.fillStyle = "#15171c";
        ctx.fillText(texto, x, y);
        ctx.restore();
      },
    };

    const grafica = new Chart(canvas, {
      type: "line",
      data: { labels: [], datasets: [] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: sinAnimacion ? false : { duration: 500 },
        layout: { padding: { top: 24, right: 8 } },
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: { display: false }, // una sola serie: el selector la nombra
          tooltip: {
            backgroundColor: "#15171c",
            padding: 10,
            cornerRadius: 8,
            displayColors: false,
            titleFont: { weight: "700" },
            callbacks: {
              label(item) {
                const serie = item.dataset;
                return serie.etiqueta + ": " + formato.format(item.parsed.y) + " " + serie.unidad;
              },
              afterLabel(item) {
                if (item.dataIndex === 0) return "Primera medición";
                const previo = item.dataset.data[item.dataIndex - 1];
                return "Vs. anterior: " + conSigno(item.parsed.y - previo, item.dataset.unidad);
              },
            },
          },
        },
        scales: {
          x: { grid: { display: false }, border: { color: "#e7e8ec" }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
          y: {
            grid: { color: "#eef0f3" },
            border: { display: false },
            ticks: { padding: 8, callback: (v) => formato.format(v) },
            grace: "10%",
          },
        },
      },
      plugins: [crosshair, etiquetaFinal],
    });

    function mostrar(campo) {
      const info = datos.campos[campo];
      const puntos = datos.series[campo] || [];
      const ctx = canvas.getContext("2d");
      const degradado = ctx.createLinearGradient(0, 0, 0, canvas.clientHeight || 300);
      degradado.addColorStop(0, "rgba(242, 106, 27, 0.18)");
      degradado.addColorStop(1, "rgba(242, 106, 27, 0)");

      grafica.data.labels = puntos.map((p) => p.fecha);
      grafica.data.datasets = [
        {
          etiqueta: info.etiqueta,
          unidad: info.unidad,
          data: puntos.map((p) => p.valor),
          borderColor: COLOR_LINEA,
          backgroundColor: degradado,
          fill: true,
          borderWidth: 2,
          tension: 0.3,
          pointRadius: 4,
          pointHoverRadius: 6,
          pointBackgroundColor: COLOR_LINEA,
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointHitRadius: 14,
        },
      ];
      grafica.update();

      // Resumen textual (también sirve a lectores de pantalla)
      if (puntos.length) {
        const ultimo = puntos[puntos.length - 1];
        let texto = info.etiqueta + ": <strong>" + formato.format(ultimo.valor) + " " + info.unidad + "</strong>";
        if (puntos.length > 1) {
          texto += " · cambio total <strong>" + conSigno(ultimo.valor - puntos[0].valor, info.unidad) + "</strong> desde el " + puntos[0].fecha;
        }
        resumen.innerHTML = texto; // los datos vienen del servidor como números y etiquetas fijas
      } else {
        resumen.textContent = "Sin datos para esta medida.";
      }
      if (aviso) aviso.classList.toggle("pl-oculto", puntos.length > 1);
    }

    selector.addEventListener("change", () => mostrar(selector.value));
    mostrar(selector.value);
  }

  /* ---------- Modal nueva / editar ---------- */
  function iniciarModal() {
    const modalEl = document.getElementById("modalMedida");
    if (!modalEl) return;
    const form = modalEl.querySelector("form");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const titulo = modalEl.querySelector("#modalMedidaTitulo");
    const avisoEdicion = modalEl.querySelector("[data-aviso-edicion]");
    const avisoVacio = modalEl.querySelector("[data-aviso-vacio]");
    const textoGuardar = modalEl.querySelector("[data-texto-guardar]");
    const campos = [...form.querySelectorAll("[data-campo-medida]")];
    const fecha = form.querySelector("[name=fecha]");
    const id = form.querySelector("[name=id]");

    function hoy() {
      const d = new Date();
      return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
    }

    // Al menos una medida: el primer campo queda inválido si todos están vacíos
    function validarAlMenosUna() {
      const alguna = campos.some((c) => c.value.trim() !== "");
      campos[0].setCustomValidity(alguna ? "" : "Registra al menos una medida.");
      avisoVacio.classList.toggle("pl-oculto", alguna || !form.classList.contains("was-validated"));
    }

    function preparar(modo, registro) {
      form.classList.remove("was-validated");
      form.reset();
      const editar = modo === "editar";
      form.action = editar ? form.dataset.accionEditar : form.dataset.accionNueva;
      titulo.textContent = editar ? "Corregir medición" : "Nueva medición";
      textoGuardar.textContent = editar ? "Guardar cambios" : "Guardar medición";
      avisoEdicion.classList.toggle("pl-oculto", !editar);
      id.value = editar ? registro.id : "";
      fecha.value = editar ? registro.fecha : hoy();
      campos.forEach((c) => {
        const valor = editar ? registro[c.name] : null;
        c.value = valor === null || valor === undefined ? "" : parseFloat(valor);
      });
      // En una medición nueva se sugiere la última altura conocida
      if (!editar && form.dataset.alturaPrevia) {
        form.querySelector("[name=altura_cm]").value = parseFloat(form.dataset.alturaPrevia);
      }
      validarAlMenosUna();
      const boton = form.querySelector("[type=submit]");
      if (boton.dataset.textoOriginal) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.textoOriginal;
      }
      modal.show();
    }

    document.querySelectorAll("[data-medida-nueva]").forEach((b) => b.addEventListener("click", () => preparar("nueva")));
    document.querySelectorAll("[data-medida-editar]").forEach((b) =>
      b.addEventListener("click", () => preparar("editar", JSON.parse(b.dataset.medidaEditar)))
    );
    campos.forEach((c) => c.addEventListener("input", validarAlMenosUna));
    form.addEventListener("submit", validarAlMenosUna, true); // antes de la validación general
    form.addEventListener("submit", () => setTimeout(validarAlMenosUna, 0));
    modalEl.addEventListener("shown.bs.modal", () => fecha.focus());
  }

  function iniciarVerMas() {
    const boton = document.querySelector("[data-medida-ver-mas]");
    if (!boton) return;
    boton.addEventListener("click", () => {
      document.querySelectorAll("[data-medida-extra]").forEach((el) => el.classList.remove("pl-oculto"));
      boton.remove();
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    iniciarGrafica();
    iniciarModal();
    iniciarVerMas();
  });
})();
