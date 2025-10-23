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
 * View Snippet: rbac/permission_table_snippet.php
 * Renders the Permission List using a responsive DIV card structure for mobile devices.
 */
// Check if the permissions array is empty
if (empty($this->permissions)) :
    ?>
    <div class="info-message"><?= _('No permissions defined yet.') ?></div>
    <?php
    return;
endif;

// Constant for the base URI
$baseUri = BASE_URI;
?>

<div class="list-cards">
    <?php
    // Loop through each permission to create a card
    foreach ($this->permissions as $perm) :
        ?>

        <div class="card" data-perm-id="<?= htmlspecialchars($perm['id']) ?>">

            <div class="card-details">

                <p class="detail-line">
                    <strong><?= _('Key') ?>:</strong> <?= htmlspecialchars($perm['permKey']) ?>
                </p>

                <p class="detail-line">
                    <strong><?= _('Name') ?>:</strong> <?= htmlspecialchars($perm['permName']) ?>
                </p>

                <p class="detail-line">
                    <strong><?= _('Description') ?>:</strong> <?= htmlspecialchars($perm['permDescription']) ?>
                </p>

            </div>
            <div class="actions column">

                <a class="button small-action edit" href="<?= htmlspecialchars($baseUri . 'rbac/editPermission/' . $perm['id']) ?>"><?= _('Edit') ?></a>

            </div>
        </div>
    <?php endforeach; ?>
</div>