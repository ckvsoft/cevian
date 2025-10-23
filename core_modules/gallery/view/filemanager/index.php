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
?>

<style>
    /* Basic styles for a clean side-by-side view */
    .file-manager-container {
        display: flex;
        gap: 20px;
        padding: 20px;
    }

    /* PANEL BORDER FIX: Restores the light default border and keeps the active blue border */
    .file-panel {
        flex: 1;
        /* Light border for separation (Restored fix for the missing white/light border) */
        border: 2px solid #e0e0e0;
        padding: 10px;
        min-height: 400px;
        overflow-y: auto;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }

    .file-panel.active-panel {
        /* Blue border for the active panel */
        border: 2px solid #007bff;
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
    }

    .file-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    /* FILE ITEM LAYOUT: Enables Flexbox for Name/Metadata display */
    .file-item {
        /* Use Flexbox to align icon/thumbnail, filename, size, and date in one row */
        display: flex;
        justify-content: space-between; /* Distributes content (Name left, Metadata right) */
        align-items: center; /* Vertically centers the content */
        width: 100%;
        padding: 5px 10px;
        cursor: pointer;
        border-bottom: 1px dotted #eee;
        user-select: none;
        text-align: left;
    }

    .file-item:hover {
        background-color: #e9e9e9;
        color: #000;
    }

    .file-item.selected {
        /* Style for selected items */
        background-color: #cce5ff;
        border-left: 5px solid #007bff;
        padding-left: 5px;
        color: #000;
    }

    .file-item.album {
        font-weight: bold;
        color: #007bff;
    }

    /* --- METADATA COLUMN DEFINITION --- */

    .item-main-content {
        /* Flex container for Icon and Filename (takes up most space) */
        display: flex;
        align-items: center;
        flex-grow: 1;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        padding-right: 15px; /* Spacing before metadata */
    }

    .item-metadata {
        /* Flex container for Size and Date (right-aligned) */
        display: flex;
        /* Fixed width for metadata section to keep columns aligned */
        min-width: 140px;
        font-size: 0.85em;
        color: #555;
        justify-content: space-between;
        /* Prevents metadata from shrinking too much */
        flex-shrink: 0;
    }

    .item-size {
        width: 50px; /* Space for e.g., "1.2 MB" */
        text-align: right;
        padding-right: 10px;
    }

    .item-date {
        width: 85px; /* Space for e.g., "2025-10-23" */
        text-align: right;
    }

    /* --- RESTORED EXISTING STYLES --- */

    .file-item img {
        /* Style for the thumbnail */
        object-fit: cover;
        border-radius: 3px;
        width: 32px;
        height: 32px;
        margin-right: 10px;
        flex-shrink: 0;
    }

    .file-panel.drag-over,
    .file-item.drag-over {
        border: 2px dashed green !important;
        background-color: #e6ffe6 !important;
    }
    .file-item.dragging {
        opacity: 0.4;
    }
    .current-path {
        font-weight: normal;
        font-style: italic;
        color: #666;
    }
    .controls button#deleteSelectedBtn {
        margin-left: 10px;
        color: white;
        background-color: #dc3545; /* Red */
        border: 1px solid #dc3545;
    }
    .controls button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Upload Overlay Styles */
    #uploadProgressOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        display: none;
        justify-content: center;
        align-items: flex-start;
        z-index: 99999;
        padding-top: 0;
    }

    #uploadProgressBar {
        width: 80%;
        max-width: 500px;
        height: 10px;
        background-color: #ddd;
        border-radius: 5px;
        overflow: hidden;
        margin-top: 20px;
    }

    #uploadProgressBar::after {
        content: '';
        display: block;
        height: 100%;
        width: 25%;
        background-color: #00bfff;
        animation: marquee 1.5s linear infinite;
    }

    @keyframes marquee {
        0% {
            transform: translateX(0%);
        }
        100% {
            transform: translateX(400%);
        }
    }
</style>

<div id="uploadProgressOverlay">
    <div id="uploadProgressBar"></div>
</div>

<fieldset>
    <legend><?= $this->title; ?></legend>

    <div class="controls" style="margin-bottom: 15px;">
        <button class="button small-action edit" id="createDirBtn"><?php echo _('Create New Directory'); ?></button>
        <button class="button small-action delete" id="deleteSelectedBtn" disabled><?php echo _('Delete Selected Items'); ?></button>
    </div>

    <div class="file-manager-container">

        <div class="file-panel" id="leftPanel" data-current-path="<?php echo htmlspecialchars($this->manager['currentPath']); ?>">
            <div class="path-display">Path: <span class="current-path">/<?php echo htmlspecialchars($this->manager['currentPath']); ?></span></div>
            <ul class="file-list">
            </ul>
        </div>

        <div class="file-panel" id="rightPanel" data-current-path="<?php echo htmlspecialchars($this->manager['currentPath']); ?>">
            <div class="path-display">Path: <span class="current-path">/<?php echo htmlspecialchars($this->manager['currentPath']); ?></span></div>
            <ul class="file-list">
            </ul>
        </div>
    </div>
