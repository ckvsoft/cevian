<?php

namespace ckvsoft;

use PDO;
use Exception;

class ModuleManager extends \ckvsoft\mvc\Config
{

    private \ckvsoft\Database $db;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Load module.json for a given module
     */
    public function loadConfig(string $module): ?array
    {
        $paths = [
            __DIR__ . "/../../" . CORE_MODULES_URI . "/{$module}/module.json",
            __DIR__ . "/../../" . MODULES_URI . "/{$module}/module.json",
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
     */
    public function getModuleDb(string $module): ?\ckvsoft\Database
    {
        $config = $this->loadConfig($module);

        if (!$config || !isset($config['database'])) {
            // No own DB → use shared framework DB
            return $this->db();
        }

        $dbConfig = $config['database'];
        return new \ckvsoft\Database([
            'type' => $dbConfig['type'],
            'host' => $dbConfig['host'],
            'name' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'pass' => $dbConfig['pass']
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
                ON DUPLICATE KEY UPDATE version = :v, core = :c, updated_at = NOW()
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
        $paths = [
            __DIR__ . "/../../" . CORE_MODULES_URI . "/{$module}/inc/sql",
            __DIR__ . "/../../" . MODULES_URI . "/{$module}/inc/sql",
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
