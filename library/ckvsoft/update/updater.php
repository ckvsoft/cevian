<?php

namespace ckvsoft\Update;

/**
 * Generic SQL migration runner.
 *
 * Originally only used for framework-level migrations. Now also runs
 * per-module migrations from {modules,core_modules}/<module>/inc/sql/*.sql.
 *
 * The constructor selects the scope:
 *   new Updater()              -> framework: library/ckvsoft/update/sql/*.sql
 *                                 tracked as module_name = '_core_'
 *   new Updater('pmwh3')       -> module:    modules/pmwh3/inc/sql/*.sql
 *                                 tracked as module_name = 'pmwh3'
 *
 * Migration tracking lives in the `migrations` table:
 *   PRIMARY KEY would be (module_name, migration); the table itself is
 *   created by the framework's first migration.
 *
 * Version bookkeeping lives in var/update.json:
 *   {
 *     "framework_updated_version": "1.2.3",
 *     "modules": {
 *       "pmwh3": "3.0.5",
 *       "blog":  "0.4.1"
 *     }
 *   }
 *
 * Files are sorted alphabetically; using semver-like names ensures the
 * intended order (e.g. 0.0.0_baseline.sql, 3.0.3.sql, 3.0.4.sql, ...).
 *
 * Each migration runs inside its own transaction. A failed migration
 * is rolled back; subsequent files are not attempted in that same
 * pass (so the admin can fix the cause and reload).
 *
 * Side effect: when an _initial_ install completes (the version was
 * '0.0.0' when this run started), a marker file is dropped:
 *   var/<module>_freshly_installed.flag
 * This allows the module to detect a first-time install and offer
 * legacy data migration on its next page load. Marker files are
 * NOT deleted by the Updater -- the module clears them itself when
 * the user has been informed.
 */
class Updater extends \ckvsoft\mvc\Config
{

    protected string $configPath;
    protected array $config;

    /** @var string '_core_' for framework, module name otherwise. */
    protected string $scope;

    /** Absolute path to the SQL directory for the active scope. */
    protected string $sqlDir;

    /** True when this run is the very first install for the scope. */
    protected bool $isFreshInstall = false;

    /**
     * @param string|null $module     Module name (e.g. 'pmwh3'). Null = framework.
     * @param string      $configPath Path to update.json. Default lives in var/.
     */
    public function __construct(?string $module = null, string $configPath = __DIR__ . '/../../../var/update.json')
    {
        parent::__construct();
        $this->configPath = $configPath;

        // Bugfix: Config::__construct() sets $this->db = framework
        // shared DB but does NOT initialize $this->moduleDb -- that
        // happens lazily on first moduleDb() call. For module-scoped
        // updates we want migration statements to land in the
        // module's own DB (if it has one), not the framework DB.
        // Modules without their own database block in module.json
        // fall back to the framework DB, so this is non-breaking
        // for core modules (rbac, user, etc.).
        if ($module !== null && $module !== '_core_') {
            error_log("[Updater] scope='{$module}': resolving module DB via ModulManager");
            try {
                $coreUri    = self::get('paths.core_modules_uri');
                $modulesUri = self::get('paths.modules_uri');
                $manager    = new \ckvsoft\ModulManager(self::db(), $coreUri, $modulesUri);
                $resolved   = $manager->getModuleDb($module);
                if ($resolved !== null) {
                    $this->moduleDb = $resolved;
                    error_log("[Updater] scope='{$module}': using module-specific DB");
                } else {
                    error_log("[Updater] scope='{$module}': no module.json database block, using framework DB");
                }
            } catch (\Throwable $e) {
                error_log("[Updater] scope='{$module}': failed to resolve module DB: " . $e->getMessage());
                // Don't rethrow -- fall back to framework DB so the
                // updater can still run for modules that share the
                // framework DB.
            }
        }

        // Load or initialize state file.
        if (file_exists($configPath)) {
            $raw = file_get_contents($configPath);
            $decoded = json_decode($raw, true);
            $this->config = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : [];
        } else {
            $this->config = [];
        }

        // Migrate older flat-format state files to the nested layout.
        if (!isset($this->config['modules']) || !is_array($this->config['modules'])) {
            $this->config['modules'] = [];
        }
        if (!isset($this->config['framework_updated_version'])) {
            $this->config['framework_updated_version'] = '0.0.0';
        }

        // Resolve scope and SQL dir.
        if ($module === null || $module === '_core_') {
            $this->scope  = '_core_';
            $this->sqlDir = __DIR__ . '/sql';
        } else {
            $this->scope  = $module;
            $this->sqlDir = $this->resolveModuleSqlDir($module);
        }

        $this->saveConfig();
    }

