<div align="center">
    <fieldset>
        <form action="login/submit" method="post" id="loginForm" data-title="<?= _('Login') ?>" data-message="<?= _('Login successful') ?>" data-redirect='dashboard'>
            <div class="form-row">
                <label for="email"><?= _("Email:") ?></label>
                <input type="email" id="email" name="email" autocomplete="username">
            </div>
            <div class="form-row">
                <label for="password"><?= _("Password:") ?></label>
                <input type="password" id="password" name="password" autocomplete="current-password">
            </div>
            <div class="form-row">
                <label for="submit">&nbsp;</label>
                <input class="button small-action save" type="submit" value="<?= _("Login") ?>">
            </div>
        </form>
    </fieldset>
</div>
