(function(){
  function revealElement(el) {
    if (!el) return;
    if (el.classList.contains('ad-modal')) {
      el.style.display = 'flex';
    } else {
      el.style.display = 'block';
    }
  }

  var schedules = (window.khAdManager && khAdManager.slotSchedules) || {};
  Object.keys(schedules).forEach(function(slot) {
    var config = schedules[slot];
    if (!config || !config.enabled) {
      return;
    }
    var el = document.getElementById(config.elementId);
    if (!el) {
      return;
    }

    if (config.trigger === 'exit_intent') {
      document.addEventListener('mouseout', function(e) {
        if (!e.relatedTarget && e.clientY < 50) {
          revealElement(el);
        }
      });
    } else {
      var delay = parseInt(config.delay || 0, 10);
      setTimeout(function() {
        revealElement(el);
      }, Math.max(0, delay));
    }
  });


// Click tracking
document.addEventListener('click', function(event) {
  if (!window.khAdManager) {
    return;
  }

  var wrapper = event.target.closest('.kh-ad-unit-wrapper[data-kh-ad-id]');
  if (!wrapper) {
    return;
  }

  var adId = wrapper.getAttribute('data-kh-ad-id');
  var slot = wrapper.getAttribute('data-kh-ad-slot') || '';
  if (!adId) {
    return;
  }

  var payload = new URLSearchParams();
  payload.set('action', 'kh_ad_click');
  payload.set('nonce', khAdManager.nonce);
  payload.set('ad_id', adId);
  payload.set('slot', slot);

  fetch(khAdManager.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
    },
    body: payload.toString()
  }).catch(function() {
    // Fail silently; tracking should not block navigation
  });
});
})();
