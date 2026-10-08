/* =========================================================
   PowerLab · JS de las páginas públicas
   - Sombra del navbar al hacer scroll y cierre del menú móvil
   - Mostrar / ocultar contraseña
   - Medidor de fuerza y confirmación de contraseña
   - Año actual en el footer
   ========================================================= */
(function () {
  "use strict";

  function iniciarNavbar() {
    const nav = document.querySelector(".pub-nav");
    if (!nav) return;
    const actualizar = () => nav.classList.toggle("is-scrolled", window.scrollY > 8);
    actualizar();
    window.addEventListener("scroll", actualizar, { passive: true });

    // En móvil, al tocar un enlace interno se cierra el menú desplegable
    const menu = nav.querySelector(".navbar-collapse");
    nav.querySelectorAll('a[href^="#"]').forEach((enlace) => {
      enlace.addEventListener("click", () => {
        if (menu && menu.classList.contains("show") && window.bootstrap) {
          bootstrap.Collapse.getOrCreateInstance(menu).hide();
        }
      });
    });
  }

  // <button data-pub-ver-clave="#idDelInput">
  function iniciarVerClave() {
    document.querySelectorAll("[data-pub-ver-clave]").forEach((boton) => {
      const campo = document.querySelector(boton.dataset.pubVerClave);
      if (!campo) return;
      boton.addEventListener("click", () => {
        const oculto = campo.type === "password";
        campo.type = oculto ? "text" : "password";
        boton.querySelector("i").className = oculto ? "bi bi-eye-slash" : "bi bi-eye";
        boton.setAttribute("aria-label", oculto ? "Ocultar contraseña" : "Mostrar contraseña");
        campo.focus();
      });
    });
  }

  // <input data-pub-fuerza="#medidor"> ; <div id="medidor"><div class="pub-fuerza__barra"></div></div>
  function iniciarFuerzaClave() {
    const niveles = [
      { ancho: "15%", color: "var(--pl-peligro)", texto: "Muy débil" },
      { ancho: "35%", color: "var(--pl-peligro)", texto: "Débil" },
      { ancho: "60%", color: "var(--pl-alerta)", texto: "Aceptable" },
      { ancho: "80%", color: "var(--pl-exito)", texto: "Buena" },
      { ancho: "100%", color: "var(--pl-exito)", texto: "Excelente" },
    ];
    document.querySelectorAll("[data-pub-fuerza]").forEach((campo) => {
      const medidor = document.querySelector(campo.dataset.pubFuerza);
      if (!medidor) return;
      const barra = medidor.querySelector(".pub-fuerza__barra");
      const texto = document.querySelector(campo.dataset.pubFuerza + "Texto");
      campo.addEventListener("input", () => {
        const v = campo.value;
        let puntos = 0;
        if (v.length >= 6) puntos++;
        if (v.length >= 10) puntos++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) puntos++;
        if (/\d/.test(v)) puntos++;
        if (/[^A-Za-z0-9]/.test(v)) puntos++;
        const nivel = niveles[Math.min(puntos, niveles.length - 1)];
        barra.style.width = v ? nivel.ancho : "0";
        barra.style.background = nivel.color;
        if (texto) texto.textContent = v ? "Seguridad: " + nivel.texto : "";
      });
    });
  }

  // <input data-pub-igual-a="#otroCampo"> marca error si no coinciden
  function iniciarConfirmacion() {
    document.querySelectorAll("[data-pub-igual-a]").forEach((confirmar) => {
      const original = document.querySelector(confirmar.dataset.pubIgualA);
      if (!original) return;
      const validar = () => {
        confirmar.setCustomValidity(confirmar.value && confirmar.value !== original.value ? "Las contraseñas no coinciden" : "");
      };
      confirmar.addEventListener("input", validar);
      original.addEventListener("input", validar);
    });
  }

  function iniciarAnio() {
    document.querySelectorAll("[data-pub-anio]").forEach((el) => (el.textContent = new Date().getFullYear()));
  }

  document.addEventListener("DOMContentLoaded", () => {
    iniciarNavbar();
    iniciarVerClave();
    iniciarFuerzaClave();
    iniciarConfirmacion();
    iniciarAnio();
  });
})();
