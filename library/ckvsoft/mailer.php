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
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace ckvsoft;

/**
 * Simple Mailer Class for sending system emails.
 */
class Mailer
{

    private $fromEmail;
    private $fromName;
    private $headers;

    public function __construct(string $fromEmail = 'noreply@ckvsoft.at', string $fromName = 'ckvsoft')
    {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;

        // Define standard email headers
        $this->headers = "MIME-Version: 1.0\r\n";
        $this->headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $this->headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $this->headers .= "Reply-To: {$this->fromEmail}\r\n";
        $this->headers .= "X-Mailer: PHP/" . phpversion();
    }

    /**
     * Sends an email.
     * * @param string $to Recipient email address.
     * @param string $subject Email subject line.
     * @param string $body Email content (plain text or HTML).
     * @return bool True on success, false on failure.
     */
    public function send(string $to, string $subject, string $body): bool
    {
        // For HTML emails, it's best practice to wrap the body in simple HTML structure.
        $htmlBody = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>' . htmlspecialchars($subject) . '</title>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6;">
                <div style="max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    ' . nl2br(htmlspecialchars($body)) . '
                    <p style="margin-top: 30px;">-- The ' . htmlspecialchars($this->fromName) . ' Team</p>
                </div>
            </body>
            </html>
        ';

        // Use the native PHP mail() function
        // Note: The $subject must be properly encoded for email transport
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // Return the result of the mail function
        return mail($to, $encodedSubject, $htmlBody, $this->headers);
    }
}
