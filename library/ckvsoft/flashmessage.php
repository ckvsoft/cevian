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
 * Manages flash messages using the PHP session for persistence across redirects.
 * This class eliminates the need for flash_message_injection.php and cleans up index.php.
 */
class FlashMessage
{

    private const SESSION_KEY = 'ckvsoft_flash_message_data';

    /**
     * Stores a message that will be available on the next page load.
     * * @param string $message The message content (e.g., 'Session expired').
     * @param string $type The message type ('success', 'error', 'warning', 'info').
     */
    public static function set(string $message, string $type = 'info'): void
    {
        // Start session if not already active to ensure storage.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::SESSION_KEY] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    /**
     * Checks if a flash message exists and returns the data, clearing the session entry.
     * NOTE: This should ONLY be called once by the View/Layout component during rendering.
     * * @return array|null The message data or null if no message exists.
     */
    public static function get(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Should not happen if index.php starts session, but safety first.
            session_start();
        }

        if (isset($_SESSION[self::SESSION_KEY])) {
            $data = $_SESSION[self::SESSION_KEY];
            unset($_SESSION[self::SESSION_KEY]);
            return $data;
        }

        return null;
    }
}
