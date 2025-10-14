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
        <legend>Permissions for <?= $this->title ?>: <?= htmlspecialchars($this->role['roleName']); ?></legend>

        <form action="<?= BASE_URI ?>rbac/saveRolePerms" method="post" id="rolePermissionsForm">
            <input type="hidden" name="role_id" value="<?= $this->role['id'] ?>">

            <div class="paginated">
                <!-- Klassische Tabelle für garantierte Spaltenausrichtung auf dem Desktop -->
                <table class="table table-striped" style="width: 100%;">
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
                                    <small style="color:#aaa;"><?= htmlspecialchars($perm['permKey']) ?></small>
                                </td>
                                <td style="white-space: nowrap;">
                                    <!-- Inline-Style für dynamische Badge-Farbe -->
                                    <span class="badge" style="background-color: <?= $effectiveValue ? '#28a745' : '#dc3545'; ?>; white-space: nowrap; padding: 6px 12px; border-radius: 4px; display: inline-block;">
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

            <!-- Speichern-Button ist außerhalb der Paginierung (immer sichtbar) -->
            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="button small-action save">
                    Berechtigungen Speichern
                </button>
            </div>
        </form>
    </div>
</fieldset>
