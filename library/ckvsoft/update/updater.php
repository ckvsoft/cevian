<?php

namespace ckvsoft\Update;

class Updater extends \ckvsoft\mvc\Config
{

    protected string $configPath;
    protected array $config;

    public function __construct(string $configPath = __DIR__ . '/update.json')
    {
        parent::__construct();
        $this->configPath = $configPath;
        $this->config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
    }

    /**
     * Get framework version without build/hash
     */
    private function getCurrentVersion(): string
    {
        $fullVersion = \ckvsoft\Version::version(); // e.g., 0.6.4-250922 (git: e9a3b9f)
        return explode('-', $fullVersion)[0]; // returns 0.6.4
    }

    /**
     * Get last updated version from config
     */
    private function getLastUpdatedVersion(): string
    {
        return $this->config['framework_updated_version'] ?? '0.0.0';
    }

    /**
     * Check if update is needed
     */
    public function needsUpdate(): bool
    {
        return version_compare($this->getCurrentVersion(), $this->getLastUpdatedVersion(), '>');
    }

    /**
     * Run the framework update
     */
    public function runUpdate(): bool
    {
        if (!$this->needsUpdate()) {
            return false;
        }

        $files = glob(__DIR__ . "/sql/*.sql");
        sort($files);

        try {
            foreach ($files as $file) {
                $info = pathinfo($file);
                $migration = basename($file, '.' . $info['extension']);

                // Check if already applied
                $stmt = $this->db->query("SHOW TABLES LIKE 'migrations'");
                if ($stmt->rowCount() !== 0) {
                    $stmt = $this->db->prepare("SELECT COUNT(*) FROM migrations WHERE module_name = :m AND migration = :mig");
                    $stmt->execute([':m' => '_core_', ':mig' => $migration]);
                    if ($stmt->fetchColumn() > 0) {
                        continue;
                    }
                }

                $this->db->beginTransaction();

                $sql = file_get_contents($file);
                $this->db->exec($sql);

                $stmt = $this->db->prepare("INSERT INTO migrations (module_name, migration) VALUES (:m, :mig)");
                $stmt->execute([':m' => '_core_', ':mig' => $migration]);
                $this->db->commit();
            }
        } catch (\ckvsoft\CkvException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                throw $e;
            }
        }

        // Save updated version to config
        $this->config['framework_updated_version'] = $this->getCurrentVersion();
        file_put_contents($this->configPath, json_encode($this->config, JSON_PRETTY_PRINT));

        return true;
    }
}
