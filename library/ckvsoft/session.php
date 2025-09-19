<?php

namespace ckvsoft;

class Session
{

    /**
     * start - Starts a session if one doesn't exist
     *
     * @param string|null $name Optional session name. If provided, the session will use this name
     */
    public static function start($name = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($name !== null) {
                session_name($name);
            }
            session_start();
        }
    }

    /**
     * set - Sets a value inside the session
     *
     * @param mixed $str_or_array String for single values, or an associative array
     * @param mixed $value The value for the key (If using an array do not set this value)
     *
     * Usage:
     * Session::set('key', 'value');
     * Session::set(['key1' => 'value1', 'key2' => 'value2']);
     */
    public static function set($str_or_array, $value = null)
    {
        if (is_string($str_or_array) && $value !== null) {
            $_SESSION[$str_or_array] = $value;
        } elseif (is_array($str_or_array)) {
            $_SESSION = array_merge($_SESSION, $str_or_array);
        }
    }

    /**
     * fetch - Retrieves a session value
     *
     * Supports up to 3 levels
     *
     * Usage:
     * $_SESSION['data'] = [
     *     'name' => 'jesse',
     *     'info' => [
     *         0 => 'testing',
     *         'age' => 28,
     *         'gender' => 'male',
     *         'other' => [
     *             'more' => 500
     *         ]
     *     ]
     * ];
     * Session::fetch('info', '0'); // returns 'testing'
     * Session::fetch('info', 'other', 'more'); // returns 500
     *
     * @return mixed The value or false if not set
     */
    public static function fetch()
    {
        $arg = func_get_args();
        $total = count($arg);

        if ($total == 0)
            return false;
        if ($total == 1)
            return $_SESSION[$arg[0]] ?? false;
        if ($total == 2)
            return $_SESSION[$arg[0]][$arg[1]] ?? false;
        if ($total == 3)
            return $_SESSION[$arg[0]][$arg[1]][$arg[2]] ?? false;

        return false;
    }

    /**
     * destroy - Kill the session if one exists
     *
     * @return boolean True if session was destroyed, false if no session existed
     * Note: This will also clear the session cookie if cookies are used
     */
    public static function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            return true;
        }
        return false;
    }

    /**
     * dump - Outputs the session for debugging purposes
     *
     * Usage:
     * Session::dump();
     */
    public static function dump()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo '<pre>';
            print_r($_SESSION);
            echo '</pre>';
        }
    }

    /* ---------- Namespace Methods ---------- */

    /**
     * setNs - Sets values inside a namespace (e.g., 'pmwh3')
     *
     * @param string $ns The namespace name
     * @param mixed $keyOrArray String for a single key or an associative array
     * @param mixed $value Optional value if $keyOrArray is a string
     */
    public static function setNs(string $ns, $keyOrArray, $value = null)
    {
        if (!isset($_SESSION[$ns]) || !is_array($_SESSION[$ns])) {
            $_SESSION[$ns] = [];
        }

        if (is_array($keyOrArray)) {
            $_SESSION[$ns] = array_merge($_SESSION[$ns], $keyOrArray);
        } elseif (is_string($keyOrArray)) {
            $_SESSION[$ns][$keyOrArray] = $value;
        }
    }

    /**
     * getNs - Retrieves value(s) from a namespace
     *
     * @param string $ns The namespace name
     * @param mixed ...$keys Optional 1–3 keys for nested arrays
     * @return mixed The value or false
     */
    public static function getNs(string $ns, ...$keys)
    {
        if (!isset($_SESSION[$ns]))
            return false;

        if (count($keys) === 0)
            return $_SESSION[$ns];
        if (count($keys) === 1)
            return $_SESSION[$ns][$keys[0]] ?? false;
        if (count($keys) === 2)
            return $_SESSION[$ns][$keys[0]][$keys[1]] ?? false;
        if (count($keys) === 3)
            return $_SESSION[$ns][$keys[0]][$keys[1]][$keys[2]] ?? false;

        return false;
    }

    /**
     * hasNs - Checks if a key exists in a namespace
     *
     * @param string $ns Namespace
     * @param string $key Key
     * @return bool
     */
    public static function hasNs(string $ns, string $key): bool
    {
        return isset($_SESSION[$ns]) && array_key_exists($key, $_SESSION[$ns]);
    }

    /**
     * clearNs - Deletes a namespace (only this section)
     *
     * @param string $ns Namespace
     * @return bool True if cleared, false if not found
     */
    public static function clearNs(string $ns): bool
    {
        if (isset($_SESSION[$ns])) {
            unset($_SESSION[$ns]);
            return true;
        }
        return false;
    }

    /**
     * removeNs - Entfernt einen einzelnen Schlüssel innerhalb eines Namespace
     *
     * @param string $ns  Namespace
     * @param string $key Schlüssel im Namespace
     * @return bool True wenn entfernt, false wenn nicht vorhanden
     */
    public static function removeNs(string $ns, string $key): bool
    {
        if (isset($_SESSION[$ns]) && array_key_exists($key, $_SESSION[$ns])) {
            unset($_SESSION[$ns][$key]);
            return true;
        }
        return false;
    }
}
