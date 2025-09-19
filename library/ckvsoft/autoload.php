<?php

namespace ckvsoft;

class Autoload
{

    private array $autoloadDirs = [];
    private array $loadedClasses = [];

    /**
     * Constructor
     * 
     * @param array $dirs Basisverzeichnisse für Autoloading (Framework + Module)
     */
    public function __construct(array $dirs)
    {
        foreach ($dirs as $dir) {
            $this->autoloadDirs[] = rtrim($dir, '/') . '/';
        }

        spl_autoload_register([$this, '_load']);
    }

    /**
     * Autoloader für eine Klasse
     */
    private function _load(string $class)
    {
        if (isset($this->loadedClasses[$class])) {
            return;
        }
        $this->loadedClasses[$class] = true;

        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

        foreach ($this->autoloadDirs as $baseDir) {
            $file = $baseDir . $relativePath;
            $fileLower = $baseDir . strtolower($relativePath);

            if (file_exists($file)) {
                require $file;
                $this->_debug($file, $class);
                return;
            } elseif (file_exists($fileLower)) {
                require $fileLower;
                $this->_debug($fileLower, $class);
                return;
            }
        }

        // Optional Debug: Klasse nicht gefunden
        $this->_debug(null, $class);
    }

    /**
     * Debug-Ausgabe
     */
    private function _debug(?string $file, string $class)
    {
        if (!defined('APP_DEBUG') || !APP_DEBUG) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $message = $file ? "[$timestamp] [Autoloader] $class => $file" : "[$timestamp] [Autoloader] Klasse $class nicht gefunden";

        // 1️⃣ In eigenes Logfile schreiben
        $logDir = __DIR__ . '/../../var/log/'; // relativ zum Autoloader
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $message = $file ? "[$timestamp] [Autoloader] $class => $file" : "[$timestamp] [Autoloader] Klasse $class nicht gefunden\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true);

        error_log($message . "\n", 3, $logDir . 'autoload.log');

        // 2️⃣ Optional auch im Browser anzeigen
        if (ini_get('display_errors') && php_sapi_name() !== 'cli') {
            echo "<pre>$message</pre>";
        }
    }

    /**
     * Liste aller geladenen Klassen (optional)
     */
    public function getLoadedClasses(): array
    {
        return array_keys($this->loadedClasses);
    }
}
