<?php

class User extends ckvsoft\mvc\BaseController
{

    public $model;

    public function __construct()
    {
        parent::__construct();
        // REQUIREMENT: Only a general login is required for all actions in this controller.
        // The 'admin' role check is performed within the respective methods.
        \ckvsoft\Auth::isNotLogged();
    }

    // ---
    // ## View Methods (index, userList, profile, edit) - NO CHANGE REQUIRED
    // ---

    public function index()
    {
        // ACCESS CONTROL: Only Admins can see the full user management list.
        if (!\ckvsoft\Auth::hasRole('admin')) {
            // Redirect non-admins to their own profile page.
            header('Location: ' . BASE_URI . 'user/profile');
            exit;
        }

        // Renders the main user management page for Admins
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('User')]],
            ['view' => 'user/index'],
            ['view' => '/inc/footer'],
        ]);
    }

    public function userList()
    {
        // ACCESS CONTROL: Only Admins can see the user list data
        if (!\ckvsoft\Auth::hasRole('admin')) {
            exit;
        }

        // Loads user data and renders the list snippet
        $this->model = $this->loadModel('user');
        $this->view->render("user/user_snipped", ['userList' => $this->model->userList()]);
    }

    /**
     * Allows a logged-in user to edit their own profile data.
     */
    public function profile()
    {
        $current_user_id = \ckvsoft\Auth::getUserId();
        // Calls the edit logic for the current user's ID
        $this->edit($current_user_id);
    }

    /**
     * Renders the user editing form.
     */
    public function edit($id)
    {
        $current_user_id = \ckvsoft\Auth::getUserId();
        $is_admin = \ckvsoft\Auth::hasRole('admin');

        // AUTHORIZATION CHECK: Admin can edit anyone OR the user is editing their own ID.
        if (!$is_admin && $id != $current_user_id) {
            header('Location: ' . BASE_URI . 'user/profile');
            exit;
        }

        $this->model = $this->loadModel('user');
        $user_data = $this->model->userSingleList($id);

        if (empty($user_data)) {
            header('Location: ' . BASE_URI . 'dashboard?error=' . urlencode(_('User not found.')));
            exit;
        }

        // Renders the user editing form
        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Edit Profile')]],
            ['view' => 'user/edit', 'data' => [
                    'user' => $user_data,
                    // Pass a flag to control the Role field visibility in the view
                    'isAdmin' => $is_admin
                ]],
            ['view' => '/inc/footer'],
        ]);
    }

    // ---
    // ## Action Methods (Create, Update, Delete)
    // ---

    /**
     * Handles the creation of a new user. Password is mandatory here.
     */
    public function create()
    {
        // ACCESS CONTROL: Only Admins can create users
        if (!\ckvsoft\Auth::hasRole('admin')) {
            \ckvsoft\Output::error([_('Authorization denied.')]);
            return;
        }

        $input = new \ckvsoft\Input();
        try {
            // 1. Input Validation and Formatting
            $input->post('username', true);
            $input->post('email', true)->validate('email');

            // Password fields are MANDATORY for creation (required=true)
            $input->post('password_confirm', true);
            $input->post('password', true)
                    ->validate('length', array(6, 40))
                    ->validate('matches', ['password_confirm'])
                    ->format('hash', array('sha256', HASH_KEY));

            // Role is mandatory
            $input->post('role', true);

            $input->submit();

            // 2. Data cleanup: Remove the confirmation field before saving to DB.
            $input->remove('password_confirm');
            $data_to_create = $input->fetch();

            // 3. Model Execution (Returns integer ID on success, string error on failure)
            $user_model = $this->loadModel('user');
            $result = $user_model->create($data_to_create);

            if (is_string($result)) {
                \ckvsoft\Output::error([$result]);
            } else {
                \ckvsoft\Output::success();
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error($input->fetchErrors());
        }
    }

    /**
     * Handles the update of an existing user. Password is optional.
     */
    public function editSave($id)
    {
        $current_user_id = \ckvsoft\Auth::getUserId();
        $is_admin = \ckvsoft\Auth::hasRole('admin');

        // AUTHORIZATION CHECK
        if (!$is_admin && $id != $current_user_id) {
            \ckvsoft\Output::error([_('Authorization denied.')]);
            return;
        }

        $input = new \ckvsoft\Input();
        $user_model = $this->loadModel('user');

        // 1. Get current user data (to check for email change)
        $current_db_data = $user_model->userSingleList($id);
        $old_email = $current_db_data[0]['email'] ?? '';
        $logout_required = false;

        try {
            // 2. Required core fields
            $input->post('username', true);
            $input->post('email', true)->validate('email');

            // 3. Optional password fields (required=false).
            // Input class will only add them to the stack if they exist in $_POST.
            $input->post('password_confirm', false);
            $input->post('password', false);

            // 4. Role Control (Admin only)
            if ($is_admin) {
                $input->post('role', true);
            }

            // 5. Run initial validation (before hashing/final cleanup)
            $input->submit();

            // Get data to check if password was actually set by the user
            $data_temp = $input->fetch();

            // 6. Conditional Password Hashing and Cleanup
            if (isset($data_temp['password']) && !empty($data_temp['password'])) {

                // If password is set, we must re-add/re-validate/hash it (Input class limitation workaround)
                $input->post('password', true)
                        ->validate('length', array(6, 40))
                        ->validate('matches', ['password_confirm'])
                        ->format('hash', array('sha256', HASH_KEY));
            } else {
                // If password is NOT set, remove all password related fields from the stack.
                $input->remove('password_confirm', 'password');
            }

            // 7. Re-submit to process conditional logic (like hashing/removal)
            $input->submit();

            // 8. Final data retrieval and cleanup
            $data_to_update = $input->fetch();

            // Always remove the unhashed confirmation field
            $input->remove('password_confirm');
            unset($data_to_update['password_confirm']); // Ensure it's not in the final array
            // Final check on 'role' (safe redundant check)
            if (!$is_admin && isset($data_to_update['role'])) {
                unset($data_to_update['role']);
            }

            // 9. Check for mandatory logout on email change
            if ($id == $current_user_id && isset($data_to_update['email']) && strtolower($data_to_update['email']) !== strtolower($old_email)) {
                $logout_required = true;
            }

            // 10. Model Execution
            $result = $user_model->update($id, $data_to_update);

            if (is_string($result)) {
                \ckvsoft\Output::error([$result]);
            } elseif ($result === false) {
                \ckvsoft\Output::error([_("Changes were not saved (no rows affected).")]);
            } else {
                // SUCCESS: Handle logout or normal success
                if ($logout_required) {
                    \ckvsoft\MultiLoginManager::logoutCurrentSession();
                    session_unset();
                    session_destroy();
                    \ckvsoft\Output::success([
                        'message' => _('Email changed successfully. Please log in again with your new email address.'),
                        'redirect' => BASE_URI . 'logout'
                    ]);
                } else {
                    \ckvsoft\Output::success();
                }
            }
        } catch (\ckvsoft\CkvException $e) {
            \ckvsoft\Output::error($input->fetchErrors());
        }
    }

    public function delete($id)
    {
        // ACCESS CONTROL: Only Admins can delete users
        if (!\ckvsoft\Auth::hasRole('admin')) {
            header('location: ' . BASE_URI . 'user?error=' . urlencode(_('Authorization denied.')));
            exit;
        }

        // ... (The rest of the method remains the same) ...
        $this->model = $this->loadModel('user');
        $result = $this->model->delete($id);

        if (is_string($result)) {
            header('location: ' . BASE_URI . 'user?error=' . urlencode($result));
        } elseif ($result === 0) {
            header('location: ' . BASE_URI . 'user?error=' . urlencode(_('User was not found or could not be deleted.')));
        } else {
            header('location: ' . BASE_URI . 'user');
        }

        exit;
    }
}
