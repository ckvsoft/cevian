<div class="media-item video-item" title="<?= htmlspecialchars($this->item['name']) ?>">
    <video controls preload="none" poster="<?= htmlspecialchars($this->item['thumburl']) ?>" width="100%" height="auto">
        <source src="<?= htmlspecialchars($this->item['url']) ?>" type="video/mp4">
        Your browser does not support the video tag.
    </video>
    <p class="video-caption" style="margin-top: 0;">
        <?= htmlspecialchars($this->item['name']) ?>
    </p>
</div>