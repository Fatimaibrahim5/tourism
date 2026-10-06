<?php
// Minimal SMTP client (SSL on port 465 or STARTTLS on 587, AUTH LOGIN) used to send real emails
// such as password-reset links and administrator login codes. Settings are in includes/config.php.

function smtp_send(string $to, string $subject, string $body): void {
    $port = (int)SMTP_PORT;
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("SMTP connection failed: $errstr ($errno)");
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;   // last line of a multi-line reply
        }
        return $data;
    };
    $cmd = function (?string $line, array $okCodes) use ($fp, $read): string {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $reply = $read();
        if (!in_array((int)substr($reply, 0, 3), $okCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($reply));
        }
        return $reply;
    };

    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    $cmd(null, [220]);
    $cmd("EHLO $host", [250]);
    if ($port !== 465) {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed');
        $cmd("EHLO $host", [250]);
    }
    $cmd('AUTH LOGIN', [334]);
    $cmd(base64_encode(SMTP_USER), [334]);
    $cmd(base64_encode(SMTP_PASS), [235]);

    $from = SMTP_FROM !== '' ? SMTP_FROM : SMTP_USER;
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);

    $encode = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $encode(SMTP_FROM_NAME) . " <$from>",
        "To: <$to>",
        'Subject: ' . $encode($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
    $cmd($message . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}
