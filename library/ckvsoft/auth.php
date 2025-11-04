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
 * Handles all authentication and authorization logic, including session checks
 * and role management.
 */
class Auth
{
    // === Authentication and Authorization Logic ===

    /**
     * Saves a notification using the FlashMessage class and executes a server-side redirect.
     * This ensures a message is displayed after a full page load.
     * * @param string $targetUrl The full URL to redirect to (e.g., BASE_URI . 'dashboard').
     * @param string $type Notification type ('success', 'error', 'alert', 'info').
     * @param string $title Notification title.
     * @param string $message Main message body.
     * @param array $details Optional details array.
     */
    public static function sendFlashRedirect(string $targetUrl, string $type = 'info', string $title = '', string $message = '', array $details = []): void
    {
        if ($message !== '') {
            // Use the dedicated FlashMessage class to store the message
            \ckvsoft\FlashMessage::set($title, $message, $type, $details);
        }

        // Force the session data to be written to the server storage immediately
        // before sending the redirect header. This is crucial.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (APP_DEBUG) {
            error_log("Location: " . $targetUrl);
        }
        header("Location: " . $targetUrl);
        exit;
    }

    /**
     * Redirects to the dashboard if the user is already logged in.
     */
    public static function isLogged(): void
    {
        if (self::loginStatus()) {
            if (self::isAjaxRequest()) {
                \ckvsoft\Output::success(['isLogged' => true]);
                exit;
            }

            header('Location: ' . BASE_URI . 'dashboard');
            exit;
        }
    }

    /**
     * Redirects to the homepage if the user is not logged in.
     * Sends a 401 Unauthorized status code for AJAX requests.
     */
    public static function isNotLogged(string $role = ""): void
    {
        $wasLoggedInBefore = isset($_SESSION['user_id']) || \ckvsoft\MultiLoginManager::isFrameworkLoggedIn();

        if (!self::loginStatus()) {

            // AJAX-Request
            if (self::isAjaxRequest()) {
                if ($wasLoggedInBefore) {
                    // Session war mal aktiv → echte SessionExpired
                    http_response_code(401);
                    \ckvsoft\Output::error(_('Session expired'));
                } else {
                    // Keine Session vorhanden → einfach "not logged in", ohne Fehlermeldung
                    http_response_code(401);
                    \ckvsoft\Output::error(_('Not logged in'));
                }
                exit;
            }

            // Normaler Seitenaufruf
            if ($wasLoggedInBefore) {
                // Nur in diesem Fall „Session expired“
                self::sendFlashRedirect(
                        BASE_URI,
                        'error',
                        _('Session Expired'),
                        _('Your session has expired. Please log in again.')
                );
            } else {
                // User war nie eingeloggt → einfach zur Startseite ohne Fehlermeldung
                header('Location: ' . BASE_URI);
                exit;
            }
        }

        // Rollenprüfung bleibt genau wie bisher:
        if ($role !== "" && !self::hasRole($role)) {
            self::sendFlashRedirect(
                    BASE_URI . 'dashboard',
                    'error',
                    _('Access Denied'),
                    _('You do not have the required permissions to access this page.')
            );
        }
    }

    /**
     * Checks whether the current request is an AJAX request by checking the
     * 'X-Requested-With' HTTP header via the Request class.
     *
     * @return bool
     */
    public static function isAjaxRequest(): bool
    {
        // Instantiates the Request object to access encapsulated server data.
        $request = new Request();
        return $request->isAjaxRequest();
    }

    // === Core Status and Data Retrieval ===

    /**
     * Checks if the user is currently logged in.
     *
     * @return bool
     */
    public static function loginStatus(): bool
    {
        // Check using MultiLoginManager
        if (\ckvsoft\MultiLoginManager::isFrameworkLoggedIn()) {
            return true;
        }

        // Fallback: Check old session structure
        if (isset($_SESSION['user_id'], $_SESSION['user_key'])) {
            $enc = \ckvsoft\Hash::create('sha256', $_SESSION['user_id'], HASH_KEY);
            if ($_SESSION['user_key'] === $enc) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the logged-in user has the specified role.
     * Supports checking against multiple roles if needed, though $role is singular here.
     *
     * @param string $role The required role name.
     * @return bool
     */
    public static function hasRole(string $role = ""): bool
    {
        if ($role === "") {
            return true; // No role required
        }

        // 1. Get roles from MultiLoginManager
        $data = \ckvsoft\MultiLoginManager::getUserData('ckvsoft');
        if ($data && isset($data['roles'], $data['roles_key'])) {
            $expectedKey = \ckvsoft\Hash::create('sha256', implode(',', (array) $data['roles']), HASH_KEY);
            if (!hash_equals($expectedKey, $data['roles_key'])) {
                return false; // Tampered data detected
            }

            return in_array($role, (array) $data['roles'], true);
        }

        // 2. Fallback old session structure
        if (isset($_SESSION['user_role'])) {
            $enc = \ckvsoft\Hash::create('sha256', $role, HASH_KEY);
            return $_SESSION['user_role'] === $enc;
        }

        return false;
    }

    /**
     * Returns the user's ID.
     *
     * @return string|null
     */
    public static function getUserId(): ?string
    {
        $userId = MultiLoginManager::getUser('ckvsoft');
        if ($userId) {
            return $userId;
        }

        // Fallback
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Returns the user's permission level.
     * 3 = Admin
     * 1 = Registered User
     * 0 = Guest (Public)
     *
     * @return int
     */
    public static function getUserPermissionLevel(): int
    {
        if (self::hasRole("admin") || self::getUserId() == 1) {
            return 3; // Admin
        }

        if (self::loginStatus()) {
            return 1; // Registered User
        }

        return 0; // Guest (Public)
    }
}
