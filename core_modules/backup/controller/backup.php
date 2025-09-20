<?php

class Backup extends ckvsoft\mvc\Controller
{

    public $model;

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged();
    }

    public function index()
    {
        $imagesBackup = $this->lastBackup(1);
        $databaseBackup = $this->lastBackup(2);

        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('dashboard/inc/header', ['menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('backup/index', [
            'images' => isset($imagesBackup[0]['modified']) ? $imagesBackup[0]['modified'] : null,
            'database' => isset($databaseBackup[0]['modified']) ? $databaseBackup[0]['modified'] : null,
        ]);
        $this->view->render('dashboard/inc/footer');
    }

    public function lastBackup($id)
    {
        $this->model = $this->loadModel('backup');
        $result = $this->model->lastBackup($id);
        return $result;
    }

    public function backupDatabase()
    {
        $timeLimit = 10;
        set_time_limit(60 * $timeLimit);

        ignore_user_abort(true);
        session_write_close();

        // Clear output buffer and save output; while loop handles potential multiple levels of output buffering.
        // you can skip this if you don't use output buffering
        $output = '';
        while (ob_get_level()) {
            $output .= ob_get_clean();
        }

        // Disable gzip compression in apache, as it can result in this request being buffered until it is complete,
        // regardless of other settings.
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', 1);
        }

        // If not redirecting, send appropriate headers and output.
        header('Connection: close');
        header('Content-length: ' . strlen($output));

        $this->model = $this->loadModel('backup', null, "", "backup/");
        $result = $this->model->saveToFile($this->model->backupDatabase(2), 'backup_' . date('Y-m-d_H-i-s') . '.json');
        if ($result === true)
            \ckvsoft\Output::success();
        else
            \ckvsoft\Output::error($result);
    }

    public function countFilesToCopy($directory)
    {
        $this->model = $this->loadModel('backup', null, $directory, "backup/images");
        $result = $this->model->countFilesToCopy();

        header('Content-Type: application/json');
        echo json_encode(['totalSize' => $result]);
    }

    /*
      public function backupFiles($directory)
      {
      $result = "";

      try {
      $timeLimit = 10;
      set_time_limit(60 * $timeLimit);

      ignore_user_abort(true);
      session_write_close();

      // Clear output buffer and save output; while loop handles potential multiple levels of output buffering.
      // you can skip this if you don't use output buffering
      $output = '';
      while (ob_get_level()) {
      $output .= ob_get_clean();
      }

      // Disable gzip compression in apache, as it can result in this request being buffered until it is complete,
      // regardless of other settings.
      if (function_exists('apache_setenv')) {
      apache_setenv('no-gzip', 1);
      }

      // If not redirecting, send appropriate headers and output.
      header('Connection: close');
      header('Content-length: ' . strlen($output));

      $this->model = $this->loadModel('backup', null, $directory, "backup/images");
      $result = $this->model->backupImages(1);

      // session_start();

      error_log("Result: $result");

      if ($result === false)
      \ckvsoft\Output::error($result);
      else
      \ckvsoft\Output::success($result);
      } catch (Exception $e) {
      \ckvsoft\Output::error(['data' => $result, 'e' => $e]);
      // throw new \ckvsoft\CkvException("Error: $e");
      }
      }
     * 
     */

    public function backupFiles($directory)
    {
        $result = "";

        try {
            set_time_limit(60 * 10);
            ignore_user_abort(true);
            session_write_close();

            $this->model = $this->loadModel('backup', null, $directory, "backup/images");
            $result = $this->model->backupImages(1);

            // Logging
            error_log("Result: " . print_r($result, true));

            // JSON-Ausgabe an den Client
            if ($result === false) {
                \ckvsoft\Output::error($result);
            } else {
                \ckvsoft\Output::success($result);
            }
        } catch (Exception $e) {
            \ckvsoft\Output::error(['data' => $result, 'e' => $e->getMessage()]);
        }
    }

    public function progress($progress_id)
    {
        $progressData = $this->loadModel('backup')->progress($progress_id);
        header('Content-Type: application/json');
        echo json_encode(isset($progressData[0]) ? $progressData[0] : []);
    }

    public function listImageDirs()
    {
        $baseDir = realpath(__DIR__ . '/../../../');
        $exclude = ['library', 'var/backup'];

        $dirs = [];

        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());

                // Exclude-Ordner überspringen
                foreach ($exclude as $ex) {
                    if (strpos($relativePath, $ex) === 0) {
                        continue 2; // direkt nächste Iteration
                    }
                }

                // Dateien im Ordner durchgehen und auf echte Bilder prüfen
                foreach (scandir($file->getPathname()) as $f) {
                    if ($f[0] === '.')
                        continue; // versteckte Dateien ignorieren

                    $filePath = $file->getPathname() . DIRECTORY_SEPARATOR . $f;

                    if (is_file($filePath) && @getimagesize($filePath) !== false) {
                        $dirs[] = $relativePath;
                        break; // reicht, wenn 1 Bild im Ordner existiert
                    }
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['dirs' => array_values(array_unique($dirs))]);
    }
}
