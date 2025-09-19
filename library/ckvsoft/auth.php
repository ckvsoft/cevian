<?php

namespace ckvsoft;

class Auth
{

    /**
     * Weiterleiten zum Dashboard, wenn eingeloggt
     */
    public static function isLogged(): void
    {
        if (self::loginStatus()) {
            header('Location: ' . BASE_URI . 'dashboard');
            exit;
        }
    }

    /**
     * Weiterleiten zur Startseite, wenn nicht eingeloggt
     */
    public static function isNotLogged(string $role = ""): void
    {
        if (!self::loginStatus()) {
            session_destroy();
            header('Location: ' . BASE_URI);
            exit;
        }

        if ($role !== "" && !self::hasRole($role)) {
            header('Location: ' . BASE_URI . 'dashboard');
            exit;
        }
    }

    /**
     * Prüfen ob eingeloggt
     */
    public static function loginStatus(): bool
    {
        // Prüfen über MultiLoginManager
        if (\ckvsoft\MultiLoginManager::isFrameworkLoggedIn()) {
            return true;
        }

        // Fallback: alte Session prüfen
        if (isset($_SESSION['user_id'], $_SESSION['user_key'])) {
            $enc = \ckvsoft\Hash::create('sha256', $_SESSION['user_id'], HASH_KEY);
            if ($_SESSION['user_key'] === $enc) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rollenprüfung (unterstützt mehrere Rollen)
     */
    public static function hasRole(string $role = ""): bool
    {
        if ($role === "") {
            return true; // keine Rolle verlangt
        }

        // 1. Rollen aus MultiLoginManager holen
        $data = \ckvsoft\MultiLoginManager::getUserData('ckvsoft');
        if ($data && isset($data['roles'], $data['roles_key'])) {
            $expectedKey = \ckvsoft\Hash::create('sha256', implode(',', (array) $data['roles']), HASH_KEY);
            if (!hash_equals($expectedKey, $data['roles_key'])) {
                return false; // manipuliert
            }

            return in_array($role, (array) $data['roles'], true);
        }

        // 2. Fallback alte Session
        if (isset($_SESSION['user_role'])) {
            $enc = \ckvsoft\Hash::create('sha256', $role, HASH_KEY);
            return $_SESSION['user_role'] === $enc;
        }

        return false;
    }

    /**
     * User-ID zurückgeben
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
}
