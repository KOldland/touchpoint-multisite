(function () {
  if (typeof window.kh_pricing_tracking === 'undefined') {
    return;
  }

  var config = window.kh_pricing_tracking || {};
  var endpoint = config.endpoint;
  var nonce = config.nonce;
  var eventId = config.event_id || 'pricing_cta_click';

  if (!endpoint || !nonce) {
    return;
  }

  function matchesPricingTarget(target) {
    if (!target) {
      return false;
    }
    if (target.closest) {
      if (target.closest('[data-kh-pricing-cta]')) {
        return true;
      }
      if (target.closest('.pricing-cta')) {
        return true;
      }
    }
    var href = target.getAttribute && target.getAttribute('href');
    if (href && href.indexOf('checkout') !== -1) {
      return true;
    }
    return false;
  }

  function sendEvent(meta) {
    try {
      fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify({
          event_id: eventId,
          metadata: meta || {}
        })
      });
    } catch (e) {
      // noop
    }
  }

  document.addEventListener(
    'click',
    function (e) {
      var target = e.target;
      if (!matchesPricingTarget(target)) {
        return;
      }
      var el = target.closest ? target.closest('a,button,[data-kh-pricing-cta],.pricing-cta') : target;
      var meta = {
        text: (el && el.textContent ? el.textContent.trim() : ''),
        href: (el && el.getAttribute ? el.getAttribute('href') : ''),
        url: window.location.href
      };
      sendEvent(meta);
    },
    { passive: true }
  );
})();
