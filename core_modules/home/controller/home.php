<?php

class Home extends ckvsoft\mvc\BaseController
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
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'Home']],
            ['view' => 'home/home'],
            ['view' => '/inc/footer'],
        ]);
    }

    public function dataprotection()
    {
                $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'Dataprotection']],
            ['view' => '/inc/dataprotection'],
            ['view' => '/inc/footer'],
        ]);
    }
}
