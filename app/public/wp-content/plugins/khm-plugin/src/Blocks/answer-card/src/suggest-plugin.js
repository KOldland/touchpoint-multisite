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
 * Confidence badge component
 */
const ConfidenceBadge = ( { confidence } ) => {
    const score = parseFloat( confidence ) * 100;
    let variant = 'low';
    if ( score >= 80 ) {
        variant = 'high';
    } else if ( score >= 60 ) {
        variant = 'medium';
    }

    return (
        <span className={ `khm-confidence-badge khm-confidence-badge--${ variant }` }>
            { score.toFixed( 0 ) }% confidence
        </span>
    );
};

/**
 * Single suggestion card preview
 */
const SuggestionCard = ( { card, index, selected, onToggle, onEdit } ) => {
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
                            { card.question }
                        </Heading>
                    </FlexItem>
                    <FlexItem>
                        <ConfidenceBadge confidence={ card.confidence || 0.5 } />
                    </FlexItem>
                </Flex>
            </CardHeader>
            <CardBody>
                <div className="khm-suggestion-card__answer">
                    <Text>{ card.concise_answer }</Text>
                </div>
                { card.key_points && card.key_points.length > 0 && (
                    <div className="khm-suggestion-card__key-points">
                        <Text weight="600" size="12px">Key Points:</Text>
                        <ul>
                            { card.key_points.map( ( point, i ) => (
                                <li key={ i }>{ point }</li>
                            ) ) }
                        </ul>
                    </div>
                ) }
                { card.citations && card.citations.length > 0 && (
                    <div className="khm-suggestion-card__citations">
                        <Text weight="600" size="12px">Citations:</Text>
                        <ul>
                            { card.citations.map( ( citation, i ) => (
                                <li key={ i }>
                                    <a href={ citation.url } target="_blank" rel="noopener noreferrer">
                                        { citation.title || citation.url }
                                    </a>
                                </li>
                            ) ) }
                        </ul>
                    </div>
                ) }
                { card.notes && (
                    <div className="khm-suggestion-card__notes">
                        <Text size="12px" isBlock>
                            <Icon icon={ warning } size={ 14 } /> { card.notes }
                        </Text>
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
     */
    const fetchSuggestions = useCallback( async () => {
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
            
            return createBlock( 'khm/answer-card', {
                question: card.question || '',
                conciseAnswer: card.concise_answer || '',
                keyPoints: card.key_points || [],
                citations: card.citations || [],
                entities: card.entities || [],
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
        const block = createBlock( 'khm/answer-card', {
            question: card.question || '',
            conciseAnswer: card.concise_answer || '',
            keyPoints: card.key_points || [],
            citations: card.citations || [],
            entities: card.entities || [],
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
                            onClick={ fetchSuggestions }
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
                            onClick={ fetchSuggestions }
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
                                        onClick={ fetchSuggestions }
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

    // Safety check for block editor availability
    const isBlockEditorAvailable = !!insertBlocks;

    // Listen for custom event to open suggestions modal
    useEffect( () => {
        const handleOpenSuggestions = () => {
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
