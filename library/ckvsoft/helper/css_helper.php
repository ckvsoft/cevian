<?php

namespace ckvsoft\Helper;

class Css_Helper extends \ckvsoft\mvc\Helper
{

    /**
     * Load, minify and fix URLs in CSS file from module or core_module fallback
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
        $foundPath = null;
        foreach ($pathsToCheck as $path) {
            if (file_exists($path)) {
                $style = file_get_contents($path);
                $found = true;
                $foundPath = $path; // <-- tatsächlicher Dateisystem-Pfad
                break;
            }
        }

        if (!$found) {
            throw new \Exception("CSS file not found in module or core_modules: $css");
        }

        // Minify
        $style = preg_replace('/\/\*[\s\S]*?\*\//', '', $style); // Kommentare entfernen
        $style = preg_replace('/\s+/', ' ', $style); // Whitespace komprimieren
        $style = str_replace(["\r", "\n"], '', $style);

        // --- Fix relative URLs ---
        // Ziel: aus dem gefundenen Dateipfad eine web-URL-Basis bauen, z.B. "/meinprojekt/modules/..../inc/css"
        $docRoot = rtrim(getcwd(), DIRECTORY_SEPARATOR);
        $cssDirFs = dirname($foundPath); // filesystem dir der gefundenen CSS
        $relativePath = null;

        if (strpos($cssDirFs, $docRoot) === 0) {
            // Pfad relativ zum Projekt-Root ermitteln und in URL-Form bringen
            $relativePath = ltrim(str_replace('\\', '/', substr($cssDirFs, strlen($docRoot))), '/');
        } else {
            // Fallback: benutze den übergebenen $css-Pfad (falls ungewöhnlich)
            $relativePath = rtrim(str_replace('\\', '/', dirname($css)), '/');
        }

        $baseUri = defined('BASE_URI') ? rtrim(BASE_URI, '/') : ''; // z.B. '/meinprojekt' oder ''
        $webDir = ($baseUri !== '' ? $baseUri . '/' : '/') . $relativePath;
        $webDir = '/' . ltrim($webDir, '/'); // sicherstellen, dass es mit einem / beginnt

        $style = preg_replace_callback(
                '/url\((["\']?)([^"\')]+)\1\)/i',
                function ($matches) use ($baseUri) {
                    $url = trim($matches[2]);

                    // Wenn schon absolut oder ein Sonderfall → nicht anfassen
                    if (preg_match('~^(data:|https?:|//|/|#)~i', $url)) {
                        return "url(\"$url\")";
                    }

                    // Falls dein Projekt-Setup immer so ist: "public/..." gehört direkt ins Root
                    if (strpos($url, 'public/') === 0) {
                        $fixed = rtrim($baseUri, '/') . '/' . $url;
                    } else {
                        // Sonst relative URL: am besten den Web-Pfad des CSS-Ordners davor setzen
                        $fixed = rtrim($baseUri, '/') . '/' . ltrim($url, '/');
                    }

                    // Doppelte Slashes normalisieren
                    $fixed = preg_replace('#/+#', '/', $fixed);

                    return "url(\"$fixed\")";
                },
                $style
        );

        return $style;
    }
}
