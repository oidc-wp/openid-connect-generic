<?php
/**
 * Class OpenID_Connect_Generic_Settings_Page_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin admin settings page class test case.
 */
class OpenID_Connect_Generic_Settings_Page_Test extends WP_UnitTestCase {

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
	 * Test plugin admin settings do_text_field() method.
	 *
	 * @group SettingsPageTests
	 */
	public function test_plugin_settings_page_do_text_field() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

}
