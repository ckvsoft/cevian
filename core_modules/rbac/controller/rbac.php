<?php

use ckvsoft\Auth;
use ckvsoft\ACL;
use ckvsoft\Input;
use ckvsoft\Output;
use ckvsoft\mvc\BaseController;

/**
 * Controller for managing Roles and Permissions (RBAC).
 * Delegates core logic (Nested Set and definition CRUD) to ACL and Rbac_Model.
 */
class Rbac extends BaseController
{

    // =================================================================
    // 1. SETUP & UTILS
    // =================================================================

    private ACL $acl;
    private $rbac; // lazy-loaded Rbac_Model instance

    public function __construct()
    {
        parent::__construct();
        // Check for admin/management privileges
        Auth::isNotLogged('admin');

        // Initialize ACL instance (needed for role tree lookups, safe to create here)
        $this->acl = new ACL();
    }

    /**
     * Lazy loader for the Rbac_Model.
     * @return \Rbac_Model
     */
    private function getRbacModel()
    {
        if (!$this->rbac) {
            $this->rbac = $this->loadModel('rbac');
        }
        return $this->rbac;
    }

    // =================================================================
    // 2. ROLE ACTIONS (CRUD)
    // =================================================================

    /**
     * Index page: Displays the role overview (role tree).
     */
    public function index(): void
    {
        $roles = $this->acl->getAllRoles('full');

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Role Management')]],
            ['view' => 'rbac/index', 'data' => ['roles' => $roles]],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * AJAX: Generates the role list table partial (HTML).
     */
    public function roleList(): void
    {
        $rbac = $this->getRbacModel();
        $roles = $rbac->getAllRoles('full');
        $this->view->render('rbac/roles_table_snippet', ['roles' => $roles]);
    }

    /**
     * Handles saving a new role or updating an existing role's name.
     * Uses the Model's logic for uniqueness and ACL delegation.
     */
    public function saveRole(): void
    {
        $input = new Input();
        try {
            $input->post('roleName', true)
                    ->post('parentId', false)
                    ->post('role_id', false);
            $input->submit();

            $data = $input->fetch();
            $roleName = trim($data['roleName']);
            $parentId = isset($data['parentId']) && is_numeric($data['parentId']) ? (int) $data['parentId'] : null;
            $roleId = isset($data['role_id']) && is_numeric($data['role_id']) ? (int) $data['role_id'] : null;

            if ($roleName === '') {
                throw new \Exception(_("Role name cannot be empty."));
            }

            $rbac = $this->getRbacModel();

            // Delegate all role saving/creation/name update logic to the Model
            $result = $rbac->saveRole($roleName, $parentId, $roleId);

            if (is_string($result)) {
                // Error (Model returned string message)
                Output::error(['general' => $result]);
                return;
            }

            // Success (Model returned role ID)
            $roleId = $result;
            Output::success(['role_id' => $roleId, 'roleName' => $roleName, 'parentId' => $parentId]);
        } catch (\Exception $e) {
            Output::error(['general' => $e->getMessage()]);
        }
    }

    /**
     * Displays the form to edit role details (name, parent) and the permissions partial.
     * @param int $id Role ID.
     */
    public function editRole(int $id): void
    {
        $rbac = $this->getRbacModel();
        $roles = $this->acl->getAllRoles('full');

        // Find role data (inefficient lookup, should use model's roleSingle or ACL's getRole)
        $roleData = null;
        foreach ($roles as $r) {
            if ($r['id'] == $id) {
                $roleData = $r;
                break;
            }
        }
        if (!$roleData) {
            header("Location: " . BASE_URI . "rbac");
            exit;
        }

        // Determine parent id (immediate parent)
        $roleData['parentId'] = $this->acl->getParentId($id);

        // Assigned permissions for this role (direct only)
        // NOTE: $rbac->getRolePerms() returns [permID => value (0, 1, or X)]
        $assignedPerms = $rbac->getRolePerms($id);

        // NEW: Get effective rights (considers inheritance)
        // Result: [permID => true/false]
        $effectivePerms = $this->acl->getRoleEffectivePermissions($id);

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Edit Role')]],
            ['view' => 'rbac/editrole', 'data' => [
                    'role' => $roleData,
                    'roles' => $roles
                ]],
            // Permissions partial (separate form)
            ['view' => 'rbac/permissions', 'data' => [
                    'allPerms' => $rbac->getAllPermissions('full'), // All defined permissions
                    'assigned' => $assignedPerms, // Directly assigned ('0', '1', 'X')
                    'effective' => $effectivePerms, // NEW: Effective value (true/false)
                    'role' => $roleData,
                    'redirect' => "rbac/editRole/{$id}"
                ]],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * Saves changes to role name and parent/position (via AJAX).
     */
    public function editRoleSave(): void
    {
        $input = new Input();
        try {
            $input->post('role_id', true)
                    ->post('roleName', true)
                    ->post('parentId', false);
            $input->submit();

            $data = $input->fetchAllJson();
            $roleId = (int) $data['role_id'];
            $roleName = trim($data['roleName']);
            // Convert empty string/null to actual null for root parent
            $parentId = isset($data['parentId']) && $data['parentId'] !== '' ? (int) $data['parentId'] : null;

            if ($roleName === '')
                throw new \Exception(_("Role name cannot be empty."));

            $rbac = $this->getRbacModel();

            // Delegate update logic (name and movement) to the Model
            $error = $rbac->updateRole($roleId, ['roleName' => $roleName, 'parentId' => $parentId]);

            if (is_string($error)) {
                Output::error(['general' => $error]);
                return;
            }

            Output::success();
        } catch (\Exception $e) {
            Output::error(['general' => $e->getMessage()]);
        }
    }

    /**
     * Deletes a role (AJAX). Delegates to ACL for Nested Set deletion.
     * @param int $id Role ID.
     */
    public function deleteRole(int $id): void
    {
        try {
            $this->acl->deleteRole($id);
            Output::success();
        } catch (\Exception $e) {
            // Catches DB/Foreign Key errors
            Output::error(['general' => $e->getMessage()]);
        }
    }

    // ---
    // ## 3. PERMISSION ACTIONS (CRUD)
    // ---

    /**
     * Shows form to create / edit a permission definition.
     * @param int|null $id Permission ID.
     */
    public function editPermission($id = null): void
    {
        $rbac = $this->getRbacModel();
        $perm = null;
        if ($id) {
            // NOTE: The Model method was called getPermission or permSingle. Assuming permSingle/permList lookup here.
            $perm = $rbac->permSingle((int) $id);
            if (!$perm) {
                header("Location: " . BASE_URI . "rbac/permissions");
                exit;
            }
        }

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => $id ? _('Edit Permission') : _('New Permission')]],
            ['view' => 'rbac/editpermission', 'data' => ['perm' => $perm]],
            ['view' => '/inc/footer'],
        ]);
    }

    public function permissions($roleId = null): void
    {
        // We only render the container. The actual list is
        // loaded subsequently by the JavaScript code via loadList().

        $this->renderPage([
            ['view' => '/inc/header', 'data' => ['title' => _('Permissions Definition')]],
            // The main view container, which holds the AJAX list and the Add/Edit form.
            ['view' => 'rbac/permissions_manage', 'data' => [
                    'roleId' => $roleId // Can be used for the back link
                ]],
            ['view' => '/inc/footer'],
        ]);
    }

    /**
     * AJAX: Generates the Permission list table partial (HTML).
     * This is the endpoint called by JS loadList() for permissionListContainer.
     */
    public function permissionList(): void
    {
        $rbac = $this->getRbacModel();
        $permissions = $rbac->getAllPermissions('full');
        $this->view->render('rbac/permissions_table_snippet', ['permissions' => $permissions]);
    }

    // ... editPermission remains unused because we are using the Single-Page View ...

    /**
     * Saves permission definition (create or update) via AJAX.
     * Ensures the response contains 'id'.
     */
    public function editPermissionSave(): void
    {
        $input = new Input();
        try {
            // ... (Your existing Input and data extraction logic) ...
            $input->post('id', false) // Use 'id' instead of 'perm_id' for consistency in the front-end form
                    ->post('permName', true)
                    ->post('permKey', true)
                    ->post('permDescription', false)
                    ->submit();

            $data = $input->fetchAllJson();
            $rbac = $this->getRbacModel();

            $permName = trim($data['permName']);
            $permKey = trim($data['permKey']);
            $permDesc = $data['permDescription'] ?? '';
            $permId = (int) ($data['id'] ?? 0); // Get ID from 'id' field

            if (empty($permName) || empty($permKey)) {
                throw new \Exception(_("Permission name and key are required."));
            }

            $updateData = [
                'permName' => $permName,
                'permKey' => $permKey,
                'permDescription' => $permDesc
            ];

            $resultId = 0;
            if ($permId > 0) {
                // UPDATE
                $error = $rbac->updatePermission($permId, $updateData);
                if (is_string($error)) {
                    Output::error(['general' => $error]);
                    return;
                }
                $resultId = $permId;
            } else {
                // CREATE
                $result = $rbac->createPermission($updateData);
                if (is_string($result)) {
                    Output::error(['general' => $result]);
                    return;
                }
                $resultId = $result;
            }

            Output::success(['id' => $resultId]); // IMPORTANT: Send the ID back
        } catch (\Exception $e) {
            Output::error(['general' => $e->getMessage()]);
        }
    }

    /**
     * Deletes a permission definition and associated assignments (via AJAX).
     * @param int $id Permission ID.
     */
    public function deletePermission(int $id): void
    {
        try {
            $rbac = $this->getRbacModel();
            $error = $rbac->deletePermission($id);

            if (is_string($error)) {
                Output::error(['general' => $error]);
                return;
            }

            Output::success();
        } catch (\Exception $e) {
            Output::error(['general' => $e->getMessage()]);
        }
    }

    // ---
    // ## 4. ASSIGNMENT ACTIONS
    // ---

    /**
     * Saves role permissions (direct assignments) via AJAX.
     */
    public function saveRolePerms(): void
    {
        $input = new Input();
        try {
            // Register role_id then read JSON body (that includes perm_* keys)
            $input->post('role_id', true);
            $input->submit();
            $data = $input->fetchAllJson();

            $roleId = (int) ($data['role_id'] ?? 0);
            if ($roleId <= 0)
                throw new \Exception(_("Invalid role id"));

            $perms = [];
            foreach ($data as $k => $v) {
                // Filter for permission keys (e.g., perm_123)
                if (strpos($k, 'perm_') === 0) {
                    $permId = (int) str_replace('perm_', '', $k);
                    // Value should be '1' (allow), '0' (deny), or 'X' (no explicit setting/delete)
                    $val = ($v === '1' || $v === '0') ? $v : 'X';
                    $perms[$permId] = $val;
                }
            }

            $rbac = $this->getRbacModel();
            $rbac->setRolePerms($roleId, $perms);

            Output::success();
        } catch (\Exception $e) {
            Output::error(['general' => $e->getMessage()]);
        }
    }
}
