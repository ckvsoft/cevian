<?php

namespace ckvsoft\mvc;

class Config
{

    protected static $sharedDb = null;
    protected static $appConfig = null;
    protected $db;

    public function __construct()
    {
        // DB nur initialisieren, wenn config.json existiert
        if (file_exists(__DIR__ . '/../../../config/config.json')) {
            $this->db = self::db();
        } else {
            $this->db = null; // Installer Mode
        }

        self::getAppConfig();
    }

    // Neue Methode, um die App-Konfiguration zu laden und zu cachen
    public static function getAppConfig()
    {
        if (self::$appConfig === null) {
            $configPath = __DIR__ . '/../../../config/app.json';
            if (!file_exists($configPath)) {
                return [];
                // die("Error: Global App-Configurationfile '$configPath' not found!");
            }
            self::$appConfig = json_decode(file_get_contents($configPath), true);
        }
        return self::$appConfig;
    }

    protected static function initDb()
    {
        $configPath = __DIR__ . '/../../../config/config.json';

        if (!file_exists($configPath)) {
            // Kein config → Installer-Mode aktiv
            self::$sharedDb = null;
            return;
        }

        $configData = json_decode(file_get_contents($configPath), true);

        if (!isset($configData['database'])) {
            die("Error: 'database' section missing in config.json");
        }

        $dbConfig = $configData['database'];

        self::$sharedDb = new \ckvsoft\Database([
            'type' => $dbConfig['type'],
            'host' => $dbConfig['host'],
            'name' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'pass' => $dbConfig['pass']
        ]);
    }

    public static function db()
    {
        if (self::$sharedDb === null) {
            self::initDb();
        }
        return self::$sharedDb;
    }
}
