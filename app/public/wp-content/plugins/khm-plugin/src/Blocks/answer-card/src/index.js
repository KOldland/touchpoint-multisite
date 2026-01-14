/**
 * AnswerCard Gutenberg Block
 *
 * A structured answer card for GEO (Generative Engine Optimization) that outputs
 * semantic content with JSON-LD schema markup.
 *
 * @package KHM\Blocks\AnswerCard
 */

console.log('[KHM AnswerCard] Script loading...');

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
    TextControl,
    TextareaControl,
    ToggleControl,
    Button,
    PanelBody,
    PanelRow,
    ExternalLink,
    Notice,
    Spinner,
} from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Fragment, useState, useCallback } from '@wordpress/element';
import { trash, plus, warning } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

import './editor.scss';

/**
 * Word count helper
 */
const countWords = ( text ) => {
    if ( ! text ) return 0;
    return text.trim().split( /\s+/ ).filter( ( word ) => word.length > 0 ).length;
};

/**
 * Score indicator component
 */
const ScoreIndicator = ( { score, isLoading } ) => {
    if ( isLoading ) {
        return (
            <div className="khm-answer-card-score khm-answer-card-score--loading">
                <Spinner />
                <span>{ __( 'Calculating...', 'khm-membership' ) }</span>
            </div>
        );
    }

    if ( score === null || score === undefined ) {
        return null;
    }

    const scoreNum = parseFloat( score );
    let scoreClass = 'low';
    if ( scoreNum >= 70 ) {
        scoreClass = 'high';
    } else if ( scoreNum >= 40 ) {
        scoreClass = 'medium';
    }

    return (
        <div className={ `khm-answer-card-score khm-answer-card-score--${ scoreClass }` }>
            <strong>{ __( 'GEO Score:', 'khm-membership' ) }</strong>
            <span className="khm-answer-card-score__value">{ scoreNum.toFixed( 1 ) }</span>
        </div>
    );
};

/**
 * Main Edit component
 */
