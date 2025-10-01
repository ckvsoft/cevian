<h1><?= $this->title ?></h1>

<fieldset>
    <legend style="font-weight: bold;">Create New Role</legend>

    <div data-form="roleForm" data-url="rbac/roleList" data-json="1">
        <form id="roleForm" action="<?= BASE_URI ?>rbac/saveRole" method="post" autocomplete="off">
            <div style="margin-bottom: 12px;">
                <label for="roleName" style="display:block;font-weight:bold;margin-bottom:4px;">Role Name</label>
                <input type="text" id="roleName" name="roleName" required style="width:100%;padding:8px;">
            </div>

            <div style="margin-bottom:12px;">
                <label for="parentRole" style="display:block;font-weight:bold;margin-bottom:4px;">Parent Role (optional)</label>
                <select id="parentRole" name="parentId" style="width:100%;padding:8px;">
                    <option value="">-- None --</option>
                    <?php foreach (($this->roles ?? []) as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= str_repeat('— ', $role['depth']) . htmlspecialchars($role['roleName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" style="width:100%;padding:10px;font-weight:bold;background:#007bff;color:#fff;border:none;border-radius:4px;">Create</button>
        </form>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend>Existing Roles</legend>
    <div data-list="rbac/roleList" id="role-list" class="ajax-list"></div>
</fieldset>>
<script>
    function deleteRole(id) {
        if (!confirm("Really delete this role?"))
            return;
        fetch("<?= BASE_URI ?>rbac/deleteRole/" + id)
                .then(r => r.json())
                .then(d => {
                    if (d.success)
                        location.href = "<?= BASE_URI ?>rbac";
                    else
                        alert(JSON.stringify(d.error || d));
                }).catch(e => alert(e));
    }
</script>
