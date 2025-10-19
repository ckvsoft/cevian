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
     * Constructor
     *
     * @param string $source_folder Source folder for images
     * @param string $destination_folder Destination folder for the backup
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
     * Retrieve the last backup timestamp
     *
     * @param int $id Progress-Bar ID
     * @return string|null Timestamp of the last backup
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
     * Backup the database
     *
     * @param int $progress_id Progress-Bar ID
     * @return string JSON data of all tables
     */
    public function backupDatabase($progress_id)
    {
        $tables = $this->db->showTables();
        $backup = [];
        $rowcount = 0;

        // Calculate total number of rows for the progress bar
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

            // Backup table structure
            $row2 = $this->db->select("SHOW CREATE TABLE $tableName");
            $tableArray['create_table_sql'] = (!empty($row2) && isset($row2[0]['Create Table'])) ? $row2[0]['Create Table'] : '';

            // Get column names and data
            if (!empty($result) && isset($result[0]) && is_array($result[0])) {
                $tableArray['fields'] = array_keys($result[0]);
                $tableArray['rows'] = $result;
            }

            $backup[] = $tableArray;

            // Increment progress
            if (!empty($result)) {
                foreach ($result as $row) {
                    $this->progress->increment();
                }
            }
        }

        return json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Backup images
     *
     * @param int $progress_id Progress-Bar ID
     * @return bool Success status
     */
    public function backupImages($progress_id): bool
    {
        $total_files = $this->countFilesToCopy();
        $this->progress = new \ckvsoft\Progress($total_files, $progress_id, $this->db);

        return $this->recurseCopy($this->source_folder, $this->destination_folder, $this->progress);
    }

    /**
     * Recursive file copying (Differential Backup)
     *
     * @param string $source_folder Source folder
     * @param string $destination_folder Destination folder
     * @param object $progress Progress object
     * @return bool Success status
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

            // Check if file is already backed up and hasn't changed (filemtime <= log timestamp)
            if (isset($backup_log[$relPath]) && filemtime($srcPath) <= $backup_log[$relPath])
                continue;

            if (!file_exists(dirname($dstPath)))
                mkdir(dirname($dstPath), 0777, true);
            if (!copy($srcPath, $dstPath))
                throw new \ckvsoft\CkvException("Failed to copy file: $srcPath");

            $backup_log[$relPath] = filemtime($srcPath);
            $progress->increment();
        }

        file_put_contents($logFile, json_encode($backup_log));
        return true;
    }

    /**
     * Helper function: Counts all files that ACTUALLY NEED TO BE COPIED
     * (Differential counting based on backup log).
     *
     * @return int
     */
    public function countFilesToCopy(): int
    {
        $total_files_to_copy = 0;

        // Load backup log
        $backup_log = [];
        $logFile = rtrim($this->destination_folder, "/") . "/" . $this->backup_log_file;
        if (file_exists($logFile)) {
            $backup_log = json_decode(file_get_contents($logFile), true);
        }

        // Get base directory for relative path calculation
        $baseDir = realpath($this->source_folder);

        // Iterate over all files in the source folder (recursively)
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->source_folder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $srcPath = $file->getPathname();
            // Critical: Get the relative path (used as key in the log)
            $relPath = ltrim(str_replace($baseDir, '', $srcPath), DIRECTORY_SEPARATOR);

            // 1. Is it an image?
            if (!@getimagesize($srcPath)) {
                continue;
            }

            // 2. Check if the file is already backed up and unchanged
            // Condition: filemtime (source) > backup_log (last backup) -> MUST be copied
            if (isset($backup_log[$relPath]) && filemtime($srcPath) <= $backup_log[$relPath]) {
                // File is unchanged, skip counting
                continue;
            }

            // If reached here, the file needs to be copied
            $total_files_to_copy++;
        }

        return $total_files_to_copy;
    }

    // NOTE: The previous private function countFilesInFolder($folder) has been replaced
    // by the corrected logic in countFilesToCopy() and removed.

    /**
     * Save data to a file
     *
     * @param string $data JSON data
     * @param string $file_name Filename
     * @return bool|string true or error message
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
     * Import JSON data into the database
     *
     * @param string $json_data JSON data
     * @return bool
     */
    public function importJSON($json_data): bool
    {
        $tables = json_decode($json_data, true);
        if (empty($tables))
            throw new \ckvsoft\CkvException("Import Error: JSON is empty or invalid.");

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
     * @param int $id Progress-Bar ID
     * @return array
     */
    public function progress($id): array
    {
        return \ckvsoft\Progress::getStatus($id, $this->db);
    }
}
