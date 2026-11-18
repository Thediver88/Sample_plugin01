// @ts-check
/**
 * Shopper journeys for Sample Shelf on a seeded store (see tests/e2e/seed.php).
 * Each test gets a fresh browser context, so a fresh, empty cart.
 */
const { test, expect } = require( '@playwright/test' );

const URL = {
	tile: '/product/sample-tile/',
	slate: '/product/paid-slate/',
	mug: '/product/plain-mug/',
	linen: '/product/stocked-linen/',
	oak: '/product/sample-oak/',
};

/** The add-to-cart value of the main (full-price) form of a simple product. */
async function productId( page, url ) {
	await page.goto( url );
	return String( await page.locator( 'form.cart [name="add-to-cart"]' ).first().getAttribute( 'value' ) );
}

/** POST a sample request exactly as the sample form would. */
async function postSample( page, url, extra = {} ) {
	const id = await productId( page, url );
	await page.request.post( url, { form: { 'add-to-cart': id, quantity: '1', smpshelf_sample: '1', ...extra } } );
}

/** Variation ids (and their attributes) of the Sample Oak product. */
async function oakVariations( page ) {
	await page.goto( URL.oak );
	const json = await page.locator( 'form.variations_form' ).getAttribute( 'data-product_variations' );
	const list = JSON.parse( json || '[]' );
	const out = {};
	for ( const v of list ) {
		out[ v.attributes.attribute_finish ] = { id: String( v.variation_id ), attributes: v.attributes };
	}
	return out;
}

async function oakParentId( page ) {
	await page.goto( URL.oak );
	return String( await page.locator( 'form.variations_form [name="add-to-cart"]' ).first().getAttribute( 'value' ) );
}

async function cartRows( page ) {
	await page.goto( '/classic-cart/' );
	return page.locator( 'tr.cart_item' );
}

test.describe( 'Product page', () => {
	test( 'offers a sample only where the product is switched on', async ( { page } ) => {
		await page.goto( URL.tile );
		await expect( page.locator( 'form.smpshelf-sample-form .smpshelf-button' ) ).toHaveText( 'Request a sample' );
		await page.goto( URL.slate );
		await expect( page.locator( 'form.smpshelf-sample-form .smpshelf-button' ) ).toContainText( 'Request a sample (£2.50)' );
		await page.goto( URL.mug );
		await expect( page.locator( 'form.smpshelf-sample-form' ) ).toHaveCount( 0 );
	} );

	test( 'the sample form is separate from the add-to-cart form', async ( { page } ) => {
		await page.goto( URL.tile );
		await expect( page.locator( 'form.cart form' ) ).toHaveCount( 0 );
		await expect( page.locator( 'form.cart .smpshelf-button' ) ).toHaveCount( 0 );
	} );

	test( 'a variable product needs an option before the sample button works', async ( { page } ) => {
		await page.goto( URL.oak );
		const button = page.locator( 'form.smpshelf-sample-form .smpshelf-button' );
		await expect( button ).toBeDisabled();
		await page.locator( 'select[name="attribute_finish"]' ).selectOption( 'Grey' );
		await expect( button ).toBeEnabled();
		await button.click();
		const rows = await cartRows( page );
		await expect( rows ).toHaveCount( 1 );
		await expect( rows.first() ).toContainText( 'Grey' );
		await expect( rows.first().locator( '.variation-Type' ) ).toHaveText( /Sample/ );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£0.00' );
	} );
} );

