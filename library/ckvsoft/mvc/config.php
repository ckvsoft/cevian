<?php

namespace ckvsoft\mvc;

use ckvsoft\Database;
use ckvsoft\ModulManager;

class Config
{

    // ---------------------------------------------------------------------
    // Shared Framework DB
    // ---------------------------------------------------------------------
    protected static ?Database $sharedDb = null;
    // ---------------------------------------------------------------------
    // Module detection/cache
    // ---------------------------------------------------------------------
    protected static ?string $cachedModuleName = null;
    protected static ?array $moduleConfigCache = null;
    // ---------------------------------------------------------------------
    // Instance-level DB (framework connection)
    // ---------------------------------------------------------------------
    protected ?Database $db = null;
    // ---------------------------------------------------------------------
    // Module-specific DB
    // ---------------------------------------------------------------------
    protected Database $moduleDb;
    protected static ?Database $moduleSharedDb = null;
    private static array $moduleDbCache = [];
    protected static array $moduleSharedDbMap = [];
    // ---------------------------------------------------------------------
    // Config caches
    // ---------------------------------------------------------------------
    protected static ?array $appConfig = null;
    protected static ?array $mergedConfig = null;

    public function __construct()
    {
        // Merged app configuration vorbereiten
        self::initMergedConfig();

        // Shared framework DB initialisieren
        if (file_exists(__DIR__ . '/../../../config/config.json')) {
            $this->db = self::db();
        } else {
            $this->db = null; // Installer-Modus
        }

        // Keine Modul-DB initialisieren – Lazy!
    }

    // ---------------------------------------------------------------------
    // Logging
    // ---------------------------------------------------------------------
    public static function logDebug(string $message): void
    {
        $debug = self::get('app.debug');
        if ($debug === true || $debug === 'true' || $debug === 1) {
            error_log($message);
        }
    }

    // ---------------------------------------------------------------------
    // Module name detection via backtrace
    // ---------------------------------------------------------------------
    public static function getModuleNameFromBacktrace(): ?string
    {
        if (self::$cachedModuleName !== null) {
            return self::$cachedModuleName;
        }

        $modulePattern = '#/(modules|core_modules)/([^/]+)/#i';
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $detectedModule = null;

        foreach ($trace as $step) {
            if (isset($step['file']) && preg_match($modulePattern, $step['file'], $matches)) {
                $detectedModule = strtolower($matches[2]);
                break;
            }
        }

        self::$cachedModuleName = $detectedModule;
        return $detectedModule;
    }

    // ---------------------------------------------------------------------
    // Module DB (lazy initialization)
    // ---------------------------------------------------------------------
    protected function getModuleDbInstance(?string $moduleName = null): Database
    {
        // When called explicitly with a name, honor it. Otherwise fall
        // back to backtrace detection (the historical behaviour --
        // critical to preserve so all the existing modules that call
        // moduleDb() without arguments keep working unchanged).
        $moduleName ??= self::getModuleNameFromBacktrace();
        $sharedDb = self::db();

        if (!isset(self::$moduleDbCache[$moduleName])) {
            $db = null;
            $moduleConfig = [];

            if ($moduleName !== null) {
                $coreUri = self::get('paths.core_modules_uri');
                $modulesUri = self::get('paths.modules_uri');

                $manager = new ModulManager($sharedDb, $coreUri, $modulesUri);
                $moduleConfig = $manager->loadConfig($moduleName);
                $db = $manager->getModuleDb($moduleName);
            }

            self::$moduleConfigCache[$moduleName] = $moduleConfig ?? [];
            self::$moduleDbCache[$moduleName] = $db ?? $sharedDb;
        }

        return self::$moduleDbCache[$moduleName];
    }

    public static function module(string $key, ?string $moduleName = null)
    {
        $moduleName ??= self::getModuleNameFromBacktrace();

        // Lazy-load: if the named module hasn't been initialized yet
        // (Config::moduleDb() was never called for it), do that now
        // so we get its module.json into $moduleConfigCache. Without
        // this, module() returns null for an entirely uninitialized
        // module even when the config file exists.
        if ($moduleName !== null && !isset(self::$moduleConfigCache[$moduleName])) {
            $instance = new self();
            $instance->getModuleDbInstance($moduleName);
        }

        $config = self::$moduleConfigCache[$moduleName] ?? null;

        if (!$config)
            return null;

        foreach (explode('.', $key) as $part) {
            if (!isset($config[$part]))
                return null;
            $config = $config[$part];
        }

        return $config;
    }

    protected function initializeModuleDbConnections(?string $moduleName = null): void
    {
        $moduleName ??= self::getModuleNameFromBacktrace();
        $moduleKey = $moduleName ?? '_default_';

        if (isset(self::$moduleSharedDbMap[$moduleKey])) {
            $this->moduleDb = self::$moduleSharedDbMap[$moduleKey];
            self::$moduleSharedDb = self::$moduleSharedDbMap[$moduleKey];
            return;
        }

        // Pass the resolved module name through so getModuleDbInstance
        // doesn't re-detect via backtrace (which gives the wrong answer
        // when one module's controller calls moduleDb() for a different
        // module's data, e.g. multilogin asking pmwh3 for its users).
        $moduleDbInstance = $this->getModuleDbInstance($moduleName);
        $this->moduleDb = $moduleDbInstance;
        self::$moduleSharedDbMap[$moduleKey] = $moduleDbInstance;
        self::$moduleSharedDb = $moduleDbInstance;

        self::logDebug("🔄 moduleSharedDbMap[{$moduleKey}] initialized (module-specific).");
    }

    public static function moduleDb(?string $moduleName = null): Database
    {
        $moduleKey = $moduleName ?: self::getModuleNameFromBacktrace();

        if (!isset(self::$moduleSharedDbMap[$moduleKey])) {
            $instance = new self();
            $instance->initializeModuleDbConnections($moduleKey);
        }

        return self::$moduleSharedDbMap[$moduleKey];
    }

    // ---------------------------------------------------------------------
    // App config
    // ---------------------------------------------------------------------
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

    public static function get(string $key)
    {
        $config = self::getMergedConfig();
        foreach (explode('.', $key) as $k) {
            if (!isset($config[$k]))
                return null;
            $config = $config[$k];
        }
        return $config;
    }

    // ---------------------------------------------------------------------
    // Shared framework DB
    // ---------------------------------------------------------------------
    protected static function initDb(): void
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

        self::$sharedDb = new Database([
            'type' => $dbConfig['type'],
            'host' => $dbConfig['host'],
            'name' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'pass' => $dbConfig['pass'],
        ]);
    }

    public static function db(): ?Database
    {
        if (self::$sharedDb === null) {
            self::initDb();
        }
        return self::$sharedDb;
    }
}
