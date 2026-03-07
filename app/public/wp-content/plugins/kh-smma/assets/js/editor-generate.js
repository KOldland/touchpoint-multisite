(function () {
  "use strict";

  window.KHSMMAEditor = window.KHSMMAEditor || {};

  var state = {
    variants: []
  };

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

  function openGenerateModal() {
    closeModal();
    var modal = document.createElement("div");
    modal.id = id();
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card">' +
      "<h3>Generate Variants</h3>" +
      '<label>Number of Variants</label><input type="number" id="kh-smma-gen-count" min="1" max="5" value="3" />' +
      '<label>Platform Target</label><select id="kh-smma-gen-platform"><option value="linkedin">LinkedIn</option><option value="google">Google</option><option value="both" selected>LinkedIn + Google</option></select>' +
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
          throw new Error((result.body && (result.body.message || result.body.error)) || "Generation failed.");
        }

        state.variants = Array.isArray(result.body.variants) ? result.body.variants : [];
        var root = document.getElementById("kh-smma-variant-grid-root");
        var messages = document.getElementById("kh-smma-workflow-messages");
        if (messages) {
          messages.textContent = "Generated " + state.variants.length + " variant(s).";
        }
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
        window.alert(err.message || "Generation failed.");
      });
  }

  function bindEntryButton() {
    var button = document.getElementById("kh-smma-open-workflow");
    if (!button) return;
    button.addEventListener("click", openGenerateModal);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindEntryButton);
  } else {
    bindEntryButton();
  }
})();
