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

// Constant for the base URI
$baseUri = BASE_URI;
?>

<div class="paginated-list list-cards">
    <?php if (!empty($this->roles)): ?>
        <?php
        // Iterate over the list of roles
        foreach ($this->roles as $role) {
            // Calculate indentation for the tree structure (20px per depth)
            $indentSize = 20;
            $paddingLeft = ($role['depth'] * $indentSize) . 'px';
            // Add a prefix arrow for child roles
            $labelPrefix = ($role['depth'] > 0) ? '↳ ' : '';
            ?>
            <div class="card role-card depth-<?= $role['depth'] ?>" data-role-id="<?= htmlspecialchars($role['id']) ?>">

                <div class="card-details" style="padding-left: <?= $paddingLeft ?>;">

                    <p class="detail-line">
                        <strong><?= _('Role') ?>:</strong> <?= $labelPrefix . htmlspecialchars($role['roleName']) ?>
                    </p>

                </div>
                <div class="actions column">

                    <a class="button small-action edit"
                       href="<?= htmlspecialchars($baseUri . 'rbac/editRole/' . $role['id']) ?>"><?= _('Edit') ?></a>

                    <a class="button small-action delete"
                       href="#"
                       onclick="deleteRole(<?= $role['id'] ?>)"><?= _('Delete') ?></a>

                </div>
            </div>
        <?php } ?>
    <?php else: ?>
        <p><?= _('No roles found.') ?></p>
    <?php endif; ?>
</div>