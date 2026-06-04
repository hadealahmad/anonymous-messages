/**
 * Anonymous Message Block
 * 
 * A Gutenberg block for collecting anonymous messages from visitors.
 * Compatible with GreenShift builder when available.
 * 
 * This block uses server-side rendering (dynamic block) for the frontend.
 */

import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

// Block components
import edit from './edit';
import save from './save';
import blockIcon from './icon';
import attributes from './attributes';

// Block styles
import './styles.editor.scss';

// Check if block is already registered (prevents double registration)
const blockRegistry = wp.blocks.getBlockType('anonymous-messages/message-block');

if (!blockRegistry) {
    registerBlockType('anonymous-messages/message-block', {
        // Block metadata
        apiVersion: 2,
        title: __('Anonymous Messages', 'anonymous-messages'),
        description: __('A block for collecting anonymous messages from visitors', 'anonymous-messages'),
        category: 'widgets',
        icon: blockIcon,

        // Keywords for search
        keywords: [
            __('anonymous', 'anonymous-messages'),
            __('messages', 'anonymous-messages'),
            __('questions', 'anonymous-messages'),
            __('contact', 'anonymous-messages'),
            __('form', 'anonymous-messages'),
            __('feedback', 'anonymous-messages'),
        ],

        // Block supports
        supports: {
            align: true,
            alignWide: true,
            html: false,
            anchor: true,
            className: true,
            color: {
                background: true,
                text: true,
                link: true,
            },
            spacing: {
                margin: true,
                padding: true,
                blockGap: true,
            },
            typography: {
                fontSize: true,
                lineHeight: true,
                fontFamily: true,
            },
            border: {
                radius: true,
                width: true,
                color: true,
                style: true,
            },
        },

        // Example for block previews
        example: {
            attributes: {
                showCategories: true,
                showAnsweredQuestions: true,
                questionsPerPage: 10,
                enableRecaptcha: true,
            },
        },

        // Block attributes
        attributes,

        // Edit and Save components
        edit,
        save,
    });
}
