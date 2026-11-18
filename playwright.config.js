// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: false, // One shared test store.
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	forbidOnly: !! process.env.CI,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		// CI uses the runner's preinstalled Google Chrome, so no browser download or apt packages
		// are needed. Locally, run `npx playwright install chromium` once.
		channel: process.env.CI ? 'chrome' : undefined,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ], channel: process.env.CI ? 'chrome' : undefined } },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ], channel: process.env.CI ? 'chrome' : undefined }, grep: /@ui/ },
	],
} );
