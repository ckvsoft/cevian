<?php

class Rbac extends \ckvsoft\mvc\Model
{

    private $_table_roles = 'roles';
    private $_table_perms = 'permissions';
    private $_table_role_perms = 'role_perms';
    private $acl;

    public function __construct()
    {
        parent::__construct();
        $this->acl = new \ckvsoft\ACL();
    }

    /** Roles * */
    public function roleList(): array
    {
        return $this->acl->getAllRoles('full');
    }

    public function roleSingle(int $id): ?array
    {
        $roles = $this->acl->getAllRoles('full');
        foreach ($roles as $r) {
            if ($r['id'] === $id)
                return $r;
        }
        return null;
    }

    public function updateRole(int $id, array $data): ?string
    {
        try {
            if (isset($data['roleName'])) {
                $this->db->update($this->_table_roles, ['roleName' => $data['roleName']], 'id = :id', ['id' => $id]);
            }
            if (isset($data['parentId'])) {
                $currentParent = $this->acl->getParentId($id);
                if ($currentParent != ($data['parentId'] ?? 0)) {
                    $this->acl->moveRole($id, $data['parentId'] ?? null);
                }
            }
            return null; // Success
        } catch (\ckvsoft\CkvException $e) {
            if (str_contains($e->getMessage(), 'New parent not found')) {
                return "The selected parent role was not found.";
            }
            if (str_contains($e->getMessage(), 'Cannot move a node into its own subtree')) {
                return "A role cannot be moved into its own subtree.";
            }
            return "An unexpected error occurred while moving or updating the role.";
        }
    }

    /** Permissions * */
    public function permList(): array
    {
        return $this->acl->getAllPerms('full');
    }

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
     * Creates a new permission. (DELEGATED TO ACL)
     *
     * @param array $data
     * @return int|string New permission ID on success, or string error message on failure.
     */
    public function createPermission(array $data): int|string
    {
        try {
            // DELEGIERUNG: Ruft die neue ACL-Methode auf
            return $this->acl->createPermission($data);
        } catch (\ckvsoft\CkvException $e) {
            // Fehlerbehandlung der CkvException
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                return "A permission with this key already exists.";
            }
            return "Database error while creating permission.";
        }
    }

    /**
     * Updates an existing permission. (DELEGATED TO ACL)
     *
     * @param int $id
     * @param array $data
     * @return string|null Null on success, or string error message on failure.
     */
    public function updatePermission(int $id, array $data): ?string
    {
        try {
            // DELEGIERUNG: Ruft die neue ACL-Methode auf
            $this->acl->updatePermission($id, $data);
            return null; // Success
        } catch (\ckvsoft\CkvException $e) {
            // Fehlerbehandlung der CkvException
            if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
                return "A permission with this key already exists.";
            }
            return "Database error while updating permission.";
        }
    }

    /**
     * Deletes a permission and associated role permissions. (DELEGATED TO ACL)
     *
     * @param int $id
     * @return string|null Null on success, or string error message on failure.
     */
    public function deletePermission(int $id): ?string
    {
        try {
            // DELEGIERUNG: Ruft die neue ACL-Methode auf
            $this->acl->deletePermission($id);
            return null; // Success
        } catch (\ckvsoft\CkvException $e) {
            // Fehlerbehandlung der CkvException
            // Die ACL kümmert sich jetzt um die Foreign Keys, aber wir fangen allgemeine DB-Fehler ab.
            return "Database error while deleting permission.";
        }
    }

    /**
     * Checks if a permission key already exists. (DELEGATED TO ACL)
     */
    public function permExists(string $key): bool
    {
        // DELEGIERUNG: Ruft die neue ACL-Methode auf
        return $this->acl->permissionKeyExists($key);
    }

    /** Role-Permission Assignment * */
    public function getRolePerms(int $roleId): array
    {
        // Bleibt im Model, da es sich um eine spezifische View-Logik für direkt zugewiesene Rechte handelt.
        $rows = $this->db->select("SELECT permID, value FROM {$this->_table_role_perms} WHERE roleID = :roleID", ['roleID' => $roleId]);
        $perms = [];
        foreach ($rows as $r)
            $perms[$r['permID']] = $r['value'] ?? 'X';
        return $perms;
    }

    public function setRolePerms(int $roleId, array $perms): void
    {
        // Bleibt im Model, da es die direkte Zuordnung (Zuweisung) von Rechten verwaltet.
        foreach ($perms as $permId => $value) {
            if ($value === 'X') {
                $this->db->delete($this->_table_role_perms, "roleID = :roleID AND permID = :permID", ['roleID' => $roleId, 'permID' => $permId]);
            } else {
                $this->db->insertUpdate($this->_table_role_perms, [
                    'roleID' => $roleId,
                    'permID' => $permId,
                    'value' => $value,
                    'date_changed' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}
