<?php

namespace ckvsoft\mvc;

class View extends \stdClass
{

    public $mobile = false;
    public $cssjsDebug = false;   // << Debug-Flag für CSS-Analyse
    private $_viewQueue = array();
    private $_path;
    private $_current;

    public function __construct($debugCssJsAnalyse = false)
    {
        $user_agent = filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_SPECIAL_CHARS);
        if (strpos($user_agent ?? '', 'Mobile') !== false) {
            $this->mobile = true;
        }

        $this->cssjsDebug = $debugCssJsAnalyse;
    }

    public function render($name, $viewValues = array())
    {
        $this->_viewQueue[] = $name;

        foreach ($viewValues as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function setPath($path)
    {
        $this->_path = $path;
    }

    /*
      public function __destruct()
      {
      ob_start(); // fange den Output der Views ab

      foreach ($this->_viewQueue as $vc) {
      $path = $this->_path . $vc . '.php';

      if (file_exists($path)) {
      require $path;
      } else {
      $firstSlashPos = strpos($vc, "/");
      if ($firstSlashPos === 0) {
      $firstPart = substr($vc, 1, strpos(substr($vc, 1), "/") + 1);
      } else {
      $firstPart = substr($vc, 0, $firstSlashPos);
      }
      $newPath = $this->_path . $firstPart . "/view/" . substr($vc, $firstSlashPos + 1) . '.php';
      require $newPath;
      }
      }

      $html = ob_get_clean();

      // Wenn CSS-Debug aktiv ist → Analyse
      if ($this->cssDebug) {
      $this->analyseCssUsage($html);
      }

      echo $html;
      }
     * 
     */

    public function __destruct()
    {
        $htmlFinal = '';

        foreach ($this->_viewQueue as $vc) {
            $path = $this->_path . $vc . '.php';

            if (!file_exists($path)) {
                // alter Pfad-Check wie bisher
                $firstSlashPos = strpos($vc, "/");
                if ($firstSlashPos === 0) {
                    $firstPart = substr($vc, 1, strpos(substr($vc, 1), "/") + 1);
                } else {
                    $firstPart = substr($vc, 0, $firstSlashPos);
                }
                $path = $this->_path . $firstPart . "/view/" . substr($vc, $firstSlashPos + 1) . '.php';
            }

            ob_start();
            require $path;
            $viewHtml = ob_get_clean();

            // --- Analyse pro View ---
            if ($this->cssjsDebug) {
                $this->analyseCssUsage($viewHtml, $vc);
                $this->analyseJsUsagePerView($viewHtml, $vc);
            }

            $htmlFinal .= $viewHtml;
        }

        echo $htmlFinal;
    }

    private function analyseCssUsage(string $htmlContent)
    {
        $log_file = $_SERVER['DOCUMENT_ROOT'] . BASE_URI . 'var/log/css_unused.log';

        // 1) CSS-Dateien aus <link> tags
        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\']/', $htmlContent, $matches);
        $cssFiles = $matches[1] ?? [];

        // 2) Inline CSS aus <style>...</style>
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $htmlContent, $inlineMatches);
        $inlineCssBlocks = $inlineMatches[1] ?? [];

        // ---- Dateien prüfen ----
        foreach ($cssFiles as $href) {
            if (strpos($href, 'http') === 0) {
                // externe CSS -> überspringen
                continue;
            }
            $cssFile = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($href, '/');
            if (file_exists($cssFile)) {
                $this->checkCssFile($cssFile, file_get_contents($cssFile), $htmlContent, $log_file);
            }
        }

        // ---- Inline CSS prüfen ----
        foreach ($inlineCssBlocks as $cssContent) {
            $this->checkCssFile('inline-style', $cssContent, $htmlContent, $log_file);
        }
    }

    private function checkCssFile(string $source, string $cssContent, string $htmlContent, string $log_file)
    {
        preg_match_all('/([.#][a-zA-Z0-9_-]+)\s*[{,]/', $cssContent, $m);
        $selectors = array_unique($m[1]);

        $used = [];
        foreach ($selectors as $selector) {
            $name = substr($selector, 1);
            if ($selector[0] === '.') {
                if (preg_match('/class=["\'][^"\']*' . preg_quote($name, '/') . '[^"\']*["\']/', $htmlContent)) {
                    $used[] = $selector;
                }
            } elseif ($selector[0] === '#') {
                if (preg_match('/id=["\']' . preg_quote($name, '/') . '["\']/', $htmlContent)) {
                    $used[] = $selector;
                }
            }
        }

        $unused = array_diff($selectors, $used);

        // Automatischer HTML-Identifier (aus View-Queue)
        $viewIdentifier = !empty($this->_viewQueue) ? implode(' > ', $this->_viewQueue) : 'HTMLHash:' . substr(md5($htmlContent), 0, 8);

        file_put_contents(
                $log_file,
                "Quelle: {$source} | View: {$viewIdentifier}\n" .
                date('Y-m-d H:i:s') . "\n" .
                implode("\n", $unused) . "\n\n",
                FILE_APPEND
        );
    }

    private function analyseJsUsagePerView(string $htmlContent, string $viewName)
    {
        $log_file = $_SERVER['DOCUMENT_ROOT'] . BASE_URI . 'var/log/js_included.log';

        // ----------------------
        // 1️⃣ Externe JS-Dateien
        // ----------------------
        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i', $htmlContent, $matches);
        $jsFiles = $matches[1] ?? [];

        foreach ($jsFiles as $src) {
            if (strpos($src, 'http') === 0)
                continue; // externe CDN-Skripte überspringen

            $jsFile = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($src, '/');
            if (file_exists($jsFile)) {
                $content = file_get_contents($jsFile);
                $usesJquery = preg_match('/\$[\(\.]|jQuery\(/', $content) ? 'ja' : 'nein';

                file_put_contents(
                        $log_file,
                        "[" . date('Y-m-d H:i:s') . "] View: {$viewName} | Datei: {$src} | jQuery: {$usesJquery}\n",
                        FILE_APPEND
                );
            }
        }

        // ----------------------
        // 2️⃣ Inline-Scripts
        // ----------------------
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is', $htmlContent, $matches);
        $inlineScripts = $matches[1] ?? [];

        foreach ($inlineScripts as $index => $code) {
            $usesJquery = preg_match('/\$[\(\.]|jQuery\(/', $code) ? 'ja' : 'nein';
            $identifier = 'inline-script #' . ($index + 1);

            file_put_contents(
                    $log_file,
                    "[" . date('Y-m-d H:i:s') . "] View: {$viewName} | {$identifier} | jQuery: {$usesJquery}\n",
                    FILE_APPEND
            );
        }
    }
}
