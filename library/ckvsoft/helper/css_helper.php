<?php

namespace ckvsoft\Helper;

class Css_Helper extends \ckvsoft\mvc\Helper
{

    /**
     * Load and minify CSS file from module or core_module fallback
     *
     * @param string $css relative path, e.g. 'inc/css/mbv.css'
     * @return string minified CSS
     * @throws \Exception if file not found
     */
    public function getCss($css)
    {
        // Prüfen, ob $css mit einem / beginnt
        if (strpos($css, '/') === 0) {
            // Suche direkt in MODULES oder CORE_MODULES
            $pathsToCheck = [
                getcwd() . '/' . MODULES_URI . ltrim($css, '/'),
                getcwd() . '/' . CORE_MODULES_URI . ltrim($css, '/'),
            ];
        } else {
            // Standard-Suche im Modul/view-Ordner
            $pathsToCheck = [
                getcwd() . '/' . MODULES_URI . $this->baseController . '/view/' . $css,
                getcwd() . '/' . CORE_MODULES_URI . $this->baseController . '/view/' . $css,
            ];
        }

        $found = false;
        foreach ($pathsToCheck as $path) {
            if (file_exists($path)) {
                $style = file_get_contents($path);
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception("CSS file not found in module or core_modules: $css");
        }

        // Minify
        $style = preg_replace('/\s+/', ' ', $style);
        $style = preg_replace('/\/\*[\s\S]*?\*\//', '', $style);
        return str_replace(["\r", "\n"], '', $style);
    }
}
