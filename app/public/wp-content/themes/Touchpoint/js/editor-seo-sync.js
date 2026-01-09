(function (wp) {
  if (!wp || !wp.data || !wp.domReady) {
    return;
  }

  var fieldMap = {
    '#khm_seo_title': '_khm_seo_title',
    '#khm_seo_description': '_khm_seo_description',
    '#khm_seo_keywords': '_khm_seo_keywords',
    '#khm_seo_robots': '_khm_seo_robots',
    '#khm_seo_canonical': '_khm_seo_canonical',
    '#khm_seo_focus_keyword': '_khm_seo_focus_keyword'
  };

  function setupSeoSync(dispatch) {
    Object.keys(fieldMap).forEach(function (selector) {
      var input = document.querySelector(selector);
      if (!input) {
        return;
      }

      var metaKey = fieldMap[selector];
      var handler = function () {
        var meta = {};
        meta[metaKey] = input.value || '';
        dispatch.editPost({ meta: meta });
      };

      input.addEventListener('input', handler);
      input.addEventListener('change', handler);
    });
  }

  function getAuthorIds() {
    var field = document.querySelector('[data-key="field_multi_author_relationship"]');
    if (!field) {
      return null;
    }

    var inputs = field.querySelectorAll('input[type=\"hidden\"][name^=\"acf[field_multi_author_relationship]\"]');
    var ids = [];
    inputs.forEach(function (input) {
      var val = parseInt(input.value, 10);
      if (!isNaN(val) && val > 0) {
        ids.push(val);
      }
    });

    return ids;
  }

  function setupAuthorSync(dispatch) {
    var field = document.querySelector('[data-key="field_multi_author_relationship"]');
    if (!field) {
      return;
    }

    var syncAuthors = function () {
      var ids = getAuthorIds();
      if (ids === null) {
        return;
      }
      dispatch.editPost({ meta: { authors: ids } });
    };

    field.addEventListener('change', syncAuthors);
    field.addEventListener('click', syncAuthors);
    field.addEventListener('keyup', syncAuthors);

    var observer = new MutationObserver(syncAuthors);
    observer.observe(field, { childList: true, subtree: true });

    setTimeout(syncAuthors, 500);
  }

  wp.domReady(function () {
    var dispatch = wp.data.dispatch('core/editor');
    if (!dispatch || !dispatch.editPost) {
      return;
    }

    setupSeoSync(dispatch);
    setupAuthorSync(dispatch);
  });
})(window.wp);
