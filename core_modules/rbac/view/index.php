<h1><?= $this->title ?></h1>

<fieldset>
    <legend><?= _('Create New Role') ?></legend>

    <div data-form="roleForm" class="ajax-form-container" data-url="rbac/roleList" data-json="1">
        <form id="roleForm" action="<?= BASE_URI ?>rbac/saveRole" method="post" autocomplete="off">
            <label for="roleName"><?= _('Role Name') ?>:</label>
            <input type="text" id="roleName" name="roleName" required><br />

            <div id="right">
                <label for="parentRole"><?= _('Parent Role (optional)') ?>:</label>
                <select id="parentRole" name="parentId">
                    <option value="">-- <?= _('None') ?> --</option>
                    <?php foreach (($this->roles ?? []) as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= str_repeat('— ', $role['depth']) . htmlspecialchars($role['roleName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <br /><br />

            <button type="submit" class="button small-action save"><?= _('Create Role') ?></button>
            <input class="button small-action cancel" type="reset" value="<?= _('Clear') ?>">

        </form>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend><?= _('Existing Roles') ?></legend>
    <div data-list="rbac/roleList" id="role-list" class="ajax-list"></div>
</fieldset>

<script>
    /**
     * Handles the asynchronous deletion of a role.
     * @param {number} id - The ID of the role to delete.
     */
    function deleteRole(id) {
        // Confirmation dialog translated
        if (!confirm("<?= _('Really delete this role?') ?>"))
            return;

        fetch("<?= BASE_URI ?>rbac/deleteRole/" + id)
                .then(r => r.json())
                .then(d => {
                    if (d.success)
                        // Redirect on success
                        location.href = "<?= BASE_URI ?>rbac";
                    else
                        // Display error translated
                        alert(JSON.stringify(d.error || d));
                }).catch(e => alert(e));
    }
</script>