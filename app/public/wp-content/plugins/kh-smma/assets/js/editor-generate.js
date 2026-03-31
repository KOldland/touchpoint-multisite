(function () {
  "use strict";

  window.KHSMMAEditor = window.KHSMMAEditor || {};

  var state = {
    variants: [],
    isGenerating: false
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
    document.removeEventListener("keydown", handleModalEscape);
  }

  function handleModalEscape(event) {
    if (event.key !== "Escape") return;
    if (state.isGenerating) return;
    if (document.getElementById(id())) {
      closeModal();
    }
  }

  function findVariant(variantId) {
    return state.variants.find(function (v) {
      return String(v.variant_id) === String(variantId);
    });
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
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

  function renderErrorNotice(message, options) {
    var node = getWorkflowMessageNode();
    if (!node) return false;

    var opts = options || {};
    node.classList.add("is-error");
    node.innerHTML = "";

    var text = document.createElement("p");
    text.textContent = message || "Generation failed.";
    node.appendChild(text);

    var actions = document.createElement("div");
    actions.className = "kh-smma-modal-actions";

    if (opts.retry !== false) {
      var retry = document.createElement("button");
      retry.type = "button";
      retry.className = "button";
      retry.textContent = "Retry Generate";
      retry.addEventListener("click", runGenerate);
      actions.appendChild(retry);
    }

    if (opts.settingsUrl) {
      var cta = document.createElement("a");
      cta.className = "button button-secondary";
      cta.href = opts.settingsUrl;
      cta.target = "_blank";
      cta.rel = "noopener noreferrer";
      cta.textContent = "Open KH Social Settings";
      actions.appendChild(cta);
    }

    if (actions.childNodes.length > 0) {
      node.appendChild(actions);
    }

    return true;
  }

  function renderFeatureDisabledNotice(message) {
    var dashboardUrl =
      window.khSmmaEditor &&
      window.khSmmaEditor.urls &&
      window.khSmmaEditor.urls.dashboard
        ? window.khSmmaEditor.urls.dashboard
        : "";
    return renderErrorNotice(message || "Social Campaigns is currently unavailable.", {
      retry: true,
      settingsUrl: dashboardUrl
    });
  }

  function openGenerateModal() {
    closeModal();
    var modal = document.createElement("div");
    modal.id = id();
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card">' +
      '<div class="kh-smma-modal-head"><h3>Generate Social Copy</h3><button type="button" class="kh-smma-modal-close button-link" id="kh-smma-gen-close" aria-label="Close">×</button></div>' +
      '<p>Standard mode will generate:</p>' +
      '<ul><li>1 LinkedIn post (title + excerpt + tags)</li><li>Google metadata draft (SEO title + meta description)</li></ul>' +
      '<div class="kh-smma-modal-actions">' +
      '<button type="button" class="button" id="kh-smma-gen-cancel">Cancel</button>' +
      '<button type="button" class="button button-primary" id="kh-smma-gen-submit">Generate</button>' +
      "</div>" +
      '<div id="kh-smma-workflow-messages" class="kh-smma-workflow-messages"></div>' +
      '<div id="kh-smma-variant-grid-root" class="kh-smma-variant-grid-root"></div>' +
      "</div>";
    document.body.appendChild(modal);

    document.getElementById("kh-smma-gen-cancel").addEventListener("click", closeModal);
    document.getElementById("kh-smma-gen-close").addEventListener("click", closeModal);
    document.getElementById("kh-smma-gen-submit").addEventListener("click", runGenerate);
    modal.addEventListener("click", function (event) {
      if (event.target === modal && !state.isGenerating) {
        closeModal();
      }
    });
    document.addEventListener("keydown", handleModalEscape);
  }

  function setGenerateBusy(isBusy) {
    state.isGenerating = !!isBusy;
    var submit = document.getElementById("kh-smma-gen-submit");
    var cancel = document.getElementById("kh-smma-gen-cancel");
    if (submit) {
      submit.disabled = state.isGenerating;
      submit.textContent = state.isGenerating ? "Generating..." : "Generate";
    }
    if (cancel) {
      cancel.disabled = state.isGenerating;
    }
  }

  function runGenerate() {
    if (state.isGenerating) {
      return;
    }

    var postId = Number(window.khSmmaEditor.postId || 0);
    if (!postId) {
      window.alert("Post must be saved before generating variants.");
      return;
    }

    var count = 1;
    var platform = "both";
    var blocksSummary = "";
    try {
      if (
        window.wp &&
        window.wp.data &&
        window.wp.data.select &&
        window.wp.data.select("core/editor") &&
        typeof window.wp.data.select("core/editor").getEditedPostContent === "function"
      ) {
        var raw = String(window.wp.data.select("core/editor").getEditedPostContent() || "");
        blocksSummary = raw.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
      }
    } catch (e) {
      blocksSummary = "";
    }
    if (!blocksSummary) {
      var contentEl = document.getElementById("content");
      blocksSummary = contentEl ? String(contentEl.value || "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim() : "";
    }
    if (!blocksSummary) {
      blocksSummary = "Summary unavailable.";
    }
    blocksSummary = blocksSummary.slice(0, 4000);

    var payload = {
      post_id: postId,
      blocks_summary: blocksSummary,
      num_variants: count,
      geo_targets: [],
      consent: true,
      metadata: {
        target_platform: platform
      },
      standard_mode: true,
      generate_google_ads: true
    };

    document.dispatchEvent(new CustomEvent("smma:generate.request", { detail: { post_id: postId, num_variants: count } }));
    renderInlineMessage("Generating variants...", false);
    setGenerateBusy(true);

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
        var modelName =
          result.body &&
          result.body.provenance &&
          result.body.provenance.model
            ? String(result.body.provenance.model)
            : "unknown";
        if (modelName === "fallback") {
          renderInlineMessage(
            "Generated " + state.variants.length + " fallback variant(s). Output may be generic - click Retry Generate for a richer model run.",
            false
          );
        } else {
          renderInlineMessage("Generated " + state.variants.length + " variant(s).", false);
        }
        var isStandardMode = modelName === "standard-template-v1";
        var renderGrid = function () {
          window.KHSMMAEditor.VariantGrid.render(root, state.variants, {
            showDetails: !isStandardMode,
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
              window.KHSMMAEditor.ScheduleModal.open(variant, null, variantId);
            },
            onPreview: function (variantId) {
              var variant = findVariant(variantId);
              if (!variant) return;
              openPreviewModal(variant);
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
        if (!renderErrorNotice((err && err.message) || "Generation failed.", { retry: true })) {
          window.alert((err && err.message) || "Generation failed.");
        }
      })
      .finally(function () {
        setGenerateBusy(false);
      });
  }

  function openPreviewModal(variant) {
    var modalId = "kh-smma-preview-modal";
    var existing = document.getElementById(modalId);
    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
    }

    var linkedIn = variant && variant.linkedIn ? variant.linkedIn : {};
    var google = variant && variant.google && variant.google.ad_groups && variant.google.ad_groups[0] ? variant.google.ad_groups[0] : {};
    var metaTitle = google.meta_title || (Array.isArray(google.headlines) ? (google.headlines[0] || "") : "");
    var metaDescription = google.meta_description || (Array.isArray(google.descriptions) ? (google.descriptions[0] || "") : "");
    var featuredImageUrl =
      window.khSmmaEditor && window.khSmmaEditor.featuredImageUrl
        ? String(window.khSmmaEditor.featuredImageUrl)
        : "";

    var modal = document.createElement("div");
    modal.id = modalId;
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card">' +
      '<div class="kh-smma-modal-head"><h3>Post Preview</h3><button type="button" class="kh-smma-modal-close button-link" id="kh-smma-preview-close" aria-label="Close">×</button></div>' +
      '<h4 style="margin:0 0 6px;">LinkedIn</h4>' +
      (featuredImageUrl
        ? '<img src="' + escapeHtml(featuredImageUrl) + '" alt="Featured image preview" style="display:block;max-width:100%;height:auto;border:1px solid #dcdcde;border-radius:6px;margin:0 0 8px;background:#fff;" />'
        : "") +
      '<p class="kh-smma-variant-text" style="border:1px solid #dcdcde;border-radius:6px;padding:10px;background:#fff;">' + escapeHtml(linkedIn.text || "") + "</p>" +
      '<h4 style="margin:14px 0 6px;">Google Metadata</h4>' +
      '<p style="margin:0 0 4px;"><strong>Title:</strong> ' + escapeHtml(metaTitle || "") + "</p>" +
      '<p style="margin:0 0 10px;"><strong>Meta Description:</strong> ' + escapeHtml(metaDescription || "") + "</p>" +
      '<div class="kh-smma-modal-actions"><button type="button" class="button" id="kh-smma-preview-cancel">Close</button></div>' +
      "</div>";
    document.body.appendChild(modal);

    function closePreview() {
      var node = document.getElementById(modalId);
      if (node && node.parentNode) {
        node.parentNode.removeChild(node);
      }
      document.removeEventListener("keydown", onEsc);
    }
    function onEsc(event) {
      if (event.key === "Escape") {
        closePreview();
      }
    }

    document.getElementById("kh-smma-preview-close").addEventListener("click", closePreview);
    document.getElementById("kh-smma-preview-cancel").addEventListener("click", closePreview);
    modal.addEventListener("click", function (event) {
      if (event.target === modal) {
        closePreview();
      }
    });
    document.addEventListener("keydown", onEsc);
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
