<?php
/*
  Recebe o formulário de encomenda (POST) e envia:
   1. email para a equipa com os dados da encomenda;
   2. email de confirmação para o cliente (opcional).
  Responde JSON: {"ok":true} ou {"ok":false,"erro":"..."}
*/
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/smtp.php';

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'erro' => 'Método não permitido.']);

// Em manutenção só aceita encomendas de quem entrou com a palavra-passe
require __DIR__ . '/gate.php';
if (!gate_ok()) responder(403, ['ok' => false, 'erro' => 'O site está em manutenção.']);

// Origem
// Origem: aceita o próprio domínio onde o site corre (ex.: *.run.app) e os da lista
$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
$origemHost = $origem !== '' ? (string)parse_url($origem, PHP_URL_HOST) : '';
$host = strtolower(explode(':', $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '')[0]);
if ($origem !== '' && !empty($cfg['origens']) && strtolower($origemHost) !== $host && !in_array($origem, $cfg['origens'], true)) {
    responder(403, ['ok' => false, 'erro' => 'Origem não autorizada.']);
}
if ($cfg['smtp']['password'] === '' && $cfg['smtp']['username'] !== '') {
    error_log('[encomenda] SMTP_PASSWORD não definida');
    responder(503, ['ok' => false, 'erro' => '']);
}

// Anti-spam: honeypot
if (!empty($_POST['website'])) responder(200, ['ok' => true]);

// Limite simples: 5 pedidos por IP a cada 10 minutos
// No Cloud Run o IP do cliente vem em X-Forwarded-For
$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]) ?: ($_SERVER['REMOTE_ADDR'] ?? '0');
$rlFile = sys_get_temp_dir() . '/inv_rl_' . md5($ip);
$agora = time();
$hist = array_filter((array)@json_decode((string)@file_get_contents($rlFile), true), fn($t) => $t > $agora - 600);
if (count($hist) >= 5) responder(429, ['ok' => false, 'erro' => 'Demasiados pedidos. Espera uns minutos.']);
$hist[] = $agora;
@file_put_contents($rlFile, json_encode(array_values($hist)));

// Campos
$f = fn(string $k, int $max = 300) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
$d = [
    'nome'        => $f('nome', 120),
    'email'       => $f('email', 160),
    'telemovel'   => $f('telemovel', 30),
    'quantidade'  => $f('quantidade', 40),
    'entrega'     => $f('entrega', 40),
    'morada'      => $f('morada', 300),
    'mensagem'    => $f('mensagem', 200),
    'maioridade'  => $f('maioridade', 3),
    'privacidade' => $f('privacidade', 3),
];

$quantidades = ['1', '2', '3', '6', '12', 'Mais de 12 (empresas)'];
$entregas = ['Envio para morada', 'Recolha em Lisboa'];
$erros = [];
if (mb_strlen($d['nome']) < 2) $erros[] = 'nome';
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'email';
if (strlen(preg_replace('/\D/', '', $d['telemovel'])) < 9) $erros[] = 'telemóvel';
if (!in_array($d['quantidade'], $quantidades, true)) $erros[] = 'quantidade';
if (!in_array($d['entrega'], $entregas, true)) $erros[] = 'entrega';
if ($d['entrega'] === 'Envio para morada' && mb_strlen($d['morada']) < 5) $erros[] = 'morada';
if ($d['maioridade'] !== 'sim') $erros[] = 'maioridade';
if ($d['privacidade'] !== 'sim') $erros[] = 'privacidade';
if ($erros) responder(422, ['ok' => false, 'erro' => 'Verifica: ' . implode(', ', $erros) . '.']);

if ($d['entrega'] !== 'Envio para morada') $d['morada'] = '';

$ref = 'INV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$preco = is_numeric($cfg['preco'] ?? null) ? (float)$cfg['preco'] : null;
$n = ctype_digit($d['quantidade']) ? (int)$d['quantidade'] : null;
$eur = fn(float $v) => number_format($v, ($v == floor($v)) ? 0 : 2, ',', ' ') . ' €';
$totalTxt = ($preco !== null && $n !== null)
    ? $eur($preco * $n) . ($d['entrega'] === 'Envio para morada' ? ' + envio' : '')
    : 'A combinar';

$linhas = [
    'Referência' => $ref,
    'Nome'       => $d['nome'],
    'Email'      => $d['email'],
    'Telemóvel'  => $d['telemovel'],
    'Garrafas'   => $d['quantidade'],
    'Entrega'    => $d['entrega'],
    'Morada'     => $d['morada'] ?: '—',
    'Mensagem'   => $d['mensagem'] ?: '—',
    'Total'      => $totalTxt,
    'Data'       => date('Y-m-d H:i'),
];

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

