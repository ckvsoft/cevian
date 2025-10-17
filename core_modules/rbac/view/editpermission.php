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

$isEditMode = !empty($this->perm['id']);
$defaultRedirect = 'rbac/permissions';
?>
<fieldset style="margin-top: 30px;">
    <legend><?= _($isEditMode ? 'Edit Permission' : 'Create Permission') ?>: <?= htmlspecialchars($this->perm['permName'] ?? '') ?></legend>
    <div data-form="permissionForm" data-json="1">

        <form action="<?= BASE_URI ?>rbac/editPermissionSave" method="post" id="permissionForm" data-redirect="<?= $defaultRedirect ?>">
            <input type="hidden" name="perm_id" value="<?= $this->perm['id'] ?? '' ?>">

            <label for="permName"><?= _('Permission Name') ?>:</label>
            <input type="text" id="permName" name="permName" value="<?= htmlspecialchars($this->perm['permName'] ?? '') ?>" required><br />

            <label for="permKey"><?= _('Permission Key') ?>:</label>
            <input type="text" id="permKey" name="permKey" value="<?= htmlspecialchars($this->perm['permKey'] ?? '') ?>" required><br />

            <label for="permDescription"><?= _('Description') ?>:</label>
            <textarea style="width: 240px; height: 55px;" id="permDescription" name="permDescription"><?= htmlspecialchars($this->perm['permDescription'] ?? '') ?></textarea><br />

            <div style="margin-top:10px;">
                <button class="button small-action save" type="submit">
                    <?= _($isEditMode ? 'Save' : 'Create') ?>
                </button>

                <?php if ($isEditMode) { ?>
                    <button class="button small-action delete" type="button" onclick="deletePermission(<?= $this->perm['id'] ?>)">
                        <?= _('Delete') ?>
                    </button>
                <?php } ?>

                <input class="button small-action cancel"
                       type="button"
                       onclick="javascript:window.location = '<?= BASE_URI . $defaultRedirect ?>';"
                       value="<?= _('Cancel') ?>">

            </div>
        </form>
    </div>
</fieldset>

<script>
    /**
     * Handles the asynchronous deletion of a permission entry.
     * @param {number} id - The ID of the permission to delete.
     */
    function deletePermission(id) {
        // Confirmation dialog translated
        if (!confirm("<?= _('Really delete this permission?') ?>"))
            return;

        fetch("<?= BASE_URI ?>rbac/deletePermission/" + id)
                .then(r => r.json())
                .then(d => {
                    if (d.success)
                        // Redirect translated
                        location.href = "<?= BASE_URI . $defaultRedirect ?>";
                    else
                        alert(JSON.stringify(d.error || d));
                }).catch(e => alert(e));
    }
</script>