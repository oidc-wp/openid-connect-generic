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
	 * @var OpenID_Connect_Generic_Option_Logger $logger
	 */
	private  $logger;

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

		// Initialize a logger instance with some testable defaults.
		$this->logger = new OpenID_Connect_Generic_Option_Logger( null, true, 2 );

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		// Make sure we cleanup any test log entries.
		$this->logger->clear_logs();

		// Clean-up the logger instance.
		unset( $this->logger );

		parent::tearDown();

	}

	/**
	 * Test logger has an OpenID_Connect_Generic_Option_Logger_Test $default_message_type property.
	 *
	 * @group LoggerTests
	 */
	public function test_logger_has_default_message_type_propery() {

		$this->assertObjectHasProperty( 'default_message_type', $this->logger, 'OpenID_Connect_Generic_Option_Logger has a $default_message_type property.' );

	}

	/**
	 * Test logger has an OpenID_Connect_Generic_Option_Logger_Test $log_limit property.
	 *
	 * @group LoggerTests
	 */
	public function test_logger_has_log_limit_propery() {

		$this->assertObjectHasProperty( 'log_limit', $this->logger, 'OpenID_Connect_Generic_Option_Logger has a $log_limit property.' );

	}

	/**
	 * Test logger has an OpenID_Connect_Generic_Option_Logger_Test $logging_enabled property.
	 *
	 * @group LoggerTests
	 */
	public function test_logger_has_logging_enabled_propery() {

		$this->assertObjectHasProperty( 'logging_enabled', $this->logger, 'OpenID_Connect_Generic_Option_Logger has a $logging_enabled property.' );

	}

	/**
	 * Test logger has an OpenID_Connect_Generic_Option_Logger_Test $logs property.
	 *
	 * @group LoggerTests
	 */
	public function test_logger_has_logs_propery() {

		$this->assertObjectHasProperty( 'logs', $this->logger, 'OpenID_Connect_Generic_Option_Logger_Test has a $logs property.' );

	}

	/**
	 * Test plugin logger get_option_name() method.
	 *
	 * @group LoggerTests
	 */
	public function test_plugin_logger_get_option_name() {

		$expected_option_name = 'openid-connect-generic-logs';
		$this->assertEquals( $expected_option_name, $this->logger->get_option_name(), 'OpenID_Connect_Generic_Option_Logger has a correct OPTION_NAME.' );

	}

	/**
	 * Test plugin logger upkeep_logs() method.
	 *
	 * @group LoggerTests
	 */
	public function test_plugin_logger_upkeep_logs() {

		$type            = 'test';
		$time            = time();
		$user_ID         = 0;
		$request_uri     = 'https://domain.tld';
		$data            = 'test';
		$processing_time = 100;

		// Construct a test message.
		$message = array(
			'type'            => $type,
			'time'            => $time,
			'user_ID'         => $user_ID,
			'uri'             => $request_uri,
			'data'            => $data,
			'processing_time' => $processing_time,
		);

		$this->logger->log( $type, $data, $processing_time, $time, $user_ID, $request_uri );
		$logs = $this->logger->get_logs();

		$this->assertIsArray( $logs, 'OpenID_Connect_Generic_Option_Logger logs is an array.' );
		$this->assertNonEmptyMultidimensionalArray( $logs, 'OpenID_Connect_Generic_Option_Logger logs is a populated multidimensional array of log entries.' );
		$this->assertEqualSets( $message, $logs[0], 'OpenID_Connect_Generic_Option_Logger first log entry message matches the created test log message.' );
		$this->assertArrayHasKey( 'type', $logs[0], 'OpenID_Connect_Generic_Option_Logger log message has a "type" attribute.' );
		$this->assertArrayHasKey( 'time', $logs[0], 'OpenID_Connect_Generic_Option_Logger log message has a "time" attribute.' );
		$this->assertArrayHasKey( 'user_ID', $logs[0], 'OpenID_Connect_Generic_Option_Logger log message has a "user_ID" attribute.' );
		$this->assertArrayHasKey( 'uri', $logs[0], 'OpenID_Connect_Generic_Option_Logger log message has a "uri" attribute.' );
		$this->assertArrayHasKey( 'data', $logs[0], 'OpenID_Connect_Generic_Option_Logger log message has a "data" attribute.' );
		$this->assertArrayHasKey( 'processing_time', $logs[0], 'OpenID_Connect_Generic_Option_Logger log message has a "processing_time" attribute.' );

	}

}
