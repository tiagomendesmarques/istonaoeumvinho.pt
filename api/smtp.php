<?php
/*
  Cliente SMTP mínimo, sem dependências (SSL / STARTTLS, AUTH LOGIN).
  Envia emails HTML + texto em UTF-8.
*/
class Smtp
{
    private $cfg;
    private $sock;
    public $log = [];

    public function __construct(array $cfg) { $this->cfg = $cfg; }

    /**
     * @param string|array $to
     * @param string|null  $replyTo
     */
    public function send($fromEmail, $fromName, $to, $subject, $html, $text, $replyTo = null)
    {
        $to = (array)$to;
        $c = $this->cfg;
        $enc = strtolower($c['encryption'] ?? '');
        $host = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . (int)$c['port'];

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $c['host']]]);
        $this->sock = @stream_socket_client($host, $errno, $errstr, $c['timeout'] ?? 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) throw new RuntimeException("Ligação SMTP falhou: $errstr ($errno)");
        stream_set_timeout($this->sock, $c['timeout'] ?? 15);

        $this->expect(220);
        $ehlo = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->cmd("EHLO $ehlo", 250);

        if ($enc === 'tls') {
            $this->cmd('STARTTLS', 220);
            $ok = stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0));
            if (!$ok) throw new RuntimeException('STARTTLS falhou');
            $this->cmd("EHLO $ehlo", 250);
        }

        if (!empty($c['username'])) {
            $this->cmd('AUTH LOGIN', 334);
            $this->cmd(base64_encode($c['username']), 334);
            $this->cmd(base64_encode($c['password']), 235, true);
        }

        $this->cmd('MAIL FROM:<' . $this->clean($fromEmail) . '>', 250);
        foreach ($to as $rcpt) $this->cmd('RCPT TO:<' . $this->clean($rcpt) . '>', [250, 251]);
        $this->cmd('DATA', 354);

        $boundary = 'b' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($fromEmail, '@'), 1) ?: 'localhost';
        $h = [];
        $h[] = 'Date: ' . date('r');
        $h[] = 'From: ' . $this->addr($fromEmail, $fromName);
        $h[] = 'To: ' . implode(', ', array_map([$this, 'clean'], $to));
        if ($replyTo) $h[] = 'Reply-To: ' . $this->clean($replyTo);
        $h[] = 'Subject: ' . $this->header($subject);
        $h[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text));
        $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html));
        $body .= "--$boundary--\r\n";

        $data = implode("\r\n", $h) . "\r\n\r\n" . $body;
        $data = preg_replace('/^\./m', '..', $data); // dot-stuffing
        fwrite($this->sock, $data . "\r\n.\r\n");
        $this->expect(250);
        $this->cmd('QUIT', 221);
        fclose($this->sock);
        return true;
    }

    private function clean($s) { return trim(str_replace(["\r", "\n", '<', '>'], '', $s)); }
    private function header($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; }
    private function addr($email, $name) { return $this->header($name) . ' <' . $this->clean($email) . '>'; }

    private function cmd($line, $codes, $secret = false)
    {
        $this->log[] = 'C: ' . ($secret ? '****' : $line);
        fwrite($this->sock, $line . "\r\n");
        return $this->expect($codes);
    }

    private function expect($codes)
    {
        $codes = (array)$codes;
        $resp = '';
        while (($l = fgets($this->sock, 515)) !== false) {
            $resp .= $l;
            if (isset($l[3]) && $l[3] === ' ') break;
        }
        $this->log[] = 'S: ' . trim($resp);
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP respondeu: ' . trim($resp));
        }
        return $resp;
    }
}
