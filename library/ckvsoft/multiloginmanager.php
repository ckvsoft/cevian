<?php

namespace ckvsoft;

class MultiLoginManager extends \ckvsoft\mvc\Config
{

    protected static string $sessionId;

    protected static function sessionId(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset(self::$sessionId)) {
            self::$sessionId = session_id();
        }
        return self::$sessionId;
    }

    /**
     * Prüfen ob Framework (ckvsoft) eingeloggt ist
     */
    public static function isFrameworkLoggedIn(): bool
    {
        try {
            $rows = self::$sharedDb->select("
                SELECT user_key, user_id, data
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = 'ckvsoft'
            ", ['session_id' => self::sessionId()]);

            if (empty($rows)) {
                return false;
            }

            $row = $rows[0];
            $expectedKey = \ckvsoft\Hash::create('sha256', $row['user_id'], HASH_KEY);
            return hash_equals($expectedKey, $row['user_key']);
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::isFrameworkLoggedIn failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * User-ID für beliebiges Modul zurückgeben
     */
    public static function getUser(string $module): ?string
    {
        try {
            $rows = self::$sharedDb->select("
                SELECT user_id
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = :module
            ", [
                'session_id' => self::sessionId(),
                'module' => $module
            ]);

            return $rows[0]['user_id'] ?? null;
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::getUser failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Beliebige User-Daten holen (inkl. Rollen)
     */
    public static function getUserData(string $module): ?array
    {
        try {
            $rows = self::$sharedDb->select("
                SELECT data
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = :module
            ", [
                'session_id' => self::sessionId(),
                'module' => $module
            ]);

            if (empty($rows)) {
                return null;
            }

            return json_decode($rows[0]['data'], true);
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::getUserData failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Login eines Users in ein Modul
     */
    public static function login(string $module, string $userId, array $data = []): void
    {
        $userKey = \ckvsoft\Hash::create('sha256', $userId, HASH_KEY);

        // Rollen absichern
        if (isset($data['roles'])) {
            $data['roles_key'] = \ckvsoft\Hash::create('sha256', implode(',', (array) $data['roles']), HASH_KEY);
        }

        try {
            self::$sharedDb->insertUpdate("multi_login_sessions", [
                'session_id' => self::sessionId(),
                'user_id' => $userId,
                'module_name' => $module,
                'user_key' => $userKey,
                'data' => json_encode($data),
                'created_at' => new DbExpr("NOW()"),
                'last_active' => new DbExpr("NOW()")
            ]);
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::login failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Logout eines Users aus einem Modul
     */
    public static function logout(string $module): void
    {
        try {
            self::$sharedDb->delete("multi_login_sessions",
                    "session_id = :session_id AND module_name = :module",
                    [
                        'session_id' => self::sessionId(),
                        'module' => $module
                    ]
            );
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::logout failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Holt das Mapping für die aktuelle Session **nur für ein bestimmtes Modul**.
     */
    public static function getMappedUserForModule(string $module): ?array
    {
        try {
            // Aktuellen Framework-User anhand der aktuellen Session ermitteln
            $fwRows = self::$sharedDb->select("
                SELECT user_id
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = 'ckvsoft'
            ", ['session_id' => self::sessionId()]);

            $frameworkUserId = $fwRows[0]['user_id'] ?? null;
            if (!$frameworkUserId) {
                return null;
            }

            // Mapping für das angeforderte Modul abfragen
            $mapRows = self::$sharedDb->select("
                SELECT module_user_id
                FROM module_user_mapping
                WHERE framework_user_id = :uid AND module_name = :module
                LIMIT 1
            ", [
                'uid' => $frameworkUserId,
                'module' => $module
            ]);

            if (empty($mapRows)) {
                return null;
            }

            return [
                'user_id' => $mapRows[0]['module_user_id']
            ];
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::getMappedUserForModule failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Mapping-Login für ein Modul ausführen
     */
    public static function applyMappedUser(string $module): bool
    {
        $user = self::getMappedUserForModule($module);
        if (!$user) {
            return false;
        }

        self::login($module, $user['user_id'], $user['data'] ?? []);
        return true;
    }

    /**
     * Session-abhängiges Logout
     */
    public static function logoutCurrentSession(): void
    {
        try {
            self::$sharedDb->delete("multi_login_sessions",
                    "session_id = :session_id",
                    ['session_id' => self::sessionId()]
            );
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::logoutCurrentSession failed: " . $e->getMessage(), 0, $e);
        }
    }
}
