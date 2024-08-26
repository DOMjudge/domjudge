/**
 * Based on:
 *
 * Author and copyright: Stefan Haack (https://shaack.com)
 * Repository: https://github.com/shaack/bootstrap-auto-dark-mode
 * License: MIT, see file 'LICENSE'
 */

window.updateTheme = function(theme) {
    theme = theme || localStorage.getItem("theme") || "auto";

    if (theme !== "dark" && theme !== "light") {
        theme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    }

    localStorage.setItem("theme", theme);
    document.querySelector("html").setAttribute("data-bs-theme", theme);

    editors = document.querySelectorAll(".editor");
    for (let i = 0; i < editors.length; i++) {
        let editor = editors[i].editor;
        editor.setTheme(theme === "dark" ? "ace/theme/tomorrow_night" : "ace/theme/eclipse");
    }
}

;(function () {
    if (document.querySelector("html").getAttribute("data-bs-theme") === 'auto') {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', window.updateTheme);
        window.updateTheme();
    }
})();
