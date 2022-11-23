<?php
/**
 * Hellō Login
 *
 * This plugin provides the ability to authenticate users with Identity
 * Providers using the OpenID Connect OAuth2 API with Authorization Code Flow.
 *
 * @package   Hello_Login
 * @category  General
 * @author    Marius Scurtescu <marius.scurtescu@hello.coop>
 * @copyright 2022 Hello Identity Co-op
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://www.hello.dev/
 *
 * @wordpress-plugin
 * Plugin Name:       Hellō Login
 * Plugin URI:        https://github.com/hellocoop/wordpress
 * Description:       A login and registration plugin for the Hellō service.
 * Version:           1.0.4
 * Requires at least: 4.9
 * Requires PHP:      7.2
 * Author:            hellocoop
 * Author URI:        http://www.hello.coop
 * Text Domain:       hello-login
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/hellocoop/wordpress
 */

/*
Notes
  Spec Doc - http://openid.net/specs/openid-connect-basic-1_0-32.html

  Filters
  - hello-login-alter-request                           - 3 args: request array, plugin settings, specific request op
  - hello-login-settings-fields                         - modify the fields provided on the settings page
  - hello-login-user-login-test                         - (bool) should the user be logged in based on their claim
  - hello-login-user-creation-test                      - (bool) should the user be created based on their claim
  - hello-login-auth-url                                - modify the authentication url
  - hello-login-alter-user-data                         - modify user data before a new user is created
  - hello-login-modify-token-response-before-validation - modify the token response before validation
  - hello-login-modify-id-token-claim-before-validation - modify the token claim before validation

  Actions
  - hello-login-user-create                     - 2 args: fires when a new user is created by this plugin
  - hello-login-user-update                     - 1 arg: user ID, fires when user is updated by this plugin
  - hello-login-update-user-using-current-claim - 2 args: fires every time an existing user logs in and the claims are updated.
  - hello-login-redirect-user-back              - 2 args: $redirect_url, $user. Allows interruption of redirect during login.
  - hello-login-user-logged-in                  - 1 arg: $user, fires when user is logged in.
  - hello-login-cron-daily                      - daily cron action
  - hello-login-state-not-found                 - the given state does not exist in the database, regardless of its expiration.
  - hello-login-state-expired                   - the given state exists, but expired before this login attempt.

  Callable actions

  User Meta
  - hello-login-subject-identity    - the identity of the user provided by the idp
  - hello-login-last-id-token-claim - the user's most recent id_token claim, decoded
  - hello-login-last-user-claim     - the user's most recent user_claim
  - hello-login-last-token-response - the user's most recent token response

  Options
  - hello_login_settings - plugin settings
*/


/**
 * Hello_Login class.
 *
 * Defines plugin initialization functionality.
 *
 * @package Hello_Login
 * @category  General
 */
class Hello_Login {

	/**
	 * Singleton instance of self
	 *
	 * @var Hello_Login
	 */
	protected static $_instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.4';

	/**
	 * Plugin option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'hello_login_settings';

	/**
	 * Plugin logs option name.
	 *
	 * @var string
	 */
	const LOGS_OPTION_NAME = 'hello-login-logs';

	/**
	 * Plugin settings.
	 *
	 * @var Hello_Login_Option_Settings
	 */
	private $settings;

	/**
	 * Plugin logs.
	 *
	 * @var Hello_Login_Option_Logger
	 */
	private $logger;

	/**
	 * Hellō Login client
	 *
	 * @var Hello_Login_Client
	 */
	private $client;

	/**
	 * Client wrapper.
	 *
	 * @var Hello_Login_Client_Wrapper
	 */
	public $client_wrapper;

	/**
	 * Setup the plugin
	 *
	 * @param Hello_Login_Option_Settings $settings The settings object.
	 * @param Hello_Login_Option_Logger   $logger   The loggin object.
	 *
	 * @return void
	 */
	public function __construct( Hello_Login_Option_Settings $settings, Hello_Login_Option_Logger $logger ) {
		$this->settings = $settings;
		$this->logger = $logger;
		self::$_instance = $this;
	}

