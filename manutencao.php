<?php
/*
  Porta de entrada das páginas HTML enquanto o site está em manutenção.
  O .htaccess encaminha para aqui "/", "/index.html" e "/politica-privacidade.html" quando MANUTENCAO=1.
  Com o cookie válido serve a página pedida; sem ele mostra a wall "O site está a fermentar".
*/
declare(strict_types=1);
require __DIR__ . '/api/gate.php';

$paginas = ['index.html', 'politica-privacidade.html'];
$pagina = (string)($_GET['__p'] ?? 'index.html');
if (!in_array($pagina, $paginas, true)) $pagina = 'index.html';

if (gate_ok()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile(__DIR__ . '/' . $pagina);
    exit;
}

$erro = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!gate_tentativa_permitida()) {
        $erro = 'Calma, que o vinho não foge. Tenta outra vez daqui a uns minutos.';
    } elseif (gate_palavra_certa(trim((string)($_POST['palavra'] ?? '')))) {
        gate_entrar();
        $destino = (string)strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $destino = '/' . ltrim($destino, '/');   // nunca sai do próprio site
        header('Location: ' . $destino, true, 303);
        exit;
    } else {
        $erro = 'Ainda não está no ponto. Essa palavra-passe não abre a garrafa.';
    }
}

http_response_code(503);
header('Retry-After: 86400');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
$e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#FFFFFF">
<title>Em fermentação | Isto não é um Vinho</title>
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<link rel="preload" href="/assets/fonts/instrument-serif-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:"Instrument Serif";src:url("/assets/fonts/instrument-serif-latin-400-normal.woff2") format("woff2");font-weight:400;font-display:swap}
@font-face{font-family:"Inter";src:url("/assets/fonts/inter-latin-400-normal.woff2") format("woff2");font-weight:400;font-display:swap}
@font-face{font-family:"Inter";src:url("/assets/fonts/inter-latin-600-normal.woff2") format("woff2");font-weight:600;font-display:swap}
:root{--preto:#0B0B0B;--branco:#FFFFFF;--vermelho:#ED342F;--vinho:#6D0C10;--suave:#5A5A5A;--serif:"Instrument Serif",Georgia,serif;--sans:"Inter",system-ui,-apple-system,"Segoe UI",sans-serif;--pad:clamp(16px,2.8vw,40px)}
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0}
body{min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;background:var(--branco);color:var(--preto);font-family:var(--sans);-webkit-font-smoothing:antialiased}
:focus-visible{outline:2px solid var(--vermelho);outline-offset:3px}
.topo{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px var(--pad);border-bottom:1px solid var(--preto)}
.topo img{height:24px;width:auto;display:block}
.topo span{font:600 11px/1 var(--sans);letter-spacing:.06em;text-transform:uppercase;background:var(--preto);color:var(--branco);padding:5px 6px}
main{flex:1;display:grid;grid-template-columns:auto minmax(0,560px);align-items:center;justify-content:center;gap:clamp(36px,7vw,110px);padding:clamp(40px,7vw,96px) var(--pad)}
.garrafa svg{display:block;width:clamp(120px,15vw,190px);height:auto;overflow:visible}
.kicker{margin:0 0 14px;font:600 11px/1 var(--sans);letter-spacing:.1em;text-transform:uppercase;color:var(--vermelho)}
h1{margin:0 0 22px;font-family:var(--serif);font-weight:400;text-transform:uppercase;font-size:clamp(46px,6.6vw,96px);line-height:.94;letter-spacing:-.01em}
.bolhas{display:inline-flex;gap:.06em;margin-left:.04em}
.bolhas i{display:inline-block;width:.13em;height:.13em;border-radius:50%;background:var(--vinho);animation:borbulha 1.8s ease-in-out infinite}
.bolhas i:nth-child(2){animation-delay:.25s}
.bolhas i:nth-child(3){animation-delay:.5s}
@keyframes borbulha{0%,100%{transform:translateY(0);opacity:1}50%{transform:translateY(-.32em);opacity:.45}}
.lead{margin:0 0 36px;font-family:var(--serif);font-size:clamp(21px,2.1vw,28px);line-height:1.2;max-width:460px}
form{display:flex;flex-wrap:wrap;gap:10px;max-width:480px}
label{width:100%;font:600 10.5px/1.2 var(--sans);letter-spacing:.08em;text-transform:uppercase}
input{flex:1 1 200px;min-width:0;height:50px;padding:0 14px;border:1px solid var(--preto);border-radius:0;background:var(--branco);color:var(--preto);font:400 16px var(--sans)}
input:focus{outline:none;box-shadow:inset 0 0 0 1px var(--preto)}
button{height:50px;padding:0 24px;border:1px solid var(--preto);border-radius:0;background:var(--preto);color:var(--branco);font:600 12px/1 var(--sans);letter-spacing:.08em;text-transform:uppercase;cursor:pointer;transition:background .2s,border-color .2s}
button:hover{background:var(--vinho);border-color:var(--vinho)}
.erro{width:100%;margin:4px 0 0;color:var(--vermelho);font-size:14px;line-height:1.4}
.rodape{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 24px;padding:18px var(--pad);border-top:1px solid var(--preto);font-size:11px;color:var(--suave)}
.rodape a{color:inherit;text-decoration:none}
.rodape a:hover{text-decoration:underline}
/* Garrafa */
.liquido{fill:var(--vinho)}
.espuma{fill:#9B1A1F}
.b{fill:rgba(255,255,255,.6);animation:sobe 3.2s linear infinite;opacity:0}
@keyframes sobe{0%{transform:translateY(0);opacity:0}12%{opacity:.85}85%{opacity:.6}100%{transform:translateY(-165px);opacity:0}}
.onda{animation:onda 3.6s ease-in-out infinite}
@keyframes onda{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
@media (max-width:760px){
  main{grid-template-columns:1fr;justify-items:center;text-align:center;gap:28px}
  .lead{margin-inline:auto}
  form{margin-inline:auto;justify-content:center}
  label{text-align:left}
  .rodape{justify-content:center;text-align:center}
}
@media (max-width:420px){button{width:100%}}
@media (prefers-reduced-motion:reduce){.b,.bolhas i,.onda{animation:none}.b{opacity:.6}}
</style>
</head>
<body>
<header class="topo">
  <img src="/assets/img/logo-header.png" alt="Isto não é um vinho" width="574" height="128">
  <span>Em manutenção</span>
</header>

<main>
  <div class="garrafa">
    <svg viewBox="0 0 120 360" role="img" aria-label="Garrafa de vinho com bolhas a subir">
      <defs>
        <clipPath id="corpo"><path d="M48 8H72V100C72 125 102 135 102 165V335Q102 350 87 350H33Q18 350 18 335V165C18 135 48 125 48 100Z"/></clipPath>
      </defs>
      <g clip-path="url(#corpo)">
        <g class="onda">
          <rect class="liquido" x="0" y="176" width="120" height="190"/>
          <rect class="espuma" x="0" y="176" width="120" height="4"/>
        </g>
        <circle class="b" cx="30" cy="342" r="3.2" style="animation-delay:0s"/>
        <circle class="b" cx="52" cy="342" r="2.2" style="animation-delay:.6s"/>
        <circle class="b" cx="74" cy="342" r="3.6" style="animation-delay:1.2s"/>
        <circle class="b" cx="92" cy="342" r="2.4" style="animation-delay:1.9s"/>
        <circle class="b" cx="41" cy="342" r="1.8" style="animation-delay:2.4s"/>
        <circle class="b" cx="84" cy="342" r="2.8" style="animation-delay:.3s"/>
        <circle class="b" cx="62" cy="342" r="2" style="animation-delay:1.6s"/>
        <circle class="b" cx="24" cy="342" r="2" style="animation-delay:2.8s"/>
      </g>
      <path d="M48 8H72V100C72 125 102 135 102 165V335Q102 350 87 350H33Q18 350 18 335V165C18 135 48 125 48 100Z" fill="none" stroke="#0B0B0B" stroke-width="2.5" stroke-linejoin="round"/>
      <rect x="45.5" y="4" width="29" height="46" fill="#ED342F"/>
      <rect x="30" y="262" width="60" height="62" fill="#FFFFFF" stroke="#0B0B0B" stroke-width="1.5"/>
      <text x="60" y="279" text-anchor="middle" font-family="Instrument Serif, Georgia, serif" font-size="8.5" letter-spacing=".6">LOTE Nº 1</text>
      <text x="60" y="298" text-anchor="middle" font-family="Instrument Serif, Georgia, serif" font-size="14">A</text>
      <text x="60" y="314" text-anchor="middle" font-family="Instrument Serif, Georgia, serif" font-size="10.5">FERMENTAR</text>
    </svg>
  </div>

  <div class="texto">
    <p class="kicker">Site em manutenção</p>
    <h1>O site está a fermentar<span class="bolhas" aria-hidden="true"><i></i><i></i><i></i></span></h1>
    <p class="lead">Volta quando estiver no ponto. Prometemos que vale a espera.</p>

    <form method="post" action="">
      <label for="palavra">Tens acesso? Palavra-passe</label>
      <input type="password" id="palavra" name="palavra" autocomplete="current-password" required autofocus>
      <button type="submit">Provar antes do tempo</button>
      <?php if ($erro !== ''): ?><p class="erro" role="alert"><?= $e($erro) ?></p><?php endif; ?>
    </form>
  </div>
</main>

<footer class="rodape">
  <span>Seja responsável. Beba com moderação. Venda proibida a menores de 18 anos.</span>
  <a href="https://www.instagram.com/istonaoeumvinho/" rel="noopener" target="_blank">@istonaoeumvinho</a>
</footer>
</body>
</html>
