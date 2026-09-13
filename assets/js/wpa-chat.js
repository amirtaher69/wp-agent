/**
 * Chat screen — conversation state and REST calls (vanilla JS).
 * Config (chatUrl, nonce, maxMessages, i18n) is injected via wp_localize_script as `wpaChat`.
 *
 * The conversation lives in page memory only; persistence arrives in a later phase.
 * Model output is always rendered with textContent — never innerHTML.
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

    function set_pending( state ) {
        pending = state;
        input.disabled = state;
        sendBtn.disabled = state;
        resetBtn.disabled = state;
    }

    function send_message( text ) {
        messages.push( { role: 'user', content: text } );
        remove_empty_state();
        append_bubble( 'user', text );

        set_pending( true );
        var thinking = append_bubble( 'assistant', wpaChat.i18n.thinking, false );
        thinking.classList.add( 'is-thinking' );

        fetch( wpaChat.chatUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpaChat.nonce
            },
            body: JSON.stringify( {
                messages: messages.slice( -wpaChat.maxMessages )
            } )
        } )
            .then( function ( response ) {
                return response.json().then( function ( json ) {
                    return { ok: response.ok, json: json };
                } );
            } )
            .then( function ( res ) {
                thinking.remove();

                if ( res.ok && res.json && 'string' === typeof res.json.reply ) {
                    messages.push( { role: 'assistant', content: res.json.reply } );
                    append_bubble( 'assistant', res.json.reply );
                } else {
                    // Errors are shown in the thread but never enter the history.
                    var message = ( res.json && res.json.message ) ? res.json.message : wpaChat.i18n.genericError;
                    append_bubble( 'assistant', message, true );
                }
            } )
            .catch( function () {
                thinking.remove();
                append_bubble( 'assistant', wpaChat.i18n.networkError, true );
            } )
            .finally( function () {
                set_pending( false );
                input.focus();
            } );
    }

    form.addEventListener( 'submit', function ( event ) {
        event.preventDefault();

        var text = input.value.trim();

        if ( pending || '' === text ) {
            return;
        }

        input.value = '';
        send_message( text );
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
