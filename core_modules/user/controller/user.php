<?php

class User extends ckvsoft\mvc\BaseController
{

    public $model;

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isNotLogged('admin');
    }

    // ... (index, userList methods remain unchanged)

    public function index()
    {
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => 'User']],
            ['view' => 'user/index'],
            ['view' => '/inc/footer'],
        ]);
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
        $input = new \ckvsoft\Input();
        try {
            // 1. Input Validation (May throw CkvException)
            $input->post('username', true)
                    ->post('email', true)
                    ->validate('email')
                    ->post('password', true)
                    ->format('hash', array('sha256', HASH_KEY))
                    ->post('role', true);
            $input->submit();

            // 2. Model Execution (Returns integer ID on success, string error on failure)
            $user_model = $this->loadModel('user');
            $result = $user_model->create($input->fetch());

            // CHECK 1: If the Model returns a string, it is a specific error message.
            if (is_string($result)) {
                \ckvsoft\Output::error([$result]);
            }
            // SUCCESS: If it returns the integer user ID
            else {
                \ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            // This now only catches exceptions thrown by the Input::submit() (validation errors).
            \ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function editSave($id)
    {
        $input = new \ckvsoft\Input();
        try {
            // 1. Input Validation (May throw CkvException)
            $input->post('email', true)
                    ->validate('email')
                    ->post('password', true)
                    ->validate('length', array(6, 40))
                    ->format('hash', array('sha256', HASH_KEY))
                    ->post('role', true);
            $input->submit();

            // 2. Model Execution (Returns true on success, string error on failure)
            $user_model = $this->loadModel('user');
            $result = $user_model->update($id, $input->fetch());

            // CHECK 1: If the Model returns a string, it is a specific error message.
            if (is_string($result)) {
                \ckvsoft\Output::error([$result]);
            }
            // CHECK 2: If the Model returns false (which shouldn't happen with the new try/catch, but as a safeguard)
            elseif ($result == false) {
                \ckvsoft\Output::error(["Changes were not saved (no rows affected)."]);
            }
            // SUCCESS: If it returns true
            else {
                \ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            // This now only catches exceptions thrown by the Input::submit() (validation errors).
            \ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function delete($id)
    {
        $this->model = $this->loadModel('user');
        $result = $this->model->delete($id);

        // CHECK 1: If the Model returns a string, it is a specific error message.
        if (is_string($result)) {
            // In a redirect flow, store the error in a session/flash message.
            // Assuming ckvsoft\Session is available:
            // \ckvsoft\Session::setFlash('error', $result);
            // For now, let's redirect and hope the next page displays the message.
            // NOTE: You need a system to display $result on the redirect target page.
            header('location: ' . BASE_URI . 'user?error=' . urlencode($result));
        }
        // CHECK 2: If the result is 0 (no rows deleted)
        elseif ($result == 0) {
            header('location: ' . BASE_URI . 'user?error=' . urlencode('User was not found or could not be deleted.'));
        }
        // SUCCESS: If it returns a row count > 0
        else {
            header('location: ' . BASE_URI . 'user');
        }

        // IMPORTANT: Always exit after a header redirect
        exit;
    }

    // ... (edit method remains unchanged)

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
//      $script .= $this->loadScript("js/useredit.js");

        $script .= $this->loadScript("/inc/js/x-notify.js") . '</script>';

        $this->view->render('inc/header', ['base_css' => $css, 'base_scripts' => $script, 'menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('user/edit', ['user' => $this->model->userSingleList($id)]);
        $this->view->render('inc/footer');
    }
}
