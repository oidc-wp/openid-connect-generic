<?php
/**
 * OpenID Connect Generic Client
 *
 * This plugin provides the ability to authenticate users with Identity
 * Providers using the OpenID Connect OAuth2 API with Authorization Code Flow.
 *
 * @package   OpenID_Connect_Generic
 * @category  General
 * @author    Jonathan Daggerhart <jonathan@daggerhartlab.com>
 * @copyright 2015-2023 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/oidc-wp
 *
 * @wordpress-plugin
 * Plugin Name:       OpenID Connect Generic
 * Plugin URI:        https://github.com/oidc-wp/openid-connect-generic
 * Description:       Connect to an OpenID Connect identity provider using Authorization Code Flow.
 * Version:           3.11.5
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            daggerhart
 * Author URI:        https://www.daggerhartlab.com
 * Text Domain:       daggerhart-openid-connect-generic
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/oidc-wp/openid-connect-generic
 */

/*
Notes
  Spec Doc - http://openid.net/specs/openid-connect-basic-1_0-32.html

  Hooks are documented with examples in the wiki: https://github.com/oidc-wp/openid-connect-generic/wiki/Hooks-and-Filters-Reference
  Keep this list in sync when adding or changing a hook.

  Filters
  - openid-connect-generic-alter-request       - 3 args: request array, plugin settings, specific request op
  - openid-connect-generic-settings-fields     - modify the fields provided on the settings page
  - openid-connect-generic-settings            - modify settings values early in plugin bootstrap.
  - openid-connect-generic-login-button-text   - modify the login button text
  - openid-connect-generic-client-redirect-to  - modify the URL the user is sent to after logging in
  - openid-connect-generic-cookie-redirect-url - deprecated since 3.8.2, use openid-connect-generic-client-redirect-to
  - openid-connect-generic-redirect-uri        - 2 args: redirect URI sent to the IDP, plugin settings
  - openid-connect-generic-user-login-test     - (bool) should the user be logged in based on their claim
  - openid-connect-generic-user-creation-test  - (bool) should the user be created based on their claim
  - openid-connect-generic-auth-url            - modify the authentication url
  - openid-connect-generic-alter-user-claim    - modify the user_claim before a new user is created
  - openid-connect-generic-alter-user-data     - modify user data before a new user is created
  - openid-connect-generic-remember-me         - 6 args: (bool) remember the login, $user, token response, id_token claim, user claim, subject identity
  - openid-connect-generic-browser-cookie-name - modify the name of the cookie that ties the login state to the browser
  - openid-connect-modify-token-response-before-validation - modify the token response before validation
  - openid-connect-modify-id-token-claim-before-validation - modify the token claim before validation
  - openid-connect-generic-new-state-value     - modify the user's state value before it us saved.

  Actions
  - openid-connect-generic-user-create                     - 2 args: fires when a new user is created by this plugin
  - openid-connect-generic-user-update                     - 1 arg: user ID, fires when user is updated by this plugin
  - openid-connect-generic-update-user-using-current-claim - 2 args: fires every time an existing user logs in and the claims are updated.
  - openid-connect-generic-redirect-user-back              - 2 args: $redirect_url, $user. Allows interruption of redirect during login.
  - openid-connect-generic-user-logged-in                  - 1 arg: $user, fires when user is logged in.
  - openid-connect-generic-cron-daily                      - daily cron action
  - openid-connect-generic-no-state-provided               - the authorization response has no state.
  - openid-connect-generic-state-not-found                 - the given state does not exist in the database, regardless of its expiration.
  - openid-connect-generic-state-expired                   - the given state exists, but expired before this login attempt.

  User Meta (since v3.10.4 prefixed with the blog database prefix, for example wp_2_openid-connect-generic-subject-identity)
  - [[BLOG_DB_PREFIX]]openid-connect-generic-subject-identity    - the identity of the user provided by the idp
  - [[BLOG_DB_PREFIX]]openid-connect-generic-last-id-token-claim - the user's most recent id_token claim, decoded
  - [[BLOG_DB_PREFIX]]openid-connect-generic-last-user-claim     - the user's most recent user_claim
  - [[BLOG_DB_PREFIX]]openid-connect-generic-last-token-response - the user's most recent token response

  Options
  - openid_connect_generic_settings                - plugin settings
  - openid-connect-generic-logs                    - plugin log, when logging is enabled
  - openid-connect-generic-plugin-version          - plugin version, used for upgrades
  - openid-connect-generic-notice-link-unverified  - shows the notice about the Link Users Without Verified Email setting
  - openid-connect-generic-notice-redirect-uri     - the redirect URI used before 3.12.0, for the notice about its change

  Transients
  - openid-connect-generic-state--<state>          - generated login states
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
	 * Singleton instance of self
	 *
	 * @var OpenID_Connect_Generic
	 */
	protected static $_instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '3.11.5';

	/**
	 * Option flagging the admin notice about linking users without a verified email.
	 *
	 * @var string
	 */
	const NOTICE_LINK_UNVERIFIED_OPTION = 'openid-connect-generic-notice-link-unverified';

	/**
	 * Option holding the redirect URI used before 3.12.0, for the admin notice about its change.
	 *
	 * @var string
	 */
	const NOTICE_REDIRECT_URI_OPTION = 'openid-connect-generic-notice-redirect-uri';

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
	public $client_wrapper;

	/**
	 * Setup the plugin
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The settings object.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   The loggin object.
	 *
	 * @return void
	 */
	public function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$this->settings = $settings;
		$this->logger = $logger;
		self::$_instance = $this;
	}

	// @codeCoverageIgnoreStart

	/**
	 * WordPress Hook 'init'.
	 *
	 * @return void
	 */
	public function init() {

		// Allow altering the settings.
		$this->settings = apply_filters( 'openid-connect-generic-settings', $this->settings );

		$this->client = new OpenID_Connect_Generic_Client(
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->endpoint_login,
			$this->settings->endpoint_userinfo,
			$this->settings->endpoint_token,
			$this->get_redirect_uri( $this->settings ),
			$this->settings->acr_values,
			$this->settings->endpoint_jwks,
			$this->settings->issuer ?? '',
			$this->settings->jwks_cache_ttl,
			$this->get_state_time_limit( $this->settings ),
			$this->settings->allow_internal_idp,
			$this->logger
		);

		$this->client_wrapper = OpenID_Connect_Generic_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		OpenID_Connect_Generic_Login_Form::register( $this->settings, $this->client_wrapper, $this->client );

		// Add a shortcode to get the auth URL.
		add_shortcode( 'openid_connect_generic_auth_url', array( $this->client_wrapper, 'get_authentication_url' ) );

		// Add actions to our scheduled cron jobs.
		add_action( 'openid-connect-generic-cron-daily', array( $this, 'cron_states_garbage_collection' ) );

		$this->upgrade();

		if ( is_admin() ) {
			OpenID_Connect_Generic_Settings_Page::register( $this->settings, $this->logger );
			add_action( 'admin_notices', array( $this, 'admin_notice_jwks_required' ) );
			add_action( 'admin_notices', array( $this, 'admin_notice_link_unverified_users' ) );
			add_action( 'admin_init', array( $this, 'dismiss_admin_notice_link_unverified_users' ) );
			add_action( 'admin_notices', array( $this, 'admin_notice_redirect_uri_changed' ) );
			add_action( 'admin_init', array( $this, 'dismiss_admin_notice_redirect_uri_changed' ) );
		}
	}

	/**
	 * Get the default redirect URI.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The settings object.
	 *
	 * @return string
	 */
	public function get_redirect_uri( OpenID_Connect_Generic_Option_Settings $settings ) {
		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $settings->alternate_redirect_uri ) {
			$redirect_uri = home_url( '/openid-connect-authorize' );
		}

		/**
		 * Filters the redirect URI sent to the identity provider.
		 *
		 * This changes only the URI that is sent; requests to it must still reach the plugin's callback.
		 *
		 * @param string                                 $redirect_uri The redirect URI.
		 * @param OpenID_Connect_Generic_Option_Settings $settings     The plugin settings.
		 */
		return apply_filters( 'openid-connect-generic-redirect-uri', $redirect_uri, $settings );
	}

	/**
	 * Get the default state time limit.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The settings object.
	 *
	 * @return int
	 */
	public function get_state_time_limit( OpenID_Connect_Generic_Option_Settings $settings ) {
		$state_time_limit = 180;
		// State time limit cannot be zero.
		if ( $settings->state_time_limit ) {
			$state_time_limit = intval( $settings->state_time_limit );
		}

		return $state_time_limit;
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
			if (
				! defined( 'DOING_AJAX' ) ||
				! boolval( constant( 'DOING_AJAX' ) ) ||
				! isset( $_GET['action'] ) ||
				'openid-connect-authorize' != $_GET['action'] ) {
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
			$content = __( 'Private site', 'daggerhart-openid-connect-generic' );
		}
		return $content;
	}

	/**
	 * Display admin notice when the plugin's dependencies are missing.
	 *
	 * @return void
	 */
	public static function admin_notice_missing_dependencies() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: the composer install command */
					esc_html__( 'OpenID Connect Generic is not running because its dependencies are missing. Install the plugin from WordPress.org, or run %s in the plugin folder.', 'daggerhart-openid-connect-generic' ),
					'<code>composer install --no-dev</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display admin notice when JWKS endpoint is not configured.
	 *
	 * @return void
	 */
	public function admin_notice_jwks_required() {
		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if JWKS endpoint is configured.
		if ( ! empty( $this->settings->endpoint_jwks ) ) {
			return;
		}

		// Check if any OIDC endpoints are configured (plugin is actually being used).
		if ( empty( $this->settings->endpoint_login ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=openid-connect-generic-settings' );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'OpenID Connect Generic - Security Configuration Required', 'daggerhart-openid-connect-generic' ); ?></strong>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s is a link to the settings page */
						__( 'Your OpenID Connect authentication is using an insecure fallback method. You must configure the <strong>JWKS endpoint</strong> in <a href="%s">plugin settings</a> as soon as possible.', 'daggerhart-openid-connect-generic' ),
						esc_url( $settings_url )
					)
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'The current insecure fallback will be removed in version 3.12.0. After that update, authentication will fail until the JWKS endpoint is configured.', 'daggerhart-openid-connect-generic' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Common JWKS endpoints:', 'daggerhart-openid-connect-generic' ); ?></strong><br>
				• Keycloak: <code>https://your-domain/realms/your-realm/protocol/openid-connect/certs</code><br>
				• Auth0: <code>https://your-domain.auth0.com/.well-known/jwks.json</code><br>
				• Okta: <code>https://your-domain.okta.com/oauth2/default/v1/keys</code><br>
				• Azure AD: <code>https://login.microsoftonline.com/your-tenant/discovery/v2.0/keys</code><br>
				• Google: <code>https://www.googleapis.com/oauth2/v3/certs</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Display admin notice after an upgrade enabled linking users without a verified email.
	 *
	 * @return void
	 */
	public function admin_notice_link_unverified_users() {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( self::NOTICE_LINK_UNVERIFIED_OPTION ) ) {
			return;
		}

		// Nothing to review once the setting is off.
		if ( empty( $this->settings->link_unverified_users ) ) {
			delete_option( self::NOTICE_LINK_UNVERIFIED_OPTION );
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=openid-connect-generic-settings' );
		$dismiss_url = wp_nonce_url( add_query_arg( 'openid-connect-generic-dismiss', 'link-unverified' ), 'openid-connect-generic-dismiss-link-unverified' );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'OpenID Connect Generic - Please review a new security setting', 'daggerhart-openid-connect-generic' ); ?></strong>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s is a link to the settings page */
						__( 'This update added the <strong>Link Users Without Verified Email</strong> setting. It was turned on so existing users can keep logging in as before. Leave it on only if your identity provider stops people from using an email address or user name that belongs to someone else. Otherwise, turn it off in the <a href="%s">plugin settings</a>.', 'daggerhart-openid-connect-generic' ),
						esc_url( $settings_url )
					)
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'I have reviewed this setting', 'daggerhart-openid-connect-generic' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss the notice about linking users without a verified email.
	 *
	 * @return void
	 */
	public function dismiss_admin_notice_link_unverified_users() {
		if ( ! isset( $_GET['openid-connect-generic-dismiss'] ) || 'link-unverified' !== sanitize_key( wp_unslash( $_GET['openid-connect-generic-dismiss'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'openid-connect-generic-dismiss-link-unverified' );
		delete_option( self::NOTICE_LINK_UNVERIFIED_OPTION );

		wp_safe_redirect( remove_query_arg( array( 'openid-connect-generic-dismiss', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Display admin notice after an upgrade changed the alternate redirect URI.
	 *
	 * @return void
	 */
	public function admin_notice_redirect_uri_changed() {
		$old_redirect_uri = get_option( self::NOTICE_REDIRECT_URI_OPTION );
		if ( ! current_user_can( 'manage_options' ) || empty( $old_redirect_uri ) ) {
			return;
		}

		$new_redirect_uri = $this->get_redirect_uri( $this->settings );

		// Nothing to update at the identity provider once the URIs match again.
		if ( $old_redirect_uri === $new_redirect_uri ) {
			delete_option( self::NOTICE_REDIRECT_URI_OPTION );
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'openid-connect-generic-dismiss', 'redirect-uri' ), 'openid-connect-generic-dismiss-redirect-uri' );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'OpenID Connect Generic - Your redirect URI has changed', 'daggerhart-openid-connect-generic' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'With Alternate Redirect URI enabled and WordPress installed in a subdirectory, the redirect URI is now based on your site address instead of the WordPress address. If your identity provider doesn\'t allow the new redirect URI yet, logins with it will fail until you add it. You can keep the old one allowed as well.', 'daggerhart-openid-connect-generic' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Old:', 'daggerhart-openid-connect-generic' ); ?> <code><?php echo esc_html( $old_redirect_uri ); ?></code><br>
				<?php esc_html_e( 'New:', 'daggerhart-openid-connect-generic' ); ?> <code><?php echo esc_html( $new_redirect_uri ); ?></code>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s is the filter name */
						__( 'If your server already routes the old URI to the plugin, you can keep sending it with the %s filter.', 'daggerhart-openid-connect-generic' ),
						'<code>openid-connect-generic-redirect-uri</code>'
					)
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'I have updated my identity provider', 'daggerhart-openid-connect-generic' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss the notice about the changed redirect URI.
	 *
	 * @return void
	 */
	public function dismiss_admin_notice_redirect_uri_changed() {
		if ( ! isset( $_GET['openid-connect-generic-dismiss'] ) || 'redirect-uri' !== sanitize_key( wp_unslash( $_GET['openid-connect-generic-dismiss'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'openid-connect-generic-dismiss-redirect-uri' );
		delete_option( self::NOTICE_REDIRECT_URI_OPTION );

		wp_safe_redirect( remove_query_arg( array( 'openid-connect-generic-dismiss', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Handle plugin upgrades
	 *
	 * @return void
	 */
	public function upgrade() {
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

			if ( ! empty( $last_version ) && version_compare( $last_version, '3.11.4', '<' ) ) {
				// Existing sites keep their current authorization request; PKCE is opt-in for them.
				if ( ! defined( 'OIDC_ENABLE_PKCE' ) ) {
					$settings->enable_pkce = false;
				}

				// Keep existing sites that link users working, and ask the admin to review the new setting.
				if ( ! empty( $settings->link_existing_users ) ) {
					$settings->link_unverified_users = true;
					update_option( self::NOTICE_LINK_UNVERIFIED_OPTION, 1 );
				}

				$settings->save();
			}

			// Before 3.12.0 the alternate redirect URI used the WordPress address; tell affected sites it changed.
			if ( ! empty( $last_version ) && version_compare( $last_version, '3.12.0', '<' ) && ! empty( $settings->alternate_redirect_uri ) ) {
				$old_redirect_uri = site_url( '/openid-connect-authorize' );
				if ( home_url( '/openid-connect-authorize' ) !== $old_redirect_uri ) {
					update_option( self::NOTICE_REDIRECT_URI_OPTION, $old_redirect_uri );
				}
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
	public function cron_states_garbage_collection() {
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
	public static function setup_cron_jobs() {
		if ( ! wp_next_scheduled( 'openid-connect-generic-cron-daily' ) ) {
			wp_schedule_event( time(), 'daily', 'openid-connect-generic-cron-daily' );
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
		wp_clear_scheduled_hook( 'openid-connect-generic-cron-daily' );
	}

	/**
	 * Simple autoloader.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	public static function autoload( $class ) {
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

		$filepath = __DIR__ . '/includes/' . $filename;

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
		// Composer installs load dependencies through the project's autoloader instead.
		if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
			require_once __DIR__ . '/vendor/autoload.php';
		}

		if ( ! class_exists( \Firebase\JWT\JWT::class ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice_missing_dependencies' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'admin_notice_missing_dependencies' ) );
			return;
		}

		$settings = new OpenID_Connect_Generic_Option_Settings(
			// Default settings values.
			array(
				// OAuth client settings.
				'login_type'           => defined( 'OIDC_LOGIN_TYPE' ) ? OIDC_LOGIN_TYPE : 'button',
				'login_button_text'    => '',
				'login_button_icon'    => '',
				'client_id'            => defined( 'OIDC_CLIENT_ID' ) ? OIDC_CLIENT_ID : '',
				'client_secret'        => defined( 'OIDC_CLIENT_SECRET' ) ? OIDC_CLIENT_SECRET : '',
				'scope'                => defined( 'OIDC_CLIENT_SCOPE' ) ? OIDC_CLIENT_SCOPE : '',
				'endpoint_login'       => defined( 'OIDC_ENDPOINT_LOGIN_URL' ) ? OIDC_ENDPOINT_LOGIN_URL : '',
				'endpoint_userinfo'    => defined( 'OIDC_ENDPOINT_USERINFO_URL' ) ? OIDC_ENDPOINT_USERINFO_URL : '',
				'endpoint_token'       => defined( 'OIDC_ENDPOINT_TOKEN_URL' ) ? OIDC_ENDPOINT_TOKEN_URL : '',
				'endpoint_end_session' => defined( 'OIDC_ENDPOINT_LOGOUT_URL' ) ? OIDC_ENDPOINT_LOGOUT_URL : '',
				'endpoint_jwks'        => defined( 'OIDC_ENDPOINT_JWKS_URL' ) ? OIDC_ENDPOINT_JWKS_URL : '',
				'issuer'               => defined( 'OIDC_ISSUER' ) ? OIDC_ISSUER : '',
				'jwks_cache_ttl'       => 3600,
				'acr_values'           => defined( 'OIDC_ACR_VALUES' ) ? OIDC_ACR_VALUES : '',

				// Non-standard settings.
				'no_sslverify'           => 0,
				'http_request_timeout'   => 5,
				'allow_internal_idp'     => 0,
				'identity_key'           => 'preferred_username',
				'nickname_key'           => 'preferred_username',
				'email_format'           => '{email}',
				'displayname_format'     => '',
				'identify_with_username' => false,
				'state_time_limit'       => 180,
				'enable_pkce'            => defined( 'OIDC_ENABLE_PKCE' ) ? intval( OIDC_ENABLE_PKCE ) : 1,

				// Plugin settings.
				'enforce_privacy'          => defined( 'OIDC_ENFORCE_PRIVACY' ) ? intval( OIDC_ENFORCE_PRIVACY ) : 0,
				'password_login'           => defined( 'OIDC_PASSWORD_LOGIN' ) ? OIDC_PASSWORD_LOGIN : '',
				'password_login_roles'     => defined( 'OIDC_PASSWORD_LOGIN_ROLES' ) ? (array) OIDC_PASSWORD_LOGIN_ROLES : array( 'subscriber', 'customer' ),
				'alternate_redirect_uri'   => 0,
				'token_refresh_enable'     => 1,
				'link_existing_users'      => defined( 'OIDC_LINK_EXISTING_USERS' ) ? intval( OIDC_LINK_EXISTING_USERS ) : 0,
				'link_unverified_users'    => defined( 'OIDC_LINK_UNVERIFIED_USERS' ) ? intval( OIDC_LINK_UNVERIFIED_USERS ) : 0,
				'create_if_does_not_exist' => defined( 'OIDC_CREATE_IF_DOES_NOT_EXIST' ) ? intval( OIDC_CREATE_IF_DOES_NOT_EXIST ) : 1,
				'redirect_user_back'       => defined( 'OIDC_REDIRECT_USER_BACK' ) ? intval( OIDC_REDIRECT_USER_BACK ) : 0,
				'redirect_on_logout'       => defined( 'OIDC_REDIRECT_ON_LOGOUT' ) ? intval( OIDC_REDIRECT_ON_LOGOUT ) : 1,
				'enable_logging'           => defined( 'OIDC_ENABLE_LOGGING' ) ? intval( OIDC_ENABLE_LOGGING ) : 0,
				'log_limit'                => defined( 'OIDC_LOG_LIMIT' ) ? intval( OIDC_LOG_LIMIT ) : 1000,
			)
		);

		$logger = new OpenID_Connect_Generic_Option_Logger( 'error', $settings->enable_logging, $settings->log_limit );

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
	 * @return OpenID_Connect_Generic|null Null if the plugin's dependencies are missing.
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::bootstrap();
		}
		return self::$_instance;
	}
}

OpenID_Connect_Generic::instance();

register_activation_hook( __FILE__, array( 'OpenID_Connect_Generic', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'OpenID_Connect_Generic', 'deactivation' ) );

// Provide publicly accessible plugin helper functions.
require_once 'includes/functions.php';
