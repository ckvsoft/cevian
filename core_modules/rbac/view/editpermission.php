<?php
/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
?>
<fieldset style="margin-top: 30px;">
    <div data-form="permissionForm" data-json="1">
        <legend>Edit Permission: <?= htmlspecialchars($this->perm['permName']); ?></legend>
        <form action="<?= BASE_URI ?>rbac/editPermissionSave" method="post" id="permissionForm" data-redirect="rbac/permissions">
            <input type="hidden" name="perm_id" value="<?= $this->perm['id'] ?? '' ?>">

            <label for="permName">Permission Name:</label>
            <input type="text" id="permName" name="permName" value="<?= htmlspecialchars($this->perm['permName'] ?? '') ?>" required><br />

            <label for="permKey">Permission Key:</label>
            <input type="text" id="permKey" name="permKey" value="<?= htmlspecialchars($this->perm['permKey'] ?? '') ?>" required><br />

            <label for="permDescription">Description:</label>
            <textarea style="width: 240px; height: 55px;" id="permDescription" name="permDescription"><?= htmlspecialchars($this->perm['permDescription'] ?? '') ?></textarea><br />

            <div style="margin-top:10px;">
                <button type="submit"><?= empty($this->perm['id']) ? 'Create' : 'Save' ?></button>
                <?php if (!empty($this->perm['id'])) { ?>
                    <button class="button small-action ajax-delete" type="button" onclick="deletePermission(<?= $this->perm['id'] ?>)">Delete</button>
                <?php } ?>
            </div>
        </form>
    </div>
</fieldset>

<script>
    function deletePermission(id) {
        if (!confirm("Really delete this permission?"))
            return;
        fetch("<?= BASE_URI ?>rbac/deletePermission/" + id)
                .then(r => r.json())
                .then(d => {
                    if (d.success)
                        location.href = "<?= BASE_URI ?>rbac/permissions";
                    else
                        alert(JSON.stringify(d.error || d));
                }).catch(e => alert(e));
    }
</script>
