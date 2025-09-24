<h2>Step 3: Admin User Setup</h2>

<?php if (!empty($this->error)): ?>
    <p style="color:red"><?= $this->error ?></p>
<?php endif; ?>

<form method="post" action="">
    <label>Username:<br>
        <input type="text" name="username" value="<?= $state['step3']['admin']['username'] ?? '' ?>" required>
    </label><br><br>

    <label>Email:<br>
        <input type="email" name="email" value="<?= $state['step3']['admin']['email'] ?? '' ?>" required>
    </label><br><br>

    <label>Password:<br>
        <input type="password" name="password" required>
    </label><br><br>
    <div class="button-row">
        <a href="checkEnvironment" class="button">← Back</a>
        <button class="button" type="submit">Next →</button>
    </div
</form>
