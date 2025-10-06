<?php
/** @var string $title */
/** @var string $currentAlbum */
/** @var string $galleryHtml */
$isRootView = ($this->currentAlbum === 'ALL_ALBUMS');
$basePath = BASE_URI . 'gallery/index';
?>

<!-- Breadcrumb -->
<div class="breadcrumb-container">
    <?php if ($isRootView): ?>
        <h2><?= htmlspecialchars($this->title) ?></h2>
    <?php else: ?>
        <?php
        $segments = explode('/', $this->currentAlbum);
        $pathAccumulator = '';
        ?>
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="<?= htmlspecialchars($basePath) ?>">Home</a>
            <?php foreach ($segments as $index => $segment): ?>
                <span class="separator">/</span>
                <?php
                $pathAccumulator .= '/' . urlencode($segment);
                $isLast = ($index === array_key_last($segments));
                ?>
                <?php if ($isLast): ?>
                    <span class="current-album"><?= htmlspecialchars($segment) ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($basePath . $pathAccumulator) ?>">
                        <?= htmlspecialchars($segment) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>

<!-- Back link -->
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

<!-- Gallery grid -->
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
