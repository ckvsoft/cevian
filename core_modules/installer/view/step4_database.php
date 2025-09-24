<h2>Step 4: Database Setup</h2>

<?php if (!empty($this->error)): ?>
    <p style="color:red"><?= $this->error ?></p>
<?php endif; ?>

<form method="post" action="">
    <label>DB Host:<br>
        <input type="text" name="db_host" value="localhost" required>
    </label><br><br>

    <label>DB Name:<br>
        <input type="text" name="db_name" required>
    </label><br><br>

    <label>DB User:<br>
        <input type="text" name="db_user" required>
    </label><br><br>

    <label>DB Password:<br>
        <input type="password" name="db_pass">
    </label><br><br>

    <div class="button-row">
        <a href="setupAdmin" class="button">← Back</a>
        <button class="button" type="submit">Install Database →</button>
    </div>
</form>
