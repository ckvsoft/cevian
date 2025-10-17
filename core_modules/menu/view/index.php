<fieldset>
    <legend><?= _('Menuentry: Add') ?></legend>
    <div id="addMenu" class="ajax-form-container" data-form="menuForm" data-url="menu/menuList">
        <form action="menu/create" method="post" id="menuForm">

            <label for="label"><?= _('Label') ?>:</label>
            <input type="text" id="label" name="label" value="" required><br />

            <label for="link"><?= _('Link') ?>:</label>
            <input type="text" id="link" name="link" value="" required><br />

            <label for="parent"><?= _('Parent') ?>:</label>
            <input type="text" id="parent" name="parent" value=""><br />

            <label for="sort"><?= _('Sort') ?>:</label>
            <input type="text" id="sort" name="sort" value=""><br />

            <div id="right">
                <label for="role"><?= _('Role') ?>:</label>
                <select name="role">
                    <option value="None"><?= _('None') ?></option>
                    <option value="admin"><?= _('Admin') ?></option>
                    <option value="owner" selected><?= _('Owner') ?></option>
                </select>
            </div>

            <label for="is_public"><?= _('Public') ?>:</label>
            <input type="hidden" name="is_public" value="-1">
            <input class="checkbox" type="checkbox" name="is_public" value="1"><br />

            <br />

            <input class="button small-action save" type="submit" value="<?= _('Create Menuentry') ?>">

            <input class="button small-action cancel" type="reset" value="<?= _('Clear') ?>">
        </form>
    </div>
</fieldset>
<fieldset style="margin-top: 30px;">
    <legend><?= _('Existing Menuentries') ?></legend>
    <div id="menulist" class="ajax-list" data-list="menu/menuList" style="position: relative;"></div>
</fieldset>