(function() {
    if (window.khmExcerptToggleInit) { return; }
    window.khmExcerptToggleInit = true;
    function getParts(span) {
        var full = span.getAttribute("data-full") || span.textContent || "";
        var short = span.getAttribute("data-short");
        if (short) {
            return { fullText: full, shortText: short };
        }
        var words = full.trim().split(/\s+/).filter(Boolean);
        var limit = 30;
        var shortText = words.length > limit ? words.slice(0, limit).join(" ") + "…" : full;
        return { fullText: full, shortText: shortText };
    }
    function toggle(btn) {
        var span = btn.previousElementSibling;
        if (!span) { return; }
        var parts = getParts(span);
        var expanded = btn.classList.contains("expanded");
        if (!expanded) {
            span.textContent = parts.fullText;
            btn.innerHTML = "<em><strong>Less</strong></em>";
            btn.classList.add("expanded");
            btn.setAttribute("aria-expanded", "true");
        } else {
            span.textContent = parts.shortText;
            btn.innerHTML = "<em><strong>More</strong></em>";
            btn.classList.remove("expanded");
            btn.setAttribute("aria-expanded", "false");
        }
    }
    if (!window.toggleExcerpt) {
        window.toggleExcerpt = function(btn) { toggle(btn); };
    }
    document.addEventListener("click", function(e) {
        var btn = e.target.closest(".excerpt-toggle");
        if (btn) { toggle(btn); }
    });
})();
