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

/*
 * Gallery Manager Index View
 * @var array $albums
 * @var string $message Status message from controller
 * @var array $permissionMap Übersetzte Zuordnung der Berechtigungsstufen
 */
$baseUri = BASE_URI;
$message = $this->data['message'] ?? null;
// Annahme: $this->albums und $this->permissionMap werden vom Controller übergeben
$albums = $this->albums ?? [];
$permissionMap = $this->permissionMap ?? [];
?>

<h1><?= htmlspecialchars($this->title) ?></h1>

<div id="status" class="<?= $message ? 'success' : 'error' ?>" style="<?= $message ? 'display: block;' : '' ?>">
    <?= htmlspecialchars($message ?? '') ?>
</div>

<fieldset class="rescan-fieldset">
    <legend><?= _('Rescan Operations') ?></legend>

    <div class="rescan-group">
        <div data-form="rescan-form" class="rescan-form-container">
            <form id="rescan-form"
                  method="POST"
                  action="<?= htmlspecialchars($baseUri . 'gallery/manager/rescan/3') ?>"
                  data-redirect="gallery/manager/index"
                  data-progress-id="3" data-progress-url="gallery/manager/progress/">

                <button type="submit"
                        id="progress-feedback-container-3"
                        data-original-text="<?= _('[Album Folder Rescan]') ?>"
                        class="button small-action yellow"
                        onclick="return confirm('<?= _('Are you sure? This will synchronize the Albums DB with the filesystem.') ?>')">

                    <span id="progress-text-3">
                        <?= _('[Album Folder Rescan]') ?>
                    </span>
                </button>
            </form>
        </div>

        <div data-form="rescan-media-form" class="rescan-form-container">
            <form id="rescan-media-form"
                  method="POST"
                  action="<?= htmlspecialchars($baseUri . 'gallery/manager/rescan_media/4') ?>"
                  data-redirect="gallery/manager/index"
                  data-progress-id="4" data-progress-url="gallery/manager/progress/">

                <button type="submit"
                        id="progress-feedback-container-4"
                        data-original-text="<?= _('[All Media Rescan]') ?>"
                        class="button small-action blue"
                        onclick="return confirm('<?= _('Are you sure? This will register ALL media files found in the filesystem into the media table. It might take a while!') ?>')">

                    <span id="progress-text-4">
                        <?= _('[All Media Rescan]') ?>
                    </span>
                </button>
            </form>
        </div>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend><?= _('Albums') ?></legend>

    <div class="paginated">
        <table>
            <thead>
                <tr>
                    <th><?= _('Album Path Name') ?></th>
                    <th><?= _('Title') ?></th>
                    <th><?= _('User Name') ?></th>
                    <th><?= _('Role') ?></th>
                    <th><?= _('Views') ?></th>
                    <th><?= _('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($albums)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;"><?= _('No albums found. Run Album Folder Rescan.') ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($albums as $album): ?>
                        <tr>
                            <td>
                                <span class="mobile-label"><?= _('Album Path') ?>:</span>
                                <?= htmlspecialchars($album['album_path'] === '' ? '/' : $album['album_path']) ?>
                            </td>
                            <td>
                                <span class="mobile-label"><?= _('Title') ?>:</span>
                                <?= htmlspecialchars($album['title'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <span class="mobile-label"><?= _('User Name') ?>:</span>
                                <?= htmlspecialchars($album['owner_username'] ?? _('System/N/A')) ?> (ID: <?= $album['owner_user_id'] ?? 'NULL' ?>)
                            </td>
                            <td>
                                <span class="mobile-label"><?= _('Role') ?>:</span>
                                <?= $album['permissions_level'] ?>
                                (<?= htmlspecialchars($permissionMap[(int) $album['permissions_level']] ?? 'N/A') ?>)
                            </td>
                            <td>
                                <span class="mobile-label"><?= _('Views') ?>:</span>
                                <strong><?= number_format($album['total_media_views'] ?? 0, 0, ',', '.') ?></strong>
                            </td>

                            <td class="table-actions">

                                <a class="button small-action edit" href="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $album['album_id']) ?>">
                                    <?= _('Media') ?>
                                </a>
                                <a class="button small-action edit" href="<?= htmlspecialchars($baseUri . 'gallery/manager/edit/' . $album['album_id']) ?>">
                                    <?= _('Edit') ?>
                                </a>

                                <form id="reset-views-<?= $album['album_id'] ?>"
                                      method="POST"
                                      action="<?= htmlspecialchars($baseUri . 'gallery/manager/reset_views/' . $album['album_id']) ?>"
                                      data-redirect="gallery/manager/index"
                                      class="inline-form"
                                      style="display: inline-block;">
                                    <button type="submit"
                                            class="button small-action delete"
                                            onclick="return confirm('<?= sprintf(_('Are you sure you want to reset the View Counter for Album ID %s to 0?'), $album['album_id']) ?>')">
                                                <?= _('Reset Views') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</fieldset>
