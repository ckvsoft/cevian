<?php

namespace ckvsoft\mvc;

use ckvsoft\Database;
use ckvsoft\ModulManager;

class Config
{

    /** @var \ckvsoft\Database|null The shared framework-wide DB instance (accessed via self::db()). */
    protected static ?\ckvsoft\Database $sharedDb = null;

    /** @var string|null Caches the detected module name for the current request. */
    protected static ?string $cachedModuleName = null;

    /** @var \ckvsoft\Database|null The instance-level DB connection (usually the framework connection). */
    protected ?Database $db;
    protected static ?array $appConfig = null;
    protected static ?array $mergedConfig = null;

    /** @var \ckvsoft\Database The module-specific DB instance (instance access via $this->moduleDb). */
    protected Database $moduleDb;

    /** @var \ckvsoft\Database|null The module’s shared static DB (used for static access, e.g. self::$moduleSharedDb). */
    protected static ?Database $moduleSharedDb = null;

    /** @var array Map of module names to their DB instances (e.g., ['pmwh3' => Database]). */
    private static array $moduleDbCache = [];

    /** @var array Map of module names to shared static DB connections (for concurrent modules). */
    protected static array $moduleSharedDbMap = [];

    public function __construct()
    {
        // Load merged app configuration
        $this->initMergedConfig();

        // Initialize the framework-level DB connection
        if (file_exists(__DIR__ . '/../../../config/config.json')) {
            $this->db = self::db();
        } else {
            $this->db = null; // Installer mode
        }

        // Initialize module-specific DB connection(s)
        $this->initializeModuleDbConnections();
    }

    /**
     * Writes a debug message to the log only if APP_DEBUG is enabled.
     */
    public static function logDebug(string $message): void
    {
        $debug = self::get('app.debug');
        if ($debug === true || $debug === 'true' || $debug === 1) {
            error_log($message);
        }
    }

    /**
     * Detects the module name by analyzing the call stack for a /modules/<name>/ path.
     * The expensive backtrace is only performed once per request.
     * @return string|null The detected module name (e.g. "pmwh3"), or null if not found.
     */
    public static function getModuleNameFromBacktrace(): ?string
    {
        // 1. Check cache first
        if (self::$cachedModuleName !== null) {
            return self::$cachedModuleName;
        }

        // 2. Perform expensive operation (only if cache is empty)
        $modulePattern = '#/(modules|core_modules)/([^/]+)/#i';
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $detectedModule = null;

        foreach ($trace as $step) {
            if (isset($step['file']) && preg_match($modulePattern, $step['file'], $matches)) {
                $detectedModule = strtolower($matches[2]);
                break;
            }
        }

        // 3. Cache the result for subsequent calls
        self::$cachedModuleName = $detectedModule;

        return $detectedModule;
    }

    /**
     * Retrieves (or creates) the correct Database instance for the current module.
     * Falls back to the shared framework DB if none is found.
     */
    protected function getModuleDbInstance(): Database
    {
        $moduleName = $this->getModuleNameFromBacktrace();
        $sharedDb = self::db();

        if (!isset(self::$moduleDbCache[$moduleName])) {
            $db = null;

            if ($moduleName !== null) {
                $coreUri = Config::get('paths.core_modules_uri');
                $modulesUri = Config::get('paths.modules_uri');
                $manager = new ModulManager(Config::db(), $coreUri, $modulesUri);
                $db = $manager->getModuleDb($moduleName);
            }

            // Fallback to the shared framework DB if no module-specific DB exists
            self::$moduleDbCache[$moduleName] = $db ?? $sharedDb;
        }

        return self::$moduleDbCache[$moduleName];
    }

    /**
     * Initializes $this->moduleDb and self::$moduleSharedDb for the active module.
     * Supports multiple modules concurrently.
     */
    protected function initializeModuleDbConnections(): void
    {
        $moduleName = $this->getModuleNameFromBacktrace();
        $moduleDbInstance = $this->getModuleDbInstance();
        $this->moduleDb = $moduleDbInstance;

        $moduleKey = $moduleName ?? '_default_';

        // Set or reuse module-specific shared DB
        if (!isset(self::$moduleSharedDbMap[$moduleKey])) {
            self::$moduleSharedDbMap[$moduleKey] = $moduleDbInstance;
            self::logDebug("🔄 moduleSharedDbMap[{$moduleKey}] initialized (module-specific).");
        }

        self::$moduleSharedDb = self::$moduleSharedDbMap[$moduleKey];
    }

    /**
     * Returns the shared DB instance for a specific module.
     * Falls back to the framework DB if not initialized.
     */
    public static function moduleDb(?string $moduleName = null): Database
    {
        $moduleKey = $moduleName ?: self::getModuleNameFromBacktrace();

        if (isset(self::$moduleSharedDbMap[$moduleKey])) {
            return self::$moduleSharedDbMap[$moduleKey];
        }

        return self::initModuleDbInternal($moduleKey);
    }

    // ---------------------------------------------------------------------
    // Existing configuration methods (unchanged, just clean comments)
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

    /**
     * Retrieves a nested configuration value using dot notation (e.g. "paths.logs_dir").
     */
    public static function get(string $key)
    {
        $config = self::getMergedConfig();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
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

        self::$sharedDb = new Database([
            'type' => $dbConfig['type'],
            'host' => $dbConfig['host'],
            'name' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'pass' => $dbConfig['pass'],
        ]);
    }

    /**
     * Retrieves the framework's shared DB instance.
     */
    public static function db(): ?Database
    {
        if (self::$sharedDb === null) {
            self::initDb();
        }
        return self::$sharedDb;
    }
}
