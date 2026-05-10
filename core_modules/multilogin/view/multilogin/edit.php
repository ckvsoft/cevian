<?php
/**
 * Picker dialog using framework conventions (fieldset / legend /
 * native table).
 */
$fwUser      = $this->data['fwUser']      ?? null;
$module      = $this->data['module']      ?? '';
$moduleLabel = $this->data['moduleLabel'] ?? $module;
$candidates  = $this->data['candidates']  ?? [];
$current     = $this->data['current']     ?? null;
?>
<fieldset>
    <legend><?php echo __('Set mapping'); ?></legend>

    <p><?php
        echo sprintf(
            __('Map framework user <strong>%s</strong> to a user in module <strong>%s</strong>.'),
            htmlspecialchars((string) $fwUser['username']),
            htmlspecialchars((string) $moduleLabel)
        );
    ?></p>

    <form method="post" action="<?php echo BASE_URI; ?>multilogin/save">
        <input type="hidden" name="framework_user_id" value="<?php echo (int) $fwUser['user_id']; ?>">
        <input type="hidden" name="module" value="<?php echo htmlspecialchars((string) $module); ?>">

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th><?php echo __('Customer'); ?></th>
                    <th><?php echo __('ID'); ?></th>
                    <th><?php echo __('Details'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <input type="radio" name="module_user_id" value="0" id="opt_none"
                            <?php echo $current === null ? 'checked' : ''; ?>>
                    </td>
                    <td colspan="3">
                        <label for="opt_none"><em><?php echo __('(no mapping)'); ?></em></label>
                    </td>
                </tr>
                <?php if (empty($candidates)): ?>
                    <tr>
                        <td colspan="4"><em><?php echo __('The provider for this module returned no candidate users.'); ?></em></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($candidates as $c):
                        $cid = (int) ($c['id'] ?? 0);
                        if ($cid <= 0) continue;
                        $rid = 'opt_' . $cid;
                    ?>
                        <tr>
                            <td>
                                <input type="radio" name="module_user_id" value="<?php echo $cid; ?>" id="<?php echo $rid; ?>"
                                    <?php echo $current === $cid ? 'checked' : ''; ?>>
                            </td>
                            <td><label for="<?php echo $rid; ?>"><strong><?php echo htmlspecialchars((string) ($c['label'] ?? '')); ?></strong></label></td>
                            <td>#<?php echo $cid; ?></td>
                            <td><?php echo htmlspecialchars((string) ($c['secondary'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <a href="<?php echo BASE_URI; ?>multilogin" class="button small-action"><?php echo __('Cancel'); ?></a>
            <button type="submit" class="button small-action save"><?php echo __('Save'); ?></button>
        </p>
    </form>
</fieldset>
