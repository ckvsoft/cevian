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
            $stmt = self::$sharedDb->prepare("
                SELECT user_key, user_id, data
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = 'ckvsoft'
            ");
            $stmt->execute(['session_id' => self::sessionId()]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $expectedKey = \ckvsoft\Hash::create('sha256', $row['user_id'], HASH_KEY);
            if (!hash_equals($expectedKey, $row['user_key'])) {
                return false;
            }

            return true;
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
            $stmt = self::$sharedDb->prepare("
                SELECT user_id
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = :module
            ");
            $stmt->execute([
                'session_id' => self::sessionId(),
                'module' => $module
            ]);
            return $stmt->fetchColumn() ?: null;
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
            $stmt = self::$sharedDb->prepare("
                SELECT data
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = :module
            ");
            $stmt->execute([
                'session_id' => self::sessionId(),
                'module' => $module
            ]);
            $json = $stmt->fetchColumn();
            return $json ? json_decode($json, true) : null;
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
            $stmt = self::$sharedDb->prepare("
                INSERT INTO multi_login_sessions 
                    (session_id, user_id, module_name, user_key, data, created_at, last_active)
                VALUES 
                    (:session_id, :user_id, :module, :user_key, :data, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    user_key = VALUES(user_key),
                    data = VALUES(data),
                    last_active = NOW()
            ");
            $stmt->execute([
                'session_id' => self::sessionId(),
                'user_id' => $userId,
                'module' => $module,
                'user_key' => $userKey,
                'data' => json_encode($data)
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
            $stmt = self::$sharedDb->prepare("
                DELETE FROM multi_login_sessions 
                WHERE session_id = :session_id AND module_name = :module
            ");
            $stmt->execute([
                'session_id' => self::sessionId(),
                'module' => $module
            ]);
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::logout failed: " . $e->getMessage(), 0, $e);
        }
    }

/**
 * Holt das Mapping für die aktuelle Session **nur für ein bestimmtes Modul**.
 * 
 * @param string $module Modulname, z.B. 'pmwh3'
 * @return array|null ['user_id' => string, 'data' => array] oder null wenn kein Mapping
 */
public static function getMappedUserForModule(string $module): ?array
{
    try {
        // Aktuellen Framework-User anhand der aktuellen Session ermitteln
        $stmt = self::$sharedDb->prepare("
            SELECT user_id 
            FROM multi_login_sessions 
            WHERE session_id = :session_id AND module_name = 'ckvsoft'
        ");
        $stmt->execute(['session_id' => self::sessionId()]);
        $frameworkUserId = $stmt->fetchColumn();

        if (!$frameworkUserId) {
            // Kein Framework-User -> Fallback
            return null;
        }

        // Mapping für das angeforderte Modul abfragen
        $stmt = self::$sharedDb->prepare("
            SELECT module_user_id 
            FROM module_user_mapping 
            WHERE framework_user_id = :uid AND module_name = :module
            LIMIT 1
        ");
        $stmt->execute([
            'uid' => $frameworkUserId,
            'module' => $module
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'user_id' => $row['module_user_id']
        ];

    } catch (\PDOException $e) {
        throw new \ckvsoft\CkvException("MultiLoginManager::getMappedUserForModule failed: " . $e->getMessage(), 0, $e);
    }
}

/**
 * Mapping-Login für ein Modul ausführen
 * Setzt die SessionNS nur, wenn Mapping existiert
 */
public static function applyMappedUser(string $module): bool
{
    $user = self::getMappedUserForModule($module);
    if (!$user) {
        return false; // kein Mapping, Modul muss eigenes Login machen
    }

    // Modul-Login setzen
    self::login($module, $user['user_id'], $user['data']);
    return true;
}
    /**
     * Session-abhängiges Logout: Auch alle Mapping-User der aktuellen Session ausloggen
     */
    public static function logoutCurrentSession(): void
    {
        try {
            $sessionId = self::sessionId();

            // Alle Modul-User dieser Session löschen
            $stmt = self::$sharedDb->prepare("
                DELETE FROM multi_login_sessions 
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);

        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::logoutCurrentSession failed: " . $e->getMessage(), 0, $e);
        }
    }    
}
