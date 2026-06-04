/**
 * Block Attributes
 * 
 * Defines all editable attributes for the Anonymous Message block.
 * These correspond to the settings available in the block's inspector panel.
 * 
 * @type {Object}
 */

import { __ } from '@wordpress/i18n';

// GreenShift collections for animation support (if available)
const collectionsObjects = window.gspblib?.helpers?.collectionsObjects;

const attributes = {
    // GreenShift standard attributes (for compatibility)
    id: {
        type: 'string',
        default: null,
    },
    localId: {
        type: 'string',
    },
    staticLocalId: {
        type: 'boolean',
    },
    inlineCssStyles: {
        type: 'string',
    },
    anchor: {
        type: 'string',
    },
    animation: {
        type: 'object',
        default: collectionsObjects?.animation || {},
    },
    interactionLayers: {
        type: 'array',
    },
    styleAttributes: {
        type: 'object',
    },
    enableSpecificity: {
        type: 'boolean',
    },
    className: {
        type: 'string',
    },
    dynamicGClasses: {
        type: 'array',
    },

    // Anonymous Messages specific attributes
    showCategories: {
        type: 'boolean',
        default: true,
    },
    showAnsweredQuestions: {
        type: 'boolean',
        default: true,
    },
    questionsPerPage: {
        type: 'number',
        default: 10,
    },
    enableRecaptcha: {
        type: 'boolean',
        default: true,
    },
    placeholder: {
        type: 'string',
        default: '',
    },
    assignedUserId: {
        type: 'number',
        default: 0,
    },
    enableEmailNotifications: {
        type: 'boolean',
        default: true,
    },
    enableImageUploads: {
        type: 'boolean',
        default: true,
    },
};

export default attributes;
