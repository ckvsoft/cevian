<?php

namespace ckvsoft\mvc;

use ckvsoft\mvc\Config;

class BaseController extends \ckvsoft\mvc\Controller
{

    protected string $baseCss;
    protected string $baseScripts;
    protected object $menuHelper;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Zugriff auf die gemergte Config (Defaults + app.json)
     */
    protected function getConfig(): array
    {
        return Config::getMergedConfig();
    }

    /**
     * CSS-Dateien aus JSON laden
     */
    protected function loadAssetsCss(): string
    {
        $assets = $this->getConfig()['assets']['css'] ?? [];
        $cssFiles = $this->mobile ? ($assets['mobile'] ?? []) : ($assets['default'] ?? []);
        $out = '';
        foreach ($cssFiles as $file) {
            $out .= "<style>" . $this->loadHelper("css", [
                        'method' => 'getCss',
                        'args' => [$file]
                    ]) . "</style>";
        }
        return $out;
    }

    /**
     * JS-Dateien aus JSON laden
     */
    protected function loadAssetsJs(): string
    {
        $jsFiles = $this->getConfig()['assets']['js'] ?? [];
        $out = '<script>';
        foreach ($jsFiles as $file) {
            $out .= $this->loadScript($file);
        }
        $out .= '</script>';
        return $out;
    }

    /**
     * Checks for a stored Flash Message and returns the necessary JavaScript
     * block as a string to be embedded in the header.
     * @return string The <script> block or an empty string.
     */
    private function getFlashMessageScript(): string
    {
        // Retrieve and clear the message from the session
        $flashMessage = \ckvsoft\FlashMessage::get();
        $script = '';

        if ($flashMessage) {
            // 1. Daten für die JSON-Übertragung vorbereiten (KEIN addslashes mehr!)
            $data = [
                // English comment: Ensure all expected fields are present to avoid JS errors
                'type' => $flashMessage['type'] ?? 'info',
                'title' => $flashMessage['title'] ?? '',
                'message' => $flashMessage['message'] ?? '',
                // Include details and options, even if empty, for safety
                'details' => $flashMessage['details'] ?? [],
                'options' => $flashMessage['options'] ?? [],
            ];

            // 2. Daten sicher als JSON-String kodieren
            $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);

            if ($json_data === false) {
                // English comment: Log error if JSON encoding fails
                error_log("Flash message JSON encoding failed.");
                return '';
            }

            // 3. Script-Block mit dem JSON-Objekt erstellen
            // English comment: Inject the data object and pass its properties to displayMessage
            $script .= '<script>';
            $script .= 'document.addEventListener("DOMContentLoaded", () => {';
            // Variable 'data' im JS definieren
            $script .= '    const data = ' . $json_data . ';';
            $script .= '    if (typeof displayMessage === "function") {';
            // Argumente einzeln aus dem 'data'-Objekt übergeben
            $script .= '        displayMessage(data.type, data.title, data.message, data.details, data.options);';
            $script .= '    }';
            $script .= '});';
            $script .= '</script>';
        }

        return $script;
    }

    /**
     * Rendert beliebige Views mit optional Header/Footer
     * Header erhält automatisch Standard-Daten (CSS/JS/Menu)
     *
     * @param array $views Array von Views: ['view' => string, 'data' => array]
     */
    protected function renderPage(array $views, string $extraCss = '', string $extraJs = ''): void
    {
        // Basis + Extras
        $this->baseCss = $this->loadAssetsCss() . $extraCss;
        $this->baseScripts = $this->loadAssetsJs() . $extraJs;
        $this->baseScripts .= $this->getFlashMessageScript();

        $this->menuHelper = $this->loadHelper("menu/menu");
        $is_mobile_view = $this->mobile ?? false;

        $headerRendered = false;

        foreach ($views as $v) {
            $viewFile = $v['view'] ?? null;
            $viewData = $v['data'] ?? [];

            if ($viewFile === null) {
                continue;
            }

            // Prüfen, ob Header
            if (str_contains($viewFile, 'header') && !$headerRendered) {
                $viewData['menuitems'] = $this->menuHelper->getMenu(0, 10, $is_mobile_view);
                $viewData['base_css'] = $this->baseCss;
                $viewData['base_scripts'] = $this->baseScripts;

                $headerRendered = true;
            }

            $this->view->render($viewFile, $viewData);
        }
    }
}
