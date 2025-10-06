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

    /**
     * Load, minify and fix URLs in CSS file from module or core_module fallback
     *
     * @param string $css relative path, e.g. 'inc/css/mbv.css'
     * @param string|null $module optional module name to override baseControllerName
     * @return string minified CSS
     * @throws \Exception if file not found
     */
    public function getCss($css, $module = null)
    {
        // Modulname bestimmen (entweder explizit angegeben oder vom Controller ableiten)
        $moduleName = $module !== null ? $module : $this->baseControllerName;

        // Prüfen, ob $css mit einem / beginnt (bedeutet: Pfad ist relativ zu MODULES_URI/CORE_MODULES_URI)
        if (strpos($css, '/') === 0) {
            // Suche direkt in MODULES oder CORE_MODULES (Unabhängig vom aktuellen Modul)
            $pathsToCheck = [
                getcwd() . '/' . MODULES_URI . ltrim($css, '/'),
                getcwd() . '/' . CORE_MODULES_URI . ltrim($css, '/'),
            ];
        } else {
            // Standard-Suche im angegebenen/aktuellen Modul/view-Ordner
            // Hier wird $moduleName verwendet
            $pathsToCheck = [
                getcwd() . '/' . MODULES_URI . $moduleName . '/view/' . $css,
                getcwd() . '/' . CORE_MODULES_URI . $moduleName . '/view/' . $css,
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
            throw new \Exception("CSS file not found in module or core_modules: $css (Module: $moduleName)");
        }

        // --- Rest der Logik (Minify & URL-Fixing) bleibt unverändert ---
        // Minify
        $style = preg_replace('/\/\*[\s\S]*?\*\//', '', $style); // Kommentare entfernen
        $style = preg_replace('/\s+/', ' ', $style); // Whitespace komprimieren
        $style = str_replace(["\r", "\n"], '', $style);

        // --- Fix relative URLs ---
        // ... (Der gesamte nachfolgende Code zur Pfadkorrektur bleibt identisch)

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
                function ($matches) use ($baseUri, $webDir) { // $webDir hier hinzugefügt, falls benötigt
                    $url = trim($matches[2]);

                    // Wenn schon absolut oder ein Sonderfall → nicht anfassen
                    if (preg_match('~^(data:|https?:|//|/|#)~i', $url)) {
                        return "url(\"$url\")";
                    }

                    // Falls dein Projekt-Setup immer so ist: "public/..." gehört direkt ins Root
                    if (strpos($url, 'public/') === 0) {
                        $fixed = rtrim($baseUri, '/') . '/' . $url;
                    } else {
                        // Sonst relative URL: den Web-Pfad des CSS-Ordners davor setzen
                        $fixed = rtrim($webDir, '/') . '/' . ltrim($url, '/'); // $webDir anstelle von $baseUri
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