const Edit = ( props ) => {
    const { attributes, setAttributes } = props;
    const {
        question,
        conciseAnswer,
        keyPoints,
        citations,
        entities,
        exposeInSchema,
    } = attributes;

    const [ score, setScore ] = useState( null );
    const [ isScoring, setIsScoring ] = useState( false );
    const [ scoreError, setScoreError ] = useState( null );

    const blockProps = useBlockProps( {
        className: 'khm-answer-card-editor',
    } );

    // Word count for concise answer
    const wordCount = countWords( conciseAnswer );
    const isWordCountGood = wordCount >= 40 && wordCount <= 80;
    const isWordCountWarning = wordCount > 0 && ( wordCount < 40 || wordCount > 80 );

    /**
     * Calculate score on demand
     */
    const calculateScore = useCallback( async () => {
        setIsScoring( true );
        setScoreError( null );

        try {
            const result = await apiFetch( {
                path: '/khm-geo/v1/score',
                method: 'POST',
                data: {
                    question,
                    concise_answer: conciseAnswer,
                    key_points: keyPoints,
                    citations,
                    entities,
                },
            } );

            if ( result && result.total_score !== undefined ) {
                setScore( result.total_score );
            }
        } catch ( error ) {
            setScoreError( error.message || __( 'Failed to calculate score', 'khm-membership' ) );
        } finally {
            setIsScoring( false );
        }
    }, [ question, conciseAnswer, keyPoints, citations, entities ] );

    /**
     * Key Points handlers
     */
    const updateKeyPoint = ( index, value ) => {
        const kp = Array.isArray( keyPoints ) ? [ ...keyPoints ] : [];
        kp[ index ] = value;
        setAttributes( { keyPoints: kp } );
    };

    const addKeyPoint = () => {
        const kp = Array.isArray( keyPoints ) ? [ ...keyPoints ] : [];
        kp.push( '' );
        setAttributes( { keyPoints: kp } );
    };

    const removeKeyPoint = ( index ) => {
        const kp = Array.isArray( keyPoints ) ? [ ...keyPoints ] : [];
        kp.splice( index, 1 );
        setAttributes( { keyPoints: kp } );
    };

    /**
     * Citations handlers
     */
    const addCitation = () => {
        const c = Array.isArray( citations ) ? [ ...citations ] : [];
        c.push( { title: '', url: '' } );
        setAttributes( { citations: c } );
    };

    const updateCitation = ( index, key, value ) => {
        const c = Array.isArray( citations ) ? [ ...citations ] : [];
        c[ index ] = { ...c[ index ], [ key ]: value };
        setAttributes( { citations: c } );
    };

    const removeCitation = ( index ) => {
        const c = Array.isArray( citations ) ? [ ...citations ] : [];
        c.splice( index, 1 );
        setAttributes( { citations: c } );
    };

    /**
     * Entities handlers
     */
    const addEntity = () => {
        const e = Array.isArray( entities ) ? [ ...entities ] : [];
        e.push( { name: '', sameAs: '' } );
        setAttributes( { entities: e } );
    };

    const updateEntity = ( index, key, value ) => {
        const e = Array.isArray( entities ) ? [ ...entities ] : [];
        e[ index ] = { ...e[ index ], [ key ]: value };
        setAttributes( { entities: e } );
    };

    const removeEntity = ( index ) => {
        const e = Array.isArray( entities ) ? [ ...entities ] : [];
        e.splice( index, 1 );
        setAttributes( { entities: e } );
    };

    /**
     * Parse comma-separated entities (simple mode)
     */
    const updateEntitiesFromString = ( str ) => {
        const arr = str
            .split( ',' )
            .map( ( s ) => s.trim() )
            .filter( ( s ) => s.length )
            .map( ( name ) => ( { name, sameAs: '' } ) );
        setAttributes( { entities: arr } );
    };

    const entitiesAsString = ( entities || [] )
        .map( ( e ) => ( typeof e === 'string' ? e : e.name ) )
        .join( ', ' );

    return (
        <Fragment>
            <InspectorControls>
                <PanelBody title={ __( 'AnswerCard Settings', 'khm-membership' ) } initialOpen={ true }>
                    <PanelRow>
                        <ToggleControl
                            label={ __( 'Include in JSON-LD schema', 'khm-membership' ) }
                            help={ __( 'When enabled, this card will be included in the page\'s structured data for search engines and AI systems.', 'khm-membership' ) }
                            checked={ !! exposeInSchema }
                            onChange={ ( val ) => setAttributes( { exposeInSchema: val } ) }
                        />
                    </PanelRow>
                </PanelBody>

                <PanelBody title={ __( 'GEO Score', 'khm-membership' ) } initialOpen={ true }>
                    <PanelRow>
                        <ScoreIndicator score={ score } isLoading={ isScoring } />
                    </PanelRow>
                    { scoreError && (
                        <Notice status="error" isDismissible={ false }>
                            { scoreError }
                        </Notice>
                    ) }
                    <PanelRow>
                        <Button
                            variant="secondary"
                            onClick={ calculateScore }
                            disabled={ isScoring }
                        >
                            { __( 'Calculate Score', 'khm-membership' ) }
                        </Button>
                    </PanelRow>
                    <p className="components-base-control__help">
                        { __( 'Score is also calculated automatically when you save the post.', 'khm-membership' ) }
                    </p>
                </PanelBody>

                <PanelBody title={ __( 'Entities (Advanced)', 'khm-membership' ) } initialOpen={ false }>
                    <p className="components-base-control__help">
                        { __( 'Add entities with their schema.org sameAs URLs for better semantic linking.', 'khm-membership' ) }
                    </p>
                    { ( entities || [] ).map( ( entity, i ) => (
                        <div key={ `entity-adv-${ i }` } className="khm-entity-advanced">
                            <TextControl
                                label={ __( 'Entity Name', 'khm-membership' ) }
                                value={ entity.name || '' }
                                onChange={ ( val ) => updateEntity( i, 'name', val ) }
                            />
                            <TextControl
                                label={ __( 'sameAs URL (optional)', 'khm-membership' ) }
                                value={ entity.sameAs || '' }
                                onChange={ ( val ) => updateEntity( i, 'sameAs', val ) }
                                placeholder="https://www.wikidata.org/wiki/Q..."
                            />
                            <Button
                                icon={ trash }
                                isDestructive
                                onClick={ () => removeEntity( i ) }
                                label={ __( 'Remove entity', 'khm-membership' ) }
                            />
                        </div>
                    ) ) }
                    <Button
                        variant="secondary"
                        icon={ plus }
                        onClick={ addEntity }
                    >
                        { __( 'Add Entity', 'khm-membership' ) }
                    </Button>
                </PanelBody>

                <PanelBody title={ __( 'Help', 'khm-membership' ) } initialOpen={ false }>
                    <p>
                        { __( 'AnswerCards help optimize your content for AI-powered search engines and featured snippets.', 'khm-membership' ) }
                    </p>
                    <ul>
                        <li>{ __( 'Question: The query this content answers', 'khm-membership' ) }</li>
                        <li>{ __( 'Concise Answer: 40-80 words ideal for featured snippets', 'khm-membership' ) }</li>
                        <li>{ __( 'Key Points: Scannable takeaways', 'khm-membership' ) }</li>
                        <li>{ __( 'Citations: Authoritative sources', 'khm-membership' ) }</li>
                        <li>{ __( 'Entities: Key concepts and topics', 'khm-membership' ) }</li>
                    </ul>
                    <ExternalLink href="https://developers.google.com/search/docs/appearance/structured-data/faqpage">
                        { __( 'Learn about FAQ Schema', 'khm-membership' ) }
                    </ExternalLink>
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <div className="khm-answer-card-editor__header">
                    <span className="khm-answer-card-editor__icon">❓</span>
                    <span className="khm-answer-card-editor__title">
                        { __( 'AnswerCard', 'khm-membership' ) }
                    </span>
                    { ! exposeInSchema && (
                        <span className="khm-answer-card-editor__badge khm-answer-card-editor__badge--hidden">
                            { __( 'Hidden from schema', 'khm-membership' ) }
                        </span>
                    ) }
                </div>

                <TextControl
                    label={ __( 'Question', 'khm-membership' ) }
                    value={ question }
                    onChange={ ( val ) => setAttributes( { question: val } ) }
                    placeholder={ __( 'What question does this content answer?', 'khm-membership' ) }
                    className="khm-answer-card-editor__question"
                />

                <div className="khm-answer-card-editor__answer-wrapper">
                    <TextareaControl
                        label={ __( 'Concise Answer', 'khm-membership' ) }
                        value={ conciseAnswer }
                        onChange={ ( val ) => setAttributes( { conciseAnswer: val } ) }
                        rows={ 4 }
                        placeholder={ __( 'Provide a direct, concise answer (40-80 words recommended for featured snippets)', 'khm-membership' ) }
                        className="khm-answer-card-editor__answer"
                    />
                    <div className={ `khm-answer-card-editor__word-count ${ isWordCountGood ? 'good' : '' } ${ isWordCountWarning ? 'warning' : '' }` }>
                        { wordCount } { __( 'words', 'khm-membership' ) }
                        { isWordCountWarning && (
                            <span className="khm-answer-card-editor__word-hint">
                                { wordCount < 40
                                    ? __( '(aim for 40+ words)', 'khm-membership' )
                                    : __( '(consider shortening to ~80 words)', 'khm-membership' )
                                }
                            </span>
                        ) }
                    </div>
                </div>

                <div className="khm-answer-card-editor__section">
                    <label className="khm-answer-card-editor__section-label">
                        { __( 'Key Points', 'khm-membership' ) }
                    </label>
                    { ( keyPoints || [] ).map( ( kp, i ) => (
                        <div key={ `kp-${ i }` } className="khm-answer-card-editor__list-item">
                            <TextControl
                                value={ kp }
                                onChange={ ( val ) => updateKeyPoint( i, val ) }
                                placeholder={ __( 'Enter a key point...', 'khm-membership' ) }
                            />
                            <Button
                                icon={ trash }
                                isDestructive
                                onClick={ () => removeKeyPoint( i ) }
                                label={ __( 'Remove', 'khm-membership' ) }
                                className="khm-answer-card-editor__remove-btn"
                            />
                        </div>
                    ) ) }
                    <Button
                        variant="secondary"
                        icon={ plus }
                        onClick={ addKeyPoint }
                        className="khm-answer-card-editor__add-btn"
                    >
                        { __( 'Add Key Point', 'khm-membership' ) }
                    </Button>
                </div>

                <div className="khm-answer-card-editor__section">
                    <label className="khm-answer-card-editor__section-label">
                        { __( 'Citations', 'khm-membership' ) }
                    </label>
                    { ( citations || [] ).map( ( c, i ) => (
                        <div key={ `cit-${ i }` } className="khm-answer-card-editor__citation-item">
                            <div className="khm-answer-card-editor__citation-fields">
                                <TextControl
                                    placeholder={ __( 'Source title', 'khm-membership' ) }
                                    value={ c.title || '' }
                                    onChange={ ( val ) => updateCitation( i, 'title', val ) }
                                />
                                <TextControl
                                    placeholder={ __( 'URL', 'khm-membership' ) }
                                    value={ c.url || '' }
                                    onChange={ ( val ) => updateCitation( i, 'url', val ) }
                                    type="url"
                                />
                            </div>
                            <Button
                                icon={ trash }
                                isDestructive
                                onClick={ () => removeCitation( i ) }
                                label={ __( 'Remove', 'khm-membership' ) }
                                className="khm-answer-card-editor__remove-btn"
                            />
                        </div>
                    ) ) }
                    <Button
                        variant="secondary"
                        icon={ plus }
                        onClick={ addCitation }
                        className="khm-answer-card-editor__add-btn"
                    >
                        { __( 'Add Citation', 'khm-membership' ) }
                    </Button>
                </div>

                <div className="khm-answer-card-editor__section">
                    <TextControl
                        label={ __( 'Entities (comma-separated)', 'khm-membership' ) }
                        value={ entitiesAsString }
                        onChange={ updateEntitiesFromString }
                        placeholder={ __( 'e.g., SEO, Content Marketing, Google', 'khm-membership' ) }
                        help={ __( 'Key topics and concepts this content covers. Use the sidebar for advanced entity linking.', 'khm-membership' ) }
                    />
                </div>
            </div>
        </Fragment>
    );
};

/**
 * Register the block
 */
console.log('[KHM AnswerCard] About to register block...');
registerBlockType( 'khm/answer-card', {
    edit: Edit,
    save: () => null, // Server-rendered
} );
console.log('[KHM AnswerCard] Block registered successfully!');
