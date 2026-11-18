/**
 * Sample Shelf: on a variable product, copy the chosen option into the sample form.
 * The server re-checks everything; this only fills in the form and enables the button.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $sample = $( 'form.smpshelf-sample-form[data-smpshelf-variable]' );
		var $main = $( 'form.variations_form' ).first();
		if ( ! $sample.length || ! $main.length ) {
			return;
		}
		var $button = $sample.find( '.smpshelf-button' );
		var $hint = $sample.find( '.smpshelf-hint' );
		var $attrs = $sample.find( '.smpshelf-attributes' );
		var $id = $sample.find( '.smpshelf-variation-id' );

		function reset() {
			$id.val( '' );
			$attrs.empty();
			$button.prop( 'disabled', true ).attr( 'aria-disabled', 'true' );
			$hint.show();
		}

		$main.on( 'found_variation', function ( event, variation ) {
			if ( ! variation || ! variation.variation_id || ! variation.is_purchasable || ! variation.is_in_stock ) {
				reset();
				return;
			}
			$id.val( variation.variation_id );
			$attrs.empty();
			$.each( variation.attributes || {}, function ( name, value ) {
				$( '<input type="hidden">' ).attr( 'name', name ).val( value ).appendTo( $attrs );
			} );
			$button.prop( 'disabled', false ).removeAttr( 'aria-disabled' );
			$hint.hide();
		} );
		$main.on( 'reset_data hide_variation', reset );
	} );
}( jQuery ) );