	/**
	 * WordPress Hook 'init'.
	 *
	 * @return void
	 */
	public function init() {

		$redirect_uri = admin_url( 'admin-ajax.php?action=hello-login-callback' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/hello-login-callback' );
		}

		$state_time_limit = 180;
		if ( $this->settings->state_time_limit ) {
			$state_time_limit = intval( $this->settings->state_time_limit );
		}

		$this->client = new Hello_Login_Client(
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->endpoint_login,
			$this->settings->endpoint_userinfo,
			$this->settings->endpoint_token,
			$redirect_uri,
			$this->settings->acr_values,
			$state_time_limit,
			$this->logger
		);

		$this->client_wrapper = Hello_Login_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		Hello_Login_Login_Form::register( $this->settings, $this->client_wrapper );

		// Add a shortcode to get the auth URL.
		add_shortcode( 'hello_login_auth_url', array( $this->client_wrapper, 'get_authentication_url' ) );

		// Add actions to our scheduled cron jobs.
		add_action( 'hello-login-cron-daily', array( $this, 'cron_states_garbage_collection' ) );

		$this->upgrade();

		if ( is_admin() ) {
			Hello_Login_Settings_Page::register( $this->settings, $this->client_wrapper, $this->logger );
		}
	}

	/**
	 * Check if privacy enforcement is enabled, and redirect users that aren't
	 * logged in.
	 *
	 * @return void
	 */
	public function enforce_privacy_redirect() {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			// The client endpoint relies on the wp-admin ajax endpoint.
			if ( ! defined( 'DOING_AJAX' ) || ! constant( 'DOING_AJAX' ) || ! isset( $_GET['action'] ) || 'hello-login-callback' != $_GET['action'] ) {
				auth_redirect();
			}
		}
	}

	/**
	 * Enforce privacy settings for rss feeds.
	 *
	 * @param string $content The content.
	 *
	 * @return mixed
	 */
	public function enforce_privacy_feeds( $content ) {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			$content = __( 'Private site', 'hello-login' );
		}
		return $content;
	}

	/**
	 * Handle plugin upgrades
	 *
	 * @return void
	 */
	public function upgrade() {
		$last_version = get_option( 'hello-login-plugin-version', 0 );
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
			update_option( 'hello-login-plugin-version', self::VERSION );
		}
	}

	/**
	 * Expire state transients by attempting to access them and allowing the
	 * transient's own mechanisms to delete any that have expired.
	 *
	 * @return void
	 */
	public function cron_states_garbage_collection() {
		global $wpdb;
		$states = $wpdb->get_col( "SELECT `option_name` FROM {$wpdb->options} WHERE `option_name` LIKE '_transient_hello-login-state--%'" );

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
	public static function setup_cron_jobs() {
		if ( ! wp_next_scheduled( 'hello-login-cron-daily' ) ) {
			wp_schedule_event( time(), 'daily', 'hello-login-cron-daily' );
		}
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activation() {
		self::setup_cron_jobs();
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivation() {
		wp_clear_scheduled_hook( 'hello-login-cron-daily' );
	}

	/**
	 * Uninstall hook.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option(self::OPTION_NAME);
		delete_option(self::LOGS_OPTION_NAME);
	}

	/**
	 * Simple autoloader.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = 'Hello_Login_';

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
	public static function bootstrap() {
		/**
		 * This is a documented valid call for spl_autoload_register.
		 *
		 * @link https://www.php.net/manual/en/function.spl-autoload-register.php#71155
		 */
		spl_autoload_register( array( 'Hello_Login', 'autoload' ) );

		$settings = new Hello_Login_Option_Settings(
			self::OPTION_NAME,
			// Default settings values.
			array(
				// OAuth client settings.
				'login_type'           => defined( 'OIDC_LOGIN_TYPE' ) ? OIDC_LOGIN_TYPE : 'button',
				'client_id'            => defined( 'OIDC_CLIENT_ID' ) ? OIDC_CLIENT_ID : '',
				'client_secret'        => defined( 'OIDC_CLIENT_SECRET' ) ? OIDC_CLIENT_SECRET : '',
				'scope'                => defined( 'OIDC_CLIENT_SCOPE' ) ? OIDC_CLIENT_SCOPE : 'openid name nickname family_name given_name email',
				'endpoint_login'       => defined( 'OIDC_ENDPOINT_LOGIN_URL' ) ? OIDC_ENDPOINT_LOGIN_URL : 'https://wallet.hello.coop/authorize',
				'endpoint_userinfo'    => defined( 'OIDC_ENDPOINT_USERINFO_URL' ) ? OIDC_ENDPOINT_USERINFO_URL : 'https://wallet.hello.coop/oauth/userinfo',
				'endpoint_token'       => defined( 'OIDC_ENDPOINT_TOKEN_URL' ) ? OIDC_ENDPOINT_TOKEN_URL : 'https://wallet.hello.coop/oauth/token',
				'endpoint_end_session' => defined( 'OIDC_ENDPOINT_LOGOUT_URL' ) ? OIDC_ENDPOINT_LOGOUT_URL : '',
				'acr_values'           => defined( 'OIDC_ACR_VALUES' ) ? OIDC_ACR_VALUES : '',
				'enable_pkce'          => defined( 'OIDC_ENABLE_PKCE' ) ? OIDC_ENABLE_PKCE : true,

				// Non-standard settings.
				'no_sslverify'    => 0,
				'http_request_timeout' => 5,
				'identity_key'    => 'nickname',
				'nickname_key'    => 'nickname',
				'email_format'       => '{email}',
				'displayname_format' => '{name}',
				'identify_with_username' => false,

				// Plugin settings.
				'enforce_privacy' => defined( 'OIDC_ENFORCE_PRIVACY' ) ? intval( OIDC_ENFORCE_PRIVACY ) : 0,
				'alternate_redirect_uri' => 0,
				'token_refresh_enable' => 0,
				'link_existing_users' => defined( 'OIDC_LINK_EXISTING_USERS' ) ? intval( OIDC_LINK_EXISTING_USERS ) : 1,
				'create_if_does_not_exist' => defined( 'OIDC_CREATE_IF_DOES_NOT_EXIST' ) ? intval( OIDC_CREATE_IF_DOES_NOT_EXIST ) : 1,
				'redirect_user_back' => defined( 'OIDC_REDIRECT_USER_BACK' ) ? intval( OIDC_REDIRECT_USER_BACK ) : 1,
				'redirect_on_logout' => defined( 'OIDC_REDIRECT_ON_LOGOUT' ) ? intval( OIDC_REDIRECT_ON_LOGOUT ) : 1,
				'enable_logging'  => 0,
				'log_limit'       => 1000,
			)
		);

		$logger = new Hello_Login_Option_Logger( self::LOGS_OPTION_NAME, 'error', $settings->enable_logging, $settings->log_limit );

		$plugin = new self( $settings, $logger );

		add_action( 'init', array( $plugin, 'init' ) );

		// Privacy hooks.
		add_action( 'template_redirect', array( $plugin, 'enforce_privacy_redirect' ), 0 );
		add_filter( 'the_content_feed', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'the_excerpt_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'comment_text_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
	}

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return Hello_Login
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::bootstrap();
		}
		return self::$_instance;
	}
}

Hello_Login::instance();

register_activation_hook( __FILE__, array( 'Hello_Login', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'Hello_Login', 'deactivation' ) );
register_uninstall_hook( __FILE__, array( 'Hello_Login', 'uninstall' ) );

// Provide publicly accessible plugin helper functions.
require_once( 'includes/functions.php' );
