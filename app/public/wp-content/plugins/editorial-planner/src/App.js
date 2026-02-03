import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

const App = ( { sessionId } ) => {
    const [ citations, setCitations ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ error, setError ] = useState( '' );
    const [ approved, setApproved ] = useState( {} );
    const [ rejected, setRejected ] = useState( {} );
    const [ additionalKeywords, setAdditionalKeywords ] = useState( '' );
    const [ statusMessage, setStatusMessage ] = useState( '' );

    useEffect(() => {
        if ( ! sessionId ) {
            setError( 'Session ID is required to load citations.' );
            setLoading( false );
            return;
        }

        setLoading( true );
        setError( '' );
        setStatusMessage( '' );

        apiFetch( { path: `/ep/v1/session/${ sessionId }` } )
            .then( ( data ) => {
                setCitations( data.citations || [] );
                setLoading( false );
            } )
            .catch( ( err ) => {
                setError( err?.message || 'Failed to load citations.' );
                setLoading( false );
            } );
    }, [ sessionId ] );

    const toggleApprove = ( citationId ) => {
        setApproved( ( prev ) => ( { ...prev, [ citationId ]: ! prev[ citationId ] } ) );
        setRejected( ( prev ) => {
            if ( ! prev[ citationId ] ) {
                return prev;
            }
            const next = { ...prev };
            delete next[ citationId ];
            return next;
        } );
    };

    const toggleReject = ( citationId ) => {
        setRejected( ( prev ) => ( { ...prev, [ citationId ]: ! prev[ citationId ] } ) );
        setApproved( ( prev ) => {
            if ( ! prev[ citationId ] ) {
                return prev;
            }
            const next = { ...prev };
            delete next[ citationId ];
            return next;
        } );
    };

    const handleConfirm = () => {
        const approvedIds = Object.keys( approved ).filter( ( id ) => approved[ id ] );
        const rejectedIds = Object.keys( rejected ).filter( ( id ) => rejected[ id ] );
        const keywords = additionalKeywords
            .split( ',' )
            .map( ( term ) => term.trim() )
            .filter( Boolean );

        setStatusMessage( 'Submitting QA decisions...' );

        apiFetch( {
            path: `/ep/v1/citation-qa/${ sessionId }`,
            method: 'POST',
            data: {
                approved_citation_ids: approvedIds,
                rejected_citation_ids: rejectedIds,
                additional_keywords: keywords,
            },
        } )
            .then( () => {
                setStatusMessage( 'Citation QA submitted. Phase 3 queued.' );
            } )
            .catch( ( err ) => {
                setStatusMessage( err?.message || 'Failed to submit Citation QA.' );
            } );
    };

    const handleRegenerate = () => {
        const idempotencyKey = `regenerate-${ Date.now() }`;
        setStatusMessage( 'Regeneration requested...' );

        apiFetch( {
            path: `/ep/v1/regenerate-citations/${ sessionId }`,
            method: 'POST',
            data: {
                idempotency_key: idempotencyKey,
            },
        } )
            .then( () => {
                setStatusMessage( 'Regeneration queued. Refresh in a moment to see new citations.' );
            } )
            .catch( ( err ) => {
                setStatusMessage( err?.message || 'Failed to request regeneration.' );
            } );
    };

    if (loading) {
        return <div>Loading...</div>;
    }

    if ( error ) {
        return <div>{ error }</div>;
    }

    return (
        <div className="ep-citation-qa-app">
            <h1>Citation QA</h1>
            {citations.length === 0 && <p>No citations available yet.</p>}
            {citations.map(citation => (
                <div key={citation.id} style={{ border: '1px solid #ccc', padding: '10px', margin: '10px 0' }}>
                    <h3>{citation.title}</h3>
                    <p>{citation.relevance_note || citation.passage_snippet}</p>
                    {citation.sponsored && <p><strong>Sponsored</strong></p>}
                    {citation.url && (
                        <p>
                            <a href={citation.url} target="_blank" rel="noreferrer">View source</a>
                        </p>
                    )}
                    <div>
                        <label>
                            <input
                                type="checkbox"
                                checked={ !!approved[ citation.id ] }
                                onChange={ () => toggleApprove( citation.id ) }
                            />
                            Approve
                        </label>
                        <button
                            style={{ marginLeft: '10px' }}
                            type="button"
                            onClick={ () => toggleReject( citation.id ) }
                        >
                            {rejected[ citation.id ] ? 'Rejected' : 'Reject'}
                        </button>
                    </div>
                </div>
            ))}
            <div style={{ marginTop: '20px' }}>
                <textarea
                    placeholder="Add additional keywords (comma separated)..."
                    value={ additionalKeywords }
                    onChange={ ( event ) => setAdditionalKeywords( event.target.value ) }
                />
            </div>
            <div style={{ marginTop: '10px' }}>
                <button type="button" onClick={ handleConfirm }>Confirm</button>
                <button type="button" style={{ marginLeft: '10px' }} onClick={ handleRegenerate }>Regenerate</button>
            </div>
            {statusMessage && (
                <p style={{ marginTop: '10px' }}>{statusMessage}</p>
            )}
        </div>
    );
};

export default App;
