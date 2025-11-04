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
     * Checks if the Framework (ckvsoft) is logged in
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
     * Returns the User-ID for a specified module
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
     * Fetches arbitrary user data (including roles)
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
     * Logs a user into a module
     */
    public static function login(string $module, string $userId, array $data = []): void
    {
        $userKey = \ckvsoft\Hash::create('sha256', $userId, HASH_KEY);

        // Secure roles
        if (isset($data['roles'])) {
            $data['roles_key'] = \ckvsoft\Hash::create('sha256', implode(',', (array) $data['roles']), HASH_KEY);
        }

        try {
            self::$sharedDb->insertUpdate("multi_login_sessions", [
                'session_id' => self::sessionId(),
                'user_id' => $userId,
                'module_name' => $module,
                'user_key' => $userKey,
                'data' => json_encode($data)
            ]);
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::login failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Logs a user out from a module
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
     * Fetches the mapping for the current session **only for a specific module**.
     */
    public static function getMappedUserForModule(string $module): ?array
    {
        try {
            // Determine the current Framework user based on the current session
            $fwRows = self::$sharedDb->select("
                SELECT user_id
                FROM multi_login_sessions
                WHERE session_id = :session_id AND module_name = 'ckvsoft'
            ", ['session_id' => self::sessionId()]);

            $frameworkUserId = $fwRows[0]['user_id'] ?? null;
            if (!$frameworkUserId) {
                return null;
            }

            // Query the mapping for the requested module
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
     * Executes a mapping login for a module
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
     * Session-dependent logout (clears all modules for current session)
     */
    public static function logoutCurrentSession(): void
    {
        try {
            // This deletes the currently active session entry.
            self::$sharedDb->delete("multi_login_sessions",
                    "session_id = :session_id",
                    ['session_id' => self::sessionId()]
            );
        } catch (\PDOException $e) {
            throw new \ckvsoft\CkvException("MultiLoginManager::logoutCurrentSession failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Updates the last_activity timestamp for the current session in the database.
     * This should be called on every page load to prevent the session
     * from being deleted by Garbage Collection.
     */
    public static function updateActivityTimestamp(): void
    {
        try {
            // Format time as YYYY-MM-DD HH:MM:SS for database insertion
            $currentTime = date('Y-m-d H:i:s');

            // Update the 'last_activity' column to the current time for the current session ID
            self::$sharedDb->update("multi_login_sessions",
                    ['last_active' => $currentTime],
                    "session_id = :session_id",
                    ['session_id' => self::sessionId()]
            );
        } catch (\PDOException $e) {
            // Log the error but do not throw a hard exception for background activity update
            error_log("MultiLoginManager::updateActivityTimestamp failed: " . $e->getMessage());
        }
    }

    /**
     * Executes the Garbage Collection and deletes all expired sessions
     * (regardless of the current session ID).
     * @param int $timeoutSeconds The timeout in seconds (from the configuration).
     */
    public static function runGarbageCollection(int $timeoutSeconds): void
    {
        try {
            // Calculates the time before which sessions are considered expired.
            $cutoffTime = date('Y-m-d H:i:s', time() - $timeoutSeconds);

            // Deletes all entries whose last activity is before the cutoff time.
            // It is assumed that the table has a 'last_activity' column.
            $deletedRows = self::$sharedDb->delete("multi_login_sessions",
                    "last_active < :cutoff_time",
                    ['cutoff_time' => $cutoffTime]
            );

            // Optional logging
            if ($deletedRows > 0) {
                error_log("MultiLoginManager: Garbage Collection deleted {$deletedRows} expired session entries.");
            }
        } catch (\PDOException $e) {
            // Executed in the background, so only log, do not throw
            error_log("MultiLoginManager::runGarbageCollection failed: " . $e->getMessage());
        }
    }
}
