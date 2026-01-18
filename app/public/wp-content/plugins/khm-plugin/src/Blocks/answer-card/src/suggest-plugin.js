/**
 * AnswerCard Suggestion Plugin
 *
 * Adds a "Suggest AnswerCards" button to the Gutenberg sidebar that opens
 * a modal to generate and insert AI-powered AnswerCard suggestions.
 *
 * @package KHM\GEO
 */

console.log('[KHM GEO] Suggest plugin JavaScript loading...');

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
    Button,
    Modal,
    Spinner,
    Notice,
    PanelBody,
    Card,
    CardHeader,
    CardBody,
    CardFooter,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    CheckboxControl,
    Flex,
    FlexItem,
    Icon,
    RangeControl,
} from '@wordpress/components';
import { useState, useCallback, useEffect } from '@wordpress/element';
import { help, check, edit, plus, warning } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

import './suggest-modal.scss';

/**
 * Decode HTML entities for display
 * @param {string} text - Text with potential HTML entities
 * @returns {string} Decoded text
 */
const decodeHtmlEntities = ( text ) => {
    if ( ! text ) return '';
    const textarea = document.createElement( 'textarea' );
    textarea.innerHTML = text;
    return textarea.value;
};

/**
 * Get confidence reason codes based on evidence data
 * @param {object} evidence - Evidence object from card
 * @param {array} citations - Citations array from card
 * @returns {object} Reasons object with primary, secondary, and actions
 */
const getConfidenceReasons = ( evidence, citations = [] ) => {
    const reasons = {
        primary: null,
        secondary: [],
        actions: [],
    };

    const tier = evidence?.tier || 'unknown';
    const confidence = parseFloat( evidence?.confidence || 0.5 );
    const hasSourcePassage = !! evidence?.source_passage;
    const hasAuthor = citations.some( c => c.author );
    const hasYear = citations.some( c => c.year );
    const hasPublisher = citations.some( c => c.publisher );

    // Determine primary reason
    if ( tier === 'tier3' || tier === 'unknown' ) {
        reasons.primary = 'Only Tier-3 or unclassified evidence detected';
        reasons.actions.push( 'Add Tier-1 citation (study with author + year)' );
    } else if ( tier === 'tier2' ) {
        reasons.primary = 'Tier-2 evidence (benchmark) — upgrade to Tier-1 for higher confidence';
        reasons.actions.push( 'Add peer-reviewed study or institutional source' );
    } else if ( confidence < 0.6 ) {
        reasons.primary = 'Low extraction confidence from source material';
    }

    // Secondary reasons
    if ( ! hasAuthor ) {
        reasons.secondary.push( 'Missing: author attribution' );
        reasons.actions.push( 'Add author name to citation' );
    }
    if ( ! hasYear ) {
        reasons.secondary.push( 'Missing: publication year' );
        reasons.actions.push( 'Add publication year to citation' );
    }
    if ( ! hasSourcePassage ) {
        reasons.secondary.push( 'No source passage attached' );
        reasons.actions.push( 'Attach source passage from article' );
    }
    if ( ! hasPublisher ) {
        reasons.secondary.push( 'Missing: publisher/institution' );
    }
    if ( citations.length === 0 ) {
        reasons.secondary.push( 'No citations present' );
        reasons.actions.push( 'Add external source citation' );
    }

    // Default primary if none set
    if ( ! reasons.primary && reasons.secondary.length > 0 ) {
        reasons.primary = 'Incomplete citation metadata';
    } else if ( ! reasons.primary ) {
        reasons.primary = 'Review evidence quality';
    }

    return reasons;
};

/**
 * Format citation for display: Title — Author (Year), Publisher
 * Returns object with separate title and meta for flexible rendering
 * @param {object} cit - Citation object
 * @returns {object} { title, meta, link, hasMeta }
 */
const formatCitationDisplay = ( cit ) => {
    const title = decodeHtmlEntities( cit.title || cit.url || 'Untitled' );
    const author = cit.author ? decodeHtmlEntities( cit.author ) : '';
    const year = cit.year ? `(${ cit.year })` : '';
    const publisher = cit.publisher ? decodeHtmlEntities( cit.publisher ) : '';

    // Build meta string: "Author (Year), Publisher" or fallbacks
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
        link: cit.url || '',
        hasMeta: !! meta,
        tier: cit.tier || '',
    };
};

