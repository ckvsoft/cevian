document.addEventListener("DOMContentLoaded", () => {
    /* --- CSS3 border-radius --- */
    document.querySelectorAll(".entry, #tipinfo, ol.commentlist, li.comment, #respond, #commentform textarea, #commentform input, #contactform, #contactform input, #contactform textarea")
            .forEach(el => el.style.borderRadius = "10px");

    // Greift auf alle Inputs und Textareas in jedem Widget (inkl. neuer Forms)
    document.querySelectorAll(".entry input, .entry textarea, .entry button")
            .forEach(el => el.style.borderRadius = "10px");


    /* --- Menu Hover --- */
    document.querySelectorAll(".menu a").forEach(link => {
        link.style.transition = "padding-left 0.3s";
        link.addEventListener("mouseenter", () => link.style.paddingLeft = "15px");
        link.addEventListener("mouseleave", () => link.style.paddingLeft = "0px");
    });

    /* --- Sidebar Widget Hover --- */
    document.querySelectorAll("#sidebar li.widget a").forEach(link => {
        link.style.transition = "padding-left 0.3s";
        link.addEventListener("mouseenter", () => link.style.paddingLeft = "20px");
        link.addEventListener("mouseleave", () => link.style.paddingLeft = "10px");
    });

    /* --- Tooltip --- */
    document.querySelectorAll("div.tool a").forEach(el => el.classList.add("tip"));
    document.querySelectorAll(".tip").forEach(el => {
        el.addEventListener("mouseenter", () => {
            const p = document.createElement("p");
            p.className = "tooltip";
            p.textContent = el.textContent;
            document.querySelector("#tipinfo").appendChild(p);
        });
        el.addEventListener("mouseleave", () => {
            document.querySelectorAll(".tooltip").forEach(t => t.remove());
        });
    });

    /* --- Collapse Widgets --- */
    const panels = document.querySelectorAll("#sidebar li.widget ul");
    const headers = document.querySelectorAll("#sidebar li.widget h2");
    headers.forEach((h2, i) => {
        h2.classList.add("active");
        if (document.cookie.includes("panel" + i + "=closed" + i)) {
            panels[i].style.display = "none";
            h2.classList.remove("active");
            h2.classList.add("inactive");
        }
        h2.addEventListener("click", () => {
            if (h2.classList.contains("active")) {
                panels[i].style.display = "none";
                h2.classList.remove("active");
                h2.classList.add("inactive");
                document.cookie = "panel" + i + "=closed" + i + "; path=/; max-age=" + 10 * 24 * 60 * 60;
            } else {
                panels[i].style.display = "block";
                h2.classList.add("active");
                h2.classList.remove("inactive");
                document.cookie = "panel" + i + "=; path=/; max-age=0";
            }
        });
    });

    /* --- Aktive Links Hauptmenü --- */
    const menuLinks = document.querySelectorAll("#mainMenu a");
    const savedMenu = localStorage.getItem("activeMenuLink");
    if (savedMenu) {
        const link = document.querySelector(`#mainMenu a[data-id="${savedMenu}"]`);
        if (link)
            link.classList.add("active");
    }
    menuLinks.forEach(link => {
        link.addEventListener("click", e => {
            // e.preventDefault();
            menuLinks.forEach(l => l.classList.remove("active"));
            link.classList.add("active");
            localStorage.setItem("activeMenuLink", link.getAttribute("data-id"));
        });
    });

    /* --- Aktive Links Widgets --- */
    const widgetLinks = document.querySelectorAll("#sidebar li.widget a");
    const savedWidget = localStorage.getItem("activeWidgetLink");
    if (savedWidget) {
        const link = document.querySelector(`#sidebar li.widget a[data-id="${savedWidget}"]`);
        if (link)
            link.classList.add("active");
    }
    widgetLinks.forEach(link => {
        link.addEventListener("click", e => {
            // e.preventDefault();
            widgetLinks.forEach(l => l.classList.remove("active"));
            link.classList.add("active");
            localStorage.setItem("activeWidgetLink", link.getAttribute("data-id"));
        });
    });
});
