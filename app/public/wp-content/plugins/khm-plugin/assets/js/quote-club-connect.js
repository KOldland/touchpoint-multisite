(function ($) {
  'use strict';

  // Toast notification system (local version for quote-club-connect.js)
  // Uses global marker to check if global showToast exists (to avoid duplicates)
  if (typeof window._khm_showToastUsed === 'undefined') {
    window._khm_showToastUsed = true;
    if (typeof window.khm_showToast === 'undefined') {
      window.showToast = window.khm_showToast = function (message, type) {
        var $toast = $('#khm-partner-toast');
        
        if (!$toast.length) {
          $toast = $('<div id="khm-partner-toast" class="khm-toast" role="status" aria-live="polite"></div>');
          $('body').append($toast);
        }

        var toastClass = type === 'error' ? 'khm-toast-error' : type === 'success' ? 'khm-toast-success' : 'khm-toast-info';
        // Clear and reset toast class
        $toast.empty().removeClass('khm-toast-error khm-toast-success khm-toast-info is-visible')
          .addClass(toastClass + ' is-visible');
        $toast.text(message || '');

        setTimeout(function () {
          $toast.removeClass('khm-toast-error khm-toast-success khm-toast-info is-visible')
            .addClass('khm-toast');
        }, 5000);
      };
    }
  }

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
      url: (window.khmPartnerPortal && khmPartnerPortal.connectRestUrl ? khmPartnerPortal.connectRestUrl : '') + path,
      method: method,
      contentType: method === 'GET' || method === 'DELETE' ? undefined : 'application/json',
      processData: method === 'GET',
      headers: {
        'X-WP-Nonce': window.khmPartnerPortal ? khmPartnerPortal.nonce : ''
      },
      dataType: 'json',
      data: method === 'GET' ? (payload || {}) : (payload ? JSON.stringify(payload) : undefined)
    });
  }

  function formatGbp(value) {
    var amount = Number(value || 0);
    return '\u00a3' + amount.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function stripUrlsFromText(text) {
    return String(text || '').replace(/https?:\/\/[^\s]+/gi, '').replace(/\s{2,}/g, ' ').trim();
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
    console.log('Quote Club Connect JS v1.0.2 Loaded');
    // Get the parent container with sponsor-id data attribute
    var $accountShell = $('#khm-partner-account-form').closest('[data-sponsor-id]');
    var $shell = $accountShell.length ? $accountShell : $('.khm-partner-connect-shell');
    if (!$shell.length) {
      return;
    }

    var $form = $('#khm-partner-connect-form');
    var $list = $shell.find('.khm-partner-connect-list');
    var $status = $shell.find('.khm-partner-connect-status');
    var $deleteButton = $shell.find('.khm-partner-connect-delete');
    var $threadList = $shell.find('.khm-partner-connect-thread-list');
    var $threadDetail = $shell.find('.khm-partner-connect-thread-detail');
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
      $form.find('[name="rfq_default_scope"]').val('fsm_evaluation_poc');
      $form.find('[name="rfq_default_seats"]').val('20_30');
      $form.find('[name="rfq_default_timeframe"]').val('3_months');
      $form.find('[name="rfq_default_cpl_gbp"]').val('325');
      $form.find('[name="rfq_supported_features"]').val('mobile_app,offline_capabilities,real_time_reporting');
      $form.find('[name="rfq_default_estimate_gbp"]').val('120000');
      $form.find('[name="rfq_max_discount_pct"]').val('10');
      $form.find('[name="comparison_fields"]').val('{}');
      $form.find('[name="match_rules"]').val('{}');
      $deleteButton.hide();
      renderList();
      clearStatus();
    }

    function populateForm(provider) {
      $form.find('[name="id"]').val(activeId ? String(activeId) : '');
      $form.find('[name="company_name"]').val(provider.company_name || '');
      $form.find('[name="offering_name"]').val(provider.offering_name || '');
      $form.find('[name="website_url"]').val(provider.website_url || '');
      $form.find('[name="provider_type"]').val(provider.provider_type || '');
      // Handle multi-select checkboxes for provider_type
      $form.find('input[name="provider_type[]"]').prop('checked', false);
      if (Array.isArray(provider.provider_type)) {
        provider.provider_type.forEach(function(type) {
          $form.find('input[name="provider_type[]"][value="' + type + '"]').prop('checked', true);
        });
      }
      $form.find('[name="description"]').val(provider.description || '');
      $form.find('[name="sweet_spot_summary"]').val(provider.sweet_spot_summary || '');
      // Populate sub-category checkbox fields
      $form.find('input[name="software_expertise[]"]').prop('checked', false);
      if (Array.isArray(provider.software_expertise)) {
        provider.software_expertise.forEach(function(expertise) {
          $form.find('input[name="software_expertise[]"][value="' + expertise + '"]').prop('checked', true);
        });
      }
      $form.find('input[name="hardware_capabilities[]"]').prop('checked', false);
      if (Array.isArray(provider.hardware_capabilities)) {
        provider.hardware_capabilities.forEach(function(capability) {
          $form.find('input[name="hardware_capabilities[]"][value="' + capability + '"]').prop('checked', true);
        });
      }
      $form.find('input[name="consultancy_areas[]"]').prop('checked', false);
      if (Array.isArray(provider.consultancy_areas)) {
        provider.consultancy_areas.forEach(function(area) {
          $form.find('input[name="consultancy_areas[]"][value="' + area + '"]').prop('checked', true);
        });
      }
      var rfqProfile = provider.comparison_fields && provider.comparison_fields.rfq_profile ? provider.comparison_fields.rfq_profile : {};
      $form.find('[name="titles"]').val(Array.isArray(provider.titles) ? provider.titles.join(', ') : '');
      $form.find('[name="regions"]').val(Array.isArray(provider.regions) ? provider.regions.join(', ') : '');
      $form.find('[name="industries"]').val(Array.isArray(provider.industries) ? provider.industries.join(', ') : (provider.match_rules && provider.match_rules.industries ? provider.match_rules.industries.join(', ') : ''));
      $form.find('[name="deployment_modes"]').val(Array.isArray(provider.deployment_modes) ? provider.deployment_modes.join(', ') : '');
      $form.find('[name="integrations"]').val(Array.isArray(provider.integrations) ? provider.integrations.join(', ') : '');
      $form.find('[name="support_tiers"]').val(Array.isArray(provider.support_tiers) ? provider.support_tiers.join(', ') : '');
      $form.find('[name="company_size_min"]').val(provider.company_size_min || '');
      $form.find('[name="company_size_max"]').val(provider.company_size_max || '');
      $form.find('[name="budget_min"]').val(provider.budget_min || '');
      $form.find('[name="budget_max"]').val(provider.budget_max || '');
      $form.find('[name="onboarding_days"]').val(provider.onboarding_days || '');
      $form.find('[name="rfq_default_scope"]').val(rfqProfile.default_scope || 'fsm_evaluation_poc');
      $form.find('[name="rfq_default_seats"]').val(rfqProfile.default_seats || '20_30');
      $form.find('[name="rfq_default_timeframe"]').val(rfqProfile.default_timeframe || '3_months');
      $form.find('[name="rfq_default_cpl_gbp"]').val(rfqProfile.default_cpl_gbp || 325);
      $form.find('[name="rfq_supported_features"]').val(Array.isArray(rfqProfile.supported_features) ? rfqProfile.supported_features.join(', ') : 'mobile_app,offline_capabilities,real_time_reporting');
      $form.find('[name="rfq_default_estimate_gbp"]').val(rfqProfile.default_estimate_gbp || 120000);
      $form.find('[name="rfq_max_discount_pct"]').val(rfqProfile.max_discount_pct || 10);
      $form.find('[name="status"]').val(provider.status || 'active');
      $form.find('[name="pilot_scheme_available"]').prop('checked', !!provider.pilot_scheme_available);
      $form.find('[name="free_trial_available"]').prop('checked', !!provider.free_trial_available);
      $form.find('[name="commentary_enabled"]').prop('checked', !!provider.commentary_enabled);
      $form.find('[name="ad_targeting_enabled"]').prop('checked', !!provider.ad_targeting_enabled);
      $form.find('[name="comparison_fields"]').val(JSON.stringify(provider.comparison_fields || {}, null, 2));
      $form.find('[name="match_rules"]').val(JSON.stringify(provider.match_rules || {}, null, 2));
      $deleteButton.show();
      renderList();
    }

    function renderList() {
      if (!providers.length) {
        $list.html('<div class="khm-partner-connect-empty">No Connect offerings yet. Create your first one to define how your sponsor appears in comparison and matching flows.</div>');
        return;
      }

      var html = providers.map(function (provider) {
        var isSelected = activeId && parseInt(provider.id, 10) === activeId;
        var tags = [];
        var budgetRange = formatRange(provider.budget_min, provider.budget_max, '$');
        var companyRange = formatRange(provider.company_size_min, provider.company_size_max, '');

        if (provider.provider_type) {
          tags.push('<span class="khm-partner-connect-pill">' + esc(provider.provider_type) + '</span>');
        }
        if (provider.status) {
          tags.push('<span class="khm-partner-connect-pill">' + esc(provider.status) + '</span>');
        }
        if (provider.is_demo) {
          tags.push('<span class="khm-partner-connect-pill">demo</span>');
        }
        if (provider.commentary_enabled) {
          tags.push('<span class="khm-partner-connect-pill">commentary</span>');
        }
        if (provider.ad_targeting_enabled) {
          tags.push('<span class="khm-partner-connect-pill">ad targeting</span>');
        }

        return '' +
          '<article class="khm-partner-connect-card' + (isSelected ? ' is-selected' : '') + '" data-provider-id="' + esc(provider.id) + '">' +
            '<div class="khm-partner-connect-card-head">' +
              '<div>' +
                '<h4>' + esc(provider.name) + '</h4>' +
                '<div class="khm-partner-connect-card-meta">' + esc(provider.slug || '') + (provider.website_url ? ' · ' + esc(provider.website_url) : '') + '</div>' +
              '</div>' +
              '<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-connect-edit" data-provider-id="' + esc(provider.id) + '">Edit</button>' +
            '</div>' +
            (tags.length ? '<div class="khm-partner-connect-card-tags">' + tags.join('') + '</div>' : '') +
            (provider.sweet_spot_summary ? '<p class="khm-partner-connect-card-summary">' + esc(provider.sweet_spot_summary) + '</p>' : '') +
            ((budgetRange || companyRange || provider.onboarding_days) ? '<p class="khm-partner-connect-card-meta">' +
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
        $threadList.html('<div class="khm-partner-connect-empty">No intro requests yet. New prospect requests will appear here.</div>');
        return;
      }

      $threadList.html(threads.map(function (thread) {
        var isSelected = activeThreadId && parseInt(thread.id, 10) === activeThreadId;
        var meta = [thread.provider_name, formatDate(thread.latest_message_at || thread.created_at)].filter(Boolean).join(' · ');

        // Build compact at-a-glance tags (channel, handover state, commercial, messages)
        var sellerResponseStatus = thread.seller_response_status || 'not_requested';
        var isRfq = thread.request_type === 'rfq_request';
        var isDiscoveryCall = thread.request_type === 'discovery_call';
        var cardBtnLabel = (isRfq && (sellerResponseStatus === 'awaiting_response' || sellerResponseStatus === 'not_requested'))
          ? 'Move to Inbox after reply'
          : 'Open';
        var handoverLabelMap = {
          not_started: 'Handover Not Started',
          buyer_requested: 'Prospect Handover Requested',
          confirmed: 'Handover Accepted'
        };
        var tierLabelMap = {
          exploring: 'Exploring',
          assessing: 'Assessing',
          accelerating: 'Accelerating',
          engaged: 'Engaged'
        };
        var commercialTier = String(thread.commercial_tier || '').toLowerCase();
        var isActiveMatch = !isRfq && !isDiscoveryCall && (!!thread.engaged_option || !!commercialTier);
        var channelLabel = isRfq ? 'RFQ' : (isDiscoveryCall ? 'Discovery Call' : (isActiveMatch ? 'Active Match' : 'Inbound Connection'));
        var handoverLabel = handoverLabelMap[thread.handover_status || 'not_started'] || 'Handover Not Started';

        var commercialLabel = '';
        if (isActiveMatch) {
          commercialLabel = tierLabelMap[commercialTier] || 'Engaged';
        } else {
          commercialLabel = thread.seller_commission_rate
            ? ('Platform Discount Offered ' + esc(thread.seller_commission_rate) + '%')
            : 'Platform Discount off';
        }

        var engagementTags = '' +
          '<span class="khm-partner-connect-pill khm-partner-connect-pill-engaged">' + esc(channelLabel) + '</span>' +
          '<span class="khm-partner-connect-pill">' + esc(handoverLabel) + '</span>' +
          '<span class="khm-partner-connect-pill">' + esc(commercialLabel) + '</span>' +
          '<span class="khm-partner-connect-pill">' + esc(thread.message_count || 0) + ' messages</span>';

        // Hint shown on RFQ cards before the seller submits a response
        var rfqHint = (isRfq && (sellerResponseStatus === 'awaiting_response' || sellerResponseStatus === 'not_requested'))
          ? '<p class="khm-partner-connect-card-hint">Open this thread, write your light proposal reply, and submit it — the prospect will then be able to accept or reject it to proceed to full handover.</p>'
          : '';

        var cardTitle = thread.handover_status === 'confirmed'
          ? ([thread.buyer_name, thread.buyer_company].filter(Boolean).join(', ') || 'Contact')
          : ([thread.buyer_job_title, thread.buyer_sector].filter(Boolean).join(', ') || 'Contact');

        return '' +
          '<article class="khm-partner-connect-card khm-partner-connect-thread-card' + (isSelected ? ' is-selected' : '') + '" data-thread-id="' + esc(thread.id) + '">' +
            '<div class="khm-partner-connect-card-head">' +
              '<div>' +
                '<h4>' + esc(cardTitle) + '</h4>' +
                '<div class="khm-partner-connect-card-meta">' + esc(meta) + '</div>' +
              '</div>' +
              '<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-connect-open-thread" data-thread-id="' + esc(thread.id) + '">' + cardBtnLabel + '</button>' +
            '</div>' +
            '<div class="khm-partner-connect-card-tags">' +
              engagementTags +
            '</div>' +
            (thread.last_message_excerpt ? '<p class="khm-partner-connect-card-summary">' + esc(stripUrlsFromText(thread.last_message_excerpt)) + '</p>' : '') +
            rfqHint +
          '</article>';
      }).join(''));
    }

    var rfqLinkStore = {}; // Store RFQ links in memory, keyed by button ID
    var rfqLinkCounter = 0;

    function extractAndObfuscateRfqLink(message) {
      if (!message) {
        return { text: message, link: null, linkId: null };
      }
      var urlPattern = /https?:\/\/[^\s]+(?:rfq-pack|\.local)[^\s]*/gi;
      var match = message.match(urlPattern);
      if (!match || !match.length) {
        return { text: message, link: null, linkId: null };
      }
      var link = match[0];
      var text = message.replace(urlPattern, '').replace(/\s+at\s+this\s+link\s+/gi, '').replace(/:\s*$/, '.');
      var linkId = 'rfq-link-' + (++rfqLinkCounter);
      rfqLinkStore[linkId] = link;
      return { text: text.trim(), link: link, linkId: linkId };
    }

    function classifyLinkCta(messageText, link) {
      var text = String(messageText || '').toLowerCase();
      var href = String(link || '').toLowerCase();
      if (href.indexOf('rfq-pack') !== -1 || text.indexOf('rfp') !== -1) {
        return { type: 'rfq', label: 'Complete Full RFQ' };
      }
      if (/calendly|meet|schedule|booking|calendar/.test(href) || /meeting|schedule|calendar/.test(text)) {
        return { type: 'meeting', label: 'Arrange Meeting' };
      }
      return { type: 'link', label: 'Open Shared Link' };
    }

    function getPostHandoverCta(messages) {
      if (!Array.isArray(messages) || !messages.length) {
        return null;
      }

      for (var i = messages.length - 1; i >= 0; i -= 1) {
        var current = messages[i] || {};
        var messageText = current.message || '';
        var extracted = extractAndObfuscateRfqLink(messageText);
        if (extracted && extracted.linkId) {
          var cta = classifyLinkCta(messageText, extracted.link);
          return {
            linkId: extracted.linkId,
            label: cta.label,
            type: cta.type
          };
        }
      }

      return null;
    }

    function renderThreadMessages(thread, messages, handoverStatus) {
      if (!Array.isArray(messages) || !messages.length) {
        return '<div class="khm-partner-connect-empty">No messages yet.</div>';
      }

      return messages.map(function (message) {
        var role = message.sender_role === 'sponsor' ? 'You' : 'Prospect';
        var messageText = message.message || '';
        var extracted = extractAndObfuscateRfqLink(messageText);
        var cleanMessage = extracted.text;

        return '' +
          '<article class="khm-partner-connect-message khm-partner-connect-message-' + esc(message.sender_role || 'buyer') + '">' +
            '<div class="khm-partner-connect-message-meta">' + esc(role) + ' · ' + esc(formatDate(message.created_at)) + '</div>' +
            '<div class="khm-partner-connect-message-body">' + esc(cleanMessage) + '</div>' +
          '</article>';
      }).join('');
    }

    function renderProspectDetailsPanel(thread, handoverStatus) {
      var sector = (thread && thread.buyer_sector) ? thread.buyer_sector : '';
      var companySize = (thread && thread.buyer_company_size) ? thread.buyer_company_size : '';
      var jobTitle = (thread && thread.buyer_job_title) ? thread.buyer_job_title : '';
      var city = (thread && thread.buyer_city) ? thread.buyer_city : '';
      var country = (thread && thread.buyer_country) ? thread.buyer_country : '';

      if (handoverStatus !== 'confirmed') {
        return '' +
          '<div class="khm-partner-connect-prospect-details">' +
            '<h5>Prospect details</h5>' +
            '<div class="khm-partner-connect-resp-row"><strong>Sector:</strong> ' + esc(sector || 'Not provided') + '</div>' +
            '<div class="khm-partner-connect-resp-row"><strong>Company size:</strong> ' + esc(companySize || 'Not provided') + '</div>' +
            '<div class="khm-partner-connect-resp-row"><strong>Country:</strong> ' + esc(country || 'Not provided') + '</div>' +
            '<div class="khm-partner-connect-resp-row"><strong>Position:</strong> ' + esc(jobTitle || 'Not provided') + '</div>' +
          '</div>';
      }

      var name = thread && thread.buyer_name ? thread.buyer_name : 'Not provided';
      var company = thread && thread.buyer_company ? thread.buyer_company : 'Not provided';
      var email = thread && thread.buyer_email ? thread.buyer_email : '';
      var phone = thread && thread.buyer_phone ? thread.buyer_phone : '';
      var linkedin = thread && thread.buyer_linkedin ? thread.buyer_linkedin : '';
      var location = [city, country].filter(Boolean).join(', ');

      var emailHtml = email
        ? '<a href="mailto:' + esc(email) + '">' + esc(email) + '</a>'
        : 'Not submitted yet';
      var phoneRow = phone
        ? '<div class="khm-partner-connect-resp-row"><strong>Phone:</strong> <a href="tel:' + esc(phone) + '">' + esc(phone) + '</a></div>'
        : '';
      var linkedinRow = linkedin
        ? '<div class="khm-partner-connect-resp-row"><strong>LinkedIn:</strong> <a href="' + esc(linkedin) + '" target="_blank" rel="noopener noreferrer">View profile</a></div>'
        : '';

      return '' +
        '<div class="khm-partner-connect-prospect-details khm-partner-connect-prospect-details-live">' +
          '<h5>Contact information</h5>' +
          '<div class="khm-partner-connect-resp-row"><strong>Name:</strong> ' + esc(name) + '</div>' +
          '<div class="khm-partner-connect-resp-row"><strong>Job title:</strong> ' + esc(jobTitle || 'Not provided') + '</div>' +
          '<div class="khm-partner-connect-resp-row"><strong>Email:</strong> ' + emailHtml + '</div>' +
          phoneRow +
          linkedinRow +
          '<h5 class="khm-partner-connect-sub-heading">Company information</h5>' +
          '<div class="khm-partner-connect-resp-row"><strong>Company name:</strong> ' + esc(company) + '</div>' +
          '<div class="khm-partner-connect-resp-row"><strong>Sector:</strong> ' + esc(sector || 'Not provided') + '</div>' +
          '<div class="khm-partner-connect-resp-row"><strong>Total employees:</strong> ' + esc(companySize || 'Not provided') + '</div>' +
          '<div class="khm-partner-connect-resp-row"><strong>Location:</strong> ' + esc(location || 'Not provided') + '</div>' +
        '</div>';
    }

    function renderDiscoveryCallPanel(thread) {
      var status = thread.seller_response_status || 'not_requested';

      if (status === 'accepted') {
        var schedulingLink = thread.scheduling_link || '';
        return '<div class="khm-partner-connect-seller-response khm-partner-connect-seller-response-submitted">' +
          '<h5>Discovery Call Accepted</h5>' +
          '<p class="khm-partner-connect-thread-meta">You accepted this discovery call request. The prospect can now book a time.</p>' +
          (schedulingLink ? '<div class="khm-partner-connect-resp-row"><a href="' + esc(schedulingLink) + '" target="_blank" class="khm-partner-btn khm-partner-btn-secondary">View Booking Link</a></div>' : '') +
        '</div>';
      }

      return '<div class="khm-partner-connect-seller-response">' +
        '<h5>Accept Discovery Call</h5>' +
        '<p class="khm-partner-connect-thread-meta">Accept this discovery call request to charge the prospect and reveal their scheduling link.</p>' +
        '<div class="khm-partner-connect-actions">' +
          '<button type="button" class="khm-partner-btn khm-partner-btn-primary khm-partner-accept-discovery-call" data-thread-id="' + esc(thread.id) + '">Accept & Charge</button>' +
        '</div>' +
      '</div>';
    }

    function renderSellerResponsePanel(thread) {
      var status = thread.seller_response_status || 'not_requested';

      // Only show for RFQ threads
      if (thread.request_type !== 'rfq_request') {
        return '';
      }

      if (status === 'submitted') {
        var resp = thread.seller_initial_response || {};
        var rate = thread.seller_commission_rate ? thread.seller_commission_rate + '%' : 'not set';
        var fields = [
          { label: 'Capability', value: resp.capability },
          { label: 'Cost range', value: resp.cost_range },
          { label: 'Approach', value: resp.approach },
          { label: 'Timeline', value: resp.timeline },
          { label: 'Platform Discount Offered', value: rate }
        ].filter(function (f) { return f.value; });
        var leadContact = resp.lead_contact || {};

        return '<div class="khm-partner-connect-seller-response khm-partner-connect-seller-response-submitted">' +
          '<h5>RFQ Response submitted</h5>' +
          fields.map(function (f) {
            return '<div class="khm-partner-connect-resp-row"><strong>' + esc(f.label) + ':</strong> ' + esc(f.value) + '</div>';
          }).join('') +
          (leadContact.name ? '<div class="khm-partner-connect-resp-row"><strong>Lead contact:</strong> ' + esc(leadContact.name) + (leadContact.title ? ', ' + esc(leadContact.title) : '') + '</div>' : '') +
        '</div>';
      }

      // Show response form if not yet submitted
      return '<div class="khm-partner-connect-seller-response">' +
        '<h5>Submit your RFQ response</h5>' +
        '<p class="khm-partner-connect-thread-meta">Provide a structured response to this prospect\'s RFQ. Once submitted the prospect can accept or reject it.</p>' +
        '<form class="khm-partner-connect-seller-response-form" data-thread-id="' + esc(thread.id) + '">' +
          '<label>Capability summary<textarea name="capability" rows="3" required placeholder="Describe how your offering meets the prospect\'s requirements."></textarea></label>' +
          '<label>Cost range<input type="text" name="cost_range" placeholder="e.g. £50K\u2013£80K implementation + £30K annual"></label>' +
          '<label>Approach / methodology<textarea name="approach" rows="3" placeholder="How would you deliver this engagement?"></textarea></label>' +
          '<label>Timeline<input type="text" name="timeline" placeholder="e.g. 8\u201312 weeks from contract signature"></label>' +
          '<label>Lead contact name<input type="text" name="lead_name"></label>' +
          '<label>Lead contact email<input type="email" name="lead_email"></label>' +
          '<label>Lead contact title<input type="text" name="lead_title"></label>' +
          '<label>Platform Discount Offered (5\u201325%)<input type="number" name="commission_rate" min="5" max="25" step="1" required placeholder="e.g. 10"></label>' +
          '<div class="khm-partner-connect-actions">' +
            '<button type="submit" class="khm-partner-btn khm-partner-btn-primary">Submit RFQ response</button>' +
          '</div>' +
        '</form>' +
      '</div>';
    }

    function renderInboundCommissionPanel(thread) {
      var isRfq = thread.request_type === 'rfq_request';
      var isActiveMatch = !!thread.engaged_option || !!thread.commercial_tier;

      if (isRfq || isActiveMatch) {
        return '';
      }

      var currentRate = parseInt(thread.seller_commission_rate || 0, 10);
      return '<div class="khm-partner-connect-seller-response">' +
        '<h5>Set inbound commission before handover</h5>' +
        '<p class="khm-partner-connect-thread-meta">Choose your platform commission now. The payment moment becomes explicit at handover confirmation.</p>' +
        '<form class="khm-partner-connect-inbound-commission-form" data-thread-id="' + esc(thread.id) + '">' +
          '<label>Commission rate (5-25%)<input type="number" name="commission_rate" min="5" max="25" step="1" required value="' + esc(currentRate > 0 ? currentRate : 10) + '"></label>' +
          '<div class="khm-partner-connect-actions">' +
            '<button type="submit" class="khm-partner-btn khm-partner-btn-primary">Save commission rate</button>' +
          '</div>' +
        '</form>' +
      '</div>';
    }

    function renderThreadDetail(thread, messages, handover) {
      if (!$threadDetail.length) {
        return;
      }

      if (!thread) {
        $threadDetail.html('<div class="khm-partner-connect-empty">Select an intro thread to review messages, reply, and manage handover.</div>');
        return;
      }

      var handoverStatus = handover && handover.status ? handover.status : (thread.handover_status || 'not_started');
      var isRfqThread = thread.request_type === 'rfq_request';
      var isActiveMatchThread = !!thread.engaged_option || !!thread.commercial_tier;
      var showInlineCommission = handoverStatus === 'buyer_requested' && !isRfqThread && !isActiveMatchThread;
      var currentCommRate = parseInt(thread.seller_commission_rate || 0, 10) || 10;
      var hasDiscount = !!(thread.seller_commission_rate);
      var inlineCommissionHtml = showInlineCommission
        ? '<div class="khm-partner-connect-discount-panel">' +
            '<div class="khm-partner-rfq-response-field">' +
              '<label>Commercial breakdown</label>' +
              '<div class="khm-partner-rfq-calc-card">' +
                '<div class="khm-partner-rfq-breakdown-row"><span>Payable today</span><strong class="khm-partner-connect-flat-fee">' + (hasDiscount ? '£375.00' : '£1,500.00') + '</strong></div>' +
                '<div class="khm-partner-rfq-breakdown-row">' +
                  '<label class="khm-partner-rfq-toggle">' +
                    '<input type="checkbox" class="khm-partner-connect-discount-toggle"' + (hasDiscount ? ' checked' : '') + ' />' +
                    '<span>Offer platform discount</span>' +
                  '</label>' +
                '</div>' +
                '<div class="khm-partner-connect-discount-controls"' + (hasDiscount ? '' : ' hidden') + '>' +
                  '<div class="khm-partner-rfq-response-field" style="padding:8px 0 0;">' +
                    '<label>Discount / commission rate (%)</label>' +
                    '<input type="range" min="5" max="25" step="1" value="' + currentCommRate + '" class="khm-partner-rfq-commission-rate" name="commission_rate" />' +
                    '<div class="khm-partner-rfq-discount-readout"><span>Selected rate: <strong class="khm-partner-rfq-rate-value">' + currentCommRate + '%</strong></span><span>This total rate is split 50/50 between estimated buyer discount and estimated platform commission.</span></div>' +
                  '</div>' +
                  '<div class="khm-partner-connect-commission-breakdown">' +
                    '<div class="khm-partner-rfq-breakdown-row"><span>Buyer discount</span><span class="khm-partner-connect-buyer-discount-readout">' + (currentCommRate / 2) + '% of contract value</span></div>' +
                    '<div class="khm-partner-rfq-breakdown-row"><span>Platform commission</span><span class="khm-partner-connect-commission-rate-readout">' + (currentCommRate / 2) + '% of contract value</span></div>' +
                  '</div>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>'
        : '';
      var handoverLabelMap = {
        not_started: 'Handover Not Started',
        buyer_requested: 'Handover Requested',
        confirmed: 'Handover Accepted'
      };
      var handoverMeta = [];
      var confirmDisabled = handoverStatus !== 'buyer_requested' ? ' disabled' : '';
      var postHandoverCta = getPostHandoverCta(messages || []);
      var handoverActionButton = '';

      if (handoverStatus === 'buyer_requested') {
        handoverActionButton = '<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-connect-confirm-handover" data-thread-id="' + esc(thread.id) + '"' + confirmDisabled + '>Accept handover</button>';
      } else if (handoverStatus === 'confirmed' && postHandoverCta && postHandoverCta.linkId) {
        handoverActionButton = '<button type="button" class="khm-partner-btn khm-partner-btn-secondary khm-partner-rfq-link-btn" data-link-id="' + esc(postHandoverCta.linkId) + '">' + esc(postHandoverCta.label) + '</button>';
      }

      if (handover && handover.requested_at) {
        handoverMeta.push('Requested ' + formatDate(handover.requested_at));
      }
      if (handover && handover.confirmed_at) {
        handoverMeta.push('Confirmed ' + formatDate(handover.confirmed_at));
      }

      $threadDetail.html(
        '<div class="khm-partner-connect-thread-head">' +
          '<div>' +
            '<h4>' + esc(thread.buyer_name || 'Contact') + '</h4>' +
            '<p>' + esc(thread.provider_name || '') + (thread.buyer_company ? ' · ' + esc(thread.buyer_company) : '') + '</p>' +
          '</div>' +
          '<div class="khm-partner-connect-card-tags">' +
            '<span class="khm-partner-connect-pill">' + esc(thread.status || 'open') + '</span>' +
            '<span class="khm-partner-connect-pill">' + esc(handoverLabelMap[handoverStatus] || ('Handover ' + handoverStatus)) + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="khm-partner-connect-thread-meta">' + esc(handoverMeta.join(' · ')) + '</div>' +
        renderProspectDetailsPanel(thread, handoverStatus) +
        '<div class="khm-partner-connect-thread-messages">' + renderThreadMessages(thread, messages, handoverStatus) + '</div>' +
        '<form class="khm-partner-connect-thread-reply" data-thread-id="' + esc(thread.id) + '">' +
          '<label for="khm-partner-connect-thread-reply-message">Reply</label>' +
          '<textarea id="khm-partner-connect-thread-reply-message" name="message" rows="4" placeholder="Reply to the prospect. This message will be relayed through the platform." required></textarea>' +
          inlineCommissionHtml +
          '<div class="khm-partner-connect-actions">' +
            '<button type="submit" class="khm-partner-btn khm-partner-btn-primary">Send reply</button>' +
            handoverActionButton +
          '</div>' +
        '</form>' +
        renderSellerResponsePanel(thread)
      );
    }

    function collectPayload() {
      var comparisonFields = parseJsonField($form.find('[name="comparison_fields"]').val());
      comparisonFields.rfq_profile = {
        default_scope: $form.find('[name="rfq_default_scope"]').val() || 'fsm_evaluation_poc',
        default_seats: $form.find('[name="rfq_default_seats"]').val() || '20_30',
        default_timeframe: $form.find('[name="rfq_default_timeframe"]').val() || '3_months',
        default_cpl_gbp: Number($form.find('[name="rfq_default_cpl_gbp"]').val() || 0),
        supported_features: splitList($form.find('[name="rfq_supported_features"]').val()),
        default_estimate_gbp: Number($form.find('[name="rfq_default_estimate_gbp"]').val() || 0),
        max_discount_pct: Number($form.find('[name="rfq_max_discount_pct"]').val() || 0)
      };

      var payload = {
        offering_name: $form.find('[name="offering_name"]').val().trim(),
        website_url: $form.find('[name="website_url"]').val().trim(),
        description: $form.find('[name="description"]').val().trim(),
        sweet_spot_summary: $form.find('[name="sweet_spot_summary"]').val().trim(),
        titles: splitList($form.find('[name="titles"]').val()),
        regions: $form.find('[name="regions"]').val(), // Directly get array of selected values
        industries: splitList($form.find('[name="industries"]').val()),
        deployment_modes: splitList($form.find('[name="deployment_modes"]').val()),
        integrations: splitList($form.find('[name="integrations"]').val()),
        support_tiers: splitList($form.find('[name="support_tiers"]').val()),
        company_size_min: $form.find('[name="company_size_min"]').val(),
        company_size_max: $form.find('[name="company_size_max"]').val(),
        budget_min: $form.find('[name="budget_min"]').val(),
        budget_max: $form.find('[name="budget_max"]').val(),
        onboarding_days: $form.find('[name="onboarding_days"]').val(),
        status: $form.find('[name="status"]').val() || 'active',
        pilot_scheme_available: $form.find('[name="pilot_scheme_available"]').is(':checked'),
        free_trial_available: $form.find('[name="free_trial_available"]').is(':checked'),
        commentary_enabled: $form.find('[name="commentary_enabled"]').is(':checked'),
        ad_targeting_enabled: $form.find('[name="ad_targeting_enabled"]').is(':checked'),
        comparison_fields: comparisonFields,
        match_rules: parseJsonField($form.find('[name="match_rules"]').val())
      };

      // Conditionally add sub-category fields based on selected provider types
      if (payload.provider_type.includes('software-expertise')) {
        payload.software_expertise = $form.find('input[name="software_expertise[]"]:checked').map(function() { return $(this).val(); }).get();
      }
      if (payload.provider_type.includes('hardware-capabilities')) {
        payload.hardware_capabilities = $form.find('input[name="hardware_capabilities[]"]:checked').map(function() { return $(this).val(); }).get();
      }
      if (payload.provider_type.includes('consultancy-areas')) {
        payload.consultancy_areas = $form.find('input[name="consultancy_areas[]"]:checked').map(function() { return $(this).val(); }).get();
      }

      return payload;
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
        $threadList.html('<div class="khm-partner-connect-empty">Unable to load intro threads right now.</div>');
      });
    }

    function openThread(threadId) {
      threadId = parseInt(threadId, 10) || 0;
      if (!threadId) {
        return;
      }

      activeThreadId = threadId;
      renderThreads();

      $threadDetail.html('<div class="khm-partner-connect-empty">Loading intro thread...</div>');

      request('intro-threads/mine/' + threadId, 'GET').done(function (res) {
        renderThreadDetail(res && res.thread, res && res.messages, res && res.handover);
      }).fail(function () {
        $threadDetail.html('<div class="khm-partner-connect-empty">Unable to load this intro thread.</div>');
      });
    }

    $shell.on('click', '.khm-partner-connect-new, .khm-partner-connect-reset', function () {
      resetForm();
    });

    $shell.on('click', '.khm-partner-connect-edit', function () {
      var providerId = parseInt($(this).data('provider-id'), 10);
      var provider = providers.find(function (item) {
        return parseInt(item && item.id, 10) === providerId;
      });

      if (provider) {
        populateForm(provider);
      }
    });

    $shell.on('click', '.khm-partner-connect-open-thread', function () {
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

    $shell.on('submit', '.khm-partner-connect-thread-reply', function (event) {
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

    $shell.on('submit', '.khm-partner-connect-seller-response-form', function (event) {
      var $form = $(this);
      var threadId = parseInt($form.data('thread-id'), 10) || 0;
      var $submit = $form.find('[type="submit"]');
      var commissionRate = parseInt($form.find('[name="commission_rate"]').val(), 10);

      event.preventDefault();

      if (!threadId) { return; }

      if (isNaN(commissionRate) || commissionRate < 5 || commissionRate > 25) {
        showStatus('Commission rate must be between 5 and 25.', 'error');
        return;
      }

      $submit.prop('disabled', true).text('Submitting...');
      request('intro-threads/mine/' + threadId + '/seller-response', 'POST', {
        capability:       $form.find('[name="capability"]').val().trim(),
        cost_range:       $form.find('[name="cost_range"]').val().trim(),
        approach:         $form.find('[name="approach"]').val().trim(),
        timeline:         $form.find('[name="timeline"]').val().trim(),
        lead_name:        $form.find('[name="lead_name"]').val().trim(),
        lead_email:       $form.find('[name="lead_email"]').val().trim(),
        lead_title:       $form.find('[name="lead_title"]').val().trim(),
        commission_rate:  commissionRate
      }).done(function () {
        showStatus('RFQ response submitted. The prospect can now review, accept, or reject it.', 'success');
        loadThreads(threadId);
      }).fail(function (xhr) {
        var res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
        showStatus(res.message || 'Unable to submit response.', 'error');
        $submit.prop('disabled', false).text('Submit RFQ response');
      });
    });

    function submitHandoverAcceptance(threadId, $button) {
      if (!threadId || !$button || !$button.length || $button.is(':disabled')) {
        return;
      }

      var $panel = $button.closest('.khm-partner-connect-thread-detail, .khm-partner-connect-thread-reply').closest('.khm-partner-connect-thread-detail');
      var $toggle = $panel.find('.khm-partner-connect-discount-toggle');
      var $rateInput = $panel.find('.khm-partner-rfq-commission-rate');
      var offerDiscount = $toggle.length && $toggle.is(':checked');
      var commissionRate = offerDiscount ? (parseInt($rateInput.val(), 10) || 0) : null;

      var doConfirm = function () {
        var originalText = $button.text();
        $button.prop('disabled', true).text('Accepting...');
        request('intro-threads/mine/' + threadId + '/handover/confirm', 'POST').done(function () {
          showStatus('Handover accepted. The prospect can now access the next-step CTA.', 'success');
          loadThreads(threadId);
        }).fail(function (xhr) {
          var res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
          showStatus(res.message || 'Unable to accept handover.', 'error');
          $button.prop('disabled', false).text(originalText);
        });
      };

      if (offerDiscount && commissionRate >= 5 && commissionRate <= 25) {
        request('intro-threads/mine/' + threadId + '/commission', 'POST', { commission_rate: commissionRate })
          .done(doConfirm)
          .fail(function (xhr) {
            var res = xhr && xhr.responseJSON ? xhr.responseJSON : {};
            showStatus(res.message || 'Unable to save discount rate.', 'error');
          });
        return;
      }

      doConfirm();
    }

    $shell.on('click', '.khm-partner-connect-confirm-handover', function () {
      var threadId = parseInt($(this).data('thread-id'), 10) || 0;
      var $button = $(this);
      submitHandoverAcceptance(threadId, $button);
    });

    $shell.on('click', '.khm-partner-connect-accept-handover', function () {
      var threadId = parseInt($(this).data('thread-id'), 10) || 0;
      var $button = $(this);
      submitHandoverAcceptance(threadId, $button);
    });

    $shell.on('change input', '.khm-partner-connect-discount-toggle, .khm-partner-rfq-commission-rate', function () {
      var $panel = $(this).closest('.khm-partner-connect-discount-panel');
      var $toggle = $panel.find('.khm-partner-connect-discount-toggle');
      var $controls = $panel.find('.khm-partner-connect-discount-controls');
      var $rateValue = $panel.find('.khm-partner-rfq-rate-value');
      var $flatFee = $panel.find('.khm-partner-connect-flat-fee');
      var isOn = $toggle.is(':checked');
      var rate = parseInt($panel.find('.khm-partner-rfq-commission-rate').val(), 10) || 10;
      var halfRate = rate / 2;
      $controls.prop('hidden', !isOn);
      $flatFee.text(isOn ? '£375.00' : '£1,500.00');
      if (isOn) {
        var halfRateDisplay = halfRate + '% of contract value';
        $panel.find('.khm-partner-connect-buyer-discount-readout').text(halfRateDisplay);
        $panel.find('.khm-partner-connect-commission-rate-readout').text(halfRateDisplay);
        $rateValue.text(rate + '%');
      }
    });

    $shell.on('click', '.khm-partner-rfq-link-btn', function () {
      var linkId = $(this).data('link-id');
      var link = rfqLinkStore[linkId];
      if (link) {
        window.open(link, '_blank', 'noopener,noreferrer');
      }
    });

    // Dynamic show/hide for provider type sub-sections
    function toggleProviderTypeSubsections() {
      var selectedTypes = $form.find('input[name="provider_type[]"]:checked').map(function() {
        return $(this).val();
      }).get();

      $shell.find('.khm-partner-connect-subsection').hide(); // Hide all by default

      selectedTypes.forEach(function(type) {
        $shell.find('.khm-partner-connect-subsection[data-provider-type*="' + type + '"]').show();
      });
    }

    // Event listener for provider type checkboxes
    $form.on('change', 'input[name="provider_type[]"]', toggleProviderTypeSubsections);

    // Initial state setup for provider type sub-sections on load and form reset
    $form.on('reset', function() {
      // Defer to allow the form to actually reset before re-evaluating
      setTimeout(toggleProviderTypeSubsections, 0);
    });

    // Override populateForm to also toggle subsections after populating
    var originalPopulateForm = populateForm;
    populateForm = function(provider) {
      originalPopulateForm(provider);
      toggleProviderTypeSubsections();
    };

    // Function to update accordion counter display
    function updateAccordionCounter($solutionCard) {
      var $checkboxes = $solutionCard.find("input[type='checkbox']");
      var checkedCount = $checkboxes.filter(":checked").length;
      var $countStrong = $solutionCard.find(".khm-accordion-count");
      
      if ($countStrong.length) {
        $countStrong.text(checkedCount); // Update just the number
      }
    }

    // Event listeners for solution card checkbox changes
    $form.on("change", "input[name='software_expertise[]']", function() {
      var $card = $(this).closest('.khm-partner-solution-card');
      if ($card.length) {
        updateAccordionCounter($card);
      }
    });

    // Initialize counters after form is loaded with data
    var originalPopulateFormWithCounters = populateForm;
    populateForm = function(provider) {
      originalPopulateFormWithCounters(provider);
      initializeAccordionCounters($form);
    };

    // Initialize counters on form reset
    $form.on('reset', function() {
      setTimeout(function() { initializeAccordionCounters($form); }, 0);
    });

    loadProviders();

    // --- Account Details Logic ---
    var $accountForm = $('#khm-partner-account-form');

    function loadAccountData() {
      if (!$accountForm.length) {
        return;
      }

      showStatus('Loading account details...', 'info');

      $.ajax({
        url: (window.khmPartnerPortal && khmPartnerPortal.restUrl ? khmPartnerPortal.restUrl : '') + 'sponsor',
        method: 'GET',
        contentType: undefined,
        processData: true,
        headers: {
          'X-WP-Nonce': window.khmPartnerPortal ? khmPartnerPortal.nonce : ''
        },
        dataType: 'json'
      })
        .done(function (sponsor) {
          populateAccountForm(sponsor);
          clearStatus();
        })
        .fail(function (xhr) {
          showStatus('Could not load account details.', 'error');
        });
    }

    function populateAccountForm(sponsor) {
      if (!$accountForm.length) {
        return;
      }

      $accountForm.find('[name="company_name"]').val(sponsor.name || '');
      $accountForm.find('[name="website_url"]').val(sponsor.website_url || '');
      $accountForm.find('[name="hq_location"]').val(sponsor.hq_location || '');
      $accountForm.find('[name="regions"]').val(sponsor.regions || '');
      $accountForm.find('[name="company_size_band"]').val(sponsor.company_size_band || '1-50');

      // Checkboxes
      var checkboxFields = [
        'provider_type',
        'software_expertise',
        'hardware_capabilities',
        'consultancy_areas',
        'deployment_modes',
        'support_tiers'
      ];

      checkboxFields.forEach(function (field) {
        $accountForm.find('input[name="' + field + '[]"]').prop('checked', false);
        var values = sponsor[field];
        if (Array.isArray(values)) {
          values.forEach(function (val) {
            $accountForm.find('input[name="' + field + '[]"][value="' + val + '"]').prop('checked', true);
          });
        }
      });

      $accountForm.find('[name="pilot_scheme_available"]').prop('checked', !!sponsor.pilot_scheme_available);
      $accountForm.find('[name="free_trial_available"]').prop('checked', !!sponsor.free_trial_available);

      // Handle regions multi-select initialization after data is loaded
      var initialRegions = Array.isArray(sponsor.regions) ? sponsor.regions : [];
      initializeRegionsMultiSelect($accountForm, initialRegions);

      initializeAccountAccordionCounters($accountForm);
    }

    function initializeRegionsMultiSelect($form, initialSelection) {
      var $select = $form.find(".khm-partner-regions-primary-select");
      if (!$select.length) {
        return;
      }

      var $container = $select.closest(".khm-partner-regions-container");
      var $tagsContainer = $container.find(".khm-partner-regions-tags");
      if (!$tagsContainer.length) {
        return;
      }

      var regionsData = window.khmAccountRegionsData || {};
      var primaryLabels = regionsData.primary || {};
      var countryLabels = regionsData.countries || {};

      var selected = initialSelection.map(function(val) {
        return { value: val, label: primaryLabels[val] || countryLabels[val] || val };
      });
      var allOptions = [];

      // Build list of all options (primary regions + countries)
      $select.find("option").each(function () {
        var val = $(this).val();
        if (!val) return;
        allOptions.push({
          value: val,
          label: primaryLabels[val] || countryLabels[val] || val
        });
      });

      function renderTags() {
        $tagsContainer.empty();
        selected.forEach(function (item) {
          var $tag = $("<span class=\"khm-partner-region-tag\" data-value=\"" + esc(item.value) + "\">")
            .text(item.label)
            .append(" <button type=\"button\" class=\"khm-partner-region-tag-remove\" aria-label=\"Remove " + esc(item.label) + "\">&times;</button>");
          $tagsContainer.append($tag);
        });
      }

      function updateSelect() {
        $select.empty();
        $select.append("<option value=\"\">Select regions</option>");
        allOptions.forEach(function (opt) {
          var isSelected = selected.some(function (s) { return s.value === opt.value; });
          $select.append("<option value=\"" + esc(opt.value) + "\"" + (isSelected ? " selected" : "") + ">" + esc(opt.label) + "</option>");
        });
      }

      function toggleSelect() {
        if (selected.length >= allOptions.length) {
          $select.prop("disabled", true).attr("aria-disabled", "true");
          // Disable all options except the empty one for reset
          $select.find("option").not("[value=\"\"]").prop("disabled", true);
          $select.find("option[value=\"\"]").prop("disabled", false).attr("selected", true); // Select empty option
        } else {
          $select.prop("disabled", false).attr("aria-disabled", "false");
          $select.find("option").prop("disabled", false);
          // If no options are selected, ensure "Select regions" is visible
          if (selected.length === 0) {
            $select.find("option[value=\"\"]").attr("selected", true);
          } else {
            $select.find("option[value=\"\"]").removeAttr("selected");
          }
        }
      }

      function addSelection(value) {
        if (selected.some(function (s) { return s.value === value; })) {
          return false;
        }
        var opt = allOptions.find(function (o) { return o.value === value; });
        if (!opt) return false;
        selected.push(opt);
        renderTags();
        updateSelect();
        toggleSelect();
        $select.trigger("change");
        return true;
      }

      function removeSelection(value) {
        var idx = selected.findIndex(function (s) { return s.value === value; });
        if (idx >= 0) {
          selected.splice(idx, 1);
          renderTags();
          updateSelect();
          toggleSelect();
          $select.trigger("change");
        }
      }

      // Click on tag removes it
      $tagsContainer.on("click", ".khm-partner-region-tag-remove", function (e) {
        e.stopPropagation(); // Prevent click from bubbling to the select
        var $tag = $(this).closest(".khm-partner-region-tag");
        var value = $tag.data("value");
        removeSelection(value);
      });

      // Click on select opens menu
      $select.on("click", function (e) {
        e.stopPropagation();
        if ($(e.target).is(".khm-partner-region-tag, .khm-partner-region-tag-remove")) {
          return; // Do nothing if a tag or remove button within the select is clicked
        }
        $container.toggleClass("is-open");
      });

      // Click outside closes menu
      $(document).on("click", function (e) {
        if (!$container.has(e.target).length) {
          $container.removeClass("is-open");
        }
      });

      // Select option click
      $container.on("click", ".khm-partner-region-option", function (e) {
        e.stopPropagation();
        var $item = $(this);
        var value = $item.data("value");

        if ($item.hasClass("is-selected")) {
          removeSelection(value);
        } else {
          addSelection(value);
        }

        // After adding/removing, ensure the actual <select> element's options reflect the state
        $select.find("option[value=\"" + esc(value) + "\"]").prop("selected", $item.hasClass("is-selected"));
        toggleSelect(); // Re-evaluate toggle state for disabled options
      });

      // Initial render
      renderTags();
      updateSelect();
      toggleSelect();

      // Initial selections from the underlying <select>
      $select.find("option:selected").each(function() {
        var val = $(this).val();
        if (val && !selected.some(function(s) { return s.value === val; })) {
          selected.push({ value: val, label: primaryLabels[val] || countryLabels[val] || val });
        }
      });
      renderTags();
      updateSelect();
      toggleSelect();
    }

    // submitAccountDetails was removed in favour of the inline fetch() handler
    // in render_account_section() which sends correct region data and solution
    // mappings. The legacy jQuery submit binding was removed to prevent a
    // parallel request that serializes empty regions (serializeArray doesn't
    // capture the tag-based multi-select UI).

    function initializeAccountAccordionCounters() {
      $accountForm.find(".khm-partner-solution-card").each(function () {
        updateAccordionCounter($(this));
      });
    }

    if ($accountForm.length) {

      $accountForm.on('change', 'input[type="checkbox"]', function () {
        var $accordion = $(this).closest('.khm-accordion');
        if ($accordion.length) {
          updateAccordionCounter($accordion);
        }
        // Also handle the new collapsible solution cards
        var $solutionCard = $(this).closest('.khm-partner-solution-card');
        if ($solutionCard.length) {
          updateAccordionCounter($solutionCard);
          updateTotalCounter();
        }
      });

      // Make solution card headers clickable to toggle collapse state
      $accountForm.on('click', '.khm-partner-solution-card-header', function (e) {
        e.stopPropagation();
        var $card = $(this).closest('.khm-partner-solution-card');
        if ($card.hasClass('is-collapsible')) {
          $card.toggleClass('is-collapsed');
        }
      });

      // Function to update the total counter at the top
      function updateTotalCounter() {
        var totalCount = 0;
        $accountForm.find('.khm-partner-solution-card .khm-accordion-count').each(function () {
          totalCount += parseInt($(this).text() || 0);
        });
        var totalText = totalCount > 0 ? totalCount : 'none';
        $accountForm.find('.khm-solutions-total-counter strong').text(totalText);
      }

      // Keep accordions collapsed on page load
      // $accountForm.find('.khm-partner-solution-card.is-collapsed').removeClass('is-collapsed');

      loadAccountData();
    }
  });

})(jQuery);
