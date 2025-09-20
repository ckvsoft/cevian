<fieldset>
    <legend>Menuentry: Add</legend>
    <form action="menu/create" method="post" id="menuForm">
        <label for="label">Label:</label>
        <input type="text" id="label" name="label" value="" required><br />
        <label for="link">Link:</label>
        <input type="text" id="link" name="link" value="" required><br />
        <label for="parent">Parent:</label>
        <input type="text" id="parent" name="parent" value=""><br />
        <label for="sort">Sort:</label>
        <input type="text" id="sort" name="sort" value=""><br />
        <div id="right">
            <label for="role">Role:</label>
            <select name="role">
                <option value="None">None</option>
                <option value="admin">Admin</option>
                <option value="owner" selected>Owner</option>
            </select>
        </div>
        <label for="is_public">Public:</label>
        <input type="hidden" name="is_public" value="-1">
        <input class="checkbox" type="checkbox" name="is_public" value="1"><br />
        <br /><br />
        <input type="submit">
        <input type="button" onclick="javascript:window.location = '<?php echo BASE_URI; ?>menu';" value="Cancel">
    </form>
</fieldset>
<hr />
<div id="menulist" class="ajax-list" data-form="menuForm" data-url="menu/menuList" style="position: relative;"></div>
<?= $this->menu_script; ?>
