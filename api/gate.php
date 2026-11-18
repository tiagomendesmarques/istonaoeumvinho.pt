<?php
/*
  Modo manutenção: o site só abre com palavra-passe.
  - Liga/desliga com a variável de ambiente MANUTENCAO (1 = ligado), definida no workflow de deploy.
  - A palavra-passe vem de SITE_PASSWORD (Secret Manager "site-password"), nunca do código.
  - Quem acerta recebe um cookie assinado válido 30 dias. Mudar a palavra-passe invalida os cookies.
  Este ficheiro não é acessível pelo browser (api/.htaccess).
*/
declare(strict_types=1);

const GATE_COOKIE = 'inv_prova';
const GATE_DIAS = 30;

function gate_env(string $k): string
{
    $v = getenv($k);
    return $v === false ? '' : trim((string)$v);
}

function gate_ativo(): bool
{
    return gate_env('MANUTENCAO') === '1';
}

function gate_chave(): string
{
    return hash('sha256', 'inv-gate|' . gate_env('SITE_PASSWORD'));
}

function gate_ok(): bool
{
    if (!gate_ativo()) return true;
    $c = (string)($_COOKIE[GATE_COOKIE] ?? '');
    if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $c, $m)) return false;
    if ((int)$m[1] < time()) return false;
    return hash_equals(hash_hmac('sha256', $m[1], gate_chave()), $m[2]);
}

function gate_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function gate_entrar(): void
{
    $exp = (string)(time() + GATE_DIAS * 86400);
    setcookie(GATE_COOKIE, $exp . '.' . hash_hmac('sha256', $exp, gate_chave()), [
        'expires'  => (int)$exp,
        'path'     => '/',
        'secure'   => gate_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function gate_palavra_certa(string $p): bool
{
    $certa = gate_env('SITE_PASSWORD');
    return $certa !== '' && hash_equals($certa, $p);
}

/** Limite de tentativas: 10 por IP a cada 10 minutos. Devolve false se excedido. */
function gate_tentativa_permitida(): bool
{
    $ip = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]) ?: (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    $f = sys_get_temp_dir() . '/inv_gate_' . md5($ip);
    $agora = time();
    $hist = array_values(array_filter((array)@json_decode((string)@file_get_contents($f), true), fn($t) => is_int($t) && $t > $agora - 600));
    if (count($hist) >= 10) return false;
    $hist[] = $agora;
    @file_put_contents($f, json_encode($hist));
    return true;
}
