<?php

/*
  CREATE TABLE IF NOT EXISTS `progress_bars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL DEFAULT 'default',
  `percent` int(11) NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 */

class Backup_Model extends \ckvsoft\mvc\Model
{

    public $model;
    private $source_folder;
    private $destination_folder;
    private $backup_log_file;
    private $progress;
    private $count;

    /**
     * Konstruktor
     *
     * @param string $source_folder Quellordner für Images
     * @param string $destination_folder Zielordner für Backup
     */
    public function __construct($source_folder = "", $destination_folder = "")
    {
        set_time_limit(0);
        $this->source_folder = rtrim($source_folder, "/") . "/";
        $this->destination_folder = "var/" . rtrim($destination_folder, "/") . "/";
        $this->backup_log_file = 'backup.log';
        parent::__construct();
    }

    /**
     * Letztes Backup abrufen
     *
     * @param int $id Progress-Bar ID
     * @return string|null Timestamp des letzten Backups
     */
    public function lastBackup($id)
    {
        $result = $this->db->select(
                "SELECT modified FROM progress_bars WHERE id = :id",
                ['id' => $id]
        );

        return !empty($result) ? $result[0]['modified'] : null;
    }

    /**
     * Datenbank sichern
     *
     * @param int $progress_id Progress-Bar ID
     * @return string JSON-Daten aller Tabellen
     */
    public function backupDatabase($progress_id)
    {
        $tables = $this->db->showTables();
        $backup = [];
        $rowcount = 0;

        // Gesamtzahl Zeilen für Progress-Bar berechnen
        foreach ($tables as $tableName) {
            $countResult = $this->db->select("SELECT COUNT(*) as rowcount FROM $tableName");
            if (!empty($countResult) && isset($countResult[0]['rowcount'])) {
                $rowcount += (int) $countResult[0]['rowcount'];
            }
        }

        $this->progress = new \ckvsoft\Progress($rowcount, $progress_id, $this->db);

        foreach ($tables as $tableName) {
            $result = $this->db->select("SELECT * FROM $tableName");

            $tableArray = [];
            $tableArray['name'] = $tableName;
            $tableArray['fields'] = [];
            $tableArray['rows'] = [];

            // Tabellenstruktur sichern
            $row2 = $this->db->select("SHOW CREATE TABLE $tableName");
            $tableArray['create_table_sql'] = (!empty($row2) && isset($row2[0]['Create Table'])) ? $row2[0]['Create Table'] : '';

            // Spaltennamen und Daten übernehmen
            if (!empty($result) && isset($result[0]) && is_array($result[0])) {
                $tableArray['fields'] = array_keys($result[0]);
                $tableArray['rows'] = $result;
            }

            $backup[] = $tableArray;

            // Fortschritt hochzählen
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->progress->increment();
                    usleep(30000);
                }
            }
        }

        return json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Images sichern
     *
     * @param int $progress_id Progress-Bar ID
     * @return bool Erfolg
     */
    public function backupImages($progress_id): bool
    {
        $total_files = $this->countFilesToCopy();
        $this->progress = new \ckvsoft\Progress($total_files, $progress_id, $this->db);

        return $this->recurseCopy($this->source_folder, $this->destination_folder, $this->progress);
    }

    /**
     * Rekursives Kopieren von Dateien
     *
     * @param string $source_folder Quellordner
     * @param string $destination_folder Zielordner
     * @param object $progress Progress-Objekt
     * @return bool Erfolg
     */
    private function recurseCopy($source_folder, $destination_folder, $progress): bool
    {
        $backup_log = [];
        $logFile = rtrim($destination_folder, "/") . "/" . $this->backup_log_file;

        if (!is_dir(dirname($logFile)))
            mkdir(dirname($logFile), 0777, true);
        if (file_exists($logFile))
            $backup_log = json_decode(file_get_contents($logFile), true);

        $baseDir = realpath($source_folder);
        $dstRoot = realpath($destination_folder);

        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_folder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir())
                continue;

            $srcPath = $file->getPathname();
            $relPath = ltrim(str_replace($baseDir, '', $srcPath), DIRECTORY_SEPARATOR);
            if (empty($relPath))
                continue;

            $dstPath = rtrim($destination_folder, '/') . '/' . $relPath;
            $srcReal = realpath($srcPath);
            if ($srcReal !== false && strpos($srcReal, $dstRoot) === 0)
                continue;
            if (!@getimagesize($srcPath))
                continue;

            if (isset($backup_log[$relPath]) && filemtime($srcPath) <= $backup_log[$relPath])
                continue;
            if (!file_exists(dirname($dstPath)))
                mkdir(dirname($dstPath), 0777, true);
            if (!copy($srcPath, $dstPath))
                throw new \ckvsoft\CkvException("Failed to copy file: $srcPath");

            $backup_log[$relPath] = filemtime($srcPath);
            $progress->increment();
            usleep(3000);
        }

        file_put_contents($logFile, json_encode($backup_log));
        return true;
    }

    /**
     * Dateien zählen
     *
     * @return int Anzahl Dateien
     */
    private function countFilesInFolder($folder): int
    {
        $backup_log = [];
        $logFile = rtrim($this->destination_folder, "/") . "/" . $this->backup_log_file;
        if (file_exists($logFile))
            $backup_log = json_decode(file_get_contents($logFile), true);

        $files = scandir($folder);
        $total_files = 0;

        foreach ($files as $filename) {
            if (in_array($filename, ['.', '..']))
                continue;
            $filepath = rtrim($folder, "/") . "/" . $filename;

            if (is_dir($filepath)) {
                $total_files += $this->countFilesInFolder($filepath);
            } else {
                if (!@getimagesize($filepath))
                    continue;
                if (isset($backup_log[$filename]) && filemtime($filepath) <= $backup_log[$filename])
                    continue;
                $total_files++;
            }
        }

        return $total_files;
    }

    /**
     * Hilfsfunktion: zählt alle zu kopierenden Dateien
     *
     * @return int
     */
    public function countFilesToCopy(): int
    {
        return $this->countFilesInFolder($this->source_folder);
    }

    /**
     * Daten in Datei speichern
     *
     * @param string $data JSON-Daten
     * @param string $file_name Dateiname
     * @return bool|string true oder Fehlermeldung
     */
    public function saveToFile($data, $file_name)
    {
        try {
            $handle = fopen($this->destination_folder . $file_name, 'w+');
            if ($handle === false)
                throw new \ckvsoft\CkvException('Failed to open file for writing.');

            if (fwrite($handle, $data) === false)
                throw new \ckvsoft\CkvException('Failed to write to file.');
            if (fclose($handle) === false)
                throw new \ckvsoft\CkvException('Failed to close file handle.');
        } catch (\ckvsoft\CkvException $e) {
            return 'Error: ' . $e->getMessage();
        }

        return true;
    }

    /**
     * JSON-Daten importieren
     *
     * @param string $json_data JSON-Daten
     * @return bool
     */
    public function importJSON($json_data): bool
    {
        $tables = json_decode($json_data, true);
        if (empty($tables))
            throw new \ckvsoft\CkvException("Import Error: JSON ist leer oder ungültig.");

        foreach ($tables as $table) {
            if (!isset($table['name'], $table['fields'], $table['rows']))
                continue;

            $tableName = $table['name'];
            $fields = $table['fields'];
            $rows = $table['rows'];

            if (empty($fields) || empty($rows))
                continue;

            foreach ($rows as $row) {
                $keys = [];
                $values = [];

                foreach ($fields as $field) {
                    $keys[] = "`$field`";
                    $value = $row[$field] ?? null;
                    $values[] = ($value === null) ? "NULL" : "'" . $this->db->escape($value) . "'";
                }

                $query = "INSERT INTO `$tableName` (" . implode(',', $keys) . ")
                          VALUES (" . implode(',', $values) . ")";
                $this->db->query($query);
            }
        }

        return true;
    }

    /**
     * Fortschritt abfragen
     *
     * @param int $id Progress-Bar ID
     * @return array
     */
    public function progress($id): array
    {
        return $this->db->select("SELECT percent FROM progress_bars WHERE id = :id", ['id' => $id]);
    }
}
