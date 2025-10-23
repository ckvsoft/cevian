<?php

class Login extends \ckvsoft\mvc\BaseController
{

    public function __construct()
    {
        parent::__construct();
        // Redirects if the user is already logged in
        \ckvsoft\Auth::isLogged();
    }

    /**
     * Display the login view.
     */
    public function index()
    {
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Login')]],
            ['view' => 'login/login'],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * Checks if the user is not logged in (used for authentication checks).
     */
    public static function isValid()
    {
        // This function redirects if the user is NOT logged in.
        return \ckvsoft\Auth::isNotLogged();
    }

    /**
     * Submits the login form.
     */
    public function submit()
    {
        $input = new \ckvsoft\Input();
        try {
            // Define required input fields and validation/formatting
            $input->post('email', true)
                    ->validate('email')
                    ->post('password', true)
                    ->format('hash', ['sha256', HASH_KEY]);
            $input->submit();

            // Check for validation errors
            if ($input->fetchErrors()) {
                \ckvsoft\Output::error($input->fetchErrors());
                return;
            }

            $data = $input->fetch();
            $user_model = $this->loadModel('user', 'user');
            $result = $user_model->login($data);

            // Check if the user was found and logged in successfully
            if (!$result) {
                \ckvsoft\Output::error([_("No user found")]);
                return;
            }

            // Set old session variables (Fallback for legacy modules)
            $_SESSION['user_id'] = $result[0]['user_id'];
            $_SESSION['user_key'] = \ckvsoft\Hash::create('sha256', $result[0]['user_id'], HASH_KEY);
            $_SESSION['user_role'] = \ckvsoft\Hash::create('sha256', $result[0]['role'], HASH_KEY);

            $roles = explode(',', $result[0]['role']); // CSV or Array from DB
            $rolesKey = \ckvsoft\Hash::create('sha256', implode(',', $roles), HASH_KEY);

            // Use the MultiLoginManager for centralized session management
            \ckvsoft\MultiLoginManager::login('ckvsoft', $result[0]['user_id'], [
                'roles' => $roles,
                'roles_key' => $rolesKey,
                'email' => $result[0]['email'] ?? ''
            ]);

            // Respond with success
            \ckvsoft\Output::success();
        } catch (\ckvsoft\CkvException $e) {
            // Handle exceptions, typically input validation errors
            \ckvsoft\Output::error($input->fetchErrors());
            throw $e;
        }
    }
}
