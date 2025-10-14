<fieldset>
    <div data-form="editRoleForm" data-json="1">
        <legend>Edit Role: <?= htmlspecialchars($this->role['roleName']); ?></legend>

        <form action="<?= BASE_URI ?>rbac/editRoleSave" method="post" id="editRoleForm" data-redirect="rbac">
            <input type="hidden" name="role_id" value="<?= $this->role['id'] ?>">

            <label for="roleName">Role Name:</label>
            <input type="text" id="roleName" name="roleName"
                   value="<?= htmlspecialchars($this->role['roleName']) ?>" required>

            <label for="parentId">Parent Role:</label>
            <select name="parentId" id="parentId">
                <option value="">-- None --</option>
                <?php foreach ($this->roles as $r): ?>
                    <?php if ($r['id'] == $this->role['id']) continue; // cannot be parent to itself ?>
                    <option value="<?= $r['id'] ?>" <?= ((isset($this->role['parentId']) && $r['id'] == $this->role['parentId']) ? 'selected' : '') ?>>
                        <?= str_repeat('&nbsp;&nbsp;', $r['depth']) . htmlspecialchars($r['roleName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div style="margin-top:12px;">
                <button type="button"
                        id="saveRoleAndPerms"
                        class="button small-action save"
                        data-forms-to-save="editRoleForm,rolePermissionsForm">
                    Save Role & Permissions
                </button>
            </div>
        </form>
    </div>
</fieldset>
