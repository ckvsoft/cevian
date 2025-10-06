// gallery.js
document.addEventListener('DOMContentLoaded', () => {

    // --- CORE LOGIC ---
    // Handles AJAX submission for a single form.
    async function submitFormLogic(form) {
        const url = form.getAttribute('action');
        const container = document.querySelector(`[data-form="${form.id}"]`);
        let sendAsJson = false;

        if (container) {
            sendAsJson = container.dataset.json === '1';
        }

        let options = { method: 'POST' };

        if (sendAsJson) {
            const formData = Object.fromEntries(new FormData(form).entries());
            options.body = JSON.stringify(formData);
            options.headers = { 'Content-Type': 'application/json' };
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
    // 1. Setup AJAX Form (single submits)
    // ----------------------------
    function setupAjaxForm(formId, listUrl, listContainerId) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await submitFormLogic(form);

                const redirectUrl = form.dataset.redirect;
                if (redirectUrl) {
                    displayMessage("success", "Edit", "Modification was successful");
                    setTimeout(() => window.location.href = BASE_URI + redirectUrl, 2000);
                } else {
                    form.reset();
                    if (listUrl && listContainerId) {
                        await loadList(listUrl, listContainerId);
                    }
                }
            } catch (error) {
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
        if (!url || !containerId) return;

        try {
            const resp = await fetch(url);
            const html = await resp.text();
            const container = document.getElementById(containerId);
            if (!container) return;

            container.innerHTML = html;
            setupPagination(container);
        } catch (err) {
            console.error('Error loading the list:', err);
        }
    }

    // ----------------------------
    // 3. Setup Pagination (table or grid)
    // ----------------------------
    function setupPagination(container) {
        if (!container) return;

        const table = container.querySelector('table');
        const imageGrid = container.querySelector('.image-grid');

        let paginatedContent, items, totalItems = 0, isTable = false;

        if (table) {
            paginatedContent = table;
            isTable = true;
            totalItems = table.rows.length - 1;
        } else if (imageGrid) {
            paginatedContent = imageGrid;
            isTable = false;
            items = Array.from(imageGrid.children);
            totalItems = items.length;
        } else {
            return; // Nothing to paginate
        }

        if (totalItems <= 0) return;

        const rowsPerPage = parseInt(container.dataset.perPage, 10) || 15;
        let currentPage = 1;
        const totalPages = Math.ceil(totalItems / rowsPerPage);

        // Remove old pagination
        const oldPagination = container.querySelector('.pagination');
        if (oldPagination) oldPagination.remove();
        if (totalPages <= 1) return;

        // Create pagination elements
        const pagination = document.createElement('div');
        pagination.className = 'pagination';
        pagination.style.marginTop = '10px';
        pagination.style.textAlign = 'left';

        const prevButton = document.createElement('button');
        prevButton.textContent = 'Previous';
        prevButton.style.minWidth = '100px';
        prevButton.style.padding = '6px 12px';
        prevButton.style.marginRight = '8px';

        const nextButton = document.createElement('button');
        nextButton.textContent = 'Next';
        nextButton.style.minWidth = '100px';
        nextButton.style.padding = '6px 12px';

        const pageStatus = document.createElement('span');
        pageStatus.style.marginLeft = '10px';
        pageStatus.style.fontWeight = 'bold';

        prevButton.onclick = () => showPage(currentPage - 1);
        nextButton.onclick = () => showPage(currentPage + 1);

        pagination.appendChild(prevButton);
        pagination.appendChild(nextButton);
        pagination.appendChild(pageStatus);
        paginatedContent.insertAdjacentElement('afterend', pagination);

        function showPage(page) {
            if (page < 1) page = 1;
            if (page > totalPages) page = totalPages;
            currentPage = page;

            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            if (isTable) {
                for (let i = 0; i < table.rows.length; i++) {
                    const startRow = start + 1;
                    const endRow = end + 1;
                    table.rows[i].style.display = (i === 0 || (i >= startRow && i < endRow)) ? '' : 'none';
                }
            } else {
                items.forEach((item, index) => {
                    item.style.display = (index >= start && index < end) ? '' : 'none';
                });
            }

            pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === totalPages;
        }

        showPage(1);
    }

    // ----------------------------
    // 4. Sequential save for multiple forms
    // ----------------------------
    async function saveSequentially(formIds, triggerButton) {
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
            if (triggerButton.textContent === 'Saving...') triggerButton.textContent = originalText;
        }
    }

    // ----------------------------
    // 5. Initialize sequential save buttons
    // ----------------------------
    function initSequentialSave() {
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
