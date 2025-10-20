<?php
$isRootView = ($this->currentAlbum === 'ALL_ALBUMS');
$basePath = BASE_URI . 'gallery/index';
?>

<div class="breadcrumb-container">
    <?php if ($isRootView): ?>
        <h2><?= htmlspecialchars($this->title) ?></h2>
    <?php else: ?>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="<?= htmlspecialchars($basePath) ?>">Home</a>

            <?php foreach ($this->breadcrumbData as $index => $item): // Iteration über die vorgefertigten Titel-Daten ?>
                <span class="separator">/</span>
                <?php
                $segmentTitle = $item['title'];
                $pathAccumulator = $item['path'];

                $isLast = ($index === array_key_last($this->breadcrumbData));
                ?>

                <?php if ($isLast): ?>
                    <span class="current-album"><?= htmlspecialchars($segmentTitle) ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($basePath . '/' . urlencode($pathAccumulator)) ?>">
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