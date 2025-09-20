<?php

class Menu extends ckvsoft\mvc\Controller
{

    public $model;
    private $menu;

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged('admin');
    }

    public function index()
    {

        $this->model = $this->loadModel('menu');
        $menuhelper = $this->loadHelper("menu/menu");
        $script = '<script>' . $this->loadScript("/inc/js/ajax-list-pagination.js") . '</script>';
        $this->view->title = 'Main Menu';

        $this->view->render('dashboard/inc/header', ['menuitems' => $menuhelper->getMenu(0, 10)]);
        $this->view->render('menu/index', ['menu_script' => $script]);
        $this->view->render('inc/footer');
    }

    public function menuList()
    {
        $model = $this->loadModel('menu');
        $menu = $model->generateMenuArray(0);
        $tableHtml = "<table id=\"menu-table\" style=\"white-space: nowrap;\"><tr><th align=right>id</th><th align=left>entry</th><th align=right>public</th><th></th><th></th></tr>";
        $tableHtml .= $this->generateMenuTable($menu);
        $tableHtml .= '</table>';
        echo $tableHtml;
    }

    private function generateMenuTable($menu, $depth = 0)
    {
        $html = '';
        foreach ($menu as $item) {
            $indent = str_repeat('&nbsp;&nbsp;', $depth); // Einrückung anhand der Tiefe
            $is_public = $item['is_public'] == 1 ? 'Ja' : 'Nein';
            $html .= "<tr><td align=right>$item[id]</td>";
            $html .= "<td align=left>$indent . $item[label]</td>";
            $html .= "<td align=right>$is_public</td>";
            $html .= '<td><a href="' . BASE_URI . 'menu/edit/' . $item['id'] . '">Edit</a></td>';
            $html .= '<td><a href="' . BASE_URI . 'menu/delete/' . $item['id'] . '">Delete</a></td>';
            $html .= '</tr>';
            if (isset($item['submenu'])) {
                $html .= $this->generateMenuTable($item['submenu'], $depth + 1); // Rekursion mit erhöhter Tiefe
            }
        }
        return $html;
    }

    public function create()
    {
        $input = new \ckvsoft\Input();
        try {
            $input->post('label', true)
                ->post('link', true)
                ->post('parent', false)
                ->post('sort', false)
                ->post('role', false)
                ->post('is_public', false);
            $input->submit();

            // If the form has no errors, lets try the.
            // model and check if its a real user!
            $model = $this->loadModel('menu');
            $result = $model->create($input->fetch());
            if ($result == false) {
                ckvsoft\Output::error(["Menuentry not created"]);
            } else {
                // When we output success, I set jQuery in the view
                // which does a window.location.href redirect
                ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            // This will output our precious form errors
            ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function edit($id)
    {
        $this->view->title = 'Edit Menuentry';
        $this->model = $this->loadModel("menu");
        $this->view->menuList = $this->model->menuSingleList($id);
        $menuhelper = $this->loadHelper("menu/menu");
        $script = '<script>' . $this->loadScript("js/edit.js") . '</script>';

        $this->view->render('dashboard/inc/header', ['menuitems' => $menuhelper->getMenu(0, 10)]);
        $this->view->render('menu/edit', ['script' => $script]);
        $this->view->render('inc/footer');
    }

    public function editSave($id)
    {
        $input = new \ckvsoft\Input();
        try {
            $input->post('label', true)
                ->post('link', true)
                ->post('parent', false)
                ->post('sort', false)
                ->post('role', false)
                ->post('is_public', false);
            $input->submit();

            // If the form has no errors, lets try the.
            // model and check if its a real user!
            $model = $this->loadModel('menu');
            $result = $model->update($id, $input->fetch());
            if ($result == false) {
                ckvsoft\Output::error(["Changes not saved"]);
            } else {
                // When we output success, I set jQuery in the view
                // which does a window.location.href redirect
                ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            // This will output our precious form errors
            ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function delete($id)
    {
        $this->model = $this->loadModel('menu');
        $this->model->delete($id);
        header('location: ' . BASE_URI . 'menu');
    }

}
