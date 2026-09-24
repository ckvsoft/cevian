<?php

namespace ckvsoft;

use PDO;
use Exception;
use ckvsoft\Database;

// NOTE: We assume CkvException is defined in the ckvsoft namespace.

class ModulManager
{

    private Database $db;
    private string $coreModulesUri;
    private string $modulesUri;

    public function __construct(Database $db, string $coreModulesUri, string $modulesUri)
    {
        $this->db = $db;
        $this->coreModulesUri = $coreModulesUri;
        $this->modulesUri = $modulesUri;
    }

    /**
     * Load module.json for a given module
     */
    public function loadConfig(string $module): ?array
    {
        // Core = geteilter Framework-Baum, Modules = Site-Docroot
        $paths = [
            \ckvsoft\Paths::coreModulesDir() . $module . '/module.json',
            \ckvsoft\Paths::siteRoot() . trim($this->modulesUri, '/') . '/' . $module . '/module.json',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return json_decode(file_get_contents($path), true);
            }
        }
        return null;
    }

    /**
     * Get module information (from DB registry or module.json if not installed)
     */
    public function getModuleInfo(string $module): array
    {
        $stmt = $this->db->prepare("SELECT * FROM modules WHERE name = :m");
        $stmt->execute([':m' => $module]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        // Not installed yet → fallback to JSON
        $json = $this->loadConfig($module);
        return $json ?? [];
    }

    /**
     * Get the correct DB connection for a module
     *
     * @param string $module Module name.
     * @param string|null $configPath Optional dotted path to a database
     *        node inside module.json (e.g. 'dns.database', 'mail.database').
     *        Default/null = the top-level 'database' node (historical
     *        behaviour). Falls back to the shared framework DB when
     *        the resolved node is missing or incomplete.
     */
    public function getModuleDb(string $module, ?string $configPath = null): ?\ckvsoft\Database
    {
        $config = $this->loadConfig($module);

        $dbConfig = [];
        if ($config) {
            if ($configPath !== null) {
                // Dotted-path lookup keeps the same semantics as
                // Config::module() (defaults to 'database' when null).
                $node = $config;
                foreach (explode('.', $configPath) as $part) {
                    if (!is_array($node) || !isset($node[$part])) {
                        $node = null;
                        break;
                    }
                    $node = $node[$part];
                }
                $dbConfig = is_array($node) ? $node : [];
            } else {
                $dbConfig = $config['database'] ?? [];
            }
        }

        if (empty($dbConfig['type']) || empty($dbConfig['host']) || empty($dbConfig['name'])) {
            // No own DB → use shared framework DB instance passed in constructor
            return $this->db;
        }

        return new \ckvsoft\Database([
            'type' => $dbConfig['type'],
            'host' => $dbConfig['host'],
            'name' => $dbConfig['name'],
            'user' => $dbConfig['user'] ?? '',
            'pass' => $dbConfig['pass'] ?? '',
            'port' => $dbConfig['port'] ?? null,
        ]);
    }

    /**
     * Check if a module is enabled
     */
    public function isEnabled(string $module): bool
    {
        $stmt = $this->db->prepare("SELECT enabled FROM modules WHERE name = :m");
        $stmt->execute([':m' => $module]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Install or update a module (runs migrations if needed)
     */
    public function installOrUpdate(string $module): void
    {
        $config = $this->loadConfig($module);
        if (!$config) {
            throw new \ckvsoft\CkvException("Module config not found: {$module}");
        }

        $this->db->beginTransaction();
        try {
            // Insert or update registry entry
            $stmt = $this->db->prepare("
                INSERT INTO modules (name, version, core, enabled)
                VALUES (:n, :v, :c, 1)
                ON DUPLICATE KEY UPDATE version = :v, core = :c
            ");
            $stmt->execute([
                ':n' => $config['name'],
                ':v' => $config['version'],
                ':c' => !empty($config['core']) ? 1 : 0,
            ]);

            // Run migrations if present
            $this->applyMigrations($module);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apply migrations for a module (from /inc/sql/*.sql)
     */
    private function applyMigrations(string $module): void
    {
        // Core = geteilter Framework-Baum, Modules = Site-Docroot
        $paths = [
            \ckvsoft\Paths::coreModulesDir() . $module . '/inc/sql',
            \ckvsoft\Paths::siteRoot() . trim($this->modulesUri, '/') . '/' . $module . '/inc/sql',
        ];

        $migrationPath = null;
        foreach ($paths as $p) {
            if (is_dir($p)) {
                $migrationPath = $p;
                break;
            }
        }
        if (!$migrationPath) {
            return; // no migrations
        }

        $files = glob($migrationPath . "/*.sql");
        sort($files);

        foreach ($files as $file) {
            $migration = basename($file);

            // Skip already applied migrations
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE module_name = :m AND migration = :mig");
            $stmt->execute([':m' => $module, ':mig' => $migration]);
            if ($stmt->fetchColumn() > 0) {
                continue;
            }

            $modulDb = $this->getModuleDb($module);

            $sql = file_get_contents($file);
            $modulDb->exec($sql);

            $stmt = $this->db->prepare("INSERT INTO migrations (module_name, migration) VALUES (:m, :mig)");
            $stmt->execute([':m' => $module, ':mig' => $migration]);
        }
    }
}
