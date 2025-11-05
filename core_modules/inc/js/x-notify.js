/*
 * XNotify - JavaScript Notification Library
 * Website: https://www.xtrendence.com
 * Portfolio: https://www.xtrendence.dev
 * GitHub: https://www.github.com/Xtrendence
 *
 * Changes:
 * - Added support for optional "OK" button in notifications.
 * - Added `withButton` and `onConfirm` options to setOptions().
 * - Updated createElement() to append OK button when `withButton` is true.
 * - Enables displayMessage() to show alert/notification with manual confirmation callback.
 *
 * Commit Path Suggestion: feature/ok-button-notification
 */
class XNotify {
    constructor(position) {
        this.position = this.empty(position) ? "TopRight" : position;

        this.defaults = {
            width: "250px",
            borderRadius: "10px",
            duration: 5000,
            color: "rgb(255,255,255)",
            success: {
                title: "Success Notification",
                description: "Whatever you did, it worked.",
                background: "rgb(40,200,80)"
            },
            error: {
                title: "Error Notification",
                description: "That didn't work out, did it?",
                background: "rgb(230,50,50)"
            },
            alert: {
                title: "Alert Notification",
                description: "This is probably important...",
                background: "rgb(240,180,10)"
            },
            info: {
                title: "Info Notification",
                description: "Just so you know...",
                background: "rgb(170,80,220)"
            }
        };
    }

    setOptions(options, type) {
        this.width = this.empty(options.width) ? this.defaults.width : options.width;
        this.borderRadius = this.empty(options.borderRadius) ? this.defaults.borderRadius : options.borderRadius;
        this.title = this.empty(options.title) ? this.defaults[type].title : options.title;
        this.description = this.empty(options.description) ? this.defaults[type].description : options.description;
        this.duration = this.empty(options.duration) ? this.defaults.duration : options.duration;
        this.background = this.empty(options.background) ? this.defaults[type].background : options.background;
        this.color = this.empty(options.color) ? this.defaults.color : options.color;

        // New options for OK button
        this.withButton = options.withButton || false;
        this.onConfirm = options.onConfirm || null;
    }

    success(options) {
        this.setOptions(options, "success");
        this.showNotification(this.createElement());
    }

    error(options) {
        this.setOptions(options, "error");
        this.showNotification(this.createElement());
    }

    alert(options) {
        this.setOptions(options, "alert");
        this.showNotification(this.createElement());
    }

    info(options) {
        this.setOptions(options, "info");
        this.showNotification(this.createElement());
    }

    createElement() {
        if (!document.getElementById("x-notify-container")) {
            let body = document.body;

            let height = "calc(100% - 20px)";
            let paddingRight = "20px";
            let paddingLeft = "0";
            let top = "0";
            let right = "0";
            let bottom = "auto";
            let left = "auto";

            switch (this.position) {
                case "BottomRight":
                    height = "auto";
                    top = "auto";
                    bottom = "0";
                    break;
                case "BottomLeft":
                    height = "auto";
                    paddingRight = "0";
                    paddingLeft = "20px";
                    top = "auto";
                    right = "auto";
                    bottom = "0";
                    left = "0";
                    break;
                case "TopLeft":
                    paddingRight = "0";
                    paddingLeft = "20px";
                    right = "auto";
                    left = "0";
                    break;
            }

            let container = document.createElement("div");
            container.id = "x-notify-container";
            container.style = `position:fixed; z-index:999; width:calc(${this.width} + 70px); height:${height}; pointer-events:none; overflow-x:hidden; overflow-y:auto; -webkit-overflow-scrolling:touch; scroll-behavior:smooth; scrollbar-width:none; padding-top:20px; padding-right:${paddingRight}; padding-left:${paddingLeft}; top:${top}; right:${right}; bottom:${bottom}; left:${left};`;

            body.appendChild(container);
        }

        let align = (this.position === "TopRight" || this.position === "BottomRight") ? "right" : "left";

        let row = document.createElement("div");
        row.id = this.generateID();
        row.style = `display:block; padding:0 0 20px 0; text-align:${align}; width:100%;`;

        let notification = document.createElement("div");
        notification.classList.add("x-notification");
        notification.style = `background:${this.background}; color:${this.color}; width:${this.width}; border-radius:${this.borderRadius}; padding:10px 12px 12px 12px; font-family:"Helvetica Neue", "Lucida Grande", "Arial", "Verdana", "Tahoma", sans-serif; display:inline-block; text-align:left; opacity:0; pointer-events:auto; user-select:none; outline:none;`;
        notification.innerHTML = `<span style="font-size:18px; font-weight:bold; color:${this.color}; display:block; line-height:25px;">${this.title}</span><span style="font-size:16px; color:${this.color}; display:block; margin-top:5px; line-height:25px;">${this.description}</span>`;

        row.append(notification);

        if (this.withButton) {
            const button = document.createElement("button");
            button.innerText = "OK";
            button.style = `margin-top:10px; padding:5px 10px; border:none; border-radius:5px; background:#fff; color:${this.background}; cursor:pointer; font-weight:bold; font-family:sans-serif;`;
            button.onclick = () => {
                this.hideNotification(row);
                if (typeof this.onConfirm === "function")
                    this.onConfirm();
            };
            notification.appendChild(button);
        }

        return row;
    }

