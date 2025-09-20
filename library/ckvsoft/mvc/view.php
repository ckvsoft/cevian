<?php

namespace ckvsoft\mvc;

class View extends \stdClass
{

    public $mobile = false;
    public $cssjsDebug = false;
    private $_viewQueue = [];
    private $_path;
    private $_coreModulePath;
    private $_current;

    public function __construct($debugCssJsAnalyse = false)
    {
        // Fetch User-Agent from server safely
        $user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_SPECIAL_CHARS);
        if (strpos($user_agent ?? '', 'Mobile') !== false) {
            $this->mobile = true;
        }

        $this->cssjsDebug = $debugCssJsAnalyse;
    }

    public function render($name, $viewValues = [])
    {
        $this->_viewQueue[] = $name;

        foreach ($viewValues as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function setPath($path)
    {
        $this->_path = rtrim($path, '/') . '/';
    }

    public function setCoreModulePath($path)
    {
        $this->_coreModulePath = rtrim($path, '/') . '/';
    }

    public function __destruct()
    {
        $htmlFinal = '';

        // Fetch DOCUMENT_ROOT once safely
        $documentRoot = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_SPECIAL_CHARS);

        foreach ($this->_viewQueue as $vc) {
            $vc = ltrim($vc, '/');

            $pathsToCheck = [
                $this->_path . $vc . '.php', // Module direct
                $this->_coreModulePath . $vc . '.php'    // Core direct
            ];

            // If not found → old View path check fallback
            if (!file_exists($pathsToCheck[0]) && !file_exists($pathsToCheck[1])) {
                $firstSlashPos = strpos($vc, "/");
                if ($firstSlashPos === false) {
                    $firstPart = $vc;
                    $restPath = '';
                } else {
                    $firstPart = substr($vc, 0, $firstSlashPos);
                    $restPath = substr($vc, $firstSlashPos + 1);

                    // Only if there is a "view" folder → Module views fallback
                    $pathsToCheck[] = $this->_path . $firstPart . '/view/' . $restPath . '.php';
                    $pathsToCheck[] = $this->_coreModulePath . $firstPart . '/view/' . $restPath . '.php';
                }
            }

            $found = false;
            foreach ($pathsToCheck as $path) {
                $path = preg_replace('#/+#', '/', $path);
                if (file_exists($path)) {
                    ob_start();
                    require $path;
                    $viewHtml = ob_get_clean();
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new \Exception("View file not found in module or core_modules: $vc");
            }

            // CSS/JS Analyse
            if ($this->cssjsDebug) {
                $this->analyseCssUsage($viewHtml, $vc, $documentRoot);
                $this->analyseJsUsagePerView($viewHtml, $vc, $documentRoot);
            }

            $htmlFinal .= $viewHtml;
        }

        echo $htmlFinal;
    }

    private function analyseCssUsage(string $htmlContent, string $viewIdentifier = '', string $documentRoot = '')
    {
        $log_file = $documentRoot . BASE_URI . 'var/log/css_unused.log';

        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\']/', $htmlContent, $matches);
        $cssFiles = $matches[1] ?? [];

        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $htmlContent, $inlineMatches);
        $inlineCssBlocks = $inlineMatches[1] ?? [];

        foreach ($cssFiles as $href) {
            if (strpos($href, 'http') === 0)
                continue;
            $cssFile = $documentRoot . '/' . ltrim($href, '/');
            if (file_exists($cssFile)) {
                $this->checkCssFile($cssFile, file_get_contents($cssFile), $htmlContent, $log_file, $viewIdentifier);
            }
        }

        foreach ($inlineCssBlocks as $cssContent) {
            $this->checkCssFile('inline-style', $cssContent, $htmlContent, $log_file, $viewIdentifier);
        }
    }

    private function checkCssFile(string $source, string $cssContent, string $htmlContent, string $log_file, string $viewIdentifier)
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
        $identifier = $viewIdentifier ?: 'HTMLHash:' . substr(md5($htmlContent), 0, 8);

        file_put_contents(
                $log_file,
                "Source: {$source} | View: {$identifier}\n" .
                date('Y-m-d H:i:s') . "\n" .
                implode("\n", $unused) . "\n\n",
                FILE_APPEND
        );
    }

    private function analyseJsUsagePerView(string $htmlContent, string $viewName, string $documentRoot = '')
    {
        $log_file = $documentRoot . BASE_URI . 'var/log/js_included.log';

        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i', $htmlContent, $matches);
        $jsFiles = $matches[1] ?? [];

        foreach ($jsFiles as $src) {
            if (strpos($src, 'http') === 0)
                continue;
            $jsFile = $documentRoot . '/' . ltrim($src, '/');
            if (file_exists($jsFile)) {
                $content = file_get_contents($jsFile);
                $usesJquery = preg_match('/\$[\(\.]|jQuery\(/', $content) ? 'ja' : 'nein';

                file_put_contents(
                        $log_file,
                        "[" . date('Y-m-d H:i:s') . "] View: {$viewName} | File: {$src} | jQuery: {$usesJquery}\n",
                        FILE_APPEND
                );
            }
        }

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
