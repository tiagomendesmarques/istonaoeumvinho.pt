/*
  Google Analytics 4 com consentimento (RGPD).
  - Só carrega o Google Analytics depois de a pessoa clicar "Aceitar" no banner de cookies.
  - A escolha fica no localStorage ("consentimento-analytics": "sim" | "nao").
  - O ID (G-XXXXXXX) está em config.js → googleAnalytics. Vazio = sem banner e sem Analytics.
  API: Analytics.iniciar(), Analytics.evento(nome, parametros), Analytics.preferencias()
*/
(function () {
  "use strict";
  var S = window.SITE || {};
  var ID = String(S.googleAnalytics || "").trim();
  var CHAVE = "consentimento-analytics";
  var carregado = false;
  var iniciado = false;
  var banner = null;

  function ler() { try { return localStorage.getItem(CHAVE); } catch (e) { return null; } }
  function guardar(v) { try { localStorage.setItem(CHAVE, v); } catch (e) {} }

  function carregar() {
    if (carregado || !ID) return;
    carregado = true;
    window["ga-disable-" + ID] = false;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag("consent", "default", {
      analytics_storage: "granted",
      ad_storage: "denied",
      ad_user_data: "denied",
      ad_personalization: "denied"
    });
    window.gtag("js", new Date());
    window.gtag("config", ID, { allow_google_signals: false, allow_ad_personalization_signals: false });
    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(ID);
    document.head.appendChild(s);
  }

  function apagarCookies() {
    var host = location.hostname;
    var raiz = host.split(".").slice(-2).join(".");
    document.cookie.split(";").forEach(function (c) {
      var nome = c.split("=")[0].trim();
      if (!/^_ga/.test(nome)) return;
      ["", "; domain=" + host, "; domain=." + host, "; domain=." + raiz].forEach(function (d) {
        document.cookie = nome + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/" + d;
      });
    });
  }

  function escolher(aceita) {
    guardar(aceita ? "sim" : "nao");
    esconder();
    if (aceita) {
      carregar();
      if (window.gtag) window.gtag("consent", "update", { analytics_storage: "granted" });
    } else {
      window["ga-disable-" + ID] = true;
      if (window.gtag) window.gtag("consent", "update", { analytics_storage: "denied" });
      apagarCookies();
    }
  }

  function criarBanner() {
    var el = document.createElement("div");
    el.className = "cookies";
    el.setAttribute("role", "dialog");
    el.setAttribute("aria-live", "polite");
    el.setAttribute("aria-label", "Cookies");
    el.innerHTML =
      '<div class="cookies-in">' +
        '<p><strong>Um brinde às estatísticas?</strong> Usamos cookies do Google Analytics para perceber como o site é visitado. ' +
        'Só com a tua autorização. <a href="politica-privacidade.html#cookies">Saber mais</a></p>' +
        '<div class="cookies-btns">' +
          '<button type="button" class="btn btn-outline" data-escolha="nao">Recusar</button>' +
          '<button type="button" class="btn" data-escolha="sim">Aceitar</button>' +
        '</div>' +
      '</div>';
    el.addEventListener("click", function (ev) {
      var b = ev.target.closest("[data-escolha]");
      if (b) escolher(b.getAttribute("data-escolha") === "sim");
    });
    document.body.appendChild(el);
    return el;
  }

  function mostrar() {
    if (!ID) return;
    if (!banner) banner = criarBanner();
    banner.hidden = false;
    var b = banner.querySelector('[data-escolha="sim"]');
    if (b) b.focus({ preventScroll: true });
  }
  function esconder() { if (banner) banner.hidden = true; }

  function iniciar() {
    if (iniciado || !ID) return;
    iniciado = true;
    var v = ler();
    if (v === "sim") carregar();
    else if (v !== "nao") mostrar();
  }

  // Link "Cookies" no rodapé para mudar de ideias
  document.addEventListener("click", function (ev) {
    var l = ev.target.closest("[data-cookies]");
    if (!l) return;
    ev.preventDefault();
    mostrar();
  });
  if (ID) {
    document.querySelectorAll("[data-cookies]").forEach(function (l) { l.hidden = false; });
  }

  window.Analytics = {
    iniciar: iniciar,
    preferencias: mostrar,
    evento: function (nome, params) {
      if (carregado && window.gtag) window.gtag("event", nome, params || {});
    }
  };

  // Páginas sem a entrada 18+ (ex.: política de privacidade) começam logo
  if (!document.body.classList.contains("bloqueado")) iniciar();
})();
