<fieldset>
    <legend>RBAC</legend>
    <section class="page-content">
        <section class="grid">
            <article>
                <div id='calendar'></div>

                <table id="menu-table">
                    <tr>
                        <th align=right>Id</th>
                        <th align=left>Name</th>
                        <th></th>
                        <th></th>
                    </tr>
                    <?php foreach ($this->roles as $role) { ?>
                        <tr>
                            <td><?= htmlspecialchars($role['id']) ?></td>
                            <td><?= htmlspecialchars($role['Name']) ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </article>
        </section>
    </section>
</fieldset>