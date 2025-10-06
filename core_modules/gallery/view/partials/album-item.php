<a href="<?= htmlspecialchars($this->item['url']) ?>" class="media-item album-item" title="<?= htmlspecialchars($this->item['name']) ?>">
    <img src="<?= htmlspecialchars($this->item['thumbnailUrl']) ?>" alt="Album: <?= htmlspecialchars($this->item['name']) ?>" loading="lazy">
    <span class="album-name"><?= htmlspecialchars($this->item['name']) ?></span>
</a>
