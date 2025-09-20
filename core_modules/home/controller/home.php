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
        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('inc/header', ['menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('home/home');
        $this->view->render('inc/footer');
    }

    public function dataprotection()
    {
        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('inc/header', ['menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('inc/dataprotection');
        $this->view->render('inc/footer');
    }
}
