document.addEventListener("DOMContentLoaded", function () {
    var url = location.href;
    var links = document.querySelectorAll("#primary_nav_wrap a");

    for (var i = 0; i < links.length; i++) {
        if (links[i].href === url) {
            var topLevel = links[i].closest("ul").closest("li");
            links[i].parentElement.classList.add("active");
            while (topLevel && topLevel.id !== "primary_nav_wrap") {
                topLevel.classList.add("active");
                topLevel = topLevel.parentElement.closest("li");
            }
        }
    }

    var menuItems = document.querySelectorAll("#primary_nav_wrap li");
    for (var i = 0; i < menuItems.length; i++) {
        if (menuItems[i].querySelectorAll("ul").length > 0) {
            menuItems[i].classList.add("has-child");
        }
    }

    /*
     
     var headerHeight = document.querySelector('.fixed-header').offsetHeight;
     document.querySelector('#flex-container').style.marginTop = headerHeight + "px";
     
     
     var statusMessage = document.querySelector('#statusmessage');
     statusMessage.textContent = '#status page loaded..';
     statusMessage.style.marginBottom = 0;
     setTimeout( function(){
     statusMessage.style.marginBottom = "-50px";
     }, 5000);
     */
});