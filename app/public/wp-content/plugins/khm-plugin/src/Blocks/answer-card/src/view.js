import './view.scss';

const openModal = ( modal, toggle ) => {
    if ( ! modal ) {
        return;
    }
    modal.classList.add( 'show' );
    modal.setAttribute( 'aria-hidden', 'false' );
    if ( toggle ) {
        toggle.setAttribute( 'aria-expanded', 'true' );
    }
    document.body.classList.add( 'khm-modal-open' );
};

const closeModal = ( modal, toggle ) => {
    if ( ! modal ) {
        return;
    }
    modal.classList.remove( 'show' );
    modal.setAttribute( 'aria-hidden', 'true' );
    if ( toggle ) {
        toggle.setAttribute( 'aria-expanded', 'false' );
    }
    if ( ! document.querySelector( '.khm-answer-card__modal.show' ) ) {
        document.body.classList.remove( 'khm-modal-open' );
    }
};

const showToast = ( message, tone = 'error' ) => {
    const existing = document.querySelector( '.khm-answer-card-toast' );
    if ( existing ) {
        existing.remove();
    }
    const toast = document.createElement( 'div' );
    toast.className = `khm-answer-card-toast ${ tone }`;
    const icon = document.createElement( 'span' );
    icon.className = 'khm-answer-card-toast__icon';
    icon.textContent = '!';
    const text = document.createElement( 'span' );
    text.className = 'khm-answer-card-toast__text';
    text.textContent = message;
    toast.appendChild( icon );
    toast.appendChild( text );
    document.body.appendChild( toast );
    requestAnimationFrame( () => {
        toast.classList.add( 'show' );
    } );
    setTimeout( () => {
        toast.classList.remove( 'show' );
        setTimeout( () => toast.remove(), 300 );
    }, 4000 );
};

const toggleMeta = ( toggle, target, label ) => {
    const isExpanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
    toggle.setAttribute( 'aria-expanded', isExpanded ? 'false' : 'true' );
    target.hidden = isExpanded;
    target.style.display = isExpanded ? 'none' : 'block';
    target.classList.toggle( 'is-open', ! isExpanded );
    target.setAttribute( 'aria-hidden', isExpanded ? 'true' : 'false' );
    if ( label ) {
        label.textContent = isExpanded ? 'Show meta' : 'Collapse meta';
    }
};

const bindMetaToggle = ( card ) => {
    const toggle = card.querySelector( '.khm-answer-card__meta-toggle' );
    if ( ! toggle ) {
        return;
    }
    if ( toggle.dataset.khmBound === 'true' ) {
        return;
    }
    const targetId = toggle.getAttribute( 'aria-controls' );
    let target = targetId ? document.getElementById( targetId ) : null;
    const label = toggle.querySelector( '.khm-answer-card__meta-label' );

    if ( ! target ) {
        const modal = toggle.closest( '.khm-answer-card__modal' ) || card;
        target = modal ? modal.querySelector( '.khm-answer-card__meta' ) : null;
    }

    if ( ! target ) {
        return;
    }

    toggle.dataset.khmBound = 'true';
    toggle.addEventListener( 'click', ( event ) => {
        event.preventDefault();
        event.stopPropagation();
        toggleMeta( toggle, target, label );
    } );
};

