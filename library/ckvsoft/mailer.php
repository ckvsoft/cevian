<?php

/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

namespace ckvsoft;

/**
 * Mailer with optional SMTP backend.
 *
 * BACKWARD COMPATIBILITY:
 *   The simple two-argument send($to, $subject, $body) signature still works
 *   exactly like before. New consumers can use addAttachment(), setHtmlBody(),
 *   addCc()/addBcc() and the chained / fluent API.
 *
 * SMTP CONFIG (config/app.json):
 *   {
 *     "mail": {
 *       "smtp": {
 *         "host":       "smtp.example.com",
 *         "port":       587,
 *         "encryption": "tls",      // "tls" | "ssl" | null
 *         "user":       "noreply@example.com",
 *         "pass":       "secret",
 *         "timeout":    10
 *       }
 *     }
 *   }
 *
 * If no SMTP config is present, falls back to native mail() — keeps existing
 * callers (pmwh3 etc.) working without any change.
 */
class Mailer
{
    private string $fromEmail;
    private string $fromName;

    /** @var array<int,string> */
    private array $to = [];
    /** @var array<int,string> */
    private array $cc = [];
    /** @var array<int,string> */
    private array $bcc = [];

    private string $subject  = '';
    private string $textBody = '';
    private ?string $htmlBody = null;

    /** @var array<int,array{path:string,name:string,mime:string}> */
    private array $attachments = [];

    /** Last error from SMTP / mail() for diagnostics */
    public ?string $lastError = null;

    public function __construct(string $fromEmail = 'noreply@ckvsoft.at', string $fromName = 'ckvsoft')
    {
        $this->fromEmail = $fromEmail;
        $this->fromName  = $fromName;
    }

    // --------------------------------------------------------------------
    // Recipients / content (fluent)
    // --------------------------------------------------------------------

    public function addTo(string $email): self  { $this->to[]  = $email; return $this; }
    public function addCc(string $email): self  { $this->cc[]  = $email; return $this; }
    public function addBcc(string $email): self { $this->bcc[] = $email; return $this; }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function setTextBody(string $body): self
    {
        $this->textBody = $body;
        return $this;
    }

