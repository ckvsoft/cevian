<?php

namespace ckvsoft\mvc;

class Config
{

    protected static ?\ckvsoft\Database $sharedDb = null;
    protected static ?array $appConfig = null;
    protected static ?array $mergedConfig = null;
    protected $db;

    public function __construct()
    {
        // App Config laden & Mergen vorbereiten
        $this->initMergedConfig();

        // DB initialisieren
        if (file_exists(__DIR__ . '/../../../config/config.json')) {
            $this->db = self::db();
        } else {
            $this->db = null; // Installer Mode
        }

    }

    public static function getAppConfig(): array
    {
        if (self::$appConfig === null) {
            $configPath = __DIR__ . '/../../../config/app.json';
            self::$appConfig = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
        }
        return self::$appConfig;
    }

    protected static function initMergedConfig(): void
    {
        if (self::$mergedConfig === null) {
            $defaultsPath = __DIR__ . '/../../../config/app_defaults.json';
            $defaultConfig = file_exists($defaultsPath) ? json_decode(file_get_contents($defaultsPath), true) : [];
            $customConfig = self::getAppConfig();
            self::$mergedConfig = array_replace_recursive($defaultConfig, $customConfig);
        }
    }

    public static function getMergedConfig(): array
    {
        if (self::$mergedConfig === null) {
            self::initMergedConfig();
        }
        return self::$mergedConfig;
    }

    protected static function initDb()
    {
        $configPath = __DIR__ . '/../../../config/config.json';

        if (!file_exists($configPath)) {
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

    public static function db(): ?\ckvsoft\Database
    {
        if (self::$sharedDb === null) {
            self::initDb();
        }
        return self::$sharedDb;
    }
}
