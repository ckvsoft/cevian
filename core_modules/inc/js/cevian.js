/**
 * Frontend JavaScript module for session monitoring.
 * It periodically polls the server and handles the 401 response code
 * to trigger a client-side redirect based on the 'redirect_path' provided by the server.
 */

/* --- CONSTANT FOR FLASH MESSAGE STORAGE --- */
const FLASH_MESSAGE_KEY = "flash_message_data";

/* * Define a global constant for debug mode based on server configuration.
 * Assuming a PHP constant APP_DEBUG exists to control this flag.
 * Set to '1' or '0' for consistent string comparison in JS.
 */
const IS_DEBUG_MODE = '<?= APP_DEBUG ? "1" : "0"; ?>';

// Placeholder for BASE_URI, which must be set by PHP (e.g., <?= BASE_URI ?> in your main HTML template)
const BASE_URI = '<?= BASE_URI ?>';

/**
 * Fetches data from a URL, adds logging (if IS_DEBUG_MODE is true), and handles common errors (like 401).
 * It consumes the response stream and attempts to parse it as JSON.
 * @param {string} url The URL to fetch.
 * @param {object} options Fetch options.
 * @returns {Promise < any > } Resolves with the parsed JSON object or the raw text string.
 * @throws {Error} Rejects the promise on fetch error or session expiration (401).
 */
function fetchAndLog(url, options = {}) {
    options.headers = {
        ...(options.headers || {}),
        "X-Requested-With": "fetch"
    };
    return fetch(url, options)
            .then(async resp => {
                if (resp.status === 401) {
                    // --- START CRITICAL REDIRECT LOGIC ---
                    let data = {};
                    // The server sends the redirect_path in the JSON body, even with 401 status.
                    // We MUST read the body now to get the path before redirecting.
                    try {
                        data = await resp.json();
                    } catch (e) {
                        if (IS_DEBUG_MODE === '1') {
                            console.warn("[Session Monitor] 401 received but failed to parse JSON body for redirect path. Falling back to BASE_URI.");
                        }
                    }

                    // Use the path provided by the server, or fallback to BASE_URI
                    const targetPath = data.redirect_path || BASE_URI;

                    localStorage.setItem("logout_reason", "session_expired");
                    window.location.href = targetPath; // <--- MODIFIED TO USE DYNAMIC PATH

                    // Throw an error to stop further processing
                    return Promise.reject("Session expired");
                    // --- END CRITICAL REDIRECT LOGIC ---
                }

                // Standard logic for 200 OK responses (read text, then try to parse JSON)
                const text = await resp.text();
                if (IS_DEBUG_MODE === '1') { // Use string comparison '1'
                    console.log("Response from", url, ":", text || "<empty>");
                }

                if (!text) {
                    if (IS_DEBUG_MODE === '1') { // Use string comparison '1'
                        console.warn("Response is empty.");
                    }
                    return null;
                }

                try {
                    return JSON.parse(text);
                } catch (e) {
                    if (IS_DEBUG_MODE === '1') { // Use string comparison '1'
                        console.warn("Not a valid JSON response:", e.message);
                    }
                    // Return raw text if parsing failed
                    return text;
                }
            })
            .catch(err => {
                const errorMessage = err.message || '';
                const errorName = err.name || '';

                // **DIESE PRÜFUNG MUSS VOR DEM ALERT STEHEN**
                if (errorMessage.includes('NS_BINDING_ABORTED') ||
                        errorMessage.includes('NetworkError') ||
                        errorMessage.includes('Failed to fetch') ||
                        errorName === 'AbortError'
                        ) {
                    if (IS_DEBUG_MODE === '1') { // Use string comparison '1'
                        console.warn("Fetch silently aborted by browser navigation/action:", url);
                    }
                    return Promise.reject(new Error("Browser Aborted"));
                }

                if (IS_DEBUG_MODE === '1') { // Use string comparison '1'
                    console.error("Fatal Fetch error at", url, ":", err);
                }
                throw err;
            });
}

/**
 * Saves a notification to localStorage (a "flash message") and triggers a redirect.
 * The message will be displayed on the target page after the redirect.
 * @param {string} type Notification type ('success', 'error', 'alert', 'info').
 * @param {string} title Notification title.
 * @param {string} message Main message body.
 * @param {array} details Optional details array.
 * @param {string} targetUrl The URL to redirect to.
 * @param {object} options Additional display options.
 */
function sendMessageAndRedirect(type, title, message, details = [], targetUrl, options = {}) {
    const data = {type, title, message, details, options};
    localStorage.setItem(FLASH_MESSAGE_KEY, JSON.stringify(data));
    if (targetUrl) {
        window.location.href = targetUrl;
    } else {
        console.warn("Target URL missing for redirect.");
}
}

/**
 * Performs the periodic POST check of the Server Session.
 */
async function checkServerSessionStatus() {
    const formData = new URLSearchParams();
    formData.append('action', 'check_session_status');

    try {
        // fetchAndLog handles 401 redirection internally
        const data = await fetchAndLog(BASE_URI, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData
        });

        // Optional: If the server returns active status, you can log the time remaining
        // Note: data is only guaranteed to be an object if the server successfully responded (200 OK)
        if (IS_DEBUG_MODE === '1' && data && data.is_active) { // Use string comparison '1'
            const remaining = Math.max(0, Math.floor(data.time_remaining_seconds));
            console.log(`[Session Monitor] Active. Remaining: ${remaining}`);
        }

    } catch (error) {
        // Only log if the error wasn't the expected session expiration or browser abort
        if (error !== "Session expired" && error.message !== "Browser Aborted") {
            console.error("[Session Monitor] Failed:", error);
        }
    }
}

