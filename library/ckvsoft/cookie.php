<?php

namespace ckvsoft;

class Cookie
{

    private array $storage = [];
    private array $deleted = [];

    /**
     * Set a cookie
     *
     * @param string $name
     * @param string|array $data
     * @param int $time Lifetime in seconds (default 1 day)
     * @return bool
     */
    public function set(string $name, string|array $data, int $time = 86400): bool
    {
        $value = is_array($data) ? json_encode($data) : $data;

        $success = setcookie(
                $name,
                $value,
                [
                    'expires' => time() + $time,
                    'secure' => true, // nur HTTPS
                    'httponly' => true, // JS kann nicht zugreifen
                    'path' => '/', // gilt für gesamte Domain
                    'samesite' => 'Lax', // verhindert CSRF bei Drittseiten
                ]
        );

        if ($success) {
            $this->storage[$name] = $value;
            unset($this->deleted[$name]);
        }

        return $success;
    }

    /**
     * Fetch a cookie
     *
     * @param string $name
     * @return string|array|false
     */
    public function fetch(string $name): string|array|false
    {
        if (isset($this->deleted[$name])) {
            return false;
        }

        if (!isset($this->storage[$name])) {
            return false;
        }

        $value = $this->storage[$name];

        // automatisch JSON erkennen
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Destroy a cookie
     *
     * @param string $name
     * @return bool
     */
    public function destroy(string $name): bool
    {
        $success = setcookie(
                $name,
                '',
                [
                    'expires' => time() - 3600,
                    'secure' => true,
                    'httponly' => true,
                    'path' => '/',
                    'samesite' => 'Lax',
                ]
        );

        if ($success) {
            unset($this->storage[$name]);
            $this->deleted[$name] = true;
        }

        return $success;
    }
}
