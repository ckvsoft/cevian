<fieldset>
    <legend><?php echo _('Menuentry') . ': ' . _('Edit'); ?></legend>
    <div data-form="editForm">
        <form action="<?php echo BASE_URI; ?>menu/editSave/<?php echo $this->menuList[0]['id']; ?>" method="post" id="editForm" data-redirect="menu">

            <label for="id">ID:</label>
            <input type="text" id="id" value="<?php echo $this->menuList[0]['id']; ?>" readonly><br />

            <label for="label"><?php echo _('Label'); ?>:</label>
            <input type="text" id="label" name="label" value="<?php echo $this->menuList[0]['label']; ?>" required><br />

            <label for="link"><?php echo _('Link'); ?>:</label>
            <input type="text" id="link" name="link" value="<?php echo $this->menuList[0]['link']; ?>" required><br />

            <label for="parent"><?php echo _('Parent'); ?>:</label>
            <input type="text" id="parent" name="parent" value="<?php echo $this->menuList[0]['parent']; ?>"><br />

            <label for="sort"><?php echo _('Sort'); ?>:</label>
            <input type="text" id="sort" name="sort" value="<?php echo $this->menuList[0]['sort']; ?>"><br />

            <div id="right">
                <label for="role"><?php echo _('Role'); ?>:</label>
                <select name="role">
                    <option value="None" <?php if ($this->menuList[0]['role'] == 'None') echo 'selected'; ?>><?php echo _('None'); ?></option>
                    <option value="admin" <?php if ($this->menuList[0]['role'] == 'admin') echo 'selected'; ?>><?php echo _('Admin'); ?></option>
                    <option value="owner" <?php if ($this->menuList[0]['role'] == 'owner') echo 'selected'; ?>><?php echo _('Owner'); ?></option>
                </select><br /><br />
            </div><br />

            <label for="is_public"><?php echo _('Public'); ?>:</label>
            <input type="hidden" name="is_public" value="-1">
            <input class="checkbox" type="checkbox" name="is_public" value="1" <?php if ($this->menuList[0]['is_public'] == '1') echo 'checked'; ?>><br />

            <br /><br />

            <input class="button small-action save" type="submit" value="<?php echo _('Save'); ?>">
            <input class="button small-action cancel" type="button" onclick="javascript:window.location = '<?php echo BASE_URI; ?>menu';" value="<?php echo _('Cancel'); ?>">
        </form>
    </div>
</fieldset>