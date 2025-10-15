<?php

namespace ckvsoft\mvc;

class View extends \stdClass
{

    public $mobile = false;
    public $cssjsDebug = false;
    private $_viewQueue = [];
    private $_path;
    private $_coreModulePath;

    public function __construct(bool $debugCssJsAnalyse = false)
    {
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $user_agent = strip_tags($http_user_agent);
        if (strpos($user_agent, 'Mobile') !== false) {
            $this->mobile = true;
        }
        $this->cssjsDebug = $debugCssJsAnalyse;
    }

    public function setPath(string $path)
    {
        $this->_path = rtrim($path, '/') . '/';
    }

    public function setCoreModulePath(string $path)
    {
        $this->_coreModulePath = rtrim($path, '/') . '/';
    }

    /**
     * Render a view or partial.
     * @param string $name View name or path.
     * @param array $viewValues Variables to extract in view.
     * @param bool $returnHtml Return HTML as string instead of queuing.
     * @return string|null
     * @throws \ckvsoft\CkvException
     */
    public function render(string $name, array $viewValues = [], bool $returnHtml = false)
    {
        foreach ($viewValues as $key => $value) {
            $this->{$key} = $value;
        }

        if ($returnHtml) {
            ob_start();
            require $this->resolveViewPath($name);
            return ob_get_clean();
        }

        $this->_viewQueue[] = $name;
    }

    /**
     * Resolve view file path using module path, core path, and view folder fallback.
     * @param string $vc
     * @return string
     * @throws \ckvsoft\CkvException
     */
    private function resolveViewPath(string $vc): string
    {
        $vc = ltrim($vc, '/');
        $pathsToCheck = [
            $this->_path . $vc . '.php',
            $this->_coreModulePath . $vc . '.php',
        ];

        if (!file_exists($pathsToCheck[0]) && !file_exists($pathsToCheck[1])) {
            $firstSlashPos = strpos($vc, "/");
            $firstPart = $firstSlashPos === false ? $vc : substr($vc, 0, $firstSlashPos);
            $restPath = $firstSlashPos === false ? '' : substr($vc, $firstSlashPos + 1);

            $pathsToCheck[] = $this->_path . $firstPart . '/view/' . $restPath . '.php';
            $pathsToCheck[] = $this->_coreModulePath . $firstPart . '/view/' . $restPath . '.php';
        }

        foreach ($pathsToCheck as $path) {
            $path = preg_replace('#/+#', '/', $path);
            if (file_exists($path))
                return $path;
        }

        throw new \ckvsoft\CkvException("View file not found: $vc");
    }

    public function __destruct()
    {
        $htmlFinal = '';
        $documentRoot = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_SPECIAL_CHARS);

        foreach ($this->_viewQueue as $vc) {
            ob_start();
            require $this->resolveViewPath($vc);
            $viewHtml = ob_get_clean();
            $htmlFinal .= $viewHtml;

            if ($this->cssjsDebug) {
                $this->analyseCssUsage($viewHtml, $vc, $documentRoot);
                $this->analyseJsUsagePerView($viewHtml, $vc, $documentRoot);
            }
        }

        echo $htmlFinal;
    }

    // --- CSS/JS Analyse bleibt unverändert ---
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
            if ($selector[0] === '.' && preg_match('/class=["\'][^"\']*' . preg_quote($name, '/') . '[^"\']*["\']/', $htmlContent)) {
                $used[] = $selector;
            } elseif ($selector[0] === '#' && preg_match('/id=["\']' . preg_quote($name, '/') . '["\']/', $htmlContent)) {
                $used[] = $selector;
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
