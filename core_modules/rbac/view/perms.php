<fieldset>
    <legend>User: Add</legend>
    <form action="rbac/create" method="post" id="permsForm">
        <label for="permName">Permission:</label><input type="text" id="permName" name="permName" required>
        <label for="permDescription">Description:</label>
        <input type="text" id="permDescription" name="permDescription" required>
        <label></label>
        <input class="buttonSubmit" type="submit" value="Create Permission">
    </form>
</fieldset>
<hr />
<div id="permslist"></div>

<script>
<?php echo $this->script; ?>
</script>
