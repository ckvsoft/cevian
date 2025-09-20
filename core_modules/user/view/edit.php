<fieldset>
    <legend>User: Edit</legend>
    <form action="<?php echo BASE_URI; ?>user/editSave/<?php echo $this->user[0]['user_id']; ?>" method="post" id="editForm">
        <label for="email">Email:</label>
        <input type="text" id="email" name="email" value="<?php echo $this->user[0]['email']; ?>" required><br />
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br />
        <label for="role">Role:</label>
        <select name="role">
            <option value="None" <?php if ($this->user[0]['role'] == 'None') echo 'selected'; ?>>None</option>
            <option value="admin" <?php if ($this->user[0]['role'] == 'admin') echo 'selected'; ?>>Admin</option>
            <option value="owner" <?php if ($this->user[0]['role'] == 'owner') echo 'selected'; ?>>Owner</option>
        </select>
        <br /><br />
        <input type="submit">
        <input type="button" onclick="javascript:window.location = '<?php echo BASE_URI; ?>user';" value="Cancel">
    </form>
</fieldset>
<script>
<?php echo $this->script; ?>
</script>