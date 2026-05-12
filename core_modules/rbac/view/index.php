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

    <?php
    $modules      = (array)  ($this->modules      ?? []);
    $activeModule = (string) ($this->activeModule ?? '');
    $listUrl      = 'rbac/roleList';
    $initialUrl   = $activeModule !== ''
            ? $listUrl . '?module=' . urlencode($activeModule)
            : $listUrl;
    if (!empty($modules)):
    ?>
        <div class="rbac-module-filter" style="margin-bottom: 12px;">
            <label for="moduleFilterRoles"><?= _('Module') ?>:</label>
            <select id="moduleFilterRoles" class="form-control" style="display:inline-block; width:auto;">
                <option value=""><?= _('All modules') ?></option>
                <?php foreach ($modules as $mod): ?>
                    <option value="<?= htmlspecialchars($mod) ?>"
                            <?php if ($mod === $activeModule): ?>selected<?php endif; ?>>
                        <?= htmlspecialchars($mod) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div data-list="<?= htmlspecialchars($initialUrl) ?>" id="role-list" class="ajax-list"></div>
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

    // Role module filter: same pattern as permissions_manage.php.
    (function () {
        var sel = document.getElementById('moduleFilterRoles');
        var container = document.getElementById('role-list');
        if (!sel || !container) return;
        var baseUrl = 'rbac/roleList';

        sel.addEventListener('change', function () {
            var mod = sel.value;
            var newUrl = mod === '' ? baseUrl : baseUrl + '?module=' + encodeURIComponent(mod);
            container.setAttribute('data-list', newUrl);
            if (typeof window.loadList === 'function') {
                window.loadList(newUrl, container.id);
            } else {
                var nav = new URL(window.location.href);
                if (mod === '') {
                    nav.searchParams.delete('module');
                } else {
                    nav.searchParams.set('module', mod);
                }
                window.location.href = nav.toString();
            }
        });
    })();
</script>
