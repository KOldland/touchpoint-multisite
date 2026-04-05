(function ($) {
  'use strict';

  var inviteRequestInFlight = false;
  var inviteRetryPayload = null;

  function tokenizeKeywords(input) {
    return (input || '')
      .split(',')
      .map(function (item) {
        return item.trim();
      })
      .filter(Boolean);
  }

  function wordCount(text) {
    var stripped = (text || '').replace(/<[^>]*>/g, '').trim();
    if (!stripped) {
      return 0;
    }
    return stripped.split(/\s+/).length;
  }

  function creditsForWords(words) {
    if (words <= 0) {
      return 0;
    }
    return Math.ceil(words / 120);
  }

  function api(path, method, data) {
    return $.ajax({
      url: (window.khmQuoteClub && khmQuoteClub.restUrl ? khmQuoteClub.restUrl : '') + path,
      method: method,
      data: data || {},
      contentType: method === 'POST' || method === 'PATCH' ? 'application/json' : undefined,
      processData: method === 'GET',
      headers: {
        'X-WP-Nonce': window.khmQuoteClub ? khmQuoteClub.nonce : ''
      },
      dataType: 'json',
      data: method === 'GET' ? (data || {}) : JSON.stringify(data || {})
    });
  }

  function sponsorApi(path, method, data) {
    return $.ajax({
      url: (window.khmQuoteClub && khmQuoteClub.sponsorRestUrl ? khmQuoteClub.sponsorRestUrl : '') + path,
      method: method,
      contentType: method === 'POST' || method === 'PATCH' ? 'application/json' : undefined,
      processData: method === 'GET',
      headers: {
        'X-WP-Nonce': window.khmQuoteClub ? khmQuoteClub.nonce : ''
      },
      dataType: 'json',
      data: method === 'GET' ? (data || {}) : JSON.stringify(data || {})
    });
  }

  function getInviteParams() {
    var token = '';
    var email = '';

    if (window.URLSearchParams) {
      var params = new URLSearchParams(window.location.search || '');
      token = params.get('khm_sponsor_invite') || '';
      email = params.get('khm_sponsor_invite_email') || '';
    }

    if (!token && window.khmQuoteClub && khmQuoteClub.inviteToken) {
      token = khmQuoteClub.inviteToken;
    }
    if (!email && window.khmQuoteClub && khmQuoteClub.inviteEmail) {
      email = khmQuoteClub.inviteEmail;
    }

    return {
      token: (token || '').trim(),
      email: (email || '').trim()
    };
  }

  function clearInviteQueryParams() {
    if (!window.history || !window.history.replaceState || !window.URLSearchParams) {
      return;
    }

    var url = new URL(window.location.href);
    var params = new URLSearchParams(url.search || '');
    params.delete('khm_sponsor_invite');
    params.delete('khm_sponsor_invite_email');
    url.search = params.toString();
    window.history.replaceState({}, '', url.toString());
  }

  function isTransientInviteError(status, errorCode) {
    if (status === 0 || status === 429 || status === 409) {
      return true;
    }
    if (status >= 500 && status <= 599) {
      return true;
    }
    return errorCode === 'invite_in_progress';
  }

  function renderInviteStatus(state, title, details, showRetry) {
    var $status = $('.khm-quoteclub-invite-status');
    if (!$status.length) {
      return;
    }

    $status
      .removeClass('is-pending is-success is-error is-visible')
      .addClass('is-visible is-' + state)
      .html(
        '<div class="khm-invite-status-main">' + title + '</div>' +
        (details ? ('<div class="khm-invite-status-sub">' + details + '</div>') : '') +
        (showRetry ? '<button type="button" class="button khm-invite-retry-btn">Retry Invite</button>' : '')
      );
  }

  function acceptInvite(invite) {
    if (inviteRequestInFlight || !invite || !invite.token || !invite.email) {
      return;
    }

    inviteRequestInFlight = true;
    inviteRetryPayload = null;
    renderInviteStatus('pending', 'Accepting sponsor invite...', 'Please wait while we verify your invitation.', false);

    sponsorApi('invite/accept', 'POST', {
      token: invite.token,
      email: invite.email
    }).done(function () {
      inviteRequestInFlight = false;
      renderInviteStatus('success', 'Sponsor invite accepted.', 'You now have access to sponsor collaboration features.', false);
      clearInviteQueryParams();
    }).fail(function (xhr) {
      inviteRequestInFlight = false;

      var status = xhr && typeof xhr.status === 'number' ? xhr.status : 0;
      var errorCode = (xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : '';
      var transient = isTransientInviteError(status, errorCode);

      if (transient) {
        inviteRetryPayload = invite;
      }

      renderInviteStatus(
        'error',
        'Invite acceptance failed.',
        errorCode ? ('Error: ' + errorCode) : 'A temporary error occurred while accepting this invite.',
        transient
      );
    });
  }

  function maybeAcceptInviteFromUrl() {
    var invite = getInviteParams();
    if (!invite.token || !invite.email) {
      return;
    }

    acceptInvite(invite);
  }

  function renderResults(results) {
    var $list = $('.khm-quoteclub-results');
    $list.empty();

    if (!results || !results.length) {
      $list.append('<div class="khm-quoteclub-empty">No matching sessions found.</div>');
      return;
    }

    results.forEach(function (item) {
      var topics = (item.topics || []).map(function (t) {
        return '<span class="khm-chip">' + t + '</span>';
      }).join('');

      $list.append(
        '<div class="khm-quoteclub-result" data-session-id="' + item.session_id + '">' +
          '<div class="khm-quoteclub-result-head">' +
            '<h4>' + item.title + '</h4>' +
            '<span class="khm-score">Score ' + item.match_score + '</span>' +
          '</div>' +
          '<div class="khm-quoteclub-meta">' +
            '<span>' + item.scheduled_publish + '</span>' +
            '<span>' + (item.portfolio || '') + '</span>' +
            '<span>' + (item.word_count || 0) + ' words</span>' +
          '</div>' +
          '<p>' + (item.brief_snippet || '') + '</p>' +
          '<div class="khm-quoteclub-topics">' + topics + '</div>' +
          '<div class="khm-quoteclub-actions">' +
            '<button class="button khm-view-session">View</button>' +
          '</div>' +
        '</div>'
      );
    });
  }

  function renderSession(session) {
    var $panel = $('.khm-quoteclub-detail');
    var questionsHtml = (session.questions || []).map(function (q) {
      return (
        '<div class="khm-question" data-question-id="' + q.id + '">' +
          '<label>' + q.text + '</label>' +
          '<textarea rows="4" placeholder="Paste or write quote here"></textarea>' +
          '<div class="khm-question-meta">' +
            '<span class="khm-word-count">0 words</span>' +
            '<span class="khm-credit-count">0 credits</span>' +
          '</div>' +
          '<div class="khm-question-actions">' +
            '<button class="button khm-save-draft-btn">Save Draft</button>' +
            '<button class="button button-primary khm-submit-commentary" disabled>Submit</button>' +
          '</div>' +
          '<div class="khm-draft-status"></div>' +
        '</div>'
      );
    }).join('');

    $panel.html(
      '<h3>' + session.title + '</h3>' +
      '<p class="khm-session-date">Scheduled: ' + session.scheduled_publish + '</p>' +
      '<p class="khm-session-brief">' + (session.brief || '') + '</p>' +
      '<div class="khm-questions">' + questionsHtml + '</div>'
    ).attr('data-session-id', session.session_id);
  }

  function loadSavedSearches() {
    api('saved-searches', 'GET').done(function (res) {
      var $select = $('.khm-saved-searches');
      $select.empty().append('<option value="">Saved searches</option>');
      (res.saved_searches || []).forEach(function (item) {
        $select.append('<option value="' + item.id + '">' + item.name + '</option>');
      });
      $select.data('items', res.saved_searches || []);
    });
  }

  function performSearch(query) {
    api('search', 'GET', query).done(function (res) {
      renderResults(res.results || []);
    });
  }

  $(document).on('click', '.khm-quoteclub-search-btn', function () {
    var query = {
      date_from: $('.khm-filter-date-from').val(),
      date_to: $('.khm-filter-date-to').val(),
      topics: tokenizeKeywords($('.khm-filter-topics').val()),
      portfolios: tokenizeKeywords($('.khm-filter-portfolio').val()),
      keywords: $('.khm-filter-keywords').val(),
      operator: $('.khm-filter-operator').val() || 'AND',
      page: 1,
      per_page: 20
    };
    performSearch(query);
  });

  $(document).on('click', '.khm-save-search-btn', function () {
    var payload = {
      name: window.prompt('Saved search name:'),
      query: {
        date_from: $('.khm-filter-date-from').val(),
        date_to: $('.khm-filter-date-to').val(),
        topics: tokenizeKeywords($('.khm-filter-topics').val()),
        portfolios: tokenizeKeywords($('.khm-filter-portfolio').val()),
        keywords: $('.khm-filter-keywords').val(),
        operator: $('.khm-filter-operator').val() || 'AND'
      }
    };

    if (!payload.name) {
      return;
    }

    api('saved-searches', 'POST', payload).done(function () {
      loadSavedSearches();
    });
  });

  $(document).on('change', '.khm-saved-searches', function () {
    var id = parseInt($(this).val(), 10);
    var items = $(this).data('items') || [];
    var selected = items.find(function (item) { return item.id === id; });
    if (!selected) {
      return;
    }

    var q = selected.query || {};
    $('.khm-filter-date-from').val(q.date_from || '');
    $('.khm-filter-date-to').val(q.date_to || '');
    $('.khm-filter-topics').val((q.topics || []).join(', '));
    $('.khm-filter-portfolio').val((q.portfolios || []).join(', '));
    $('.khm-filter-keywords').val(q.keywords || '');
    $('.khm-filter-operator').val(q.operator || 'AND');

    performSearch(q);
  });

  $(document).on('click', '.khm-view-session', function () {
    var sessionId = $(this).closest('.khm-quoteclub-result').data('session-id');
    api('upcoming', 'GET', { limit: 50 }).done(function (res) {
      var session = (res.sessions || []).find(function (item) {
        return item.session_id === sessionId;
      });
      if (session) {
        renderSession(session);
      }
    });
  });

  // -------------------------------------------------------------------------
  // Draft / confirm workflow
  // -------------------------------------------------------------------------

  // Track draft IDs per question so we know which draft to update vs create.
  var draftIds = {};

  function getDraftKey($question) {
    var sessionId = $('.khm-quoteclub-detail').data('session-id') || '';
    var questionId = $question.data('question-id') || '';
    return sessionId + '__' + questionId;
  }

  function showCreditModal(creditsNeeded, creditsAvailable, onConfirm) {
    $('#khm-credit-modal').remove();

    var hasCredits = creditsAvailable >= creditsNeeded;
    var bundleUrl  = window.khmQuoteClub && khmQuoteClub.bundleRestUrl
      ? (window.khmQuoteClub.portalUrl || '') + '?qc_section=overview'
      : '';

    var bodyHtml = hasCredits
      ? '<p>Submitting this commentary will use <strong>' + creditsNeeded + ' editorial credit' + (creditsNeeded !== 1 ? 's' : '') + '</strong>.</p>' +
        '<p>You have <strong>' + creditsAvailable + '</strong> available.</p>'
      : '<p class="khm-modal-warning">You need <strong>' + creditsNeeded + ' credit' + (creditsNeeded !== 1 ? 's' : '') + '</strong> but only have <strong>' + creditsAvailable + '</strong>.</p>' +
        (bundleUrl ? '<p><a href="' + bundleUrl + '" class="button button-primary">Buy More Credits</a></p>' : '');

    var $modal = $(
      '<div id="khm-credit-modal" class="khm-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="khm-modal-title">' +
        '<div class="khm-modal-box">' +
          '<h3 id="khm-modal-title">Confirm Submission</h3>' +
          bodyHtml +
          '<div class="khm-modal-actions">' +
            (hasCredits ? '<button class="button button-primary khm-modal-confirm">Confirm &amp; Submit</button>' : '') +
            '<button class="button khm-modal-cancel">Cancel</button>' +
          '</div>' +
        '</div>' +
      '</div>'
    );

    $('body').append($modal);

    $modal.on('click', '.khm-modal-confirm', function () {
      $modal.remove();
      onConfirm();
    });

    $modal.on('click', '.khm-modal-cancel, .khm-modal-overlay', function (e) {
      if ($(e.target).is('.khm-modal-overlay, .khm-modal-cancel')) {
        $modal.remove();
      }
    });

    $(document).one('keydown.khmModal', function (e) {
      if (e.key === 'Escape') {
        $modal.remove();
      }
    });
  }

  $(document).on('input', '.khm-question textarea', function () {
    var words = wordCount($(this).val());
    var credits = creditsForWords(words);
    var $q = $(this).closest('.khm-question');
    $q.find('.khm-word-count').text(words + ' words');
    $q.find('.khm-credit-count').text(credits + ' credits');
    // Enable submit only when draft has been saved once.
    if (draftIds[getDraftKey($q)]) {
      $q.find('.khm-submit-commentary').prop('disabled', false);
    }
  });

  // Save draft (no credits consumed).
  $(document).on('click', '.khm-save-draft-btn', function () {
    var $btn      = $(this);
    var $question = $btn.closest('.khm-question');
    var $status   = $question.find('.khm-draft-status');
    var text      = $question.find('textarea').val();
    var questionId = $question.data('question-id');
    var sessionId  = $('.khm-quoteclub-detail').data('session-id');
    var key        = getDraftKey($question);
    var existingId = draftIds[key];

    if (!text.trim()) {
      $status.text('Nothing to save.').addClass('khm-status-error');
      return;
    }

    $btn.prop('disabled', true).text('Saving…');
    $status.text('').removeClass('khm-status-error khm-status-ok');

    var payload = { commentary_text: text, session_id: sessionId, question_id: questionId };

    var req = existingId
      ? api('commentary/' + existingId + '/draft', 'PUT', payload)
      : api('commentary/draft', 'POST', payload);

    req.done(function (res) {
      if (!existingId && res.draft_id) {
        draftIds[key] = res.draft_id;
        $question.find('.khm-submit-commentary').prop('disabled', false);
      }
      $btn.text('Save Draft');
      $btn.prop('disabled', false);
      $status.text('Draft saved.').addClass('khm-status-ok');
    }).fail(function () {
      $btn.text('Save Draft').prop('disabled', false);
      $status.text('Save failed — try again.').addClass('khm-status-error');
    });
  });

  // Confirm and submit (credits consumed here).
  $(document).on('click', '.khm-submit-commentary', function () {
    var $btn      = $(this);
    var $question = $btn.closest('.khm-question');
    var key       = getDraftKey($question);
    var draftId   = draftIds[key];

    if (!draftId) {
      alert('Please save a draft first.');
      return;
    }

    var words          = wordCount($question.find('textarea').val());
    var creditsNeeded  = creditsForWords(words);
    var creditsAvail   = window.khmQuoteClub && khmQuoteClub.editorialCredits
      ? parseInt(khmQuoteClub.editorialCredits, 10)
      : 0;

    showCreditModal(creditsNeeded, creditsAvail, function () {
      $btn.prop('disabled', true).text('Submitting…');

      api('commentary/' + draftId + '/confirm', 'POST', { is_press_release: false })
        .done(function (res) {
          $btn.text('Submitted').prop('disabled', true);
          $question.find('.khm-draft-status')
            .text('Submitted for editorial review. Credits used: ' + res.credits_used)
            .addClass('khm-status-ok');
          // Update displayed balance.
          if (res.new_editorial_balance !== undefined && window.khmQuoteClub) {
            khmQuoteClub.editorialCredits = res.new_editorial_balance;
            $('.khm-qc-editorial-credits').text(res.new_editorial_balance + ' editorial credits');
          }
          $question.find('textarea').prop('disabled', true);
          $question.find('.khm-save-draft-btn').prop('disabled', true);
        })
        .fail(function (xhr) {
          $btn.text('Submit').prop('disabled', false);
          var res = xhr.responseJSON || {};
          if (res.error === 'insufficient_editorial_credits') {
            var needed = res.credits_needed || creditsNeeded;
            var avail  = res.credits_available || 0;
            showCreditModal(needed, avail, function () {});
          } else {
            $question.find('.khm-draft-status')
              .text('Submission failed: ' + (res.error || 'unknown error'))
              .addClass('khm-status-error').removeClass('khm-status-ok');
          }
        });
    });
  });

  $(document).on('click', '.khm-invite-retry-btn', function () {
    if (!inviteRetryPayload) {
      return;
    }

    acceptInvite(inviteRetryPayload);
  });

  $(function () {
    if (!$('.khm-quoteclub').length) {
      return;
    }

    loadSavedSearches();
    maybeAcceptInviteFromUrl();

    var today = new Date();
    var plus42 = new Date(today.getTime() + 42 * 24 * 60 * 60 * 1000);
    $('.khm-filter-date-from').val(today.toISOString().slice(0, 10));
    $('.khm-filter-date-to').val(plus42.toISOString().slice(0, 10));

    $('.khm-quoteclub-search-btn').trigger('click');
  });
})(jQuery);
