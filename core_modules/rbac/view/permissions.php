<?php
/*
 * The MIT License
 *
 * Copyright 2025 chris.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * View: rbac/role_permissions_editor.php
 * Renders the editor for assigning permissions to a specific role using the classic TABLE structure.
 */
// NOTE: This view expects $this->title, $this->role, $this->allPerms, $this->effective, $this->assigned to be available.
?>
<fieldset style="margin-top: 30px;">
    <div data-form="rolePermissionsForm" data-json="1">
        <legend><?= _('Permissions for') ?> <?= htmlspecialchars($this->title) ?>: <?= htmlspecialchars($this->role['roleName']); ?></legend>

        <form action="<?= BASE_URI ?>rbac/saveRolePerms" method="post" id="rolePermissionsForm" data-redirect="<?= $this->redirect ?>">
            <input type="hidden" name="role_id" value="<?= $this->role['id'] ?>">

            <div class="paginated">
                <table class="table table-striped" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 40%; white-space: nowrap;"><?= _('Permission Key / Name') ?></th>
                            <th style="width: 25%; white-space: nowrap;"><?= _('Current Effective Access') ?></th>
                            <th style="width: 35%; white-space: nowrap;"><?= _('Direct Assignment (Override: 0/1/X)') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($this->allPerms as $perm):
                            $permId = $perm['id'];
                            $effectiveValue = $this->effective[$permId] ?? false;

                            // Dynamic effective label translated
                            $effectiveLabel = $effectiveValue ? _('✅ Allowed') : _('❌ Denied');

                            $assignedValue = $this->assigned[$permId] ?? 'X';

                            // Source label translation logic
                            $source = ' (' . _('Default') . ')';
                            if (isset($this->assigned[$permId])) {
                                $source = ($this->assigned[$permId] == 'X') ? ' (' . _('Inherited') . ')' : ' (' . _('Explicit') . ')';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($perm['permName']) ?></strong><br>
                                    <small style="color:#aaa;"><?= htmlspecialchars($perm['permKey']) ?></small>
                                </td>
                                <td style="white-space: nowrap;">
                                    <span class="badge" style="background-color: <?= $effectiveValue ? '#28a745' : '#dc3545'; ?>; white-space: nowrap; padding: 6px 12px; border-radius: 4px; display: inline-block;">
                                        <?= $effectiveLabel . $source ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <select name="perm_<?= $permId ?>" id="perm_<?= $permId ?>" class="form-control" style="min-width: 200px;">
                                        <option value="X" <?= ($assignedValue == 'X' ? 'selected' : '') ?>>
                                            <?= _('X - Inherit') ?> (<?= _('Effective') ?>: <?= $effectiveValue ? _('Allowed') : _('Denied') ?>)
                                        </option>
                                        <option value="1" <?= ($assignedValue == '1' ? 'selected' : '') ?>>
                                            <?= _('1 - Explicitly Allow') ?> (<?= _('Allow') ?>)
                                        </option>
                                        <option value="0" <?= ($assignedValue == '0' ? 'selected' : '') ?>>
                                            <?= _('0 - Explicitly Deny') ?> (<?= _('Deny') ?>)
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <button type="submit" class="button small-action save">
                    <?= _('Save permissions') ?>
                </button>
            </div>
        </form>
    </div>
</fieldset>