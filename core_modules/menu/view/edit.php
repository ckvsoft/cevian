<fieldset>
    <legend>Menuentry: Edit</legend>
    <form action="<?php echo BASE_URI; ?>menu/editSave/<?php echo $this->menuList[0]['id']; ?>" method="post" id="editForm">
        <label for="label">Label:</label>
        <input type="text" id="label" name="label" value="<?php echo $this->menuList[0]['label']; ?>" required><br />
        <label for="link">Link:</label>
        <input type="text" id="link" name="link" value="<?php echo $this->menuList[0]['link']; ?>" required><br />
        <label for="parent">Parent:</label>
        <input type="text" id="parent" name="parent" value="<?php echo $this->menuList[0]['parent']; ?>"><br />
        <label for="sort">Sort:</label>
        <input type="text" id="sort" name="sort" value="<?php echo $this->menuList[0]['sort']; ?>"><br />
        <div id="right">
            <label for="role">Role:</label>
            <select name="role">
                <option value="None" <?php if ($this->menuList[0]['role'] == 'None') echo 'selected'; ?>>None</option>
                <option value="admin" <?php if ($this->menuList[0]['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                <option value="owner" <?php if ($this->menuList[0]['role'] == 'owner') echo 'selected'; ?>>Owner</option>
            </select><br /><br />
        </div><br />
        <label for="is_public">Public:</label>
        <input type="hidden" name="is_public" value="-1">
        <input class="checkbox" type="checkbox" name="is_public" value="1" <?php if ($this->menuList[0]['is_public'] == '1') echo 'checked'; ?>><br />
        <br /><br />
        <input type="submit">
        <input type="button" onclick="javascript:window.location = '<?php echo BASE_URI; ?>menu';" value="Cancel">
    </form>
</fieldset>
<?php echo $this->script; ?>
