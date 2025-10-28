document.addEventListener("DOMContentLoaded", () => {

    // === Navigation aktiv markieren ===
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

    // === Hamburger Menü für Mobile ===
    const hamburger = document.querySelector('.hamburger-menu');
    const menu = document.querySelector('#menu_11');

    if (hamburger && menu) {
        hamburger.addEventListener('click', () => {
            menu.classList.toggle('open');
        });
    }
});

// === Notify / Change Detection / Shrink Header ===
function displayMessage(type, title, message, details = []) {
    const Notify = new XNotify("BottomRight");
    let finalDescription = message;

    if (Array.isArray(details) && details.length > 0) {
        finalDescription += `
            <br><br>
            <strong><?= _('Details:') ?></strong>
            <ul><li>${details.join("</li><li>")}</li></ul>
        `;
    } else if (details && typeof details === "object" && Object.keys(details).length > 0) {
        finalDescription += `
            <br><br>
            <strong><?= _('Details (Object):') ?></strong>
            <pre>${JSON.stringify(details, null, 2)}</pre>
        `;
    }

    const notifyOptions = {
        title,
        description: finalDescription,
        duration: 5000
    };

    switch (type) {
        case "success": Notify.success(notifyOptions); break;
        case "error": Notify.error(notifyOptions); break;
        default: Notify.info(notifyOptions);
    }
}

function initChangeDetection(form) {
    Array.from(form).forEach(el => el.dataset.origValue = el.value);
}

function formHasChanges(form) {
    return Array.from(form).some(el =>
        "origValue" in el.dataset &&
        el.dataset.origValue !== el.value
    );
}

window.addEventListener("scroll", shrinkHeader);

function shrinkHeader() {
    const header = document.getElementById("mainHeader");
    if (!header) return;
    const scrolled = window.scrollY > 50;
    header.classList.toggle("shrink", scrolled);
}
