<?php
/**
 * Plugin Admin settings page class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2023 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Settings_Page class.
 *
 * Admin settings page.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 */
class OpenID_Connect_Generic_Settings_Page {

	/**
	 * Local copy of the settings provided by the base plugin.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * Instance of the plugin logger.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * The controlled list of settings & associated defined during
	 * construction for i18n reasons.
	 *
	 * @var array
	 */
	private $settings_fields = array();

	/**
	 * Options page slug.
	 *
	 * @var string
	 */
	private $options_page_name = 'openid-connect-generic-settings';

	/**
	 * Options page settings group name.
	 *
	 * @var string
	 */
	private $settings_field_group;

	/**
	 * Settings page class constructor.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The plugin settings object.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   The plugin logging class object.
	 */
	public function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {

		$this->settings             = $settings;
		$this->logger               = $logger;
		$this->settings_field_group = $this->settings->get_option_name() . '-group';

		$fields = $this->get_settings_fields();

		// Some simple pre-processing.
		foreach ( $fields as $key => &$field ) {
			$field['key']  = $key;
			$field['name'] = $this->settings->get_option_name() . '[' . $key . ']';
		}

		// Allow alterations of the fields.
		$this->settings_fields = $fields;
	}

	/**
	 * Make a safe HTTP GET request with optional internal endpoint support.
	 *
	 * By default, uses wp_safe_remote_get() which blocks requests to internal/private
	 * networks (SSRF protection). If allow_internal_idp is enabled, uses wp_remote_get()
	 * to allow connections to localhost and private network identity providers.
	 *
	 * @param string $url  The URL to request.
	 * @param array  $args Optional. Request arguments.
	 *
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	private function http_get( $url, $args = array() ) {
		if ( $this->settings->allow_internal_idp ) {
			return wp_remote_get( $url, $args );
		}
		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Hook the settings page into WordPress.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   A plugin logger object instance.
	 *
	 * @return void
	 */
	public static function register( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$settings_page = new self( $settings, $logger );

		// Add our options page the the admin menu.
		add_action( 'admin_menu', array( $settings_page, 'admin_menu' ) );

		// Register our settings.
		add_action( 'admin_init', array( $settings_page, 'admin_init' ) );
	}

