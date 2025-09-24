<h2>Installation abgeschlossen ✅</h2>

<?php if (!empty($undeletedFiles)): ?>
    <p>⚠️ Die folgenden Dateien konnten nicht automatisch gelöscht werden. Bitte manuell entfernen:</p>
    <ul>
        <?php foreach ($undeletedFiles as $file): ?>
            <li><?= htmlspecialchars($file) ?></li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>✅ Alle temporären Dateien erfolgreich gelöscht.</p>
<?php endif; ?>

<p><a href="<?= htmlspecialchars(BASE_URI) ?>" class="button">Zur Startseite der Anwendung</a></p>
