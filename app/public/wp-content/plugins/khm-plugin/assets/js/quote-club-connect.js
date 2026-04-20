(function ($) {
  'use strict';

  function esc(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function splitList(value) {
    return String(value || '')
      .split(/[\n,|]/)
      .map(function (item) {
        return item.trim();
      })
      .filter(Boolean);
  }

  function parseJsonField(value) {
    var raw = (value || '').trim();
    if (!raw) {
      return {};
    }

    return JSON.parse(raw);
  }

  function request(path, method, payload) {
    return $.ajax({
      url: (window.khmQuoteClub && khmQuoteClub.connectRestUrl ? khmQuoteClub.connectRestUrl : '') + path,
      method: method,
      contentType: method === 'GET' || method === 'DELETE' ? undefined : 'application/json',
      processData: method === 'GET',
      headers: {
        'X-WP-Nonce': window.khmQuoteClub ? khmQuoteClub.nonce : ''
      },
      dataType: 'json',
      data: method === 'GET' ? (payload || {}) : (payload ? JSON.stringify(payload) : undefined)
    });
  }

  function formatRange(minValue, maxValue, prefix) {
    var min = minValue !== null && minValue !== undefined && minValue !== '' ? String(minValue) : '';
    var max = maxValue !== null && maxValue !== undefined && maxValue !== '' ? String(maxValue) : '';
    if (!min && !max) {
      return '';
    }
    if (min && max) {
      return prefix + min + ' - ' + prefix + max;
    }
    if (min) {
      return prefix + min + '+';
    }
    return 'Up to ' + prefix + max;
  }

  $(function () {
    var $shell = $('.khm-qc-connect-shell');
    if (!$shell.length) {
      return;
    }

    var $form = $('#khm-qc-connect-form');
    var $list = $shell.find('.khm-qc-connect-list');
    var $status = $shell.find('.khm-qc-connect-status');
    var $deleteButton = $shell.find('.khm-qc-connect-delete');
    var $threadList = $shell.find('.khm-qc-connect-thread-list');
    var $threadDetail = $shell.find('.khm-qc-connect-thread-detail');
    var providers = [];
    var threads = [];
    var activeId = 0;
    var activeThreadId = 0;

    function formatDate(value) {
      if (!value) {
        return '';
      }

      var date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return String(value);
      }

      return date.toLocaleString();
    }

    function showStatus(message, tone) {
      $status
        .removeClass('is-error is-success')
        .addClass('is-visible');

      if (tone === 'error') {
        $status.addClass('is-error');
      } else if (tone === 'success') {
        $status.addClass('is-success');
      }

      $status.text(message || '');
    }

    function clearStatus() {
      $status.removeClass('is-visible is-error is-success').text('');
    }

    function resetForm() {
      activeId = 0;
      $form.trigger('reset');
      $form.find('[name="id"]').val('');
      $form.find('[name="comparison_fields"]').val('{}');
      $form.find('[name="match_rules"]').val('{}');
      $deleteButton.hide();
      renderList();
      clearStatus();
    }

    function populateForm(provider) {
      activeId = parseInt(provider && provider.id, 10) || 0;
      $form.find('[name="id"]').val(activeId ? String(activeId) : '');
      $form.find('[name="name"]').val(provider.name || '');
      $form.find('[name="slug"]').val(provider.slug || '');
      $form.find('[name="website_url"]').val(provider.website_url || '');
      $form.find('[name="provider_type"]').val(provider.provider_type || '');
      $form.find('[name="description"]').val(provider.description || '');
      $form.find('[name="sweet_spot_summary"]').val(provider.sweet_spot_summary || '');
      $form.find('[name="titles"]').val(Array.isArray(provider.titles) ? provider.titles.join(', ') : '');
      $form.find('[name="regions"]').val(Array.isArray(provider.regions) ? provider.regions.join(', ') : '');
      $form.find('[name="deployment_modes"]').val(Array.isArray(provider.deployment_modes) ? provider.deployment_modes.join(', ') : '');
      $form.find('[name="support_tiers"]').val(Array.isArray(provider.support_tiers) ? provider.support_tiers.join(', ') : '');
      $form.find('[name="company_size_min"]').val(provider.company_size_min || '');
      $form.find('[name="company_size_max"]').val(provider.company_size_max || '');
      $form.find('[name="budget_min"]').val(provider.budget_min || '');
      $form.find('[name="budget_max"]').val(provider.budget_max || '');
      $form.find('[name="onboarding_days"]').val(provider.onboarding_days || '');
      $form.find('[name="status"]').val(provider.status || 'active');
      $form.find('[name="commentary_enabled"]').prop('checked', !!provider.commentary_enabled);
      $form.find('[name="ad_targeting_enabled"]').prop('checked', !!provider.ad_targeting_enabled);
      $form.find('[name="comparison_fields"]').val(JSON.stringify(provider.comparison_fields || {}, null, 2));
      $form.find('[name="match_rules"]').val(JSON.stringify(provider.match_rules || {}, null, 2));
      $deleteButton.show();
      renderList();
    }

    function renderList() {
      if (!providers.length) {
        $list.html('<div class="khm-qc-connect-empty">No Connect offerings yet. Create your first one to define how your sponsor appears in comparison and matching flows.</div>');
        return;
      }

      var html = providers.map(function (provider) {
        var isSelected = activeId && parseInt(provider.id, 10) === activeId;
        var tags = [];
        var budgetRange = formatRange(provider.budget_min, provider.budget_max, '$');
        var companyRange = formatRange(provider.company_size_min, provider.company_size_max, '');

        if (provider.provider_type) {
          tags.push('<span class="khm-qc-connect-pill">' + esc(provider.provider_type) + '</span>');
        }
        if (provider.status) {
          tags.push('<span class="khm-qc-connect-pill">' + esc(provider.status) + '</span>');
        }
        if (provider.commentary_enabled) {
          tags.push('<span class="khm-qc-connect-pill">commentary</span>');
        }
        if (provider.ad_targeting_enabled) {
          tags.push('<span class="khm-qc-connect-pill">ad targeting</span>');
        }

        return '' +
          '<article class="khm-qc-connect-card' + (isSelected ? ' is-selected' : '') + '" data-provider-id="' + esc(provider.id) + '">' +
            '<div class="khm-qc-connect-card-head">' +
              '<div>' +
                '<h4>' + esc(provider.name) + '</h4>' +
                '<div class="khm-qc-connect-card-meta">' + esc(provider.slug || '') + (provider.website_url ? ' · ' + esc(provider.website_url) : '') + '</div>' +
              '</div>' +
              '<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-connect-edit" data-provider-id="' + esc(provider.id) + '">Edit</button>' +
            '</div>' +
            (tags.length ? '<div class="khm-qc-connect-card-tags">' + tags.join('') + '</div>' : '') +
            (provider.sweet_spot_summary ? '<p class="khm-qc-connect-card-summary">' + esc(provider.sweet_spot_summary) + '</p>' : '') +
            ((budgetRange || companyRange || provider.onboarding_days) ? '<p class="khm-qc-connect-card-meta">' +
              (budgetRange ? 'Budget ' + esc(budgetRange) : '') +
              (budgetRange && companyRange ? ' · ' : '') +
              (companyRange ? 'Company size ' + esc(companyRange) : '') +
              ((budgetRange || companyRange) && provider.onboarding_days ? ' · ' : '') +
              (provider.onboarding_days ? 'Onboarding ' + esc(provider.onboarding_days) + ' days' : '') +
            '</p>' : '') +
          '</article>';
      }).join('');

      $list.html(html);
    }

    function renderThreads() {
      if (!$threadList.length) {
        return;
      }

      if (!threads.length) {
        $threadList.html('<div class="khm-qc-connect-empty">No intro requests yet. New buyer requests will appear here.</div>');
        return;
      }

      $threadList.html(threads.map(function (thread) {
        var isSelected = activeThreadId && parseInt(thread.id, 10) === activeThreadId;
        var meta = [thread.provider_name, thread.buyer_company, formatDate(thread.latest_message_at || thread.created_at)].filter(Boolean).join(' · ');

        return '' +
          '<article class="khm-qc-connect-card khm-qc-connect-thread-card' + (isSelected ? ' is-selected' : '') + '" data-thread-id="' + esc(thread.id) + '">' +
            '<div class="khm-qc-connect-card-head">' +
              '<div>' +
                '<h4>' + esc(thread.buyer_name || 'Buyer') + '</h4>' +
                '<div class="khm-qc-connect-card-meta">' + esc(meta) + '</div>' +
              '</div>' +
              '<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-connect-open-thread" data-thread-id="' + esc(thread.id) + '">Open</button>' +
            '</div>' +
            '<div class="khm-qc-connect-card-tags">' +
              '<span class="khm-qc-connect-pill">' + esc(thread.status || 'open') + '</span>' +
              '<span class="khm-qc-connect-pill">handover ' + esc(thread.handover_status || 'not_started') + '</span>' +
              '<span class="khm-qc-connect-pill">' + esc(thread.message_count || 0) + ' messages</span>' +
            '</div>' +
            (thread.last_message_excerpt ? '<p class="khm-qc-connect-card-summary">' + esc(thread.last_message_excerpt) + '</p>' : '') +
          '</article>';
      }).join(''));
    }

    function renderThreadMessages(messages) {
      if (!Array.isArray(messages) || !messages.length) {
        return '<div class="khm-qc-connect-empty">No messages yet.</div>';
      }

      return messages.map(function (message) {
        var role = message.sender_role === 'sponsor' ? 'Sponsor' : 'Buyer';

        return '' +
          '<article class="khm-qc-connect-message khm-qc-connect-message-' + esc(message.sender_role || 'buyer') + '">' +
            '<div class="khm-qc-connect-message-meta">' + esc(role) + ' · ' + esc(formatDate(message.created_at)) + '</div>' +
            '<div class="khm-qc-connect-message-body">' + esc(message.message || '') + '</div>' +
          '</article>';
      }).join('');
    }

    function renderThreadDetail(thread, messages, handover) {
      if (!$threadDetail.length) {
        return;
      }

      if (!thread) {
        $threadDetail.html('<div class="khm-qc-connect-empty">Select an intro thread to review messages, reply, and manage handover.</div>');
        return;
      }

      var handoverStatus = handover && handover.status ? handover.status : (thread.handover_status || 'not_started');
      var handoverMeta = [];
      var confirmDisabled = handoverStatus !== 'buyer_requested' ? ' disabled' : '';

      if (handover && handover.requested_at) {
        handoverMeta.push('Requested ' + formatDate(handover.requested_at));
      }
      if (handover && handover.confirmed_at) {
        handoverMeta.push('Confirmed ' + formatDate(handover.confirmed_at));
      }

      $threadDetail.html(
        '<div class="khm-qc-connect-thread-head">' +
          '<div>' +
            '<h4>' + esc(thread.buyer_name || 'Buyer') + '</h4>' +
            '<p>' + esc(thread.provider_name || '') + (thread.buyer_company ? ' · ' + esc(thread.buyer_company) : '') + '</p>' +
          '</div>' +
          '<div class="khm-qc-connect-card-tags">' +
            '<span class="khm-qc-connect-pill">' + esc(thread.status || 'open') + '</span>' +
            '<span class="khm-qc-connect-pill">handover ' + esc(handoverStatus) + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="khm-qc-connect-thread-meta">' + esc(handoverMeta.join(' · ')) + '</div>' +
        '<div class="khm-qc-connect-thread-messages">' + renderThreadMessages(messages) + '</div>' +
        '<form class="khm-qc-connect-thread-reply" data-thread-id="' + esc(thread.id) + '">' +
          '<label for="khm-qc-connect-thread-reply-message">Reply</label>' +
          '<textarea id="khm-qc-connect-thread-reply-message" name="message" rows="4" placeholder="Reply to the buyer. This message will be relayed through the platform." required></textarea>' +
          '<div class="khm-qc-connect-actions">' +
            '<button type="submit" class="khm-qc-btn khm-qc-btn-primary">Send reply</button>' +
            '<button type="button" class="khm-qc-btn khm-qc-btn-secondary khm-qc-connect-confirm-handover" data-thread-id="' + esc(thread.id) + '"' + confirmDisabled + '>Confirm handover</button>' +
          '</div>' +
        '</form>'
      );
    }

    function collectPayload() {
      return {
        name: $form.find('[name="name"]').val().trim(),
        slug: $form.find('[name="slug"]').val().trim(),
        website_url: $form.find('[name="website_url"]').val().trim(),
        provider_type: $form.find('[name="provider_type"]').val(),
        description: $form.find('[name="description"]').val().trim(),
        sweet_spot_summary: $form.find('[name="sweet_spot_summary"]').val().trim(),
        titles: splitList($form.find('[name="titles"]').val()),
        regions: splitList($form.find('[name="regions"]').val()),
        deployment_modes: splitList($form.find('[name="deployment_modes"]').val()),
        support_tiers: splitList($form.find('[name="support_tiers"]').val()),
        company_size_min: $form.find('[name="company_size_min"]').val(),
        company_size_max: $form.find('[name="company_size_max"]').val(),
        budget_min: $form.find('[name="budget_min"]').val(),
        budget_max: $form.find('[name="budget_max"]').val(),
        onboarding_days: $form.find('[name="onboarding_days"]').val(),
        status: $form.find('[name="status"]').val() || 'active',
        commentary_enabled: $form.find('[name="commentary_enabled"]').is(':checked'),
        ad_targeting_enabled: $form.find('[name="ad_targeting_enabled"]').is(':checked'),
        comparison_fields: parseJsonField($form.find('[name="comparison_fields"]').val()),
        match_rules: parseJsonField($form.find('[name="match_rules"]').val())
      };
    }

    function loadProviders() {
      request('providers/mine', 'GET').done(function (res) {
        providers = Array.isArray(res && res.providers) ? res.providers : [];
        renderList();
      }).fail(function () {
        showStatus('Unable to load Connect offerings right now.', 'error');
      });
    }

    function loadThreads(selectThreadId) {
      if (!$threadList.length) {
        return;
      }

      request('intro-threads/mine', 'GET').done(function (res) {
        threads = Array.isArray(res && res.threads) ? res.threads : [];
        renderThreads();

        if (selectThreadId) {
          openThread(selectThreadId);
        } else if (activeThreadId) {
          openThread(activeThreadId);
        }
      }).fail(function () {
        $threadList.html('<div class="khm-qc-connect-empty">Unable to load intro threads right now.</div>');
      });
    }

    function openThread(threadId) {
      threadId = parseInt(threadId, 10) || 0;
      if (!threadId) {
        return;
      }

      activeThreadId = threadId;
      renderThreads();
      $threadDetail.html('<div class="khm-qc-connect-empty">Loading intro thread...</div>');

      request('intro-threads/mine/' + threadId, 'GET').done(function (res) {
        renderThreadDetail(res && res.thread, res && res.messages, res && res.handover);
      }).fail(function () {
        $threadDetail.html('<div class="khm-qc-connect-empty">Unable to load this intro thread.</div>');
      });
    }

    $shell.on('click', '.khm-qc-connect-new, .khm-qc-connect-reset', function () {
      resetForm();
    });

    $shell.on('click', '.khm-qc-connect-edit', function () {
      var providerId = parseInt($(this).data('provider-id'), 10);
      var provider = providers.find(function (item) {
        return parseInt(item && item.id, 10) === providerId;
      });

      if (provider) {
        populateForm(provider);
      }
    });

    $shell.on('click', '.khm-qc-connect-open-thread', function () {
      openThread($(this).data('thread-id'));
    });

    $deleteButton.on('click', function () {
      var providerId = parseInt($form.find('[name="id"]').val(), 10);
      if (!providerId) {
        return;
      }

      if (!window.confirm('Delete this offering?')) {
        return;
      }

      request('providers/mine/' + providerId, 'DELETE').done(function () {
        showStatus('Offering deleted.', 'success');
        resetForm();
        loadProviders();
      }).fail(function () {
        showStatus('Unable to delete the offering.', 'error');
      });
    });

    $form.on('submit', function (event) {
      var providerId = parseInt($form.find('[name="id"]').val(), 10);
      var payload;
      var path = 'providers/mine';
      var method = 'POST';

      event.preventDefault();
      clearStatus();

      try {
        payload = collectPayload();
      } catch (error) {
        showStatus('Comparison or match rule JSON is invalid. Please fix the JSON and try again.', 'error');
        return;
      }

      if (providerId) {
        path += '/' + providerId;
        method = 'PUT';
      }

      request(path, method, payload).done(function (res) {
        showStatus(providerId ? 'Offering updated.' : 'Offering created.', 'success');
        if (res && res.provider) {
          populateForm(res.provider);
        }
        loadProviders();
      }).fail(function (xhr) {
        var res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
        showStatus(res.message || 'Unable to save the offering.', 'error');
      });
    });

    $shell.on('submit', '.khm-qc-connect-thread-reply', function (event) {
      var threadId = parseInt($(this).data('thread-id'), 10) || 0;
      var $textarea = $(this).find('[name="message"]');
      var $submit = $(this).find('[type="submit"]');
      var message = $textarea.val().trim();

      event.preventDefault();

      if (!threadId || !message) {
        return;
      }

      $submit.prop('disabled', true).text('Sending...');
      request('intro-threads/mine/' + threadId + '/reply', 'POST', { message: message }).done(function (res) {
        $textarea.val('');
        showStatus('Reply sent.', 'success');
        renderThreadDetail(res && res.thread, res && res.messages, res && res.handover);
        loadThreads(threadId);
      }).fail(function (xhr) {
        var res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
        showStatus(res.message || 'Unable to send reply.', 'error');
      }).always(function () {
        $submit.prop('disabled', false).text('Send reply');
      });
    });

    $shell.on('click', '.khm-qc-connect-confirm-handover', function () {
      var threadId = parseInt($(this).data('thread-id'), 10) || 0;
      var $button = $(this);

      if (!threadId || $button.is(':disabled')) {
        return;
      }

      $button.prop('disabled', true).text('Confirming...');
      request('intro-threads/mine/' + threadId + '/handover/confirm', 'POST').done(function () {
        showStatus('Handover confirmed. Attribution can now start from the confirmed handover point.', 'success');
        loadThreads(threadId);
      }).fail(function (xhr) {
        var res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
        showStatus(res.message || 'Unable to confirm handover.', 'error');
        $button.prop('disabled', false).text('Confirm handover');
      });
    });

    resetForm();
    loadProviders();
    loadThreads();
  });
})(jQuery);