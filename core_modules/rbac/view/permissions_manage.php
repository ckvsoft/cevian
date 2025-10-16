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
$listUrl = BASE_URI . 'rbac/permissionList';
$addUrl = BASE_URI . 'rbac/editPermissionSave'; // A single save endpoint
?>
<fieldset>
    <div id="addPermissionContainer"
         class="ajax-form-container"
         data-form="addPermissionForm"
         data-url="<?= $listUrl ?>"
         data-json="1">

        <legend id="formTitle"><?= _('Add New Permission') ?></legend>

        <form action="<?= $addUrl ?>" method="post" id="addPermissionForm">
            <input type="hidden" name="id" id="permId" value="">

            <label for="permKey"><?= _('Permission Key') ?> (<?= _('e.g.') ?> 'user_create')</label>
            <input type="text" name="permKey" id="permKey" class="form-control" required><br />

            <label for="permName"><?= _('Display Name') ?> (<?= _('e.g.') ?> 'Create User')</label>
            <input type="text" name="permName" id="permName" class="form-control" required><br />

            <label for="permDescription"><?= _('Description') ?></label>
            <textarea style="width: 240px; height: 55px;" name="permDescription" id="permDescription" class="form-control"></textarea><br />

            <button type="submit" class="button small-action save" id="submitPermButton">
                <?= _('Add Permission') ?>
            </button>
            <button type="button" class="button small-action cancel" id="cancelPermButton" style="display:none;">
                <?= _('Cancel Edit') ?>
            </button>
        </form>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend><?= _('Existing Permissions') ?></legend>
    <div id="permissionListContainer"
         class="ajax-list"
         data-list="<?= $listUrl ?>">
        <p><?= _('Loading...') ?></p>
    </div>
</fieldset>