import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { RawHTML } from '@wordpress/element';
import { PanelBody, TextControl } from '@wordpress/components';

registerBlockType( 'ep/citation-qa', {
    title: 'Editorial Planner Citation QA',
    icon: 'megaphone',
    category: 'common',
    attributes: {
        sessionId: {
            type: 'string',
            default: '',
        },
        blockId: {
            type: 'string',
            default: '',
        },
    },
    edit: ( { attributes, setAttributes, clientId } ) => {
        const { sessionId, blockId } = attributes;
        
        // Set unique block ID on first render
        if ( ! blockId ) {
            setAttributes( { blockId: clientId } );
        }
        
        const uniqueId = blockId || clientId;
        const blockProps = useBlockProps();
        
        return (
            <>
                <InspectorControls>
                    <PanelBody title="Citation QA Settings">
                        <TextControl
                            label="Session ID"
                            value={ sessionId }
                            onChange={ ( value ) => setAttributes( { sessionId: value } ) }
                            help="Paste the Editorial Planner session ID to load citations."
                        />
                    </PanelBody>
                </InspectorControls>
                <div
                    { ...blockProps }
                    className="ep-citation-qa-root"
                    data-block-id={ uniqueId }
                    data-session-id={ sessionId }
                />
            </>
        );
    },
    save: ( { attributes } ) => {
        const { sessionId, blockId } = attributes;
        return (
            <div
                className="ep-citation-qa-root"
                data-block-id={ blockId }
                data-session-id={ sessionId }
            />
        );
    },
} );
