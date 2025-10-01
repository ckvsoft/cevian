<fieldset style="margin-top: 30px;">
    <div data-form="rolePermissionsForm" data-json="1">
        <legend>Permissions for <?= $this->title ?>: <?= htmlspecialchars($this->role['roleName']); ?></legend>

        <form action="<?= BASE_URI ?>rbac/saveRolePerms" method="post" id="rolePermissionsForm">
            <input type="hidden" name="role_id" value="<?= $this->role['id'] ?>">
            <div class="paginated">

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th style="width: 40%; white-space: nowrap;">Permission Key / Name</th>
                            <th style="width: 25%; white-space: nowrap;">Current Effective Access (Geerbt/Gesetzt)</th>
                            <th style="width: 35%; white-space: nowrap;">Direct Assignment (Override: 0/1/X)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($this->allPerms as $perm):
                            $permId = $perm['id'];
                            $effectiveValue = $this->effective[$permId] ?? false;
                            $effectiveLabel = $effectiveValue ? '✅ Erlaubt' : '❌ Verweigert';

                            $assignedValue = $this->assigned[$permId] ?? 'X';

                            $source = ' (Standard)';
                            if (isset($this->assigned[$permId])) {
                                $source = ($this->assigned[$permId] == 'X') ? ' (Geerbt)' : ' (Explizit)';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($perm['permName']) ?></strong><br>
                                    <small style="color:#666;"><?= htmlspecialchars($perm['permKey']) ?></small>
                                </td>
                                <td style="white-space: nowrap;">
                                    <span class="badge" style="background-color: <?= $effectiveValue ? '#28a745' : '#dc3545'; ?>; white-space: nowrap;">
                                        <?= $effectiveLabel . $source ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <select name="perm_<?= $permId ?>" id="perm_<?= $permId ?>" class="form-control" style="min-width: 200px;">
                                        <option value="X" <?= ($assignedValue == 'X' ? 'selected' : '') ?>>
                                            X - Inherit (Erbt: <?= $effectiveValue ? 'Erlaubt' : 'Verweigert' ?>)
                                        </option>
                                        <option value="1" <?= ($assignedValue == '1' ? 'selected' : '') ?>>
                                            1 - Explicitly Allow (Erlauben)
                                        </option>
                                        <option value="0" <?= ($assignedValue == '0' ? 'selected' : '') ?>>
                                            0 - Explicitly Deny (Verweigern)
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</fieldset>