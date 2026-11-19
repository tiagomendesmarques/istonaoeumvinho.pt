(function () {
  "use strict";
  var S = window.SITE || {};
  var $ = function (id) { return document.getElementById(id); };
  var eur = function (n) {
    return n.toLocaleString("pt-PT", { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }) + "€";
  };

  /* ---------- Entrada 18+ ---------- */
  var gate = $("idade");
  var ok = false;
  try { ok = localStorage.getItem("idade-ok") === "1"; } catch (e) {}
  var A = window.Analytics || { iniciar: function () {}, evento: function () {} };
  function abrirSite() {
    gate.hidden = true;
    document.body.classList.remove("bloqueado");
    A.iniciar(); // banner de cookies só depois da entrada 18+
  }

  // Cliques nos botões de encomendar (menu, hero)
  document.querySelectorAll('a[href="#encomendar"]').forEach(function (a) {
    a.addEventListener("click", function () {
      A.evento("clique_encomendar", { local: a.closest(".nav") ? "menu" : "hero" });
    });
  });
  if (ok) abrirSite(); else $("idade-sim").focus();
  $("idade-sim").addEventListener("click", function () {
    try { localStorage.setItem("idade-ok", "1"); } catch (e) {}
    abrirSite();
  });
  $("idade-nao").addEventListener("click", function () { $("idade-nao-msg").hidden = false; });

  /* ---------- Configuração ---------- */
  var total = typeof S.total === "number" ? S.total : 700;
  var restam = typeof S.restam === "number" ? S.restam : total;
  document.querySelectorAll("[data-total]").forEach(function (el) { el.textContent = total; });
  if (S.instagram) $("link-instagram").href = S.instagram;
  if (typeof S.preco === "number") $("preco").textContent = eur(S.preco) + " a garrafa";

  // Contador anima de "total" até "restam" quando aparece no ecrã
  var num = $("restam");
  num.textContent = restam;
  if ("IntersectionObserver" in window && !matchMedia("(prefers-reduced-motion: reduce)").matches && total > restam) {
    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      var t0 = null, dur = 1400;
      (function step(t) {
        if (!t0) t0 = t;
        var p = Math.min((t - t0) / dur, 1);
        var e = 1 - Math.pow(1 - p, 3);
        num.textContent = Math.round(total - (total - restam) * e);
        if (p < 1) requestAnimationFrame(step);
      })(performance.now());
    }, { threshold: 0.4 });
    io.observe(num);
  }

  /* Cabeçalho escondido no topo, para não aparecerem dois logos: entra quando o logo grande sai do ecrã */
  var header = document.querySelector(".header");
  var logoGrande = document.querySelector(".hero-logo");
  if (header && logoGrande && "IntersectionObserver" in window) {
    header.style.transition = "none"; // esconde logo à entrada, sem animação
    header.classList.add("header-auto");
    requestAnimationFrame(function () { requestAnimationFrame(function () { header.style.transition = ""; }); });
    new IntersectionObserver(function (entries) {
      var e = entries[0];
      header.classList.toggle("visivel", !e.isIntersecting && e.boundingClientRect.top < 0);
    }, { rootMargin: "-" + header.offsetHeight + "px 0px 0px 0px" }).observe(logoGrande);
  }

  /* Parallax da textura do vinho: a imagem desce mais devagar do que a página e aproxima-se ligeiramente */
  var tex = $("vinho-textura");
  if (tex && !matchMedia("(prefers-reduced-motion: reduce)").matches) {
    var sec = tex.closest(".vinho");
    var pedido = false;
    var mover = function () {
      pedido = false;
      var r = sec.getBoundingClientRect();
      var vh = window.innerHeight;
      if (r.bottom < -100 || r.top > vh + 100) return;
      var p = (vh - r.top) / (vh + r.height);            // 0 quando entra por baixo, 1 quando sai por cima
      p = Math.max(0, Math.min(1, p));
      var y = (p - 0.5) * r.height * 0.55;                // até ±27% da altura da secção
      var s = 1.12 - p * 0.08;                            // 1.12 -> 1.04
      tex.style.transform = "translate3d(0," + y.toFixed(1) + "px,0) scale(" + s.toFixed(3) + ")";
    };
    var agendar = function () { if (!pedido) { pedido = true; requestAnimationFrame(mover); } };
    window.addEventListener("scroll", agendar, { passive: true });
    window.addEventListener("resize", agendar);
    mover();
  }

  var form = $("form");
  if (restam <= 0) {
    $("encomenda-txt").textContent = "Acabou mesmo. Este foi o primeiro duma série.";
    form.remove();
    return;
  }

  /* ---------- Formulário ---------- */
  var qtd = $("quantidade");
  var campoMorada = $("campo-morada");
  var msg = $("form-msg");
  var btn = $("enviar");
  var btnTxt = btn.textContent;

  function envio() { return form.entrega.value === "Envio para morada"; }

  function atualizarTotal() {
    var el = $("total-encomenda");
    var n = parseInt(qtd.value, 10);
    if (typeof S.preco === "number" && !isNaN(n)) {
      el.hidden = false;
      el.textContent = "Total: " + eur(S.preco * n) + (envio() ? " + envio" : "");
    } else {
      el.hidden = true;
    }
  }
  function atualizarEntrega() {
    campoMorada.hidden = !envio();
    $("info-recolha").hidden = envio();
    form.morada.required = envio();
    atualizarTotal();
  }
  qtd.addEventListener("change", atualizarTotal);
  Array.prototype.forEach.call(form.entrega, function (r) { r.addEventListener("change", atualizarEntrega); });
  atualizarEntrega();

  function marcarErro(input, texto) {
    var wrap = input.closest(".field, .check");
    if (!wrap) return;
    wrap.classList.toggle("invalid", !!texto);
    var e = wrap.querySelector(".err");
    if (e) e.textContent = texto || "";
  }

  function validar() {
    var primeiro = null;
    function v(input, texto) { marcarErro(input, texto); if (texto && !primeiro) primeiro = input; }
    var f = form;
    v(f.nome, f.nome.value.trim().length >= 2 ? "" : "Falta o teu nome.");
    v(f.email, /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.value.trim()) ? "" : "Este email não parece certo.");
    v(f.telemovel, f.telemovel.value.replace(/\D/g, "").length >= 9 ? "" : "Falta o teu telemóvel.");
    v(f.morada, !envio() || f.morada.value.trim().length >= 5 ? "" : "Falta a morada de entrega.");
    var nif = f.nif.value.trim();
    v(f.nif, !nif || /^\d{9}$/.test(nif) ? "" : "O NIF tem 9 algarismos.");
    v(f.maioridade, f.maioridade.checked ? "" : "x");
    v(f.privacidade, f.privacidade.checked ? "" : "x");

    msg.className = "form-msg full";
    msg.textContent = "";
    if (primeiro === f.maioridade) mostrarErro("Confirma que tens 18 anos ou mais.");
    else if (primeiro === f.privacidade) mostrarErro("Precisamos que aceites ser contactado para confirmar a encomenda.");
    if (primeiro) primeiro.focus();
    return !primeiro;
  }

  function mostrarErro(texto) {
    msg.className = "form-msg full erro";
    msg.textContent = texto;
  }

  // limpa o erro de um campo assim que é corrigido
  form.addEventListener("input", function (ev) {
    var w = ev.target.closest(".field.invalid, .check.invalid");
    if (w) marcarErro(ev.target, "");
  });
  form.addEventListener("change", function (ev) {
    if (ev.target.type === "checkbox" && ev.target.checked) marcarErro(ev.target, "");
  });

  function sucesso() {
    var n = parseInt(qtd.value, 10);
    var ev = { quantidade: qtd.value, entrega: form.entrega.value, currency: "EUR" };
    if (typeof S.preco === "number" && !isNaN(n)) ev.value = S.preco * n;
    A.evento("generate_lead", ev);
    form.hidden = true;
    var s = $("sucesso");
    s.hidden = false;
    s.focus();
  }

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    if (form.website.value) return; // bot
    if (!validar()) return;

    var dados = new FormData(form);
    if (!envio()) dados.delete("morada");

    btn.disabled = true;
    btn.textContent = "A enviar…";

    fetch(S.formEndpoint, { method: "POST", body: dados, headers: { Accept: "application/json" } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok || !j.ok) throw new Error(j.erro || "HTTP " + r.status);
          sucesso();
        });
      })
      .catch(function (err) {
        var detalhe = err && err.message && !/^HTTP|Failed|NetworkError|Load failed/i.test(err.message) ? err.message + " " : "";
        mostrarErro(detalhe + "Não conseguimos enviar. Tenta outra vez ou escreve para " + (S.emailEncomendas || "") + ".");
        btn.disabled = false;
        btn.textContent = btnTxt;
      });
  });
})();
