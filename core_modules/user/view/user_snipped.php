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

$userList = $this->userList; // Use the variable name from the user's original context
$baseUri = BASE_URI; // Use the base URI constant
?>

<div class="paginated list-cards">
    <?php if (!empty($userList)): ?>
        <?php foreach ($userList as $user): ?>

            <div class="card" data-user-id="<?= htmlspecialchars($user['user_id']) ?>">

                <div class="user-details">
                    <p class="name">
                        <strong><?= _('Name') ?>:</strong> <?= htmlspecialchars($user['username']); ?><br>
                        <strong><?= _('eMail') ?>:</strong> <?= htmlspecialchars($user['email']); ?>
                    </p>
                </div>

                <div class="actions" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">

                    <a class="button small-action edit" href="<?= htmlspecialchars($baseUri . 'user/edit/' . $user['user_id']) ?>">
                        <?= _('Edit') ?>
                    </a>

                    <?php if ($user['user_id'] > 1) { ?>
                        <a class="button small-action delete" href="<?= htmlspecialchars($baseUri . 'user/delete/' . $user['user_id']) ?>">
                            <?= _('Delete') ?>
                        </a>
                    <?php } ?>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p><?= _('No users found.') ?></p>

    <?php endif; ?>

</div>