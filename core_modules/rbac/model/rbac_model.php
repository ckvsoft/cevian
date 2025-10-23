<?php

use ckvsoft\ACL;
use ckvsoft\CkvException; // Added for explicit Exception import

/**
 * Model responsible for Role-Based Access Control (RBAC) management
 * views and operations, primarily delegating core logic to the ACL component.
 */
class Rbac_Model extends \ckvsoft\mvc\Model
{

    // =================================================================
    // 1. PROPERTIES
    // =================================================================
    // Tables used by this model (or its delegation logic)
    protected string $_table_roles = 'roles';
    protected string $_table_perms = 'permissions';
    protected string $_table_role_perms = 'role_perms';
    // User-Roles table (optional, depending on its use elsewhere)
    protected string $_table_user_roles = 'user_roles';
    // The core ACL instance for handling nested set logic and permission definitions
    private ACL $acl;

    // =================================================================
    // 2. CONSTRUCTOR
    // =================================================================

    public function __construct()
    {
        parent::__construct();
        $this->acl = new ACL();
    }

    // =================================================================
    // 3. ROLE MANAGEMENT (Delegated to ACL)
    // =================================================================

    /**
     * Retrieves a list of all roles in Nested Set order (full data).
     * @return array
     */
    public function roleList(): array
    {
        return $this->acl->getAllRoles('full');
    }

    /**
     * Retrieves single role details by ID.
     *
     * NOTE: This implementation is inefficient (loads all roles, then filters).
     * If the roles table is large, ACL should provide a getRoleById() method.
     *
     * @param int $id The role ID.
     * @return array|null
     */
    public function roleSingle(int $id): ?array
    {
        $roles = $this->acl->getAllRoles('full');
        foreach ($roles as $r) {
            if ($r['id'] === $id)
                return $r;
        }
        return null;
    }

    /**
     * Saves a role (create new or update existing name).
     * Handles name uniqueness check and creation via ACL (Nested Set).
     *
     * @param string $roleName The name of the role.
     * @param int|null $parentId The ID of the parent role for creation.
     * @param int|null $roleId The ID of the role to update (if exists).
     * @return int|string Role ID on success, or string error message on failure.
     */
    public function saveRole(string $roleName, ?int $parentId, ?int $roleId): int|string
    {
        try {
            // 1. UPDATE
            if (!empty($roleId)) {
                // Delegate name update to ACL
                $this->acl->updateRoleName($roleId, $roleName);
                // NOTE: Role movement (parentId change) is handled by updateRole() below, not here.
                return $roleId;
            }

            // 2. CREATE
            if ($this->acl->roleNameExists($roleName)) {
                // Use _() for the error message
                return _("A role with this name already exists.");
            }

            // Delegate creation (Nested Set logic) to ACL
            return $this->acl->addRole($roleName, $parentId);
        } catch (CkvException $e) {
            // Catch ACL/DB errors and return a user-friendly message
            if (str_contains($e->getMessage(), 'Parent role not found')) {
                // Use _() for the error message
                return _("The selected parent role was not found.");
            }
            // Use _() for the error message
            return _("An unexpected error occurred during role save: ") . $e->getMessage();
        } catch (\Exception $e) {
            // Use _() for the generic error message
            return _("An unexpected system error occurred.");
        }
    }

    /**
     * Updates an existing role's properties (name and parent ID/position).
     * Delegates name update and movement to ACL.
     *
     * @param int $id The role ID.
     * @param array $data Contains 'roleName' and/or 'parentId'.
     * @return string|null Null on success, or string error message on failure.
     */
    public function updateRole(int $id, array $data): ?string
    {
        try {
            if (isset($data['roleName'])) {
                $this->acl->updateRoleName($id, $data['roleName']);
            }

            if (array_key_exists('parentId', $data)) {
                $currentParent = $this->acl->getParentId($id);
                $newParentId = $data['parentId'] ?? null;

                // Only move if parent actually changed
                if ($currentParent != $newParentId) {
                    $this->acl->moveRole($id, $newParentId);
                }
            }
            return null; // Success
        } catch (CkvException $e) {
            // Check for specific error messages from ACL component
            if (str_contains($e->getMessage(), 'New parent not found')) {
                // Use _() for the error message
                return _("The selected parent role was not found.");
            }
            if (str_contains($e->getMessage(), 'Cannot move a node into its own subtree')) {
                // Use _() for the error message
                return _("A role cannot be moved into its own subtree.");
            }
            // Use _() for the generic error message
            return _("An unexpected error occurred while updating the role.");
        }
    }

    // =================================================================
    // 4. PERMISSION DEFINITION MANAGEMENT (Delegated to ACL)
    // =================================================================

