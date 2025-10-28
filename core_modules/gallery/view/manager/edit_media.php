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
 * View for editing individual media item details.
 * Uses the framework grid layout (.form-group) for the form fields.
 * @var array $item Contains: 'id', 'album_id', 'title', 'description', 'file',
 * 'thumburl', 'album_path', etc.
 */
$baseUri = BASE_URI;
$item = $this->item;

$displayTitle = $item['title'] ?? $item['file'] ?? _('No Title Available');
?>

<fieldset style="margin-top: 30px;">
    <legend><?= _('Media Edit') ?></legend>

    <div data-form="mediaEditForm" id="mediaEditContainer" data-json="1">

        <div class="page-header">
            <h1><?= _('Edit Media Item:') ?> <?= htmlspecialchars($displayTitle); ?></h1>
        </div>

        <p class="back-link">
            <a href="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $item['album_id']) ?>">← <?= _('Back to Media Overview') ?></a>
        </p>

        <div class="media-details-block">
            <img src="<?= htmlspecialchars($item['thumburl']) ?>"
                 alt="<?= _('Thumbnail for') ?> <?= htmlspecialchars($item['file']) ?>"
                 class="media-preview-thumb-minimal">
            <p><?= _('File:') ?> <strong><?= htmlspecialchars($item['file']) ?></strong> (ID: <?= $item['id'] ?>)</p>
            <p><?= _('Album Path:') ?> <?= htmlspecialchars($item['album_path']) ?></p>
        </div>

        <div class="clear-both"></div>
        <hr style="margin: 20px 0; border-color: #555;">

        <form id="mediaEditForm" action="<?= htmlspecialchars($baseUri . 'gallery/manager/edit_media/' . $item['id']) ?>"
              method="POST" class="form-horizontal"
              data-redirect="<?= htmlspecialchars('gallery/manager/album_media/' . $item['album_id']) ?>"
              data-message="<?= _('Details for media "%s" updated successfully.') . ' ' . htmlspecialchars($item['file']) ?>">

            <input type="hidden" name="media_id" value="<?= $item['id'] ?>">

            <div class="form-group">
                <label for="media_name"><?= _('Display Name:') ?></label>
                <input type="text" id="media_name" name="title"
                       value="<?= htmlspecialchars($item['title'] ?? '') ?>"
                       maxlength="255" style="width: 100%; box-sizing: border-box;">
                <p class="form-hint-simple"><?= _('Displayed below the image (optional).') ?></p>
            </div>
            <div class="form-group">
                <label for="media_description"><?= _('Description:') ?></label>
                <textarea id="media_description" name="description" rows="6" maxlength="1024" style="width: 100%; box-sizing: border-box;"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                <p class="form-hint-simple"><?= _('Additional image description. Max 1024 characters.') ?></p>
            </div>
            <div class="form-actions-simple">
                <button type="submit" class="button small-action save">
                    <?= _('Save') ?>
                </button>
                <a class="button small-action cancel" href="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $item['album_id']) ?>">
                    <?= _('Cancel') ?>
                </a>
            </div>
        </form>
    </div>
</fieldset>