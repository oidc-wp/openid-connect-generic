<?php
/**
 * OpenID Connect Generic Client
 *
 * This plugin provides the ability to authenticate users with Identity
 * Providers using the OpenID Connect OAuth2 API with Authorization Code Flow.
 *
 * @package   OpenID_Connect_Generic
 * @category  General
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/daggerhart
 *
 * @wordpress-plugin
 * Plugin Name:       OpenID Connect Generic
 * Plugin URI:        https://github.com/daggerhart/openid-connect-generic
 * Description:       Connect to an OpenID Connect generic client using Authorization Code Flow.
 * Version:           3.8.1
 * Author:            daggerhart
 * Author URI:        http://www.daggerhart.com
 * Text Domain:       daggerhart-openid-connect-generic
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/daggerhart/openid-connect-generic
 */

/*
Notes
  Spec Doc - http://openid.net/specs/openid-connect-basic-1_0-32.html

  Filters
  - openid-connect-generic-alter-request       - 3 args: request array, plugin settings, specific request op
  - openid-connect-generic-settings-fields     - modify the fields provided on the settings page
  - openid-connect-generic-login-button-text   - modify the login button text
  - openid-connect-generic-cookie-redirect-url - modify the redirect url stored as a cookie
  - openid-connect-generic-user-login-test     - (bool) should the user be logged in based on their claim
  - openid-connect-generic-user-creation-test  - (bool) should the user be created based on their claim
  - openid-connect-generic-auth-url            - modify the authentication url
  - openid-connect-generic-alter-user-claim    - modify the user_claim before a new user is created
  - openid-connect-generic-alter-user-data     - modify user data before a new user is created
  - openid-connect-modify-token-response-before-validation - modify the token response before validation
  - openid-connect-modify-id-token-claim-before-validation - modify the token claim before validation

  Actions
  - openid-connect-generic-user-create        - 2 args: fires when a new user is created by this plugin
  - openid-connect-generic-user-update        - 1 arg: user ID, fires when user is updated by this plugin
  - openid-connect-generic-update-user-using-current-claim - 2 args: fires every time an existing user logs
  - openid-connect-generic-redirect-user-back - 2 args: $redirect_url, $user. Allows interruption of redirect during login.
  - openid-connect-generic-user-logged-in     - 1 arg: $user, fires when user is logged in.
  - openid-connect-generic-cron-daily         - daily cron action
  - openid-connect-generic-state-not-found    - the given state does not exist in the database, regardless of its expiration.
  - openid-connect-generic-state-expired      - the given state exists, but expired before this login attempt.

  User Meta
  - openid-connect-generic-subject-identity    - the identity of the user provided by the idp
  - openid-connect-generic-last-id-token-claim - the user's most recent id_token claim, decoded
  - openid-connect-generic-last-user-claim     - the user's most recent user_claim
  - openid-connect-generic-last-token-response - the user's most recent token response

  Options
  - openid_connect_generic_settings     - plugin settings
  - openid-connect-generic-valid-states - locally stored generated states
*/


/**
 * OpenID_Connect_Generic class.
 *
 * Defines plugin initialization functionality.
 *
 * @package OpenID_Connect_Generic
 * @category  General
 */
class OpenID_Connect_Generic {

	/**
	 * Plugin version.
	 *
	 * @var
	 */
	const VERSION = '3.8.1';

	/**
	 * Plugin settings.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * Plugin logs.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * Openid Connect Generic client
	 *
	 * @var OpenID_Connect_Generic_Client
	 */
	private $client;

	/**
	 * Client wrapper.
	 *
	 * @var OpenID_Connect_Generic_Client_Wrapper
	 */
	private $client_wrapper;

	/**
	 * Setup the plugin
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The settings object.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   The loggin object.
	 *
	 * @return void
	 */
	function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * WordPress Hook 'init'.
	 *
	 * @return void
	 */
	function init() {

		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}

		$state_time_limit = 180;
		if ( $this->settings->state_time_limit ) {
			$state_time_limit = intval( $this->settings->state_time_limit );
		}

		$this->client = new OpenID_Connect_Generic_Client(
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->endpoint_login,
			$this->settings->endpoint_userinfo,
			$this->settings->endpoint_token,
			$redirect_uri,
			$state_time_limit,
			$this->logger
		);

		$this->client_wrapper = OpenID_Connect_Generic_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		OpenID_Connect_Generic_Login_Form::register( $this->settings, $this->client_wrapper );

		// Add a shortcode to get the auth URL.
		add_shortcode( 'openid_connect_generic_auth_url', array( $this->client_wrapper, 'get_authentication_url' ) );

		// Add actions to our scheduled cron jobs.
		add_action( 'openid-connect-generic-cron-daily', array( $this, 'cron_states_garbage_collection' ) );

		$this->upgrade();

