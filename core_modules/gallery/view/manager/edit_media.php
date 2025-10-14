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
 * Nutzt das Framework-Grid-Layout (.form-group) für die Formularfelder.
 * @var array $item Contains: 'id', 'album_id', 'name', 'description', 'file',
 * 'thumburl', 'album_path', etc.
 */
$baseUri = BASE_URI;
$item = $this->item;
?>

<fieldset style="margin-top: 30px;">
    <legend>Media Edit</legend>

    <div data-form="mediaEditForm" id="mediaEditContainer" data-json="1">

        <div class="page-header">
            <h1>Medium bearbeiten: <?= htmlspecialchars($item['name'] ?? 'none'); ?></h1>
        </div>

        <p class="back-link">
            <a href="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $item['album_id']) ?>">← Zurück zur Medien-Übersicht</a>
        </p>

        <div class="media-details-block">
            <img src="<?= htmlspecialchars($item['thumburl']) ?>"
                 alt="Thumbnail for <?= htmlspecialchars($item['file']) ?>"
                 class="media-preview-thumb-minimal">
            <p>Datei: <strong><?= htmlspecialchars($item['file']) ?></strong> (ID: <?= $item['id'] ?>)</p>
            <p>Album-Pfad: <?= htmlspecialchars($item['album_path']) ?></p>
        </div>

        <div class="clear-both"></div>
        <hr style="margin: 20px 0; border-color: #555;">

        <form id="mediaEditForm" action="<?= htmlspecialchars($baseUri . 'gallery/manager/edit_media/' . $item['id']) ?>"
              method="POST" class="form-horizontal"
              data-redirect="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $item['album_id']) ?>">

            <input type="hidden" name="media_id" value="<?= $item['id'] ?>">

            <div class="form-group">
                <label for="media_name">Anzeigename / Titel:</label>
                <input type="text" id="media_name" name="name"
                       value="<?= htmlspecialchars($item['name'] ?? '') ?>"
                       maxlength="255">
            </div>
            <p class="form-hint-simple">Wird unter dem Bild angezeigt (optional).</p>

            <div class="form-group">
                <label for="media_description">Beschreibung / Caption:</label>
                <textarea id="media_description" name="description" rows="5"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
            </div>
            <p class="form-hint-simple">Zusätzliche Bildbeschreibung. Max 1024 Zeichen.</p>


            <div class="form-actions-simple">
                <button type="submit" class="button small-action save">
                    Save
                </button>
                <a class="button small-action cancel" href="<?= htmlspecialchars($baseUri . 'gallery/manager/album_media/' . $item['album_id']) ?>">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</fieldset>
