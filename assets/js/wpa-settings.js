/**
 * Settings screen — "test connection" button behaviour (vanilla JS).
 * Config (ajaxUrl, nonce, i18n) is injected via wp_localize_script as `wpaSettings`.
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
        var data = new FormData();
        data.append( 'action', 'wpa_test_connection' );
        data.append( 'nonce', wpaSettings.nonce );

        button.disabled = true;
        show_result( wpaSettings.i18n.testing, 'loading' );

        fetch( wpaSettings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        } )
            .then( function ( response ) {
                return response.json();
            } )
            .then( function ( json ) {
                var message = ( json.data && json.data.message ) ? json.data.message : wpaSettings.i18n.genericError;
                show_result( message, json.success ? 'success' : 'error' );
            } )
            .catch( function () {
                show_result( wpaSettings.i18n.networkError, 'error' );
            } )
            .finally( function () {
                button.disabled = false;
            } );
    } );
} )();
