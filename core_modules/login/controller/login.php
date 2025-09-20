<?php

class Login extends \ckvsoft\mvc\Controller
{

    public function __construct()
    {
        parent::__construct();
        \ckvsoft\Auth::isLogged(); // leitet weiter, wenn schon eingeloggt
    }

    /**
     * Display those views
     */
    public function index()
    {
        $menuhelper = $this->loadHelper("menu/menu");
        $this->view->render('inc/header', ['menuitems' => $menuhelper->getMenu(0)]);
        $this->view->render('login/login');
        $this->view->render('inc/footer');
    }

    public static function isValid()
    {
        return \ckvsoft\Auth::isNotLogged();
    }

    /**
     * Submits the login form.
     */
    public function submit()
    {
        $input = new \ckvsoft\Input();
        try {
            $input->post('email', true)
                    ->validate('email')
                    ->post('password', true)
                    ->format('hash', ['sha256', HASH_KEY]);
            $input->submit();

            if ($input->fetchErrors()) {
                \ckvsoft\Output::error($input->fetchErrors());
                return;
            }

            $data = $input->fetch();
            $user_model = $this->loadModel('user', 'user');
            $result = $user_model->login($data);

            if (!$result) {
                \ckvsoft\Output::error(["No user found"]);
                return;
            }

            // Alte Session setzen (Fallback für alte Module)
            $_SESSION['user_id'] = $result[0]['user_id'];
            $_SESSION['user_key'] = \ckvsoft\Hash::create('sha256', $result[0]['user_id'], HASH_KEY);
            $_SESSION['user_role'] = \ckvsoft\Hash::create('sha256', $result[0]['role'], HASH_KEY);

            $roles = explode(',', $result[0]['role']); // CSV oder Array aus DB
            $rolesKey = \ckvsoft\Hash::create('sha256', implode(',', $roles), HASH_KEY);

            \ckvsoft\MultiLoginManager::login('ckvsoft', $result[0]['user_id'], [
                'roles' => $roles,
                'roles_key' => $rolesKey,
                'email' => $result[0]['email'] ?? ''
            ]);

            \ckvsoft\Output::success();
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error($input->fetchErrors());
            throw $e;
        }
    }
}