    /**
     * Delegates to ACL to retrieve all permission definitions (full data).
     *
     * @param string $format The format (e.g., 'full' for detailed array).
     * @return array
     */
    public function getAllPermissions(string $format = 'ids'): array
    {
        return $this->acl->getAllPerms($format);
    }

    /**
     * Retrieves a list of all permission definitions (full data).
     * Alias for getAllPermissions.
     * @return array
     */
    public function permList(): array
    {
        return $this->acl->getAllPerms('full');
    }

    /**
     * Retrieves a single permission definition by ID.
     *
     * NOTE: Inefficient implementation (loads all, then filters).
     *
     * @param int $id The permission ID.
     * @return array|null
     */
    public function permSingle(int $id): ?array
    {
        $perms = $this->acl->getAllPerms('full');
        foreach ($perms as $perm) {
            if ($perm['id'] === $id) {
                return $perm;
            }
        }
        return null;
    }

    /**
     * Creates a new permission definition. (DELEGATED TO ACL)
     *
     * @param array $data Contains 'permKey', 'permName', 'permDescription'.
     * @return int|string New permission ID on success, or string error message on failure.
     */
    public function createPermission(array $data): int|string
    {
        try {
            return $this->acl->createPermission($data);
        } catch (CkvException $e) {
            // Check for duplicate key constraint violation (SQLSTATE[23000])
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                // Use _() for the error message
                return _("A permission with this key already exists.");
            }
            // Use _() for the generic error message
            return _("Database error while creating permission.");
        }
    }

    /**
     * Updates an existing permission definition. (DELEGATED TO ACL)
     *
     * @param int $id The permission ID.
     * @param array $data Data to update.
     * @return string|null Null on success, or string error message on failure.
     */
    public function updatePermission(int $id, array $data): ?string
    {
        try {
            $this->acl->updatePermission($id, $data);
            return null; // Success
        } catch (CkvException $e) {
            // Check for duplicate key constraint violation (SQLSTATE[23000])
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                // Use _() for the error message
                return _("A permission with this key already exists.");
            }
            // Use _() for the generic error message
            return _("Database error while updating permission.");
        }
    }

    /**
     * Deletes a permission definition and associated role/user permissions. (DELEGATED TO ACL)
     *
     * @param int $id The permission ID.
     * @return string|null Null on success, or string error message on failure.
     */
    public function deletePermission(int $id): ?string
    {
        try {
            $this->acl->deletePermission($id);
            return null; // Success
        } catch (CkvException $e) {
            // ACL handles cleanup, catch general DB errors here
            // Use _() for the generic error message
            return _("Database error while deleting permission.");
        }
    }

    /**
     * Checks if a permission key already exists. (DELEGATED TO ACL)
     *
     * @param string $key The permission key.
     * @return bool
     */
    public function permExists(string $key): bool
    {
        return $this->acl->permissionKeyExists($key);
    }

    // =================================================================
    // 5. ROLE-PERMISSION ASSIGNMENT (Direct Model Logic)
    // =================================================================

    /**
     * Delegates to ACL to retrieve all roles (full data).
     *
     * @param string $format The format (e.g., 'full' for detailed array).
     * @return array
     */
    public function getAllRoles(string $format = 'ids'): array
    {
        return $this->acl->getAllRoles($format);
    }

    /**
     * Retrieves the permission values directly assigned to a specific role.
     * Does not include inherited permissions.
     *
     * @param int $roleId The role ID.
     * @return array An array mapping permID to its set value (0, 1, or 'X').
     */
    public function getRolePerms(int $roleId): array
    {
        $rows = $this->db->select("SELECT permID, value FROM {$this->_table_role_perms} WHERE roleID = :roleID", ['roleID' => $roleId]);
        $perms = [];
        foreach ($rows as $r)
        // Use 'X' as placeholder for 'no explicit setting' if value is null/empty, otherwise the saved value
            $perms[$r['permID']] = $r['value'] ?? 'X';
        return $perms;
    }

    /**
     * Sets the direct permission values for a specific role.
     * Deletes entries where $value === 'X' (no explicit setting).
     *
     * @param int $roleId The role ID.
     * @param array $perms An array mapping permID to its new value (0, 1, or 'X').
     * @return void
     */
    public function setRolePerms(int $roleId, array $perms): void
    {
        foreach ($perms as $permId => $value) {
            if ($value === 'X') {
                // Delete explicit setting if 'X' is passed
                $this->db->delete($this->_table_role_perms, "roleID = :roleID AND permID = :permID", ['roleID' => $roleId, 'permID' => $permId]);
            } else {
                // Insert or update the explicit setting (0 or 1)
                $this->db->insertUpdate($this->_table_role_perms, [
                    'roleID' => $roleId,
                    'permID' => $permId,
                    'value' => $value, // Should be 0 or 1
                    'date_changed' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}
