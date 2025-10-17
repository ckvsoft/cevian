<fieldset>
    <legend>User: Add</legend>
    <div id="addUser" class="ajax-form-container" data-form="userForm" data-url="user/userList">
        <form action="user/create" method="post" id="userForm" autocomplete="off">
            <label for="username">Name:</label>
            <input type="text" id="username" name="username" required autocomplete="off"><br />

            <label for="email">Email:</label>
            <input type="text" id="email" name="email" required autocomplete="off"><br />

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required autocomplete="new-password"><br />

            <label for="role">Role:</label>
            <select name="role">
                <option value="owner">Owner</option>
                <option value="admin">Admin</option>
            </select>

            <br /><br />

            <input class="button small-action save" type="submit" value="Create User">
            <input class="button small-action cancel" type="reset" value="<?= _('Clear') ?>">

        </form>
    </div>
</fieldset>
<fieldset style="margin-top: 30px;">
    <legend>Existing User</legend>
    <div id="userlist" class="ajax-list" data-list="user/userList"></div>
</fieldset>
