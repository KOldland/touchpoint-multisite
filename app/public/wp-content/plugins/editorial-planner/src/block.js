import { registerBlockType } from '@wordpress/blocks';
import { RawHTML } from '@wordpress/element';

registerBlockType( 'ep/citation-qa', {
    title: 'Editorial Planner Citation QA',
    icon: 'megaphone',
    category: 'common',
    edit: () => {
        return <RawHTML>{'<div id="ep-citation-qa-root"></div>'}</RawHTML>;
    },
    save: () => {
        return <RawHTML>{'<div id="ep-citation-qa-root"></div>'}</RawHTML>;
    },
} );
