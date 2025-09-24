<?php

class Dashboard extends ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged();
    }

    /**
     * Display those views!
     */
    public function index()
    {
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'Dashboard']],
            ['view' => 'dashboard/dashboard'],
            ['view' => '/inc/footer'],
        ]);
    }
}
