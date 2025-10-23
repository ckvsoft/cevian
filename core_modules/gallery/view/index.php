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

$isRootView = ($this->currentAlbum === 'ALL_ALBUMS');
$basePath = BASE_URI . 'gallery/index';
?>

<div class="breadcrumb-container">
    <?php if ($isRootView): ?>
        <h2><?= htmlspecialchars($this->title) ?></h2>
    <?php else: ?>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="<?= htmlspecialchars($basePath) ?>">Home</a>

            <?php foreach ($this->breadcrumbData as $index => $item): // Iteration über die vorgefertigten Titel-Daten  ?>
                <span class="separator">/</span>
                <?php
                $segmentTitle = $item['title'];
                $pathAccumulator = $item['path'];

                $isLast = ($index === array_key_last($this->breadcrumbData));
                ?>

                <?php if ($isLast): ?>
                    <span class="current-album"><?= htmlspecialchars($segmentTitle) ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($basePath . '/' . $pathAccumulator) ?>">
                        <?= htmlspecialchars($segmentTitle) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>

<?php if (!$isRootView): ?>
    <?php
    $segments = explode('/', $this->currentAlbum);
    array_pop($segments);
    $parentPath = implode('/', $segments);
    $backUrl = empty($parentPath) ? $basePath : $basePath . '/' . implode('/', array_map('urlencode', explode('/', $parentPath)));
    ?>
    <p class="back-link">
        <a href="<?= htmlspecialchars($backUrl) ?>">&larr; Back</a>
    </p>
<?php endif; ?>

<div id="gallery-list-container" class="paginated" data-per-page="9">
    <div class="image-grid">
        <?= $this->galleryHtml ?>
    </div>
</div>

<script>
    (function () {
        var $gallery = new SimpleLightbox('.paginated a:not(.album-item)', {overlayOpacity: 0.7});
    })();
</script>