/**
 * Get tier badge display
 * @param {string} tier - Evidence tier
 * @returns {object} Tier display info
 */
const getTierDisplay = ( tier ) => {
    const tiers = {
        tier1: { label: '🏆 Tier-1', desc: 'Study + Year', className: 'tier1' },
        tier2: { label: '📊 Tier-2', desc: 'Benchmark', className: 'tier2' },
        tier3: { label: '📰 Tier-3', desc: 'Trade Publication', className: 'tier3' },
    };
    return tiers[ tier ] || { label: '❓ Unknown', desc: 'Unclassified', className: 'unknown' };
};

/**
 * Confidence badge component with tooltip
 */
const ConfidenceBadge = ( { confidence, evidence, citations } ) => {
    const score = parseFloat( confidence ) * 100;
    const reasons = getConfidenceReasons( evidence, citations );
    let variant = 'low';
    if ( score >= 80 ) {
        variant = 'high';
    } else if ( score >= 60 ) {
        variant = 'medium';
    }

    const tooltipContent = [
        `${ score.toFixed( 0 ) }% confidence`,
        `Primary: ${ reasons.primary }`,
        ...reasons.secondary.slice( 0, 2 ).map( s => `• ${ s }` ),
    ].join( '\n' );

    return (
        <span 
            className={ `khm-confidence-badge khm-confidence-badge--${ variant }` }
            title={ tooltipContent }
        >
            { score.toFixed( 0 ) }% confidence
        </span>
    );
};

/**
 * Single suggestion card preview
 */
