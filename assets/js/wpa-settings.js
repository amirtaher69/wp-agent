/**
 * Settings screen — "test connection" button behaviour (vanilla JS).
 * Config (restUrl, nonce, i18n) is injected via wp_localize_script as `wpaSettings`.
 */
( function () {
    'use strict';

    var button = document.getElementById( 'wpa-test-connection' );
    var result = document.getElementById( 'wpa-test-result' );

    if ( ! button || ! result || 'undefined' === typeof wpaSettings ) {
        return;
    }

    function show_result( message, state ) {
        result.textContent = message;
        result.className = 'wpa-test-result is-' + state;
    }

    button.addEventListener( 'click', function () {
        button.disabled = true;
        show_result( wpaSettings.i18n.testing, 'loading' );

        fetch( wpaSettings.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpaSettings.nonce
            },
            body: '{}'
        } )
            .then( function ( response ) {
                return response.json().then( function ( json ) {
                    return { ok: response.ok, json: json };
                } );
            } )
            .then( function ( res ) {
                var message = ( res.json && res.json.message ) ? res.json.message : wpaSettings.i18n.genericError;
                show_result( message, res.ok ? 'success' : 'error' );
            } )
            .catch( function () {
                show_result( wpaSettings.i18n.networkError, 'error' );
            } )
            .finally( function () {
                button.disabled = false;
            } );
    } );
} )();
