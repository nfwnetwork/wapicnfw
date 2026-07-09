/* ============================================================
   Rohr- & Kanalreinigung — Interaktionen
   Mobile-Nav, Scroll-Reveal, Zähler, Sticky-Header, Formular
   ============================================================ */
(function () {
  "use strict";

  /* ---------- Aktuelles Jahr im Footer ---------- */
  var yearEl = document.getElementById("year");
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());

  /* ---------- Mobile Navigation ---------- */
  var burger = document.getElementById("burger");
  var nav = document.getElementById("nav");

  function closeNav() {
    if (!nav) return;
    nav.classList.remove("is-open");
    burger.classList.remove("is-open");
    burger.setAttribute("aria-expanded", "false");
    burger.setAttribute("aria-label", "Menü öffnen");
    document.body.style.removeProperty("overflow");
  }

  if (burger && nav) {
    burger.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      burger.classList.toggle("is-open", open);
      burger.setAttribute("aria-expanded", open ? "true" : "false");
      burger.setAttribute("aria-label", open ? "Menü schließen" : "Menü öffnen");
      document.body.style.overflow = open ? "hidden" : "";
    });
    // Schließen bei Klick auf einen Link
    nav.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", closeNav);
    });
    // Schließen bei Escape
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeNav();
    });
  }

  /* ---------- Sticky-Header Schatten ---------- */
  var header = document.getElementById("header");
  function onScroll() {
    if (!header) return;
    header.classList.toggle("is-stuck", window.scrollY > 8);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Scroll-Reveal ---------- */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(
      function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-in");
            obs.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.14, rootMargin: "0px 0px -40px 0px" }
    );
    reveals.forEach(function (el, i) {
      // sanfte Staffelung innerhalb einer Gruppe
      el.style.transitionDelay = (i % 4) * 70 + "ms";
      io.observe(el);
    });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-in"); });
  }

  /* ---------- Animierte Zähler ---------- */
  function formatNumber(n) {
    return n.toLocaleString("de-DE");
  }

  function animateCount(el) {
    var target = parseInt(el.getAttribute("data-count"), 10) || 0;
    var duration = 1400;
    var start = null;

    function tick(ts) {
      if (start === null) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      // easeOutExpo
      var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
      el.textContent = formatNumber(Math.round(target * eased));
      if (progress < 1) requestAnimationFrame(tick);
      else el.textContent = formatNumber(target);
    }
    requestAnimationFrame(tick);
  }

  var counters = document.querySelectorAll(".strip__num[data-count]");
  if ("IntersectionObserver" in window && counters.length) {
    var co = new IntersectionObserver(
      function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCount(entry.target);
            obs.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.5 }
    );
    counters.forEach(function (el) { co.observe(el); });
  } else {
    counters.forEach(function (el) {
      el.textContent = formatNumber(parseInt(el.getAttribute("data-count"), 10) || 0);
    });
  }

  /* ---------- FAQ: nur eines gleichzeitig offen ---------- */
  var faqItems = document.querySelectorAll(".faq__item");
  faqItems.forEach(function (item) {
    item.addEventListener("toggle", function () {
      if (item.open) {
        faqItems.forEach(function (other) {
          if (other !== item) other.open = false;
        });
      }
    });
  });

  /* ---------- Kontaktformular (clientseitiges Feedback) ---------- */
  var form = document.getElementById("contactForm");
  var note = document.getElementById("formNote");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      // Vorherige Erfolgsmeldung zurücksetzen, damit sie nicht neben
      // Validierungsfehlern stehen bleibt.
      if (note) {
        note.textContent = "";
        note.classList.remove("is-ok");
      }
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      // Hinweis: Für den Live-Betrieb hier an Backend/Mailservice anbinden.
      if (note) {
        note.textContent = "Vielen Dank! Wir melden uns umgehend bei Ihnen zurück.";
        note.classList.add("is-ok");
      }
      form.reset();
    });
  }
})();
