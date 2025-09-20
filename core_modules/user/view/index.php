<fieldset>
    <legend>User: Add</legend>
    <form action="user/create" method="post" id="userForm" autocomplete="off">
        <label for="email">Email:</label>
        <input type="text" id="email" name="email" required autocomplete="off"><br />

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required autocomplete="new-password"><br />

        <label for="role">Role:</label>
        <select name="role">
            <option value="owner">Owner</option>
            <option value="admin">Admin</option>
        </select><br /><br />

        <input type="submit" value="Create User">
    </form>
</fieldset>
<hr />
<div id="userlist" class="ajax-list" data-form="userForm" data-url="user/userList"></div>
<?= $this->user_script; ?>

