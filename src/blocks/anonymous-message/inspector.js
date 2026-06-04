/**
 * Inspector Component
 * 
 * Renders the sidebar inspector controls for the Anonymous Messages block.
 * Includes settings for display, form behavior, user assignment, and notifications.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    ToggleControl,
    RangeControl,
    TextControl,
    SelectControl,
    TextareaControl,
} from '@wordpress/components';

// Check if GreenShift library is available
const gspblib = window.gspblib || null;
const hasGreenShift = !!gspblib;

// GreenShift components (conditionally imported)
const GlobalClasses = gspblib?.components?.GlobalClasses;
const AttributeTabs = gspblib?.collections?.AttributeTabs;
const Animation = gspblib?.collections?.Animation;
const InteractionsPanel = gspblib?.collections?.InteractionsPanel;

const Inspector = (props) => {
    const { attributes, setAttributes } = props;
    const {
        showCategories,
        showAnsweredQuestions,
        questionsPerPage,
        enableRecaptcha,
        placeholder,
        assignedUserId,
        enableEmailNotifications,
        enableImageUploads,
        anchor,
        styleAttributes,
        enableSpecificity,
        localId,
        staticLocalId,
    } = attributes;

    // Get users and categories from localized data
    const anonymousMessagesData = window.anonymousMessages || {};
    const users = anonymousMessagesData.users || [];

    // User options for the select control
    const userOptions = [
        { value: 0, label: __('No specific user (Admin only)', 'anonymous-messages') },
        ...users.map((user) => ({
            value: user.id,
            label: `${user.name} (${user.email})`,
        })),
    ];

    return (
        <InspectorControls>
            <div className="anonymous-messages-inspector">
                {/* GreenShift Global Classes (if available) */}
                {hasGreenShift && GlobalClasses && (
                    <GlobalClasses flexChild={true} {...props} />
                )}

                {/* Display Settings Panel */}
                <PanelBody
                    title={__('Display Settings', 'anonymous-messages')}
                    initialOpen={true}
                >
                    <ToggleControl
                        label={__('Show Categories Filter', 'anonymous-messages')}
                        checked={showCategories}
                        onChange={(value) => setAttributes({ showCategories: value })}
                        help={__(
                            'Display category filter for answered questions. Categories are assigned by admin only.',
                            'anonymous-messages'
                        )}
                    />

                    <ToggleControl
                        label={__('Show Answered Questions', 'anonymous-messages')}
                        checked={showAnsweredQuestions}
                        onChange={(value) => setAttributes({ showAnsweredQuestions: value })}
                        help={__(
                            'Display the list of previously answered questions below the form.',
                            'anonymous-messages'
                        )}
                    />

                    {showAnsweredQuestions && (
                        <RangeControl
                            label={__('Questions Per Page', 'anonymous-messages')}
                            value={questionsPerPage}
                            onChange={(value) => setAttributes({ questionsPerPage: value })}
                            min={5}
                            max={50}
                            step={5}
                        />
                    )}
                </PanelBody>

                {/* Form Settings Panel */}
                <PanelBody
                    title={__('Form Settings', 'anonymous-messages')}
                    initialOpen={true}
                >
                    <ToggleControl
                        label={__('Enable reCAPTCHA', 'anonymous-messages')}
                        checked={enableRecaptcha}
                        onChange={(value) => setAttributes({ enableRecaptcha: value })}
                        help={__(
                            'Requires reCAPTCHA keys to be configured in plugin settings.',
                            'anonymous-messages'
                        )}
                    />

                    <TextControl
                        label={__('Message Placeholder Text', 'anonymous-messages')}
                        value={placeholder}
                        onChange={(value) => setAttributes({ placeholder: value })}
                        placeholder={__('Ask your anonymous question here...', 'anonymous-messages')}
                    />

                    <ToggleControl
                        label={__('Enable Image Uploads', 'anonymous-messages')}
                        checked={enableImageUploads}
                        onChange={(value) => setAttributes({ enableImageUploads: value })}
                        help={__(
                            'Allow visitors to attach images to their messages.',
                            'anonymous-messages'
                        )}
                    />
                </PanelBody>

                {/* User & Notifications Panel */}
                <PanelBody
                    title={__('User & Notifications', 'anonymous-messages')}
                    initialOpen={false}
                >
                    {users.length > 0 && (
                        <SelectControl
                            label={__('Assigned User', 'anonymous-messages')}
                            value={assignedUserId}
                            options={userOptions}
                            onChange={(value) => setAttributes({ assignedUserId: parseInt(value, 10) })}
                            help={__(
                                'Messages will be sent to this user. If no user is selected, only administrators can view messages.',
                                'anonymous-messages'
                            )}
                        />
                    )}

                    <ToggleControl
                        label={__('Send Email Notifications', 'anonymous-messages')}
                        checked={enableEmailNotifications}
                        onChange={(value) => setAttributes({ enableEmailNotifications: value })}
                        help={
                            assignedUserId > 0
                                ? __('The assigned user will receive an email for each new submission.', 'anonymous-messages')
                                : __('The site administrator will receive an email for each new submission.', 'anonymous-messages')
                        }
                    />
                </PanelBody>

                {/* GreenShift Style Attributes (if available) */}
                {hasGreenShift && AttributeTabs && (
                    <PanelBody
                        title={__('Style Settings', 'anonymous-messages')}
                        initialOpen={false}
                        className="gst-inspector-tab gst-elements-styles"
                    >
                        <AttributeTabs
                            attributeName="styleAttributes"
                            {...props}
                            defaultTab=""
                            includes={['typography', 'color', 'spacing', 'shadow', 'border', 'position', 'size', 'responsive']}
                            selfAlign="both"
                        />
                    </PanelBody>
                )}

                {/* GreenShift Animation (if available) */}
                {hasGreenShift && Animation && (
                    <PanelBody
                        title={__('Animation', 'anonymous-messages')}
                        initialOpen={false}
                    >
                        <Animation attributeName="animation" {...props} />
                    </PanelBody>
                )}

                {/* GreenShift Interactions (if available) */}
                {hasGreenShift && InteractionsPanel && (
                    <PanelBody
                        title={__('Interaction Layers', 'anonymous-messages')}
                        initialOpen={false}
                    >
                        <InteractionsPanel {...props} />
                    </PanelBody>
                )}

                {/* Advanced Settings */}
                <PanelBody
                    title={__('Advanced', 'anonymous-messages')}
                    initialOpen={false}
                >
                    <TextareaControl
                        label={__('HTML Anchor', 'anonymous-messages')}
                        value={anchor || ''}
                        onChange={(value) => setAttributes({ anchor: value.trim() })}
                        help={__(
                            'Enter an ID to link directly to this block. Available for single-page navigation.',
                            'anonymous-messages'
                        )}
                    />
                </PanelBody>
            </div>
        </InspectorControls>
    );
};

export default Inspector;