    public function setHtmlBody(string $html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function addAttachment(string $path, ?string $displayName = null, string $mime = 'application/octet-stream'): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Attachment not found: $path");
        }
        $this->attachments[] = [
            'path' => $path,
            'name' => $displayName ?: basename($path),
            'mime' => $mime,
        ];
        return $this;
    }

    // --------------------------------------------------------------------
    // Backward-compatible send signature
    // --------------------------------------------------------------------

    /**
     * Backward-compatible: send($to, $subject, $body).
     * Body is treated as plain text and auto-wrapped in basic HTML for
     * legacy parity with the previous Mailer implementation.
     */
    public function send(string $to, string $subject, string $body): bool
    {
        // Reset per-call state
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->attachments = [];
        $this->subject  = $subject;
        $this->textBody = $body;
        $this->htmlBody = $this->wrapLegacyHtml($subject, $body);
        $this->addTo($to);

        return $this->dispatch();
    }

    /**
     * Send the currently configured message.
     */
    public function dispatch(): bool
    {
        if (empty($this->to)) {
            $this->lastError = 'No recipient (to) configured';
            return false;
        }

        $smtpConfig = $this->resolveSmtpConfig();

        try {
            if ($smtpConfig !== null) {
                return $this->sendViaSmtp($smtpConfig);
            }
            return $this->sendViaMailFunction();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------
    // SMTP config resolution
    // --------------------------------------------------------------------

    /**
     * @return array{host:string,port:int,encryption:?string,user:?string,pass:?string,timeout:int}|null
     */
    private function resolveSmtpConfig(): ?array
    {
        if (!class_exists('\\ckvsoft\\mvc\\Config')) {
            return null;
        }

        $host = \ckvsoft\mvc\Config::get('mail.smtp.host');
        if (!$host) {
            return null;
        }

        return [
            'host'       => (string) $host,
            'port'       => (int) (\ckvsoft\mvc\Config::get('mail.smtp.port') ?: 25),
            'encryption' => \ckvsoft\mvc\Config::get('mail.smtp.encryption'),
            'user'       => \ckvsoft\mvc\Config::get('mail.smtp.user'),
            'pass'       => \ckvsoft\mvc\Config::get('mail.smtp.pass'),
            'timeout'    => (int) (\ckvsoft\mvc\Config::get('mail.smtp.timeout') ?: 10),
        ];
    }

    // --------------------------------------------------------------------
    // MIME message construction
    // --------------------------------------------------------------------

    /**
     * @return array{0:string,1:string} [headers, body]
     */
    private function buildMimeMessage(bool $includeToHeader = true): array
    {
        $boundaryMixed = '==MX_' . bin2hex(random_bytes(8));
        $boundaryAlt   = '==AL_' . bin2hex(random_bytes(8));

        $hasAttach = !empty($this->attachments);
        $hasHtml   = $this->htmlBody !== null && $this->htmlBody !== '';

        // ---- Headers -----------------------------------------------------
        $h  = "From: " . $this->encodeHeader($this->fromName) . " <{$this->fromEmail}>\r\n";
        if ($includeToHeader) {
            $h .= "To: " . implode(', ', $this->to) . "\r\n";
        }
        if (!empty($this->cc)) {
            $h .= "Cc: " . implode(', ', $this->cc) . "\r\n";
        }
        // Bcc intentionally NOT in headers
        $h .= "Subject: " . $this->encodeHeader($this->subject) . "\r\n";
        $h .= "Date: " . date('r') . "\r\n";
        $h .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@" . $this->extractDomain($this->fromEmail) . ">\r\n";
        $h .= "MIME-Version: 1.0\r\n";

        // ---- Body --------------------------------------------------------
        if ($hasAttach) {
            $h .= "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"\r\n";
            $altBody = $this->buildAlternativePart($boundaryAlt, $hasHtml);
            $body  = "--{$boundaryMixed}\r\n"
                   . "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n"
                   . $altBody . "\r\n";

            foreach ($this->attachments as $a) {
                $data = chunk_split(base64_encode((string) file_get_contents($a['path'])));
                $body .= "--{$boundaryMixed}\r\n";
                $body .= "Content-Type: {$a['mime']}; name=\"{$a['name']}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$a['name']}\"\r\n\r\n";
                $body .= $data . "\r\n";
            }
            $body .= "--{$boundaryMixed}--\r\n";
        } elseif ($hasHtml) {
            $h .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n";
            $body = $this->buildAlternativePart($boundaryAlt, true);
        } else {
            $h .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $h .= "Content-Transfer-Encoding: 8bit\r\n";
            $body = $this->textBody;
        }

        return [$h, $body];
    }

    private function buildAlternativePart(string $boundary, bool $includeHtml): string
    {
        $out  = "--{$boundary}\r\n";
        $out .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $out .= $this->textBody . "\r\n\r\n";

        if ($includeHtml) {
            $out .= "--{$boundary}\r\n";
            $out .= "Content-Type: text/html; charset=UTF-8\r\n";
            $out .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $out .= $this->htmlBody . "\r\n\r\n";
        }
        $out .= "--{$boundary}--\r\n";
        return $out;
    }

    // --------------------------------------------------------------------
    // Backends: native mail() and SMTP
    // --------------------------------------------------------------------

    private function sendViaMailFunction(): bool
    {
        // For mail(): "To" goes as separate arg, NOT as header
        [$headers, $body] = $this->buildMimeMessage(false);

        $encodedSubject = $this->encodeHeader($this->subject);
        $allTo = implode(', ', $this->to);

        if (!empty($this->bcc)) {
            $headers .= "Bcc: " . implode(', ', $this->bcc) . "\r\n";
        }

        $returnPath = '-f' . $this->fromEmail;
        $ok = @mail($allTo, $encodedSubject, $body, $headers, $returnPath);
        if (!$ok) {
            $this->lastError = 'mail() returned false';
        }
        return $ok;
    }

    /**
     * Minimal SMTP client (no external library).
     * Supports HELO/EHLO, STARTTLS, AUTH LOGIN, MAIL FROM, RCPT TO, DATA, QUIT.
     */
    private function sendViaSmtp(array $cfg): bool
    {
        [$headers, $body] = $this->buildMimeMessage(true);

        $encryption = $cfg['encryption'] ? strtolower($cfg['encryption']) : null;
        $hostUri    = ($encryption === 'ssl' ? 'ssl://' : '') . $cfg['host'];
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client(
            "{$hostUri}:{$cfg['port']}",
            $errno, $errstr,
            $cfg['timeout'],
            STREAM_CLIENT_CONNECT
        );
        if (!$sock) {
            $this->lastError = "SMTP connect failed: $errstr ($errno)";
            return false;
        }
        stream_set_timeout($sock, $cfg['timeout']);

        $expect = function (int $code) use ($sock): string {
            $line = '';
            while (!feof($sock)) {
                $chunk = fgets($sock, 515);
                if ($chunk === false) break;
                $line .= $chunk;
                if (preg_match('/^\d{3} /', $chunk)) break;
            }
            if ((int) substr($line, 0, 3) !== $code) {
                throw new \RuntimeException("SMTP expected $code, got: " . trim($line));
            }
            return $line;
        };
        $say = function (string $cmd) use ($sock): void {
            fwrite($sock, $cmd . "\r\n");
        };

        try {
            $expect(220);

            $helloHost = $_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost';
            $say("EHLO {$helloHost}");
            $expect(250);

            if ($encryption === 'tls') {
                $say('STARTTLS');
                $expect(220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed');
                }
                $say("EHLO {$helloHost}");
                $expect(250);
            }

            if (!empty($cfg['user'])) {
                $say('AUTH LOGIN');
                $expect(334);
                $say(base64_encode($cfg['user']));
                $expect(334);
                $say(base64_encode((string) $cfg['pass']));
                $expect(235);
            }

            $say("MAIL FROM:<{$this->fromEmail}>");
            $expect(250);

            foreach (array_merge($this->to, $this->cc, $this->bcc) as $rcpt) {
                $say("RCPT TO:<{$rcpt}>");
                $expect(250);
            }

            $say('DATA');
            $expect(354);

            // Dot-stuffing per RFC 5321 §4.5.2
            $payload = $headers . "\r\n" . $body;
            $payload = preg_replace('/^\./m', '..', $payload);
            fwrite($sock, $payload . "\r\n.\r\n");
            $expect(250);

            $say('QUIT');
            fclose($sock);
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            @fclose($sock);
            return false;
        }
    }

    // --------------------------------------------------------------------
    // Helpers
    // --------------------------------------------------------------------

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function extractDomain(string $email): string
    {
        $at = strrpos($email, '@');
        return $at === false ? 'localhost' : substr($email, $at + 1);
    }

    private function wrapLegacyHtml(string $subject, string $body): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
             . htmlspecialchars($subject) . '</title></head><body style="font-family:Arial,sans-serif;line-height:1.6;">'
             . '<div style="max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:5px;">'
             . nl2br(htmlspecialchars($body))
             . '<p style="margin-top:30px;">-- The ' . htmlspecialchars($this->fromName) . ' Team</p>'
             . '</div></body></html>';
    }
}
