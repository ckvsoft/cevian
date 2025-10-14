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
// BASE_URI ist in der BaseController-Klasse definiert und verfügbar.
$listUrl = BASE_URI . 'rbac/permissionList';
$addUrl = BASE_URI . 'rbac/editPermissionSave'; // Ein einziger Save-Endpunkt
?>
<fieldset>
    <div id="addPermissionContainer"
         class="ajax-form-container"
         data-form="addPermissionForm"
         data-url="<?= $listUrl ?>"
         data-json="1">

        <legend id="formTitle">Add New Permission</legend>

        <form action="<?= $addUrl ?>" method="post" id="addPermissionForm">
            <input type="hidden" name="id" id="permId" value="">

            <label for="permKey">Permission Key (z.B. 'user_create')</label>
            <input type="text" name="permKey" id="permKey" class="form-control" required><br />

            <label for="permName">Display Name (z.B. 'Create User')</label>
            <input type="text" name="permName" id="permName" class="form-control" required><br />

            <label for="permDescription">Description</label>
            <textarea style="width: 240px; height: 55px;" name="permDescription" id="permDescription" class="form-control"></textarea><br />

            <button type="submit" class="button small-action save" id="submitPermButton">
                <i class="fas fa-plus"></i> Add Permission
            </button>
            <button type="button" class="button small-action cancel" id="cancelPermButton" style="display:none;">
                Cancel Edit
            </button>
        </form>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend>Existing Permissions</legend>
    <div id="permissionListContainer"
         class="ajax-list"
         data-list="<?= $listUrl ?>">
        <p>Loading...</p>
    </div>
</fieldset>
