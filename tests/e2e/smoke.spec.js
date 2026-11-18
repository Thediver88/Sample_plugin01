// @ts-check
const { test, expect } = require( '@playwright/test' );

test( 'the product page loads with the plugin active', async ( { page } ) => {
	const response = await page.goto( '/product/sample-tile/' );
	expect( response && response.status() ).toBe( 200 );
	await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
	await expect( page.getByRole( 'heading', { name: 'Sample Tile' } ).first() ).toBeVisible();
} );
