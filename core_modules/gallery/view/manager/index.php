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
 */
$baseUri = BASE_URI;
$message = $this->data['message'] ?? null;
?>

<h1><?= htmlspecialchars($this->title) ?></h1>

<div id="status" class="<?= $message ? 'success' : 'error' ?>" style="<?= $message ? 'display: block;' : '' ?>">
    <?= htmlspecialchars($message ?? '') ?>
</div>

<fieldset class="rescan-fieldset">
    <legend>Rescan Operations</legend>

    <div class="rescan-group">
        <div data-form="rescan-form" class="rescan-form-container">
            <form id="rescan-form"
                  method="POST"
                  action="<?= htmlspecialchars($baseUri . 'gallery/manager/rescan/3') ?>"
                  data-redirect="gallery/manager/index"
                  data-progress-id="3" data-progress-url="gallery/manager/progress/">

                <button type="submit"
                        id="progress-feedback-container-3"
                        data-original-text="[Album Folder Rescan]"
                        class="button small-action yellow"
                        onclick="return confirm('Are you sure? This will synchronize the Albums DB with the filesystem.')">

                    <span id="progress-text-3">
                        [Album Folder Rescan]
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
                        data-original-text="[All Media Rescan]"
                        class="button small-action blue"
                        onclick="return confirm('Are you sure? This will register ALL media files found in the filesystem into the media table. It might take a while!')">

                    <span id="progress-text-4">
                        [All Media Rescan]
                    </span>
                </button>
            </form>
        </div>
    </div>
</fieldset>

<fieldset style="margin-top: 30px;">
    <legend>Alben</legend>

    <div class="paginated">
        <table>
            <thead>
                <tr>
                    <th>Album Path Name</th>
                    <th>User Name</th>
                    <th>Role</th>
                    <th>Views</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->albums)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No albums found. Run Album Folder Rescan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($this->albums as $album): ?>
                        <tr>
                            <td>
                                <span class="mobile-label">Album Path Name:</span>
                                <?= htmlspecialchars($album['album_path']) ?>
                            </td>
                            <td>
                                <span class="mobile-label">User Name:</span>
                                <?= htmlspecialchars($album['owner_username'] ?? 'System/N/A') ?> (ID: <?= $album['owner_user_id'] ?? 'NULL' ?>)
                            </td>
                            <td>
                                <span class="mobile-label">Role:</span>
                                <?= $album['permissions_level'] ?>
                                (<?=
                                match ((int) $album['permissions_level']) {
                                    0 => 'Public',
                                    1 => 'User',
                                    2 => 'Admin',
                                    default => 'N/A'
                                }
                                ?>)
                            </td>
                            <td>
                                <span class="mobile-label">Views:</span>
                                <strong><?= number_format($album['total_media_views'] ?? 0, 0, ',', '.') ?></strong>
                            </td>

                            <td class="table-actions">

                                <a class="button small-action edit" href="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $album['album_id']) ?>">
                                    Media
                                </a>
                                <a class="button small-action edit" href="<?= htmlspecialchars($baseUri . 'gallery/manager/edit/' . $album['album_id']) ?>">
                                    Edit
                                </a>

                                <form id="reset-views-<?= $album['album_id'] ?>"
                                      method="POST"
                                      action="<?= htmlspecialchars($baseUri . 'gallery/manager/reset_views/' . $album['album_id']) ?>"
                                      data-redirect="gallery/manager/index"
                                      class="inline-form"
                                      style="display: inline-block;"> <button type="submit"
                                                                        class="button small-action delete"
                                                                        onclick="return confirm('Are you sure you want to reset the View Counter for Album ID <?= $album['album_id'] ?> to 0?')">
                                        Reset Views
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
