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
    return Math.ceil(words / 100);
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
          '<button class="button button-primary khm-submit-commentary">Submit</button>' +
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

  $(document).on('input', '.khm-question textarea', function () {
    var words = wordCount($(this).val());
    var credits = creditsForWords(words);
    var $meta = $(this).closest('.khm-question-meta');
    $meta.find('.khm-word-count').text(words + ' words');
    $meta.find('.khm-credit-count').text(credits + ' credits');
  });

  $(document).on('click', '.khm-submit-commentary', function () {
    var $question = $(this).closest('.khm-question');
    var text = $question.find('textarea').val();
    var questionId = $question.data('question-id');
    var sessionId = $('.khm-quoteclub-detail').data('session-id');

    api('commentary', 'POST', {
      session_id: sessionId,
      question_id: questionId,
      commentary_text: text,
      is_press_release: false
    }).done(function (res) {
      alert('Submitted. Credits used: ' + res.credits_used);
      $question.find('textarea').val('').trigger('input');
    }).fail(function (xhr) {
      var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Submission failed';
      alert(msg);
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
