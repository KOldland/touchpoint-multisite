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
import { Fragment, useState, useCallback, useEffect, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { trash, plus, warning, check } from '@wordpress/icons';
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
 * Detect truncated or placeholder URLs.
 */
const isTruncatedUrl = ( url ) => {
    if ( ! url ) return true;
    const lower = url.toLowerCase();
    return lower.includes( '...' ) || lower.includes( 'example.com' );
};

/**
 * Extract footnote references from ACF footnotes block content.
 */
const extractFootnoteReferences = ( content ) => {
    if ( ! content ) return [];
    const match = content.match( /<!--\s*wp:acf\/footnotes\s+([\s\S]*?)\s*\/-->/ );
    if ( ! match || ! match[1] ) {
        return [];
    }

    try {
        const parsed = JSON.parse( match[1] );
        const data = parsed?.data || {};
        const refs = [];
        Object.keys( data ).forEach( ( key ) => {
            const textMatch = key.match( /^footnotes_(\d+)_reference_text$/ );
            if ( textMatch ) {
                const idx = textMatch[1];
                const linkKey = `footnotes_${ idx }_reference_link`;
                if ( data[ key ] && data[ linkKey ] ) {
                    refs.push( {
                        text: data[ key ],
                        link: data[ linkKey ],
                    } );
                }
            }
        } );
        return refs;
    } catch ( error ) {
        return [];
    }
};

const normalizeReferenceText = ( text ) =>
    ( text || '' ).toLowerCase().replace( /[^a-z0-9\s]/g, ' ' ).replace( /\s+/g, ' ' ).trim();

const resolveCitationUrlFromRefs = ( citation, refs ) => {
    const title = normalizeReferenceText( citation?.title || '' );
    const publisher = normalizeReferenceText( citation?.publisher || '' );
    if ( ! refs.length ) return '';

    let best = { score: 0, link: '' };
    refs.forEach( ( ref ) => {
        const refText = normalizeReferenceText( ref.text );
        let score = 0;
        if ( title && refText.includes( title ) ) score += 3;
        if ( publisher && refText.includes( publisher ) ) score += 2;
        if ( title ) {
            const titleParts = title.split( ' ' ).filter( ( part ) => part.length > 3 );
            const hits = titleParts.filter( ( part ) => refText.includes( part ) ).length;
            score += Math.min( hits, 3 );
        }
        if ( score > best.score ) {
            best = { score, link: ref.link };
        }
    } );

    return best.score > 0 ? best.link : '';
};

/**
 * Convert a display date to ISO based on the configured format.
 */
const parseDateToIso = ( value, format ) => {
    if ( ! value ) return '';
    const trimmed = value.trim();
    if ( /^\d{4}-\d{2}-\d{2}$/.test( trimmed ) ) {
        return trimmed;
    }

    const valueSep = trimmed.includes( '/' ) ? '/' : ( trimmed.includes( '-' ) ? '-' : '' );
    const formatSep = format.includes( '/' ) ? '/' : ( format.includes( '-' ) ? '-' : '' );
    if ( ! valueSep || ! formatSep ) {
        return '';
    }

    const valueParts = trimmed.split( valueSep );
    const formatParts = format.split( formatSep );
    if ( valueParts.length !== formatParts.length ) {
        return '';
    }

    const map = {};
    formatParts.forEach( ( token, idx ) => {
        map[ token ] = valueParts[ idx ];
    } );

    const year = map.Y || map.y;
    const month = map.m;
    const day = map.d;
    if ( ! year || ! month || ! day ) {
        return '';
    }

    const iso = `${ String( year ).padStart( 4, '0' ) }-${ String( month ).padStart( 2, '0' ) }-${ String( day ).padStart( 2, '0' ) }`;
    return /^\d{4}-\d{2}-\d{2}$/.test( iso ) ? iso : '';
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

const buildSponsorOptions = ( docs ) => {
    const map = new Map();
    ( docs || [] ).forEach( ( doc ) => {
        const id = Number( doc.sponsor_id || 0 );
        if ( ! id ) {
            return;
        }
        const metaName = doc?.sponsor_name || doc?.meta?.sponsor_name;
        const name = metaName || `Sponsor ${ id }`;
        if ( ! map.has( id ) ) {
            map.set( id, name );
        }
    } );
    return [ { label: __( 'Select sponsor', 'khm-membership' ), value: 0 } ].concat(
        Array.from( map.entries() ).map( ( [ value, label ] ) => ( { label, value } ) )
    );
};

const reorderCitationsSponsorFirst = ( list, id ) => {
    if ( ! Array.isArray( list ) || ! id ) {
        return list;
    }
    const sponsor = [];
    const publicCits = [];
    list.forEach( ( citation ) => {
        if ( citation && Number( citation.sponsor_id || 0 ) === Number( id ) ) {
            sponsor.push( citation );
        } else {
            publicCits.push( citation );
        }
    } );
    return sponsor.concat( publicCits );
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
        exposeInSchema,
        requiresReview,
        reviewJustification,
        generationOverride,
        generationOverrideNote,
        sponsorToggle,
        sponsorId,
        sponsorName,
        sponsorUrl,
        sponsorBoost,
        sponsorRequiresApproval,
        sponsorApproved,
        sponsorJustification,
        sponsorDocIds,
        citationOrdering,
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
    const [ topicDefaultsLoading, setTopicDefaultsLoading ] = useState( false );
    const [ topicDefaultsError, setTopicDefaultsError ] = useState( '' );
    const [ dateFormat, setDateFormat ] = useState( 'd/m/Y' );
    const [ siteKeywordsSource, setSiteKeywordsSource ] = useState( '' );
    const [ autoResolveEnabled, setAutoResolveEnabled ] = useState( false );
    const [ autoResolveThreshold, setAutoResolveThreshold ] = useState( 0.85 );
    const [ didSyncServerSummary, setDidSyncServerSummary ] = useState( false );
    const [ showAllReasons, setShowAllReasons ] = useState( false );
    const [ showDiagnostics, setShowDiagnostics ] = useState( false );
    const [ diagnosticsAudit, setDiagnosticsAudit ] = useState( null );
    const [ regenJobId, setRegenJobId ] = useState( '' );
    const [ regenStatus, setRegenStatus ] = useState( '' );
    const [ entitySuggestions, setEntitySuggestions ] = useState( {} );
    const [ sponsorDocs, setSponsorDocs ] = useState( [] );
    const [ sponsorLoading, setSponsorLoading ] = useState( false );
    const [ sponsorError, setSponsorError ] = useState( '' );
    const [ sponsorSaving, setSponsorSaving ] = useState( false );

    const { postId, postTitle, postContent } = useSelect( ( select ) => {
        try {
            return {
                postId: select( 'core/editor' ).getCurrentPostId(),
                postTitle: select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '',
                postContent: select( 'core/editor' ).getEditedPostAttribute( 'content' ) || '',
            };
        } catch ( error ) {
            return {
                postId: null,
                postTitle: '',
                postContent: '',
            };
        }
    }, [] );

    const authors = useSelect( ( select ) => {
        try {
            return select( 'core' ).getUsers( { per_page: 100, who: 'authors' } ) || [];
        } catch ( error ) {
            return [];
        }
    }, [] );

    const currentUser = useSelect( ( select ) => {
        try {
            return select( 'core' ).getCurrentUser();
        } catch ( error ) {
            return null;
        }
    }, [] );
    const canApproveSponsor = !! currentUser?.capabilities?.publish_posts;

    // Generate answerCardId if not present
    useEffect( () => {
        if ( ! answerCardId ) {
            setAttributes( { answerCardId: generateClientId() } );
        }
    }, [ answerCardId, setAttributes ] );

    // Prefill Topic Discussed At defaults on mount
    useEffect( () => {
        if ( ! postId || topicDefaultsLoading ) {
            return;
        }

        setTopicDefaultsLoading( true );
        apiFetch( {
            path: `/khm-geo/v1/topic-defaults?post_id=${ postId }`,
            method: 'GET',
        } ).then( ( result ) => {
            const defaults = result?.topic || {};
            const current = topicDiscussedAt || {};
            const nextTopic = { ...current };

            if ( ! nextTopic.title && defaults.title ) nextTopic.title = defaults.title;
            if ( ! nextTopic.url && defaults.url ) nextTopic.url = defaults.url;

            const currentAuthorName = nextTopic.author_name || nextTopic.author || '';
            const currentAuthorId = nextTopic.author_id || 0;
            const shouldUseLeadAuthor = !! ( defaults.author_name && (
                ! currentAuthorName ||
                ( defaults.wp_author_name && currentAuthorName === defaults.wp_author_name ) ||
                ( defaults.wp_author_id && currentAuthorId === defaults.wp_author_id )
            ) );
            if ( shouldUseLeadAuthor ) {
                nextTopic.author_name = defaults.author_name;
                nextTopic.author = defaults.author_name;
            }
            if ( shouldUseLeadAuthor && defaults.author_id ) {
                nextTopic.author_id = defaults.author_id;
            }
            if ( ! nextTopic.publisher && defaults.publisher ) nextTopic.publisher = defaults.publisher;
            if ( ! nextTopic.date && defaults.date ) nextTopic.date = defaults.date;

            setAttributes( { topicDiscussedAt: nextTopic } );
            setAttributes( { siteKeywords: result?.site_keywords || [] } );
            setAttributes( { publicSummaryLabel: result?.public_summary_label || '' } );

            setDateFormat( result?.date_format || 'd/m/Y' );
            setSiteKeywordsSource( result?.site_keywords_source || '' );
            setAutoResolveEnabled( !! result?.auto_resolve );
            setAutoResolveThreshold( typeof result?.auto_resolve_threshold === 'number'
                ? result.auto_resolve_threshold
                : 0.85 );
            setTopicDefaultsLoading( false );
        } ).catch( ( error ) => {
            setTopicDefaultsError( error.message || __( 'Failed to load topic defaults', 'khm-membership' ) );
            setTopicDefaultsLoading( false );
        } );
    }, [ postId ] );

    // Load sponsor docs for selector and preview
    useEffect( () => {
        if ( sponsorLoading ) {
            return;
        }
        setSponsorLoading( true );
        apiFetch( { path: '/khm-geo/v1/sponsor-docs', method: 'GET' } )
            .then( ( result ) => {
                setSponsorDocs( Array.isArray( result ) ? result : [] );
                setSponsorLoading( false );
            } )
            .catch( ( error ) => {
                setSponsorError( error.message || __( 'Failed to load sponsor docs', 'khm-membership' ) );
                setSponsorLoading( false );
            } );
    }, [] );

    // Sync server-generated preferred summary into block attributes once.
    useEffect( () => {
        if ( ! postId || ! answerCardId || didSyncServerSummary ) {
            return;
        }

        apiFetch( {
            path: `/khm-geo/v1/tracker/posts/${ postId }/answercards`,
            method: 'GET',
        } ).then( ( result ) => {
            const cards = result?.cards || [];
            const serverCard = cards.find( ( card ) => card?.answer_card_id === answerCardId );
            if ( serverCard?.preferred_summary && ! preferredSummary ) {
                setAttributes( { preferredSummary: serverCard.preferred_summary } );
            }
            setDidSyncServerSummary( true );
        } ).catch( () => {
            setDidSyncServerSummary( true );
        } );
    }, [ postId, answerCardId, didSyncServerSummary, preferredSummary, setAttributes ] );

    // Normalize citations: default tracking + resolve truncated URLs from footnotes.
    useEffect( () => {
        if ( ! Array.isArray( citations ) || citations.length === 0 ) {
            return;
        }
        const refs = extractFootnoteReferences( postContent );
        let updated = false;
        const normalized = citations.map( ( citation ) => {
            if ( ! citation || typeof citation !== 'object' ) {
                return citation;
            }
            let next = { ...citation };
            if ( next.enableTracking === undefined && next.enable_tracking !== undefined ) {
                next.enableTracking = !! next.enable_tracking;
                updated = true;
            } else if ( next.enableTracking === undefined && next.enable_tracking === undefined ) {
                next.enableTracking = true;
                updated = true;
            }

            if ( isTruncatedUrl( next.url ) && refs.length > 0 ) {
                const resolvedUrl = resolveCitationUrlFromRefs( next, refs );
                if ( resolvedUrl ) {
                    next.url = resolvedUrl;
                    updated = true;
                }
            }
            return next;
        } );

        if ( updated ) {
            setAttributes( { citations: normalized } );
        }
    }, [ citations, postContent, setAttributes ] );

    const openPanelByTitle = ( title ) => {
        const buttons = document.querySelectorAll( '.components-panel__body-toggle' );
        buttons.forEach( ( btn ) => {
            if ( btn.textContent && btn.textContent.trim() === title ) {
                const expanded = btn.getAttribute( 'aria-expanded' );
                if ( expanded === 'false' ) {
                    btn.click();
                }
            }
        } );
    };

    const openCitationEditor = ( citationIndex ) => {
        if ( citationIndex === null || citationIndex === undefined ) return;
        openPanelByTitle( __( 'Citation Details', 'khm-membership' ) );
        const input = document.getElementById( `citation-author-${ citationIndex }` )
            || document.getElementById( `citation-year-${ citationIndex }` )
            || document.getElementById( 'evidence-source-passage' );
        if ( input ) {
            input.focus();
            input.scrollIntoView( { behavior: 'smooth', block: 'center' } );
        }
    };

    const openEntityResolver = ( entityName ) => {
        if ( ! entityName ) return;
        const index = normalizedEntities.findIndex( ( entity ) => entity?.name === entityName );
        if ( index >= 0 ) {
            openResolver( index );
        }
    };

    const regenerate = async () => {
        if ( ! postId ) return;
        setRegenStatus( 'queued' );
        try {
            const result = await apiFetch( {
                path: '/khm-geo/v1/enqueue',
                method: 'POST',
                data: {
                    post_id: postId,
                    answer_card_id: answerCardId,
                    force: true,
                },
            } );
            if ( result?.job_id ) {
                setRegenJobId( result.job_id );
                setRegenStatus( result.status || 'processing' );
                const interval = setInterval( async () => {
                    try {
                        const job = await apiFetch( {
                            path: `/khm-geo/v1/job/${ result.job_id }?post_id=${ postId }`,
                            method: 'GET',
                        } );
                        if ( job?.status && job.status !== 'queued' && job.status !== 'processing' ) {
                            clearInterval( interval );
                            setRegenStatus( job.status );
                            fetchSavedScoreDetails();
                            setDidSyncServerSummary( false );
                        }
                    } catch ( error ) {
                        clearInterval( interval );
                        setRegenStatus( 'error' );
                    }
                }, 1500 );
                setTimeout( () => clearInterval( interval ), 15000 );
            }
        } catch ( error ) {
            setRegenStatus( 'error' );
        }
    };

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

    const recomputePersistedScore = useCallback( async () => {
        if ( ! postId ) {
            return;
        }

        setIsScoring( true );
        setScoreError( null );
        setScoreMessage( '' );

        try {
            await apiFetch( {
                path: `/khm-geo/v1/posts/${ postId }/score/recompute`,
                method: 'POST',
            } );
            await fetchSavedScoreDetails();
        } catch ( error ) {
            setScoreError( error.message || __( 'Failed to recompute score', 'khm-membership' ) );
        } finally {
            setIsScoring( false );
        }
    }, [ postId, fetchSavedScoreDetails ] );

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
            enableTracking: true,
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

    const topicAuthorName = topicDiscussedAt?.author_name || topicDiscussedAt?.author || '';
    const topicIsComplete = !! (
        ( topicDiscussedAt?.title || '' ).trim() &&
        ( topicDiscussedAt?.url || '' ).trim() &&
        ( topicAuthorName || '' ).trim() &&
        ( topicDiscussedAt?.publisher || '' ).trim() &&
        ( topicDiscussedAt?.date || '' ).trim()
    );

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
        if ( e.length >= 5 ) {
            return;
        }
        e.push( { name: '', sameAs: '', resolvedBy: '', resolvedConfidence: null, resolvedAt: '', resolvedMethod: '' } );
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
            .slice( 0, 5 )
            .map( ( name ) => ( { name, sameAs: '', resolvedBy: '', resolvedConfidence: null, resolvedAt: '', resolvedMethod: '' } ) );
        setAttributes( { entities: arr } );
    };

    const entitiesAsString = ( entities || [] )
        .map( ( e ) => ( typeof e === 'string' ? e : e.name ) )
        .join( ', ' );

    const normalizeEntity = ( entity ) => {
        if ( typeof entity === 'string' ) {
            return {
                name: entity,
                sameAs: '',
                resolvedBy: '',
                resolvedConfidence: null,
                resolvedAt: '',
                resolvedMethod: '',
            };
        }
        return {
            ...entity,
            sameAs: entity?.sameAs || entity?.same_as || '',
            resolvedBy: entity?.resolvedBy || entity?.resolved_by || '',
            resolvedConfidence: entity?.resolvedConfidence ?? entity?.resolved_confidence ?? null,
            resolvedAt: entity?.resolvedAt || entity?.resolved_at || '',
            resolvedMethod: entity?.resolvedMethod || entity?.resolved_method || '',
        };
    };

    const normalizedEntities = ( entities || [] ).map( normalizeEntity );

    const getEntityQid = ( entity ) => {
        const sameAs = entity?.sameAs || entity?.same_as || '';
        if ( ! sameAs ) return '';
        const parts = sameAs.split( '/' );
        return parts[ parts.length - 1 ] || '';
    };

    const fetchEntitySuggestions = ( index ) => {
        const entityName = normalizedEntities[ index ]?.name || '';
        if ( ! entityName ) {
            return Promise.resolve( [] );
        }
        if ( entitySuggestions[ entityName ]?.status === 'ready' ) {
            return Promise.resolve( entitySuggestions[ entityName ]?.candidates || [] );
        }

        setEntitySuggestions( ( prev ) => ( {
            ...prev,
            [ entityName ]: { status: 'loading', candidates: [] },
        } ) );

        return apiFetch( {
            path: `/khm-geo/v1/entity/suggest?term=${ encodeURIComponent( entityName ) }&context=${ encodeURIComponent( question || postTitle || '' ) }`,
            method: 'GET',
        } ).then( ( result ) => {
            const candidates = Array.isArray( result ) ? result : ( result?.candidates || [] );
            setEntitySuggestions( ( prev ) => ( {
                ...prev,
                [ entityName ]: { status: 'ready', candidates },
            } ) );
            return candidates;
        } );
    };

    const resolveEntityAtIndex = ( index, candidate, resolvedBy = 'editor' ) => {
        const entity = normalizedEntities[ index ];
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
                answer_card_id: answerCardId,
                entity_name: entity.name,
                qid: candidate.qid,
                label: candidate.label,
                provider: 'wikidata',
                page_role: '',
                resolved_by: resolvedBy,
                resolved_confidence: candidate.score ?? null,
                resolved_method: 'wikidata',
            },
        } ).then( ( result ) => {
            const updated = [ ...normalizedEntities ];
            updated[ index ] = {
                ...updated[ index ],
                sameAs: result?.same_as?.url || `https://www.wikidata.org/wiki/${ candidate.qid }`,
                resolvedBy,
                resolvedConfidence: candidate.score ?? null,
                resolvedAt: result?.resolved_at || new Date().toISOString(),
                resolvedMethod: 'wikidata',
            };
            setAttributes( { entities: updated } );

            setResolverOpen( false );
            setResolverLoading( false );
        } ).catch( ( error ) => {
            setResolverError( error.message || __( 'Failed to resolve entity', 'khm-membership' ) );
            setResolverLoading( false );
        } );
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
        fetchEntitySuggestions( index ).then( ( candidates ) => {
            setResolverCandidates( candidates );
            setResolverLoading( false );

            if ( autoResolveEnabled && candidates.length > 0 ) {
                const top = candidates[ 0 ];
                const entity = normalizedEntities[ index ];
                if ( top?.score !== undefined && top.score >= autoResolveThreshold && entity && ! entity.sameAs ) {
                    resolveEntityAtIndex( index, top, 'ai' );
                }
            }
        } ).catch( ( error ) => {
            setResolverError( error.message || __( 'Failed to load candidates', 'khm-membership' ) );
            setResolverLoading( false );
        } );
    };

    const resolveEntity = ( candidate ) => {
        if ( resolverIndex === null || resolverIndex === undefined ) {
            return;
        }
        resolveEntityAtIndex( resolverIndex, candidate, 'editor' );
    };

    useEffect( () => {
        if ( normalizedEntities.length === 0 ) {
            return;
        }
        const timeout = setTimeout( () => {
            normalizedEntities.forEach( ( entity, index ) => {
                if ( ! entity?.name || entity?.sameAs ) {
                    return;
                }
                if ( entitySuggestions[ entity.name ]?.status ) {
                    return;
                }
                fetchEntitySuggestions( index ).then( ( candidates ) => {
                    if ( autoResolveEnabled && candidates?.length ) {
                        const top = candidates[ 0 ];
                        if ( top?.score !== undefined && top.score >= autoResolveThreshold && ! entity.sameAs ) {
                            resolveEntityAtIndex( index, top, 'ai' );
                        }
                    }
                } ).catch( () => {} );
            } );
        }, 500 );

        return () => clearTimeout( timeout );
    }, [ normalizedEntities, autoResolveEnabled, autoResolveThreshold, entitySuggestions ] );

    const unresolveEntity = ( index ) => {
        const entity = normalizedEntities[ index ];
        if ( ! entity?.name ) {
            return;
        }

        setResolverLoading( true );
        setResolverError( '' );

        apiFetch( {
            path: '/khm-geo/v1/entity/unresolve',
            method: 'POST',
            data: {
                post_id: postId,
                answer_card_id: answerCardId,
                entity_name: entity.name,
                qid: getEntityQid( entity ),
            },
        } ).then( () => {
            const updated = [ ...normalizedEntities ];
            updated[ index ] = {
                ...updated[ index ],
                sameAs: '',
                resolvedBy: '',
                resolvedConfidence: null,
                resolvedAt: '',
                resolvedMethod: '',
            };
            setAttributes( { entities: updated } );
            setResolverLoading( false );
        } ).catch( ( error ) => {
            setResolverError( error.message || __( 'Failed to unresolve entity', 'khm-membership' ) );
            setResolverLoading( false );
        } );
    };

    /**
     * Sponsor-related functions and constants
     */
    const sponsorOptions = buildSponsorOptions( sponsorDocs );
    const sponsorPreview = useMemo( () => {
        return ( sponsorDocs || [] )
            .filter( ( doc ) => Number( doc.sponsor_id || 0 ) === Number( sponsorId || 0 ) && doc.approved )
            .slice( 0, 3 );
    }, [ sponsorDocs, sponsorId ] );

    const persistSponsorToggle = ( enable, overrides = {} ) => {
        if ( ! postId || ! answerCardId ) {
            return;
        }

        setSponsorSaving( true );
        apiFetch( {
            path: `/khm-geo/v1/cards/${ postId }/sponsor-toggle`,
            method: 'POST',
            data: {
                enable,
                sponsor_id: overrides.sponsorId ?? sponsorId ?? 0,
                sponsor_name: overrides.sponsorName ?? sponsorName ?? '',
                sponsor_url: overrides.sponsorUrl ?? sponsorUrl ?? '',
                sponsor_doc_ids: overrides.sponsorDocIds ?? sponsorDocIds ?? [],
                answer_card_id: answerCardId,
                sponsor_boost: overrides.sponsorBoost ?? sponsorBoost ?? 0,
                approval_required: overrides.sponsorRequiresApproval ?? sponsorRequiresApproval ?? true,
                approved: overrides.sponsorApproved ?? sponsorApproved ?? false,
                justification: overrides.sponsorJustification ?? sponsorJustification ?? '',
            },
        } ).then( () => {
            setSponsorSaving( false );
        } ).catch( ( error ) => {
            setSponsorError( error.message || __( 'Failed to save sponsor settings', 'khm-membership' ) );
            setSponsorSaving( false );
        } );
    };

    const approveSponsor = () => {
        if ( ! postId || ! answerCardId ) {
            return;
        }
        setSponsorSaving( true );
        apiFetch( {
            path: `/khm-geo/v1/cards/${ postId }/sponsor-approve`,
            method: 'POST',
            data: {
                answer_card_id: answerCardId,
                justification: sponsorJustification || '',
            },
        } ).then( () => {
            setAttributes( { sponsorApproved: true } );
            setSponsorSaving( false );
        } ).catch( ( error ) => {
            setSponsorError( error.message || __( 'Failed to approve sponsor', 'khm-membership' ) );
            setSponsorSaving( false );
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

                <PanelBody title={ __( 'Sponsor', 'khm-membership' ) } initialOpen={ false }>
                    { sponsorError && (
                        <Notice status="error" isDismissible={ false }>
                            { sponsorError }
                        </Notice>
                    ) }
                    <PanelRow>
                        <ToggleControl
                            label={ __( 'Mark as Sponsored', 'khm-membership' ) }
                            checked={ !! sponsorToggle }
                            onChange={ ( val ) => {
                                const nextDocIds = sponsorPreview.map( ( doc ) => doc.id );
                                setAttributes( {
                                    sponsorToggle: val,
                                    citationOrdering: val ? 'sponsor_first' : '',
                                    sponsorDocIds: val ? nextDocIds : [],
                                } );
                                if ( val && sponsorId ) {
                                    setAttributes( { citations: reorderCitationsSponsorFirst( citations || [], sponsorId ) } );
                                }
                                persistSponsorToggle( val, { sponsorId: sponsorId || 0, sponsorDocIds: nextDocIds } );
                            } }
                        />
                    </PanelRow>
                    <SelectControl
                        label={ __( 'Sponsor', 'khm-membership' ) }
                        value={ sponsorId || 0 }
                        options={ sponsorOptions }
                        onChange={ ( value ) => {
                            const nextId = Number( value || 0 );
                            const match = sponsorDocs.find( ( doc ) => Number( doc.sponsor_id || 0 ) === nextId );
                            const nextDocIds = ( sponsorDocs || [] )
                                .filter( ( doc ) => Number( doc.sponsor_id || 0 ) === nextId && doc.approved )
                                .slice( 0, 3 )
                                .map( ( doc ) => doc.id );
                            setAttributes( {
                                sponsorId: nextId,
                                sponsorName: match?.sponsor_name || match?.meta?.sponsor_name || sponsorName || '',
                                sponsorDocIds: nextDocIds,
                            } );
                            if ( sponsorToggle && nextId ) {
                                setAttributes( { citations: reorderCitationsSponsorFirst( citations || [], nextId ), citationOrdering: 'sponsor_first' } );
                                persistSponsorToggle( true, { sponsorId: nextId, sponsorDocIds: nextDocIds } );
                            }
                        } }
                    />
                    <TextControl
                        label={ __( 'Sponsor name', 'khm-membership' ) }
                        value={ sponsorName || '' }
                        onChange={ ( value ) => setAttributes( { sponsorName: value } ) }
                    />
                    <TextControl
                        label={ __( 'Sponsor URL', 'khm-membership' ) }
                        value={ sponsorUrl || '' }
                        onChange={ ( value ) => setAttributes( { sponsorUrl: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Require approval before schema', 'khm-membership' ) }
                        checked={ sponsorRequiresApproval !== false }
                        onChange={ ( value ) => {
                            setAttributes( { sponsorRequiresApproval: value } );
                            if ( sponsorToggle ) {
                                persistSponsorToggle( true, { sponsorRequiresApproval: value } );
                            }
                        } }
                    />
                    <ToggleControl
                        label={ __( 'Sponsor approved', 'khm-membership' ) }
                        checked={ !! sponsorApproved }
                        onChange={ ( value ) => {
                            setAttributes( { sponsorApproved: value } );
                            if ( sponsorToggle ) {
                                persistSponsorToggle( true, { sponsorApproved: value } );
                            }
                        } }
                    />
                    <TextControl
                        label={ __( 'Sponsor boost (0.00–0.10)', 'khm-membership' ) }
                        type="number"
                        min="0"
                        max="0.1"
                        step="0.01"
                        value={ sponsorBoost ?? 0 }
                        onChange={ ( value ) => {
                            const numeric = Math.max( 0, Math.min( 0.1, parseFloat( value ) || 0 ) );
                            setAttributes( { sponsorBoost: numeric } );
                            if ( sponsorToggle ) {
                                persistSponsorToggle( true, { sponsorBoost: numeric } );
                            }
                        } }
                    />
                    <TextareaControl
                        label={ __( 'Justification', 'khm-membership' ) }
                        value={ sponsorJustification || '' }
                        onChange={ ( value ) => {
                            setAttributes( { sponsorJustification: value } );
                            if ( sponsorToggle ) {
                                persistSponsorToggle( true, { sponsorJustification: value } );
                            }
                        } }
                        rows={ 2 }
                    />
                    { canApproveSponsor && sponsorRequiresApproval && sponsorToggle && ! sponsorApproved && (
                        <Button
                            variant="primary"
                            onClick={ approveSponsor }
                            disabled={ sponsorSaving }
                        >
                            { __( 'Approve sponsor for schema', 'khm-membership' ) }
                        </Button>
                    ) }
                    { sponsorLoading && <Spinner /> }
                    { sponsorPreview.length > 0 && (
                        <div className="khm-sponsor-preview">
                            <strong>{ __( 'Sponsor documents', 'khm-membership' ) }</strong>
                            <ul>
                                { sponsorPreview.map( ( doc ) => (
                                    <li key={ doc.id }>
                                        { doc.title }
                                    </li>
                                ) ) }
                            </ul>
                        </div>
                    ) }
                    { sponsorSaving && (
                        <p className="components-base-control__help">
                            { __( 'Saving sponsor settings…', 'khm-membership' ) }
                        </p>
                    ) }
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
                    <div className="khm-reasons-header">
                        <strong>{ __( 'Confidence reasons', 'khm-membership' ) }</strong>
                        { scoreDetails?.generated_at && (
                            <span className="khm-reasons-header__meta">
                                { __( 'Last run:', 'khm-membership' ) } { scoreDetails.generated_at }
                            </span>
                        ) }
                        { (() => {
                            const reasons = scoreDetails?.reasons || [];
                            const issues = reasons.filter( ( reason ) => ( reason.polarity || 'issue' ) === 'issue' );
                            const supports = reasons.filter( ( reason ) => reason.polarity === 'support' );
                            const confidenceScore = scoreDetails?.scores?.evidence_confidence ?? scoreDetails?.total_score;
                            const confidencePercent = Number.isFinite( confidenceScore )
                                ? `${ Math.round( confidenceScore * 100 ) }%`
                                : __( 'n/a', 'khm-membership' );
                            return (
                                <span className="khm-reasons-header__meta">
                                    { __( 'Score:', 'khm-membership' ) } { confidencePercent } • { supports.length } { __( 'supports', 'khm-membership' ) } · { issues.length } { __( 'issues', 'khm-membership' ) }
                                </span>
                            );
                        })() }
                        <Button variant="secondary" onClick={ recomputePersistedScore } disabled={ isScoring }>
                            { __( 'Recompute score', 'khm-membership' ) }
                        </Button>
                    </div>
                    { (() => {
                        const reasons = scoreDetails?.reasons || [];
                        const issues = reasons.filter( ( reason ) => ( reason.polarity || 'issue' ) === 'issue' );
                        const supports = reasons.filter( ( reason ) => reason.polarity === 'support' );
                        return (
                            <>
                                { issues.length === 0 && supports.length === 0 && (
                                    <p className="components-base-control__help">
                                        { __( 'No confidence issues detected.', 'khm-membership' ) }
                                    </p>
                                ) }
                                { issues.length === 0 && supports.length > 0 && (
                                    <p className="components-base-control__help">
                                        { __( 'No confidence issues detected.', 'khm-membership' ) }
                                    </p>
                                ) }
                                { issues.length > 0 && (
                                    <>
                                        { issues
                                            .slice()
                                            .sort( ( a, b ) => {
                                                const order = { high: 3, medium: 2, low: 1, info: 0 };
                                                return ( order[ b.severity ] || 0 ) - ( order[ a.severity ] || 0 );
                                            } )
                                            .slice( 0, showAllReasons ? undefined : 3 )
                                            .map( ( reason, idx ) => (
                                                <div key={ `reason-${ idx }` } className="khm-reason-item">
                                                    <div className="khm-reason-item__header">
                                                        <span>{ reason.label }</span>
                                                        { reason.severity && (
                                                            <span className={ `khm-reason-badge khm-reason-badge--${ reason.severity }` }>
                                                                { reason.severity }
                                                            </span>
                                                        ) }
                                                    </div>
                                                    { reason.component && (
                                                        <div className="khm-reason-item__meta">
                                                            { reason.component }
                                                        </div>
                                                    ) }
                                                    { reason.detail && (
                                                        <div className="khm-reason-item__detail">
                                                            { reason.detail }
                                                        </div>
                                                    ) }
                                                    { reason.suggestion && (
                                                        <div className="khm-reason-item__suggestion">
                                                            { reason.suggestion }
                                                        </div>
                                                    ) }
                                                    <div className="khm-reason-item__actions">
                                                        { Number.isInteger( reason.citation_index ) && (
                                                            <Button
                                                                variant="link"
                                                                onClick={ () => openCitationEditor( reason.citation_index ) }
                                                            >
                                                                { __( 'Open citation', 'khm-membership' ) }
                                                            </Button>
                                                        ) }
                                                        { reason.entity_name && (
                                                            <Button
                                                                variant="secondary"
                                                                onClick={ () => openEntityResolver( reason.entity_name ) }
                                                            >
                                                                { __( 'Resolve entity', 'khm-membership' ) }
                                                            </Button>
                                                        ) }
                                                    </div>
                                                </div>
                                            ) ) }
                                        { issues.length > 3 && (
                                            <Button variant="link" onClick={ () => setShowAllReasons( ! showAllReasons ) }>
                                                { showAllReasons ? __( 'Show top 3', 'khm-membership' ) : __( 'Show all', 'khm-membership' ) }
                                            </Button>
                                        ) }
                                    </>
                                ) }
                                { supports.length > 0 && (
                                    <div className="khm-reason-supports">
                                        <strong>{ __( 'Supports', 'khm-membership' ) }</strong>
                                        <ul>
                                            { supports.map( ( reason, idx ) => (
                                                <li key={ reason.code || `support-${ idx }` }>
                                                    { reason.label }
                                                    { reason.detail ? ` — ${ reason.detail }` : '' }
                                                </li>
                                            ) ) }
                                        </ul>
                                    </div>
                                ) }
                                <div className="khm-reason-item__actions">
                                    <Button
                                        variant="secondary"
                                        onClick={ regenerate }
                                        disabled={ regenStatus === 'processing' || regenStatus === 'queued' }
                                    >
                                        { __( 'Regenerate', 'khm-membership' ) }
                                    </Button>
                                </div>
                                { regenJobId && (
                                    <p className="components-base-control__help">
                                        { __( 'Regeneration job:', 'khm-membership' ) } { regenJobId } { regenStatus ? `(${ regenStatus })` : '' }
                                    </p>
                                ) }
                                { currentUser?.roles?.includes( 'administrator' ) && (
                                    <Button
                                        variant="link"
                                        onClick={ async () => {
                                            setShowDiagnostics( ! showDiagnostics );
                                            if ( ! diagnosticsAudit && postId ) {
                                                const result = await apiFetch( {
                                                    path: `/khm-geo/v1/tracker/posts/${ postId }/answercards`,
                                                    method: 'GET',
                                                } );
                                                const cards = result?.cards || [];
                                                const serverCard = cards.find( ( card ) => card?.answer_card_id === answerCardId );
                                                setDiagnosticsAudit( serverCard?.audit || [] );
                                            }
                                        } }
                                    >
                                        { __( 'Show full diagnostics', 'khm-membership' ) }
                                    </Button>
                                ) }
                                { showDiagnostics && (
                                    <pre className="khm-reason-diagnostics">
                                        { JSON.stringify( { reasons: scoreDetails?.reasons || [], audit: diagnosticsAudit || [] }, null, 2 ) }
                                    </pre>
                                ) }
                            </>
                        );
                    })() }
                    <p className="components-base-control__help">
                        { __( 'Score is also calculated automatically when you save the post.', 'khm-membership' ) }
                    </p>
                </PanelBody>

                <PanelBody title={ __( 'Topic Discussed At', 'khm-membership' ) } initialOpen={ false }>
                    <div className="khm-topic-status">
                        <Icon icon={ topicIsComplete ? check : warning } className={ topicIsComplete ? 'is-complete' : 'is-incomplete' } />
                        <span>
                            { topicIsComplete
                                ? __( 'All required fields are populated.', 'khm-membership' )
                                : __( 'Missing required fields: title, URL, author, publisher, date.', 'khm-membership' ) }
                        </span>
                    </div>
                    { topicDefaultsLoading && (
                        <p className="components-base-control__help">
                            { __( 'Loading defaults...', 'khm-membership' ) }
                        </p>
                    ) }
                    { topicDefaultsError && (
                        <Notice status="warning" isDismissible={ false }>
                            { topicDefaultsError }
                        </Notice>
                    ) }
                    <TextControl
                        label={ __( 'Title', 'khm-membership' ) }
                        value={ topicDiscussedAt?.title || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'title', val ) }
                    />
                    <TextControl
                        label={ __( 'URL', 'khm-membership' ) }
                        value={ topicDiscussedAt?.url || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'url', val ) }
                    />
                    <TextControl
                        label={ __( 'Author', 'khm-membership' ) }
                        value={ topicAuthorName }
                        onChange={ ( val ) => {
                            updateTopicDiscussedAt( 'author', val );
                            updateTopicDiscussedAt( 'author_name', val );
                        } }
                    />
                    <TextControl
                        label={ __( 'Publisher', 'khm-membership' ) }
                        value={ topicDiscussedAt?.publisher || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'publisher', val ) }
                    />
                    <TextControl
                        label={ __( 'Date', 'khm-membership' ) }
                        value={ topicDiscussedAt?.date || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'date', val ) }
                        help={ dateFormat ? `${ __( 'Format:', 'khm-membership' ) } ${ dateFormat }` : '' }
                    />
                    { topicDiscussedAt?.date && (
                        <p className="components-base-control__help">
                            { __( 'ISO:', 'khm-membership' ) } { parseDateToIso( topicDiscussedAt.date, dateFormat ) || __( 'Invalid date', 'khm-membership' ) }
                        </p>
                    ) }
                    <div className="khm-topic-keywords">
                        <strong>{ __( 'Site Keywords', 'khm-membership' ) }</strong>
                        { ( siteKeywords || [] ).length > 0 ? (
                            <div className="khm-topic-keywords__chips">
                                { siteKeywords.map( ( keyword, idx ) => (
                                    <span key={ `kw-${ idx }` } className="khm-topic-keywords__chip">
                                        { keyword }
                                    </span>
                                ) ) }
                            </div>
                        ) : (
                            <p className="components-base-control__help">
                                { __( 'No site keywords found from SEO metadata.', 'khm-membership' ) }
                            </p>
                        ) }
                        { siteKeywordsSource && siteKeywordsSource !== 'none' && (
                            <p className="components-base-control__help">
                                { __( 'Source:', 'khm-membership' ) } { siteKeywordsSource }
                            </p>
                        ) }
                    </div>

                    <TextareaControl
                        label={ __( 'Additional note', 'khm-membership' ) }
                        value={ topicDiscussedAt?.note || '' }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'note', val ) }
                        placeholder={ __( 'Optional editorial note for the author authority view', 'khm-membership' ) }
                        rows={ 2 }
                        maxLength={ 280 }
                    />

                    <SelectControl
                        label={ __( 'Note author', 'khm-membership' ) }
                        value={ topicDiscussedAt?.author_id || 0 }
                        onChange={ ( val ) => updateTopicDiscussedAt( 'author_id', parseInt( val, 10 ) || 0 ) }
                        options={ [
                            { label: __( 'Select an author', 'khm-membership' ), value: 0 },
                            ...( authors || [] ).map( ( author ) => ( {
                                label: author.name || author.slug || author.id,
                                value: author.id,
                            } ) ),
                        ] }
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
                                            <TextareaControl
                                                label={ __( 'Source passage', 'khm-membership' ) }
                                                value={ evidence.source_passage || evidence.sourcePassage || '' }
                                                onChange={ ( val ) => setAttributes( { evidence: { ...evidence, source_passage: val } } ) }
                                                rows={ 3 }
                                                id="evidence-source-passage"
                                            />
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
                                type="text"
                                inputMode="url"
                                autoComplete="off"
                            />
                            <TextControl
                                label={ __( 'Author', 'khm-membership' ) }
                                value={ c.author || '' }
                                onChange={ ( val ) => updateCitation( i, 'author', val ) }
                                placeholder="e.g., Jürgen Schröder et al."
                                id={ `citation-author-${ i }` }
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
                                id={ `citation-year-${ i }` }
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

                <PanelBody title={ __( 'Card Tags', 'khm-membership' ) } initialOpen={ false }>
                    <p className="components-base-control__help">
                        { __( 'Link card tags to canonical entities to improve machine linking. Unresolved tags do not affect scoring.', 'khm-membership' ) }
                    </p>
                    { normalizedEntities.length > 0 && (
                        <div className="khm-topic-keywords__chips">
                            { normalizedEntities.map( ( entity, idx ) => (
                                <span key={ `tag-${ idx }` } className="khm-topic-keywords__chip">
                                    { decodeHtmlEntities( entity.name ) || __( 'Untitled', 'khm-membership' ) }
                                </span>
                            ) ) }
                        </div>
                    ) }
                    { ( entities || [] ).map( ( entity, i ) => (
                        <div key={ `entity-adv-${ i }` } className="khm-entity-advanced">
                            <TextControl
                                label={ __( 'Card Tag', 'khm-membership' ) }
                                value={ entity.name || '' }
                                onChange={ ( val ) => updateEntity( i, 'name', val ) }
                            />
                            <TextControl
                                label={ __( 'sameAs URL (optional)', 'khm-membership' ) }
                                value={ entity.sameAs || entity.same_as || '' }
                                onChange={ ( val ) => updateEntity( i, 'sameAs', val ) }
                                placeholder="https://www.wikidata.org/wiki/Q..."
                            />
                            { (() => {
                                const normalized = normalizeEntity( entity );
                                const suggestion = entitySuggestions[ normalized.name ]?.candidates?.[ 0 ];
                                if ( normalized.sameAs || ! suggestion ) {
                                    return null;
                                }
                                return (
                                    <div className="khm-entity-resolution__meta">
                                        { __( 'Suggested:', 'khm-membership' ) } { suggestion.label } { suggestion.qid ? `(${ suggestion.qid })` : '' }
                                        { suggestion.score !== undefined ? ` • ${ Math.round( suggestion.score * 100 ) }%` : '' }
                                        <Button
                                            variant="link"
                                            onClick={ () => resolveEntityAtIndex( i, suggestion, 'editor' ) }
                                        >
                                            { __( 'Accept', 'khm-membership' ) }
                                        </Button>
                                        <Button
                                            variant="link"
                                            onClick={ () => openResolver( i ) }
                                        >
                                            { __( 'View options', 'khm-membership' ) }
                                        </Button>
                                    </div>
                                );
                            })() }
                            <Button
                                icon={ trash }
                                isDestructive
                                onClick={ () => removeEntity( i ) }
                                label={ __( 'Remove tag', 'khm-membership' ) }
                            />
                            { (() => {
                                const normalized = normalizeEntity( entity );
                                return normalized.sameAs ? (
                                    <Tooltip text={ __( 'Confirmed', 'khm-membership' ) }>
                                        <span className="khm-entity-resolution__badge khm-entity-resolution__badge--resolved">
                                            <Icon icon={ check } />
                                        </span>
                                    </Tooltip>
                                ) : (
                                    <Tooltip text={ __( 'Confirm', 'khm-membership' ) }>
                                        <Button
                                            variant="link"
                                            icon="media-document"
                                            label={ __( 'Confirm', 'khm-membership' ) }
                                            onClick={ () => openResolver( i ) }
                                        />
                                    </Tooltip>
                                );
                            })() }
                        </div>
                    ) ) }
                    <Button
                        variant="secondary"
                        icon={ plus }
                        onClick={ addEntity }
                    >
                        { __( 'Add Card Tag', 'khm-membership' ) }
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
                        <li>{ __( 'Card tags: Key topics for discovery', 'khm-membership' ) }</li>
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
                        label={ __( 'Card Tags (Topics)', 'khm-membership' ) }
                        value={ entitiesAsString }
                        onChange={ updateEntitiesFromString }
                        placeholder={ __( 'e.g., SEO, Content Marketing, Google', 'khm-membership' ) }
                        help={ __( 'Key topics and concepts this content covers. Use Advanced to link tags to canonical entities.', 'khm-membership' ) }
                    />
                </div>
            </div>
            { resolverOpen && (
                <Modal
                    title={ __( 'Resolve Card Tag', 'khm-membership' ) }
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
                                { candidate.score !== undefined && (
                                    <span>{ __( 'Score:', 'khm-membership' ) } { Math.round( candidate.score * 100 ) }%</span>
                                ) }
                            </div>
                            { candidate.description && <p>{ candidate.description }</p> }
                            <div className="khm-entity-resolver-candidate__actions">
                                <Button
                                    variant="primary"
                                    onClick={ () => resolveEntity( candidate ) }
                                    disabled={ resolverLoading }
                                >
                                    { __( 'Accept', 'khm-membership' ) }
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={ () => setResolverOpen( false ) }
                                >
                                    { __( 'Reject', 'khm-membership' ) }
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
