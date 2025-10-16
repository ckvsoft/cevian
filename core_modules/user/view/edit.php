<fieldset>
    <legend><?= _('User: Edit') ?></legend>
    <div data-form="editForm">
        <?php
        // The user data being edited
        $user = $this->user[0];
        // Flag passed from the controller about the CURRENTLY LOGGED IN user
        $is_admin = $this->isAdmin ?? false;

        // Set redirect based on who is editing: Admin goes to list, User goes to dashboard/profile
        $redirect_url = $is_admin ? 'user' : 'dashboard';
        ?>
        <form action="<?php echo BASE_URI; ?>user/editSave/<?php echo htmlspecialchars($user['user_id']); ?>" method="post" id="editForm" data-redirect="<?php echo $redirect_url; ?>">

            <label for="user_id">ID:</label>
            <input type="text" id="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>" readonly><br />

            <label for="username"><?= _('Name') ?>:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required><br />

            <label for="email"><?= _('Email') ?>:</label>
            <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required><br />

            <label for="password"><?= _('Password') ?>:</label>
            <input type="password" id="password" name="password" placeholder="<?= _('Leave empty to keep current password') ?>"><br />

            <label for="password_confirm"><?= _('Confirm Password') ?>:</label>
            <input type="password" id="password_confirm" name="password_confirm" placeholder="<?= _('Repeat password if changing') ?>"><br />

            <?php if ($is_admin): ?>
                <label for="role"><?= _('Role') ?>:</label>
                <select name="role">
                    <option value="None" <?php if ($user['role'] == 'None') echo 'selected'; ?>><?= _('None') ?></option>
                    <option value="admin" <?php if ($user['role'] == 'admin') echo 'selected'; ?>><?= _('Admin') ?></option>
                    <option value="owner" <?php if ($user['role'] == 'owner') echo 'selected'; ?>><?= _('Owner') ?></option>
                </select>
                <br /><br />
            <?php endif; ?>

            <input class="button small-action save" type="submit" value="<?= _('Save') ?>">

            <input class="button small-action cancel" type="button"
                   onclick="javascript:window.location = '<?php echo BASE_URI . $redirect_url; ?>';"
                   value="<?= _('Cancel') ?>">
        </form>
    </div>
</fieldset>