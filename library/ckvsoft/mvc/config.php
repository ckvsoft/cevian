<?php

namespace ckvsoft\mvc;

class Config
{
    protected static $sharedDb = null;
    protected static $appConfig = null;
    protected $db;

    public function __construct()
    {
        $this->db = self::db();
        self::getAppConfig();
    }

    // Neue Methode, um die App-Konfiguration zu laden und zu cachen
    public static function getAppConfig()
    {
        if (self::$appConfig === null) {
            $configPath = __DIR__ . '/../../../config/app.json';
            if (!file_exists($configPath)) {
                die("Error: Global App-Configurationfile '$configPath' not found!");
            }
            self::$appConfig = json_decode(file_get_contents($configPath), true);
        }
        return self::$appConfig;
    }

    // ... (deine bestehende initDb-Methode)

    protected static function initDb()
    {
        // JSON-Konfigurationsdatei lesen (oder eine separate db.json verwenden)
        $configPath = __DIR__ . '/../../../config/config.json';
        if (!file_exists($configPath)) {
                die("Error: Database-Configurationfile '$configPath' not found!");
        }
        $configData = json_decode(file_get_contents($configPath), true);
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