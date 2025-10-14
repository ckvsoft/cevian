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
if (empty($this->permissions)) :
    ?>
    <div class="info-message">No permissions defined yet.</div>
    <?php
    return;
endif;

// Konstante für die Basis-URI
$baseUri = BASE_URI;
?>

<div class="list-cards">
    <?php foreach ($this->permissions as $perm) : ?>

        <!-- Start of Permission Card -->
        <div class="card permission-card" data-perm-id="<?= htmlspecialchars($perm['id']) ?>">

            <!-- 1. Permission Details Group -->
            <div class="permission-details">

                <!-- ID Line -->
                <p class="detail-line">
                    <strong>ID:</strong> <?= htmlspecialchars($perm['id']) ?>
                </p>

                <!-- Key Line -->
                <p class="detail-line">
                    <strong>Key:</strong> <?= htmlspecialchars($perm['permKey']) ?>
                </p>

                <!-- Name Line -->
                <p class="detail-line">
                    <strong>Name:</strong> <?= htmlspecialchars($perm['permName']) ?>
                </p>

                <!-- Description Line -->
                <p class="detail-line">
                    <strong>Description:</strong> <?= htmlspecialchars($perm['permDescription']) ?>
                </p>

            </div>
            <!-- End .permission-details -->

            <!-- 2. Actions Group (Stacked Buttons for mobile editing) -->
            <!-- Inline style ensures buttons are stacked vertically and sized correctly -->
            <div class="actions" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">

                <!-- Edit Button (Width limited to 150px) -->
                <a class="button small-action edit"
                   style="width: 150px; text-align: center;"
                   href="<?= htmlspecialchars($baseUri . 'rbac/editPermission/' . $perm['id']) ?>">Edit</a>

            </div>
            <!-- End .actions -->

        </div>
        <!-- End of Permission Card -->

    <?php endforeach; ?>
</div>
