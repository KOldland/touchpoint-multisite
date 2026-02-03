import { render } from '@wordpress/element';
import App from './App';

import './block';
import './style.scss';

const container = document.getElementById( 'ep-citation-qa-root' );

if ( container ) {
    const sessionId = container.dataset.sessionId || '';
    render( <App sessionId={ sessionId } />, container );
}
