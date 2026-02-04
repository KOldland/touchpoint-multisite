(function () {
  if (typeof window.kh_reading_progress === 'undefined') {
    return;
  }

  var config = window.kh_reading_progress || {};
  var endpoint = config.endpoint;
  var nonce = config.nonce;
  var postId = config.post_id;
  var repSent = !!config.rep_sent;

  if (!endpoint || !nonce || !postId) {
    return;
  }

  var thresholds = [25, 50, 75, 100];
  var fired = {};
  var ticking = false;

  function percentScrolled() {
    var doc = document.documentElement;
    var scrollTop = window.pageYOffset || doc.scrollTop || 0;
    var scrollHeight = doc.scrollHeight - doc.clientHeight;
    if (scrollHeight <= 0) {
      return 100;
    }
    return Math.min(100, Math.round((scrollTop / scrollHeight) * 100));
  }

  function eventIdFor(percent) {
    if (percent >= 75) {
      return repSent ? 'article_read_75_plus_rep_sent' : 'article_read_75_plus_marketing';
    }
    if (percent >= 25) {
      return 'article_partial_25_75';
    }
    return 'article_skimm_lt25';
  }

  function sendEvent(eventId, meta) {
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
          metadata: meta
        })
      });
    } catch (e) {
      // noop
    }
  }

  function handleScroll() {
    if (ticking) {
      return;
    }
    ticking = true;
    window.requestAnimationFrame(function () {
      var pct = percentScrolled();
      thresholds.forEach(function (threshold) {
        if (pct >= threshold && !fired[threshold]) {
          fired[threshold] = true;
          sendEvent(eventIdFor(threshold), {
            percent: pct,
            post_id: postId,
            url: window.location.href
          });
        }
      });
      ticking = false;
    });
  }

  window.addEventListener('scroll', handleScroll, { passive: true });
  window.addEventListener('resize', handleScroll);
  handleScroll();
})();