const bindSaveButton = ( card ) => {
    const button = card.querySelector( '.khm-answer-card__save' );
    if ( ! button ) {
        return;
    }

    const postId = button.getAttribute( 'data-post-id' );
    const answerCardId = button.getAttribute( 'data-answer-card-id' );
    const question = button.getAttribute( 'data-answer-card-question' );
    const nonce = button.getAttribute( 'data-rest-nonce' );
    const loginUrl = button.getAttribute( 'data-login-url' );
    const restRoot = button.getAttribute( 'data-rest-root' ) || '/wp-json/';

    button.addEventListener( 'click', async () => {
        if ( ! nonce ) {
            if ( loginUrl ) {
                window.location.href = loginUrl;
            }
            return;
        }

        if ( ! postId ) {
            return;
        }

        const base = restRoot.endsWith( '/' ) ? restRoot : `${ restRoot }/`;
        const endpoint = `${ base }khm/v1/portal/answercards/${ postId }`;

        button.disabled = true;
        const label = button.querySelector( '.khm-answer-card__save-label' );
        const originalText = label ? label.textContent : button.textContent;
        if ( label ) {
            label.textContent = 'Saving...';
        } else {
            button.textContent = 'Saving...';
        }
        button.setAttribute( 'title', 'Saving...' );

        try {
            const response = await fetch( endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                credentials: 'same-origin',
                body: JSON.stringify( { answer_card_id: answerCardId, question } ),
            } );

            if ( ! response.ok ) {
                let message = 'Save failed';
                try {
                    const data = await response.json();
                    if ( data && data.error ) {
                        message = data.error;
                    }
                } catch ( err ) {
                    // ignore parsing errors
                }
                throw new Error( message );
            }

            if ( label ) {
                label.textContent = 'Saved';
            } else {
                button.textContent = 'Saved';
            }
            button.setAttribute( 'title', 'Saved to library' );
            button.classList.add( 'is-saved' );
        } catch ( error ) {
            if ( label ) {
                label.textContent = originalText;
            } else {
                button.textContent = originalText;
            }
            button.setAttribute( 'title', originalText );
            button.disabled = false;
            showToast( error.message || 'Unable to save section summary. Please try again.' );
        }
    } );
};