		if ( is_admin() ) {
			OpenID_Connect_Generic_Settings_Page::register( $this->settings, $this->logger );
		}
	}

	/**
	 * Handle plugin upgrades
	 *
	 * @return void
	 */
	function upgrade() {
		$last_version = get_option( 'openid-connect-generic-plugin-version', 0 );
		$settings = $this->settings;

		if ( version_compare( self::VERSION, $last_version, '>' ) ) {
			// An upgrade is required.
			self::setup_cron_jobs();

			// @todo move this to another file for upgrade scripts
			if ( isset( $settings->ep_login ) ) {
				$settings->endpoint_login = $settings->ep_login;
				$settings->endpoint_token = $settings->ep_token;
				$settings->endpoint_userinfo = $settings->ep_userinfo;

				unset( $settings->ep_login, $settings->ep_token, $settings->ep_userinfo );
				$settings->save();
			}

			// Update the stored version number.
			update_option( 'openid-connect-generic-plugin-version', self::VERSION );
		}
	}

	/**
	 * Expire state transients by attempting to access them and allowing the
	 * transient's own mechanisms to delete any that have expired.
	 *
	 * @return void
	 */
	function cron_states_garbage_collection() {
		global $wpdb;
		$states = $wpdb->get_col( "SELECT `option_name` FROM {$wpdb->options} WHERE `option_name` LIKE '_transient_openid-connect-generic-state--%'" );

		if ( ! empty( $states ) ) {
			foreach ( $states as $state ) {
				$transient = str_replace( '_transient_', '', $state );
				get_transient( $transient );
			}
		}
	}

	/**
	 * Ensure cron jobs are added to the schedule.
	 *
	 * @return void
	 */
	static public function setup_cron_jobs() {
		if ( ! wp_next_scheduled( 'openid-connect-generic-cron-daily' ) ) {
			wp_schedule_event( time(), 'daily', 'openid-connect-generic-cron-daily' );
		}
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	static public function activation() {
		self::setup_cron_jobs();
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	static public function deactivation() {
		wp_clear_scheduled_hook( 'openid-connect-generic-cron-daily' );
	}

	/**
	 * Simple autoloader.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	static public function autoload( $class ) {
		$prefix = 'OpenID_Connect_Generic_';

		if ( stripos( $class, $prefix ) !== 0 ) {
			return;
		}

		$filename = $class . '.php';

		// Internal files are all lowercase and use dashes in filenames.
		if ( false === strpos( $filename, '\\' ) ) {
			$filename = strtolower( str_replace( '_', '-', $filename ) );
		} else {
			$filename  = str_replace( '\\', DIRECTORY_SEPARATOR, $filename );
		}

		$filepath = dirname( __FILE__ ) . '/includes/' . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}

	/**
	 * Instantiate the plugin and hook into WordPress.
	 *
	 * @return void
	 */
	static public function bootstrap() {
		/**
		 * This is a documented valid call for spl_autoload_register.
		 *
		 * @link https://www.php.net/manual/en/function.spl-autoload-register.php#71155
		 */
		spl_autoload_register( array( 'OpenID_Connect_Generic', 'autoload' ) );

		$settings = new OpenID_Connect_Generic_Option_Settings(
			'openid_connect_generic_settings',
			// Default settings values.
			array(
				// OAuth client settings.
				'login_type'           => 'button',
				'client_id'            => defined( 'OIDC_CLIENT_ID' ) ? OIDC_CLIENT_ID : '',
				'client_secret'        => defined( 'OIDC_CLIENT_SECRET' ) ? OIDC_CLIENT_SECRET : '',
				'scope'                => '',
				'endpoint_login'       => defined( 'OIDC_ENDPOINT_LOGIN_URL' ) ? OIDC_ENDPOINT_LOGIN_URL : '',
				'endpoint_userinfo'    => defined( 'OIDC_ENDPOINT_USERINFO_URL' ) ? OIDC_ENDPOINT_USERINFO_URL : '',
				'endpoint_token'       => defined( 'OIDC_ENDPOINT_TOKEN_URL' ) ? OIDC_ENDPOINT_TOKEN_URL : '',
				'endpoint_end_session' => defined( 'OIDC_ENDPOINT_LOGOUT_URL' ) ? OIDC_ENDPOINT_LOGOUT_URL : '',

				// Non-standard settings.
				'no_sslverify'    => 0,
				'http_request_timeout' => 5,
				'identity_key'    => 'preferred_username',
				'nickname_key'    => 'preferred_username',
				'email_format'       => '{email}',
				'displayname_format' => '',
				'identify_with_username' => false,

				// Plugin settings.
				'enforce_privacy' => 0,
				'alternate_redirect_uri' => 0,
				'token_refresh_enable' => 1,
				'link_existing_users' => 0,
				'create_if_does_not_exist' => 1,
				'redirect_user_back' => 0,
				'redirect_on_logout' => 1,
				'enable_logging'  => 0,
				'log_limit'       => 1000,
			)
		);

		$logger = new OpenID_Connect_Generic_Option_Logger( 'openid-connect-generic-logs', 'error', $settings->enable_logging, $settings->log_limit );

		$plugin = new self( $settings, $logger );

		add_action( 'init', array( $plugin, 'init' ) );
		OpenID_Connect_Generic_Privacy::register( $settings );
	}
}

OpenID_Connect_Generic::bootstrap();

register_activation_hook( __FILE__, array( 'OpenID_Connect_Generic', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'OpenID_Connect_Generic', 'deactivation' ) );
