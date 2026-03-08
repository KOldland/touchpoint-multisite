(function () {
  "use strict";

  window.KHSMMAEditor = window.KHSMMAEditor || {};

  function idempotencyKey() {
    return "idem-edit-" + Date.now() + "-" + Math.random().toString(16).slice(2);
  }

  function closeModal(id) {
    var node = document.getElementById(id);
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  }

  function openEditor(variant, onSaved) {
    var linkedIn = (variant && variant.linkedIn) || {};
    var modalId = "kh-smma-variant-editor-modal";
    closeModal(modalId);
    var modal = document.createElement("div");
    modal.id = modalId;
    modal.className = "kh-smma-modal";
    modal.innerHTML =
      '<div class="kh-smma-modal-card">' +
      '<h3>Edit Variant</h3>' +
      '<label>Variant Text</label>' +
      '<textarea id="kh-smma-edit-text">' + (linkedIn.text || "") + '</textarea>' +
      '<label>Edit Reason</label>' +
      '<input type="text" id="kh-smma-edit-reason" placeholder="Reason for edit (optional)" />' +
      '<div class="kh-smma-live-preview"><strong>Live preview:</strong><div id="kh-smma-edit-preview"></div></div>' +
      '<div class="kh-smma-modal-actions">' +
      '<button type="button" class="button" id="kh-smma-edit-cancel">Cancel</button>' +
      '<button type="button" class="button button-primary" id="kh-smma-edit-save">Save</button>' +
      "</div></div>";
    document.body.appendChild(modal);

    var textEl = document.getElementById("kh-smma-edit-text");
    var previewEl = document.getElementById("kh-smma-edit-preview");
    if (previewEl) previewEl.textContent = linkedIn.text || "";
    if (textEl && previewEl) {
      textEl.addEventListener("input", function () {
        previewEl.textContent = textEl.value;
      });
    }

    document.getElementById("kh-smma-edit-cancel").addEventListener("click", function () {
      closeModal(modalId);
    });

    document.getElementById("kh-smma-edit-save").addEventListener("click", function () {
      var payload = {
        editor_user_id: Number((window.khSmmaEditor && window.khSmmaEditor.userId) || 0),
        text: (textEl && textEl.value) || "",
        metadata: {
          edit_reason: (document.getElementById("kh-smma-edit-reason").value || "")
        },
        asset_hints: linkedIn.asset_hints || []
      };

      fetch(window.khSmmaEditor.apiBase + "/variant/" + encodeURIComponent(variant.variant_id) + "/edit", {
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
            throw new Error(result.body && (result.body.message || result.body.error) || "Variant update failed.");
          }
          if (typeof onSaved === "function") onSaved(result.body);
          closeModal(modalId);
          window.alert("Variant updated successfully. Compliance re-check completed.");
          document.dispatchEvent(new CustomEvent("smma:variant.edit", { detail: { variant_id: variant.variant_id } }));
        })
        .catch(function (err) {
          window.alert(err.message || "Variant update failed.");
        });
    });
  }

  window.KHSMMAEditor.VariantEditor = {
    open: openEditor
  };
})();
