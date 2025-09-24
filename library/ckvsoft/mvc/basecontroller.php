<?php

namespace ckvsoft\mvc;

use ckvsoft\Request;
use ckvsoft\mvc\Config;

class BaseController extends \ckvsoft\mvc\Controller
{
    
    protected Request $request;
    protected string $baseCss;
    protected string $baseScripts;
    protected object $menuHelper;

    public function __construct()
    {
        parent::__construct();

        // Request kapseln
        $this->request = new Request();

        // Mobile-Erkennung
        $this->mobile = $this->request->isMobile();
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

        $this->menuHelper = $this->loadHelper("menu/menu");

        foreach ($views as $v) {
            $viewFile = $v['view'] ?? null;
            $viewData = $v['data'] ?? [];

            if ($viewFile !== null) {
                if (str_contains($viewFile, 'header')) {
                    $viewData = array_merge($viewData, [
                        'base_css' => $this->baseCss,
                        'base_scripts' => $this->baseScripts,
                        'menuitems' => $this->menuHelper->getMenu(0),
                    ]);
                }

                $this->view->render($viewFile, $viewData);
            }
        }
    }
}
