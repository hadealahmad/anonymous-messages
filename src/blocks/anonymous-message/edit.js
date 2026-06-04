/**
 * Edit Component
 * 
 * Renders the block in the editor with a live preview and inspector controls.
 * Supports GreenShift animations and styling when the library is available.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

// Import block components
import Inspector from './inspector';

// Check if GreenShift library is available
const gspblib = window.gspblib || null;
const hasGreenShift = !!gspblib;

// GreenShift utilities and components (conditionally used)
const gspb_setBlockId = gspblib?.utilities?.gspb_setBlockId;
const gspb_Css_Final = gspblib?.utilities?.gspb_Css_Final;
const getFinalCssFromDynamicLocalClasses = gspblib?.utilities?.getFinalCssFromDynamicLocalClasses;
const getCssFromStyleAttributes = gspblib?.utilities?.getCssFromStyleAttributes;
const aos_animation_cssGen = gspblib?.utilities?.aos_animation_cssGen;
const getDataAttributesfromDynamic = gspblib?.utilities?.getDataAttributesfromDynamic;
const AnimationRenderProps = gspblib?.collections?.AnimationRenderProps;
const AnimationWrapper = gspblib?.collections?.AnimationWrapper;
const BlockToolBar = gspblib?.components?.BlockToolBar;
const gspb_convert_styles_for_editor = gspblib?.helpers?.gspb_convert_styles_for_editor;

export default function Edit(props) {
    const { attributes, setAttributes, isSelected } = props;
    const {
        id,
        localId,
        animation,
        styleAttributes,
        enableSpecificity,
        interactionLayers,
        anchor,
        showCategories,
        showAnsweredQuestions,
        questionsPerPage,
        enableRecaptcha,
        placeholder,
        assignedUserId,
        enableImageUploads,
    } = attributes;

    // Animation ref for GreenShift
    const animationRef = useRef();

    // Generate unique ID for the block (GreenShift pattern)
    useEffect(() => {
        if (hasGreenShift && gspb_setBlockId) {
            gspb_setBlockId(props, 2);
        }
    }, []);

    // Set localId when id changes (GreenShift pattern)
    useEffect(() => {
        if (hasGreenShift && id != null) {
            if (attributes.localId && attributes.localId === id) {
                // do nothing
            } else {
                if (!(attributes.staticLocalId && attributes.localId)) {
                    setAttributes({ localId: id });
                }
            }
        }
    }, [id]);

    // Build animation props if GreenShift is available
    let AnimationProps = {};
    if (hasGreenShift && AnimationRenderProps) {
        AnimationProps = AnimationRenderProps(animation, interactionLayers);
    }

    // Get dynamic data attributes if GreenShift is available
    let DynamicDataAttributes = {};
    if (hasGreenShift && getDataAttributesfromDynamic) {
        DynamicDataAttributes = getDataAttributesfromDynamic(props);
    }

    // Block props with GreenShift support
    const blockPropsConfig = {
        className: `anonymous-messages-editor ${localId ? localId : ''}`,
        ref: animationRef,
        ...DynamicDataAttributes,
        ...AnimationProps,
    };

    if (hasGreenShift && id) {
        blockPropsConfig['data-gspb-block-id'] = id;
    }

    const blockProps = useBlockProps(blockPropsConfig);

    // Generate CSS if GreenShift is available
    let final_css = '';
    let editor_css = '';

    if (hasGreenShift && localId) {
        const css_selector_by_user = '.' + localId;

        // Get Final CSS from Dynamic Local Classes
        if (getFinalCssFromDynamicLocalClasses) {
            final_css = getFinalCssFromDynamicLocalClasses(props, final_css);
        }

        // Get CSS from Style Attributes
        if (styleAttributes && getCssFromStyleAttributes) {
            let local_css = getCssFromStyleAttributes(styleAttributes, css_selector_by_user, '', enableSpecificity);
            if (local_css) {
                final_css += local_css;
            }
        }

        // Animation CSS Generation
        if (aos_animation_cssGen) {
            final_css = aos_animation_cssGen(animation, css_selector_by_user, final_css, props);
        }

        // Convert for editor
        if (gspb_convert_styles_for_editor && final_css) {
            editor_css = gspb_convert_styles_for_editor(final_css);
        }

        // Store final CSS
        if (gspb_Css_Final && id) {
            gspb_Css_Final(id, final_css, props);
        }
    }

    // Apply anchor if set
    if (anchor) {
        blockProps.id = anchor;
    }

    // Get placeholder text with default
    const placeholderText = placeholder || __('Ask your anonymous question here...', 'anonymous-messages');

    // Render content
    const TEMPLATE = [
        [ 'anonymous-messages/message-textarea' ],
        [ 'anonymous-messages/image-uploader' ],
        [ 'anonymous-messages/submit-button' ],
        [ 'anonymous-messages/questions-list' ]
    ];

    const renderContent = () => (
        <div {...blockProps}>
            <div className="anonymous-messages-editor-container" style={{ border: '1px dashed #bbb', padding: '15px', borderRadius: '4px', background: '#fafafa' }}>
                <div style={{ fontSize: '11px', color: '#777', marginBottom: '12px', textTransform: 'uppercase', fontWeight: '600', letterSpacing: '0.05em' }}>
                    {__('Anonymous Message Form Container (Rearrange or edit fields below)', 'anonymous-messages')}
                </div>
                <InnerBlocks 
                    template={ TEMPLATE }
                    allowedBlocks={[
                        'anonymous-messages/message-textarea',
                        'anonymous-messages/image-uploader',
                        'anonymous-messages/submit-button',
                        'anonymous-messages/questions-list'
                    ]}
                    templateLock={ false }
                />
            </div>
            {/* Editor CSS injection */}
            {editor_css && (
                <style
                    dangerouslySetInnerHTML={{
                        __html: editor_css,
                    }}
                />
            )}
        </div>
    );

    // Wrap with GreenShift AnimationWrapper if available
    if (hasGreenShift && AnimationWrapper) {
        return (
            <>
                <AnimationWrapper attributes={attributes} props={props} animationExtRef={animationRef}>
                    {isSelected && (
                        <>
                            <Inspector {...props} />
                            {BlockToolBar && <BlockToolBar {...props} />}
                        </>
                    )}
                    {renderContent()}
                </AnimationWrapper>
            </>
        );
    }

    // Fallback without GreenShift
    return (
        <>
            {isSelected && <Inspector {...props} />}
            {renderContent()}
        </>
    );
}
