<?php

class User extends ckvsoft\mvc\Controller
{

    public $model;

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged('admin');
    }

    public function index()
    {
        $this->view->title = 'Users';
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
        $this->view->render('user/index', ['user_script' => $script]);
        $this->view->render('inc/footer');
    }

    public function userList()
    {
        $this->model = $this->loadModel('user');
        $html = "<table><tr><th>id</th><th>email</th><th></th><th></th></tr>";
        foreach ($this->model->userList() as $key => $value) {
            $html .= '<tr><td>' . $value['user_id'] . '</td><td>' . $value['email'] . '</td> <td> <a href="' . BASE_URI . 'user/edit/' . $value['user_id'] . '">Edit</a> ';
            if ($value['user_id'] > 1)
                $html .= '<a href="' . BASE_URI . 'user/delete/' . $value['user_id'] . '">Delete</a>';
            $html .= '</td></tr>';
        }
        $html .= '</table>';

        echo $html;
    }

    public function create()
    {
        try {
            $input = new \ckvsoft\Input();
            $input->post('email', true)
                    ->validate('email')
                    ->post('password', true)
                    ->format('hash', array('sha256', HASH_KEY))
                    ->post('role', true);
            $input->submit();

            // If the form has no errors, lets try the.
            // model and check if its a real user!
            $user_model = $this->loadModel('user');
            $result = $user_model->create($input->fetch());
            if ($result == false) {
                ckvsoft\Output::error(["User not created"]);
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
        $this->model = $this->loadModel('user');
        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->title = 'Edit User';
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
//        $script .= $this->loadScript("js/useredit.js");

        $script .= $this->loadScript("/inc/js/x-notify.js") . '</script>';

        $this->view->render('inc/header', ['base_css' => $css, 'base_scripts' => $script, 'menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('user/edit', ['user' => $this->model->userSingleList($id)]);
        $this->view->render('inc/footer');
    }

    public function editSave($id)
    {
        $input = new \ckvsoft\Input();
        try {
            $input->post('email', true)
                    ->validate('email')
                    ->post('password', true)
                    ->validate('length', array(6, 40))
                    ->format('hash', array('sha256', HASH_KEY))
                    ->post('role', true);
            $input->submit();

            // If the form has no errors, lets try the.
            // model and check if its a real user!
            $user_model = $this->loadModel('user');
            $result = $user_model->update($id, $input->fetch());
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
        $this->model = $this->loadModel('user');
        $this->model->delete($id);
        header('location: ' . BASE_URI . 'user');
    }
}
