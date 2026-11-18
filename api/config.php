<?php
/*
  Configuração do envio de emails (SMTP).
  Os valores vêm de variáveis de ambiente (definidas no Cloud Run pelo deploy automático).
  A password NUNCA vai para o GitHub: fica no Secret Manager da Google Cloud (SMTP_PASSWORD).
  Os valores à direita são só valores por omissão.
*/
$env = function (string $k, $omissao = '') {
    $v = getenv($k);
    return ($v === false || $v === '') ? $omissao : $v;
};
$lista = fn(string $s) => array_values(array_filter(array_map('trim', explode(',', $s))));

return [
    'smtp' => [
        'host'       => $env('SMTP_HOST', 'smtp-pt.securemail.pro'),
        'port'       => (int)$env('SMTP_PORT', '465'),
        'encryption' => $env('SMTP_ENCRYPTION', 'ssl'),   // 'ssl' (465), 'tls' (587, STARTTLS) ou ''
        'username'   => $env('SMTP_USER', 'geral@istonaoeumvinho.pt'),
        'password'   => $env('SMTP_PASSWORD', ''),
        'timeout'    => 15,
    ],

    // Remetente
    'from_email' => $env('MAIL_FROM', $env('SMTP_USER', 'geral@istonaoeumvinho.pt')),
    'from_name'  => $env('MAIL_FROM_NAME', 'Isto não é um Vinho'),

    // Quem recebe as encomendas (separados por vírgula)
    'to' => $lista($env('MAIL_TO', 'geral@istonaoeumvinho.pt')),

    // Email de confirmação para o cliente
    'confirmacao_cliente' => $env('CONFIRMACAO_CLIENTE', '1') === '1',

    // Cópia em CSV (no Cloud Run o disco é temporário, por isso vem desligado)
    'guardar_csv' => $env('GUARDAR_CSV', '0') === '1',

    // Preço por garrafa (só para o resumo do email). Deixar igual ao config.js.
    'preco' => $env('PRECO', '99'),

    // Domínios autorizados (além do próprio domínio onde o site está a correr)
    'origens' => $lista($env('ALLOWED_ORIGINS', 'https://istonaoeumvinho.pt,https://www.istonaoeumvinho.pt')),
];