    /**
     * Find the inc/sql directory for a module. Looks in modules first,
     * then core_modules. Returns '' if neither exists (no migrations).
     */
    protected function resolveModuleSqlDir(string $module): string
    {
        $candidates = [
            __DIR__ . '/../../../' . trim(\MODULES_URI, '/')      . '/' . $module . '/inc/sql',
            __DIR__ . '/../../../' . trim(\CORE_MODULES_URI, '/') . '/' . $module . '/inc/sql',
        ];
        foreach ($candidates as $c) {
            if (is_dir($c)) {
                return $c;
            }
        }
        return '';
    }

    private function saveConfig(): void
    {
        @file_put_contents(
                $this->configPath,
                json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Code-side version: for _core_ from \ckvsoft\Version, for modules
     * from <module-dir>/config/version.php (constant pmwh3\Config\Version::VERSION etc.)
     * or fall back to module.json.
     *
     * Returns '0.0.0' when no version source can be found.
     */
    private function getCurrentVersion(): string
    {
        if ($this->scope === '_core_') {
            $full = \ckvsoft\Version::version();
            return explode('-', $full)[0];
        }

        // Try config/version.php constant first.
        $module = $this->scope;
        $candidates = [
            __DIR__ . '/../../../' . trim(\MODULES_URI, '/')      . '/' . $module . '/config/version.php',
            __DIR__ . '/../../../' . trim(\CORE_MODULES_URI, '/') . '/' . $module . '/config/version.php',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                require_once $c;
                $cls = ucfirst($module) . '\\Config\\Version';
                if (class_exists($cls) && defined($cls . '::VERSION')) {
                    return explode('-', (string) constant($cls . '::VERSION'))[0];
                }
            }
        }

        // Fall back to module.json's "version".
        foreach ([
            __DIR__ . '/../../../' . trim(\MODULES_URI, '/')      . '/' . $module . '/module.json',
            __DIR__ . '/../../../' . trim(\CORE_MODULES_URI, '/') . '/' . $module . '/module.json',
        ] as $jsonPath) {
            if (is_file($jsonPath)) {
                $data = json_decode((string) file_get_contents($jsonPath), true);
                if (is_array($data) && !empty($data['version'])) {
                    return explode('-', (string) $data['version'])[0];
                }
            }
        }

        return '0.0.0';
    }

    private function getLastUpdatedVersion(): string
    {
        if ($this->scope === '_core_') {
            return $this->config['framework_updated_version'] ?? '0.0.0';
        }
        return $this->config['modules'][$this->scope] ?? '0.0.0';
    }

    private function setLastUpdatedVersion(string $version): void
    {
        if ($this->scope === '_core_') {
            $this->config['framework_updated_version'] = $version;
        } else {
            $this->config['modules'][$this->scope] = $version;
        }
        $this->saveConfig();
    }

    public function needsUpdate(): bool
    {
        if ($this->sqlDir === '') {
            error_log("[Updater] scope='{$this->scope}': sqlDir empty, no migrations possible");
            return false;
        }
        $current = $this->getCurrentVersion();
        $last    = $this->getLastUpdatedVersion();
        $needs   = version_compare($current, $last, '>');
        error_log("[Updater] scope='{$this->scope}': version current={$current} last={$last} needsUpdate=" . ($needs ? 'true' : 'false'));
        return $needs;
    }

    /**
     * Run all pending migrations for the active scope.
     * Returns true when at least one migration was applied.
     */
    public function runUpdate(): bool
    {
        if (!$this->needsUpdate()) {
            return false;
        }

        $this->isFreshInstall = ($this->getLastUpdatedVersion() === '0.0.0');

        $files = glob($this->sqlDir . '/*.sql') ?: [];
        sort($files);

        error_log("[Updater] scope='{$this->scope}': found " . count($files) . " SQL files in {$this->sqlDir}");

        $appliedAny = false;

        foreach ($files as $file) {
            $info = pathinfo($file);
            $migration = basename($file, '.' . ($info['extension'] ?? 'sql'));

            // Skip already applied migrations (track per module_name).
            $stmt = $this->db->query("SHOW TABLES LIKE 'migrations'");
            if ($stmt && $stmt->rowCount() !== 0) {
                $check = $this->db->prepare(
                        "SELECT COUNT(*) FROM migrations
                         WHERE module_name = :m AND migration = :mig"
                );
                $check->execute([':m' => $this->scope, ':mig' => $migration]);
                if ((int) $check->fetchColumn() > 0) {
                    error_log("[Updater] scope='{$this->scope}': skip {$migration} (already applied)");
                    continue;
                }
            }

            error_log("[Updater] scope='{$this->scope}': applying {$migration}");

            $sql = (string) file_get_contents($file);
            $statements = $this->splitSql($sql);

            // We deliberately don't wrap in beginTransaction() because
            // DDL (CREATE/ALTER/DROP TABLE) implicitly commits in MySQL/
            // MariaDB anyway. Wrapping would only give a false sense of
            // atomicity. Each statement runs on its own; the migrations-
            // table entry is only inserted when the whole file
            // succeeded, so partial failures show up as "not yet applied"
            // on the next run and the admin can fix and rerun.
            //
            // Target DB selection: migration statements run against the
            // module's own DB when set (see constructor), otherwise the
            // framework shared DB -- same as before. Bookkeeping (the
            // migrations table) always lives in the framework DB.
            $migrationDb = isset($this->moduleDb) ? $this->moduleDb : $this->db;
            error_log("[Updater] scope='{$this->scope}': {$migration} target=" . (isset($this->moduleDb) ? 'module-db' : 'framework-db') . " (" . count($statements) . " statements)");

            $stmtIndex = 0;
            try {
                foreach ($statements as $oneStmt) {
                    $stmtIndex++;
                    $migrationDb->exec($oneStmt);
                }
            } catch (\Throwable $e) {
                $msg = sprintf(
                        "Migration %s/%s failed at statement #%d: %s\n--- statement ---\n%s",
                        $this->scope, $migration, $stmtIndex, $e->getMessage(),
                        $statements[$stmtIndex - 1] ?? '<unknown>'
                );
                error_log($msg);
                throw new \RuntimeException($msg, 0, $e);
            }

            $ins = $this->db->prepare(
                    "INSERT INTO migrations (module_name, migration)
                     VALUES (:m, :mig)"
            );
            $ins->execute([':m' => $this->scope, ':mig' => $migration]);

            error_log("[Updater] scope='{$this->scope}': {$migration} applied successfully");

            $appliedAny = true;
        }

        if ($appliedAny) {
            $this->setLastUpdatedVersion($this->getCurrentVersion());

            // Drop fresh-install marker so the module can react on its
            // next page load.
            if ($this->isFreshInstall && $this->scope !== '_core_') {
                $varDir = __DIR__ . '/../../../var';
                if (is_dir($varDir) && is_writable($varDir)) {
                    @file_put_contents(
                            $varDir . '/' . $this->scope . '_freshly_installed.flag',
                            (string) time()
                    );
                }
            }
        }

        return $appliedAny;
    }

    /**
     * Split a SQL script into individual statements.
     *
     * Naive ';' splitting is wrong because semicolons can appear inside
     * quoted strings and comments. This implementation walks the input
     * once, tracking quote/comment state, and only treats a ';' as a
     * statement terminator when not inside a string or comment.
     *
     * Supports:
     *   - line comments (-- ... and # ...)
     *   - block comments (slash-star ... star-slash)
     *   - single- and double-quoted strings with backslash escapes
     *   - DELIMITER directive (for trigger/routine definitions)
     *
     * @return string[] Trimmed, non-empty statements (no trailing ';')
     */
    private function splitSql(string $sql): array
    {
        $stmts = [];
        $buf = '';
        $delim = ';';
        $i = 0;
        $len = strlen($sql);

        while ($i < $len) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            // Line comments: -- ... \n  and  # ... \n
            if (($ch === '-' && $next === '-') || $ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            // Block comments
            if ($ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }
            // Quoted strings (' or ")
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $buf .= $ch;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    $buf .= $c;
                    if ($c === '\\' && $i + 1 < $len) {
                        // include the escaped character verbatim
                        $buf .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($c === $quote) {
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // DELIMITER directive (line-based, MySQL-specific)
            if (($i === 0 || $sql[$i - 1] === "\n")
                    && stripos(substr($sql, $i, 9), 'DELIMITER') === 0) {
                // flush whatever's buffered as a statement first
                $trim = trim($buf);
                if ($trim !== '') {
                    $stmts[] = $trim;
                    $buf = '';
                }
                // read delim until end of line
                $i += 9;
                while ($i < $len && ($sql[$i] === ' ' || $sql[$i] === "\t")) {
                    $i++;
                }
                $newDelim = '';
                while ($i < $len && $sql[$i] !== "\n" && $sql[$i] !== "\r") {
                    $newDelim .= $sql[$i];
                    $i++;
                }
                $delim = trim($newDelim) !== '' ? trim($newDelim) : ';';
                continue;
            }

            // Custom delimiter check (multi-char support)
            $dlen = strlen($delim);
            if ($dlen > 0 && substr($sql, $i, $dlen) === $delim
                    && $delim !== ';') {
                $trim = trim($buf);
                if ($trim !== '') {
                    $stmts[] = $trim;
                }
                $buf = '';
                $i += $dlen;
                continue;
            }
            // Default ';' delimiter
            if ($delim === ';' && $ch === ';') {
                $trim = trim($buf);
                if ($trim !== '') {
                    $stmts[] = $trim;
                }
                $buf = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        $trim = trim($buf);
        if ($trim !== '') {
            $stmts[] = $trim;
        }

        return $stmts;
    }

    /** Public so the bootstrap can check after runUpdate(). */
    public function wasFreshInstall(): bool
    {
        return $this->isFreshInstall;
    }
}
