<?php
/**
 * Matrix view: rows = framework users, columns = modules with a
 * UserProvider, cells = currently mapped module-user (or "Set"
 * link). Uses the framework's own widget conventions (fieldset /
 * legend / native table) -- no inline styles, no pmwh3-specific
 * classes.
 */
$fwUsers = $this->data['fwUsers'] ?? [];
$modules = $this->data['modules'] ?? [];
$byUser  = $this->data['byUser']  ?? [];
$labels  = $this->data['labels']  ?? [];
?>
<fieldset>
    <legend><?php echo __('MultiLogin Mapping'); ?></legend>

    <p><?php echo __('Map framework users to module-specific user accounts. Each framework user can have at most one mapping per module.'); ?></p>

    <?php if (empty($modules)): ?>
        <p><em><?php echo __('No modules with a UserProvider were found. A module supports mapping by shipping utils/multilogin/userprovider.php.'); ?></em></p>
    <?php elseif (empty($fwUsers)): ?>
        <p><em><?php echo __('No framework users in the user table yet.'); ?></em></p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo __('Framework user'); ?></th>
                    <?php foreach ($modules as $key => $label): ?>
                        <th><?php echo htmlspecialchars((string) $label); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fwUsers as $u):
                    $uid = (int) $u['user_id'];
                ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars((string) $u['username']); ?></strong>
                            <br><small><?php echo htmlspecialchars((string) $u['email']); ?></small>
                            <br><small>#<?php echo $uid; ?> &middot; <?php echo htmlspecialchars((string) $u['role']); ?></small>
                        </td>
                        <?php foreach ($modules as $key => $label):
                            $mapped = $labels[$uid][$key] ?? null;
                            $editUrl = BASE_URI . 'multilogin/edit/' . $uid . '/' . urlencode($key);
                        ?>
                            <td>
                                <?php if ($mapped !== null): ?>
                                    <a href="<?php echo $editUrl; ?>"><?php echo htmlspecialchars((string) $mapped); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo $editUrl; ?>" class="button small-action edit"><?php echo __('Set'); ?></a>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</fieldset>
