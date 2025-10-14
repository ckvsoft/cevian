<?php
/** * @var array $album ...
 * @var array $media ...
 */
$baseUri = BASE_URI;
$album = $this->album;
$media = $this->media;
?>

<h1><?= htmlspecialchars($this->title) ?></h1>

<p class="back-link">
    <a href="<?= htmlspecialchars($baseUri . 'gallery/manager/index') ?>">← Back to Album Management</a>
</p>

<h2>Album: <?= htmlspecialchars($album['album_path']) ?> (ID: <?= $album['album_id'] ?>)</h2>
<p>Owner: <?= htmlspecialchars($album['owner_username'] ?? 'N/A') ?> (ID: <?= $album['owner_user_id'] ?? '-' ?>)</p>
<p>Total Media Items: <?= count($media) ?></p>

<hr>

<h3>Media Items Overview</h3>

<?php if (empty($media)): ?>
    <div class="gallery-message-box">
        No media files found in this album. Run the [All Media Rescan] if files are missing.
    </div>
<?php else: ?>

    <div class="image-grid">

        <?php
        foreach ($media as $item):
            $mediaLink = $item['url'];
            $thumbLink = $item['thumburl'];

            $editLink = $baseUri . 'gallery/manager/edit_media/' . $item['id'] . '?album_id=' . $album['album_id'];
            $deleteLink = $baseUri . 'gallery/manager/delete_media/' . $item['id'];
            ?>

            <div class="media-item media-item-manager-overlay">
                <a href="<?= htmlspecialchars($mediaLink) ?>" target="_blank" class="media-thumb-link">
                    <img src="<?= htmlspecialchars($thumbLink) ?>"
                         alt="<?= htmlspecialchars($item['file']) ?>">
                </a>

                <div class="media-item-content">
                    <p class="media-filename">
                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                    </p>

                    <p class="media-stats">
                        Views: <?= number_format($item['views'] ?? 0) ?><br>
                        Last View: <?= htmlspecialchars($item['last_view'] ?? '-') ?>
                    </p>

                    <div class="media-actions">
                        <a class="button small-action edit" href="<?= htmlspecialchars($editLink) ?>">
                            Edit Details
                        </a>
                        <a class="button small-action delete"
                           href="<?= htmlspecialchars($deleteLink) ?>"
                           onclick="return confirm('Delete <?= htmlspecialchars($item['file']) ?>?')">
                            Delete
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

<?php endif; ?>