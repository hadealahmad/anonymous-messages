/**
 * Child Blocks for Anonymous Messages Form
 * 
 * Defines message-textarea, image-uploader, submit-button, and questions-list blocks.
 */

import { __ } from '@wordpress/i18n';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, RangeControl, ToggleControl } from '@wordpress/components';

// 1. TEXTAREA BLOCK
if (!getBlockType('anonymous-messages/message-textarea')) {
    registerBlockType('anonymous-messages/message-textarea', {
        title: __('Message Textarea', 'anonymous-messages'),
        parent: ['anonymous-messages/message-block'],
        icon: 'editor-paragraph',
        category: 'widgets',
        description: __('The textarea input field for anonymous messages.', 'anonymous-messages'),
        attributes: {
            placeholder: {
                type: 'string',
                default: __('Ask your anonymous question here...', 'anonymous-messages')
            },
            rows: {
                type: 'number',
                default: 4
            },
            helpText: {
                type: 'string',
                default: __('Your message will be sent anonymously. No personal information is collected.', 'anonymous-messages')
            }
        },
        supports: {
            color: {
                background: true,
                text: true
            },
            spacing: {
                margin: true,
                padding: true
            },
            typography: {
                fontSize: true
            },
            border: {
                radius: true,
                width: true,
                color: true,
                style: true
            }
        },
        edit: function Edit({ attributes, setAttributes }) {
            const { placeholder, rows, helpText } = attributes;
            const blockProps = useBlockProps({ className: 'form-group message-textarea-container' });

            return (
                <div {...blockProps}>
                    <InspectorControls>
                        <PanelBody title={__('Textarea Settings', 'anonymous-messages')} initialOpen={true}>
                            <TextControl
                                label={__('Placeholder Text', 'anonymous-messages')}
                                value={placeholder}
                                onChange={(val) => setAttributes({ placeholder: val })}
                            />
                            <RangeControl
                                label={__('Rows', 'anonymous-messages')}
                                value={rows}
                                onChange={(val) => setAttributes({ rows: val })}
                                min={2}
                                max={15}
                            />
                            <TextControl
                                label={__('Help Text', 'anonymous-messages')}
                                value={helpText}
                                onChange={(val) => setAttributes({ helpText: val })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <textarea
                        className="message-input"
                        placeholder={placeholder}
                        rows={rows}
                        disabled
                        style={{ width: '100%', resize: 'none' }}
                    />
                    {helpText && <div className="form-help" style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>{helpText}</div>}
                </div>
            );
        },
        save: function Save({ attributes }) {
            const { placeholder, rows, helpText } = attributes;
            const blockProps = useBlockProps.save({ className: 'form-group message-textarea-container' });

            return (
                <div {...blockProps}>
                    <textarea
                        name="message"
                        className="message-input"
                        placeholder={placeholder}
                        rows={rows}
                        required
                    />
                    {helpText && <div className="form-help">{helpText}</div>}
                </div>
            );
        }
    });
}

// 2. IMAGE UPLOADER BLOCK
if (!getBlockType('anonymous-messages/image-uploader')) {
    registerBlockType('anonymous-messages/image-uploader', {
        title: __('Image Uploader', 'anonymous-messages'),
        parent: ['anonymous-messages/message-block'],
        icon: 'format-image',
        category: 'widgets',
        description: __('Adds an image attachment area to the anonymous message form.', 'anonymous-messages'),
        attributes: {
            labelText: {
                type: 'string',
                default: __('Attach Images (Optional)', 'anonymous-messages')
            },
            buttonText: {
                type: 'string',
                default: __('Choose Images', 'anonymous-messages')
            }
        },
        supports: {
            spacing: {
                margin: true,
                padding: true
            }
        },
        edit: function Edit({ attributes, setAttributes }) {
            const { labelText, buttonText } = attributes;
            const blockProps = useBlockProps({ className: 'form-group image-upload-section' });

            return (
                <div {...blockProps}>
                    <InspectorControls>
                        <PanelBody title={__('Image Uploader Settings', 'anonymous-messages')} initialOpen={true}>
                            <TextControl
                                label={__('Label Text', 'anonymous-messages')}
                                value={labelText}
                                onChange={(val) => setAttributes({ labelText: val })}
                            />
                            <TextControl
                                label={__('Button Text', 'anonymous-messages')}
                                value={buttonText}
                                onChange={(val) => setAttributes({ buttonText: val })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <label className="image-upload-label">{labelText}</label>
                    <div className="image-upload-container" style={{ border: '1px dashed #ccc', padding: '10px', borderRadius: '4px', textAlign: 'center', background: '#fcfcfc' }}>
                        <button type="button" className="image-upload-button" disabled style={{ pointerEvents: 'none' }}>
                            <span className="upload-icon">📁 </span>
                            <span className="upload-text">{buttonText}</span>
                        </button>
                        <div style={{ fontSize: '11px', color: '#999', marginTop: '5px' }}>
                            {__('Image upload preview (configured via plugin settings on frontend)', 'anonymous-messages')}
                        </div>
                    </div>
                </div>
            );
        },
        save: function Save() {
            // Rendered dynamically using PHP to fetch options from settings page
            return null;
        }
    });
}

// 3. SUBMIT BUTTON BLOCK
if (!getBlockType('anonymous-messages/submit-button')) {
    registerBlockType('anonymous-messages/submit-button', {
        title: __('Submit Button', 'anonymous-messages'),
        parent: ['anonymous-messages/message-block'],
        icon: 'button',
        category: 'widgets',
        description: __('Submit action button for the anonymous message form.', 'anonymous-messages'),
        attributes: {
            buttonText: {
                type: 'string',
                default: __('Send Message', 'anonymous-messages')
            }
        },
        supports: {
            color: {
                background: true,
                text: true
            },
            spacing: {
                margin: true,
                padding: true
            },
            border: {
                radius: true,
                width: true,
                color: true,
                style: true
            }
        },
        edit: function Edit({ attributes, setAttributes }) {
            const { buttonText } = attributes;
            const blockProps = useBlockProps({ className: 'form-actions' });

            return (
                <div {...blockProps}>
                    <InspectorControls>
                        <PanelBody title={__('Button Settings', 'anonymous-messages')} initialOpen={true}>
                            <TextControl
                                label={__('Button Text', 'anonymous-messages')}
                                value={buttonText}
                                onChange={(val) => setAttributes({ buttonText: val })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <button type="submit" className="submit-button" disabled style={{ pointerEvents: 'none' }}>
                        <span className="button-text">{buttonText}</span>
                    </button>
                </div>
            );
        },
        save: function Save({ attributes }) {
            const { buttonText } = attributes;
            const blockProps = useBlockProps.save({ className: 'form-actions' });

            return (
                <div {...blockProps}>
                    <button type="submit" className="submit-button">
                        <span className="button-text">{buttonText}</span>
                        <span className="button-spinner" style={{ display: 'none' }}>
                            {__('Submitting...', 'anonymous-messages')}
                        </span>
                    </button>
                    <div className="rate-limit-timer" style={{ display: 'none' }}>
                        <span className="timer-text">
                            Please wait <strong className="timer-seconds"></strong> seconds before sending another message.
                        </span>
                    </div>
                </div>
            );
        }
    });
}

// 4. QUESTIONS LIST BLOCK
if (!getBlockType('anonymous-messages/questions-list')) {
    registerBlockType('anonymous-messages/questions-list', {
        title: __('Answered Questions List', 'anonymous-messages'),
        parent: ['anonymous-messages/message-block'],
        icon: 'list-view',
        category: 'widgets',
        description: __('Lists previously answered questions below the form.', 'anonymous-messages'),
        attributes: {
            titleText: {
                type: 'string',
                default: __('Previously Answered Questions', 'anonymous-messages')
            },
            showSearch: {
                type: 'boolean',
                default: true
            },
            showCategories: {
                type: 'boolean',
                default: true
            }
        },
        supports: {
            spacing: {
                margin: true,
                padding: true
            }
        },
        edit: function Edit({ attributes, setAttributes }) {
            const { titleText, showSearch, showCategories } = attributes;
            const blockProps = useBlockProps({ className: 'anonymous-messages-questions' });

            return (
                <div {...blockProps}>
                    <InspectorControls>
                        <PanelBody title={__('Questions List Settings', 'anonymous-messages')} initialOpen={true}>
                            <TextControl
                                label={__('Section Title', 'anonymous-messages')}
                                value={titleText}
                                onChange={(val) => setAttributes({ titleText: val })}
                            />
                            <ToggleControl
                                label={__('Show Search Input', 'anonymous-messages')}
                                checked={showSearch}
                                onChange={(val) => setAttributes({ showSearch: val })}
                            />
                            <ToggleControl
                                label={__('Show Category Filter', 'anonymous-messages')}
                                checked={showCategories}
                                onChange={(val) => setAttributes({ showCategories: val })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    <h3 className="questions-title">{titleText}</h3>
                    <div style={{ padding: '15px', border: '1px dashed #ccc', borderRadius: '4px', background: '#fafafa', color: '#666', fontSize: '13px' }}>
                        {__('Answered Questions Loader (Will load list of questions on frontend)', 'anonymous-messages')}
                        {showSearch && <div style={{ marginTop: '5px', fontSize: '11px', color: '#555' }}>✓ {__('Search enabled', 'anonymous-messages')}</div>}
                        {showCategories && <div style={{ marginTop: '2px', fontSize: '11px', color: '#555' }}>✓ {__('Category filter enabled', 'anonymous-messages')}</div>}
                    </div>
                </div>
            );
        },
        save: function Save() {
            // Rendered dynamically using PHP to load questions and categories dynamically from database
            return null;
        }
    });
}
