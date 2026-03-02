(function (blocks, blockEditor, components, element, i18n) {
    'use strict';

    if (!blocks || !blocks.registerBlockType) {
        return;
    }

    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;

    blocks.registerBlockType('khm/commerce-checkout-button', {
        title: __('Commerce Checkout Button', 'khm-membership'),
        description: __('Renders a button that opens the KHM commerce payment modal for a specific post.', 'khm-membership'),
        icon: 'cart',
        category: 'widgets',
        attributes: {
            postId: {
                type: 'number',
                default: 0
            },
            label: {
                type: 'string',
                default: 'Buy Now'
            },
            buttonClass: {
                type: 'string',
                default: ''
            }
        },
        edit: function (props) {
            var attrs = props.attributes;
            var setAttributes = props.setAttributes;
            return el(
                element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Button Settings', 'khm-membership'), initialOpen: true },
                        el(TextControl, {
                            label: __('Post ID', 'khm-membership'),
                            type: 'number',
                            value: attrs.postId || '',
                            onChange: function (value) {
                                var numeric = parseInt(value, 10);
                                setAttributes({ postId: isNaN(numeric) ? 0 : numeric });
                            }
                        }),
                        el(TextControl, {
                            label: __('Button Label', 'khm-membership'),
                            value: attrs.label || '',
                            onChange: function (value) {
                                setAttributes({ label: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Additional CSS classes', 'khm-membership'),
                            value: attrs.buttonClass || '',
                            onChange: function (value) {
                                setAttributes({ buttonClass: value });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    { className: 'components-placeholder' },
                    el('strong', null, __('Commerce Checkout Button', 'khm-membership')),
                    el(
                        'p',
                        null,
                        attrs.postId
                            ? __('Frontend renders an active commerce checkout button.', 'khm-membership')
                            : __('Set a Post ID in block settings to render the button.', 'khm-membership')
                    ),
                    attrs.postId
                        ? el('p', null, __('Target Post ID: ', 'khm-membership') + attrs.postId)
                        : null
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp && window.wp.blocks, window.wp && window.wp.blockEditor, window.wp && window.wp.components, window.wp && window.wp.element, window.wp && window.wp.i18n);

