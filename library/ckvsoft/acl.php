<?php

namespace ckvsoft;

/**
 * ACL with nested-set role storage and permission helpers.
 */
class ACL extends \ckvsoft\mvc\Config
{

    private $perms = [];      // Calculated permissions for current user
    private $userid = 0;      // Current user id
    private $userRoles = []; // Roles of current user

    public function __construct($user_id = -1)
    {
        parent::__construct();
        $this->userid = ($user_id != -1) ? $user_id : $_SESSION['user_id'] ?? 0;
        $this->userRoles = $this->getUserRoles();
        $this->buildACL();
    }

    /**
     * Build ACL (role-perms + user-perms).
     */
    private function buildACL()
    {
        if (!empty($this->userRoles)) {
            $this->perms = array_merge($this->perms, $this->getRolePerms($this->userRoles, true));
        }
        $this->perms = array_merge($this->perms, $this->getUserPerms($this->userid));
    }

    /**
     * Check if current user is superuser (user_id == 1).
     */
    public function isSuperuser(): bool
    {
        if (!isset($_SESSION['user_id'], $_SESSION['user_key'])) {
            return false;
        }
        $expectedKey = \ckvsoft\Hash::create('sha256', $_SESSION['user_id'], HASH_KEY);
        return hash_equals($expectedKey, $_SESSION['user_key']) && $_SESSION['user_id'] == 1;
    }

    // -----------------------------------------------------------------
    // Roles / Nested Set operations
    // -----------------------------------------------------------------

    /**
     * Add a new role under a parent or as root.
     *
     * @return int inserted role id
     * @throws \ckvsoft\CkvException If parent role is not found or a DB error occurs during transaction.
     */
    public function addRole(string $roleName, ?int $parentId = null): int
    {
        // If parent specified: insert as last child of parent
        if ($parentId) {
            $parent = $this->db->select(
                    "SELECT lft, rgt, depth FROM roles WHERE id = :id LIMIT 1",
                    ['id' => $parentId]
            );
            if (empty($parent)) {
                // Throw CkvException for consistency in application errors
                throw new \ckvsoft\CkvException("Parent role not found");
            }
            $parent = $parent[0];
            $insertAt = (int) $parent['rgt'];
            $depth = (int) $parent['depth'] + 1;

            // Make space
            $this->db->beginTransaction();
            try {
                $this->db->update('roles', ['rgt' => new DbExpr('rgt + ' . (2))], 'rgt >= :p', ['p' => $insertAt]);
                $this->db->update('roles', ['lft' => new DbExpr('lft + ' . (2))], 'lft >= :p', ['p' => $insertAt]);

                // Insert new node
                $this->db->insert('roles', [
                    'roleName' => $roleName,
                    'lft' => $insertAt,
                    'rgt' => $insertAt + 1,
                    'depth' => $depth
                ]);

                $newId = (int) $this->db->id();
                $this->db->commit();
                return $newId;
            } catch (\ckvsoft\CkvException $e) { // <-- Catch CkvException from Database
                $this->db->rollback();
                throw $e;
            }
        }

        // Insert as root (append at the end)
        $maxR = $this->db->select("SELECT MAX(rgt) AS r FROM roles", []);
        $lft = (($maxR[0]['r'] ?? 0) + 1);
        $this->db->insert('roles', [
            'roleName' => $roleName,
            'lft' => $lft,
            'rgt' => $lft + 1,
            'depth' => 0
        ]);
        return (int) $this->db->id();
    }

