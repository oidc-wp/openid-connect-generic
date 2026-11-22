<?php
/**
 * Class OpenID_Connect_Generic_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin initialization functionality class test case.
 */
class OpenID_Connect_Generic_Test extends WP_UnitTestCase {

	/**
	 * @var OpenID_Connect_Generic $oidc_plugin
	 */
	private $oidc_plugin = null;

	/**
	 * @var OpenID_Connect_Generic_Option_Settings $oidc_plugin_settings
	 */
	private $oidc_plugin_settings = null;

	/**
	 * @var int
	 */
	private $state_time_limit_default = 9999;

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();
		$this->oidc_plugin = OpenID_Connect_Generic::instance();
		$this->oidc_plugin_settings = new OpenID_Connect_Generic_Option_Settings(
			// Default settings values.
			array(
				'state_time_limit' => $this->state_time_limit_default,
			)
		);

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		unset( $this->oidc_plugin );
		parent::tearDown();

	}

	/**
	 * Test plugin has an OpenID_Connect_Generic $_instance property.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_has_instance_propery() {

		$this->assertObjectHasProperty( '_instance', $this->oidc_plugin, 'OpenID_Connect_Generic has a $_instance property.' );

	}

	/**
	 * Test plugin has an OpenID_Connect_Generic_Option_Settings $settings property.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_has_settings_propery() {

		$this->assertObjectHasProperty( 'settings', $this->oidc_plugin, 'OpenID_Connect_Generic has a $settings property.' );

	}

	/**
	 * Test plugin has an OpenID_Connect_Generic_Option_Logger $logger property.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_has_logger_propery() {

		$this->assertObjectHasProperty( 'logger', $this->oidc_plugin, 'OpenID_Connect_Generic has a $logger property.' );

	}

	/**
	 * Test plugin has an OpenID_Connect_Generic_Client $client property.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_has_client_propery() {

		$this->assertObjectHasProperty( 'client', $this->oidc_plugin, 'OpenID_Connect_Generic has a $client property.' );

	}

	/**
	 * Test plugin has an OpenID_Connect_Generic_Client_Wrapper $client_wrapper property.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_has_client_wrapper_propery() {

		$this->assertObjectHasProperty( 'settings', $this->oidc_plugin, 'OpenID_Connect_Generic has a $settings property.' );
		$this->assertInstanceOf( 'OpenID_Connect_Generic_Client_Wrapper', $this->oidc_plugin->client_wrapper, 'Plugin $client_wrapper property is an OpenID_Connect_Generic_Client_Wrapper instance.' );

	}

	/**
	 * Test plugin get_redirect_uri() method.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_redirect_uri() {

		$this->assertInstanceOf( 'OpenID_Connect_Generic', $this->oidc_plugin, 'Instance is of type OpenID_Connect_Generic.' );

	}

	/**
	 * Test the redirect URI with and without the alternate redirect URI setting.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_redirect_uri_follows_home_url() {
		// WordPress in a subdirectory, as on Bedrock installs.
		update_option( 'siteurl', home_url( '/wp' ) );

		$settings = new OpenID_Connect_Generic_Option_Settings();

		$settings->alternate_redirect_uri = 0;
		$this->assertSame( admin_url( 'admin-ajax.php?action=openid-connect-authorize' ), $this->oidc_plugin->get_redirect_uri( $settings ) );

		// The alternate route is a rewrite rule, which WordPress matches relative to the home URL.
		$settings->alternate_redirect_uri = 1;
		$this->assertSame( home_url( '/openid-connect-authorize' ), $this->oidc_plugin->get_redirect_uri( $settings ) );
		$this->assertStringNotContainsString( '/wp/', $this->oidc_plugin->get_redirect_uri( $settings ) );
	}

	/**
	 * Test plugin instance() method.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_returns_valid_instance() {

		$this->assertInstanceOf( 'OpenID_Connect_Generic', $this->oidc_plugin, 'Instance is of type OpenID_Connect_Generic.' );

	}

	/**
	 * Test plugin settings `state_time_limit` has a default.
	 *
	 * @group PluginTests
	 */
	public function test_plugin_settings_has_state_time_limit_default() {

		$this->assertIsInt( $this->oidc_plugin_settings->state_time_limit, "The OpenID_Connect_Generic_Option_Settings `state_time_limit` value '{$this->oidc_plugin_settings->state_time_limit}' is not and integer or is not set." );
		$this->assertEquals( $this->state_time_limit_default, $this->oidc_plugin_settings->state_time_limit, "The OpenID_Connect_Generic_Option_Settings `state_time_limit` default value '{$this->oidc_plugin_settings->state_time_limit}' is not '{$this->state_time_limit_default}'." );

	}

	/**
	 * Run upgrade() against the given stored settings and previous version.
	 *
	 * @param array<string, mixed> $stored_settings Settings saved in the database.
	 * @param string|null          $last_version    Previously installed version, or null for a new install.
	 *
	 * @return OpenID_Connect_Generic_Option_Settings
	 */
	private function run_upgrade( array $stored_settings, ?string $last_version ): OpenID_Connect_Generic_Option_Settings {
		update_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME, $stored_settings );
		if ( null === $last_version ) {
			delete_option( 'openid-connect-generic-plugin-version' );
		} else {
			update_option( 'openid-connect-generic-plugin-version', $last_version );
		}
		delete_option( OpenID_Connect_Generic::NOTICE_LINK_UNVERIFIED_OPTION );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			array(
				'link_existing_users'   => 0,
				'link_unverified_users' => 0,
				'enable_pkce'           => 1,
			)
		);

		$property = new ReflectionProperty( OpenID_Connect_Generic::class, 'settings' );
		$property->setAccessible( true );
		$original = $property->getValue( $this->oidc_plugin );
		$property->setValue( $this->oidc_plugin, $settings );

		try {
			$this->oidc_plugin->upgrade();
		} finally {
			$property->setValue( $this->oidc_plugin, $original );
		}

		return new OpenID_Connect_Generic_Option_Settings();
	}

	/**
	 * Upgrading a site that links existing users keeps linking working and flags the notice.
	 *
	 * @group PluginTests
	 */
	public function test_upgrade_enables_link_unverified_users_for_sites_linking_users() {

		$saved = $this->run_upgrade( array( 'link_existing_users' => 1 ), '3.11.2' );

		$this->assertEquals( 1, $saved->link_unverified_users );
		$this->assertEquals( 1, get_option( OpenID_Connect_Generic::NOTICE_LINK_UNVERIFIED_OPTION ) );

	}

	/**
	 * Upgrading a site that does not link existing users leaves the new setting off.
	 *
	 * @group PluginTests
	 */
	public function test_upgrade_leaves_link_unverified_users_off_when_not_linking() {

		$saved = $this->run_upgrade( array( 'link_existing_users' => 0 ), '3.11.2' );

		$this->assertEmpty( $saved->link_unverified_users );
		$this->assertFalse( get_option( OpenID_Connect_Generic::NOTICE_LINK_UNVERIFIED_OPTION ) );

	}

	/**
	 * A new install never enables linking without a verified email.
	 *
	 * @group PluginTests
	 */
	public function test_new_install_leaves_link_unverified_users_off() {

		$saved = $this->run_upgrade( array( 'link_existing_users' => 1 ), null );

		$this->assertEmpty( $saved->link_unverified_users );
		$this->assertFalse( get_option( OpenID_Connect_Generic::NOTICE_LINK_UNVERIFIED_OPTION ) );

	}

	/**
	 * Existing sites keep PKCE off after upgrading; new installs get it on.
	 *
	 * @group PluginTests
	 */
	public function test_upgrade_leaves_pkce_off_for_existing_sites() {

		$this->assertEmpty( $this->run_upgrade( array( 'client_id' => 'existing' ), '3.11.2' )->enable_pkce, 'An upgraded site should not start sending PKCE.' );

		$this->run_upgrade( array(), null );
		$settings = new OpenID_Connect_Generic_Option_Settings( array( 'enable_pkce' => 1 ) );
		$this->assertNotEmpty( $settings->enable_pkce, 'A new install should use PKCE by default.' );
		$this->assertArrayNotHasKey( 'enable_pkce', (array) get_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME ), 'A new install should not store an explicit PKCE value.' );

	}

	/**
	 * The notice clears itself once the setting is turned off.
	 *
	 * @group PluginTests
	 */
	public function test_link_unverified_notice_clears_when_setting_off() {

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( OpenID_Connect_Generic::NOTICE_LINK_UNVERIFIED_OPTION, 1 );

		$property = new ReflectionProperty( OpenID_Connect_Generic::class, 'settings' );
		$property->setAccessible( true );
		$original = $property->getValue( $this->oidc_plugin );
		$property->setValue( $this->oidc_plugin, new OpenID_Connect_Generic_Option_Settings( array( 'link_unverified_users' => 0 ) ) );

		try {
			ob_start();
			$this->oidc_plugin->admin_notice_link_unverified_users();
			$output = ob_get_clean();
		} finally {
			$property->setValue( $this->oidc_plugin, $original );
		}

		$this->assertSame( '', $output );
		$this->assertFalse( get_option( OpenID_Connect_Generic::NOTICE_LINK_UNVERIFIED_OPTION ) );

	}

	/**
	 * Test the missing dependencies notice is shown only to users who can activate plugins.
	 *
	 * @group PluginTests
	 */
	public function test_admin_notice_missing_dependencies() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		ob_start();
		OpenID_Connect_Generic::admin_notice_missing_dependencies();
		$this->assertSame( '', ob_get_clean() );

		// On multisite, only super admins can activate plugins.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}
		wp_set_current_user( $admin_id );
		ob_start();
		OpenID_Connect_Generic::admin_notice_missing_dependencies();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'dependencies are missing', $output );
		$this->assertStringContainsString( '<code>composer install --no-dev</code>', $output );
	}
}
