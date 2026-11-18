document.addEventListener("DOMContentLoaded", function () {
    var more = document.querySelector("#adminmenu .wp-submenu li.sgap-menu-more");
    if (!more) return;
    var menu = more.parentNode;
    var toggle = more.querySelector("a");
    if (!toggle) return;
    menu.classList.add("sgap-menu-js");
    toggle.setAttribute("role", "button");
    function setOpen(open) {
        menu.classList.toggle("sgap-more-open", open);
        more.classList.toggle("is-open", open);
        toggle.setAttribute("aria-expanded", String(open));
    }
    setOpen(more.classList.contains("is-open"));
    toggle.addEventListener("click", function (event) {
        event.preventDefault();
        setOpen(!menu.classList.contains("sgap-more-open"));
    });
    toggle.addEventListener("keydown", function (event) {
        if (event.key === " ") {
            event.preventDefault();
            toggle.click();
        }
    });
});
