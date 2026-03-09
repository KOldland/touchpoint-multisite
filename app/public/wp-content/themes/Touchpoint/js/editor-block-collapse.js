( function( wp ) {
	if ( ! wp || ! wp.domReady ) {
		return;
	}

	const TARGET_BLOCKS = {
		'acf/abstract': 'Abstract Block',
		'acf/footnotes': 'Footnotes Block',
	};

	const COLLAPSED_CLASS = 'touchpoint-editor-block-collapsed';
	const COLLAPSIBLE_CLASS = 'touchpoint-editor-block-collapsible';
	const CONTROLS_CLASS = 'touchpoint-editor-block-collapse-controls';

	const getClientId = ( blockEl ) => blockEl.getAttribute( 'data-block' ) || '';
	const getStorageKey = ( clientId ) => `touchpoint:block-collapse:${ clientId }`;

	const readCollapsedState = ( clientId ) => {
		if ( ! clientId ) {
			return false;
		}

		try {
			return window.sessionStorage.getItem( getStorageKey( clientId ) ) === '1';
		} catch ( error ) {
			return false;
		}
	};

	const writeCollapsedState = ( clientId, isCollapsed ) => {
		if ( ! clientId ) {
			return;
		}

		try {
			window.sessionStorage.setItem( getStorageKey( clientId ), isCollapsed ? '1' : '0' );
		} catch ( error ) {
			// Ignore storage errors.
		}
	};

	const setButtonLabel = ( buttonEl, isCollapsed ) => {
		const nextLabel = isCollapsed ? 'Expand' : 'Collapse';
		if ( buttonEl.textContent !== nextLabel ) {
			buttonEl.textContent = nextLabel;
		}
	};

	const applyCollapsedState = ( blockEl, buttonEl, isCollapsed ) => {
		const hasCollapsedClass = blockEl.classList.contains( COLLAPSED_CLASS );
		if ( hasCollapsedClass !== isCollapsed ) {
			blockEl.classList.toggle( COLLAPSED_CLASS, isCollapsed );
		}
		setButtonLabel( buttonEl, isCollapsed );
	};

	const ensureControls = ( blockEl ) => {
		const blockType = blockEl.getAttribute( 'data-type' );
		if ( ! TARGET_BLOCKS[ blockType ] ) {
			return;
		}

		blockEl.classList.add( COLLAPSIBLE_CLASS );

		let controlsEl = blockEl.querySelector( `.${ CONTROLS_CLASS }` );

		if ( controlsEl ) {
			return;
		}

		controlsEl = document.createElement( 'div' );
		controlsEl.className = CONTROLS_CLASS;

		const labelEl = document.createElement( 'span' );
		labelEl.className = 'touchpoint-editor-block-collapse-label';
		labelEl.textContent = TARGET_BLOCKS[ blockType ];

		const buttonEl = document.createElement( 'button' );
		buttonEl.type = 'button';
		buttonEl.className = 'components-button is-secondary is-small';

		const clientId = getClientId( blockEl );
		const initialCollapsed = readCollapsedState( clientId );
		applyCollapsedState( blockEl, buttonEl, initialCollapsed );

		buttonEl.addEventListener( 'click', () => {
			const isCollapsed = ! blockEl.classList.contains( COLLAPSED_CLASS );
			applyCollapsedState( blockEl, buttonEl, isCollapsed );
			writeCollapsedState( clientId, isCollapsed );
		} );

		controlsEl.appendChild( labelEl );
		controlsEl.appendChild( buttonEl );
		blockEl.insertBefore( controlsEl, blockEl.firstChild );
	};

	const refreshBlocks = () => {
		document.querySelectorAll( '.block-editor-block-list__block[data-type]' ).forEach( ensureControls );
	};

	wp.domReady( () => {
		refreshBlocks();
		let refreshQueued = false;

		const observer = new MutationObserver( () => {
			if ( refreshQueued ) {
				return;
			}

			refreshQueued = true;
			window.requestAnimationFrame( () => {
				refreshQueued = false;
				refreshBlocks();
			} );
		} );

		observer.observe( document.body, {
			childList: true,
			subtree: true,
		} );
	} );
} )( window.wp );
