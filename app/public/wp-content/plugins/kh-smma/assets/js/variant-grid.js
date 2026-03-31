(function () {
  "use strict";

  window.KHSMMAEditor = window.KHSMMAEditor || {};

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function normalizeCompliance(raw) {
    var status = String(raw || "OK").toUpperCase();
    if (status === "PASS") return "OK";
    if (status !== "OK" && status !== "WARN" && status !== "FAIL") return "WARN";
    return status;
  }

  function badgeClass(status) {
    var normalized = normalizeCompliance(status);
    if (normalized === "OK") return "kh-smma-badge kh-smma-badge-ok";
    if (normalized === "FAIL") return "kh-smma-badge kh-smma-badge-fail";
    return "kh-smma-badge kh-smma-badge-warn";
  }

  function render(root, variants, handlers) {
    if (!root) return;
    var showDetails = !(handlers && handlers.showDetails === false);
    var html = '<div class="kh-smma-grid">';
    (variants || []).forEach(function (variant, idx) {
      var linkedIn = variant.linkedIn || {};
      var complianceStatus = normalizeCompliance(linkedIn.compliance_status || (linkedIn.compliance && linkedIn.compliance.status));
      var reason = linkedIn.compliance_reason || "";
      var summary = linkedIn.ai_review_summary || "";
      var hints = Array.isArray(linkedIn.asset_hints) ? linkedIn.asset_hints : [];
      var hintHtml = hints.length
        ? "<ul>" + hints.map(function (h) {
            return "<li>" + escapeHtml((h.type || "asset") + ": " + (h.description || h.asset_id || "")) + "</li>";
          }).join("") + "</ul>"
        : "<em>No asset hints</em>";

      html +=
        '<article class="kh-smma-variant-card" data-variant-id="' + escapeHtml(variant.variant_id) + '">' +
        '<header><h4>Variant ' + (idx + 1) + '</h4>' +
        '<span class="' + badgeClass(complianceStatus) + '" title="' + escapeHtml((reason || "No reason provided") + (summary ? " | " + summary : "")) + '">' +
        escapeHtml(complianceStatus) +
        "</span></header>" +
        '<p class="kh-smma-variant-text">' + escapeHtml(linkedIn.text || "") + "</p>" +
        (showDetails
          ? ('<p class="kh-smma-variant-rationale"><strong>Rationale:</strong> ' + escapeHtml(linkedIn.rationale || "") + "</p>" +
            '<div class="kh-smma-variant-hints"><strong>Asset hints:</strong>' + hintHtml + "</div>")
          : "") +
        '<footer class="kh-smma-variant-actions">' +
        '<button type="button" class="button kh-smma-preview-btn" data-action="preview">Preview</button>' +
        '<button type="button" class="button kh-smma-edit-btn" data-action="edit">Edit</button>' +
        '<button type="button" class="button button-primary kh-smma-schedule-btn" data-action="schedule" ' + (complianceStatus === "FAIL" ? "disabled" : "") + '>Schedule</button>' +
        (complianceStatus === "FAIL" ? '<p class="description">Scheduling blocked due to compliance violation.</p>' : "") +
        "</footer>" +
        "</article>";
    });
    html += "</div>";
    root.innerHTML = html;

    root.querySelectorAll("[data-action='edit']").forEach(function (button) {
      button.addEventListener("click", function () {
        var card = button.closest(".kh-smma-variant-card");
        if (!card || !handlers || typeof handlers.onEdit !== "function") return;
        handlers.onEdit(String(card.getAttribute("data-variant-id") || ""));
      });
    });

    root.querySelectorAll("[data-action='schedule']").forEach(function (button) {
      button.addEventListener("click", function () {
        var card = button.closest(".kh-smma-variant-card");
        if (!card || !handlers || typeof handlers.onSchedule !== "function") return;
        handlers.onSchedule(String(card.getAttribute("data-variant-id") || ""));
      });
    });

    root.querySelectorAll("[data-action='preview']").forEach(function (button) {
      button.addEventListener("click", function () {
        var card = button.closest(".kh-smma-variant-card");
        if (!card || !handlers || typeof handlers.onPreview !== "function") return;
        handlers.onPreview(String(card.getAttribute("data-variant-id") || ""));
      });
    });
  }

  window.KHSMMAEditor.VariantGrid = {
    render: render,
    normalizeCompliance: normalizeCompliance,
    badgeClass: badgeClass
  };
})();
