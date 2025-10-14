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

// Konstante für die Basis-URI
$baseUri = BASE_URI;
?>

<!-- Die Klasse 'paginated' wird hier durch 'paginated-list' und 'list-cards' ersetzt,
     um das mobile DIV-Karten-Layout zu aktivieren. -->
<div class="paginated-list list-cards">
    <?php if (!empty($this->roles)): ?>
        <?php
        foreach ($this->roles as $role) {
            // Berechne die Einrückung für die Baumstruktur (20px pro Tiefe)
            $indentSize = 20;
            $paddingLeft = ($role['depth'] * $indentSize) . 'px';
            $labelPrefix = ($role['depth'] > 0) ? '↳ ' : '';
            ?>
            <!-- Start der Rollen-Karte -->
            <div class="card role-card depth-<?= $role['depth'] ?>" data-role-id="<?= htmlspecialchars($role['id']) ?>">

                <!-- 1. Rollen-Details mit Einrückung für die Baumstruktur -->
                <div class="role-details" style="padding-left: <?= $paddingLeft ?>;">

                    <!-- ID-Zeile -->
                    <p class="detail-line">
                        <strong>ID:</strong> <?= htmlspecialchars($role['id']) ?>
                    </p>

                    <!-- Rollenname-Zeile -->
                    <p class="detail-line">
                        <strong>Rolle:</strong> <?= $labelPrefix . htmlspecialchars($role['roleName']) ?>
                    </p>

                </div>
                <!-- Ende .role-details -->

                <!-- 2. Aktionen-Gruppe (Gestapelte Buttons für mobile Bearbeitung) -->
                <!-- Inline-Style stellt sicher, dass Buttons vertikal gestapelt und zentriert werden -->
                <div class="actions" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">

                    <!-- Bearbeiten Button (Breite auf 150px begrenzt) -->
                    <a class="button small-action edit"
                       href="<?= htmlspecialchars($baseUri . 'rbac/editRole/' . $role['id']) ?>">Bearbeiten</a>

                    <!-- Löschen Button (Breite auf 150px begrenzt) -->
                    <a class="button small-action delete"
                       href="#"
                       onclick="deleteRole(<?= $role['id'] ?>)">Löschen</a>

                </div>
                <!-- Ende .actions -->

            </div>
            <!-- Ende der Rollen-Karte -->
        <?php } ?>
    <?php else: ?>
        <p>Keine Rollen gefunden.</p>
    <?php endif; ?>
</div>