const SuggestionCard = ( { card, index, selected, onToggle, onEdit } ) => {
    const [ showSourcePassage, setShowSourcePassage ] = useState( false );
    const evidence = card.evidence || {};
    const tierDisplay = getTierDisplay( evidence.tier );
    const reasons = getConfidenceReasons( evidence, card.citations );

    return (
        <Card className={ `khm-suggestion-card ${ selected ? 'khm-suggestion-card--selected' : '' }` }>
            <CardHeader>
                <Flex>
                    <FlexItem>
                        <CheckboxControl
                            checked={ selected }
                            onChange={ onToggle }
                            __nextHasNoMarginBottom
                        />
                    </FlexItem>
                    <FlexItem isBlock>
                        <Heading level={ 4 } className="khm-suggestion-card__question">
                            { decodeHtmlEntities( card.question ) }
                        </Heading>
                    </FlexItem>
                    <FlexItem>
                        <span className={ `khm-tier-badge khm-tier-badge--${ tierDisplay.className }` } title={ tierDisplay.desc }>
                            { tierDisplay.label }
                        </span>
                    </FlexItem>
                    <FlexItem>
                        <ConfidenceBadge 
                            confidence={ evidence.confidence || card.confidence || 0.5 } 
                            evidence={ evidence }
                            citations={ card.citations }
                        />
                    </FlexItem>
                </Flex>
            </CardHeader>
            <CardBody>
                <div className="khm-suggestion-card__answer">
                    <Text>{ decodeHtmlEntities( card.concise_answer ) }</Text>
                </div>
                { card.key_points && card.key_points.length > 0 && (
                    <div className="khm-suggestion-card__key-points">
                        <Text weight="600" size="12px">{ __( 'KEY POINTS:', 'khm-membership' ) }</Text>
                        <ul>
                            { card.key_points.map( ( point, i ) => (
                                <li key={ i }>{ decodeHtmlEntities( point ) }</li>
                            ) ) }
                        </ul>
                    </div>
                ) }
                { card.citations && card.citations.length > 0 && (
                    <div className="khm-suggestion-card__citations">
                        <Text weight="600" size="12px">{ __( 'CITATIONS:', 'khm-membership' ) }</Text>
                        <ul>
                            { card.citations.map( ( citation, i ) => {
                                const formatted = formatCitationDisplay( citation );
                                return (
                                    <li key={ i } className="khm-suggestion-card__citation-item">
                                        <a 
                                            href={ formatted.link } 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            aria-label={ `Open citation: ${ formatted.title } (opens in new tab)` }
                                        >
                                            { formatted.title }
                                        </a>
                                        { formatted.hasMeta && (
                                            <span className="khm-suggestion-card__citation-meta">
                                                { ' — ' }{ formatted.meta }
                                            </span>
                                        ) }
                                    </li>
                                );
                            } ) }
                        </ul>
                    </div>
                ) }
                { evidence.source_passage && (
                    <div className="khm-suggestion-card__source-passage">
                        <Button
                            variant="link"
                            onClick={ () => setShowSourcePassage( ! showSourcePassage ) }
                            className="khm-suggestion-card__source-toggle"
                        >
                            { showSourcePassage ? __( '▼ Hide source passage', 'khm-membership' ) : __( '▶ Show source passage', 'khm-membership' ) }
                        </Button>
                        { showSourcePassage && (
                            <blockquote className="khm-suggestion-card__quote">
                                "{ decodeHtmlEntities( evidence.source_passage ) }"
                                { evidence.context_heading && (
                                    <cite>— { decodeHtmlEntities( evidence.context_heading ) }</cite>
                                ) }
                            </blockquote>
                        ) }
                    </div>
                ) }
                { card.notes && (
                    <div className="khm-suggestion-card__notes">
                        <Text size="12px" isBlock>
                            <Icon icon={ warning } size={ 14 } /> { decodeHtmlEntities( card.notes ) }
                        </Text>
                    </div>
                ) }
                { /* Confidence reasons panel */ }
                { ( evidence.confidence || card.confidence || 0.5 ) < 0.6 && (
                    <div className="khm-suggestion-card__reasons">
                        <Text size="12px" weight="600">{ __( 'Low confidence reasons:', 'khm-membership' ) }</Text>
                        <Text size="12px">{ reasons.primary }</Text>
                        { reasons.secondary.length > 0 && (
                            <ul className="khm-suggestion-card__reasons-list">
                                { reasons.secondary.slice( 0, 3 ).map( ( reason, i ) => (
                                    <li key={ i }><Text size="11px">{ reason }</Text></li>
                                ) ) }
                            </ul>
                        ) }
                        { reasons.actions.length > 0 && (
                            <Text size="11px" variant="muted">
                                { __( 'Quick fixes:', 'khm-membership' ) } { reasons.actions.slice( 0, 2 ).join( ', ' ) }
                            </Text>
                        ) }
                    </div>
                ) }
            </CardBody>
            <CardFooter>
                <Button
                    variant="tertiary"
                    icon={ edit }
                    onClick={ () => onEdit( card ) }
                    size="small"
                >
                    { __( 'Edit before insert', 'khm-membership' ) }
                </Button>
            </CardFooter>
        </Card>
    );
};

/**
 * Main suggestion modal component
 */
