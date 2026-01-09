(function () {
  function getSidebarTarget() {
    return document.querySelector('.elementor-element-47963c0e .elementor-widget-wrap') ||
      document.querySelector('.elementor-element-47963c0e');
  }

  function positionSidebar(sidebar, anchor) {
    if (!anchor || !sidebar.parentElement) {
      return;
    }

    var parent = sidebar.parentElement;
    var spacer = parent.querySelector('.sr-sidebar-spacer');
    if (!spacer) {
      spacer = document.createElement('div');
      spacer.className = 'sr-sidebar-spacer';
      parent.insertBefore(spacer, sidebar);
    }

    var anchorTop = anchor.getBoundingClientRect().top + window.scrollY;
    var parentTop = parent.getBoundingClientRect().top + window.scrollY;
    var offset = Math.max(0, Math.round(anchorTop - parentTop));

    spacer.style.height = offset + 'px';
  }

  function reveal(sidebar) {
    sidebar.classList.remove('is-hidden');
    window.requestAnimationFrame(function () {
      sidebar.classList.add('is-visible');
    });
  }

  function init() {
    var anchor = document.querySelector('.sr-abstract-end-anchor');
    var sidebar = document.querySelector('.suggested-reading-sidebar');

    if (!sidebar) {
      return;
    }

    if (!anchor || !('IntersectionObserver' in window)) {
      reveal(sidebar);
      return;
    }

    var handleResize = function () {
      if (sidebar.classList.contains('is-visible')) {
        positionSidebar(sidebar, anchor);
      }
    };

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          positionSidebar(sidebar, anchor);
          reveal(sidebar);
          window.requestAnimationFrame(function () {
            positionSidebar(sidebar, anchor);
          });
          observer.disconnect();
        }
      });
    }, { threshold: 0.2, rootMargin: '0px 0px -20% 0px' });

    observer.observe(anchor);
    window.addEventListener('load', handleResize);
    window.addEventListener('resize', handleResize);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
