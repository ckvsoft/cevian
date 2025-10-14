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

$album = $this->album ?? ['id' => 'N/A', 'name' => 'Album Not Found', 'owner_user_id' => null, 'permissions_level' => 1];
$users = $this->users ?? [];

$albumId = htmlspecialchars($album['album_id']);
?>

<fieldset style="margin-top: 30px;">
    <legend>Edit</legend>

    <div data-form="albumEditForm" id="albumEditContainer" data-json="1">

        <div class="page-header">
            <h1>Album bearbeiten: <?= htmlspecialchars($album['title'] ?? 'none'); ?></h1>
        </div>

        <form id="albumEditForm" action="<?= BASE_URI ?>gallery/manager/edit/<?= $albumId; ?>" method="POST" class="form-horizontal"
              data-redirect="gallery/manager/index">

            <input type="hidden" name="album_id" value="<?= $albumId; ?>">

            <div class="form-group">
                <label style="white-space: nowrap; padding-right: 10px;" for="owner_user_id">Album-Eigentümer:</label>
                <select name="owner_user_id" id="owner_user_id">
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['user_id']); ?>"
                                <?= ($user['user_id'] == $album['owner_user_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($user['username']); ?> (ID: <?= htmlspecialchars($user['user_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label style="white-space: nowrap;" for="permissions_level">Berechtigungsstufe:</label>
                <select name="permissions_level" id="permissions_level">
                    <option value="1" <?= (1 == $album['permissions_level']) ? 'selected' : ''; ?>>1 - Privat (Nur Eigentümer)</option>
                    <option value="2" <?= (2 == $album['permissions_level']) ? 'selected' : ''; ?>>2 - Geschützt (Mitglieder)</option>
                    <option value="0" <?= (0 == $album['permissions_level']) ? 'selected' : ''; ?>>0 - Öffentlich (Jeder)</option>
                </select>
            </div>
            <button type="submit" class="button small-action save">Änderungen speichern</button>
            <a class="button small-action cancel" href="<?= BASE_URI ?>gallery/manager/index">Zurück zur Übersicht</a>
        </form>
    </div>
</fieldset>>