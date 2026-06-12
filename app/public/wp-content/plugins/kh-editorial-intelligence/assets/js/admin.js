/**
 * KH Editorial — Collapsible Sections
 */
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.kh-section-header').forEach(function (header) {
            header.addEventListener('click', function () {
                var section = this.parentElement;
                section.classList.toggle('open');
            });
        });
        // Open first section by default
        var first = document.querySelector('.kh-section');
        if (first) first.classList.add('open');
    });
})();