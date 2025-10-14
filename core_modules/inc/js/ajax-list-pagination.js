document.addEventListener('DOMContentLoaded', () => {

    function startProgressPolling(progressId, progressUrl, submitButton, originalText) {
        const container = submitButton; // The button element itself
        const text = document.getElementById('progress-text-' + progressId);

        if (text) {
            text.textContent = '[Processing...]';
        }

        if (container) {
            container.disabled = true;
        }

        const interval = setInterval(async () => {
            try {
                const resp = await fetch(BASE_URI + progressUrl + progressId);
                const data = await resp.json();

                if (data && data.data.percent !== undefined) {
                    const percentage = parseInt(data.data.percent, 10);

                    if (text) {
                        const statusText = (percentage < 100)
                                ? `Processing: ${percentage}%`
                                : `Complete: 100% ✅`;
                        text.textContent = statusText;
                    }
                    if (percentage >= 100) {
                        clearInterval(interval);

                        if (container) {
                            container.disabled = false;
                        }

                        if (text) {
                            text.textContent = `[Complete]`;
                        }
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
                clearInterval(interval);

                if (container) {
                    container.disabled = false;
                }
                if (text) {
                    text.textContent = 'Error!';
                }
            }
        }, 200);

        return interval;
    }

    function stopProgressPolling(pollingInterval) {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    }

    // --- CORE LOGIC ---
    // Handles AJAX submission for a single form.
    async function submitFormLogic(form) {
        const url = form.getAttribute('action');
        const container = document.querySelector(`[data-form="${form.id}"]`);
        let sendAsJson = false;

        if (container) {
            sendAsJson = container.dataset.json === '1';
        }

        let options = {method: 'POST'};

        if (sendAsJson) {
            const formData = Object.fromEntries(new FormData(form).entries());
            options.body = JSON.stringify(formData);
            options.headers = {'Content-Type': 'application/json'};
        } else {
            options.body = new FormData(form);
        }

        const resp = await fetch(url, options);
        const data = await resp.json();

        if (data.success !== 1) {
            const errorMsg = Object.entries(data.errorMessage || {})
                    .map(([k, v]) => `${k} ${v}`)
                    .join('<br />');
            throw new Error(errorMsg || 'Unknown error');
        }
        return data;
    }

    // ----------------------------
    // 1. Setup AJAX Form (single submits - WITH PROGRESS EXTENSION)
    // ----------------------------
    function setupAjaxForm(formId, listUrl, listContainerId) {
        const form = document.getElementById(formId);
        if (!form)
            return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const progressId = form.dataset.progressId;
            const progressUrl = form.dataset.progressUrl;
            const isLongRunningJob = !!progressId && !!progressUrl;

            const submitButton = form.querySelector('button[type="submit"]');
            let pollingInterval = null;
            let originalText = submitButton ? submitButton.textContent : null;

            try {
                // Start polling if it's a long-running job
                if (isLongRunningJob) {
                    pollingInterval = startProgressPolling(progressId, progressUrl, submitButton, originalText);
                }

                await submitFormLogic(form);

                // Stop polling and hide UI
                stopProgressPolling(pollingInterval);

                const redirectUrl = form.dataset.redirect;
                if (redirectUrl) {
                    displayMessage("success", "Complete", "Operation successful. Redirecting...", true);
                    setTimeout(() => window.location.href = BASE_URI + redirectUrl, 1500);
                } else {
                    form.reset();
                    if (listUrl && listContainerId) {
                        await loadList(listUrl, listContainerId);
                    }
                }
            } catch (error) {
                // Stop polling on error and restore the button
                stopProgressPolling(pollingInterval);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }

                const statusEl = document.getElementById('status');
                if (statusEl) {
                    statusEl.innerHTML = error.message;
                    statusEl.style.display = 'block';
                }
                console.error('Error submitting the form:', error);
            }
        });
    }

    // ----------------------------
    // 2. Load list via AJAX
    // ----------------------------
    async function loadList(url, containerId) {
        if (!url || !containerId)
            return;
        // ... (Unchanged loadList function)
        try {
            const resp = await fetch(url);
            const html = await resp.text();
            const container = document.getElementById(containerId);
            if (!container)
                return;

            container.innerHTML = html;
            setupPagination(container);
        } catch (err) {
            console.error('Error loading the list:', err);
        }
    }

    // ----------------------------
    // 3. Setup Pagination (table or generic list)
    // ----------------------------
    function setupPagination(container) {
        if (!container)
            return;

        const table = container.querySelector('table');
        
        let paginatedContent, items = [], totalItems = 0;

        if (table) {
            // Case 1: Table - paginatedContent is the table, items are all rows EXCEPT the header (row 0).
            paginatedContent = table;
            // Select all rows from index 1 onwards (data rows)
            items = Array.from(table.rows).slice(1);
            totalItems = items.length;
        } else {
            // Case 2: Generic List (Cards, Grid, or items directly inside .paginated)
            
            // Use the tightest known wrapper for the items: .list-cards, .image-grid, or the main container.
            paginatedContent = container.querySelector('.list-cards') || 
                               container.querySelector('.image-grid') || 
                               container;

            // Select all direct element children, filtering out scripts and the pagination element itself.
            items = Array.from(paginatedContent.children).filter(el => 
                     el.nodeType === 1 && 
                     el.tagName !== 'SCRIPT' && 
                     !el.classList.contains('pagination')
            );
            
            totalItems = items.length;
        }


        // Exit if no items found
        if (totalItems <= 0)
            return;

        const rowsPerPage = parseInt(container.dataset.perPage, 10) || 15;
        let currentPage = 1;
        const totalPages = Math.ceil(totalItems / rowsPerPage);

        // Remove old pagination
        const oldPagination = container.querySelector('.pagination');
        if (oldPagination)
            oldPagination.remove();

        // ----------------------------------------------------
        // START: Button creation is always done if items exist
        // ----------------------------------------------------

        // Create pagination elements
        const pagination = document.createElement('div');
        pagination.className = 'pagination';
        pagination.style.marginTop = '10px';
        pagination.style.marginBottom = '20px';
        pagination.style.textAlign = 'left';

        const prevButton = document.createElement('button');
        prevButton.textContent = 'Previous';
        // Applying the new CSS classes for styling
        prevButton.className = 'button small-action pagination-nav'; 
        prevButton.style.marginRight = '8px';

        const nextButton = document.createElement('button');
        nextButton.textContent = 'Next';
        // Applying the new CSS classes for styling
        nextButton.className = 'button small-action pagination-nav';

        const pageStatus = document.createElement('span');
        pageStatus.style.marginLeft = '10px';
        pageStatus.style.fontWeight = 'bold';

        prevButton.onclick = () => showPage(currentPage - 1);
        nextButton.onclick = () => showPage(currentPage + 1);

        pagination.appendChild(prevButton);
        pagination.appendChild(nextButton);
        pagination.appendChild(pageStatus);
        paginatedContent.insertAdjacentElement('afterend', pagination);
        
        // Hide the pagination block completely if only one page exists
        /*
        if (totalPages <= 1) {
            pagination.style.display = 'none';
            // Also ensure all items are visible if pagination is hidden (they should be by default)
            items.forEach(item => item.style.display = '');
            return;
        }
         * 
         */
        // ----------------------------------------------------
        // END: Button creation
        // ----------------------------------------------------

        function showPage(page) {
            if (page < 1)
                page = 1;
            if (page > totalPages)
                page = totalPages;
            currentPage = page;

            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            // FIX: Use explicit 'table-row' display for tables for robust visibility, 
            // otherwise use '' to respect CSS layouts (flex/grid/block).
            const displayValue = table ? 'table-row' : '';

            // Unified display logic for both tables and generic lists:
            // Hides/shows the items (which are table rows or divs/cards).
            items.forEach((item, index) => {
                item.style.display = (index >= start && index < end) ? displayValue : 'none';
            });

            pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
            
            // Buttons are disabled, but remain visible
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === totalPages;
        }

        showPage(1);
    }

    // ----------------------------
    // 4. Sequential save for multiple forms
    // ----------------------------
    async function saveSequentially(formIds, triggerButton) {
        // ... (Unchanged saveSequentially function)
        const forms = formIds.map(id => document.getElementById(id)).filter(f => f);
        if (forms.length !== formIds.length) {
            console.error('Not all forms found for sequential save:', formIds);
            return;
        }

        triggerButton.disabled = true;
        const originalText = triggerButton.textContent;
        triggerButton.textContent = 'Saving...';
        displayMessage('info', 'Save Process', 'Saving sequence initiated...');

        try {
            for (let i = 0; i < forms.length; i++) {
                const form = forms[i];
                await submitFormLogic(form);
                displayMessage('success', `Step ${i + 1}/${forms.length}`, `Data for '${form.id}' saved successfully.`);
            }

            const redirectUrl = forms[0].dataset.redirect;
            if (redirectUrl) {
                displayMessage('success', 'Complete', 'All changes saved! Redirecting...');
                setTimeout(() => window.location.href = BASE_URI + redirectUrl, 1500);
            } else {
                displayMessage('success', 'Complete', 'All changes saved!');
                document.querySelectorAll('.paginated').forEach(c => setupPagination(c));
            }
        } catch (error) {
            console.error('Save failed:', error);
            displayMessage('error', 'Save Failed!', `Process aborted: ${error.message}`);
        } finally {
            triggerButton.disabled = false;
            if (triggerButton.textContent === 'Saving...')
                triggerButton.textContent = originalText;
        }
    }

    // ----------------------------
    // 5. Initialize sequential save buttons
    // ----------------------------
    function initSequentialSave() {
        // ... (Unchanged initSequentialSave function)
        document.querySelectorAll('[data-forms-to-save]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const formIds = button.dataset.formsToSave.split(',').map(id => id.trim());
                saveSequentially(formIds, button);
            });
        });
    }

    // ----------------------------
    // 6. Global initialization
    // ----------------------------

    // Initialize AJAX forms
    document.querySelectorAll('[data-form]').forEach(container => {
        const formId = container.dataset.form;
        const listUrl = container.dataset.url;
        const containerId = container.id;
        const listContainer = listUrl ? document.querySelector(`[data-list="${listUrl}"]`) : null;
        const listContainerId = listContainer ? listContainer.id : null;

        if (document.getElementById(formId)) {
            setupAjaxForm(formId, listUrl, listContainerId);
        }
    });

    // Initialize AJAX-loaded lists
    document.querySelectorAll('[data-list]').forEach(container => {
        const listUrl = container.dataset.list;
        const containerId = container.id;
        loadList(listUrl, containerId);
    });

    initSequentialSave();

    // Initialize pagination for static containers
    document.querySelectorAll('.paginated').forEach(container => {
        setupPagination(container);
    });
});