</fieldset>

<script>
    const LOCAL_BASE_URI = '<?php echo BASE_URI; ?>';

    // --- GLOBAL VARIABLES ---
    let draggedItem = null;
    let activePanelId = 'leftPanel'; // Stores the ID of the panel last clicked or focused
    let selectedItems = new Map(); // Stores the selection: Map<PanelID, Array<Path>>
    let lastClickedItem = new Map(); // Stores the last clicked path per panel for range selection
    const deleteButton = document.getElementById('deleteSelectedBtn');

    // CRITICAL FIX: Flag to prevent re-entry/double execution of drop logic.
    let isMoving = false;


    // --- UTILITY: Disables the delete button if nothing is selected ---
    function updateDeleteButtonState() {
        // Calculate the total number of selected items across all panels
        let totalSelected = 0;
        selectedItems.forEach(paths => totalSelected += paths.length);
        deleteButton.disabled = totalSelected === 0;
    }

    // --- SELECTION & ACTIVATION HANDLERS ---
    /**
     * Sets the specified panel as the active panel (for selection/operations).
     * This function is crucial for visual feedback and directing actions like deletion/mkdir.
     * @param {HTMLElement} panelElement - The panel DOM element to set as active.
     */
    function setActivePanel(panelElement) {
        // Remove 'active-panel' class from all panels
        document.querySelectorAll('.file-panel').forEach(p => p.classList.remove('active-panel'));
        // Add 'active-panel' class to the target panel
        panelElement.classList.add('active-panel');
        // Update the global variable
        activePanelId = panelElement.id;
    }

    /**
     * Handles file item selection (single click, Ctrl/Cmd click, Shift click).
     * @param {MouseEvent} e - The click event object.
     */
    function handleSelection(e) {
        e.stopPropagation(); // Stop event from bubbling up to the panel click handler

        const panelElement = e.target.closest('.file-panel');
        if (!panelElement)
            return;

        const panelId = panelElement.id;
        const itemElement = e.target.closest('.file-item');
        if (!itemElement)
            return;

        const path = itemElement.dataset.path;

        let currentSelection = selectedItems.get(panelId) || [];
        const isSelected = currentSelection.includes(path);
        let lastPath = lastClickedItem.get(panelId);

        // 1. Range Selection (Shift)
        if (e.shiftKey && lastPath) {
            currentSelection = [...selectedItems.get(panelId)]; // Start with existing selection
            const items = Array.from(panelElement.querySelectorAll('.file-item:not(.isParent)')); // All selectable items

            const lastIndex = items.findIndex(i => i.dataset.path === lastPath);
            const currentIndex = items.findIndex(i => i.dataset.path === path);

            if (lastIndex !== -1 && currentIndex !== -1) {
                const [start, end] = lastIndex < currentIndex ? [lastIndex, currentIndex] : [currentIndex, lastIndex];

                // Clear previous range selection
                if (currentSelection.length > 0) {
                    items.forEach(item => {
                        const itemPath = item.dataset.path;
                        const index = currentSelection.indexOf(itemPath);
                        if (index > -1) {
                            currentSelection.splice(index, 1);
                            item.classList.remove('selected');
                        }
                    });
                    // Re-select everything
                    currentSelection = [];
                }

                // Add new range
                for (let i = start; i <= end; i++) {
                    const itemPath = items[i].dataset.path;
                    if (!currentSelection.includes(itemPath)) {
                        currentSelection.push(itemPath);
                        items[i].classList.add('selected');
                    }
                }
            }

            // Set selection on DOM elements based on the final currentSelection list
            items.forEach(item => {
                const itemPath = item.dataset.path;
                if (currentSelection.includes(itemPath)) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });

        }

        // 2. Multi Selection (Ctrl/Cmd)
        else if (e.ctrlKey || e.metaKey) {
            if (isSelected) {
                currentSelection = currentSelection.filter(p => p !== path);
                itemElement.classList.remove('selected');
            } else {
                currentSelection.push(path);
                itemElement.classList.add('selected');
            }
            lastClickedItem.set(panelId, path); // Update last clicked path
        }

        // 3. Single Selection (No modifiers)
        else {
            // Clear all other selections in this panel
            panelElement.querySelectorAll('.file-item.selected').forEach(item => {
                if (item !== itemElement) {
                    item.classList.remove('selected');
                }
            });

            // Toggle the current item
            if (isSelected && currentSelection.length === 1) {
                // If already selected and only item, deselect it
                currentSelection = [];
                itemElement.classList.remove('selected');
                lastClickedItem.set(panelId, null);
            } else {
                // Select the current item and clear others
                currentSelection = [path];
                itemElement.classList.add('selected');
                lastClickedItem.set(panelId, path);
            }
        }

        selectedItems.set(panelId, currentSelection);
        updateDeleteButtonState();
    }


    // --- DRAG-AND-DROP HANDLERS ---
    /**
     * Handles the start of a drag operation.
     * @param {DragEvent} e - The drag event object.
     */
    function handleDragStart(e) {
        const itemElement = e.target.closest('.file-item');
        if (!itemElement)
            return;

        const path = itemElement.dataset.path;
        const panelElement = itemElement.closest('.file-panel');
        const panelId = panelElement.id;

        // Ensure the dragged item is selected, or make it the only selected item
        let currentSelection = selectedItems.get(panelId) || [];
        let pathsToDrag;

        if (currentSelection.includes(path)) {
            // Dragging a part of the current selection
            pathsToDrag = currentSelection;
        } else {
            // Dragging an unselected item: deselect all others and select this one
            panelElement.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
            itemElement.classList.add('selected');
            pathsToDrag = [path];
            selectedItems.set(panelId, pathsToDrag);
            updateDeleteButtonState();
        }

        // Store paths in a custom JSON format for multi-file move handling in handleDrop
        e.dataTransfer.setData('application/json/paths', JSON.stringify(pathsToDrag));

        // Store the main path in text/plain (Fallback for single-item moves)
        e.dataTransfer.setData('text/plain', pathsToDrag.join(','));

        draggedItem = pathsToDrag; // Store for internal checks
        e.dataTransfer.dropEffect = 'move';
    }

    /**
     * Handles the end of a drag operation.
     * @param {DragEvent} e - The drag event object.
     */
    function handleDragEnd(e) {
        draggedItem = null;
        // Clean up visual effects if necessary
    }

    /**
     * Handles when a dragged item enters a drop target.
     * @param {DragEvent} e - The drag event object.
     */
    function handleDragEnter(e) {
        e.preventDefault();
        // Simple visual feedback
        if (e.target.closest('.file-item.album') || e.target.closest('.file-panel')) {
            e.target.classList.add('drag-over');
        }
    }

    /**
     * Handles when a dragged item leaves a drop target.
     * @param {DragEvent} e - The drag event object.
     */
    function handleDragLeave(e) {
        // Remove visual feedback
        e.target.classList.remove('drag-over');
    }

    /**
     * Handles drag over event (required for drop to work).
     * @param {DragEvent} e - The drag event object.
     */
    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }


    /**
     * Processes the drop operation, handling both local file uploads and internal file moves.
     * CORRECTIONS:
     * 1. Uses 'isMoving' flag to prevent re-entry/double execution.
     * 2. Robust path extraction with deduplication.
     * 3. Targeted panel reload after all moves complete (Promise.all) to prevent race conditions.
     * @param {DragEvent} e - The drag event object.
     */
    function handleDrop(e) {
        e.preventDefault();
        e.target.classList.remove('drag-over');

        // CRITICAL FIX 1: Prevents re-entry/double execution.
        if (isMoving) {
            console.warn('Move already in progress. Ignoring drop event.');
            return;
        }

        let dropTargetElement = e.target;
        // Find the closest droppable element (album or panel)
        dropTargetElement = e.target.closest('.file-item.album') || e.target.closest('.file-panel');

        if (!dropTargetElement) {
            return;
        }

        // Determine the target path and panel ID
        let targetPath;
        let targetPanelId;

        if (dropTargetElement.classList.contains('file-panel')) {
            targetPath = dropTargetElement.dataset.currentPath;
            targetPanelId = dropTargetElement.id; // Get the panel ID
        } else if (dropTargetElement.classList.contains('album')) {
            targetPath = dropTargetElement.dataset.path;
            targetPanelId = dropTargetElement.closest('.file-panel').id; // Get the containing panel ID
        } else {
            return;
        }

        if (targetPath === '..') {
            return;
        }

        // --- LOGIC: Check for local files first (Upload) ---
        if (e.dataTransfer.files.length > 0) {
            const files = Array.from(e.dataTransfer.files);
            uploadFiles(files, targetPath);
            return;
        }

        // --- LOGIC: Internal Drag-and-Drop (MOVE operation) ---

        // 1. Get paths from internal Drag-and-Drop data transfer and DEDUPLICATE
        let rawSourcePaths = [];

        const jsonPaths = e.dataTransfer.getData('application/json/paths');
        if (jsonPaths) {
            try {
                rawSourcePaths = JSON.parse(jsonPaths);
            } catch (error) {
                // Ignore parsing error for robustness
            }
        }

        // Add text/plain data
        const textPath = e.dataTransfer.getData('text/plain');
        if (textPath) {
            if (textPath.includes(',')) {
                rawSourcePaths = rawSourcePaths.concat(textPath.split(',').map(p => p.trim()));
            } else {
                rawSourcePaths.push(textPath);
            }
        }

        // CRITICAL: Deduplicate the paths to ensure each move is initiated only once.
        const uniqueSourcePaths = [...new Set(rawSourcePaths.filter(path => path))];

        if (uniqueSourcePaths.length === 0) {
            return;
        }

        // 2. Set the flag and collect promises
        isMoving = true; // Set flag to block further calls
        let movePromises = [];
        let moveCount = 0;

        uniqueSourcePaths.forEach(sourcePath => {
            const sourceParentPath = sourcePath.substring(0, sourcePath.lastIndexOf('/'));

            // Validation for each element
            if (targetPath === sourceParentPath) {
                if (moveCount === 0)
                    displayMessage('info', '<?= _("Move Canceled") ?>', `<?= _("Item %s is already in this folder.") ?>`.replace('%s', basename(sourcePath)));
                return;
            }
            if (targetPath.startsWith(sourcePath + '/')) {
                if (moveCount === 0)
                    displayMessage('error', '<?= _("Move Failed") ?>', '<?= _("Cannot move an item into its own subfolder.") ?>');
                return;
            }

            // Add the Promise returned by moveItem to the array
            movePromises.push(moveItem(sourcePath, targetPath));
            moveCount++;
        });

        // 3. WAIT for all move operations to complete before reloading the panels.
        if (moveCount > 0) {
            Promise.all(movePromises)
                    .finally(() => {
                        isMoving = false; // RESET the flag regardless of success/failure
                    })
                    .then(results => {
                        // Get the target panel element (where the items were moved TO)
                        const targetPanel = document.getElementById(targetPanelId);

                        if (targetPanel) {
                            const currentTargetPath = targetPanel.dataset.currentPath;
                            // Reload the target panel (new files appear here)
                            browseDirectory(targetPanel, currentTargetPath);

                            // Check the OTHER panel (item disappeared from there)
                            const otherPanelId = (targetPanelId === 'leftPanel') ? 'rightPanel' : 'leftPanel';
                            const otherPanel = document.getElementById(otherPanelId);

                            if (otherPanel && uniqueSourcePaths.length > 0) {
                                const otherPanelPath = otherPanel.dataset.currentPath;
                                // Source path: We need the parent of one of the moved items
                                const sourceParentPath = uniqueSourcePaths[0].substring(0, uniqueSourcePaths[0].lastIndexOf('/'));

                                // Reload the other panel IF it was showing the source folder
                                if (otherPanelPath === sourceParentPath) {
                                    browseDirectory(otherPanel, otherPanelPath);
                                }
                            }
                        } else {
                            // Fallback: Reload both if the targetPanelId was lost
                            browseDirectory(document.getElementById('leftPanel'), document.getElementById('leftPanel').dataset.currentPath);
                            browseDirectory(document.getElementById('rightPanel'), document.getElementById('rightPanel').dataset.currentPath);
                        }

                        // Provide a final summary of the move operation
                        let successMoves = results.filter(r => r && r.success === 1).length;
                        let failedMoves = moveCount - successMoves;

                        if (successMoves > 0 && failedMoves === 0) {
                            // Display success message only if ALL succeeded
                        } else if (failedMoves > 0) {
                            displayMessage('error', '<?= _("Move Complete with Errors") ?>', `<?= _("Failed to move %s of %s item(s).") ?>`.replace('%s', failedMoves).replace('%s', moveCount));
                        }
                    })
                    .catch(error => {
                        console.error('Global error waiting for move operations:', error);
                        displayMessage('error', '<?= _("Move Failed") ?>', '<?= _("An unexpected error occurred during move processing.") ?>');
                    });
        }
    }

    // --- CORE FUNCTION: uploadFiles ---
    /**
     * Handles the upload of files dropped from the local system, showing a loading indicator.
     * @param {File[]} files - An array of File objects to upload.
     * @param {string} targetPath - The server path to upload the files to.
     */
    function uploadFiles(files, targetPath) {
        const uri = LOCAL_BASE_URI + 'gallery/filemanager/upload';
        const progressOverlay = document.getElementById('uploadProgressOverlay');

        let successCount = 0;
        let failCount = 0;
        let totalFiles = files.length;
        let processedFiles = 0;
        let panelToReload = document.querySelector(`.file-panel[data-current-path="${targetPath}"]`);

        // Display the progress overlay
        const headerElement = document.querySelector('.fixed-header');
        let headerHeight = 0;
        if (headerElement && window.getComputedStyle(headerElement).position === 'fixed') {
            headerHeight = headerElement.offsetHeight;
        }
        progressOverlay.style.setProperty('top', headerHeight + 'px');
        progressOverlay.style.display = 'flex';

        // Loop through each dropped file and initiate an upload
        files.forEach(file => {
            const formData = new FormData();
            formData.append('targetPath', targetPath);
            formData.append('file_name', file.name);
            formData.append('image', file);

            fetch(uri, {
                method: 'POST',
                body: formData
            })
                    .then(response => response.json())
                    .then(data => {
                        processedFiles++;
                        if (data.success === 1) {
                            successCount++;
                        } else {
                            failCount++;
                            const message = data.errorMessage && data.errorMessage.message ? data.errorMessage.message : 'Unknown upload error.';
                            displayMessage('error', `<?= _("Upload Failed: %s") ?>`.replace('%s', file.name), message);
                        }
                    })
                    .catch(error => {
                        processedFiles++;
                        failCount++;
                        console.error('Upload failed for %s:'.replace('%s', file.name), error);
                        displayMessage('error', `<?= _("Upload Failed: %s") ?>`.replace('%s', file.name), '<?= _("Network or unexpected error occurred.") ?>');
                    })
                    .finally(() => {
                        // Check if all files have been processed
                        if (processedFiles === totalFiles) {

                            // HIDE THE LOADING BAR/OVERLAY when all files are done
                            progressOverlay.style.display = 'none';

                            if (successCount > 0) {
                                displayMessage('success', '<?= _("Upload Complete") ?>', `<?= _("%s file(s) uploaded successfully.") ?>`.replace('%s', successCount));
                                // Reload the target panel to show the new files
                                if (panelToReload) {
                                    browseDirectory(panelToReload, targetPath);
                                }
                            }
                            if (failCount > 0 && successCount === 0) {
                                displayMessage('error', '<?= _("Upload Failed") ?>', '<?= _("All uploads failed. Check individual error messages.") ?>');
                            }
                        }
                    });
        });
    }

    // --- CORE FUNCTION: deleteSelectedItems ---
    /**
     * Deletes the currently selected items in the active panel after user confirmation.
     */
    function deleteSelectedItems() {
        const panelId = activePanelId;
        const pathsToDelete = selectedItems.get(panelId);
        const currentPath = document.getElementById(panelId).dataset.currentPath;
        const targetPanel = document.getElementById(panelId);

        if (!pathsToDelete || pathsToDelete.length === 0) {
            displayMessage('info', '<?= _("No Selection") ?>', '<?= _("Please select at least one item to delete.") ?>');
            return;
        }

        const itemsString = pathsToDelete.map(p => `\n- ${basename(p)}`).join('');
        const confirmation = confirm(`<?= _("Are you sure you want to delete the following %s item(s) from \"%s\"?%s") ?>`.replace('%s', pathsToDelete.length).replace('%s', currentPath).replace('%s', itemsString));

        if (!confirmation) {
            return;
        }

        const bodyData = {paths: pathsToDelete};

        fetch(LOCAL_BASE_URI + 'gallery/filemanager/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bodyData) // Send the array as a JSON object
        }).then(response => response.text())
                .then(text => {
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('SERVER RESPONSE ERROR: Failed to parse JSON. Raw server output:', text);
                        const serverMessage = text.substring(0, 100) + (text.length > 100 ? '...' : '');
                        displayMessage('error', '<?= _("Delete Failed (Invalid Server Response)") ?>', `<?= _("Server sent non-JSON data. First characters: \"%s\"") ?>`.replace('%s', serverMessage));
                        return;
                    }

                    // --- FINAL ROBUST LOGIC FOR ALL ERROR CASES ---
                    let message = '<?= _("Deletion failed due to server error.") ?>'; // Default message
                    let details = null;

                    if (data.success === 1) {
                        // CASE 1: SUCCESS
                        message = data.data && data.data.message ? data.data.message : '<?= _("Deletion Complete.") ?>';
                    } else {
                        // CASE 2: ERROR
                        if (data.data && data.data.message) {
                            message = data.data.message;
                            details = data.data.details;
                        } else if (data.errorMessage) {
                            if (typeof data.errorMessage === 'object' && data.errorMessage !== null) {
                                message = data.errorMessage.message || '<?= _("Unknown error object received.") ?>';
                            } else if (typeof data.errorMessage === 'string') {
                                message = data.errorMessage;
                            }
                        }
                    }

                    // Display the final message
                    if (data.success === 1) {
                        displayMessage('success', '<?= _("Deletion Complete") ?>', message);
                    } else {
                        displayMessage('error', '<?= _("Deletion Failed or Completed with Errors") ?>', message, details);
                    }

                    // Reload the affected panel and reset selection
                    browseDirectory(targetPanel, currentPath);
                    selectedItems.set(panelId, []);
                    updateDeleteButtonState();
                })
                .catch(error => {
                    console.error('Delete failed due to network or unexpected error:', error);
                    displayMessage('error', '<?= _("Delete Failed") ?>', '<?= _("A network error occurred.") ?>');
                });
    }

    // --- UTILITY ---
    /**
     * Extracts the file or directory name from a full path.
     * @param {string} path - The full path string.
     * @returns {string} The basename (file/dir name).
     */
    function basename(path) {
        return path.split('/').reverse()[0];
    }

    // ----------------------------
    // File Manager Path Persistence
    // ----------------------------

    /**
     * Creates a unique storage key based on the panel ID.
     * @param {string} panelId - The ID of the panel (e.g., 'panel-left').
     * @returns {string} The unique localStorage key.
     */
    function getPathKey(panelId) {
        return 'filemanager_path_' + panelId;
    }

    /**
     * Saves the current directory path for a specific panel in localStorage.
     * @param {HTMLElement} panelElement - The panel DOM element.
     * @param {string} path - The directory path to store.
     */
    function savePanelPath(panelElement, path) {
        if (panelElement.id) {
            try {
                localStorage.setItem(getPathKey(panelElement.id), path);
            } catch (e) {
                console.warn('localStorage unavailable or quota exceeded.', e);
            }
        }
    }

    /**
     * Loads the saved directory path for a specific panel from localStorage.
     * @param {HTMLElement} panelElement - The panel DOM element.
     * @returns {string|null} The saved path, or null if not found.
     */
    function loadPanelPath(panelElement) {
        if (panelElement.id) {
            try {
                return localStorage.getItem(getPathKey(panelElement.id));
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    // --- CORE FUNCTION: renderPanel ---
    /**
     * Renders the file list content into a specific panel element.
     * CRITICAL CORRECTION: The album drop handler now calls e.stopPropagation()
     * to prevent the drop event from bubbling up to the main panel listener,
     * which stops the double execution of handleDrop.
     * @param {HTMLElement} panelElement - The target panel DOM element.
     * @param {Array<Object>|Object} contents - The array of file/folder objects or an error object.
     * @param {string} currentPath - The path of the directory being displayed.
     */
    function renderPanel(panelElement, contents, currentPath) {
        const fileList = panelElement.querySelector('.file-list');
        const pathDisplay = panelElement.querySelector('.current-path');

        fileList.innerHTML = ''; // Clear existing list

        // Update data-current-path on the panel and the visual path display
        panelElement.dataset.currentPath = currentPath;
        pathDisplay.textContent = currentPath;

        // Clear all selection markings in the DOM and in the Map when content is reloaded
        selectedItems.set(panelElement.id, []);
        updateDeleteButtonState(); // Disable delete button

        // Handle content error (e.g., access denied from API)
        if (contents.error) {
            displayMessage('error', '<?= _("Access Denied") ?>', contents.error);
            fileList.innerHTML = `<li class="error-item">Error: ${contents.error}</li>`;
            return;
        }

        // Render each item
        contents.forEach(item => {
            const li = document.createElement('li');
            li.className = 'file-item ' + item.type;
            li.dataset.path = item.path;

            // --- NEU: Content-Container und Metadaten-Div ---
            const mainContent = document.createElement('div');
            mainContent.className = 'item-main-content';

            const metadata = document.createElement('div');
            metadata.className = 'item-metadata';

            // --- 1. Dateiname und Icon/Bild-Logik ---
            let nameText = item.name;
            let iconHTML = ''; // Zum Speichern des Icons oder Bild-Elements

            if (item.type === 'album' || item.name === '..') {
                iconHTML = item.name === '..' ? '⬆️' : '📁';
                nameText = item.name === '..' ? '.. <?= _('(Parent Folder)') ?>' : item.name;
                if (item.name === '..') {
                    li.classList.add('isParent');
                    metadata.style.minWidth = '140px'; // Platz für Metadaten freihalten
                }
                mainContent.appendChild(document.createTextNode(iconHTML + ' ' + nameText));
            } else if ((item.type === 'image' || item.type === 'video') && (item.thumburl || (item.type === 'image' && item.url))) {
                // Logik für Bilder/Videos mit Thumbnail
                const imgUrl = item.thumburl || (item.type === 'image' ? item.url : null);
                if (imgUrl) {
                    const img = document.createElement('img');
                    img.src = imgUrl;
                    img.alt = item.name;
                    img.style.width = '32px';
                    img.style.height = '32px';
                    img.style.marginRight = '10px';
                    mainContent.appendChild(img);
                    mainContent.appendChild(document.createTextNode(item.name));
                }
            } else {
                // Logik für alle anderen Dateien ohne Thumbnail
                let icon = '📄';
                if (item.type === 'video')
                    icon = '🎬';
                else if (item.type === 'image')
                    icon = '🖼️';
                else if (item.type === 'audio')
                    icon = '🎵';

                const iconSpan = document.createElement('span');
                iconSpan.textContent = icon;
                iconSpan.style.marginRight = '10px';
                iconSpan.style.fontSize = '24px';

                mainContent.appendChild(iconSpan);
                mainContent.appendChild(document.createTextNode(item.name));
            }

            // --- 2. Metadaten-Anzeige ---

            // Größe (nur für Dateien)
            if (item.size) {
                const sizeSpan = document.createElement('span');
                sizeSpan.className = 'item-size';
                sizeSpan.textContent = item.size;
                metadata.appendChild(sizeSpan);
            }

            // Datum (nur für Dateien)
            if (item.date_formatted) {
                const dateSpan = document.createElement('span');
                dateSpan.className = 'item-date';
                dateSpan.textContent = item.date_formatted;
                metadata.appendChild(dateSpan);
            }


            // --- 3. Zusammenfügen ---
            li.appendChild(mainContent);
            li.appendChild(metadata);

            // Setzt die Drag-Handlers etc. auf das li-Element
            if (item.name !== '..') {
                li.setAttribute('draggable', true);
                li.addEventListener('dragstart', handleDragStart);
                li.addEventListener('dragend', handleDragEnd);
                li.addEventListener('click', handleSelection);
            }
            // Attach folder navigation handlers
            if (item.type === 'album' || item.name === '..') {
                li.addEventListener('dblclick', (e) => {
                    e.stopPropagation();
                    browseDirectory(panelElement, item.path);
                });

                // Drop target handlers remain on albums (folders) for internal moves/uploads
                if (item.type === 'album') {
                    li.addEventListener('dragenter', handleDragEnter);
                    li.addEventListener('dragleave', handleDragLeave);

                    li.addEventListener('drop', (e) => {
                        e.stopPropagation();
                        handleDrop(e);
                    });
                }
            }

            fileList.appendChild(li);
        });

        savePanelPath(panelElement, currentPath);
    }

    // --- CORE FUNCTIONS (browseDirectory) ---
    /**
     * Fetches and displays the content of a directory in the specified panel.
     * @param {HTMLElement} panelElement - The panel to render the directory in.
     * @param {string} targetPath - The path of the directory to browse.
     */
    function browseDirectory(panelElement, targetPath) {
        const uri = LOCAL_BASE_URI || panelElement.dataset.baseUri;

        fetch(uri + 'gallery/filemanager/browse/' + targetPath)
                .then(response => {
                    if (!response.ok) {
                        console.error(`HTTP error! status: ${response.status} when browsing ${targetPath}`);
                        displayMessage('error', '<?= _("Network Error") ?>', `<?= _("HTTP error! status: %s when browsing %s") ?>`.replace('%s', response.status).replace('%s', targetPath));
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const responseData = data.data;

                    if (responseData && responseData.contents) {
                        renderPanel(panelElement, responseData.contents, responseData.path);
                    } else if (data.errorMessage) {
                        displayMessage('error', '<?= _("Browsing Error") ?>', data.errorMessage);
                    } else if (responseData && responseData.error) {
                        displayMessage('error', '<?= _("Browsing Error") ?>', responseData.error);
                    } else {
                        console.error('Browsing failed: Unexpected response format.', data);
                        displayMessage('error', '<?= _("Browsing Failed") ?>', '<?= _("Unexpected server response format.") ?>');
                    }
                })
                .catch(error => {
                    console.error('Browsing failed:', error);
                    displayMessage('error', '<?= _("Browsing Failed") ?>', '<?= _("A network error occurred while loading the directory.") ?>');
                });
    }

    /**
     * Sends an AJAX request to the server to move a single item.
     * @param {string} sourcePath - The path of the item to move.
     * @param {string} targetPath - The destination path (folder).
     * @returns {Promise<Object>} A Promise that resolves with the server's response data, regardless of success.
     */
    function moveItem(sourcePath, targetPath) {
        // Collect data for the POST request
        const formData = new FormData();
        formData.append('sourcePath', sourcePath);
        formData.append('targetPath', targetPath);

        // Return the entire fetch chain as a Promise
        return fetch(LOCAL_BASE_URI + 'gallery/filemanager/move', {
            method: 'POST',
            body: formData
        })
                .then(response => response.text())
                .then(text => {
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON response for MOVE. Server Output:', text);
                        displayMessage('error', '<?= _("Move Failed") ?>', '<?= _("Server sent invalid response format. Check the browser console.") ?>');
                        // Resolve the promise with an error status to prevent Promise.all from failing
                        return {success: 0, errorMessage: 'Invalid JSON response.'};
                    }

                    // Get the message, using specific data.message or falling back to errorMessage
                    const message = data.data && data.data.message ? data.data.message : (data.errorMessage || '<?= _("Move failed due to server error.") ?>');

                    if (data.success !== 1) {
                        // Only display the message on error
                        displayMessage('error', `<?= _("Move Failed: %s") ?>`.replace('%s', basename(sourcePath)), message);
                    }

                    // Return the server data object (success or failure)
                    return data;
                })
                .catch(error => {
                    console.error('Move failed due to network or unexpected error:', error);
                    displayMessage('error', '<?= _("Move Failed") ?>', '<?= _("A network error occurred while moving an item.") ?>');
                    // Resolve the promise with an error status for Promise.all
                    return {success: 0, errorMessage: 'A network error occurred.'};
                });
    }

    // --- DOM READY INIT ---

    document.addEventListener('DOMContentLoaded', () => {

                const leftPanel = document.getElementById('leftPanel');
                const rightPanel = document.getElementById('rightPanel');

                // Initialize Maps for selection state
                selectedItems.set('leftPanel', []);
                selectedItems.set('rightPanel', []);
                lastClickedItem.set('leftPanel', null);
                lastClickedItem.set('rightPanel', null);

                // Set the left panel as active initially and add panel click handlers
                setActivePanel(leftPanel);
                leftPanel.addEventListener('click', () => setActivePanel(leftPanel));
                rightPanel.addEventListener('click', () => setActivePanel(rightPanel));


                // --- MKDIR HANDLER ---
                document.getElementById('createDirBtn').addEventListener('click', () => {
                        const newDirName = prompt('<?= _("Enter new directory name:") ?>');
                        if (!newDirName)
                                return;

                        const targetPanel = document.getElementById(activePanelId); // Uses the active panel ID
                        const targetPath = targetPanel.dataset.currentPath;

                        const formData = new FormData();
                        formData.append('newDirName', newDirName);
                        formData.append('targetPath', targetPath);

                        fetch(LOCAL_BASE_URI + 'gallery/filemanager/mkdir', {
                                method: 'POST',
                                body: formData
                        })
                                        .then(response => response.json())
                                        .then(data => {
                                                const message = data.data && data.data.message ? data.data.message : (data.errorMessage || '<?= _("Error creating directory.") ?>');

                                                if (data.success === 1) {
                                                        displayMessage('success', '<?= _("Directory Created") ?>', message);
                                                        // Reload only the active panel
                                                        browseDirectory(targetPanel, targetPath);
                                                } else {
                                                        displayMessage('error', '<?= _("Creation Failed") ?>', message);
                                                }
                                        })
                                        .catch(error => {
                                                console.error('MKDIR failed:', error);
                                                displayMessage('error', '<?= _("Creation Failed") ?>', '<?= _("A network error occurred while creating the directory.") ?>');
                                        });
                });

                // --- DELETE HANDLER ---
                document.getElementById('deleteSelectedBtn').addEventListener('click', deleteSelectedItems);

                // --- INITIAL RENDER ---

                // PHP-generated initial content (used only if no path is saved in localStorage)
                const initialLeftData = <?php echo json_encode($this->manager['leftPanel']); ?>;
                const initialRightData = <?php echo json_encode($this->manager['rightPanel']); ?>;

        // This is the default path provided by the server (e.g., '/')
                const controllerPath = '<?php echo $this->manager['currentPath']; ?>';


        // 1. LEFT Panel: Check for saved path and load content
        const savedPathLeft = loadPanelPath(leftPanel);
        const finalPathLeft = savedPathLeft || controllerPath; // Use saved path or controller default

        if (savedPathLeft) {
            // Path found in storage, fetch its current contents dynamically
            // browseDirectory handles fetching data and calling renderPanel internally.
            browseDirectory(leftPanel, finalPathLeft);
        } else {
            // No path saved, use the initial content baked into the PHP page
            renderPanel(leftPanel, initialLeftData, finalPathLeft);
        }

        // 2. RIGHT Panel: Check for saved path and load content
        const savedPathRight = loadPanelPath(rightPanel);
        const finalPathRight = savedPathRight || controllerPath; // Use saved path or controller default

        if (savedPathRight) {
            // Path found in storage, fetch its current contents dynamically
            browseDirectory(rightPanel, finalPathRight);
        } else {
            // No path saved, use the initial content baked into the PHP page
            renderPanel(rightPanel, initialRightData, finalPathRight);
        }

                // --- Attach DragOver/Drop Listeners to the panels ---
                [leftPanel, rightPanel].forEach(panel => {
                        panel.addEventListener('dragover', handleDragOver);
                        // This listener is for drops onto the panel background.
                        panel.addEventListener('drop', handleDrop);
                        panel.addEventListener('dragenter', handleDragEnter);
                        panel.addEventListener('dragleave', handleDragLeave);
                });
        });
</script>