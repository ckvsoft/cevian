<a href="<?= htmlspecialchars($this->item['url']) ?>" class="media-item image-item" title="<?= htmlspecialchars($this->item['name']) ?>">
    <img src="<?= htmlspecialchars($this->item['thumburl']) ?>" alt="Image <?= htmlspecialchars($this->item['name']) ?>" loading="lazy">
    <span class="image-caption"><?= htmlspecialchars($this->item['name']) ?></span>
</a>
