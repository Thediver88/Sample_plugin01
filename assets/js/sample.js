/**
 * Sample Shelf: on a variable product, copy the option the shopper chose in the main form
 * (variation id and attribute values, "any" attributes included) into the sample form.
 * The server re-checks everything; this only fills in the form and enables the button.
 * It works with the classic variation form and with the Add to Cart with Options block.
 */
( function () {
	'use strict';

	function init() {
		var sample = document.querySelector( 'form.smpshelf-sample-form[data-smpshelf-variable]' );
		if ( ! sample ) {
			return;
		}
		var button = sample.querySelector( '.smpshelf-button' );
		var hint = sample.querySelector( '.smpshelf-hint' );
		var attrs = sample.querySelector( '.smpshelf-attributes' );
		var id = sample.querySelector( '.smpshelf-variation-id' );
		var last = '';

		function mainForm() {
			return document.querySelector( 'form.variations_form' ) || document.querySelector( 'form.cart' );
		}

		function sync() {
			var main = mainForm();
			var field = main ? main.querySelector( 'input[name="variation_id"]' ) : null;
			var chosen = field ? String( field.value || '' ) : '';
			var pairs = [];
			if ( chosen && '0' !== chosen && main ) {
				main.querySelectorAll( '[name^="attribute_"]' ).forEach( function ( el ) {
					pairs.push( [ el.name, el.value ] );
				} );
			}
			var key = chosen + '|' + JSON.stringify( pairs );
			if ( key === last ) {
				return;
			}
			last = key;
			id.value = chosen && '0' !== chosen ? chosen : '';
			attrs.textContent = '';
			pairs.forEach( function ( pair ) {
				var input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = pair[ 0 ];
				input.value = pair[ 1 ];
				attrs.appendChild( input );
			} );
			var ready = '' !== id.value;
			button.disabled = ! ready;
			if ( ready ) {
				button.removeAttribute( 'aria-disabled' );
			} else {
				button.setAttribute( 'aria-disabled', 'true' );
			}
			hint.style.display = ready ? 'none' : '';
		}

		// Themes and blocks change the chosen option in different ways: react to form events at once,
		// and keep a slow timer (paused while the tab is hidden) as a backstop.
		[ 'change', 'input', 'click' ].forEach( function ( name ) {
			document.addEventListener( name, function () {
				window.setTimeout( sync, 50 );
			} );
		} );
		window.setInterval( function () {
			if ( ! document.hidden ) {
				sync();
			}
		}, 500 );
		sync();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
