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
 * Renders ONLY the Permission Table for AJAX/Pagination.
 */
if (empty($this->permissions)) :
    ?>
    <div class="info-message">No permissions defined yet.</div>
    <?php
    return;
endif;
?>

<table class="paginated">
    <thead>
        <tr>
            <th style="white-space: nowrap;">ID</th>
            <th style="width: 10%; white-space: nowrap;">Key</th>
            <th style="width: 20%; white-space: nowrap;">Name</th>
            <th style="width: 60%; white-space: nowrap;">Description</th>
            <th style="width: 150px; white-space: nowrap;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($this->permissions as $perm) :
            ?>
            <tr>
                <td><?= htmlspecialchars($perm['id']) ?></td>
                <td><?= htmlspecialchars($perm['permKey']) ?></td>
                <td><?= htmlspecialchars($perm['permName']) ?></td>
                <td><?= htmlspecialchars($perm['permDescription']) ?></td>
                <td style="white-space: nowrap;">
                    <a href="<?= BASE_URI ?>rbac/editPermission/<?= $perm['id'] ?>"
                       class="small-action edit-permission">
                        Edit
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
