<?php
$defaultRedirect = 'rbac';
?>
<fieldset>
    <legend><?= _('Edit Role') ?>: <?= htmlspecialchars($this->role['roleName']); ?></legend>
    <div data-form="editRoleForm" data-json="1">

        <form action="<?= BASE_URI ?>rbac/editRoleSave" method="post" id="editRoleForm" data-redirect="<?= $defaultRedirect ?>">
            <input type="hidden" name="role_id" value="<?= $this->role['id'] ?>">

            <label for="roleName"><?= _('Role Name') ?>:</label>
            <input type="text" id="roleName" name="roleName"
                   value="<?= htmlspecialchars($this->role['roleName']) ?>" required>

            <label for="parentId"><?= _('Parent Role') ?>:</label>
            <select name="parentId" id="parentId">
                <option value="">-- <?= _('None') ?> --</option>
                <?php foreach ($this->roles as $r): ?>
                    <?php if ($r['id'] == $this->role['id']) continue; // cannot be parent to itself ?>
                    <option value="<?= $r['id'] ?>" <?= ((isset($this->role['parentId']) && $r['id'] == $this->role['parentId']) ? 'selected' : '') ?>>
                        <?= str_repeat('&nbsp;&nbsp;', $r['depth']) . htmlspecialchars($r['roleName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <br /><br />

            <input type="button" id="saveRoleAndPerms" class="button small-action save" data-forms-to-save="editRoleForm,rolePermissionsForm"
                   value="<?= _('Save Role & Permissions') ?>">

            <input type="button" class="button small-action cancel"
                   onclick="javascript:window.location = '<?= BASE_URI . $defaultRedirect ?>';"
                   value="<?= _('Cancel') ?>">
        </form>
    </div>
</fieldset>