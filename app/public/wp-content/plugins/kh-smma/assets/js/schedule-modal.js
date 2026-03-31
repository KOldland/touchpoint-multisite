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

  function openSchedule(variant, onScheduled) {
    var modalId = "kh-smma-schedule-modal";
    closeModal(modalId);

    var modal = document.createElement("div");
    modal.id = modalId;
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card">' +
      "<h3>Schedule Campaign</h3>" +
      '<label>Date & Time (UTC)</label><input type="datetime-local" id="kh-smma-sch-time" />' +
      '<label>Platform</label><select id="kh-smma-sch-platform">' + platformOptionsHtml() + '</select>' +
      '<label>Boost Budget (cents)</label><input type="number" id="kh-smma-sch-budget" value="10000" min="0" step="100" />' +
      '<label>Duration (days)</label><input type="number" id="kh-smma-sch-duration" value="7" min="1" max="30" />' +
      '<label>Sponsor ID</label><input type="text" id="kh-smma-sch-sponsor" placeholder="sp_123 or numeric sponsor id" />' +
      '<div class="kh-smma-modal-actions">' +
      '<button type="button" class="button" id="kh-smma-sch-cancel">Cancel</button>' +
      '<button type="button" class="button button-primary" id="kh-smma-sch-submit">Create Schedule</button>' +
      "</div></div>";
    document.body.appendChild(modal);

    document.getElementById("kh-smma-sch-cancel").addEventListener("click", function () {
      closeModal(modalId);
    });

    document.getElementById("kh-smma-sch-submit").addEventListener("click", function () {
      var sponsorId = (document.getElementById("kh-smma-sch-sponsor").value || "").trim();
      if (!sponsorId) {
        window.alert("Sponsor ID is required.");
        return;
      }

      var when = document.getElementById("kh-smma-sch-time").value;
      if (!when) {
        window.alert("Please choose a schedule date/time.");
        return;
      }

      var payload = {
        variant_id: variant.variant_id,
        sponsor_id: sponsorId,
        schedule_time: new Date(when).toISOString(),
        boost_options: {
          budget_cents: Number(document.getElementById("kh-smma-sch-budget").value || 0),
          currency: "AUD",
          channels: [document.getElementById("kh-smma-sch-platform").value || "linkedin"],
          duration_days: Number(document.getElementById("kh-smma-sch-duration").value || 7),
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
