import { useState, useEffect } from '@wordpress/element';

const App = () => {
    const [citations, setCitations] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        // In a real application, you would fetch the citations from the REST API
        const mockCitations = [
            { id: 'cid1-abc', title: 'Citation 1', passage_snippet: 'This is a snippet.', sponsored: false },
            { id: 'cid2-def', title: 'Citation 2', passage_snippet: 'This is another snippet.', sponsored: true },
            { id: 'cid3-ghi', title: 'Citation 3', passage_snippet: 'This is a third snippet.', sponsored: false },
        ];
        setCitations(mockCitations);
        setLoading(false);
    }, []);

    if (loading) {
        return <div>Loading...</div>;
    }

    return (
        <div className="ep-citation-qa-app">
            <h1>Citation QA</h1>
            {citations.map(citation => (
                <div key={citation.id} style={{ border: '1px solid #ccc', padding: '10px', margin: '10px 0' }}>
                    <h3>{citation.title}</h3>
                    <p>{citation.passage_snippet}</p>
                    {citation.sponsored && <p><strong>Sponsored</strong></p>}
                    <div>
                        <label>
                            <input type="checkbox" />
                            Approve
                        </label>
                        <button style={{ marginLeft: '10px' }}>Reject</button>
                    </div>
                </div>
            ))}
            <div style={{ marginTop: '20px' }}>
                <textarea placeholder="Add additional keywords..."></textarea>
            </div>
            <div style={{ marginTop: '10px' }}>
                <button>Confirm</button>
                <button style={{ marginLeft: '10px' }}>Regenerate</button>
            </div>
        </div>
    );
};

export default App;
