(function(root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
        return;
    }

    root.KHSmmaVariantGrid = factory();
})(typeof self !== 'undefined' ? self : this, function() {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toArray(value) {
        if (Array.isArray(value)) {
            return value;
        }

        if (value && typeof value === 'object') {
            return [value];
        }

        return [];
    }

    function normalizeAssetHints(assetHints) {
        return toArray(assetHints).map(function(hint) {
            var type = String(hint && hint.type ? hint.type : 'image').trim() || 'image';
            var description = String(
                hint && (
                    hint.description ||
                    hint.alt_text ||
                    hint.image_aspect ||
                    hint.asset_id ||
                    'Suggested creative asset'
                )
            ).trim();

            return {
                type: type,
                description: description || 'Suggested creative asset'
            };
        });
    }

    function extractVariant(entry) {
        if (entry && entry.linkedIn && typeof entry.linkedIn === 'object') {
            return entry.linkedIn;
        }

        return entry || {};
    }

    function deriveComplianceStatus(variant) {
        var direct = String(variant.compliance_status || '').toUpperCase();
        if (direct === 'PASS') {
            return 'OK';
        }
        if (direct === 'OK' || direct === 'WARN' || direct === 'FAIL') {
            return direct;
        }

        var nested = variant.compliance && String(variant.compliance.status || '').toUpperCase();
        if (nested === 'PASS') {
            return 'OK';
        }
        if (nested === 'OK' || nested === 'WARN' || nested === 'FAIL') {
            return nested;
        }

        var notes = String(variant.compliance_notes || '');
        if (notes.indexOf('FAIL') !== -1) {
            return 'FAIL';
        }
        if (notes.indexOf('WARN') !== -1) {
            return 'WARN';
        }

        return 'OK';
    }

    function deriveComplianceReason(variant) {
        if (variant.compliance_reason) {
            return String(variant.compliance_reason);
        }

        if (variant.compliance && Array.isArray(variant.compliance.reasons) && variant.compliance.reasons.length) {
            return variant.compliance.reasons.join('; ');
        }

        if (variant.compliance_notes) {
            return String(variant.compliance_notes);
        }

        return '';
    }

    function normalizeVariantEntry(entry) {
        var variant = extractVariant(entry);
        var assetHints = normalizeAssetHints(variant.asset_hints || []);
        var complianceStatus = deriveComplianceStatus(variant);
        var complianceReason = deriveComplianceReason(variant);

        return {
            variantId: String(variant.variant_id || entry.variant_id || ''),
            text: String(variant.text || ''),
            rationale: String(variant.rationale || variant.explainability || ''),
            assetHints: assetHints,
            platform: String(variant.platform || variant.channel || 'linkedin'),
            complianceStatus: complianceStatus,
            complianceReason: complianceReason,
            checkedAt: String(variant.checked_at || ''),
            approvalStatus: String(entry.approval_status || variant.approval_status || ''),
            raw: entry
        };
    }

    function complianceClass(status) {
        var normalized = String(status || 'OK').toLowerCase();
        if (normalized === 'pass') {
            normalized = 'ok';
        }
        return 'khm-compliance-' + normalized;
    }

    function renderAssetHints(assetHints) {
        var hints = normalizeAssetHints(assetHints);
        if (!hints.length) {
            return '<li>No asset hints returned.</li>';
        }

        return hints.map(function(hint) {
            return '<li><strong>' + escapeHtml(hint.type) + ':</strong> ' + escapeHtml(hint.description) + '</li>';
        }).join('');
    }

    function renderVariantCard(entry, index) {
        var variant = normalizeVariantEntry(entry);
        var scheduleDisabled = variant.complianceStatus === 'FAIL' ? ' disabled' : '';
        var complianceReason = variant.complianceReason
            ? '<p class="khm-variant-card__reason"><strong>Compliance:</strong> ' + escapeHtml(variant.complianceReason) + '</p>'
            : '';
        var rationale = variant.rationale
            ? '<p class="khm-variant-card__rationale">' + escapeHtml(variant.rationale) + '</p>'
            : '<p class="khm-variant-card__rationale khm-variant-card__rationale--muted">No rationale provided.</p>';

        return [
            '<article class="khm-variant-card" data-variant-id="' + escapeHtml(variant.variantId) + '" data-variant-index="' + index + '">',
            '<header class="khm-variant-card__header">',
            '<label class="khm-variant-card__select"><input type="checkbox" class="khm-variant-select" data-variant-id="' + escapeHtml(variant.variantId) + '" checked> Select</label>',
            '<div class="khm-variant-card__meta">',
            '<span class="khm-compliance-badge ' + complianceClass(variant.complianceStatus) + '">' + escapeHtml(variant.complianceStatus) + '</span>',
            '<span class="khm-variant-card__platform">' + escapeHtml(variant.platform) + '</span>',
            '</div>',
            '</header>',
            '<div class="khm-variant-card__body">',
            '<p class="khm-variant-card__text">' + escapeHtml(variant.text) + '</p>',
            rationale,
            complianceReason,
            '<div class="khm-variant-card__assets">',
            '<h4>Asset hints</h4>',
            '<ul>' + renderAssetHints(variant.assetHints) + '</ul>',
            '</div>',
            '</div>',
            '<footer class="khm-variant-card__footer">',
            '<button type="button" class="button khm-smma-inline-edit-btn" data-variant-id="' + escapeHtml(variant.variantId) + '">Edit</button>',
            '<button type="button" class="button button-primary khm-smma-inline-schedule-btn" data-variant-id="' + escapeHtml(variant.variantId) + '"' + scheduleDisabled + '>Schedule</button>',
            '</footer>',
            '<section class="khm-variant-editor" hidden>',
            '<label class="screen-reader-text" for="khm-variant-text-' + escapeHtml(variant.variantId) + '">Variant text</label>',
            '<textarea id="khm-variant-text-' + escapeHtml(variant.variantId) + '" class="khm-variant-editor__textarea">' + escapeHtml(variant.text) + '</textarea>',
            '<input type="text" class="khm-variant-editor__reason" placeholder="Edit reason (optional)" value="">',
            '<div class="khm-variant-editor__actions">',
            '<button type="button" class="button button-primary khm-smma-save-variant-btn" data-variant-id="' + escapeHtml(variant.variantId) + '">Save</button>',
            '<button type="button" class="button khm-smma-cancel-variant-btn" data-variant-id="' + escapeHtml(variant.variantId) + '">Cancel</button>',
            '<span class="spinner"></span>',
            '</div>',
            '<div class="khm-variant-editor__feedback" aria-live="polite"></div>',
            '</section>',
            '</article>'
        ].join('');
    }

    return {
        escapeHtml: escapeHtml,
        normalizeAssetHints: normalizeAssetHints,
        normalizeVariantEntry: normalizeVariantEntry,
        renderAssetHints: renderAssetHints,
        renderVariantCard: renderVariantCard,
        deriveComplianceStatus: deriveComplianceStatus
    };
});
