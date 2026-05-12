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

/**
 * View: rbac/permissions_manage.php
 * Manages Permission Definitions (Add, Edit, List).
 * Uses AJAX framework for form submissions and list refreshing.
 */
// BASE_URI is defined and available from the BaseController class.
$modules      = (array)  ($this->modules      ?? []);
$activeModule = (string) ($this->activeModule ?? '');
$listUrl = BASE_URI . 'rbac/permissionList';
$addUrl = BASE_URI . 'rbac/editPermissionSave'; // A single save endpoint
// Pre-filter the AJAX list URL with the currently active module
// so the initial page load already shows the filtered view.
$listUrlWithFilter = $activeModule !== ''
        ? $listUrl . '?module=' . urlencode($activeModule)
        : $listUrl;
?>
<fieldset>
    <legend id="formTitle"><?= _('Add New Permission') ?></legend>

    <div id="addPermissionContainer"
         class="ajax-form-container"
         data-form="addPermissionForm"
         data-url="<?= $listUrl ?>"
         data-json="1"
         data-message="<?= _('Permission created successfully!') ?>">


        <form action="<?= $addUrl ?>" method="post" id="addPermissionForm">
            <input type="hidden" name="id" id="permId" value="">

            <label for="permKey"><?= _('Permission Key') ?>:</label>
            <input type="text" name="permKey" id="permKey" class="form-control" required placeholder=" <?= _('e.g.') ?> <?= _('user_create') ?>"><br />

            <label for="permName"><?= _('Display Name') ?>:</label>
            <input type="text" name="permName" id="permName" class="form-control" required placeholder="<?= _('e.g.') ?> <?= _('Create User') ?>"><br />

            <label for="permDescription"><?= _('Description') ?>:</label>
            <textarea style="width: 240px; height: 55px;" name="permDescription" id="permDescription" class="form-control"></textarea><br />

            <br />

            <input class="button small-action save" type="submit" value="<?= _('Add Permission') ?>">
            <input class="button small-action cancel" type="reset" value="<?= _('Clear') ?>">
        </form>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend><?= _('Existing Permissions') ?></legend>

    <?php if (!empty($modules)): ?>
        <div class="rbac-module-filter" style="margin-bottom: 12px;">
            <label for="moduleFilter"><?= _('Module') ?>:</label>
            <select id="moduleFilter" class="form-control" style="display:inline-block; width:auto;">
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

    <div id="permissionListContainer"
         class="ajax-list"
         data-list="<?= htmlspecialchars($listUrlWithFilter) ?>">
        <p><?= _('Loading...') ?></p>
    </div>
</fieldset>

<script>
(function () {
    // Module filter: change the data-list URL on the container and
    // re-trigger the loadList() flow. The framework's loadList(url, id)
    // is the canonical reloader; if it isn't exposed globally,
    // fall back to a full page navigation with ?module= so the
    // server renders the filtered initial state.
    var sel = document.getElementById('moduleFilter');
    var container = document.getElementById('permissionListContainer');
    if (!sel || !container) return;
    var baseUrl = '<?= addslashes($listUrl) ?>';

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