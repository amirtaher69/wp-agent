/**
 * Chat screen — conversation state, REST calls and the confirmation flow
 * for destructive actions (vanilla JS).
 * Config (chatUrl, confirmUrl, nonce, maxMessages, i18n) is injected via
 * wp_localize_script as `wpaChat`.
 *
 * The conversation lives in page memory only; persistence arrives in a later
 * phase. Model output is always rendered with textContent — never innerHTML.
 */
( function () {
    'use strict';

    var form     = document.getElementById( 'wpa-chat-form' );
    var input    = document.getElementById( 'wpa-chat-input' );
    var list     = document.getElementById( 'wpa-chat-messages' );
    var sendBtn  = document.getElementById( 'wpa-chat-send' );
    var resetBtn = document.getElementById( 'wpa-chat-reset' );

    if ( ! form || ! input || ! list || 'undefined' === typeof wpaChat ) {
        return;
    }

    var messages = [];
    var pending  = false;

    function scroll_to_bottom() {
        list.scrollTop = list.scrollHeight;
    }

    function remove_empty_state() {
        var empty = document.getElementById( 'wpa-chat-empty' );
        if ( empty ) {
            empty.remove();
        }
    }

    function show_empty_state() {
        var empty = document.createElement( 'div' );
        empty.className = 'wpa-chat-empty';
        empty.id = 'wpa-chat-empty';
        empty.textContent = wpaChat.i18n.emptyState;
        list.appendChild( empty );
    }

    function append_bubble( role, text, is_error ) {
        var row = document.createElement( 'div' );
        row.className = 'wpa-msg wpa-msg-' + role + ( is_error ? ' is-error' : '' );

        var content = document.createElement( 'div' );
        content.className = 'wpa-msg-content';
        content.textContent = text;

        row.appendChild( content );
        list.appendChild( row );
        scroll_to_bottom();

        return row;
    }

    // Chips strip showing which tools the agent executed for an answer.
    function append_steps( steps ) {
        var row = document.createElement( 'div' );
        row.className = 'wpa-msg wpa-msg-tools';

        var wrap = document.createElement( 'div' );
        wrap.className = 'wpa-tools-list';

        var label = document.createElement( 'span' );
        label.className = 'wpa-tools-label';
        label.textContent = wpaChat.i18n.toolsUsed;
        wrap.appendChild( label );

        steps.forEach( function ( step ) {
            var chip = document.createElement( 'span' );
            chip.className = 'wpa-tool-chip' + ( step.ok ? '' : ' is-failed' );
            chip.textContent = step.title || step.tool;
            wrap.appendChild( chip );
        } );

        row.appendChild( wrap );
        list.appendChild( row );
        scroll_to_bottom();
    }

    // Confirmation card for pending destructive actions.
    function append_confirmation( json ) {
        var row = document.createElement( 'div' );
        row.className = 'wpa-msg wpa-msg-confirm';

        var card = document.createElement( 'div' );
        card.className = 'wpa-confirm-card';

        var title = document.createElement( 'div' );
        title.className = 'wpa-confirm-title';
        title.textContent = wpaChat.i18n.confirmTitle;
        card.appendChild( title );

        ( json.actions || [] ).forEach( function ( action ) {
            var box = document.createElement( 'div' );
            box.className = 'wpa-confirm-action';

            var name = document.createElement( 'div' );
            name.className = 'wpa-confirm-action-title';
            name.textContent = action.title || action.tool;
            box.appendChild( name );

            ( action.summary || [] ).forEach( function ( line ) {
                var item = document.createElement( 'div' );
                item.className = 'wpa-confirm-line';
                item.textContent = line;
                box.appendChild( item );
            } );

            card.appendChild( box );
        } );

        var buttons = document.createElement( 'div' );
        buttons.className = 'wpa-confirm-buttons';

        var approveBtn = document.createElement( 'button' );
        approveBtn.type = 'button';
        approveBtn.className = 'button button-primary';
        approveBtn.textContent = wpaChat.i18n.approve;

        var rejectBtn = document.createElement( 'button' );
        rejectBtn.type = 'button';
        rejectBtn.className = 'button';
        rejectBtn.textContent = wpaChat.i18n.reject;

        var status = document.createElement( 'span' );
        status.className = 'wpa-confirm-status';

        function decide( approve ) {
            approveBtn.disabled = true;
            rejectBtn.disabled = true;
            card.classList.add( approve ? 'is-approved' : 'is-rejected' );
            status.textContent = approve ? wpaChat.i18n.approved : wpaChat.i18n.rejected;

            run_request( wpaChat.confirmUrl, { token: json.token, approve: approve } );
        }

        approveBtn.addEventListener( 'click', function () {
            decide( true );
        } );

        rejectBtn.addEventListener( 'click', function () {
            decide( false );
        } );

        buttons.appendChild( approveBtn );
        buttons.appendChild( rejectBtn );
        buttons.appendChild( status );
        card.appendChild( buttons );
        row.appendChild( card );
        list.appendChild( row );
        scroll_to_bottom();
    }

    function set_pending( state ) {
        pending = state;
        input.disabled = state;
        sendBtn.disabled = state;
        resetBtn.disabled = state;
    }

    function post_json( url, payload ) {
        return fetch( url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpaChat.nonce
            },
            body: JSON.stringify( payload )
        } ).then( function ( response ) {
            return response.json().then( function ( json ) {
                return { ok: response.ok, json: json };
            } );
        } );
    }

    /**
     * Render a chat/confirm response. Returns true while the turn is still
     * waiting on a user decision (confirmation card open).
     */
    function handle_response( res ) {
        if ( res.ok && res.json && res.json.confirmation_required ) {
            if ( Array.isArray( res.json.steps ) && res.json.steps.length ) {
                append_steps( res.json.steps );
            }
            append_confirmation( res.json );
            return true;
        }

        if ( res.ok && res.json && 'string' === typeof res.json.reply ) {
            if ( Array.isArray( res.json.steps ) && res.json.steps.length ) {
                append_steps( res.json.steps );
            }
            messages.push( { role: 'assistant', content: res.json.reply } );
            append_bubble( 'assistant', res.json.reply );
            return false;
        }

        // Errors are shown in the thread but never enter the history.
        var message = ( res.json && res.json.message ) ? res.json.message : wpaChat.i18n.genericError;
        append_bubble( 'assistant', message, true );
        return false;
    }

    function run_request( url, payload ) {
        set_pending( true );

        var thinking = append_bubble( 'assistant', wpaChat.i18n.thinking, false );
        thinking.classList.add( 'is-thinking' );

        post_json( url, payload )
            .then( function ( res ) {
                thinking.remove();

                var waiting = handle_response( res );

                if ( ! waiting ) {
                    set_pending( false );
                    input.focus();
                }
            } )
            .catch( function () {
                thinking.remove();
                append_bubble( 'assistant', wpaChat.i18n.networkError, true );
                set_pending( false );
            } );
    }

    form.addEventListener( 'submit', function ( event ) {
        event.preventDefault();

        var text = input.value.trim();

        if ( pending || '' === text ) {
            return;
        }

        input.value = '';
        messages.push( { role: 'user', content: text } );
        remove_empty_state();
        append_bubble( 'user', text );

        run_request( wpaChat.chatUrl, { messages: messages.slice( -wpaChat.maxMessages ) } );
    } );

    // Enter sends the message; Shift+Enter inserts a newline.
    input.addEventListener( 'keydown', function ( event ) {
        if ( 'Enter' === event.key && ! event.shiftKey ) {
            event.preventDefault();

            if ( 'function' === typeof form.requestSubmit ) {
                form.requestSubmit();
            } else {
                form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
            }
        }
    } );

    resetBtn.addEventListener( 'click', function () {
        if ( pending ) {
            return;
        }

        messages = [];
        list.textContent = '';
        show_empty_state();
        input.focus();
    } );
} )();