    /**
     * Move a role (and its subtree) to be a child of $newParentId.
     * If $newParentId is null, move to root (append).
     *
     * This implements a safe nested-set move without changing role IDs (permissions kept).
     * @throws \ckvsoft\CkvException If the new parent is not found or if the move is invalid.
     */
    public function moveRole(int $roleId, ?int $newParentId): bool
    {
        $node = $this->db->select("SELECT lft, rgt, depth FROM roles WHERE id = :id LIMIT 1", ['id' => $roleId]);
        if (empty($node))
            return false;
        $nodeL = (int) $node[0]['lft'];
        $nodeR = (int) $node[0]['rgt'];
        $nodeDepth = (int) $node[0]['depth'];
        $width = $nodeR - $nodeL + 1;

        // Target position
        if ($newParentId) {
            $parent = $this->db->select("SELECT lft, rgt, depth FROM roles WHERE id = :id LIMIT 1", ['id' => $newParentId]);
            if (empty($parent)) {
                // Throw CkvException for consistency in application errors
                throw new \ckvsoft\CkvException("New parent not found");
            }
            $parent = $parent[0];
            $dest = (int) $parent['rgt']; // insert before parent's rgt
            $newDepth = (int) $parent['depth'] + 1;
        } else {
            // move to root (append)
            $maxR = $this->db->select("SELECT MAX(rgt) AS r FROM roles", []);
            $dest = (($maxR[0]['r'] ?? 0) + 1);
            $newDepth = 0;
        }

        // Can't move into itself or its subtree
        if ($dest >= $nodeL && $dest <= $nodeR) {
            // Throw CkvException for consistency in application errors
            throw new \ckvsoft\CkvException("Cannot move a node into its own subtree");
        }

        $this->db->beginTransaction();
        try {
            // 1) mark subtree with negative values (to protect it)
            $this->db->update(
                    'roles',
                    ['lft' => new DbExpr('-lft'), 'rgt' => new DbExpr('-rgt')],
                    'lft >= :l AND rgt <= :r',
                    ['l' => $nodeL, 'r' => $nodeR]
            );

            // 2) close gap left by the subtree
            $this->db->update('roles', ['lft' => new DbExpr("lft - {$width}")], 'lft > :r', ['r' => $nodeR]);
            $this->db->update('roles', ['rgt' => new DbExpr("rgt - {$width}")], 'rgt > :r', ['r' => $nodeR]);

            // Adjust dest if it was after the removed node
            if ($dest > $nodeR) {
                $dest = $dest - $width;
            }

            // 3) make room at destination
            $this->db->update('roles', ['lft' => new DbExpr("lft + {$width}")], 'lft >= :d', ['d' => $dest]);
            $this->db->update('roles', ['rgt' => new DbExpr("rgt + {$width}")], 'rgt >= :d', ['d' => $dest]);

            // 4) move the marked subtree into place and fix depth
            $offset = $dest - $nodeL; // how much to add to absolute positions
            $depthDiff = $newDepth - $nodeDepth;
            // Convert negative lft/rgt back to positive and shift them
            $this->db->update(
                    'roles',
                    [
                        'lft' => new DbExpr("(-lft) + " . ((int) $offset)),
                        'rgt' => new DbExpr("(-rgt) + " . ((int) $offset)),
                        'depth' => new DbExpr("depth + " . ((int) $depthDiff))
                    ],
                    'lft < 0'
            );

            $this->db->commit();
            return true;
        } catch (\ckvsoft\CkvException $e) { // <-- Catch CkvException from Database
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Delete role and its subtree.
     */
    public function deleteRole(int $roleId): bool
    {
        $node = $this->db->select("SELECT lft, rgt FROM roles WHERE id = :id LIMIT 1", ['id' => $roleId]);
        if (empty($node))
            return false;
        $lft = (int) $node[0]['lft'];
        $rgt = (int) $node[0]['rgt'];
        $width = $rgt - $lft + 1;

        // Delete subtree (will throw CkvException on foreign key constraint violation)
        $this->db->delete('roles', "lft BETWEEN :l AND :r", ['l' => $lft, 'r' => $rgt]);

        // Close gap
        $this->db->update('roles', ['rgt' => new DbExpr("rgt - {$width}")], 'rgt > :r', ['r' => $rgt]);
        $this->db->update('roles', ['lft' => new DbExpr("lft - {$width}")], 'lft > :r', ['r' => $rgt]);

        return true;
    }

    /**
     * Return all roles. Format 'full' returns id, roleName and depth.
     */
    public function getAllRoles($format = 'ids')
    {
        $rows = $this->db->select("SELECT * FROM roles ORDER BY lft ASC");
        $resp = [];
        foreach ($rows as $row) {
            if (strtolower($format) === 'full') {
                $resp[] = [
                    'id' => $row['id'],
                    'roleName' => $row['roleName'],
                    'depth' => $row['depth']
                ];
            } else {
                $resp[] = $row['id'];
            }
        }
        return $resp;
    }

    /**
     * Get role name from id.
     */
    public function getRoleNameFromid($roleID)
    {
        $res = $this->db->select("SELECT roleName FROM roles WHERE id = :id LIMIT 1", ['id' => $roleID]);
        return $res[0]['roleName'] ?? null;
    }

    /**
     * Return direct children of a role (subtree excluding the node itself).
     */
    public function getChildren(int $roleId): array
    {
        $node = $this->db->select("SELECT lft, rgt FROM roles WHERE id = :id LIMIT 1", ['id' => $roleId]);
        if (empty($node))
            return [];
        return $this->db->select(
                        "SELECT * FROM roles WHERE lft BETWEEN :l AND :r AND id != :id ORDER BY lft ASC",
                        ['l' => $node[0]['lft'], 'r' => $node[0]['rgt'], 'id' => $roleId]
                );
    }

    /**
     * Return all managers/ancestors for a role, ordered by depth ASC (root first).
     */
    public function getManagers(int $roleId): array
    {
        $node = $this->db->select("SELECT lft, rgt FROM roles WHERE id = :id LIMIT 1", ['id' => $roleId]);
        if (empty($node))
            return [];
        return $this->db->select(
                        "SELECT * FROM roles WHERE lft < :l AND rgt > :r ORDER BY depth ASC",
                        ['l' => $node[0]['lft'], 'r' => $node[0]['rgt']]
                );
    }

    /**
     * Return direct parent id (or null).
     */
    public function getParentId(int $roleId): ?int
    {
        $managers = $this->getManagers($roleId);
        if (empty($managers))
            return null;
        // last manager in the list is the immediate parent
        $last = end($managers);
        return $last['id'] ?? null;
    }

    // -----------------------------------------------------------------
    // Permissions Management (CRUD on permissions table) - MOVED FROM RBAC MODEL
    // -----------------------------------------------------------------

    /**
     * Checks if a permission key already exists.
     */
    public function permissionKeyExists(string $key): bool
    {
        return !empty($this->db->select("SELECT id FROM permissions WHERE permKey = :key", ['key' => $key]));
    }

    /**
     * Creates a new permission.
     * * @param array $data Contains 'permKey', 'permName', 'permDescription'.
     * @return int New permission ID.
     * @throws \ckvsoft\CkvException On DB error (e.g., duplicate key).
     */
    public function createPermission(array $data): int
    {
        if (!$this->permissionKeyExists($data['permKey'] ?? '')) {
            $this->db->insert('permissions', $data);
        }
        return (int) $this->db->id();
    }

    /**
     * Updates an existing permission.
     * * @param int $id
     * @param array $data Data to update.
     * @throws \ckvsoft\CkvException On DB error (e.g., duplicate key).
     */
    public function updatePermission(int $id, array $data): void
    {
        $this->db->update('permissions', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Deletes a permission and associated role/user permissions.
     * * @param int $id
     * @throws \ckvsoft\CkvException On DB error (e.g., foreign key violation).
     */
    public function deletePermission(int $id): void
    {
        // Cleanup associated role/user permissions first
        $this->db->delete('role_perms', "permID = :id", ['id' => $id]);
        $this->db->delete('user_perms', "permID = :id", ['id' => $id]);
        // Delete the permission definition
        $this->db->delete('permissions', "id = :id", ['id' => $id]);
    }

    // -----------------------------------------------------------------
    // Permissions Lookups
    // -----------------------------------------------------------------

    public function getPermKeyFromid($permID)
    {
        $res = $this->db->select("SELECT permKey FROM permissions WHERE id = :id LIMIT 1", ['id' => $permID]);
        return $res[0]['permKey'] ?? null;
    }

    public function getPermNameFromid($permID)
    {
        $res = $this->db->select("SELECT permName FROM permissions WHERE id = :id LIMIT 1", ['id' => $permID]);
        return $res[0]['permName'] ?? null;
    }

    /**
     * Return all permissions. 'full' returns associative details keyed by permKey.
     */
    public function getAllPerms($format = 'ids')
    {
        $rows = $this->db->select("SELECT * FROM permissions ORDER BY permName ASC");
        $resp = [];
        foreach ($rows as $row) {
            if (strtolower($format) === 'full') {
                $resp[$row['permKey']] = [
                    'id' => $row['id'],
                    'permName' => $row['permName'],
                    'permKey' => $row['permKey'],
                    'permDescription' => $row['permDescription']
                ];
            } else {
                $resp[] = $row['id'];
            }
        }
        return $resp;
    }

    /**
     * Get role permissions (optionally include inherited from ancestors).
     *
     * Returns array of permission entries (not just IDs):
     * [
     * 'perm.key' => ['perm'=>'perm.key','inheritted'=>true,'value'=>true,'permName'=>..., 'id'=>...],
     * ...
     * ]
     */
    public function getRolePerms(array $roleIds, bool $includeInherited = false): array
    {
        if (empty($roleIds))
            return [];

        // named placeholders for IN()
        $placeholders = [];
        $params = [];
        foreach ($roleIds as $i => $rid) {
            $ph = ":r{$i}";
            $placeholders[] = $ph;
            $params["r{$i}"] = $rid;
        }

        $rows = $this->db->select(
                "SELECT * FROM role_perms WHERE roleID IN (" . implode(',', $placeholders) . ")",
                $params
        );

        $perms = [];
        foreach ($rows as $row) {
            $key = strtolower($this->getPermKeyFromid($row['permID']));
            $perms[$key] = [
                'perm' => $key,
                'inheritted' => true,
                'value' => ((string) $row['value'] === '1'),
                'permName' => $this->getPermNameFromid($row['permID']),
                'id' => $row['permID']
            ];
        }

        if ($includeInherited) {
            foreach ($roleIds as $rid) {
                $managers = $this->getManagers($rid);
                foreach ($managers as $m) {
                    // Only merge if not already explicitly set in a closer role
                    $perms = array_merge($perms, $this->getRolePerms([$m['id']], false));
                }
            }
        }

        return $perms;
    }

    /**
     * Get permissions assigned directly to the user.
     */
    private function getUserPerms(int $userId): array
    {
        $rows = $this->db->select("SELECT * FROM user_perms WHERE userID = :u", ['u' => $userId]);
        $perms = [];
        foreach ($rows as $row) {
            $key = strtolower($this->getPermKeyFromid($row['permID']));
            $perms[$key] = [
                'perm' => $key,
                'inheritted' => false,
                'value' => ((string) $row['value'] === '1'),
                'permName' => $this->getPermNameFromid($row['permID']),
                'id' => $row['permID']
            ];
        }
        return $perms;
    }

    // -----------------------------------------------------------------
    // User roles / checks
    // -----------------------------------------------------------------

    public function getUserRoles(): array
    {
        $rows = $this->db->select("SELECT roleID FROM user_roles WHERE userID = :u ORDER BY date_added ASC", ['u' => $this->userid]);
        return array_map(fn($r) => $r['roleID'], $rows);
    }

    public function userHasRole($roleID): bool
    {
        if ($this->isSuperuser())
            return true;
        return in_array((int) $roleID, $this->userRoles, true);
    }

    /**
     * Check permission key for current user.
     */
    public function hasPermission(string $permKey): bool
    {
        $key = strtolower($permKey);
        if ($this->isSuperuser()) {
            $perm = $this->db->select("SELECT id FROM permissions WHERE permKey = :k", ['k' => $key]);
            if (!$perm) {
                // Auto-create permission for superuser if it doesn't exist
                $this->db->insert('permissions', [
                    'permKey' => $key,
                    'permName' => ucfirst($key),
                    'permDescription' => 'Auto-created for superuser'
                ]);
            }
            return true;
        }
        return isset($this->perms[$key]) && $this->perms[$key]['value'] === true;
    }

    public function getUserName($userId)
    {
        $res = $this->db->select("SELECT username FROM user WHERE user_id = :id LIMIT 1", ['id' => $userId]);
        return $res[0]['username'] ?? null;
    }
}
