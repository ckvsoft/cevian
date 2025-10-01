<?php

namespace ckvsoft;

use ckvsoft\DbExpr;
use ckvsoft\CkvException;

/**
 * Access Control List (ACL) component with nested-set role storage and
 * permission resolution logic.
 */
class ACL extends \ckvsoft\mvc\Config
{

    // =================================================================
    // 1. PROPERTIES
    // =================================================================

    private array $perms = [];     // Calculated permissions (role + user overrides) for the current user (keyed by permKey)
    private int $userid = 0;       // Current user ID
    private array $userRoles = []; // Roles assigned directly to the current user

    // =================================================================
    // 2. CONSTRUCTOR & INITIALIZATION
    // =================================================================

    /**
     * ACL constructor. Initializes user context and calculates effective permissions.
     */
    public function __construct(int $user_id = -1)
    {
        parent::__construct();
        // Set user ID
        $this->userid = ($user_id !== -1) ? $user_id : $_SESSION['user_id'] ?? 0;
        // Retrieve roles
        $this->userRoles = $this->getUserRoles();
        // Calculate the final permissions map
        $this->buildACL();
    }

    /**
     * Check if the current user is the superuser (user_id == 1).
     */
    public function isSuperuser(): bool
    {
        if (!isset($_SESSION['user_id'], $_SESSION['user_key'])) {
            return false;
        }
        $expectedKey = \ckvsoft\Hash::create('sha256', $_SESSION['user_id'], HASH_KEY);
        return hash_equals($expectedKey, $_SESSION['user_key']) && $_SESSION['user_id'] === 1;
    }

    // =================================================================
    // 3. ROLE MANAGEMENT (NESTED SET CRUD & LOOKUPS)
    // =================================================================

    /**
     * Add a new role under a parent or as a new root node.
     *
     * @param string $roleName The name of the new role.
     * @param int|null $parentId The ID of the parent role, or null for a root role.
     * @return int The ID of the inserted role.
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
                throw new CkvException("Parent role not found");
            }
            $parent = $parent[0];
            $insertAt = (int) $parent['rgt'];
            $depth = (int) $parent['depth'] + 1;

            // Transaction for Nested Set insert
            $this->db->beginTransaction();
            try {
                // Make space
                $this->db->update('roles', ['rgt' => new DbExpr('rgt + 2')], 'rgt >= :p', ['p' => $insertAt]);
                $this->db->update('roles', ['lft' => new DbExpr('lft + 2')], 'lft >= :p', ['p' => $insertAt]);

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
            } catch (CkvException $e) {
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
     * Implements a safe nested-set move.
     * @param int $roleId The ID of the role to move.
     * @param int|null $newParentId The ID of the new parent role, or null to move to root.
     * @throws \ckvsoft\CkvException If the new parent is not found or if the move is invalid (e.g., into its own subtree).
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

        // Calculate target position
        if ($newParentId) {
            $parent = $this->db->select("SELECT lft, rgt, depth FROM roles WHERE id = :id LIMIT 1", ['id' => $newParentId]);
            if (empty($parent)) {
                throw new CkvException("New parent not found");
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

        // Prevent moving into itself or its subtree
        if ($dest >= $nodeL && $dest <= $nodeR) {
            throw new CkvException("Cannot move a node into its own subtree");
        }

        $this->db->beginTransaction();
        try {
            // 1) Mark subtree with negative values
            $this->db->update(
                    'roles',
                    ['lft' => new DbExpr('-lft'), 'rgt' => new DbExpr('-rgt')],
                    'lft >= :l AND rgt <= :r',
                    ['l' => $nodeL, 'r' => $nodeR]
            );

            // 2) Close gap left by the subtree
            $this->db->update('roles', ['lft' => new DbExpr("lft - {$width}")], 'lft > :r', ['r' => $nodeR]);
            $this->db->update('roles', ['rgt' => new DbExpr("rgt - {$width}")], 'rgt > :r', ['r' => $nodeR]);

            // Adjust destination if it was after the removed node
            if ($dest > $nodeR) {
                $dest = $dest - $width;
            }

            // 3) Make room at destination
            $this->db->update('roles', ['lft' => new DbExpr("lft + {$width}")], 'lft >= :d', ['d' => $dest]);
            $this->db->update('roles', ['rgt' => new DbExpr("rgt + {$width}")], 'rgt >= :d', ['d' => $dest]);

            // 4) Move the marked subtree into place and fix depth
            $offset = $dest - $nodeL;
            $depthDiff = $newDepth - $nodeDepth;
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
        } catch (CkvException $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Updates a role's name.
     * @param int $roleId The ID of the role to update.
     * @param string $roleName The new name for the role.
     * @throws \ckvsoft\CkvException On DB error.
     */
    public function updateRoleName(int $roleId, string $roleName): void
    {
        $this->db->update('roles', ['roleName' => $roleName], 'id = :id', ['id' => $roleId]);
    }

// ... (Letztes Fragment der ACL-Klasse) ...