    showNotification(element) {
        let container = document.getElementById("x-notify-container");
        let notification = element.getElementsByClassName("x-notification")[0];

        this.clearDuplicates(this.title, this.description);
        container = document.getElementById("x-notify-container");
        if (this.position === "BottomRight" || this.position === "BottomLeft") {
            container.append(element);
            if (container.scrollHeight > window.innerHeight) {
                container.style.height = "calc(100% - 20px)";
            }
            container.scrollTo(0, container.scrollHeight);
        } else {
            container.prepend(element);
        }

        // Fade-in
        let opacity = 0.05;
        let animation = setInterval(() => {
            opacity += 0.05;
            notification.style.opacity = opacity;
            if (opacity >= 1) {
                notification.style.opacity = 1;
                clearInterval(animation);
            }
        }, 10);

        // Only auto-hide if no button is present
        if (!this.withButton) {
            setTimeout(() => {
                this.hideNotification(element);
            }, this.duration);
        }
    }

    hideNotification(element) {
        let container = document.getElementById("x-notify-container");
        if (!container)
            return;

        let notification = element.getElementsByClassName("x-notification")[0];
        let opacity = 1;
        let animation = setInterval(() => {
            opacity -= 0.05;
            notification.style.opacity = opacity;
            if (opacity <= 0) {
                element.remove();
                clearInterval(animation);
            }
        }, 10);

        if (container.scrollHeight <= window.innerHeight)
            container.style.height = "auto";
        if (!container.innerHTML.trim())
            container.remove();
    }

    clear() {
        let container = document.getElementById("x-notify-container");
        if (!container)
            return;
        Array.from(container.getElementsByClassName("x-notification")).forEach(el => this.hideNotification(el));
    }

    clearDuplicates(title, description) {
        const container = document.getElementById("x-notify-container");
        if (!container)
            return;

        const notifications = Array.from(container.querySelectorAll(".x-notification"));

        notifications.forEach(notification => {
            const spans = notification.querySelectorAll("span");

            if (spans.length >= 2) {
                const existingTitle = spans[0].innerText.trim();
                const existingDescription = spans[1].innerText.trim();

                if (existingTitle === title && existingDescription === description) {
                    const rowElement = notification.parentElement;
                    this.hideNotification(rowElement);
                }
            }
        });
    }

    generateID() {
        let id = this.epoch() + "-" + this.shuffle(this.epoch());
        while (document.getElementById(id)) {
            id = this.epoch() + "-" + this.shuffle(this.epoch());
        }
        return id;
    }

    shuffle(string) {
        let parts = string.toString().split("");
        for (let i = parts.length; i > 0; ) {
            let random = parseInt(Math.random() * i);
            let temp = parts[--i];
            parts[i] = parts[random];
            parts[random] = temp;
        }
        return parts.join("");
    }

    epoch() {
        return Math.round(new Date().getTime() / 1000);
    }

    empty(value) {
        return value === null || value === undefined || value.toString().trim() === "";
    }
}
