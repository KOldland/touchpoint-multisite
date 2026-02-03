import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
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
    },
    edit: ( { attributes, setAttributes } ) => {
        const { sessionId } = attributes;
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
                    id="ep-citation-qa-root"
                    data-session-id={ sessionId }
                />
            </>
        );
    },
    save: ( { attributes } ) => {
        return (
            <div
                id="ep-citation-qa-root"
                data-session-id={ attributes.sessionId }
            />
        );
    },
} );