test.describe( 'Cart rules (classic cart)', () => {
	test( 'a free sample is its own line, quantity 1, labelled Sample, at £0.00', async ( { page } ) => {
		await page.goto( URL.tile );
		await page.locator( 'form.smpshelf-sample-form .smpshelf-button' ).click();
		const rows = await cartRows( page );
		await expect( rows ).toHaveCount( 1 );
		await expect( rows.first().locator( '.variation-Type' ) ).toHaveText( /Sample/ );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£0.00' );
		await expect( rows.first().locator( 'input[type="number"]' ) ).toHaveCount( 0 ); // No quantity box.
	} );

	test( 'a paid sample uses the sample price, not the product price', async ( { page } ) => {
		await postSample( page, URL.slate );
		const rows = await cartRows( page );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£2.50' );
	} );

	test( 'the same sample twice is refused', async ( { page } ) => {
		await postSample( page, URL.tile );
		await postSample( page, URL.tile );
		await expect( await cartRows( page ) ).toHaveCount( 1 );
	} );

	test( 'a sample and the full product sit side by side at their own prices', async ( { page } ) => {
		const id = await productId( page, URL.tile );
		await page.request.post( URL.tile, { form: { 'add-to-cart': id, quantity: '2' } } );
		await postSample( page, URL.tile );
		const rows = await cartRows( page );
		await expect( rows ).toHaveCount( 2 );
		await expect( page.locator( 'tr.cart_item .product-subtotal', { hasText: '£60.00' } ) ).toHaveCount( 1 );
		await expect( page.locator( 'tr.cart_item .product-subtotal', { hasText: '£0.00' } ) ).toHaveCount( 1 );
	} );

	test( 'a tampered quantity is ignored: a sample is always one', async ( { page } ) => {
		await postSample( page, URL.tile, { quantity: '5' } );
		const rows = await cartRows( page );
		await expect( rows ).toHaveCount( 1 );
		await expect( rows.first().locator( '.product-quantity' ) ).toContainText( '1' );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£0.00' );
	} );

	test( 'a sample flag on a product that has samples off adds nothing', async ( { page } ) => {
		await postSample( page, URL.mug );
		await expect( await cartRows( page ) ).toHaveCount( 0 );
	} );

	test( 'a sample request for a product with samples off is refused, and a normal add stays full price', async ( { page } ) => {
		const id = await productId( page, URL.mug );
		await page.request.post( URL.mug + '?smpshelf_sample=1', { form: { 'add-to-cart': id, quantity: '1' } } );
		await expect( await cartRows( page ) ).toHaveCount( 0 ); // Refused: samples are off for the mug.
		await page.request.post( URL.tile, { form: { 'add-to-cart': await productId( page, URL.tile ), quantity: '1' } } );
		const rows = await cartRows( page );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£30.00' ); // A normal add stays full price.
	} );

	test( 'at most the configured number of samples per order (3)', async ( { page } ) => {
		await postSample( page, URL.tile );
		await postSample( page, URL.slate );
		const variations = await oakVariations( page );
		const parent = await oakParentId( page );
		const natural = variations.Natural;
		await page.request.post( URL.oak, { form: { 'add-to-cart': parent, variation_id: natural.id, ...natural.attributes, quantity: '1', smpshelf_sample: '1' } } );
		await expect( await cartRows( page ) ).toHaveCount( 3 );
		const grey = variations.Grey;
		await page.request.post( URL.oak, { form: { 'add-to-cart': parent, variation_id: grey.id, ...grey.attributes, quantity: '1', smpshelf_sample: '1' } } );
		await expect( await cartRows( page ) ).toHaveCount( 3 ); // The fourth is refused.
	} );

	test( 'two finishes of a variable product are two separate samples', async ( { page } ) => {
		const variations = await oakVariations( page );
		const parent = await oakParentId( page );
		for ( const finish of [ 'Natural', 'Grey' ] ) {
			const v = variations[ finish ];
			await page.request.post( URL.oak, { form: { 'add-to-cart': parent, variation_id: v.id, ...v.attributes, quantity: '1', smpshelf_sample: '1' } } );
		}
		await expect( await cartRows( page ) ).toHaveCount( 2 );
	} );

	test( 'a sample sits beside a normal order of the same limited-stock product', async ( { page } ) => {
		const id = await productId( page, URL.linen );
		await page.request.post( URL.linen, { form: { 'add-to-cart': id, quantity: '2' } } ); // 2 of the 3 in stock.
		await postSample( page, URL.linen );
		await page.goto( '/classic-cart/' );
		await expect( page.locator( 'tr.cart_item' ) ).toHaveCount( 2 );
		await expect( page.locator( '.woocommerce-error' ) ).toHaveCount( 0 );
	} );

	test( 'the normal add-to-cart button still adds the full product (quantity 3) beside a sample form', async ( { page } ) => {
		await page.goto( URL.tile );
		await page.locator( 'form.cart input.qty' ).fill( '3' );
		await page.locator( '.single_add_to_cart_button' ).click();
		const rows = await cartRows( page );
		await expect( rows ).toHaveCount( 1 );
		await expect( rows.first().locator( '.product-quantity input.qty' ) ).toHaveValue( '3' );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£90.00' );
		await expect( rows.first().locator( '.variation-Type' ) ).toHaveCount( 0 ); // Not a sample.
	} );

	test( 'the normal add-to-cart button still adds the chosen full-price variation', async ( { page } ) => {
		await page.goto( URL.oak );
		await page.locator( 'select[name="attribute_finish"]' ).selectOption( 'Grey' );
		await page.locator( '.single_add_to_cart_button' ).click();
		const rows = await cartRows( page );
		await expect( rows ).toHaveCount( 1 );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£45.00' );
		await expect( rows.first().locator( '.variation-Type' ) ).toHaveCount( 0 );
	} );

	test( 'a quantity change to more than one is refused', async ( { page } ) => {
		await postSample( page, URL.tile );
		await page.goto( '/classic-cart/' );
		const name = await page.locator( 'tr.cart_item input[type="hidden"][name^="cart["]' ).first().getAttribute( 'name' );
		await page.evaluate( ( n ) => {
			const input = document.querySelector( `input[name="${ n }"]` );
			if ( input ) {
				input.value = '3';
			}
		}, name );
		await page.locator( 'button[name="update_cart"]' ).evaluate( ( b ) => {
			b.removeAttribute( 'disabled' );
			b.removeAttribute( 'aria-disabled' );
		} );
		await page.locator( 'button[name="update_cart"]' ).click();
		await expect( page.locator( '.woocommerce-error' ) ).toContainText( 'Only one of each sample' );
		const rows = page.locator( 'tr.cart_item' );
		await expect( rows ).toHaveCount( 1 );
		await expect( rows.first().locator( '.product-quantity' ) ).toContainText( '1' );
		await expect( rows.first().locator( '.product-subtotal' ) ).toContainText( '£0.00' );
	} );
} );

