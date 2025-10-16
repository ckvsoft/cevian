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

// Initialize $album with default values if not set, ensuring 'title' is included.
$album = $this->album ?? ['id' => 'N/A', 'name' => 'Album Not Found', 'owner_user_id' => null, 'permissions_level' => 1, 'title' => ''];
// Initialize other data arrays.
$users = $this->users ?? [];
$permissionMap = $this->permissionMap ?? [];

// Sanitize the album ID for use in HTML attributes and links.
$albumId = htmlspecialchars($album['album_id']);
?>

<fieldset style="margin-top: 30px;">
    <legend><?= _('Edit') ?></legend>

    <div data-form="albumEditForm" id="albumEditContainer" data-json="1">

        <div class="page-header">
            <h1><?= _('Edit Album') ?>: <?= htmlspecialchars($album['title'] ?? 'none'); ?></h1>
        </div>

        <form id="albumEditForm" action="<?= BASE_URI ?>gallery/manager/edit/<?= $albumId; ?>" method="POST" class="form-horizontal"
              data-redirect="gallery/manager/index">

            <input type="hidden" name="album_id" value="<?= $albumId; ?>">

            <div class="form-group">
                <label style="white-space: nowrap; padding-right: 10px;" for="album_title"><?= _('Title') ?>:</label>
                <input type="text"
                       id="album_title"
                       name="title"
                       value="<?= htmlspecialchars($album['title'] ?? ''); ?>"
                       required
                       style="width: 100%; max-width: 400px;">
            </div>

            <div class="form-group">
                <label style="white-space: nowrap; padding-right: 10px;" for="owner_user_id"><?= _('Album Owner') ?>:</label>
                <select name="owner_user_id" id="owner_user_id">
                    <option value=""><?= _('-- Please select --') ?></option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['user_id']); ?>"
                                <?= ($user['user_id'] == $album['owner_user_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($user['username']); ?> (ID: <?= htmlspecialchars($user['user_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <div style="padding-left: 10px; padding-top: 5px;">
                    <input type="checkbox" id="apply_owner_to_subfolders" name="apply_owner_to_subfolders" value="1">
                    <label for="apply_owner_to_subfolders"><?= _('Apply Owner to all subfolders') ?></label>
                </div>
            </div>

            <div class="form-group">
                <label style="white-space: nowrap;" for="permissions_level"><?= _('Permission Level') ?>:</label>
                <select name="permissions_level" id="permissions_level">
                    <?php
                    // Loop through the permission map to populate the dropdown
                    foreach ($permissionMap as $level => $text):
                        $selected = ($level == $album['permissions_level']) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($level); ?>" <?= $selected; ?>>
                            <?= htmlspecialchars($level . ' - ' . $text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <div style="padding-left: 10px; padding-top: 5px;">
                    <input type="checkbox" id="apply_permissions_to_subfolders" name="apply_permissions_to_subfolders" value="1">
                    <label for="apply_permissions_to_subfolders"><?= _('Apply Permissions to all subfolders') ?></label>
                </div>
            </div>

            <div class="form-group button-group">
                <button type="submit" class="button small-action save"><?= _('Save Changes') ?></button>
                <a class="button small-action cancel" href="<?= BASE_URI ?>gallery/manager/index"><?= _('Back to Overview') ?></a>
            </div>

        </form>
    </div>
</fieldset>