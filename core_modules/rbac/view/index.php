<?php
$defaultRedirect = 'rbac';
?>
<h1><?= $this->title ?></h1>

<fieldset>
    <legend><?= _('Create New Role') ?></legend>

    <div data-form="roleForm" class="ajax-form-container" data-url="rbac/roleList" data-json="1" data-message="<?= _('Role created successfully!') ?>">
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
        // Confirmation dialog uses translated string
        if (!confirm("<?= _('Really delete this role?') ?>")) {
            return;
        }

        fetchAndLog("<?= BASE_URI ?>rbac/deleteRole/" + id)
                .then(d => {
                    if (d && d.success) {
                        // Redirect on success (assuming successful deletion requires page refresh/redirect)
                        sendMessageAndRedirect('success', '<?= _("Delete") ?>', '<?= _("Role successfully deleted") ?>', [], "<?= BASE_URI ?>rbac");
                    } else {
                        // Display error translated
                        const msg = d?.error ?? d ?? "<?= _('Unknown Error') ?>";
                        alert(typeof msg === "string" ? msg : JSON.stringify(msg));
                    }
                })
                .catch(e => {
                    alert("<?= _('Error') ?>: " + (e && e.message ? e.message : e));
                });
    }
</script>
