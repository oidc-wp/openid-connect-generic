<?php
/**
 * Class OpenID_Connect_Generic_Option_Settings_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin WordPress options handling class test case.
 */
class OpenID_Connect_Generic_Option_Settings_Test extends WP_UnitTestCase {

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		parent::tearDown();

	}

	/**
	 * Test plugin option settings get_option_name() method.
	 *
	 * @group ClientTests
	 */
	public function test_plugin_option_settings_get_option_name() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	/**
	 * Test that OIDC_WOOCOMMERCE_LOGIN overrides the saved WooCommerce Login value and isn't saved.
	 *
	 * @group OptionSettingsTests
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_woocommerce_login_constant_overrides_saved_value() {
		update_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME, array( 'woocommerce_login' => 'end' ) );
		define( 'OIDC_WOOCOMMERCE_LOGIN', 'auto' );

		$settings = new OpenID_Connect_Generic_Option_Settings( array( 'woocommerce_login' => '' ) );
		$this->assertSame( 'auto', $settings->woocommerce_login );

		$settings->save();
		$this->assertArrayNotHasKey( 'woocommerce_login', get_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME ) );
	}

}
