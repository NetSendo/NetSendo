/**
 * NetSendo for WordPress - Gutenberg Blocks Editor Script
 *
 * @package NetSendo_WordPress
 */

(function(blocks, element, blockEditor, components, i18n) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var InnerBlocks = blockEditor.InnerBlocks;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
    var ToggleControl = components.ToggleControl;
    var Placeholder = components.Placeholder;

    var data = window.netsendoBlocksData || {
        lists: [],
        defaultListId: '',
        defaultPercentage: 30,
        defaultMessage: 'Subscribe to continue reading',
        styles: [
            { value: 'inline', label: 'Inline' },
            { value: 'minimal', label: 'Minimal' },
            { value: 'card', label: 'Card' }
        ],
        gateTypes: [
            { value: 'percentage', label: 'Percentage visible' },
            { value: 'subscribers_only', label: 'Subscribers only' },
            { value: 'logged_in', label: 'Logged in only' }
        ]
    };

    // Build list options for select
    var listOptions = [{ value: '', label: __('— Default —', 'netsendo-wordpress') }];
    if (data.lists && data.lists.length) {
        data.lists.forEach(function(list) {
            listOptions.push({ value: String(list.id), label: list.name });
        });
    }

    /**
     * NetSendo Subscription Form Block
     */
    blocks.registerBlockType('netsendo/subscription-form', {
        title: __('NetSendo Form', 'netsendo-wordpress'),
        description: __('Add a newsletter subscription form', 'netsendo-wordpress'),
        icon: 'email-alt',
        category: 'widgets',
        keywords: [__('newsletter', 'netsendo-wordpress'), __('subscribe', 'netsendo-wordpress'), __('email', 'netsendo-wordpress')],
        attributes: {
            listId: { type: 'string', default: '' },
            style: { type: 'string', default: 'card' },
            buttonText: { type: 'string', default: __('Subscribe', 'netsendo-wordpress') },
            showName: { type: 'boolean', default: false },
            title: { type: 'string', default: '' },
            description: { type: 'string', default: '' }
        },

        edit: function(props) {
            var attributes = props.attributes;

            return el('div', { className: props.className },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Form Settings', 'netsendo-wordpress'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Subscription List', 'netsendo-wordpress'),
                            value: attributes.listId,
                            options: listOptions,
                            onChange: function(value) {
                                props.setAttributes({ listId: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Form Style', 'netsendo-wordpress'),
                            value: attributes.style,
                            options: data.styles,
                            onChange: function(value) {
                                props.setAttributes({ style: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Button Text', 'netsendo-wordpress'),
                            value: attributes.buttonText,
                            onChange: function(value) {
                                props.setAttributes({ buttonText: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: __('Show Name Field', 'netsendo-wordpress'),
                            checked: attributes.showName,
                            onChange: function(value) {
                                props.setAttributes({ showName: value });
                            }
                        })
                    ),
                    el(PanelBody, { title: __('Content', 'netsendo-wordpress'), initialOpen: false },
                        el(TextControl, {
                            label: __('Title', 'netsendo-wordpress'),
                            value: attributes.title,
                            onChange: function(value) {
                                props.setAttributes({ title: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Description', 'netsendo-wordpress'),
                            value: attributes.description,
                            onChange: function(value) {
                                props.setAttributes({ description: value });
                            }
                        })
                    )
                ),
                el('div', {
                    className: 'netsendo-block-preview netsendo-form-preview netsendo-form--' + attributes.style,
                    style: {
                        backgroundColor: attributes.style === 'card' ? '#f9fafb' : 'transparent',
                        padding: attributes.style === 'card' ? '24px' : '16px',
                        borderRadius: '12px',
                        border: '1px solid #e5e7eb'
                    }
                },
                    attributes.title && el('h3', { style: { margin: '0 0 8px', fontSize: '18px' } }, attributes.title),
                    attributes.description && el('p', { style: { margin: '0 0 16px', color: '#6b7280' } }, attributes.description),
                    el('div', { style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } },
                        attributes.showName && el('input', {
                            type: 'text',
                            placeholder: __('Your name', 'netsendo-wordpress'),
                            disabled: true,
                            style: { flex: 1, minWidth: '120px', padding: '10px 14px', border: '1px solid #e5e7eb', borderRadius: '8px' }
                        }),
                        el('input', {
                            type: 'email',
                            placeholder: __('Enter your email', 'netsendo-wordpress'),
                            disabled: true,
                            style: { flex: 1, minWidth: '180px', padding: '10px 14px', border: '1px solid #e5e7eb', borderRadius: '8px' }
                        }),
                        el('button', {
                            type: 'button',
                            disabled: true,
                            style: {
                                padding: '10px 20px',
                                background: 'linear-gradient(135deg, #6366f1, #4f46e5)',
                                color: '#fff',
                                border: 'none',
                                borderRadius: '8px',
                                fontWeight: '600',
                                cursor: 'default'
                            }
                        }, attributes.buttonText)
                    )
                )
            );
        },

        save: function() {
            // Server-side rendering
            return null;
        }
    });

    /**
     * NetSendo Content Gate Block
     */
    blocks.registerBlockType('netsendo/content-gate', {
        title: __('NetSendo Content Gate', 'netsendo-wordpress'),
        description: __('Restrict content to subscribers only', 'netsendo-wordpress'),
        icon: 'lock',
        category: 'widgets',
        keywords: [__('paywall', 'netsendo-wordpress'), __('restrict', 'netsendo-wordpress'), __('subscribe', 'netsendo-wordpress')],
        attributes: {
            type: { type: 'string', default: 'percentage' },
            percentage: { type: 'number', default: 30 },
            listId: { type: 'string', default: '' },
            message: { type: 'string', default: '' }
        },

        edit: function(props) {
            var attributes = props.attributes;

            return el('div', { className: props.className },
                el(InspectorControls, {},
                    el(PanelBody, { title: __('Gate Settings', 'netsendo-wordpress'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Gate Type', 'netsendo-wordpress'),
                            value: attributes.type,
                            options: data.gateTypes,
                            onChange: function(value) {
                                props.setAttributes({ type: value });
                            }
                        }),
                        attributes.type === 'percentage' && el(RangeControl, {
                            label: __('Visible Percentage', 'netsendo-wordpress'),
                            value: attributes.percentage,
                            min: 10,
                            max: 90,
                            step: 5,
                            onChange: function(value) {
                                props.setAttributes({ percentage: value });
                            }
                        }),
                        attributes.type !== 'logged_in' && el(SelectControl, {
                            label: __('Subscription List', 'netsendo-wordpress'),
                            value: attributes.listId,
                            options: listOptions,
                            onChange: function(value) {
                                props.setAttributes({ listId: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Gate Message', 'netsendo-wordpress'),
                            value: attributes.message,
                            placeholder: data.defaultMessage,
                            onChange: function(value) {
                                props.setAttributes({ message: value });
                            }
                        })
                    )
                ),
                el('div', {
                    className: 'netsendo-content-gate-editor',
                    style: {
                        border: '2px dashed #6366f1',
                        borderRadius: '12px',
                        padding: '24px',
                        background: 'linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%)'
                    }
                },
                    el('div', {
                        style: {
                            display: 'flex',
                            alignItems: 'center',
                            gap: '12px',
                            marginBottom: '16px',
                            padding: '12px 16px',
                            background: '#6366f1',
                            borderRadius: '8px',
                            color: '#fff'
                        }
                    },
                        el('span', { className: 'dashicons dashicons-lock', style: { fontSize: '20px' } }),
                        el('span', { style: { fontWeight: '600' } },
                            attributes.type === 'percentage'
                                ? __('Content Gate', 'netsendo-wordpress') + ' (' + attributes.percentage + '% ' + __('visible', 'netsendo-wordpress') + ')'
                                : attributes.type === 'subscribers_only'
                                    ? __('Subscribers Only', 'netsendo-wordpress')
                                    : __('Logged In Only', 'netsendo-wordpress')
                        )
                    ),
                    el(InnerBlocks, {
                        template: [['core/paragraph', { placeholder: __('Add gated content here...', 'netsendo-wordpress') }]],
                        templateLock: false
                    })
                )
            );
        },

        save: function() {
            return el(InnerBlocks.Content);
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);