	/**
	 * Implements hook admin_menu to add our options/settings page to the
	 *  dashboard menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__( 'OpenID Connect - Generic Client', 'daggerhart-openid-connect-generic' ),
			__( 'OpenID Connect Client', 'daggerhart-openid-connect-generic' ),
			'manage_options',
			$this->options_page_name,
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Implements hook admin_init to register our settings.
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			$this->settings_field_group,
			$this->settings->get_option_name(),
			array(
				$this,
				'sanitize_settings',
			)
		);

		add_settings_section(
			'client_settings',
			__( 'Client Settings', 'daggerhart-openid-connect-generic' ),
			array( $this, 'client_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'user_settings',
			__( 'WordPress User Settings', 'daggerhart-openid-connect-generic' ),
			array( $this, 'user_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'authorization_settings',
			__( 'Authorization Settings', 'daggerhart-openid-connect-generic' ),
			array( $this, 'authorization_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'log_settings',
			__( 'Log Settings', 'daggerhart-openid-connect-generic' ),
			array( $this, 'log_settings_description' ),
			$this->options_page_name
		);

		// Preprocess fields and add them to the page.
		foreach ( $this->settings_fields as $key => $field ) {
			// Make sure each key exists in the settings array.
			if ( ! isset( $this->settings->{ $key } ) ) {
				$this->settings->{ $key } = null;
			}

			// Determine appropriate output callback.
			switch ( $field['type'] ) {
				case 'checkbox':
					$callback = 'do_checkbox';
					break;

				case 'select':
					$callback = 'do_select';
					break;

				case 'text':
				default:
					$callback = 'do_text_field';
					break;
			}

			// Add the field.
			add_settings_field(
				$key,
				$field['title'],
				array( $this, $callback ),
				$this->options_page_name,
				$field['section'],
				$field
			);
		}
	}

	/**
	 * Get the plugin settings fields definition.
	 *
	 * @return array
	 */
	private function get_settings_fields() {

		/**
		 * Simple settings fields have:
		 *
		 * - title
		 * - description
		 * - type ( checkbox | text | select )
		 * - section - settings/option page section ( client_settings | authorization_settings )
		 * - example (optional example will appear beneath description and be wrapped in <code>)
		 */
		$fields = array(
			'login_type'        => array(
				'title'       => __( 'Login Type', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Select how the client (login form) should provide login options.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'select',
				'options'     => array(
					'button' => __( 'OpenID Connect button on login form', 'daggerhart-openid-connect-generic' ),
					'auto'   => __( 'Auto Login - SSO', 'daggerhart-openid-connect-generic' ),
				),
				'disabled'    => defined( 'OIDC_LOGIN_TYPE' ),
				'section'     => 'client_settings',
			),
			'login_button_text' => array(
				'title'       => __( 'Login Button Text', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Customize the text shown on the OpenID Connect login button. Leave empty to use the default text.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'Login with Single Sign-On',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'client_id'         => array(
				'title'       => __( 'Client ID', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'The ID this client will be recognized as when connecting the to Identity provider server.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'my-wordpress-client-id',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_CLIENT_ID' ),
				'section'     => 'client_settings',
			),
			'client_secret'     => array(
				'title'       => __( 'Client Secret Key', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Arbitrary secret key the server expects from this client. Can be anything, but should be very unique.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_CLIENT_SECRET' ),
				'section'     => 'client_settings',
			),
			'scope'             => array(
				'title'       => __( 'OpenID Scope', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Space separated list of scopes this client should access.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'email profile openid offline_access',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_CLIENT_SCOPE' ),
				'section'     => 'client_settings',
			),
			'endpoint_login'    => array(
				'title'       => __( 'Login Endpoint URL', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Identify provider authorization endpoint.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'https://example.com/oauth2/authorize',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ENDPOINT_LOGIN_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_userinfo' => array(
				'title'       => __( 'Userinfo Endpoint URL', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Identify provider User information endpoint.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'https://example.com/oauth2/UserInfo',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ENDPOINT_USERINFO_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_token'    => array(
				'title'       => __( 'Token Validation Endpoint URL', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Identify provider token endpoint.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'https://example.com/oauth2/token',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ENDPOINT_TOKEN_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_end_session'    => array(
				'title'       => __( 'End Session Endpoint URL', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Identify provider logout endpoint.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'https://example.com/oauth2/logout',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ENDPOINT_LOGOUT_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_jwks' => array(
				'title'       => __( 'JWKS URI', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Identity provider JWKS (JSON Web Key Set) endpoint for JWT signature verification. Usually found at /.well-known/jwks.json', 'daggerhart-openid-connect-generic' ),
				'example'     => 'https://example.com/.well-known/jwks.json',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ENDPOINT_JWKS_URL' ),
				'section'     => 'client_settings',
			),
			'issuer' => array(
				'title'       => __( 'Issuer', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Identity provider issuer URL for JWT validation. If not set, the issuer will be automatically derived from the Login Endpoint URL. Only configure this if your IDP uses a different issuer than the base URL of the login endpoint.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'https://example.com',
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ISSUER' ),
				'section'     => 'client_settings',
			),
			'jwks_cache_ttl' => array(
				'title'       => __( 'JWKS Cache TTL (seconds)', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Time in seconds to cache JWKS keys. Default: 3600 (1 hour)', 'daggerhart-openid-connect-generic' ),
				'example'     => 3600,
				'type'        => 'number',
				'section'     => 'client_settings',
			),
			'acr_values'    => array(
				'title'       => __( 'ACR values', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Use a specific defined authentication contract from the IDP - optional.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'text',
				'disabled'    => defined( 'OIDC_ACR_VALUES' ),
				'section'     => 'client_settings',
			),
			'identity_key'     => array(
				'title'       => __( 'Identity Key', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Where in the user claim array to find the user\'s identification data. Possible standard values: preferred_username, name, or sub. If you\'re having trouble, use "sub".', 'daggerhart-openid-connect-generic' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'http_request_timeout'      => array(
				'title'       => __( 'HTTP Request Timeout', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Set the timeout for requests made to the IDP. Default value is 5.', 'daggerhart-openid-connect-generic' ),
				'example'     => 30,
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'enforce_privacy'   => array(
				'title'       => __( 'Enforce Privacy', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Require users be logged in to see the site.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'disabled'    => defined( 'OIDC_ENFORCE_PRIVACY' ),
				'section'     => 'authorization_settings',
			),
			'alternate_redirect_uri'   => array(
				'title'       => __( 'Alternate Redirect URI', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Provide an alternative redirect route. Useful if your server is causing issues with the default admin-ajax method. You must flush rewrite rules after changing this setting. This can be done by saving the Permalinks settings page.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'authorization_settings',
			),
			'nickname_key'     => array(
				'title'       => __( 'Nickname Key', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Where in the user claim array to find the user\'s nickname. Possible standard values: preferred_username, name, or sub.', 'daggerhart-openid-connect-generic' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'email_format'     => array(
				'title'       => __( 'Email Formatting', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'String from which the user\'s email address is built. Specify "{email}" as long as the user claim contains an email claim.', 'daggerhart-openid-connect-generic' ),
				'example'     => '{email}',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'displayname_format'     => array(
				'title'       => __( 'Display Name Formatting', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'String from which the user\'s display name is built.', 'daggerhart-openid-connect-generic' ),
				'example'     => '{given_name} {family_name}',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'identify_with_username'     => array(
				'title'       => __( 'Identify with User Name', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'If checked, the user\'s identity will be determined by the user name instead of the email address.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'state_time_limit'     => array(
				'title'       => __( 'State time limit', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'State valid time in seconds. Defaults to 180', 'daggerhart-openid-connect-generic' ),
				'type'        => 'number',
				'section'     => 'client_settings',
			),
			'token_refresh_enable'   => array(
				'title'       => __( 'Enable Refresh Token', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'If checked, support refresh tokens used to obtain access tokens from supported IDPs.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'no_sslverify'      => array(
				'title'       => __( 'Disable SSL Verify', 'daggerhart-openid-connect-generic' ),
				// translators: %1$s HTML tags for layout/styles (strong tag start with warning class), %2$s closing HTML tag for styles.
				'description' => sprintf( __( 'Do not require SSL verification during authorization. %1$sOnly works in local development (WP_DEBUG=true, WP_ENVIRONMENT_TYPE=local).%2$s This setting is automatically disabled in production. If you need this in production, fix your SSL certificates instead.', 'daggerhart-openid-connect-generic' ), '<br><strong class="oidc-warning">', '</strong>' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'allow_internal_idp'      => array(
				'title'       => __( 'Allow Internal IDP', 'daggerhart-openid-connect-generic' ),
				// translators: %1$s HTML tags for layout/styles (strong tag start with warning class), %2$s closing HTML tag for styles.
				'description' => sprintf( __( 'Allow HTTP requests to internal/private network endpoints (localhost, 127.0.0.1, 10.x.x.x, 192.168.x.x, 172.16-31.x.x). %1$sOnly enable this for local development or corporate internal identity providers. Disabling SSRF protection can expose your server to security risks.%2$s', 'daggerhart-openid-connect-generic' ), '<br><strong class="oidc-warning">', '</strong>' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'link_existing_users'   => array(
				'title'       => __( 'Link Existing Users', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'If a WordPress account already exists with the same identity as a newly-authenticated user over OpenID Connect, login as that user instead of generating an error.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'disabled'    => defined( 'OIDC_LINK_EXISTING_USERS' ),
				'section'     => 'user_settings',
			),
			'create_if_does_not_exist'   => array(
				'title'       => __( 'Create user if does not exist', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'If the user identity is not linked to an existing WordPress user, it is created. If this setting is not enabled, and if the user authenticates with an account which is not linked to an existing WordPress user, then the authentication will fail.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'disabled'    => defined( 'OIDC_CREATE_IF_DOES_NOT_EXIST' ),
				'section'     => 'user_settings',
			),
			'redirect_user_back'   => array(
				'title'       => __( 'Redirect Back to Origin Page', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'After a successful OpenID Connect authentication, this will redirect the user back to the page on which they clicked the OpenID Connect login button. This will cause the login process to proceed in a traditional WordPress fashion. For example, users logging in through the default wp-login.php page would end up on the WordPress Dashboard and users logging in through the WooCommerce "My Account" page would end up on their account page.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'disabled'    => defined( 'OIDC_REDIRECT_USER_BACK' ),
				'section'     => 'user_settings',
			),
			'redirect_on_logout'   => array(
				'title'       => __( 'Redirect to the login screen when session is expired', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'When enabled, this will automatically redirect the user back to the WordPress login page if their access token has expired.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'disabled'    => defined( 'OIDC_REDIRECT_ON_LOGOUT' ),
				'section'     => 'user_settings',
			),
			'enable_logging'    => array(
				'title'       => __( 'Enable Logging', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Very simple log messages for debugging purposes.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'disabled'    => defined( 'OIDC_ENABLE_LOGGING' ),
				'section'     => 'log_settings',
			),
			'log_limit'         => array(
				'title'       => __( 'Log Limit', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Number of items to keep in the log. These logs are stored as an option in the database, so space is limited.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'number',
				'disabled'    => defined( 'OIDC_LOG_LIMIT' ),
				'section'     => 'log_settings',
			),
		);

		return apply_filters( 'openid-connect-generic-settings-fields', $fields );
	}

	/**
	 * Sanitization callback for settings/option page.
	 *
	 * @param array $input The submitted settings values.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$options = array();

		// Loop through settings fields to control what we're saving.
		foreach ( $this->settings_fields as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$options[ $key ] = sanitize_text_field( trim( $input[ $key ] ) );
			} else {
				$options[ $key ] = '';
			}
		}

		return $options;
	}

	/**
	 * Output the options/settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		// Handle discovery form submission before any output.
		$this->handle_discovery_import();

		wp_enqueue_style( 'daggerhart-openid-connect-generic-admin', plugin_dir_url( __DIR__ ) . 'css/styles-admin.css', array(), OpenID_Connect_Generic::VERSION, 'all' );

		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<?php
			// Render discovery document import form.
			$this->render_discovery_form();
			?>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();
				?>
			</form>

			<h4><?php esc_html_e( 'Notes', 'daggerhart-openid-connect-generic' ); ?></h4>

			<p class="description">
				<strong><?php esc_html_e( 'Redirect URI', 'daggerhart-openid-connect-generic' ); ?></strong>
				<code><?php print esc_url( $redirect_uri ); ?></code>
			</p>
			<p class="description">
				<strong><?php esc_html_e( 'Login Button Shortcode', 'daggerhart-openid-connect-generic' ); ?></strong>
				<code>[openid_connect_generic_login_button]</code>
			</p>
			<p class="description">
				<strong><?php esc_html_e( 'Authentication URL Shortcode', 'daggerhart-openid-connect-generic' ); ?></strong>
				<code>[openid_connect_generic_auth_url]</code>
			</p>

			<?php if ( $this->settings->enable_logging ) { ?>
				<h2><?php esc_html_e( 'Logs', 'daggerhart-openid-connect-generic' ); ?></h2>
				<div id="logger-table-wrapper">
					<?php print wp_kses_post( $this->logger->get_logs_table() ); ?>
				</div>

			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Output a standard text field.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_text_field( $field ) {
		?>
		<input type="<?php print esc_attr( $field['type'] ); ?>"
			id="<?php print esc_attr( $field['key'] ); ?>"
			class="large-text<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) === true ) ? ' disabled' : ''; ?>"
			name="<?php print esc_attr( $field['name'] ); ?>"
			<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) === true ) ? ' disabled' : ''; ?>
			value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a checkbox for a boolean setting.
	 *  - hidden field is default value so we don't have to check isset() on save.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_checkbox( $field ) {
		$hidden_value = 0;
		if ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) === true ) {
			$hidden_value = intval( $this->settings->{ $field['key'] } );
		}
		?>
		<input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="<?php print esc_attr( strval( $hidden_value ) ); ?>">
		<input type="checkbox"
			   id="<?php print esc_attr( $field['key'] ); ?>"
				 name="<?php print esc_attr( $field['name'] ); ?>"
				 <?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) === true ) ? ' disabled="disabled"' : ''; ?>
			   value="1"
			<?php checked( $this->settings->{ $field['key'] }, 1 ); ?>>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a select control.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_select( $field ) {
		$current_value = isset( $this->settings->{ $field['key'] } ) ? $this->settings->{ $field['key'] } : '';
		?>
		<select
			id="<?php print esc_attr( $field['key'] ); ?>"
			name="<?php print esc_attr( $field['name'] ); ?>"
			<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) === true ) ? ' disabled' : ''; ?>
			>
			<?php foreach ( $field['options'] as $value => $text ) : ?>
				<option value="<?php print esc_attr( $value ); ?>" <?php selected( $value, $current_value ); ?>><?php print esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output the field description, and example if present.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_field_description( $field ) {
		?>
		<p class="description">
			<?php print wp_kses_post( $field['description'] ); ?>
			<?php if ( isset( $field['example'] ) ) : ?>
				<br/><strong><?php esc_html_e( 'Example', 'daggerhart-openid-connect-generic' ); ?>: </strong>
				<code><?php print esc_html( $field['example'] ); ?></code>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Output the 'Client Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function client_settings_description() {
		esc_html_e( 'Enter your OpenID Connect identity provider settings.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Output the 'WordPress User Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function user_settings_description() {
		esc_html_e( 'Modify the interaction between OpenID Connect and WordPress users.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Output the 'Authorization Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function authorization_settings_description() {
		esc_html_e( 'Control the authorization mechanics of the site.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Output the 'Log Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function log_settings_description() {
		esc_html_e( 'Log information about login attempts through OpenID Connect Generic.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Fetch OpenID Connect discovery document from provider.
	 *
	 * @param string $discovery_url The discovery document URL (.well-known/openid-configuration).
	 *
	 * @return array|WP_Error Array of discovery data on success, WP_Error on failure.
	 */
	private function fetch_discovery_document( $discovery_url ) {
		// Validate URL is provided.
		if ( empty( $discovery_url ) ) {
			return new WP_Error(
				'empty-discovery-url',
				__( 'Please enter a discovery URL.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Validate HTTPS in production.
		$parsed_url = wp_parse_url( $discovery_url );
		if ( ! $parsed_url || ! isset( $parsed_url['scheme'] ) ) {
			return new WP_Error(
				'invalid-discovery-url',
				__( 'Invalid discovery URL format.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Require HTTPS except in local development.
		$is_local_dev = defined( 'WP_DEBUG' ) && WP_DEBUG === true &&
			( ! defined( 'WP_ENVIRONMENT_TYPE' ) || WP_ENVIRONMENT_TYPE === 'local' );

		if ( 'https' !== $parsed_url['scheme'] && ! $is_local_dev ) {
			return new WP_Error(
				'discovery-url-not-https',
				__( 'Discovery URL must use HTTPS in production environments.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Fetch discovery document.
		$response = $this->http_get(
			$discovery_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'discovery-fetch-failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to fetch discovery document: %s', 'daggerhart-openid-connect-generic' ),
					$response->get_error_message()
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'discovery-fetch-failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Discovery document request returned HTTP %d.', 'daggerhart-openid-connect-generic' ),
					$response_code
				)
			);
		}

		// Parse JSON response.
		$body = wp_remote_retrieve_body( $response );
		$discovery = json_decode( $body, true );

		if ( null === $discovery || ! is_array( $discovery ) ) {
			return new WP_Error(
				'discovery-invalid-json',
				__( 'Discovery document is not valid JSON.', 'daggerhart-openid-connect-generic' )
			);
		}

		// Validate required fields are present.
		$required_fields = array( 'authorization_endpoint', 'token_endpoint', 'jwks_uri' );
		$missing_fields = array();

		foreach ( $required_fields as $field ) {
			if ( ! isset( $discovery[ $field ] ) || empty( $discovery[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			return new WP_Error(
				'discovery-missing-fields',
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Discovery document is missing required fields: %s', 'daggerhart-openid-connect-generic' ),
					implode( ', ', $missing_fields )
				)
			);
		}

		return $discovery;
	}

	/**
	 * Populate plugin settings from discovery document.
	 *
	 * Maps discovery document fields to plugin setting keys.
	 * Does not save to database - only updates in-memory values.
	 *
	 * @param array $discovery The discovery document data.
	 *
	 * @return array Array of setting keys that were populated.
	 */
	private function populate_settings_from_discovery( $discovery ) {
		$populated_fields = array();

		// Map discovery fields to plugin settings.
		$field_mapping = array(
			'authorization_endpoint' => 'endpoint_login',
			'token_endpoint'         => 'endpoint_token',
			'userinfo_endpoint'      => 'endpoint_userinfo',
			'jwks_uri'               => 'endpoint_jwks',
			'issuer'                 => 'issuer',
			'end_session_endpoint'   => 'endpoint_end_session',
		);

		foreach ( $field_mapping as $discovery_key => $setting_key ) {
			if ( isset( $discovery[ $discovery_key ] ) && ! empty( $discovery[ $discovery_key ] ) ) {
				// Update the setting value (not saved yet).
				$this->settings->{ $setting_key } = $discovery[ $discovery_key ];
				$populated_fields[] = $setting_key;
			}
		}

		return $populated_fields;
	}

	/**
	 * Handle discovery document import form submission.
	 *
	 * Checks if the discovery form was submitted, validates it,
	 * fetches the discovery document, and populates settings.
	 *
	 * @return void
	 */
	private function handle_discovery_import() {
		// Check if discovery form was submitted.
		if ( ! isset( $_POST['oidc_discovery_submit'] ) ) {
			return;
		}

		// Verify nonce.
		if (
			! isset( $_POST['oidc_discovery_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oidc_discovery_nonce'] ) ), 'oidc_discovery_import' )
		) {
			add_settings_error(
				'openid-connect-generic',
				'invalid-nonce',
				__( 'Security check failed. Please try again.', 'daggerhart-openid-connect-generic' ),
				'error'
			);
			return;
		}

		// Get discovery URL from form.
		$discovery_url = isset( $_POST['oidc_discovery_url'] )
			? esc_url_raw( wp_unslash( $_POST['oidc_discovery_url'] ) )
			: '';

		// Fetch discovery document.
		$discovery = $this->fetch_discovery_document( $discovery_url );

		if ( is_wp_error( $discovery ) ) {
			add_settings_error(
				'openid-connect-generic',
				$discovery->get_error_code(),
				$discovery->get_error_message(),
				'error'
			);
			return;
		}

		// Populate settings from discovery document.
		$populated_fields = $this->populate_settings_from_discovery( $discovery );

		// Log the import.
		$this->logger->log(
			sprintf(
				'Configuration loaded from discovery URL: %s. Populated fields: %s',
				$discovery_url,
				implode( ', ', $populated_fields )
			),
			'discovery-import'
		);

		// Show success message.
		$field_count = count( $populated_fields );
		add_settings_error(
			'openid-connect-generic',
			'discovery-success',
			sprintf(
				/* translators: %d: number of fields populated */
				_n(
					'Configuration loaded successfully! %d field was populated. Review the settings below and click "Save Changes" to apply.',
					'Configuration loaded successfully! %d fields were populated. Review the settings below and click "Save Changes" to apply.',
					$field_count,
					'daggerhart-openid-connect-generic'
				),
				$field_count
			),
			'success'
		);
	}

	/**
	 * Render the discovery document import form.
	 *
	 * Outputs HTML form for importing configuration from discovery document.
	 * Collapsed by default if endpoint_login is already configured.
	 *
	 * @return void
	 */
	private function render_discovery_form() {
		// Auto-expand if plugin is not yet configured.
		$is_configured = ! empty( $this->settings->endpoint_login );
		$open_attribute = $is_configured ? '' : ' open';
		?>
		<details<?php echo esc_attr( $open_attribute ); ?> class="oidc-discovery-section">
			<summary class="oidc-discovery-summary">
				⚡ <?php esc_html_e( 'Quick Setup: Import from Discovery Document', 'daggerhart-openid-connect-generic' ); ?>
			</summary>
			<div class="notice notice-info inline oidc-discovery-content">
				<p>
					<?php esc_html_e( 'Auto-populate endpoint settings from your identity provider\'s OpenID Connect discovery document. After loading, review the populated fields below and click "Save Changes" to apply.', 'daggerhart-openid-connect-generic' ); ?>
				</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'oidc_discovery_import', 'oidc_discovery_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="oidc_discovery_url">
									<?php esc_html_e( 'Discovery URL', 'daggerhart-openid-connect-generic' ); ?>
								</label>
							</th>
							<td>
								<input
									type="url"
									id="oidc_discovery_url"
									name="oidc_discovery_url"
									class="regular-text oidc-discovery-url-input"
									placeholder="https://your-idp.com/.well-known/openid-configuration"
								/>
								<p class="description">
									<?php esc_html_e( 'Enter your identity provider\'s OpenID Connect discovery endpoint URL.', 'daggerhart-openid-connect-generic' ); ?>
									<br>
									<strong><?php esc_html_e( 'Examples:', 'daggerhart-openid-connect-generic' ); ?></strong>
									<br>
									• Auth0: <code>https://{tenant}.{region}.auth0.com/.well-known/openid-configuration</code>
									<br>
									• Keycloak: <code>https://{domain}/realms/{realm}/.well-known/openid-configuration</code>
									<br>
									• Okta: <code>https://{domain}/.well-known/openid-configuration</code>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Load Configuration', 'daggerhart-openid-connect-generic' ), 'secondary', 'oidc_discovery_submit', false ); ?>
				</form>
			</div>
		</details>
		<hr class="oidc-discovery-separator">
		<?php
	}
}
