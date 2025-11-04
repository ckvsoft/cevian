const LOCAL_BASE_URI = '<?php echo BASE_URI; ?>';

// --- GLOBAL VARIABLES ---
let draggedItem = null;
let activePanelId = 'leftPanel';
let selectedItems = new Map();
let lastClickedItem = new Map();
let deleteButton = null;

let isMoving = false;


function updateDeleteButtonState() {
    if (!deleteButton)
        return;
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
    document.querySelectorAll('.file-panel').forEach(p => p.classList.remove('active-panel'));
    panelElement.classList.add('active-panel');
    activePanelId = panelElement.id;
}

/**
 * Handles file item selection (single click, Ctrl/Cmd click, Shift click).
 * @param {MouseEvent} e - The click event object.
 */
function handleSelection(e) {
    e.stopPropagation();

    const panelElement = e.target.closest('.file-panel');
    if (!panelElement)
        return;

    setActivePanel(panelElement);

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
        currentSelection = [...selectedItems.get(panelId)];
        const items = Array.from(panelElement.querySelectorAll('.file-item:not(.isParent)'));

        const lastIndex = items.findIndex(i => i.dataset.path === lastPath);
        const currentIndex = items.findIndex(i => i.dataset.path === path);

        if (lastIndex !== -1 && currentIndex !== -1) {
            const [start, end] = lastIndex < currentIndex ? [lastIndex, currentIndex] : [currentIndex, lastIndex];

            if (currentSelection.length > 0) {
                items.forEach(item => {
                    const itemPath = item.dataset.path;
                    const index = currentSelection.indexOf(itemPath);
                    if (index > -1) {
                        currentSelection.splice(index, 1);
                        item.classList.remove('selected');
                    }
                });
                currentSelection = [];
            }

            for (let i = start; i <= end; i++) {
                const itemPath = items[i].dataset.path;
                if (!currentSelection.includes(itemPath)) {
                    currentSelection.push(itemPath);
                    items[i].classList.add('selected');
                }
            }
        }

        items.forEach(item => {
            const itemPath = item.dataset.path;
            if (currentSelection.includes(itemPath)) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });

    } else if (e.ctrlKey || e.metaKey) {
        if (isSelected) {
            currentSelection = currentSelection.filter(p => p !== path);
            itemElement.classList.remove('selected');
        } else {
            currentSelection.push(path);
            itemElement.classList.add('selected');
        }
        lastClickedItem.set(panelId, path);
    } else {
        panelElement.querySelectorAll('.file-item.selected').forEach(item => {
            if (item !== itemElement) {
                item.classList.remove('selected');
            }
        });

        if (isSelected && currentSelection.length === 1) {
            currentSelection = [];
            itemElement.classList.remove('selected');
            lastClickedItem.set(panelId, null);
        } else {
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

    let currentSelection = selectedItems.get(panelId) || [];
    let pathsToDrag;

    if (currentSelection.includes(path)) {
        pathsToDrag = currentSelection;
    } else {
        panelElement.querySelectorAll('.file-item.selected').forEach(i => i.classList.remove('selected'));
        itemElement.classList.add('selected');
        pathsToDrag = [path];
        selectedItems.set(panelId, pathsToDrag);
        updateDeleteButtonState();
    }

    e.dataTransfer.setData('application/json/paths', JSON.stringify(pathsToDrag));

    e.dataTransfer.setData('text/plain', pathsToDrag.join(','));

    draggedItem = pathsToDrag;
    e.dataTransfer.dropEffect = 'move';
}

/**
 * Handles the end of a drag operation.
 * @param {DragEvent} e - The drag event object.
 */
function handleDragEnd(e) {
    draggedItem = null;
}

/**
 * Handles when a dragged item enters a drop target.
 * @param {DragEvent} e - The drag event object.
 */
function handleDragEnter(e) {
    e.preventDefault();
    if (e.target.closest('.file-item.album') || e.target.closest('.file-panel')) {
        e.target.classList.add('drag-over');
    }
}

/**
 * Handles when a dragged item leaves a drop target.
 * @param {DragEvent} e - The drag event object.
 */
function handleDragLeave(e) {
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

    if (isMoving) {
        console.warn('Move already in progress. Ignoring drop event.');
        return;
    }

    let dropTargetElement = e.target;
    dropTargetElement = e.target.closest('.file-item.album') || e.target.closest('.file-panel');

    if (!dropTargetElement) {
        return;
    }

    let targetPath;
    let targetPanelId;

    if (dropTargetElement.classList.contains('file-panel')) {
        targetPath = dropTargetElement.dataset.currentPath;
        targetPanelId = dropTargetElement.id; // Get the panel ID
    } else if (dropTargetElement.classList.contains('album')) {
        targetPath = dropTargetElement.dataset.path;
        targetPanelId = dropTargetElement.closest('.file-panel').id;
    } else {
        return;
    }

    if (targetPath === '..') {
        return;
    }

    if (e.dataTransfer.files.length > 0) {
        const files = Array.from(e.dataTransfer.files);
        uploadFiles(files, targetPath);
        return;
    }

    let rawSourcePaths = [];

    const jsonPaths = e.dataTransfer.getData('application/json/paths');
    if (jsonPaths) {
        try {
            rawSourcePaths = JSON.parse(jsonPaths);
        } catch (error) {
        }
    }

    const textPath = e.dataTransfer.getData('text/plain');
    if (textPath) {
        if (textPath.includes(',')) {
            rawSourcePaths = rawSourcePaths.concat(textPath.split(',').map(p => p.trim()));
        } else {
            rawSourcePaths.push(textPath);
        }
    }

    const uniqueSourcePaths = [...new Set(rawSourcePaths.filter(path => path))];

    if (uniqueSourcePaths.length === 0) {
        return;
    }

    isMoving = true;
    let movePromises = [];
    let moveCount = 0;

    uniqueSourcePaths.forEach(sourcePath => {
        const sourceParentPath = sourcePath.substring(0, sourcePath.lastIndexOf('/'));

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

    if (moveCount > 0) {
        Promise.all(movePromises)
                .finally(() => {
                    isMoving = false;
                })
                .then(results => {
                    const targetPanel = document.getElementById(targetPanelId);

                    if (targetPanel) {
                        const currentTargetPath = targetPanel.dataset.currentPath;
                        browseDirectory(targetPanel, currentTargetPath);

                        const otherPanelId = (targetPanelId === 'leftPanel') ? 'rightPanel' : 'leftPanel';
                        const otherPanel = document.getElementById(otherPanelId);

                        if (otherPanel && uniqueSourcePaths.length > 0) {
                            const otherPanelPath = otherPanel.dataset.currentPath;
                            const sourceParentPath = uniqueSourcePaths[0].substring(0, uniqueSourcePaths[0].lastIndexOf('/'));

                            if (otherPanelPath === sourceParentPath) {
                                browseDirectory(otherPanel, otherPanelPath);
                            }
                        }
                    } else {
                        browseDirectory(document.getElementById('leftPanel'), document.getElementById('leftPanel').dataset.currentPath);
                        browseDirectory(document.getElementById('rightPanel'), document.getElementById('rightPanel').dataset.currentPath);
                    }

                    let successMoves = results.filter(r => r && r.success === 1).length;
                    let failedMoves = moveCount - successMoves;

                    if (successMoves > 0 && failedMoves === 0) {
                    } else if (failedMoves > 0) {
                        displayMessage('error', '<?= _("Move Complete with Errors") ?>', `<?= _("Failed to move %d of %d item(s).") ?>`.replace('%d', failedMoves).replace('%d', moveCount));
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
/**
 * Handles the upload of files dropped from the local system, showing a loading indicator
 * and updating the progress with the "File X of Y" status.
 * @param {File[]} files - An array of File objects to upload.
 * @param {string} targetPath - The server path to upload the files to.
 */
function uploadFiles(files, targetPath) {
    const uri = LOCAL_BASE_URI + 'gallery/filemanager/upload';
    const progressOverlay = document.getElementById('uploadProgressOverlay');

    // Retrieve the status text element for "File X of Y" display
    const statusTextElement = document.getElementById('uploadStatusText');

    let successCount = 0;
    let failCount = 0;
    let totalFiles = files.length;
    let processedFiles = 0;
    const panelToReload = document.querySelector(`.file-panel[data-current-path="${targetPath}"]`);

    /**
     * Updates the status text display (e.g., "Processing file 5 of 141...")
     */
    function updateProgressDisplay() {
        if (statusTextElement) {
            statusTextElement.textContent = `<?= _("Processing file") ?> ${processedFiles} <?= _("of") ?> ${totalFiles} ...`;
        }
    }

    // Show progress overlay and position it correctly below a fixed header
    const headerElement = document.querySelector('.fixed-header');
    const headerHeight = headerElement && window.getComputedStyle(headerElement).position === 'fixed' ? headerElement.offsetHeight : 0;
    progressOverlay.style.top = headerHeight + 'px';
    progressOverlay.style.display = 'flex';

    // Set initial status display
    updateProgressDisplay();

    files.forEach(file => {
        const formData = new FormData();
        formData.append('targetPath', targetPath);
        formData.append('file_name', file.name);
        formData.append('image', file);

        fetchAndLog(uri, {method: 'POST', body: formData})
                .then(data => {
                    if (data.success === 1) {
                        successCount++;
                    } else {
                        failCount++;
                        // Display error message for the specific file
                        const message = data.errorMessage && data.errorMessage.message ? data.errorMessage.message : 'Unknown upload error.';
                        displayMessage('error', `<?= _("Upload Failed: %s") ?>`.replace('%s', file.name), message);
                    }
                })
                .catch(error => {
                    failCount++;
                    // Log and display error message for network/unexpected errors
                    console.error('Upload failed for %s:'.replace('%s', file.name), error);
                    displayMessage('error', `<?= _("Upload Failed: %s") ?>`.replace('%s', file.name), '<?= _("Network or unexpected error occurred.") ?>');
                })
                .finally(() => {
                    // Increment the counter for processed files
                    processedFiles++;

                    // Update the "File X of Y" display
                    updateProgressDisplay();

                    // When all files are done
                    if (processedFiles === totalFiles) {
                        progressOverlay.style.display = 'none';

                        // Final success handling
                        if (successCount > 0) {
                            displayMessage('success', '<?= _("Upload Complete") ?>', `<?= _("%d file(s) uploaded successfully.") ?>`.replace('%d', successCount));

                            // Reload the directory panel to show new files
                            if (panelToReload)
                                browseDirectory(panelToReload, targetPath);
                        }

                        // Final failure handling (only show if ALL failed)
                        if (failCount > 0 && successCount === 0) {
                            displayMessage('error', '<?= _("Upload Failed") ?>', '<?= _("All uploads failed. Check individual error messages.") ?>');
                        }

                        // Clear the final status text
                        if (statusTextElement)
                            statusTextElement.textContent = '';
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
    const confirmation = confirm('<?= _("Are you sure you want to delete the following %d item(s) from \\u0022%s\\u0022?%s") ?>'
            .replace('%d', pathsToDelete.length)
            .replace('%s', currentPath)
            .replace('%s', itemsString));

    if (!confirmation)
        return;

    const bodyData = {paths: pathsToDelete};

    fetchAndLog(LOCAL_BASE_URI + 'gallery/filemanager/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(bodyData)
    })
            .then(data => {
                let message = '<?= _("Deletion failed due to server error.") ?>';
                let details = null;

                if (data.success === 1) {
                    message = data.data && data.data.message ? data.data.message : '<?= _("Deletion Complete.") ?>';
                    displayMessage('success', '<?= _("Deletion Complete") ?>', message);
                } else {
                    if (data.data && data.data.message) {
                        message = data.data.message;
                        details = data.data.details;
                    } else if (data.errorMessage) {
                        message = typeof data.errorMessage === 'object' ? data.errorMessage.message || '<?= _("Unknown error object received.") ?>' : data.errorMessage;
                    }
                    displayMessage('error', '<?= _("Deletion Failed or Completed with Errors") ?>', message, details);
                }

                // Reload panel & reset selection
                browseDirectory(targetPanel, currentPath);
                selectedItems.set(panelId, []);
                updateDeleteButtonState();
            })
            .catch(error => {
                console.error('Delete failed:', error);
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

    panelElement.dataset.currentPath = currentPath;
    pathDisplay.textContent = currentPath;

    selectedItems.set(panelElement.id, []);
    updateDeleteButtonState(); // Disable delete button

    if (contents.error) {
        if (!panelElement.dataset.reloaded) {
            panelElement.dataset.reloaded = '1';

            try {
                localStorage.removeItem(getPathKey(panelElement.id));
            } catch (e) {
                console.warn('Could not remove localStorage item for panel', e);
            }

            console.log('Retrying panel with fallback path...');
            browseDirectory(panelElement, '');
            return;
        } else {
            displayMessage('error', '<?= _("Access Denied") ?>', contents.error);
            fileList.innerHTML = `<li class="error-item">Error: ${contents.error}</li>`;
            console.warn('Panel still failed after fallback reload');
            return;
        }
    }

    contents.forEach(item => {
        const li = document.createElement('li');
        li.className = 'file-item ' + item.type;
        li.dataset.path = item.path;

        // --- NEU: Content-Container und Metadaten-Div ---
        const mainContent = document.createElement('div');
        mainContent.className = 'item-main-content';

        const metadata = document.createElement('div');
        metadata.className = 'item-metadata';

        let nameText = item.name;
        let iconHTML = '';

        if (item.type === 'album' || item.name === '..') {
            iconHTML = item.name === '..' ? '⬆️' : '📁';
            nameText = item.name === '..' ? '.. <?= _("(Parent Folder)") ?>' : item.name;
            if (item.name === '..') {
                li.classList.add('isParent');
                metadata.style.minWidth = '140px';
            }
            mainContent.appendChild(document.createTextNode(iconHTML + ' ' + nameText));
        } else if ((item.type === 'image' || item.type === 'video') && (item.thumburl || (item.type === 'image' && item.url))) {
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

        if (item.size) {
            const sizeSpan = document.createElement('span');
            sizeSpan.className = 'item-size';
            sizeSpan.textContent = item.size;
            metadata.appendChild(sizeSpan);
        }

        if (item.date_formatted) {
            const dateSpan = document.createElement('span');
            dateSpan.className = 'item-date';
            dateSpan.textContent = item.date_formatted;
            metadata.appendChild(dateSpan);
        }

        li.appendChild(mainContent);
        li.appendChild(metadata);

        if (item.name !== '..') {
            li.setAttribute('draggable', true);
            li.addEventListener('dragstart', handleDragStart);
            li.addEventListener('dragend', handleDragEnd);
            li.addEventListener('click', handleSelection);
        }
        if (item.type === 'album' || item.name === '..') {
            li.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                browseDirectory(panelElement, item.path);
            });

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

    fetchAndLog(uri + 'gallery/filemanager/browse/' + targetPath)
            .then(data => {
                if (data.success !== 1) {
                    displayMessage('error', '<?= _("Browsing Error") ?>', data.errorMessage || '<?= _("Unknown error.") ?>');
                    return;
                }

                const responseData = data.data;
                if (responseData && responseData.contents) {
                    renderPanel(panelElement, responseData.contents, responseData.path);
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
    const formData = new FormData();
    formData.append('sourcePath', sourcePath);
    formData.append('targetPath', targetPath);

    return fetchAndLog(LOCAL_BASE_URI + 'gallery/filemanager/move', {method: 'POST', body: formData})
            .then(data => {
                const message = data.data && data.data.message ? data.data.message : (data.errorMessage || '<?= _("Move failed due to server error.") ?>');

                if (data.success !== 1) {
                    displayMessage('error', `<?= _("Move Failed: %s") ?>`.replace('%s', basename(sourcePath)), message);
                }

                return data;
            })
            .catch(error => {
                console.error('Move failed due to network or unexpected error:', error);
                displayMessage('error', '<?= _("Move Failed") ?>', '<?= _("A network error occurred while moving an item.") ?>');
                return {success: 0, errorMessage: 'A network error occurred.'};
            });
}

// --- DOM READY INIT ---

document.addEventListener('DOMContentLoaded', () => {

            const leftPanel = document.getElementById('leftPanel');
            const rightPanel = document.getElementById('rightPanel');

    deleteButton = document.getElementById("deleteSelectedBtn");

    if (deleteButton) {
        updateDeleteButtonState(); // Initial state
        deleteButton.addEventListener("click", deleteSelectedItems);
    } else {
        console.error("Button mit ID 'deleteSelectedBtn' nicht gefunden. Funktionalität eingeschränkt.");
    }
            // Initialize Maps for selection state
            selectedItems.set('leftPanel', []);
            selectedItems.set('rightPanel', []);
            lastClickedItem.set('leftPanel', null);
            lastClickedItem.set('rightPanel', null);

            setActivePanel(leftPanel);
            leftPanel.addEventListener('click', () => setActivePanel(leftPanel));
            rightPanel.addEventListener('click', () => setActivePanel(rightPanel));


            // --- MKDIR HANDLER ---
    document.getElementById('createDirBtn').addEventListener('click', () => {
        const newDirName = prompt('<?= _("Enter new directory name:") ?>');
        if (!newDirName)
            return;

        const targetPanel = document.getElementById(activePanelId);
        const targetPath = targetPanel.dataset.currentPath;

        const formData = new FormData();
        formData.append('newDirName', newDirName);
        formData.append('targetPath', targetPath);

        fetchAndLog(LOCAL_BASE_URI + 'gallery/filemanager/mkdir', {method: 'POST', body: formData})
                .then(data => {
                    const message = data.data && data.data.message ? data.data.message : (data.errorMessage || '<?= _("Error creating directory.") ?>');

                    if (data.success === 1) {
                        displayMessage('success', '<?= _("Directory Created") ?>', message);
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
            const initialLeftData = JSON.parse('<?php echo json_encode($data["manager"]["leftPanel"] ?? []); ?>');
            const initialRightData = JSON.parse('<?php echo json_encode($data["manager"]["leftPanel"] ?? []); ?>');

            const controllerPath = "<?php echo $data['manager']['currentPath']; ?>";


    const savedPathLeft = loadPanelPath(leftPanel);
    const finalPathLeft = savedPathLeft || controllerPath;

    if (savedPathLeft) {
        browseDirectory(leftPanel, finalPathLeft);
    } else {
        renderPanel(leftPanel, initialLeftData, finalPathLeft);
    }

    const savedPathRight = loadPanelPath(rightPanel);
    const finalPathRight = savedPathRight || controllerPath; // Use saved path or controller default

    if (savedPathRight) {
        browseDirectory(rightPanel, finalPathRight);
    } else {
        renderPanel(rightPanel, initialRightData, finalPathRight);
    }
            [leftPanel, rightPanel].forEach(panel => {
                    panel.addEventListener('dragover', handleDragOver);
                    panel.addEventListener('drop', handleDrop);
                    panel.addEventListener('dragenter', handleDragEnter);
                    panel.addEventListener('dragleave', handleDragLeave);
            });
    });
