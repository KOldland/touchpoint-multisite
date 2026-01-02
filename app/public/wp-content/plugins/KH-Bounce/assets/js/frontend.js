(function($){
    var storageKey = 'khBounceShown';
    var settings = window.khBounceSettings || {};
    var telemetryMode = settings.telemetryMode || 'none';
    var templateName = settings.template || 'classic';
    var forceShow = !!settings.forceShow;
    var focusableSelector = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';
    var previousActiveElement = null;
    var hasShown = false;

    $(document).ready(function(){
        var modal = $('.kh-bounce-modal');
        if (!modal.length) {
            return;
        }

        hasShown = forceShow;

        if (forceShow) {
            showModal(modal, true);
            emitTelemetry('impression');
        } else if (getStorage('get', storageKey)) {
            return;
        }

        $(document).on('mouseleave.khBounce', function(e){
            if (hasShown) {
                return;
            }
            if (e.clientY <= 0) {
                hasShown = true;
                showModal(modal);
                getStorage('set', storageKey, '1');
            }
        });

        modal.on('click', '.kh-bounce-close, .kh-bounce-dismiss, .kh-bounce-modal', function(e){
            if ($(e.target).is('.kh-bounce-modal') || $(e.target).is('.kh-bounce-close') || $(e.target).is('.kh-bounce-dismiss')) {
                hideModal(modal);
                emitTelemetry('dismiss');
            }
        });

        modal.on('click', '.kh-bounce-cta', function(){
            emitTelemetry('conversion');
        });

        $(document).on('keydown.khBounce', function(e){
            if (e.key === 'Escape') {
                hideModal(modal);
            }
        });
    });

    function showModal($modal, skipTelemetry) {
        previousActiveElement = document.activeElement;
        $modal.attr('data-visible', 'true').attr('aria-hidden', 'false');
        trapFocus($modal);
        if (!skipTelemetry) {
            emitTelemetry('impression');
        }
    }

    function hideModal($modal) {
        $modal.removeAttr('data-visible').attr('aria-hidden', 'true');
        releaseFocus($modal);
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
            previousActiveElement = null;
        }
    }

    function getStorage(action, key, value){
        try {
            if (action === 'get') {
                return window.sessionStorage.getItem(key);
            }
            if (action === 'set') {
                window.sessionStorage.setItem(key, value);
            }
        } catch (err) {
            // noop, likely private mode.
        }
        return null;
    }

    function emitTelemetry(eventName){
        if (telemetryMode === 'none') {
            return;
        }
        var detail = {
            event: eventName,
            template: templateName,
            timestamp: Date.now()
        };
        document.dispatchEvent(new CustomEvent('khBounceEvent', { detail: detail }));

        if (telemetryMode === 'rest' && settings.restEndpoint) {
            var payload = new FormData();
            payload.append('event', eventName);
            payload.append('template', templateName);
            payload.append('timestamp', detail.timestamp);

            var headers = { 'X-WP-Nonce': settings.restNonce };
            if (navigator.sendBeacon) {
                var blob = new Blob([JSON.stringify({ event: eventName, template: templateName, timestamp: detail.timestamp })], { type: 'application/json' });
                navigator.sendBeacon(settings.restEndpoint, blob);
            } else {
                fetch(settings.restEndpoint, {
                    method: 'POST',
                    body: payload,
                    headers: headers,
                    credentials: 'same-origin'
                }).catch(function(){});
            }
        }
    }

    function trapFocus($modal){
        var focusables = $modal.find(focusableSelector).filter(':visible');
        if (!focusables.length) {
            return;
        }

        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        first.focus();

        $modal.on('keydown.khBounceFocus', function(e){
            if (e.key !== 'Tab') {
                return;
            }

            if (focusables.length === 1) {
                e.preventDefault();
                first.focus();
                return;
            }

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
                return;
            }

            if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    function releaseFocus($modal){
        $modal.off('keydown.khBounceFocus');
    }
})(jQuery);
