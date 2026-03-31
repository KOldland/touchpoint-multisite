(function () {
  "use strict";

  window.KHSMMAEditor = window.KHSMMAEditor || {};

  var state = {
    variants: []
  };

  function allowedPlatforms() {
    var configured = window.khSmmaEditor && Array.isArray(window.khSmmaEditor.allowedPlatforms) ? window.khSmmaEditor.allowedPlatforms : ["linkedin", "google"];
    var cleaned = configured.filter(function (platform) {
      return platform === "linkedin" || platform === "google";
    });
    return cleaned.length ? cleaned : ["linkedin"];
  }

  function generatePlatformOptionsHtml() {
    var platforms = allowedPlatforms();
    if (platforms.length === 2) {
      return '<option value="linkedin">LinkedIn</option><option value="google">Google</option><option value="both" selected>LinkedIn + Google</option>';
    }
    if (platforms[0] === "google") {
      return '<option value="google" selected>Google</option>';
    }
    return '<option value="linkedin" selected>LinkedIn</option>';
  }

  function id() {
    return "kh-smma-generate-modal";
  }

  function closeModal() {
    var node = document.getElementById(id());
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  }

  function findVariant(variantId) {
    return state.variants.find(function (v) {
      return String(v.variant_id) === String(variantId);
    });
  }

  function getWorkflowMessageNode() {
    return document.getElementById("kh-smma-workflow-messages");
  }

  function renderInlineMessage(text, isError) {
    var node = getWorkflowMessageNode();
    if (!node) return;
    node.textContent = text || "";
    node.classList.toggle("is-error", !!isError);
  }

  function renderFeatureDisabledNotice(message) {
    var node = getWorkflowMessageNode();
    if (!node) return false;

    var dashboardUrl =
      window.khSmmaEditor &&
      window.khSmmaEditor.urls &&
      window.khSmmaEditor.urls.dashboard
        ? window.khSmmaEditor.urls.dashboard
        : "";

    node.classList.add("is-error");
    node.innerHTML = "";

    var text = document.createElement("p");
    text.textContent = message || "Social Campaigns is currently unavailable.";
    node.appendChild(text);

    if (dashboardUrl) {
      var cta = document.createElement("a");
      cta.className = "button button-secondary";
      cta.href = dashboardUrl;
      cta.target = "_blank";
      cta.rel = "noopener noreferrer";
      cta.textContent = "Open KH Social Settings";
      node.appendChild(cta);
    }

    return true;
  }

  function openGenerateModal() {
    closeModal();
    var modal = document.createElement("div");
    modal.id = id();
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card">' +
      "<h3>Generate Variants</h3>" +
      '<label>Number of Variants</label><input type="number" id="kh-smma-gen-count" min="1" max="5" value="3" />' +
      '<label>Platform Target</label><select id="kh-smma-gen-platform">' + generatePlatformOptionsHtml() + '</select>' +
      '<div class="kh-smma-modal-actions">' +
      '<button type="button" class="button" id="kh-smma-gen-cancel">Cancel</button>' +
      '<button type="button" class="button button-primary" id="kh-smma-gen-submit">Generate</button>' +
      "</div>" +
      '<div id="kh-smma-workflow-messages" class="kh-smma-workflow-messages"></div>' +
      '<div id="kh-smma-variant-grid-root" class="kh-smma-variant-grid-root"></div>' +
      "</div>";
    document.body.appendChild(modal);

    document.getElementById("kh-smma-gen-cancel").addEventListener("click", closeModal);
    document.getElementById("kh-smma-gen-submit").addEventListener("click", runGenerate);
  }

  function runGenerate() {
    var postId = Number(window.khSmmaEditor.postId || 0);
    if (!postId) {
      window.alert("Post must be saved before generating variants.");
      return;
    }

    var count = Number(document.getElementById("kh-smma-gen-count").value || 3);
    var platform = document.getElementById("kh-smma-gen-platform").value || "both";
    var contentEl = document.getElementById("content");
    var blocksSummary = contentEl ? String(contentEl.value || "").slice(0, 4000) : "Post content summary";

    var payload = {
      post_id: postId,
      blocks_summary: blocksSummary,
      num_variants: count,
      geo_targets: [],
      consent: true,
      metadata: {
        target_platform: platform
      }
    };

    document.dispatchEvent(new CustomEvent("smma:generate.request", { detail: { post_id: postId, num_variants: count } }));

    fetch(window.khSmmaEditor.apiBase + "/generate", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.khSmmaEditor.nonce,
        "X-Request-Id": "ui-gen-" + Date.now()
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
          throw {
            code: result.body && result.body.code ? result.body.code : "",
            message: (result.body && (result.body.message || result.body.error)) || "Generation failed.",
            status: result.body && result.body.data && result.body.data.status ? result.body.data.status : 0
          };
        }

        state.variants = Array.isArray(result.body.variants) ? result.body.variants : [];
        var root = document.getElementById("kh-smma-variant-grid-root");
        renderInlineMessage("Generated " + state.variants.length + " variant(s).", false);
        var renderGrid = function () {
          window.KHSMMAEditor.VariantGrid.render(root, state.variants, {
            onEdit: function (variantId) {
              var variant = findVariant(variantId);
              if (!variant) return;
              window.KHSMMAEditor.VariantEditor.open(variant, function (editResponse) {
                if (variant.linkedIn) {
                  variant.linkedIn.text = (editResponse && editResponse.revision && editResponse.revision.updated_text) || variant.linkedIn.text;
                  if (editResponse && editResponse.compliance) {
                    variant.linkedIn.compliance_status = editResponse.compliance.status;
                    variant.linkedIn.compliance_reason = (editResponse.compliance.reasons || []).join(", ");
                  }
                }
                renderGrid();
              });
            },
            onSchedule: function (variantId) {
              var variant = findVariant(variantId);
              if (!variant) return;
              window.KHSMMAEditor.ScheduleModal.open(variant);
            }
          });
        };
        renderGrid();
      })
      .catch(function (err) {
        if (err && err.code === "kh_smma_disabled") {
          renderFeatureDisabledNotice(err.message);
          return;
        }
        renderInlineMessage((err && err.message) || "Generation failed.", true);
        window.alert(err.message || "Generation failed.");
      });
  }

  function bindEntryButton() {
    var button = document.getElementById("kh-smma-open-workflow");
    if (!button) return;
    button.addEventListener("click", openGenerateModal);
  }

  function bindWorkspaceTabs() {
    var workspace = document.getElementById("kh-smma-post-boost-workspace");
    if (!workspace) return;

    var tabs = Array.prototype.slice.call(workspace.querySelectorAll("[data-kh-social-tab]"));
    var panels = Array.prototype.slice.call(workspace.querySelectorAll("[data-kh-social-panel]"));
    if (!tabs.length || !panels.length) return;

    function activate(tabKey) {
      tabs.forEach(function (tab) {
        var isActive = tab.getAttribute("data-kh-social-tab") === tabKey;
        tab.classList.toggle("is-active", isActive);
        tab.setAttribute("aria-selected", isActive ? "true" : "false");
      });

      panels.forEach(function (panel) {
        var isActive = panel.getAttribute("data-kh-social-panel") === tabKey;
        panel.classList.toggle("is-active", isActive);
        panel.hidden = !isActive;
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        activate(tab.getAttribute("data-kh-social-tab"));
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      bindEntryButton();
      bindWorkspaceTabs();
    });
  } else {
    bindEntryButton();
    bindWorkspaceTabs();
  }

  document.addEventListener("khSmmaOpenWorkflow", openGenerateModal);
  window.KHSMMAEditor.openGenerateModal = openGenerateModal;
})();
