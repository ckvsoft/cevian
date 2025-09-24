<h1>Step 1: Security Check</h1>

<?php if (!$this->ok): ?>
    <p>Security file missing ❌</p>
    <p>Bitte erstelle die Security-Datei im Root-Verzeichnis: <strong><?= $this->securityFile ?></strong></p>
    <form method="post" action="checkSecurity">
        <button class="button" type="submit" name="check" value="1">Check again</button>
    </form>
<?php else: ?>
    <p>Security file found ✅</p>
    <a href="checkEnvironment" class="button">Next →</a>
<?php endif; ?>
