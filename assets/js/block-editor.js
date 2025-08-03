(function() {
    'use strict';
    
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, ToggleControl, RangeControl, TextControl } = wp.components;
    const { __, sprintf } = wp.i18n;
    const { createElement: el } = wp.element;
    
    // Check if block is already registered
    if (wp.blocks.getBlockType('anonymous-messages/message-block')) {
        return;
    }
    
    registerBlockType('anonymous-messages/message-block', {
        title: __('Anonymous Messages', 'anonymous-messages'),
        description: __('A block for collecting anonymous messages from visitors', 'anonymous-messages'),
        icon: 'email-alt2',
        category: 'widgets',
        keywords: [
            __('anonymous', 'anonymous-messages'),
            __('messages', 'anonymous-messages'),
            __('questions', 'anonymous-messages'),
            __('contact', 'anonymous-messages')
        ],
        supports: {
            align: true,
            alignWide: true
        },
        attributes: {
            showCategories: {
                type: 'boolean',
                default: true
            },
            showAnsweredQuestions: {
                type: 'boolean',
                default: true
            },
            questionsPerPage: {
                type: 'number',
                default: 10
            },
            enableRecaptcha: {
                type: 'boolean',
                default: true
            },
            placeholder: {
                type: 'string',
                default: __('Ask your anonymous question here...', 'anonymous-messages')
            },
            assignedUserId: {
                type: 'number',
                default: 0
            },
            enableEmailNotifications: {
                type: 'boolean',
                default: true
            },
            enableImageUploads: {
                type: 'boolean',
                default: true
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const {
                showCategories,
                showAnsweredQuestions,
                questionsPerPage,
                enableRecaptcha,
                placeholder,
                assignedUserId,
                enableEmailNotifications,
                enableImageUploads
            } = attributes;
            
            return el('div', { className: 'anonymous-messages-editor' }, [
                // Inspector Controls (Sidebar Settings)
                el(InspectorControls, { key: 'inspector' }, [
                    el(PanelBody, {
                        title: __('Display Settings', 'anonymous-messages'),
                        initialOpen: true,
                        key: 'display-settings'
                    }, [
                        el(ToggleControl, {
                            label: __('Show Categories Filter (for answered questions)', 'anonymous-messages'),
                            checked: showCategories,
                            onChange: (value) => setAttributes({ showCategories: value }),
                            help: __('Categories are assigned by admin only. This option controls filtering of answered questions.', 'anonymous-messages'),
                            key: 'show-categories'
                        }),
                        el(ToggleControl, {
                            label: __('Show Answered Questions', 'anonymous-messages'),
                            checked: showAnsweredQuestions,
                            onChange: (value) => setAttributes({ showAnsweredQuestions: value }),
                            key: 'show-answered'
                        }),
                        showAnsweredQuestions && el(RangeControl, {
                            label: __('Questions Per Page', 'anonymous-messages'),
                            value: questionsPerPage,
                            min: 5,
                            max: 50,
                            step: 5,
                            onChange: (value) => setAttributes({ questionsPerPage: value }),
                            key: 'questions-per-page'
                        })
                    ]),
                    el(PanelBody, {
                        title: __('Form Settings', 'anonymous-messages'),
                        initialOpen: true,
                        key: 'form-settings'
                    }, [
                        el(ToggleControl, {
                            label: __('Enable reCAPTCHA', 'anonymous-messages'),
                            checked: enableRecaptcha,
                            onChange: (value) => setAttributes({ enableRecaptcha: value }),
                            help: __('Requires reCAPTCHA keys to be configured in plugin settings', 'anonymous-messages'),
                            key: 'enable-recaptcha'
                        }),
                        el(TextControl, {
                            label: __('Message Placeholder Text', 'anonymous-messages'),
                            value: placeholder,
                            onChange: (value) => setAttributes({ placeholder: value }),
                            key: 'placeholder'
                        }),
                        el(ToggleControl, {
                            label: __('Enable Image Uploads', 'anonymous-messages'),
                            checked: enableImageUploads,
                            onChange: (value) => setAttributes({ enableImageUploads: value }),
                            help: __('Allow visitors to attach images to their messages. Can override global setting.', 'anonymous-messages'),
                            key: 'enable-image-uploads'
                        }),
                        // User Selection Control
                        anonymousMessages.users && anonymousMessages.users.length > 0 && el('div', { key: 'user-selection' }, [
                            el('label', { 
                                style: { 
                                    display: 'block', 
                                    marginBottom: '8px',
                                    fontSize: '11px',
                                    fontWeight: '500',
                                    lineHeight: '1.4',
                                    textTransform: 'uppercase',
                                    color: '#1e1e1e'
                                }
                            }, __('Assigned User (Messages will be sent to this user)', 'anonymous-messages')),
                            el('select', {
                                value: assignedUserId,
                                onChange: (e) => setAttributes({ assignedUserId: parseInt(e.target.value) }),
                                style: { width: '100%', marginBottom: '8px' }
                            }, [
                                el('option', { value: 0 }, __('No specific user (Admin only)', 'anonymous-messages')),
                                ...anonymousMessages.users.map(user => 
                                    el('option', { 
                                        value: user.id,
                                        key: user.id 
                                    }, user.name + ' (' + user.email + ')')
                                )
                            ]),
                            el('p', {
                                style: {
                                    fontSize: '11px',
                                    color: '#757575',
                                    fontStyle: 'italic',
                                    marginTop: '4px',
                                    marginBottom: '0'
                                }
                            }, __('If no user is selected, only administrators can view the messages.', 'anonymous-messages'))
                        ]),
                        // Email Notification Toggle
                        el(ToggleControl, {
                            label: __('Send Email Notification', 'anonymous-messages'),
                            checked: enableEmailNotifications,
                            onChange: (value) => setAttributes({ enableEmailNotifications: value }),
                            help: assignedUserId > 0 ? 
                                __('If enabled, the assigned user will receive an email for each new submission.', 'anonymous-messages') :
                                __('If enabled, the site administrator will receive an email for each new submission.', 'anonymous-messages'),
                            key: 'enable-email-notifications'
                        })
                    ])
                ]),
                
                // Block Preview in Editor
                el('div', { 
                    className: 'anonymous-messages-block-preview',
                    key: 'preview'
                }, [
                    el('div', { className: 'anonymous-messages-preview-header' }, [
                        el('h3', {}, __('Anonymous Messages Block', 'anonymous-messages')),
                        el('p', {}, __('Preview of how the block will appear on the frontend', 'anonymous-messages'))
                    ]),
                    
                    // Category Filter Preview
                    showCategories && el('div', { className: 'category-filter-preview' }, [
                        el('label', {}, __('Filter by Category:', 'anonymous-messages')),
                        el('select', { disabled: true }, [
                            el('option', {}, __('All Categories', 'anonymous-messages')),
                            el('option', {}, __('General', 'anonymous-messages')),
                            el('option', {}, __('Technical', 'anonymous-messages'))
                        ])
                    ]),
                    
                    // Message Form Preview
                    el('div', { className: 'message-form-preview' }, [
                        el('textarea', {
                            placeholder: placeholder,
                            disabled: true,
                            rows: 4
                        }),
                        el('div', { className: 'form-actions-preview' }, [
                            enableRecaptcha && el('div', { className: 'recaptcha-info' }, [
                                el('small', {}, __('⚡ reCAPTCHA protection enabled', 'anonymous-messages'))
                            ]),
                            el('button', { 
                                type: 'button',
                                disabled: true,
                                className: 'submit-button-preview'
                            }, __('Send Message', 'anonymous-messages'))
                        ])
                    ]),
                    
                    // Answered Questions Preview
                    showAnsweredQuestions && el('div', { className: 'answered-questions-preview' }, [
                        el('h4', {}, __('Previously Answered Questions', 'anonymous-messages')),
                        el('div', { className: 'question-item-preview' }, [
                            el('div', { className: 'question-header' }, [
                                el('strong', {}, __('Curious Fox 123:', 'anonymous-messages')),
                                el('span', { className: 'question-category' }, __('General', 'anonymous-messages'))
                            ]),
                            el('p', { className: 'question-text' }, __('This is a sample question that has been answered...', 'anonymous-messages')),
                            el('div', { className: 'question-response' }, [
                                el('strong', {}, __('Answer:', 'anonymous-messages')),
                                el('p', {}, __('This is a sample answer to the question.', 'anonymous-messages'))
                            ])
                        ]),
                        el('p', { className: 'questions-info' }, 
                            sprintf(__('Showing %d questions per page', 'anonymous-messages'), questionsPerPage)
                        )
                    ])
                ])
            ]);
        },
        
        save: function() {
            // Return null since we're using render_callback
            return null;
        }
    });
})();