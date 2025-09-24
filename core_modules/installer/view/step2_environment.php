<h2>Step 2: Environment Check</h2>

<h3>Writable Paths</h3>
<ul>
    <?php foreach ($this->checks['writable'] as $path => $ok): ?>
        <li><?= $path ?> : <?= $ok ? '✅' : '❌' ?></li>
    <?php endforeach; ?>
</ul>

<h3>Readable Paths</h3>
<ul>
    <?php foreach ($this->checks['readable'] as $path => $ok): ?>
        <li><?= $path ?> : <?= $ok ? '✅' : '❌' ?></li>
    <?php endforeach; ?>
</ul>

<a href="checkSecurity" class="button">← Back</a>
<a class="button" href="<?= !in_array(false, $this->checks['writable']) && !in_array(false, $this->checks['readable']) ? 'setupAdmin' : '#' ?>" class="<?= !in_array(false, $this->checks['writable']) && !in_array(false, $this->checks['readable']) ? '' : 'disabled' ?>">Next →</a>
