<?php

class Home extends ckvsoft\mvc\Controller
{

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isLogged();
    }

    /**
     * Display those views!
     */
    public function index()
    {
        $params = [
            'method' => 'getCss',
            'args' => ['/inc/css/style.css']
        ];

        if ($this->mobile) {
            $params = [
                'method' => 'getCss',
                'args' => ['/inc/css/mobile.css']
            ];
        }

        $css = "<style>" . $this->loadHelper("css", $params) . "</style>";

        $script = '<script>' . $this->loadScript("/inc/js/ajax-list-pagination.js");
        $script .= $this->loadScript("/inc/js/menuscript.js");
        $script .= $this->loadScript("/inc/js/x-notify.js") . '</script>';

        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('inc/header', ['base_css' => $css, 'base_scripts' => $script, 'menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('home/home');
        $this->view->render('inc/footer');
    }

    public function dataprotection()
    {
        $params = [
            'method' => 'getCss',
            'args' => ['/inc/css/style.css']
        ];

        if ($this->mobile) {
            $params = [
                'method' => 'getCss',
                'args' => ['/inc/css/mobile.css']
            ];
        }

        $css = "<style>" . $this->loadHelper("css", $params) . "</style>";

        $script = '<script>' . $this->loadScript("/inc/js/ajax-list-pagination.js");
        $script .= $this->loadScript("/inc/js/menuscript.js");
        $script .= $this->loadScript("/inc/js/x-notify.js") . '</script>';

        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('inc/header', ['base_css' => $css, 'base_scripts' => $script, 'menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('inc/dataprotection');
        $this->view->render('inc/footer');
    }
}
