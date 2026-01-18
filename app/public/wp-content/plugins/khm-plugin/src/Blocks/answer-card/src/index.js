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
    SelectControl,
    Button,
    PanelBody,
    PanelRow,
    ExternalLink,
    Notice,
    Spinner,
    Tooltip,
    Icon,
    Modal,
} from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Fragment, useState, useCallback, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { trash, plus, warning, info } from '@wordpress/icons';
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
 * Generate a client-side answer_card_id (will be validated/replaced server-side)
 */
const generateClientId = () => {
    const hex = Array.from( { length: 8 }, () =>
        Math.floor( Math.random() * 16 ).toString( 16 )
    ).join( '' );
    return `AC-new-${ hex }`;
};

/**
 * Decode HTML entities for display
 */
const decodeHtmlEntities = ( text ) => {
    if ( ! text ) return '';
    const textarea = document.createElement( 'textarea' );
    textarea.innerHTML = text;
    return textarea.value;
};

/**
 * Format citation display: Title — Author (Year), Publisher
 */
const formatCitationDisplay = ( citation ) => {
    const title = decodeHtmlEntities( citation.title || citation.url || '' );
    const author = citation.author ? decodeHtmlEntities( citation.author ) : '';
    const year = citation.year ? `(${ citation.year })` : '';
    const publisher = citation.publisher ? decodeHtmlEntities( citation.publisher ) : '';

    let meta = '';
    if ( author && year ) {
        meta = `${ author } ${ year }`;
        if ( publisher ) {
            meta += `, ${ publisher }`;
        }
    } else if ( author ) {
        meta = author;
        if ( publisher ) {
            meta += `, ${ publisher }`;
        }
    } else if ( year ) {
        meta = year;
        if ( publisher ) {
            meta += `, ${ publisher }`;
        }
    } else if ( publisher ) {
        meta = publisher;
    }

    return {
        title,
        meta,
        link: citation.url || '',
        hasMeta: !! meta,
    };
};

/**
 * Get remediation tips based on reason codes
 */
const getRemediationTips = ( reasons ) => {
    const tips = [];
    
    ( reasons || [] ).forEach( r => {
        switch ( r.code ) {
            case 'only_tier3':
                tips.push( __( 'Add a Tier-1 source (peer-reviewed study with year) or Tier-2 (industry benchmark)', 'khm-membership' ) );
                break;
            case 'no_source_passage':
                tips.push( __( 'Add a source passage to strengthen evidence', 'khm-membership' ) );
                break;
            case 'missing_author':
            case 'missing_year':
                tips.push( __( 'Add author and year to citations for better attribution', 'khm-membership' ) );
                break;
            case 'few_anchor_entities':
                tips.push( __( 'Add more relevant entities/topics to improve semantic coverage', 'khm-membership' ) );
                break;
            case 'entities_unresolved':
                tips.push( __( 'Resolve suggested entities to anchor them in scoring', 'khm-membership' ) );
                break;
        }
    } );
    
    return [ ...new Set( tips ) ]; // Remove duplicates
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
        return (
            <div className="khm-answer-card-score khm-answer-card-score--unavailable">
                <strong>{ __( 'GEO Score:', 'khm-membership' ) }</strong>
                <span className="khm-answer-card-score__value">{ __( 'Unavailable', 'khm-membership' ) }</span>
            </div>
        );
    }

    const scoreNum = parseFloat( score );
    const scorePercent = Math.round( scoreNum * 100 );
    let scoreClass = 'low';
    if ( scorePercent >= 80 ) {
        scoreClass = 'high';
    } else if ( scorePercent >= 60 ) {
        scoreClass = 'medium';
    }

    return (
        <div className={ `khm-answer-card-score khm-answer-card-score--${ scoreClass }` }>
            <strong>{ __( 'GEO Score:', 'khm-membership' ) }</strong>
            <span className="khm-answer-card-score__value">{ scorePercent }%</span>
        </div>
    );
};

/**
 * Score bar component
 */
const ScoreBar = ( { label, value } ) => {
    const percent = Math.round( ( value || 0 ) * 100 );
    return (
        <div className="khm-score-bar">
            <div className="khm-score-bar__label">
                <span>{ label }</span>
                <span>{ percent }%</span>
            </div>
            <div className="khm-score-bar__track">
                <span className="khm-score-bar__fill" style={ { width: `${ percent }%` } } />
            </div>
        </div>
    );
};

/**
 * Confidence Badge with Tooltip
 */
