(function () {
  "use strict";

  window.KHSMMAEditor = window.KHSMMAEditor || {};

  function allowedPlatforms() {
    var configured = window.khSmmaEditor && Array.isArray(window.khSmmaEditor.allowedPlatforms) ? window.khSmmaEditor.allowedPlatforms : ["linkedin", "google"];
    var cleaned = configured.filter(function (platform) {
      return platform === "linkedin" || platform === "google";
    });
    return cleaned.length ? cleaned : ["linkedin"];
  }

  function platformOptionsHtml() {
    return allowedPlatforms().map(function (platform) {
      var label = platform === "google" ? "Google" : "LinkedIn";
      return '<option value="' + platform + '">' + label + "</option>";
    }).join("");
  }

  function idempotencyKey() {
    return "idem-sch-" + Date.now() + "-" + Math.random().toString(16).slice(2);
  }

  function closeModal(id) {
    var node = document.getElementById(id);
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  }

  function openSchedule(variant, onScheduled, forcedVariantId) {
    var modalId = "kh-smma-schedule-modal";
    closeModal(modalId);

    var modal = document.createElement("div");
    modal.id = modalId;
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card" style="position:relative;">' +
      '<button type="button" class="kh-smma-modal-close" id="kh-smma-sch-close" aria-label="Close modal" style="position:absolute;top:10px;right:10px;border:0;background:transparent;font-size:20px;line-height:1;cursor:pointer;">&times;</button>' +
      "<h3>Schedule Campaign</h3>" +
      '<p style="margin:0 0 10px;color:#50575e;">Click each section to expand.</p>' +
      '<section class="kh-smma-acc">' +
      '<button type="button" class="button-link" data-kh-toggle="kh-smma-sec-type" style="display:block;width:100%;text-align:left;padding:8px 0;font-weight:600;">Campaign Type</button>' +
      '<div id="kh-smma-sec-type" style="display:none;padding:0 0 8px;">' +
      '<label style="display:block;margin:4px 0;"><input type="radio" name="kh-smma-sch-type" value="organic_social" checked /> Organic Social Post</label>' +
      '<label style="display:block;margin:4px 0;"><input type="radio" name="kh-smma-sch-type" value="linkedin_boost" /> LinkedIn Boost</label>' +
      '<label style="display:block;margin:4px 0;"><input type="radio" name="kh-smma-sch-type" value="google_ads" /> Google Ads Campaign</label>' +
      "</div>" +
      "</section>" +
      '<section class="kh-smma-acc">' +
      '<button type="button" class="button-link" data-kh-toggle="kh-smma-sec-basics" style="display:block;width:100%;text-align:left;padding:8px 0;font-weight:600;">Schedule Basics</button>' +
      '<div id="kh-smma-sec-basics" style="display:none;padding:0 0 8px;">' +
      '<label>Date & Time (UTC)</label><input type="datetime-local" id="kh-smma-sch-time" />' +
      '<label>Platform</label><select id="kh-smma-sch-platform">' + platformOptionsHtml() + '</select>' +
      "</div>" +
      "</section>" +
      '<section class="kh-smma-acc" id="kh-smma-sch-ads-section">' +
      '<button type="button" class="button-link" data-kh-toggle="kh-smma-sec-ads" style="display:block;width:100%;text-align:left;padding:8px 0;font-weight:600;">Ad Settings</button>' +
      '<div id="kh-smma-sec-ads" style="display:none;padding:0 0 8px;">' +
      '<label>Boost Budget (cents)</label><input type="number" id="kh-smma-sch-budget" value="10000" min="0" step="100" />' +
      '<label>Duration (days)</label><input type="number" id="kh-smma-sch-duration" value="7" min="1" max="30" />' +
      "</div>" +
      "</section>" +
      '<section class="kh-smma-acc" id="kh-smma-sch-sponsor-section">' +
      '<button type="button" class="button-link" data-kh-toggle="kh-smma-sec-sponsor" style="display:block;width:100%;text-align:left;padding:8px 0;font-weight:600;">Sponsor</button>' +
      '<div id="kh-smma-sec-sponsor" style="display:none;padding:0 0 8px;">' +
      '<label>Sponsor ID</label><input type="text" id="kh-smma-sch-sponsor" placeholder="sp_123 or numeric sponsor id" />' +
      "</div>" +
      "</section>" +
      '<div class="kh-smma-modal-actions">' +
      '<button type="button" class="button" id="kh-smma-sch-cancel">Cancel</button>' +
      '<button type="button" class="button button-primary" id="kh-smma-sch-submit">Create Schedule</button>' +
      "</div></div>";
    document.body.appendChild(modal);

    var platformEl = document.getElementById("kh-smma-sch-platform");
    var adsSectionEl = document.getElementById("kh-smma-sch-ads-section");
    var sponsorSectionEl = document.getElementById("kh-smma-sch-sponsor-section");
    var submitEl = document.getElementById("kh-smma-sch-submit");

    function getSelectedType() {
      var selected = document.querySelector('input[name="kh-smma-sch-type"]:checked');
      return selected && selected.value ? selected.value : "organic_social";
    }

    function applyTypeState() {
      var selectedType = getSelectedType();
      var isOrganic = selectedType === "organic_social";
      var isLinkedInBoost = selectedType === "linkedin_boost";
      var isGoogleAds = selectedType === "google_ads";

      if (adsSectionEl) adsSectionEl.style.display = isOrganic ? "none" : "";
      if (sponsorSectionEl) sponsorSectionEl.style.display = isLinkedInBoost ? "" : "none";
      if (submitEl) submitEl.textContent = isOrganic ? "Publish Post" : "Create Schedule";

      if (platformEl) {
        if (isLinkedInBoost) {
          platformEl.value = "linkedin";
          platformEl.disabled = true;
        } else if (isGoogleAds) {
          platformEl.value = "google";
          platformEl.disabled = true;
        } else {
          platformEl.disabled = false;
        }
      }
    }

    modal.querySelectorAll('input[name="kh-smma-sch-type"]').forEach(function (radio) {
      radio.addEventListener("change", applyTypeState);
    });
    modal.querySelectorAll("[data-kh-toggle]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var targetId = btn.getAttribute("data-kh-toggle");
        var target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;
        target.style.display = target.style.display === "none" ? "block" : "none";
      });
    });
    applyTypeState();

    document.getElementById("kh-smma-sch-cancel").addEventListener("click", function () {
      closeModal(modalId);
    });
    document.getElementById("kh-smma-sch-close").addEventListener("click", function () {
      closeModal(modalId);
    });

    document.getElementById("kh-smma-sch-submit").addEventListener("click", function () {
      var selectedType = getSelectedType();
      var variantId = String(forcedVariantId || (variant && (variant.variant_id || variant.variantId)) || "").trim();
      if (!variantId) {
        window.alert("This variant is no longer available. Please regenerate variants and try again.");
        return;
      }
      var sponsorId = (document.getElementById("kh-smma-sch-sponsor").value || "").trim();
      if (selectedType === "linkedin_boost" && !sponsorId) {
        window.alert("Sponsor ID is required for LinkedIn boost campaigns.");
        return;
      }

      var when = document.getElementById("kh-smma-sch-time").value;
      if (!when) {
        window.alert("Please choose a schedule date/time.");
        return;
      }

      var budgetCents = selectedType === "organic_social" ? 0 : Number(document.getElementById("kh-smma-sch-budget").value || 0);
      var durationDays = selectedType === "organic_social" ? 1 : Number(document.getElementById("kh-smma-sch-duration").value || 7);
      var payload = {
        variant_id: variantId,
        sponsor_id: sponsorId,
        campaign_type: selectedType,
        variant_snapshot: variant || {},
        schedule_time: new Date(when).toISOString(),
        boost_options: {
          budget_cents: budgetCents,
          currency: "AUD",
          channels: [document.getElementById("kh-smma-sch-platform").value || "linkedin"],
          duration_days: durationDays,
          prioritize: "reach"
        },
        metadata: {}
      };

      fetch(window.khSmmaEditor.apiBase + "/schedule", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.khSmmaEditor.nonce,
          "Idempotency-Key": idempotencyKey()
        },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (body) {
            return { ok: res.ok, body: body };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            throw new Error((result.body && (result.body.message || result.body.error)) || "Schedule creation failed.");
          }
          if (typeof onScheduled === "function") onScheduled(result.body);
          closeModal(modalId);
          var approval = result.body.approval_status || "approved";
          window.alert("Schedule created (" + approval + ")");
          document.dispatchEvent(new CustomEvent("smma:schedule.create", { detail: { schedule_id: result.body.schedule_id } }));
        })
        .catch(function (err) {
          window.alert(err.message || "Schedule creation failed.");
        });
    });
  }

  window.KHSMMAEditor.ScheduleModal = {
    open: openSchedule
  };
})();
