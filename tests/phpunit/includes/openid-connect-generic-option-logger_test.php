<?php
/**
 * Class OpenID_Connect_Generic_Option_Logger_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin logging class test case.
 */
class OpenID_Connect_Generic_Option_Logger_Test extends WP_UnitTestCase {

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
	 * Test plugin logger get_option_name() method.
	 *
	 * @group LoggerTests
	 */
	public function test_plugin_logger_get_option_name() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

}
