<h2>Welcome to the Installer</h2>

<p>Please create the security file: <strong><?= $this->securityFile ?></strong></p>

<?php if (!empty($this->htaccessHint)): ?>
    <p style="color:orange; font-weight:bold"><?= $this->htaccessHint ?></p>
<?php endif; ?>

<?php if (!empty($this->nginxHint)): ?>
    <p style="color:orange; font-weight:bold"><?= $this->nginxHint ?></p>
<?php endif; ?>

<form action="installer/checkSecurity" method="post">
    <button class="button" type="submit">Check</button>
</form>