/**
 * Starts the periodic session check.
 */
function startSessionMonitor() {
    // Run an immediate check on load
    checkServerSessionStatus();

    // Start periodic check
    setInterval(checkServerSessionStatus, 60 * 1000);
}

/**
 * Check for pending flash messages in localStorage and display them once.
 * Removes the message data after successful retrieval/display attempt.
 */
function checkFlashMessage() {
    const storedMessage = localStorage.getItem(FLASH_MESSAGE_KEY);
    if (!storedMessage)
        return;
    try {
        const data = JSON.parse(storedMessage);
        displayMessage(data.type, data.title, data.message, data.details, data.options);
    } catch (e) {
        console.error("Error parsing flash message:", e);
    } finally {
        localStorage.removeItem(FLASH_MESSAGE_KEY);
    }
}

/**
 * Multi-tab logout synchronization via the Storage Event.
 * Shows a notification with an OK button for redirect to the base URI.
 */
window.addEventListener("storage", event => {
    if (event.key === "logout" && event.newValue !== null) {
        displayMessage(
                'alert',
                '<?= _("Logged Out") ?>',
                '<?= _("You have been logged out in another browser tab.") ?>',
                [],
                {
                    withButton: true,
                    onConfirm: () => window.location.href = BASE_URI
                }
        );
    }
});

/**
 * Broadcast a manual logout status to all other open tabs by setting the 'logout' key.
 */
function broadcastLogout()
{
    localStorage.setItem("logout", Date.now());
}


/**
 * Handle initial page load events, including displaying stored messages and navigation highlighting.
 */
document.addEventListener("DOMContentLoaded", () => {
    // 1️⃣ Show flash messages from previous JS redirect (e.g., after saving data).
    checkFlashMessage();
    startSessionMonitor();
    // 2️⃣ Show logout/session-expired message if redirected due to a failed AJAX call (401).
    const reason = localStorage.getItem("logout_reason");
    if (reason === "session_expired") {
        // This block executes AFTER an AJAX 401 redirect (session timeout).
        displayMessage(
                'alert',
                '<?= _("Session Expired") ?>',
                '<?= _("Your session has expired. Please log in again.") ?>',
                [],
                {withButton: true}
        );
        localStorage.removeItem("logout_reason");
    }

    // === Navigation active highlighting ===
    const url = location.href;
    const links = document.querySelectorAll("#primary_nav_wrap a");
    links.forEach(link => {
        if (link.href === url) {
            let topLevel = link.closest("ul")?.closest("li");
            link.parentElement.classList.add("active");
            while (topLevel && topLevel.id !== "primary_nav_wrap") {
                topLevel.classList.add("active");
                topLevel = topLevel.parentElement?.closest("li");
            }
        }
    });
    document.querySelectorAll("#primary_nav_wrap li").forEach(li => {
        if (li.querySelector("ul")) {
            li.classList.add("has-child");
        }
    });
// === Hamburger menu for mobile ===
    const hamburger = document.querySelector('.hamburger-menu');
    const menu = document.querySelector('#menu_11');
    if (hamburger && menu) {
        hamburger.addEventListener('click', () => {
            menu.classList.toggle('open');
        });
    }
});

/**
 * Displays a notification using the XNotify class.
 * options: { withButton: boolean, onConfirm: function }
 * @param {string} type Notification type ('success', 'error', 'alert', 'info').
 * @param {string} title Notification title.
 * @param {string} message Main message body.
 * @param {array|object} details Optional array or object containing extra details.
 * @param {object} options Notification display options.
 */
function displayMessage(type, title, message, details = [], options = {}) {
    const Notify = new XNotify("BottomRight");
    let finalDescription = message;
    if (Array.isArray(details) && details.length > 0) {
        finalDescription += `<br><br><strong>Details:</strong><ul><li>${details.join("</li><li>")}</li></ul>`;
    } else if (details && typeof details === "object" && Object.keys(details).length > 0) {
        finalDescription += `<br><br><strong>Details (Object):</strong><pre>${JSON.stringify(details, null, 2)}</pre>`;
    }

    const notifyOptions = {
        title,
        description: finalDescription,
        duration: 5000,
        withButton: options.withButton || false,
        onConfirm: options.onConfirm || null
    };
    switch (type) {
        case "success":
            Notify.success(notifyOptions);
            break;
        case "error":
            Notify.error(notifyOptions);
            break;
        case "alert":
            Notify.alert(notifyOptions);
            break;
        default:
            Notify.info(notifyOptions);
}
}


/**
 * Optional form change detection helpers
 * Initializes change detection on a form by storing the initial value of each element.
 * @param {HTMLFormElement} form The form element to initialize.
 */
function initChangeDetection(form) {
    Array.from(form).forEach(el => el.dataset.origValue = el.value);
}

/**
 * Checks if any input element in a form has a value different from its stored original value.
 * @param {HTMLFormElement} form The form element to check.
 * @returns {boolean} True if changes are detected, false otherwise.
 */
function formHasChanges(form) {
    return Array.from(form).some(el =>
        "origValue" in el.dataset &&
                el.dataset.origValue !== el.value
    );
}


/**
 * Shrink header on scroll
 * Adds the 'shrink' class to the main header when the page is scrolled down past 50 pixels.
 */
window.addEventListener("scroll", shrinkHeader);

function shrinkHeader()
{
    const header = document.getElementById("mainHeader");
    if (!header)
        return;
    const scrolled = window.scrollY > 50;
    header.classList.toggle("shrink", scrolled);
}