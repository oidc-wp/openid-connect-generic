<?php
/**
 * Plugin Admin settings page class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
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
	function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {

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
	 * Hook the settings page into WordPress.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   A plugin logger object instance.
	 *
	 * @return void
	 */
	static public function register( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
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

				case 'textarea':
					$callback = 'do_textarea';
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
			'identity_key'     => array(
				'title'       => __( 'Identity Key', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Where in the user claim array to find the user\'s identification data. Possible standard values: preferred_username, name, or sub. If you\'re having trouble, use "sub".', 'daggerhart-openid-connect-generic' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'no_sslverify'      => array(
				'title'       => __( 'Disable SSL Verify', 'daggerhart-openid-connect-generic' ),
				'description' => sprintf( __( 'Do not require SSL verification during authorization. The OAuth extension uses curl to make the request. By default CURL will generally verify the SSL certificate to see if its valid an issued by an accepted CA. This setting disabled that verification.%1$sNot recommended for production sites.%2$s', 'daggerhart-openid-connect-generic' ), '<br><strong>', '</strong>' ),
				'type'        => 'checkbox',
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
				'section'     => 'authorization_settings',
			),
			'unprotected_urls'   => array(
				'title'       => __( 'Unprotected URLs', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Skip privacy for the URLs in the list separated by comma.', 'daggerhart-openid-connect-generic'  ),
				'type'        => 'textarea',
				'section'     => 'authorization_settings'
			),
			'protected_urls'   => array(
				'title'       => __( 'Protected URLs', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Enforce privacy just for the URLs in the list separated by comma.', 'daggerhart-openid-connect-generic'  ),
				'type'        => 'textarea',
				'section'     => 'authorization_settings'
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
			'link_existing_users'   => array(
				'title'       => __( 'Link Existing Users', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'If a WordPress account already exists with the same identity as a newly-authenticated user over OpenID Connect, login as that user instead of generating an error.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'create_if_does_not_exist'   => array(
				'title'       => __( 'Create user if does not exist', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'If the user identity is not link to an existing Wordpress user, it is created. If this setting is not enabled and if the user authenticates with an account which is not link to an existing Wordpress user then the authentication failed', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'redirect_user_back'   => array(
				'title'       => __( 'Redirect Back to Origin Page', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'After a successful OpenID Connect authentication, this will redirect the user back to the page on which they clicked the OpenID Connect login button. This will cause the login process to proceed in a traditional WordPress fashion. For example, users logging in through the default wp-login.php page would end up on the WordPress Dashboard and users logging in through the WooCommerce "My Account" page would end up on their account page.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'redirect_on_logout'   => array(
				'title'       => __( 'Redirect to the login screen when session is expired', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'When enabled, this will automatically redirect the user back to the WordPress login page if their access token has expired.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'enable_logging'    => array(
				'title'       => __( 'Enable Logging', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Very simple log messages for debugging purposes.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'checkbox',
				'section'     => 'log_settings',
			),
			'log_limit'         => array(
				'title'       => __( 'Log Limit', 'daggerhart-openid-connect-generic' ),
				'description' => __( 'Number of items to keep in the log. These logs are stored as an option in the database, so space is limited.', 'daggerhart-openid-connect-generic' ),
				'type'        => 'number',
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
		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();

				// Simple debug to view settings array.
				if ( isset( $_GET['debug'] ) ) {
					var_dump( $this->settings->get_values() );
				}
				?>
			</form>

			<h4><?php _e( 'Notes', 'daggerhart-openid-connect-generic' ); ?></h4>

			<p class="description">
				<strong><?php _e( 'Redirect URI', 'daggerhart-openid-connect-generic' ); ?></strong>
				<code><?php print $redirect_uri; ?></code>
			</p>
			<p class="description">
				<strong><?php _e( 'Login Button Shortcode', 'daggerhart-openid-connect-generic' ); ?></strong>
				<code>[openid_connect_generic_login_button]</code>
			</p>
			<p class="description">
				<strong><?php _e( 'Authentication URL Shortcode', 'daggerhart-openid-connect-generic' ); ?></strong>
				<code>[openid_connect_generic_auth_url]</code>
			</p>

			<?php if ( $this->settings->enable_logging ) { ?>
				<h2><?php _e( 'Logs', 'daggerhart-openid-connect-generic' ); ?></h2>
				<div id="logger-table-wrapper">
					<?php print $this->logger->get_logs_table(); ?>
				</div>

			<?php } ?>
		</div>
		
		<script>
			if ( document.getElementById('enforce_privacy').checked )
				jQuery('#protected_urls').closest('tr').hide();
			else
				jQuery('#unprotected_urls').closest('tr').hide();

			jQuery('#enforce_privacy').click(function() {
				jQuery("#protected_urls").closest('tr').toggle();
				jQuery("#unprotected_urls").closest('tr').toggle();
			});
		</script>

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
				<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) ) ? ' disabled' : ''; ?>
			  id="<?php print esc_attr( $field['key'] ); ?>"
			  class="large-text<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) ) ? ' disabled' : ''; ?>"
			  name="<?php print esc_attr( $field['name'] ); ?>"
			  value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a standard textarea
	 *
	 * @param $field
	 */
	public function do_textarea( $field ) {
		?>
		<textarea id="<?php print esc_attr( $field['key'] ); ?>"
		       class="large-text" rows="5"
		       name="<?php print esc_attr( $field['name'] ); ?>"><?php print esc_attr( $this->settings->{ $field['key'] } ); ?></textarea>
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
		?>
		<input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="0">
		<input type="checkbox"
			   id="<?php print esc_attr( $field['key'] ); ?>"
			   name="<?php print esc_attr( $field['name'] ); ?>"
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
	function do_select( $field ) {
		$current_value = isset( $this->settings->{ $field['key'] } ) ? $this->settings->{ $field['key'] } : '';
		?>
		<select name="<?php print esc_attr( $field['name'] ); ?>">
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
			<?php print $field['description']; ?>
			<?php if ( isset( $field['example'] ) ) : ?>
				<br/><strong><?php _e( 'Example', 'daggerhart-openid-connect-generic' ); ?>: </strong>
				<code><?php print $field['example']; ?></code>
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
		_e( 'Enter your OpenID Connect identity provider settings.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Output the 'WordPress User Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function user_settings_description() {
		_e( 'Modify the interaction between OpenID Connect and WordPress users.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Output the 'Authorization Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function authorization_settings_description() {
		_e( 'Control the authorization mechanics of the site.', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Output the 'Log Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function log_settings_description() {
		_e( 'Log information about login attempts through OpenID Connect Generic.', 'daggerhart-openid-connect-generic' );
	}
}