    /**
     * Delete role and its subtree.
     * @param int $roleId The ID of the role to delete.
     * @throws \ckvsoft\CkvException On DB error (e.g., foreign key constraint violation).
     */
    public function deleteRole(int $roleId): bool
    {
        $node = $this->db->select("SELECT lft, rgt FROM roles WHERE id = :id LIMIT 1", ['id' => $roleId]);
        if (empty($node)) {
            return false;
        }
        $nodeL = (int) $node[0]['lft'];
        $nodeR = (int) $node[0]['rgt'];
        $width = $nodeR - $nodeL + 1; // Correctly calculate the width of the subtree

        $this->db->beginTransaction();
        try {
            // 1. Delete all nodes in the subtree
            $this->db->delete('roles', 'lft >= :l AND rgt <= :r', ['l' => $nodeL, 'r' => $nodeR]);

            // 2. Close the gap left by the deleted subtree
            $this->db->update('roles', ['lft' => new DbExpr("lft - {$width}")], 'lft > :r', ['r' => $nodeR]);
            $this->db->update('roles', ['rgt' => new DbExpr("rgt - {$width}")], 'rgt > :r', ['r' => $nodeR]);

            // NOTE: Associated permissions (role_perms) and user assignments (user_roles)
            // should ideally be handled by CASCADE DELETE constraints in the database.
            // If not, explicit deletion logic must be added here (or in Rbac_Model).

            $this->db->commit();
            return true;
        } catch (CkvException $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Checks if a role name already exists.
     */
    public function roleNameExists(string $roleName): bool
    {
        $exists = $this->db->select(
                "SELECT id FROM roles WHERE roleName = :name LIMIT 1",
                ['name' => $roleName]
        );

        return !empty($exists);
    }

    /**
     * Return all roles, ordered by Nested Set (tree traversal).
     * Format 'full' returns id, roleName and depth.
     */
    public function getAllRoles(string $format = 'ids'): array
    {
        $rows = $this->db->select("SELECT * FROM roles ORDER BY lft ASC");
        $resp = [];
        foreach ($rows as $row) {
            if (strtolower($format) === 'full') {
                $resp[] = [
                    'id' => (int) $row['id'],
                    'roleName' => $row['roleName'],
                    'depth' => (int) $row['depth']
                ];
            } else {
                $resp[] = (int) $row['id'];
            }
        }
        return $resp;
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
     * Get role name from id.
     */
    public function getRoleNameFromid(int $roleID): ?string
    {
        $res = $this->db->select("SELECT roleName FROM roles WHERE id = :id LIMIT 1", ['id' => $roleID]);
        return $res[0]['roleName'] ?? null;
    }

    // =================================================================
    // 4. PERMISSION DEFINITION MANAGEMENT (CRUD)
    // =================================================================

    /**
     * Creates a new permission.
     * @param array $data Contains 'permKey', 'permName', 'permDescription'.
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
     * @param int $id The ID of the permission to update.
     * @param array $data Data to update.
     * @throws \ckvsoft\CkvException On DB error (e.g., duplicate key).
     */
    public function updatePermission(int $id, array $data): void
    {
        $this->db->update('permissions', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * Deletes a permission and associated role/user permissions.
     * @param int $id The ID of the permission to delete.
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

    /**
     * Checks if a permission key already exists.
     */
    public function permissionKeyExists(string $key): bool
    {
        return !empty($this->db->select("SELECT id FROM permissions WHERE permKey = :key", ['key' => $key]));
    }

    /**
     * Return all permission definitions. 'full' returns associative details keyed by permKey.
     */
    public function getAllPerms(string $format = 'ids'): array
    {
        $rows = $this->db->select("SELECT * FROM permissions ORDER BY permName ASC");
        $resp = [];
        foreach ($rows as $row) {
            if (strtolower($format) === 'full') {
                $resp[$row['permKey']] = [
                    'id' => (int) $row['id'],
                    'permName' => $row['permName'],
                    'permKey' => $row['permKey'],
                    'permDescription' => $row['permDescription']
                ];
            } else {
                $resp[] = (int) $row['id'];
            }
        }
        return $resp;
    }

    /**
     * Get permission key from ID.
     */
    public function getPermKeyFromid(int $permID): ?string
    {
        $res = $this->db->select("SELECT permKey FROM permissions WHERE id = :id LIMIT 1", ['id' => $permID]);
        return $res[0]['permKey'] ?? null;
    }

    /**
     * Get permission name from ID.
     */
    public function getPermNameFromid(int $permID): ?string
    {
        $res = $this->db->select("SELECT permName FROM permissions WHERE id = :id LIMIT 1", ['id' => $permID]);
        return $res[0]['permName'] ?? null;
    }

    // =================================================================
    // 5. PERMISSION ASSIGNMENT & RESOLUTION (ROLE/USER PERMS)
    // =================================================================

    /**
     * Get role permissions (optionally include inherited from ancestors).
     *
     * Returns array of permission entries: [perm.key => ['perm'=>..., 'value'=>true, ...]]
     *
     * NOTE: Performance could be improved by caching key/name lookups or using a JOIN.
     */
    public function getRolePerms(array $roleIds, bool $includeInherited = false): array
    {
        if (empty($roleIds))
            return [];

        // Named placeholders for IN() clause
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
            $key = strtolower($this->getPermKeyFromid($row['permID']) ?? '');
            if ($key === '')
                continue;

            $perms[$key] = [
                'perm' => $key,
                'inheritted' => true,
                'value' => ((string) $row['value'] === '1'),
                'permName' => $this->getPermNameFromid($row['permID']),
                'id' => (int) $row['permID']
            ];
        }

        if ($includeInherited) {
            foreach ($roleIds as $rid) {
                $managers = $this->getManagers($rid);
                foreach ($managers as $m) {
                    // Inherited permissions are merged. Explicit settings in $perms take priority.
                    $inheritedPerms = $this->getRolePerms([$m['id']], false);
                    $perms = array_merge($inheritedPerms, $perms);
                }
            }
        }

        return $perms;
    }

// --- NEUE METHODE IN ckvsoft\ACL ---

    /**
     * Berechnet die effektiven Berechtigungen für eine Rolle, indem die Hierarchie
     * (Role und Ancestors) nach der ersten expliziten Zuweisung (0 oder 1) durchsucht wird.
     *
     * @param int $roleId Die ID der zu prüfenden Rolle.
     * @return array [permID => effectiveValue (bool)]
     */

    /**
     * Berechnet die effektiven Berechtigungen für eine Rolle, indem die Hierarchie
     * (Role und Ancestors) nach der ersten expliziten Zuweisung (0 oder 1) durchsucht wird.
     *
     * @param int $roleId Die ID der zu prüfenden Rolle.
     * @return array [permID => effectiveValue (bool)]
     */
    public function getRoleEffectivePermissions(int $roleId): array
    {
        $effectivePerms = [];
        // 1. Alle verfügbaren Rechte definieren (als Basis)
        $allPermDefs = $this->db->select("SELECT id, permKey FROM permissions");

        // Initialisieren aller effektiven Rechte auf false (Deny-by-Default)
        foreach ($allPermDefs as $def) {
            $effectivePerms[(int) $def['id']] = false;
        }

        // 2. Rollen-IDs abrufen, die zur Auflösung beitragen (Rolle selbst + alle Manager/Ahnen)
        $relevantRoleIds = [$roleId];
        $managers = $this->getManagers($roleId);
        foreach ($managers as $manager) {
            $relevantRoleIds[] = (int) $manager['id'];
        }

        if (empty($relevantRoleIds)) {
            return $effectivePerms;
        }

        // 💥 KORREKTUR: Benannte Platzhalter für die IN() Klausel erstellen.
        // Die Keys im $params-Array sind OHNE Doppelpunkt, da _prepareAndBind den Doppelpunkt hinzufügt.
        $placeholders = [];
        $params = [];
        foreach ($relevantRoleIds as $index => $id) {
            $name = "roleId_{$index}"; // Key Name OHNE Doppelpunkt für $params
            $key = ":{$name}";          // Platzhalter MIT Doppelpunkt für SQL
            $placeholders[] = $key;
            $params[$name] = $id;    // Parameter Key OHNE Doppelpunkt
        }
        $inClause = implode(',', $placeholders);

        // 3. Rollendaten für Sortierung abrufen (benötigt lft für Hierarchie-Reihenfolge)
        $roleRows = $this->db->select(
                "SELECT id, lft FROM roles WHERE id IN ({$inClause}) ORDER BY lft ASC",
                $params
        );

        // 4. Zuweisungen abrufen: RoleID, PermID, Value
        $permRows = $this->db->select(
                "SELECT roleID, permID, value FROM role_perms WHERE roleID IN ({$inClause})",
                $params
        );

        // 5. Alle Zuweisungen nach Rolle gruppieren
        $roleAssignments = [];
        foreach ($permRows as $row) {
            $roleAssignments[(int) $row['roleID']][(int) $row['permID']] = $row['value'];
        }

        // 6. Merging: Von der Wurzel zur aktuellen Rolle (lft ASC)
        $sortedRoleIds = array_column($roleRows, 'id');

        foreach ($sortedRoleIds as $currentRoleId) {
            if (isset($roleAssignments[$currentRoleId])) {
                foreach ($roleAssignments[$currentRoleId] as $permID => $value) {
                    // Nur wenn Wert explizit 0 oder 1 ist (nicht 'X'/'null')
                    if ($value == 1 || $value == 0) {
                        // Der gefundene Wert (näher am Blatt/der aktuellen Rolle) überschreibt den vorherigen (vom Ahnen).
                        $effectivePerms[$permID] = ($value == 1);
                    }
                }
            }
        }

        return $effectivePerms;
    }

    // =================================================================
    // 6. USER/ROLE QUERIES & CHECKS
    // =================================================================

    /**
     * Check permission key for current user.
     */
    public function hasPermission(string $permKey): bool
    {
        $key = strtolower($permKey);

        if ($this->isSuperuser()) {
            $perm = $this->db->select("SELECT id, is_used FROM permissions WHERE permKey = :k", ['k' => $key]);

            if (!$perm) {
                // Auto-create permission by superuser if it doesn't exist
                $this->db->insert('permissions', [
                    'permKey' => $key,
                    'permName' => ucfirst($key),
                    'permDescription' => 'Auto-created by superuser',
                    'is_used' => 1
                ]);
            } else {
                if (isset($perm[0]['is_used']) && $perm[0]['is_used'] == 0) {
                    $this->db->update('permissions', ['is_used' => 1], 'id = :id_val', ['id_val' => $perm[0]['id']]);
                }
            }

            return true;
        }

        return isset($this->perms[$key]) && $this->perms[$key]['value'] === true;
    }

    /**
     * Return roles assigned directly to the current user.
     */
    public function getUserRoles(): array
    {
        $rows = $this->db->select("SELECT roleID FROM user_roles WHERE userID = :u ORDER BY date_added ASC", ['u' => $this->userid]);
        return array_map(fn($r) => (int) $r['roleID'], $rows);
    }

    /**
     * Check if current user has a specific role.
     */
    public function userHasRole($roleID): bool
    {
        if ($this->isSuperuser())
            return true;
        return in_array((int) $roleID, $this->userRoles, true);
    }

    /**
     * Get user name from user id.
     * NOTE: This is a user-specific lookup and ideally belongs in a separate User/Auth component.
     */
    public function getUserName(int $userId): ?string
    {
        $res = $this->db->select("SELECT username FROM user WHERE user_id = :id LIMIT 1", ['id' => $userId]);
        return $res[0]['username'] ?? null;
    }

    // =================================================================
    // 7. INTERNAL LOGIC (PRIVATE METHODS)
    // =================================================================

    /**
     * Builds the final ACL map by merging role permissions and user overrides.
     */
    private function buildACL(): void
    {
        // 1. Load role permissions (including inheritance)
        if (!empty($this->userRoles)) {
            $this->perms = array_merge($this->perms, $this->getRolePerms($this->userRoles, true));
        }
        // 2. Apply user overrides (user perms overwrite role perms)
        $this->perms = array_merge($this->perms, $this->getUserPerms($this->userid));
    }

    /**
     * Get permissions assigned directly to the user (overrides).
     */
    private function getUserPerms(int $userId): array
    {
        $rows = $this->db->select("SELECT * FROM user_perms WHERE userID = :u", ['u' => $userId]);
        $perms = [];
        foreach ($rows as $row) {
            $key = strtolower($this->getPermKeyFromid($row['permID']) ?? '');
            if ($key === '')
                continue;

            $perms[$key] = [
                'perm' => $key,
                'inheritted' => false,
                'value' => ((string) $row['value'] === '1'),
                'permName' => $this->getPermNameFromid($row['permID']),
                'id' => (int) $row['permID']
            ];
        }
        return $perms;
    }
}
