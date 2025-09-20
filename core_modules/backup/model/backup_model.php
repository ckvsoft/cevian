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

    public function __construct($source_folder = "", $destination_folder = "")
    {
        // Set the maximum execution time to unlimited
        set_time_limit(0);
        $this->source_folder = rtrim($source_folder, "/") . "/";
        $this->destination_folder = "var/" . rtrim($destination_folder, "/") . "/";
        $this->backup_log_file = 'backup.log';
        parent::__construct();
    }

    public function lastBackup($id)
    {
        $result = $this->db->select("SELECT modified FROM progress_bars WHERE id=" . $id);
        return $result;
    }

    public function backupDatabase($progress_id)
    {
        $tables = $this->db->showTables();
        $backup = array();
        $rowcount = 0;
        foreach ($tables as $tableName) {
            $rowcount += $this->db->select("SELECT COUNT(*) as rowcount FROM $tableName")[0]['rowcount'];
        }

        $this->progress = new \ckvsoft\Progress($rowcount, $progress_id, $this->db);

        foreach ($tables as $tableName) {
            $result = $this->db->select("SELECT * FROM $tableName");

            $tableArray = array();
            $tableArray['name'] = $tableName;
            $tableArray['fields'] = array();

            $row2 = $this->db->select("SHOW CREATE TABLE $tableName");
            $tableArray['create_table_sql'] = $row2[0]['Create Table'];

            foreach ($result[0] as $fieldName) {
                $tableArray['fields'][] = $fieldName;
            }

            $tableArray['rows'] = $result;

            $backup[] = $tableArray;
            // Simulate rows ... Wait for 30ms
            for ($i = 0; $i <= count($result); $i++) {
                // $this->progress->addToCurrent(count($result));
                $this->progress->increment();
                usleep(30000);
            }
            // sleep(2);
        }

        $json_data = json_encode($backup);
        return $json_data;
    }

    public function backupImages($progress_id): int
    {

        $total_files = $this->countFilesToCopy();
        $progress = new \ckvsoft\Progress($total_files, $progress_id, $this->db);

        return $this->recurseCopy($this->source_folder, $this->destination_folder, $progress);
    }

public function recurseCopy($source_folder, $destination_folder, $progress)
    {
        $backup_log = [];
        $logFile = rtrim($destination_folder, "/") . "/" . $this->backup_log_file;

        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0777, true)) {
                throw new \ckvsoft\CkvException("Failed to create log directory: " . $logDir);
            }
        }

        if (file_exists($logFile)) {
            $backup_log = json_decode(file_get_contents($logFile), true);
        }

        $baseDir = realpath($this->source_folder); // Quell-Root merken
        $dstRoot = realpath($destination_folder);  // Ziel-Root merken

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $srcPath = $file->getPathname();

            // relativer Pfad ab Quell-Root
            $relPath = ltrim(str_replace($baseDir, '', $srcPath), DIRECTORY_SEPARATOR);

            // Zielpfad
            $dstPath = rtrim($destination_folder, '/') . '/' . $relPath;

            // Skip: nicht ins Backup-Ziel kopieren
            $srcReal = realpath($srcPath);
            if ($srcReal !== false && strpos($srcReal, $dstRoot) === 0) {
                continue;
            }

            // Nur echte Bilder
            if (!@getimagesize($srcPath)) {
                continue;
            }

            // DEBUG: Eine Datei wurde als Bild identifiziert
            // error_log("Bild gefunden, wird kopiert: " . $srcPath);

            // Nur wenn neuer oder geändert
            if (isset($backup_log[$relPath]) && filemtime($srcPath) <= $backup_log[$relPath]) {
                continue;
            }

            // Zielordner anlegen
            if (!file_exists(dirname($dstPath))) {
                mkdir(dirname($dstPath), 0777, true);
            }

            // Datei kopieren
            if (!copy($srcPath, $dstPath)) {
                throw new \ckvsoft\CkvException("Failed to copy file: " . $srcPath);
            }

            // Log aktualisieren
            $backup_log[$relPath] = filemtime($srcPath);

            $progress->increment();
            usleep(3000);
        }

        return file_put_contents($logFile, json_encode($backup_log));
    }
    
    private function countFilesInFolder($folder)
    {
        // Read the backup log (if it exists)
        $backup_log = [];
        if (file_exists(rtrim($this->destination_folder, "/") . "/" . $this->backup_log_file)) {
            $backup_log = json_decode(file_get_contents(rtrim($this->destination_folder, "/") . "/" . $this->backup_log_file), true);
        }

        $files = scandir($folder);
        $total_files = 0;

        foreach ($files as $filename) {
            if (in_array($filename, ['.', '..'])) {
                continue;
            }

            $filepath = rtrim($folder, "/") . "/" . $filename;

            if (is_dir($filepath)) {
                // Recursively count files in subfolders
                $total_files += $this->countFilesInFolder($filepath);
            } else {
                // Nur echte Bilder zählen
                if (!@getimagesize($filepath)) {
                    continue;
                }

                // Skip files that are already backed up and haven't been modified since
                if (isset($backup_log[$filename]) && filemtime($filepath) <= $backup_log[$filename]) {
                    continue;
                }

                $total_files++;
            }
        }

        return $total_files;
    }

    public function countFilesToCopy()
    {
        $total_files = $this->countFilesInFolder($this->source_folder);
        return $total_files;
    }

    public function saveToFile($data, $file_name)
    {
        try {
            $handle = fopen($this->destination_folder . $file_name, 'w+');
            if ($handle === false) {
                throw new \ckvsoft\CkvException('Failed to open file for writing.');
            }

            $result = fwrite($handle, $data);
            if ($result === false) {
                throw new \ckvsoft\CkvException('Failed to write to file.');
            }

            $closed = fclose($handle);
            if ($closed === false) {
                throw new \ckvsoft\CkvException('Failed to close file handle.');
            }
        } catch (\ckvsoft\CkvException $e) {
            // Handle the error here, e.g. log it or display a message to the user.
            return 'Error: ' . $e->getMessage();
        }
        return true;
    }

    public function jsonToCsv($json_data)
    {
        $data = json_decode($json_data, true);
        $csv = '';

        if (!empty($data)) {
            $header = array_keys($data[0]);
            $csv .= implode(',', $header) . "\n";

            foreach ($data as $row) {
                $csv .= implode(',', $row) . "\n";
            }
        }

        return $csv;
    }

    public function importCSV($table, $filename, $delimiter = ',', $enclosure = '"')
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            die("<div>Import Error: <b>$filename</b> does not exist or is not readable</div>");
        }

        $header = NULL;
        $data = array();

        if (($handle = fopen($filename, 'r')) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, $delimiter, $enclosure)) !== FALSE) {
                if (!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }

        foreach ($data as $row) {
            $keys = array();
            $values = array();

            foreach ($row as $key => $value) {
                $keys[] = "`$key`";
                $values[] = "'" . $this->db->escape($value) . "'";
            }

            $query = "INSERT INTO $table (implode(',', $keys)) VALUES (implode(',', $values))";
            $this->db->query($query);
        }
    }

    public function progress($id)
    {
        return $this->db->select("SELECT percent FROM progress_bars WHERE id=" . $id);
    }
}
