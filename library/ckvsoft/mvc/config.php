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
        if (file_exists(\ckvsoft\Paths::siteRoot() . 'config/config.json')) {
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

    /**
     * Build a Database from a named module.json node (dotted path,
     * e.g. 'dns.database', 'mail.database'). Returns the shared
     * framework DB when the node is missing/incomplete (same
     * fallback semantics as getModuleDbInstance()). Does NOT touch
     * the per-module caches of getModuleDbInstance -- named paths
     * have their own cache keys in moduleSharedDbMap.
     */
    private static function buildModuleDbForNode(?string $moduleName, string $configPath): Database
    {
        $sharedDb = self::db();

        if ($moduleName === null) {
            return $sharedDb;
        }

        $coreUri = self::get('paths.core_modules_uri');
        $modulesUri = self::get('paths.modules_uri');

        $manager = new ModulManager($sharedDb, $coreUri, $modulesUri);
        $db = $manager->getModuleDb($moduleName, $configPath);

        return $db ?? $sharedDb;
    }

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

    /**
     * Module DB access.
     *
     * Historical shapes (unchanged semantics):
     *   moduleDb()                    → backtrace module, top-level 'database' node
     *   moduleDb('pmwh3')             → named module, top-level 'database' node
     *
     * New shape (Etappe 1):
     *   moduleDb('pmwh3', 'dns.database')  → named module + dotted
     *     config node from module.json. Cached separately per
     *     (module, configPath) in moduleSharedDbMap.
     *
     * @param string|null $moduleName Module name; null = backtrace detection.
     * @param string|null $configPath Optional dotted path to a database
     *        node inside module.json. Omitted for the historical behaviour.
     */
    public static function moduleDb(?string $moduleName = null, ?string $configPath = null): Database
    {
        $moduleName ??= self::getModuleNameFromBacktrace();

        if ($configPath !== null) {
            $key = ($moduleName ?? '_default_') . ':' . $configPath;
            if (!isset(self::$moduleSharedDbMap[$key])) {
                self::$moduleSharedDbMap[$key] = self::buildModuleDbForNode($moduleName, $configPath);
            }
            return self::$moduleSharedDbMap[$key];
        }

        $moduleKey = $moduleName ?: self::getModuleNameFromBacktrace();

        if (!isset(self::$moduleSharedDbMap[$moduleKey])) {
            $instance = new self();
            $instance->initializeModuleDbConnections($moduleKey);
        }

        return self::$moduleSharedDbMap[$moduleKey];
    }

    /**
     * Create (and cache) a Database from explicit runtime
     * credentials -- for connections that can't be expressed in
     * module.json (e.g. DB-admin connections whose credentials come
     * from editable settings). Module code must use THIS instead of
     * `new PDO` or self-built `new Database` instances.
     *
     * Cache key is a stable hash of the credentials, so repeated
     * calls with the same creds return the same connection.
     *
     * @param array $creds ['type','host','name','user','pass','port'] --
     *        'name' optional (admin connections without dbname), 'port' optional.
     */
    public static function cachedDatabase(array $creds): Database
    {
        ksort($creds);
        $key = 'cred:' . md5(json_encode($creds));

        if (!isset(self::$moduleSharedDbMap[$key])) {
            self::$moduleSharedDbMap[$key] = new Database([
                'type' => $creds['type'] ?? 'mysql',
                'host' => $creds['host'] ?? 'localhost',
                'name' => $creds['name'] ?? null,
                'user' => $creds['user'] ?? '',
                'pass' => $creds['pass'] ?? '',
                'port' => $creds['port'] ?? null,
            ]);
        }

        return self::$moduleSharedDbMap[$key];
    }

    // ---------------------------------------------------------------------
    // App config
    // ---------------------------------------------------------------------
    public static function getAppConfig(): array
    {
        if (self::$appConfig === null) {
            $configPath = \ckvsoft\Paths::siteRoot() . 'config/app.json';
            self::$appConfig = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
        }

        return self::$appConfig;
    }

    protected static function initMergedConfig(): void
    {
        if (self::$mergedConfig === null) {
            // Framework-Defaults leben im geteilten Baum (shared-split);
            // standalone ist coreRoot == siteRoot.
            $defaultsPath = \ckvsoft\Paths::coreRoot() . 'config/app_defaults.json';
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
        $configPath = \ckvsoft\Paths::siteRoot() . 'config/config.json';

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