const SuggestAnswerCardsModal = ( { isOpen, onClose, postId, postTitle, postContent, postUrl } ) => {
    const [ suggestions, setSuggestions ] = useState( [] );
    const [ selectedCards, setSelectedCards ] = useState( [] );
    const [ isLoading, setIsLoading ] = useState( false );
    const [ error, setError ] = useState( null );
    const [ cacheStatus, setCacheStatus ] = useState( null );
    const [ maxCards, setMaxCards ] = useState( 4 );

    const { insertBlocks } = useDispatch( 'core/block-editor' );

    // Safety check for block editor availability
    const isBlockEditorAvailable = !!insertBlocks;

    /**
     * Fetch suggestions from API
     * @param {boolean} forceRefresh - If true, bypass cache and regenerate
     */
    const fetchSuggestions = useCallback( async ( forceRefresh = false ) => {
        setIsLoading( true );
        setError( null );
        setSuggestions( [] );
        setSelectedCards( [] );

        try {
            const response = await apiFetch( {
                path: '/khm-geo/v1/suggest-answercards',
                method: 'POST',
                data: {
                    post_id: postId,
                    title: postTitle,
                    url: postUrl || window.location.href,
                    content: postContent,
                    max_cards: maxCards,
                    force_refresh: forceRefresh,
                },
                parse: false,
            } );

            const cacheHeader = response.headers.get( 'X-KHM-GEO-Cache' );
            setCacheStatus( cacheHeader );

            const data = await response.json();

            if ( data.cards && Array.isArray( data.cards ) ) {
                setSuggestions( data.cards );
                setSelectedCards( data.cards.map( ( _, i ) => i ) );
            } else {
                throw new Error( 'Invalid response format' );
            }
        } catch ( err ) {
            console.error( 'Suggestion fetch error details:', err );
            console.error( 'Error type:', typeof err );
            console.error( 'Error properties:', Object.keys(err));
            console.error( 'Error message:', err.message );
            console.error( 'Error code:', err.code );
            console.error( 'Error data:', err.data );
            
            let errorMessage = err.message || __( 'Failed to fetch suggestions', 'khm-membership' );
            
            // Handle specific error codes from REST API
            if ( err.code === 'rate_limit_exceeded' ) {
                errorMessage = __( 'Rate limit exceeded. Please wait before trying again.', 'khm-membership' );
            } else if ( err.code === 'validation_failed' ) {
                errorMessage = __( 'Generated content failed validation. Please try again.', 'khm-membership' );
            } else if ( err.code === 'no_api_key' ) {
                errorMessage = __( 'OpenAI API key not configured. Please set it in Dual GPT settings.', 'khm-membership' );
            } else if ( err.code === 'rest_forbidden' || err.code === 'rest_cannot_edit' ) {
                errorMessage = __( 'You do not have permission to generate suggestions. Please log in.', 'khm-membership' );
            } else if ( err.data && err.data.status === 401 ) {
                errorMessage = __( 'Authentication required. Please log in to use this feature.', 'khm-membership' );
            } else if ( err.data && err.data.status === 403 ) {
                errorMessage = __( 'You do not have permission to use this feature.', 'khm-membership' );
            }
            
            setError( errorMessage );
        } finally {
            setIsLoading( false );
        }
    }, [ postId, postTitle, postUrl, postContent, maxCards ] );

    /**
     * Toggle card selection
     */
    const toggleCardSelection = ( index ) => {
        setSelectedCards( ( prev ) => {
            if ( prev.includes( index ) ) {
                return prev.filter( ( i ) => i !== index );
            }
            return [ ...prev, index ];
        } );
    };

    /**
     * Insert selected cards as blocks
     */
    const insertSelectedCards = useCallback( () => {
        if ( !isBlockEditorAvailable ) {
            console.error( '[KHM GEO] Block editor not available for inserting blocks' );
            setError( 'Block editor not available on this page' );
            return;
        }

        const blocksToInsert = selectedCards.map( ( index ) => {
            const card = suggestions[ index ];
            const evidence = card.evidence || {};
            
            return createBlock( 'khm/answer-card', {
                question: card.question || '',
                conciseAnswer: card.concise_answer || '',
                keyPoints: card.key_points || [],
                citations: card.citations || [],
                entities: card.entities || [],
                evidence: {
                    tier: evidence.tier || '',
                    confidence: evidence.confidence ?? card.confidence ?? 0,
                    context_heading: evidence.context_heading || '',
                    source_passage: evidence.source_passage || '',
                    anchor_entities: evidence.anchor_entities || [],
                },
                exposeInSchema: true,
            } );
        } );

        if ( blocksToInsert.length > 0 ) {
            insertBlocks( blocksToInsert );
            onClose();
        }
    }, [ selectedCards, suggestions, insertBlocks, onClose, isBlockEditorAvailable ] );

    /**
     * Edit a card before inserting
     */
    const editCard = ( card ) => {
        if ( !isBlockEditorAvailable ) {
            console.error( '[KHM GEO] Block editor not available for inserting blocks' );
            setError( 'Block editor not available on this page' );
            return;
        }
        const evidence = card.evidence || {};
        const block = createBlock( 'khm/answer-card', {
            question: card.question || '',
            conciseAnswer: card.concise_answer || '',
            keyPoints: card.key_points || [],
            citations: card.citations || [],
            entities: card.entities || [],
            evidence: {
                tier: evidence.tier || '',
                confidence: evidence.confidence ?? card.confidence ?? 0,
                context_heading: evidence.context_heading || '',
                source_passage: evidence.source_passage || '',
                anchor_entities: evidence.anchor_entities || [],
            },
            exposeInSchema: true,
        } );

        insertBlocks( [ block ] );
        onClose();
    };

    if ( ! isOpen ) {
        return null;
    }

    return (
        <Modal
            title={ __( 'Suggest AnswerCards', 'khm-membership' ) }
            onRequestClose={ onClose }
            className="khm-suggest-modal"
            size="large"
        >
            <div className="khm-suggest-modal__content">
                { ! suggestions.length && ! isLoading && ! error && (
                    <div className="khm-suggest-modal__intro">
                        <Text>
                            { __( 'Generate AI-powered AnswerCard suggestions based on your article content. The AI will analyze your content and suggest structured Q&A cards optimized for search engines and AI citations.', 'khm-membership' ) }
                        </Text>
                        
                        <div className="khm-suggest-modal__options">
                            <RangeControl
                                label={ __( 'Maximum cards to generate', 'khm-membership' ) }
                                value={ maxCards }
                                onChange={ setMaxCards }
                                min={ 1 }
                                max={ 8 }
                                __nextHasNoMarginBottom
                            />
                        </div>

                        <Button
                            variant="primary"
                            onClick={ () => fetchSuggestions( false ) }
                            icon={ plus }
                            className="khm-suggest-modal__generate-btn"
                        >
                            { __( 'Generate Suggestions', 'khm-membership' ) }
                        </Button>
                    </div>
                ) }

                { isLoading && (
                    <div className="khm-suggest-modal__loading">
                        <Spinner />
                        <Text>{ __( 'Analyzing content and generating suggestions...', 'khm-membership' ) }</Text>
                        <Text size="12px" variant="muted">
                            { __( 'This may take 10-30 seconds depending on content length.', 'khm-membership' ) }
                        </Text>
                    </div>
                ) }

                { error && (
                    <Notice status="error" isDismissible={ false }>
                        { error }
                        <Button
                            variant="secondary"
                            onClick={ () => fetchSuggestions( false ) }
                            style={ { marginLeft: '10px' } }
                        >
                            { __( 'Try Again', 'khm-membership' ) }
                        </Button>
                    </Notice>
                ) }

                { suggestions.length > 0 && ! isLoading && (
                    <>
                        { cacheStatus && (
                            <div className="khm-suggest-modal__cache-info">
                                <Text size="12px" variant="muted">
                                    { cacheStatus === 'HIT' 
                                        ? __( 'Using cached suggestions', 'khm-membership' )
                                        : __( 'Fresh suggestions generated', 'khm-membership' )
                                    }
                                </Text>
                            </div>
                        ) }

                        <div className="khm-suggest-modal__cards">
                            { suggestions.map( ( card, index ) => (
                                <SuggestionCard
                                    key={ index }
                                    card={ card }
                                    index={ index }
                                    selected={ selectedCards.includes( index ) }
                                    onToggle={ () => toggleCardSelection( index ) }
                                    onEdit={ editCard }
                                />
                            ) ) }
                        </div>

                        <div className="khm-suggest-modal__actions">
                            <Flex>
                                <FlexItem>
                                    <Button
                                        variant="secondary"
                                        onClick={ () => fetchSuggestions( true ) }
                                    >
                                        { __( 'Regenerate', 'khm-membership' ) }
                                    </Button>
                                </FlexItem>
                                <FlexItem>
                                    <Text variant="muted">
                                        { selectedCards.length } of { suggestions.length } selected
                                    </Text>
                                </FlexItem>
                                <FlexItem>
                                    <Button
                                        variant="primary"
                                        onClick={ insertSelectedCards }
                                        disabled={ selectedCards.length === 0 }
                                        icon={ check }
                                    >
                                        { __( 'Insert Selected', 'khm-membership' ) }
                                    </Button>
                                </FlexItem>
                            </Flex>
                        </div>
                    </>
                ) }
            </div>
        </Modal>
    );
};

