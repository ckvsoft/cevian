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

$item = $this->item;
$titleText = $item['title'] ?? $item['name'];
$descriptionText = $item['description'] ?? '';
$mediaId = $item['id'] ?? null;

$isAdmin = $this->isAdmin ?? null;
$managerBasePath = BASE_URI . 'gallery/manager/rotate_media/';

$captionContent = $titleText;

if (!empty($descriptionText)) {
    $captionContent = sprintf('%s — %s', $titleText, $descriptionText);
}
?>

<div class="gallery-item-wrapper">

    <?php if ($isAdmin && $mediaId !== null): ?>
        <div class="media-rotate-actions"
             data-media-id="<?= $mediaId ?>"
             data-rotation-url="<?= htmlspecialchars($managerBasePath . $mediaId) ?>">

            <button type="button" class="button small-action rotate-action rotate-left edit"
                    data-degrees="90"
                    title="<?= _('Rotate Left (90°)') ?>">
                <span class="rotate-icon" aria-hidden="true">↺</span>
            </button>

            <button type="button" class="button small-action rotate-action rotate-right edit"
                    data-degrees="-90"
                    title="<?= _('Rotate Right (90°)') ?>">
                <span class="rotate-icon" aria-hidden="true">↻</span>
            </button>
        </div>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($item['url']) ?>"
       class="media-item image-item"
       data-media-id="<?= $mediaId ?>">

        <img title="<?= htmlspecialchars($captionContent) ?>"
             src="<?= htmlspecialchars($item['thumburl']) ?>"
             alt="<?= _('Image') ?> <?= htmlspecialchars($titleText) ?>"
             loading="lazy">

        <span class="image-caption"><?= htmlspecialchars($titleText) ?></span>
    </a>
</div>