const ConfidenceBadge = ( { confidence, reasons, tips } ) => {
    const confidencePercent = Math.round( ( confidence || 0 ) * 100 );
    let badgeClass = 'low';
    
    if ( confidence >= 0.8 ) {
        badgeClass = 'high';
    } else if ( confidence >= 0.6 ) {
        badgeClass = 'medium';
    }
    
    const tooltipContent = (
        <div className="khm-confidence-tooltip">
            <strong>{ __( 'Confidence:', 'khm-membership' ) } { confidencePercent }%</strong>
            { reasons.length > 0 && (
                <>
                    <p><strong>{ __( 'Issues:', 'khm-membership' ) }</strong></p>
                    <ul>
                        { reasons.map( ( r, i ) => (
                            <li key={ i }>{ r.label }</li>
                        ) ) }
                    </ul>
                </>
            ) }
            { tips.length > 0 && (
                <>
                    <p><strong>{ __( 'Tips:', 'khm-membership' ) }</strong></p>
                    <ul>
                        { tips.map( ( t, i ) => (
                            <li key={ i }>{ t }</li>
                        ) ) }
                    </ul>
                </>
            ) }
        </div>
    );
    
    return (
        <Tooltip text={ tooltipContent } position="bottom center">
            <span className={ `khm-confidence-badge khm-confidence-badge--${ badgeClass }` }>
                { confidencePercent }%
                { confidence < 0.6 && <Icon icon={ warning } size={ 16 } /> }
            </span>
        </Tooltip>
    );
};

/**
 * Main Edit component
 */