/**
 * Plugin sidebar content
 */
const AnswerCardSidebarContent = () => {
    const [ isModalOpen, setIsModalOpen ] = useState( false );

    const { postId, postTitle, postContent, postUrl } = useSelect( ( select ) => {
        try {
            const { getCurrentPostId, getEditedPostAttribute } = select( 'core/editor' );
            const id = getCurrentPostId();
            const title = getEditedPostAttribute( 'title' ) || '';
            const content = getEditedPostAttribute( 'content' ) || '';
            const link = getEditedPostAttribute( 'link' ) || '';

            const plainContent = content.replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim();

            return {
                postId: id,
                postTitle: title,
                postContent: plainContent,
                postUrl: link,
            };
        } catch ( error ) {
            console.warn( '[KHM GEO] core/editor store not available:', error );
            // Fallback to localized data
            return {
                postId: window.khmGeoSuggest?.postId || 0,
                postTitle: '',
                postContent: '',
                postUrl: '',
            };
        }
    }, [] );

    const { insertBlocks } = useDispatch( 'core/block-editor' );
    const { openGeneralSidebar } = useDispatch( 'core/edit-post' );

    // Safety check for block editor availability
    const isBlockEditorAvailable = !!insertBlocks;

    // Listen for custom event to open suggestions modal
    useEffect( () => {
        const handleOpenSuggestions = () => {
            if ( typeof openGeneralSidebar === 'function' ) {
                openGeneralSidebar( 'plugin/khm-geo-sidebar' );
            }
            setIsModalOpen( true );
        };

        window.addEventListener( 'khmGeoOpenSuggestions', handleOpenSuggestions );

        return () => {
            window.removeEventListener( 'khmGeoOpenSuggestions', handleOpenSuggestions );
        };
    }, [] );

    const answerCardCount = useSelect( ( select ) => {
        try {
            const blocks = select( 'core/block-editor' ).getBlocks();
            return blocks.filter( ( block ) => block.name === 'khm/answer-card' ).length;
        } catch ( error ) {
            console.warn( '[KHM GEO] core/block-editor store not available:', error );
            return 0;
        }
    }, [] );

    return (
        <>
            <PanelBody title={ __( 'AI Suggestions', 'khm-membership' ) } initialOpen={ true }>
                <Text>
                    { __( 'Use AI to analyze your content and suggest optimized AnswerCards for GEO (Generative Engine Optimization).', 'khm-membership' ) }
                </Text>
                
                <div style={ { marginTop: '15px' } }>
                    { !isBlockEditorAvailable ? (
                        <Notice status="warning" isDismissible={ false }>
                            { __( 'Block editor not available on this page. Please use the post editor.', 'khm-membership' ) }
                        </Notice>
                    ) : (
                        <Button
                            variant="primary"
                            onClick={ () => setIsModalOpen( true ) }
                            icon={ help }
                            disabled={ ! postContent || postContent.length < 100 }
                        >
                            { __( 'Suggest AnswerCards', 'khm-membership' ) }
                        </Button>
                    ) }
                </div>

                { postContent.length < 100 && (
                    <Notice status="warning" isDismissible={ false } className="khm-sidebar-notice">
                        { __( 'Add more content to enable AI suggestions (minimum 100 characters).', 'khm-membership' ) }
                    </Notice>
                ) }
            </PanelBody>

            <PanelBody title={ __( 'Current AnswerCards', 'khm-membership' ) } initialOpen={ true }>
                <Text>
                    { answerCardCount === 0 
                        ? __( 'No AnswerCards in this post yet.', 'khm-membership' )
                        : `${ answerCardCount } AnswerCard${ answerCardCount > 1 ? 's' : '' } in this post.`
                    }
                </Text>
            </PanelBody>

            <SuggestAnswerCardsModal
                isOpen={ isModalOpen }
                onClose={ () => setIsModalOpen( false ) }
                postId={ postId }
                postTitle={ postTitle }
                postContent={ postContent }
                postUrl={ postUrl }
            />
        </>
    );
};

/**
 * Register the plugin
 */
console.log('[KHM GEO] Checking khmGeoSuggest localization:', window.khmGeoSuggest);
console.log('[KHM GEO] Registering khm-answercard-suggestions plugin...');

registerPlugin( 'khm-answercard-suggestions', {
    render: () => (
        <>
            <PluginSidebarMoreMenuItem target="khm-geo-sidebar">
                { __( 'GEO AnswerCards', 'khm-membership' ) }
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="khm-geo-sidebar"
                title={ __( 'GEO AnswerCards', 'khm-membership' ) }
                icon={ help }
            >
                <AnswerCardSidebarContent />
            </PluginSidebar>
        </>
    ),
} );