test.describe( 'Block cart', () => {
	test( 'shows the sample line labelled Sample at £0.00 and keeps it at quantity 1', async ( { page } ) => {
		await postSample( page, URL.tile );
		await page.goto( '/cart/' );
		const items = page.locator( '.wc-block-cart-items' );
		await expect( items ).toContainText( 'Sample Tile' );
		await expect( items.locator( '.wc-block-components-product-details' ) ).toContainText( 'Sample' );
		await expect( items ).toContainText( '£0.00' );
		// If the theme still shows a "+" button, using it must not raise the quantity.
		const plus = items.locator( '.wc-block-components-quantity-selector__button--plus' );
		if ( ( await plus.count() ) && ( await plus.first().isEnabled() ) ) {
			await plus.first().click();
			await page.waitForTimeout( 1500 );
		}
		await page.reload();
		await expect( page.locator( '.wc-block-cart-items' ) ).toContainText( '£0.00' );
		const qty = page.locator( '.wc-block-cart-items input.wc-block-components-quantity-selector__input' );
		if ( await qty.count() ) {
			await expect( qty.first() ).toHaveValue( '1' );
		}
	} );
} );

test.describe( 'Classic checkout', () => {
	test( 'an order with a sample is placed, the sample is recorded and no stock is taken', async ( { page } ) => {
		await postSample( page, URL.linen );
		await page.goto( '/classic-checkout/' );
		await page.locator( '#billing_first_name' ).fill( 'Sam' );
		await page.locator( '#billing_last_name' ).fill( 'Ple' );
		await page.locator( '#billing_address_1' ).fill( '1 Test Street' );
		await page.locator( '#billing_city' ).fill( 'London' );
		await page.locator( '#billing_postcode' ).fill( 'SW1A 1AA' );
		await page.locator( '#billing_phone' ).fill( '07700900123' );
		await page.locator( '#billing_email' ).fill( 'sample@example.com' );
		await page.locator( '#payment_method_cod' ).check();
		await page.locator( '#place_order' ).click();
		await expect( page.locator( '.woocommerce-table--order-details' ) ).toBeVisible( { timeout: 30000 } );
		await expect( page.locator( '.woocommerce-table--order-details .wc-item-meta' ) ).toContainText( 'Sample' );
		await page.goto( URL.linen );
		await expect( page.locator( '.stock' ).first() ).toContainText( '3 in stock' ); // The sample took none.
	} );
} );

test.describe( 'Settings screen', () => {
	test( 'WooCommerce > Settings > Sample Shelf loads and saves', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=smpshelf' );
		await expect( page.locator( '#smpshelf_max_per_order' ) ).toHaveValue( '3' );
		await page.locator( '#smpshelf_price' ).fill( '1,5' );
		await page.locator( '#smpshelf_price' ).blur();
		await page.locator( 'button[name="save"]' ).click();
		await expect( page.locator( '#smpshelf_price' ) ).toHaveValue( '1.50' );
		// Put the free default back for any later test.
		await page.locator( '#smpshelf_price' ).fill( '' );
		await page.locator( '#smpshelf_price' ).blur();
		await page.locator( 'button[name="save"]' ).click();
		await expect( page.locator( '#smpshelf_price' ) ).toHaveValue( '' );
	} );
} );