const openShareModal = ( card, data ) => {
    const existing = document.querySelector( '.khm-answer-card-share-modal' );
    if ( existing ) {
        existing.remove();
    }

    const modal = document.createElement( 'div' );
    modal.className = 'khm-answer-card-share-modal khm-modal-backdrop';
    modal.innerHTML = `
        <div class="khm-modal khm-answer-card-share-modal__card" role="dialog" aria-modal="true">
            <div class="khm-modal-header">
                <h3 class="khm-modal-title">Share section summary</h3>
                <button type="button" class="khm-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="khm-modal-content">
                <div class="khm-answer-card-share-modal__title">${ data.question || '' }</div>
                <form class="khm-answer-card-share-modal__form">
                    <label>
                        Recipient Email:
                        <span class="khm-answer-card-share-modal__input-row">
                            <input type="email" name="recipient_email" required placeholder="friend@example.com" />
                            <button type="button" class="khm-answer-card-share-modal__contact-btn" aria-label="Open address book" title="Open address book">
                                <span class="dashicons dashicons-admin-users"></span>
                            </button>
                        </span>
                    </label>
                    <label>
                        Personal Message (Optional):
                        <textarea name="personal_message" placeholder="I thought you'd find this section summary useful..."></textarea>
                    </label>
                    <div class="khm-answer-card-share-modal__actions">
                        <button type="button" class="khm-answer-card-share-modal__cancel">Cancel</button>
                        <button type="submit" class="khm-answer-card-share-modal__submit">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    document.body.appendChild( modal );
    requestAnimationFrame( () => modal.classList.add( 'show' ) );

    const closeModal = () => {
        modal.classList.remove( 'show' );
        setTimeout( () => modal.remove(), 200 );
    };

    modal.addEventListener( 'click', ( event ) => {
        if ( event.target === modal ) {
            closeModal();
        }
    } );

    modal.querySelector( '.khm-modal-close' ).addEventListener( 'click', closeModal );
    modal.querySelector( '.khm-answer-card-share-modal__cancel' ).addEventListener( 'click', closeModal );

    modal.querySelector( '.khm-answer-card-share-modal__form' ).addEventListener( 'submit', async ( event ) => {
        event.preventDefault();

        const recipient = modal.querySelector( 'input[name="recipient_email"]' ).value.trim();
        if ( ! recipient ) {
            showToast( 'Please enter a recipient email address.' );
            return;
        }

        const message = modal.querySelector( 'textarea[name="personal_message"]' ).value.trim();
        const submitButton = modal.querySelector( '.khm-answer-card-share-modal__submit' );
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';

        try {
            // Validate ajaxUrl is from the same origin to prevent XSS
            const ajaxUrl = new URL( data.ajaxUrl, window.location.origin );
            if ( ajaxUrl.origin !== window.location.origin ) {
                throw new Error( 'Invalid URL' );
            }

            const body = new URLSearchParams();
            body.append( 'action', 'khm_share_library_article' );
            body.append( 'nonce', data.shareNonce );
            body.append( 'post_id', data.postId );
            body.append( 'recipient_email', recipient );
            body.append( 'personal_message', message );
            body.append( 'include_notes', 'false' );
            body.append( 'include_membership_info', 'false' );

            const response = await fetch( ajaxUrl.toString(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                credentials: 'same-origin',
                body: body.toString(),
            } );

            const payload = await response.json();
            if ( ! payload || payload.success !== true ) {
                throw new Error( ( payload && payload.data ) || 'Share failed' );
            }

            showToast( 'Section summary shared.', 'success' );
            closeModal();
        } catch ( error ) {
            showToast( error.message || 'Share failed.' );
            submitButton.disabled = false;
            submitButton.textContent = 'Send Email';
        }
    } );
};

const bindShareButton = ( card ) => {
    const button = card.querySelector( '.khm-answer-card__share' );
    if ( ! button ) {
        return;
    }

    const postId = button.getAttribute( 'data-post-id' );
    const question = button.getAttribute( 'data-answer-card-question' );
    const ajaxUrl = button.getAttribute( 'data-ajax-url' );
    const shareNonce = button.getAttribute( 'data-share-nonce' );
    const loginUrl = button.getAttribute( 'data-login-url' );

    button.addEventListener( 'click', () => {
        if ( ! shareNonce ) {
            if ( loginUrl ) {
                window.location.href = loginUrl;
            }
            return;
        }

        if ( ! ajaxUrl || ! postId ) {
            return;
        }

        openShareModal( card, {
            postId,
            question,
            ajaxUrl,
            shareNonce,
        } );
    } );
};

const bindAnswerCard = ( card ) => {
    const toggle = card.querySelector( '.khm-answer-card__toggle' );
    const modal = card.querySelector( '.khm-answer-card__modal' );
    const closeButton = card.querySelector( '.khm-answer-card__modal-close' );

    if ( toggle && modal ) {
        toggle.addEventListener( 'click', () => openModal( modal, toggle ) );
    }

    if ( closeButton && modal ) {
        closeButton.addEventListener( 'click', () => closeModal( modal, toggle ) );
    }

    if ( modal ) {
        modal.addEventListener( 'click', ( event ) => {
            if ( event.target === modal ) {
                closeModal( modal, toggle );
            }
        } );
    }

    bindMetaToggle( card );
    bindSaveButton( card );
    bindShareButton( card );
};

const init = () => {
    const cards = document.querySelectorAll( '.khm-answer-card' );
    if ( ! cards.length ) {
        return;
    }

    cards.forEach( bindAnswerCard );

    document.addEventListener( 'click', ( event ) => {
        const toggle = event.target.closest( '.khm-answer-card__meta-toggle' );
        if ( ! toggle || toggle.dataset.khmBound === 'true' ) {
            return;
        }
        const targetId = toggle.getAttribute( 'aria-controls' );
        let target = targetId ? document.getElementById( targetId ) : null;
        if ( ! target ) {
            const modal = toggle.closest( '.khm-answer-card__modal' ) || toggle.closest( '.khm-answer-card' );
            target = modal ? modal.querySelector( '.khm-answer-card__meta' ) : null;
        }
        if ( ! target ) {
            return;
        }
        const label = toggle.querySelector( '.khm-answer-card__meta-label' );
        event.preventDefault();
        event.stopPropagation();
        toggleMeta( toggle, target, label );
    } );

    document.addEventListener( 'keydown', ( event ) => {
        if ( event.key !== 'Escape' ) {
            return;
        }
        const openModalEl = document.querySelector( '.khm-answer-card__modal.show' );
        if ( ! openModalEl ) {
            return;
        }
        const card = openModalEl.closest( '.khm-answer-card' );
        const toggle = card ? card.querySelector( '.khm-answer-card__toggle' ) : null;
        closeModal( openModalEl, toggle );
    } );
};

if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', init );
} else {
    init();
}