function tabelaHtml(array $linhas, callable $e): string {
    $h = '';
    foreach ($linhas as $k => $v) {
        $h .= '<tr><td style="padding:8px 16px 8px 0;font:12px Arial,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#888;vertical-align:top;white-space:nowrap">'
            . $e($k) . '</td><td style="padding:8px 0;font:16px Georgia,serif;color:#0B0B0B">' . nl2br($e($v)) . '</td></tr>';
    }
    return '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $h . '</table>';
}
function tabelaTxt(array $linhas): string {
    $t = '';
    foreach ($linhas as $k => $v) $t .= str_pad($k . ':', 12) . ' ' . $v . "\n";
    return $t;
}
function moldura(string $titulo, string $conteudo, callable $e): string {
    return '<!doctype html><html><body style="margin:0;background:#F4F4F4;padding:24px">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:32px;border-top:4px solid #ED342F">'
        . '<p style="margin:0 0 4px;font:12px Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#0B0B0B">Isto <s style="text-decoration-color:#ED342F">não</s> é um vinho</p>'
        . '<h1 style="margin:0 0 20px;font:400 30px Georgia,serif;color:#0B0B0B">' . $e($titulo) . '</h1>'
        . $conteudo
        . '<p style="margin:28px 0 0;font:11px Arial,sans-serif;color:#999">Seja responsável. Beba com moderação. Venda proibida a menores de 18 anos.</p>'
        . '</div></body></html>';
}

$smtp = new Smtp($cfg['smtp']);

// 1) Email para a equipa
try {
    $html = moldura('Nova encomenda ' . $ref, tabelaHtml($linhas, $e), $e);
    $txt = "Nova encomenda $ref\n\n" . tabelaTxt($linhas);
    $smtp->send($cfg['from_email'], $cfg['from_name'], $cfg['to'],
        "Encomenda $ref — {$d['quantidade']} garrafa(s) — {$d['nome']}", $html, $txt, $d['email']);
} catch (Throwable $ex) {
    error_log('[encomenda] ' . $ex->getMessage() . ' | ' . implode(' | ', $smtp->log));
    guardarCsv($cfg, $linhas, 'ERRO EMAIL');
    responder(502, ['ok' => false, 'erro' => '']);
}

guardarCsv($cfg, $linhas, 'novo');

// 2) Confirmação para o cliente (falha aqui não estraga a encomenda)
if (!empty($cfg['confirmacao_cliente'])) {
    try {
        $resumo = $linhas;
        unset($resumo['Data']);
        $intro = '<p style="margin:0 0 20px;font:16px/1.5 Georgia,serif;color:#0B0B0B">Olá ' . $e(explode(' ', $d['nome'])[0]) . ',<br>recebemos o teu pedido. Respondemos até amanhã com a confirmação, o pagamento e a entrega. Não pagas nada agora.</p>';
        $recolhaHtml = $recolhaTxt = '';
        if ($d['entrega'] === 'Recolha em Lisboa') {
            $recolhaHtml = '<p style="margin:24px 0 0;font:16px/1.5 Georgia,serif;color:#0B0B0B"><b>Onde levantar</b><br>'
                . 'marcAsério<br>R. Fernando Namora 4, 6º D<br>2675-487 Odivelas<br>'
                . 'Das 9h às 12h e das 14h às 18h<br>969 420 907</p>';
            $recolhaTxt = "\nOnde levantar:\nmarcAsério\nR. Fernando Namora 4, 6º D\n2675-487 Odivelas\nDas 9h às 12h e das 14h às 18h\n969 420 907\n";
        }
        $html = moldura('Recebido.', $intro . tabelaHtml($resumo, $e) . $recolhaHtml
            . '<p style="margin:24px 0 0;font:16px Georgia,serif;color:#0B0B0B">Obrigado por entrares nesta brincadeira.</p>', $e);
        $txt = "Olá {$d['nome']},\n\nRecebemos o teu pedido. Respondemos até amanhã com a confirmação, o pagamento e a entrega.\n\n"
            . tabelaTxt($resumo) . $recolhaTxt . "\nObrigado por entrares nesta brincadeira.\nIsto não é um Vinho";
        (new Smtp($cfg['smtp']))->send($cfg['from_email'], $cfg['from_name'], $d['email'],
            "Recebemos o teu pedido ($ref)", $html, $txt, $cfg['to'][0] ?? null);
    } catch (Throwable $ex) {
        error_log('[encomenda/confirmacao] ' . $ex->getMessage());
    }
}

responder(200, ['ok' => true, 'ref' => $ref]);

function guardarCsv(array $cfg, array $linhas, string $estado): void {
    if (empty($cfg['guardar_csv'])) return;
    $dir = __DIR__ . '/dados';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $file = $dir . '/encomendas.csv';
    $novo = !file_exists($file);
    $fh = @fopen($file, 'a');
    if (!$fh) return;
    flock($fh, LOCK_EX);
    if ($novo) { fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, array_merge(array_keys($linhas), ['Estado']), ';'); }
    // evita injeção de fórmulas no Excel
    $vals = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, array_values($linhas));
    fputcsv($fh, array_merge($vals, [$estado]), ';');
    flock($fh, LOCK_UN);
    fclose($fh);
}