const Edit = ( props ) => {
    const { attributes, setAttributes } = props;
    const {
        answerCardId,
        question,
        conciseAnswer,
        keyPoints,
        citations,
        entities,
        evidence,
        topicDiscussedAt,
        siteKeywords,
        preferredSummary,
        publicSummaryLabel,
        exposeInSchema,
        requiresReview,
        reviewJustification,
    } = attributes;

    const [ score, setScore ] = useState( null );
    const [ isScoring, setIsScoring ] = useState( false );
    const [ scoreError, setScoreError ] = useState( null );
    const [ sourcePassageExpanded, setSourcePassageExpanded ] = useState( false );
    const [ scoreDetails, setScoreDetails ] = useState( null );
    const [ scoreStatus, setScoreStatus ] = useState( 'idle' );
    const [ scoreMessage, setScoreMessage ] = useState( '' );
    const [ resolverOpen, setResolverOpen ] = useState( false );
    const [ resolverIndex, setResolverIndex ] = useState( null );
    const [ resolverCandidates, setResolverCandidates ] = useState( [] );
    const [ resolverLoading, setResolverLoading ] = useState( false );
    const [ resolverError, setResolverError ] = useState( '' );

    const { postId, postTitle } = useSelect( ( select ) => {
        try {
            return {
                postId: select( 'core/editor' ).getCurrentPostId(),
                postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '',
            };
        } catch ( error ) {
            return {
                postId: null,
                postTitle: '',
            };
        }
    }, [] );

    // Generate answerCardId if not present
    useEffect( () => {
        if ( ! answerCardId ) {
            setAttributes( { answerCardId: generateClientId() } );
        }
    }, [ answerCardId, setAttributes ] );

    const confidence = scoreDetails?.scores?.evidence_confidence ?? evidence?.confidence ?? 0;
    const isLowConfidence = confidence < 0.6;
    const confidenceReasons = scoreDetails?.reasons || [];
    const remediationTips = getRemediationTips( confidenceReasons );

    const evidenceKey = JSON.stringify( evidence || {} );
    const citationsKey = JSON.stringify( citations || [] );
    const entitiesKey = JSON.stringify( entities || [] );

    const blockProps = useBlockProps( {
        className: `khm-answer-card-editor${ isLowConfidence ? ' khm-answer-card-editor--low-confidence' : '' }`,
    } );

    // Word count for concise answer
    const wordCount = countWords( conciseAnswer );
    const isWordCountGood = wordCount >= 40 && wordCount <= 80;
    const isWordCountWarning = wordCount > 0 && ( wordCount < 40 || wordCount > 80 );

    /**
     * Calculate score on demand
     */
    const buildScoringPayload = useCallback( () => ( {
        question,
        concise_answer: conciseAnswer,
        key_points: keyPoints,
        citations,
        entities,
        evidence,
    } ), [ question, conciseAnswer, keyPoints, citations, entities, evidence ] );

    const calculateScore = useCallback( async () => {
        setIsScoring( true );
        setScoreError( null );
        setScoreMessage( '' );

        try {
            const result = await apiFetch( {
                path: '/khm-geo/v1/score',
                method: 'POST',
                data: buildScoringPayload(),
            } );

            if ( result ) {
                if ( result.total_score !== undefined ) {
                    setScore( result.total_score );
                }
                setScoreDetails( result );
                setScoreStatus( 'ready' );
            }
        } catch ( error ) {
            setScoreError( error.message || __( 'Failed to calculate score', 'khm-membership' ) );
            setScoreStatus( 'error' );
        } finally {
            setIsScoring( false );
        }
    }, [ buildScoringPayload ] );

    const fetchSavedScoreDetails = useCallback( async () => {
        if ( ! postId ) {
            return;
        }

        setScoreStatus( 'loading' );
        setScoreMessage( '' );
        try {
            const result = await apiFetch( {
                path: `/khm-geo/v1/posts/${ postId }/score`,
                method: 'GET',
            } );

            const details = result?.score_details;
            if ( details && details.error ) {
                setScoreStatus( 'error' );
                setScoreMessage( details.error );
                setScoreDetails( null );
                setScore( null );
                return;
            }

            let matching = null;
            if ( Array.isArray( details ) ) {
                matching = details.find( ( item ) => item?.card?.answer_card_id === answerCardId );
                if ( ! matching && question ) {
                    matching = details.find( ( item ) => item?.card?.question === question );
                }
            }

            if ( matching && matching.score_data ) {
                setScoreDetails( matching.score_data );
                setScore( matching.score_data.total_score ?? null );
                setScoreStatus( 'ready' );
            } else {
                setScoreDetails( null );
                setScore( null );
                setScoreStatus( 'unavailable' );
            }
        } catch ( error ) {
            setScoreStatus( 'error' );
            setScoreMessage( error.message || __( 'Score unavailable', 'khm-membership' ) );
            setScoreDetails( null );
            setScore( null );
        }
    }, [ postId, answerCardId, question ] );

    useEffect( () => {
        fetchSavedScoreDetails();
    }, [ fetchSavedScoreDetails, answerCardId, question, citationsKey, entitiesKey, evidenceKey ] );

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
     * Citations handlers - with enhanced fields
     */
    const addCitation = () => {
        const c = Array.isArray( citations ) ? [ ...citations ] : [];
        c.push( { 
            title: '', 
            url: '', 
            author: '', 
            publisher: '', 
            year: '', 
            tier: 'tier3',
            doi: '',
            keywords: [],
            enableTracking: false,
        } );
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
     * Topic Discussed At handlers
     */
    const updateTopicDiscussedAt = ( key, value ) => {
        const current = topicDiscussedAt || {};
        setAttributes( { 
            topicDiscussedAt: { ...current, [ key ]: value } 
        } );
    };

    /**
     * Site Keywords handlers - parse comma-separated input
     */
    const siteKeywordsAsString = ( siteKeywords || [] ).join( ', ' );
    
    const updateSiteKeywordsFromString = ( str ) => {
        const keywords = str
            .split( ',' )
            .map( ( s ) => s.trim() )
            .filter( ( s ) => s.length > 0 );
        setAttributes( { siteKeywords: keywords } );
    };

    /**
     * Copy source passage to preferred summary
     */
    const useSourcePassageAsQuote = () => {
        const passage = decodeHtmlEntities( evidence?.source_passage || evidence?.sourcePassage || '' );
        if ( passage ) {
            const quotedPassage = `"${ passage }"`;
            const nextSummary = preferredSummary
                ? `${ preferredSummary }\n\n${ quotedPassage }`
                : quotedPassage;
            setAttributes( { preferredSummary: nextSummary } );
        }
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

    const normalizedEntities = ( entities || [] ).map( ( entity ) => (
        typeof entity === 'string' ? { name: entity, sameAs: '' } : entity
    ) );

    const getEntityQid = ( entity ) => {
        const sameAs = entity?.sameAs || '';
        if ( ! sameAs ) return '';
        const parts = sameAs.split( '/' );
        return parts[ parts.length - 1 ] || '';
    };

    const openResolver = ( index ) => {
        setResolverIndex( index );
        setResolverOpen( true );
        setResolverCandidates( [] );
        setResolverError( '' );

        const entityName = normalizedEntities[ index ]?.name || '';
        if ( ! entityName ) {
            return;
        }

        setResolverLoading( true );
        apiFetch( {
            path: `/khm-geo/v1/entity/suggest?term=${ encodeURIComponent( entityName ) }&context=${ encodeURIComponent( question || postTitle || '' ) }`,
            method: 'GET',
        } ).then( ( result ) => {
            setResolverCandidates( result?.candidates || [] );
            setResolverLoading( false );
        } ).catch( ( error ) => {
            setResolverError( error.message || __( 'Failed to load candidates', 'khm-membership' ) );
            setResolverLoading( false );
        } );
    };

    const resolveEntity = ( candidate, pageRole ) => {
        const entity = normalizedEntities[ resolverIndex ];
        if ( ! entity ) {
            return;
        }

        setResolverLoading( true );
        setResolverError( '' );

        apiFetch( {
            path: '/khm-geo/v1/entity/resolve',
            method: 'POST',
            data: {
                post_id: postId,
                entity_name: entity.name,
                qid: candidate.qid,
                label: candidate.label,
                provider: 'wikidata',
                page_role: pageRole || '',
            },
        } ).then( ( result ) => {
            const updated = [ ...normalizedEntities ];
            updated[ resolverIndex ] = {
                ...updated[ resolverIndex ],
                sameAs: result?.same_as?.url || `https://www.wikidata.org/wiki/${ candidate.qid }`,
            };
            setAttributes( { entities: updated } );

            if ( ! pageRole ) {
                const anchors = Array.isArray( evidence?.anchor_entities ) ? [ ...evidence.anchor_entities ] : [];
                if ( ! anchors.includes( entity.name ) ) {
                    anchors.push( entity.name );
                }
                setAttributes( { evidence: { ...evidence, anchor_entities: anchors } } );
            }

            setResolverOpen( false );
            setResolverLoading( false );
        } ).catch( ( error ) => {
            setResolverError( error.message || __( 'Failed to resolve entity', 'khm-membership' ) );
            setResolverLoading( false );
        } );
    };

    return (
        <Fragment>
            <InspectorControls>
                <PanelBody title={ __( 'AnswerCard Settings', 'khm-membership' ) } initialOpen={ true }>
                    { /* Answer Card ID - readonly */ }
                    <div className="khm-answer-card-id">
                        <strong>{ __( 'Card ID:', 'khm-membership' ) }</strong>
                        <code>{ answerCardId || 'Generating...' }</code>
                    </div>
                    
                    { /* Low confidence warning */ }
                    { isLowConfidence && (
                        <Notice status="warning" isDismissible={ false }>
                            <strong>{ __( 'Review Required', 'khm-membership' ) }</strong>
                            <p>{ __( 'This card has low confidence and is hidden from public schema by default.', 'khm-membership' ) }</p>
                        </Notice>
                    ) }
                    
                    <PanelRow>
                        <ToggleControl
                            label={ __( 'Include in JSON-LD schema', 'khm-membership' ) }
                            help={ isLowConfidence 
                                ? __( 'Warning: Enabling this for low-confidence cards may affect SEO quality.', 'khm-membership' )
                                : __( 'When enabled, this card will be included in the page\'s structured data.', 'khm-membership' )
                            }
                            checked={ !! exposeInSchema }
                            onChange={ ( val ) => setAttributes( { exposeInSchema: val } ) }
                        />
                    </PanelRow>
                    { isLowConfidence && (
                        <TextareaControl
                            label={ __( 'Justification for low-confidence schema', 'khm-membership' ) }
                            help={ __( 'Provide a brief rationale to include this card in JSON-LD despite low confidence.', 'khm-membership' ) }
                            value={ reviewJustification || '' }
                            onChange={ ( val ) => setAttributes( { reviewJustification: val } ) }
                            rows={ 2 }
                        />
                    ) }
                    
                    { /* Confidence badge */ }
                    <div className="khm-confidence-section">
                        <strong>{ __( 'Confidence:', 'khm-membership' ) }</strong>
                        <ConfidenceBadge 
                            confidence={ confidence } 
                            reasons={ confidenceReasons }
                            tips={ remediationTips }
                        />
                    </div>
                </PanelBody>

                <PanelBody title={ __( 'GEO Score', 'khm-membership' ) } initialOpen={ false }>
                    <PanelRow>
                        <ScoreIndicator score={ score } isLoading={ isScoring } />
                    </PanelRow>
                    { scoreStatus === 'unavailable' && (
                        <Notice status="warning" isDismissible={ false }>
                            { __( 'Score unavailable. Save the post or recompute to generate a score.', 'khm-membership' ) }
                        </Notice>
                    ) }
                    { scoreError && (
                        <Notice status="error" isDismissible={ false }>
                            { scoreError }
                        </Notice>
                    ) }
                    { scoreStatus === 'error' && scoreMessage && (
                        <Notice status="error" isDismissible={ false }>
                            { scoreMessage }
                        </Notice>
                    ) }
                    { scoreDetails?.scores && (
                        <div className="khm-score-breakdown">
                            <ScoreBar label={ __( 'Content completeness', 'khm-membership' ) } value={ scoreDetails.scores.content_completeness } />
                            <ScoreBar label={ __( 'Citation quality', 'khm-membership' ) } value={ scoreDetails.scores.citation_quality } />
                            <ScoreBar label={ __( 'Entity anchor', 'khm-membership' ) } value={ scoreDetails.scores.entity_anchor_score } />
                            <ScoreBar label={ __( 'Evidence confidence', 'khm-membership' ) } value={ scoreDetails.scores.evidence_confidence } />
                            <ScoreBar label={ __( 'Metadata', 'khm-membership' ) } value={ scoreDetails.scores.metadata } />
                        </div>
                    ) }
                    { ( scoreDetails?.citation_contributions || [] ).length > 0 && (
                        <div className="khm-score-citations">
                            <strong>{ __( 'Citation contributions', 'khm-membership' ) }</strong>
                            <ul>
                                { scoreDetails.citation_contributions.map( ( item ) => {
                                    const citation = citations?.[ item.idx ] || {};
                                    const title = decodeHtmlEntities( citation.title || citation.url || '' );
                                    const contribution = Math.round( ( item.contribution || 0 ) * 100 );
                                    return (
                                        <li key={ `contrib-${ item.idx }` }>
                                            <span>{ title || __( 'Citation', 'khm-membership' ) } #{ item.idx + 1 }</span>
                                            <span className="khm-score-citations__meta">
                                                { item.tier ? item.tier.toUpperCase() : '' } • { contribution }%
                                            </span>
                                        </li>
                                    );
                                } ) }
                            </ul>
                        </div>
                    ) }
                    { ( scoreDetails?.reasons || [] ).length > 0 && (
                        <div className="khm-score-reasons">
                            <strong>{ __( 'Confidence reasons', 'khm-membership' ) }</strong>
                            <ul>
                                { scoreDetails.reasons.map( ( reason ) => (
                                    <li key={ reason.code }>
                                        <span>{ reason.label }</span>
                                        { reason.severity && (
                                            <span className="khm-score-reasons__severity">
                                                { reason.severity }
                                            </span>
                                        ) }
                                    </li>
                                ) ) }
                            </ul>
                        </div>
                    ) }
                    <PanelRow>
                        <Button
                            variant="secondary"
                            onClick={ calculateScore }
                            disabled={ isScoring }
                        >
                            { __( 'Recompute Score', 'khm-membership' ) }
                        </Button>
                    </PanelRow>
                    <p className="components-base-control__help">
                        { __( 'Score is also calculated automatically when you save the post.', 'khm-membership' ) }
                    </p>
                </PanelBody>

                <PanelBody title={ __( 'Topic Discussed At', 'khm-membership' ) } initialOpen={ false }>
                    <p className="components-base-control__help">
                        { __( 'Configure where this topic is canonically discussed (your site). This tells AI your page is the human synthesis.', 'khm-membership' ) }
                    </p>
                    <TextControl
                        label={ __( 'Title', 'khm-membership' ) }
                        value={ topicDiscussedAt?.title || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'title', val ) }
                        placeholder={ __( 'Auto-filled with post title on save', 'khm-membership' ) }
                    />
                    <TextControl
                        label={ __( 'URL', 'khm-membership' ) }
                        value={ topicDiscussedAt?.url || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'url', val ) }
                        placeholder={ __( 'Auto-filled with post URL on save', 'khm-membership' ) }
                    />
                    <TextControl
                        label={ __( 'Author', 'khm-membership' ) }
                        value={ topicDiscussedAt?.author || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'author', val ) }
                        placeholder={ __( 'Auto-filled with post author on save', 'khm-membership' ) }
                    />
                    <TextControl
                        label={ __( 'Publisher', 'khm-membership' ) }
                        value={ topicDiscussedAt?.publisher || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'publisher', val ) }
                        placeholder={ __( 'Auto-filled with site name on save', 'khm-membership' ) }
                    />
                    <TextControl
                        label={ __( 'Date', 'khm-membership' ) }
                        value={ topicDiscussedAt?.date || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'date', val ) }
                        placeholder={ __( 'YYYY-MM-DD', 'khm-membership' ) }
                    />
                    <TextareaControl
                        label={ __( 'Note', 'khm-membership' ) }
                        value={ topicDiscussedAt?.note || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'note', val ) }
                        placeholder={ __( 'Optional editorial note about this discussion', 'khm-membership' ) }
                        rows={ 2 }
                    />
                    
                    <TextControl
                        label={ __( 'Site Keywords', 'khm-membership' ) }
                        help={ __( 'Comma-separated keywords for this card. Added to JSON-LD schema.', 'khm-membership' ) }
                        value={ siteKeywordsAsString }
                        onChange={ updateSiteKeywordsFromString }
                        placeholder={ __( 'e.g., customer retention, SaaS metrics, churn', 'khm-membership' ) }
                    />
                    
                    <TextControl
                        label={ __( 'Public Summary Label', 'khm-membership' ) }
                        help={ __( 'Optional label displayed above the answer (e.g., "Quick Answer", "Summary").', 'khm-membership' ) }
                        value={ publicSummaryLabel || '' }
                        onChange={ ( val ) => setAttributes( { publicSummaryLabel: val } ) }
                        placeholder={ __( 'e.g., Quick Answer', 'khm-membership' ) }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Evidence & Source', 'khm-membership' ) } initialOpen={ false }>
                    { evidence && evidence.tier ? (
                        <div className="khm-evidence-info">
                            <div className="khm-evidence-tier">
                                <strong>{ __( 'Evidence Tier:', 'khm-membership' ) }</strong>
                                <span className={ `khm-tier-badge khm-tier-${ evidence.tier }` }>
                                    { evidence.tier === 'tier1' ? '🏆 Tier-1' : 
                                      evidence.tier === 'tier2' ? '📊 Tier-2' : 
                                      '📰 Trade Publication' }
                                </span>
                            </div>
                            
                            { evidence.context_heading && (
                                <div className="khm-evidence-context">
                                    <strong>{ __( 'Context:', 'khm-membership' ) }</strong>
                                    <em>{ decodeHtmlEntities( evidence.context_heading ) }</em>
                                </div>
                            ) }
                            
                            { /* Source passage - collapsible */ }
                            { ( evidence.source_passage || evidence.sourcePassage ) && (
                                <div className="khm-evidence-passage">
                                    <div className="khm-evidence-passage-header">
                                        <strong>{ __( 'Source Passage:', 'khm-membership' ) }</strong>
                                        <Button
                                            variant="link"
                                            onClick={ () => setSourcePassageExpanded( ! sourcePassageExpanded ) }
                                        >
                                            { sourcePassageExpanded ? __( 'Hide source passage', 'khm-membership' ) : __( 'Show source passage', 'khm-membership' ) }
                                        </Button>
                                    </div>
                                    { sourcePassageExpanded ? (
                                        <>
                                            <blockquote className="khm-source-quote">
                                                "{ decodeHtmlEntities( evidence.source_passage || evidence.sourcePassage ) }"
                                            </blockquote>
                                            <Button
                                                variant="secondary"
                                                onClick={ useSourcePassageAsQuote }
                                                className="khm-use-quote-btn"
                                            >
                                                { __( 'Use as quote', 'khm-membership' ) }
                                            </Button>
                                        </>
                                    ) : (
                                        <p className="khm-source-preview">
                                            { decodeHtmlEntities( evidence.source_passage || evidence.sourcePassage || '' ).substring( 0, 100 ) }...
                                        </p>
                                    ) }
                                </div>
                            ) }
                            
                            { /* Preferred Summary */ }
                            <TextareaControl
                                label={ __( 'Preferred Summary', 'khm-membership' ) }
                                help={ __( 'The canonical summary for JSON-LD. If empty, concise answer is used.', 'khm-membership' ) }
                                value={ preferredSummary || '' }
                                onChange={ ( val ) => setAttributes( { preferredSummary: val } ) }
                                rows={ 3 }
                            />
                        </div>
                    ) : (
                        <Notice status="warning" isDismissible={ false }>
                            { __( 'No evidence information available. Add citations with metadata to improve confidence.', 'khm-membership' ) }
                        </Notice>
                    ) }
                </PanelBody>

                <PanelBody title={ __( 'Confidence Reasons', 'khm-membership' ) } initialOpen={ false }>
                    <p className="components-base-control__help">
                        { __( 'Use the GEO Score panel for the latest server-side confidence reasons.', 'khm-membership' ) }
                    </p>
                </PanelBody>

                <PanelBody title={ __( 'Citation Details', 'khm-membership' ) } initialOpen={ false }>
                    <p className="components-base-control__help">
                        { __( 'Configure detailed citation metadata for better SEO and attribution.', 'khm-membership' ) }
                    </p>
                    { ( citations || [] ).map( ( c, i ) => (
                        <div key={ `cit-detail-${ i }` } className="khm-citation-detail">
                            <div className="khm-citation-detail-header">
                                <strong>{ c.title || __( 'Citation', 'khm-membership' ) } #{ i + 1 }</strong>
                                <Button
                                    icon={ trash }
                                    isDestructive
                                    onClick={ () => removeCitation( i ) }
                                    label={ __( 'Remove', 'khm-membership' ) }
                                />
                            </div>
                            <TextControl
                                label={ __( 'Title', 'khm-membership' ) }
                                value={ c.title || '' }
                                onChange={ ( val ) => updateCitation( i, 'title', val ) }
                            />
                            <TextControl
                                label={ __( 'URL (Publisher Canonical)', 'khm-membership' ) }
                                value={ c.url || '' }
                                onChange={ ( val ) => updateCitation( i, 'url', val ) }
                                type="url"
                            />
                            <TextControl
                                label={ __( 'Author', 'khm-membership' ) }
                                value={ c.author || '' }
                                onChange={ ( val ) => updateCitation( i, 'author', val ) }
                                placeholder="e.g., Jürgen Schröder et al."
                            />
                            <TextControl
                                label={ __( 'Publisher', 'khm-membership' ) }
                                value={ c.publisher || '' }
                                onChange={ ( val ) => updateCitation( i, 'publisher', val ) }
                                placeholder="e.g., McKinsey & Company"
                            />
                            <TextControl
                                label={ __( 'Year', 'khm-membership' ) }
                                value={ c.year || '' }
                                onChange={ ( val ) => updateCitation( i, 'year', val ) }
                                placeholder="e.g., 2020"
                            />
                            <SelectControl
                                label={ __( 'Evidence Tier', 'khm-membership' ) }
                                value={ c.tier || 'tier3' }
                                options={ [
                                    { label: __( 'Tier 1 - Peer-reviewed Study', 'khm-membership' ), value: 'tier1' },
                                    { label: __( 'Tier 2 - Industry Benchmark', 'khm-membership' ), value: 'tier2' },
                                    { label: __( 'Tier 3 - Trade Publication', 'khm-membership' ), value: 'tier3' },
                                ] }
                                onChange={ ( val ) => updateCitation( i, 'tier', val ) }
                            />
                            <TextControl
                                label={ __( 'DOI (optional)', 'khm-membership' ) }
                                value={ c.doi || '' }
                                onChange={ ( val ) => updateCitation( i, 'doi', val ) }
                                placeholder="e.g., 10.1234/example"
                            />
                            <ToggleControl
                                label={ __( 'Enable Click Tracking', 'khm-membership' ) }
                                help={ __( 'Creates a tracked redirect link through your site. The original URL remains unchanged.', 'khm-membership' ) }
                                checked={ !! c.enableTracking }
                                onChange={ ( val ) => updateCitation( i, 'enableTracking', val ) }
                            />
                            { c.trackedUrl && (
                                <div className="khm-tracked-url-display">
                                    <strong>{ __( 'Tracked URL:', 'khm-membership' ) }</strong>
                                    <code>{ c.trackedUrl }</code>
                                </div>
                            ) }
                        </div>
                    ) ) }
                    <Button
                        variant="secondary"
                        icon={ plus }
                        onClick={ addCitation }
                    >
                        { __( 'Add Citation', 'khm-membership' ) }
                    </Button>
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

                <PanelBody title={ __( 'Entity Resolution', 'khm-membership' ) } initialOpen={ false }>
                    { normalizedEntities.length === 0 && (
                        <p className="components-base-control__help">
                            { __( 'Add entities to resolve them against Wikidata.', 'khm-membership' ) }
                        </p>
                    ) }
                    { normalizedEntities.map( ( entity, index ) => {
                        const qid = getEntityQid( entity );
                        return (
                            <div key={ `entity-res-${ index }` } className="khm-entity-resolution">
                                <div className="khm-entity-resolution__row">
                                    <span className="khm-entity-resolution__name">{ decodeHtmlEntities( entity.name ) }</span>
                                    { qid ? (
                                        <span className="khm-entity-resolution__badge khm-entity-resolution__badge--resolved">
                                            { __( 'Resolved', 'khm-membership' ) } { qid }
                                        </span>
                                    ) : (
                                        <span className="khm-entity-resolution__badge khm-entity-resolution__badge--unresolved">
                                            { __( 'Unresolved', 'khm-membership' ) }
                                        </span>
                                    ) }
                                </div>
                                <Button
                                    variant="secondary"
                                    onClick={ () => openResolver( index ) }
                                    disabled={ resolverLoading }
                                >
                                    { __( 'Resolve', 'khm-membership' ) }
                                </Button>
                            </div>
                        );
                    } ) }
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
                    { evidence?.tier && (
                        <span className={ `khm-answer-card-editor__badge khm-answer-card-editor__badge--tier khm-tier-${ evidence.tier }` }>
                            { evidence.tier === 'tier1' ? 'Tier-1' : evidence.tier === 'tier2' ? 'Tier-2' : 'Tier-3' }
                        </span>
                    ) }
                    { answerCardId && (
                        <span className="khm-answer-card-editor__badge khm-answer-card-editor__badge--id">
                            { answerCardId.slice( 0, 8 ) }
                        </span>
                    ) }
                    { requiresReview && (
                        <Tooltip text={ __( 'Low confidence — will not appear in public schema', 'khm-membership' ) }>
                            <span className="khm-answer-card-editor__badge khm-answer-card-editor__badge--review">
                                ⚠️ { __( 'Needs Review', 'khm-membership' ) }
                            </span>
                        </Tooltip>
                    ) }
                    { ! exposeInSchema && ! requiresReview && (
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
                                <div className="khm-answer-card-editor__citation-preview">
                                    { /* Display citation as Title — Author (Year), Publisher */ }
                                    { (() => {
                                        const formatted = formatCitationDisplay( c );
                                        return (
                                            <>
                                                <span className="khm-citation-tier-indicator" data-tier={ c.tier || 'tier3' }>
                                                    { c.tier === 'tier1' ? '🏆' : c.tier === 'tier2' ? '📊' : '📰' }
                                                </span>
                                                <span className="khm-citation-text">
                                                    { formatted.link ? (
                                                        <ExternalLink href={ formatted.link }>
                                                            { formatted.title || __( 'Untitled', 'khm-membership' ) }
                                                        </ExternalLink>
                                                    ) : (
                                                        formatted.title || __( 'Untitled', 'khm-membership' )
                                                    ) }
                                                    { formatted.hasMeta && ` — ${ formatted.meta }` }
                                                </span>
                                                { c.enableTracking && (
                                                    <span className="khm-citation-tracking-badge" title={ __( 'Click tracking enabled', 'khm-membership' ) }>📈</span>
                                                ) }
                                            </>
                                        );
                                    })() }
                                </div>
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
            { resolverOpen && (
                <Modal
                    title={ __( 'Resolve Entity', 'khm-membership' ) }
                    onRequestClose={ () => setResolverOpen( false ) }
                    className="khm-entity-resolver-modal"
                >
                    { resolverLoading && <Spinner /> }
                    { resolverError && (
                        <Notice status="error" isDismissible={ false }>
                            { resolverError }
                        </Notice>
                    ) }
                    { ! resolverLoading && resolverCandidates.length === 0 && ! resolverError && (
                        <p>{ __( 'No candidates found.', 'khm-membership' ) }</p>
                    ) }
                    { resolverCandidates.map( ( candidate ) => (
                        <div key={ candidate.qid } className="khm-entity-resolver-candidate">
                            <div className="khm-entity-resolver-candidate__meta">
                                <strong>{ candidate.label }</strong>
                                <span>{ candidate.qid }</span>
                            </div>
                            { candidate.description && <p>{ candidate.description }</p> }
                            <div className="khm-entity-resolver-candidate__actions">
                                <Button
                                    variant="primary"
                                    onClick={ () => resolveEntity( candidate, '' ) }
                                    disabled={ resolverLoading }
                                >
                                    { __( 'Resolve + Anchor', 'khm-membership' ) }
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={ () => resolveEntity( candidate, 'about' ) }
                                    disabled={ resolverLoading }
                                >
                                    { __( 'Resolve + Add to Page', 'khm-membership' ) }
                                </Button>
                            </div>
                        </div>
                    ) ) }
                </Modal>
            ) }
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
