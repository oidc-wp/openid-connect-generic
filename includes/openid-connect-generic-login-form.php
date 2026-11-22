<?php
/**
 * Login form and login button handling class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Login
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Login_Form class.
 *
 * Login form and login button handling.
 *
 * @package OpenID_Connect_Generic
 * @category  Login
 */
class OpenID_Connect_Generic_Login_Form {

	/**
	 * The Roles Allowed to Use a Password value for users with no role on the site.
	 *
	 * @var string
	 */
	const NO_ROLE = '_no_role';

	/**
	 * Plugin settings object.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * Plugin client wrapper instance.
	 *
	 * @var OpenID_Connect_Generic_Client_Wrapper
	 */
	private $client_wrapper;

	/**
	 * The client object instance.
	 *
	 * @var OpenID_Connect_Generic_Client
	 */
	private $client;

	/**
	 * Whether a login button with an icon has been made on this page.
	 *
	 * @var bool
	 */
	private $icon_used = false;

	/**
	 * The ID of the user the current login authenticated with an Application Password.
	 *
	 * @var int
	 */
	private $application_password_user_id = 0;

	/**
	 * The class constructor.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings       A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Client_Wrapper  $client_wrapper A plugin client wrapper object instance.
	 * @param OpenID_Connect_Generic_Client          $client         A plugin client object instance.
	 */
	public function __construct( $settings, $client_wrapper, $client ) {
		$this->settings = $settings;
		$this->client_wrapper = $client_wrapper;
		$this->client = $client;
	}

	/**
	 * Create an instance of the OpenID_Connect_Generic_Login_Form class.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings       A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Client_Wrapper  $client_wrapper A plugin client wrapper object instance.
	 * @param OpenID_Connect_Generic_Client          $client         A plugin client object instance.
	 *
	 * @return void
	 */
	public static function register( $settings, $client_wrapper, $client ) {
		$login_form = new self( $settings, $client_wrapper, $client );

		// Alter the login form as dictated by settings.
		add_filter( 'login_message', array( $login_form, 'handle_login_page' ), 99 );

		// Add a shortcode for the login button.
		add_shortcode( 'openid_connect_generic_login_button', array( $login_form, 'make_login_button' ) );

		// Print the CSS for the login button icon.
		add_action( 'wp_footer', array( $login_form, 'print_icon_styles' ) );
		add_action( 'login_footer', array( $login_form, 'print_icon_styles' ) );

		// Block password logins and password resets for the users set by the Password Login setting.
		if ( ! empty( $settings->password_login ) ) {
			add_filter( 'authenticate', array( $login_form, 'start_password_login_check' ), PHP_INT_MIN );
			add_action( 'application_password_did_authenticate', array( $login_form, 'record_application_password_login' ) );
			add_filter( 'authenticate', array( $login_form, 'block_password_login' ), 30 );
			add_filter( 'allow_password_reset', array( $login_form, 'block_password_reset' ), 10, 2 );
		}

		$login_form->handle_redirect_login_type_auto();
	}

	/**
	 * Auto Login redirect.
	 *
	 * @return void
	 */
	public function handle_redirect_login_type_auto() {

		if ( 'wp-login.php' == $GLOBALS['pagenow']
			&& ( 'auto' == $this->settings->login_type || ! empty( $_GET['force_redirect'] ) )
			// Don't send users to the IDP on logout or post password protected authentication.
			&& ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'logout', 'postpass' ) ) )
			// Let a login started from a stored state continue to the IDP.
			&& ! isset( $_GET[ OpenID_Connect_Generic_Client_Wrapper::LOGIN_START_QUERY_VAR ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP Login Form doesn't have a nonce.
			&& ! isset( $_POST['wp-submit'] ) ) {
			if ( ! isset( $_GET['login-error'] ) ) {
				wp_redirect( $this->client_wrapper->get_authentication_url() );
				exit;
			} else {
				add_action( 'login_footer', array( $this, 'remove_login_form' ), 99 );
			}
		}
	}

	/**
	 * Forget any earlier Application Password login when a new login starts.
	 *
	 * A request can log in more than once, for example with XML-RPC system.multicall,
	 * so an Application Password login must only count for the login it happened in.
	 *
	 * @param WP_User|WP_Error|null $user The result of the earlier authentication checks.
	 *
	 * @return WP_User|WP_Error|null
	 */
	public function start_password_login_check( $user ) {
		$this->application_password_user_id = 0;
		return $user;
	}

	/**
	 * Record the user the current login authenticated with an Application Password.
	 *
	 * @param WP_User $user The user who authenticated.
	 *
	 * @return void
	 */
	public function record_application_password_login( $user ) {
		$this->application_password_user_id = $user instanceof WP_User ? $user->ID : 0;
	}

	/**
	 * Block password logins for users who must log in with OpenID Connect.
	 *
	 * Runs after WordPress has checked the password, so a wrong password still gets the
	 * normal error and nothing reveals which users must use OpenID Connect. OpenID Connect
	 * logins don't use this filter, and a user's own Application Passwords are still allowed.
	 *
	 * @param WP_User|WP_Error|null $user The result of the earlier authentication checks.
	 *
	 * @return WP_User|WP_Error|null
	 */
	public function block_password_login( $user ) {
		if ( ! $user instanceof WP_User || $user->ID === $this->application_password_user_id ) {
			return $user;
		}

		if ( ! $this->is_password_login_blocked( $user ) ) {
			return $user;
		}

		return new WP_Error( 'password-login-blocked', $this->get_password_login_blocked_message() );
	}

	/**
	 * Block password resets for users who must log in with OpenID Connect.
	 *
	 * Stops reset emails being sent for a password the user can't log in with.
	 *
	 * @param bool|WP_Error $allow   Whether the password can be reset.
	 * @param int           $user_id The ID of the user resetting their password.
	 *
	 * @return bool|WP_Error
	 */
	public function block_password_reset( $allow, $user_id ) {
		$user = get_userdata( $user_id );
		if ( true !== $allow || ! $user instanceof WP_User || ! $this->is_password_login_blocked( $user ) ) {
			return $allow;
		}

		return new WP_Error( 'password-reset-blocked', $this->get_password_login_blocked_message() );
	}

	/**
	 * Whether the Password Login setting blocks this user from using a password.
	 *
	 * @param WP_User $user The user.
	 *
	 * @return bool
	 */
	private function is_password_login_blocked( WP_User $user ) {
		if ( 'linked' === $this->settings->password_login ) {
			return (bool) get_user_option( 'openid-connect-generic-subject-identity', $user->ID );
		}

		$allowed_roles = (array) $this->settings->password_login_roles;

		// A user with no role on this site, for example a multisite super admin, is blocked unless ticked.
		if ( empty( $user->roles ) ) {
			return ! in_array( self::NO_ROLE, $allowed_roles, true );
		}

		// Any role not ticked under Roles Allowed to Use a Password.
		return (bool) array_diff( $user->roles, $allowed_roles );
	}

	/**
	 * The message shown to users whose password login or password reset is blocked.
	 *
	 * @return string
	 */
	private function get_password_login_blocked_message() {
		$button_text = apply_filters( 'openid-connect-generic-login-button-text', $this->get_login_button_text() );

		// translators: %s is the text of the OpenID Connect login button.
		return sprintf( __( 'Please log in with the "%s" button.', 'daggerhart-openid-connect-generic' ), esc_html( $button_text ) );
	}

	/**
	 * Implements filter login_message.
	 *
	 * @param string $message The text message to display on the login page.
	 *
	 * @return string
	 */
	public function handle_login_page( $message ) {

		if ( isset( $_GET['login-error'] ) ) {
			$error_message = ! empty( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : 'Unknown error.';
			$message .= $this->make_error_output( sanitize_text_field( wp_unslash( $_GET['login-error'] ) ), $error_message );
		}

		// Login button is appended to existing messages in case of error.
		if ( 'shortcode' !== $this->settings->login_type ) {
			$message .= $this->make_login_button();
		}

		return $message;
	}

	/**
	 * Display an error message to the user.
	 *
	 * @param string $error_code    The error code.
	 * @param string $error_message The error message test.
	 *
	 * @return string
	 */
	public function make_error_output( $error_code, $error_message ) {

		ob_start();
		?>
		<div id="login_error"><?php // translators: %1$s is the error code from the IDP. ?>
			<strong><?php printf( esc_html__( 'ERROR (%1$s)', 'daggerhart-openid-connect-generic' ), esc_html( $error_code ) ); ?>: </strong>
			<?php print esc_html( $error_message ); ?>
		</div>
		<?php
		return wp_kses_post( ob_get_clean() );
	}

	/**
	 * The admin-configured login button text, or the default text.
	 *
	 * @return string
	 */
	private function get_login_button_text() {
		return ! empty( trim( $this->settings->login_button_text ?? '' ) )
			? $this->settings->login_button_text
			: __( 'Login with OpenID Connect', 'daggerhart-openid-connect-generic' );
	}

	/**
	 * Create a login button (link).
	 *
	 * @param array $atts Array of optional attributes to override login buton
	 * functionality when used by shortcode.
	 *
	 * @return string
	 */
	public function make_login_button( $atts = array() ) {

		$atts = shortcode_atts(
			array(
				'button_text' => $this->get_login_button_text(),
				'endpoint_login' => $this->settings->endpoint_login,
				'scope' => $this->settings->scope,
				'client_id' => $this->settings->client_id,
				'redirect_uri' => $this->client->get_redirect_uri(),
				'redirect_to' => $this->client_wrapper->get_redirect_to(),
				'acr_values' => $this->settings->acr_values,
			),
			$atts,
			'openid_connect_generic_login_button'
		);

		$text = apply_filters( 'openid-connect-generic-login-button-text', $atts['button_text'] );
		$text = esc_html( $text );

		// The icon is drawn by print_icon_styles() in the button's text colour.
		$icon     = '';
		$icon_url = $this->settings->login_button_icon;
		if ( ! empty( $icon_url ) ) {
			$this->icon_used = true;
			$icon = '<span class="openid-connect-login-button-icon" aria-hidden="true"></span>';
		}

		$href = $this->client_wrapper->get_authentication_url(
			array(
				'endpoint_login' => $atts['endpoint_login'],
				'scope' => $atts['scope'],
				'client_id' => $atts['client_id'],
				'redirect_uri' => $atts['redirect_uri'],
				'redirect_to' => $atts['redirect_to'],
				'acr_values' => $atts['acr_values'],
			)
		);
		$href = esc_url_raw( $href );

		$login_button = <<<HTML
<div class="openid-connect-login-button" style="margin: 1em 0; text-align: center;">
	<a class="button button-large" href="{$href}">{$icon}{$text}</a>
</div>
HTML;

		return $login_button;
	}

	/**
	 * Print the CSS that draws the login button icon in the button's text colour.
	 *
	 * @return void
	 */
	public function print_icon_styles() {
		// esc_url_raw() removes quotes and angle brackets, so the URL cannot break out of the CSS.
		$icon_url = esc_url_raw( $this->settings->login_button_icon );
		if ( ! $this->icon_used || empty( $icon_url ) ) {
			return;
		}
		$mask = 'url("' . $icon_url . '") center/contain no-repeat';
		echo '<style id="openid-connect-login-button-icon">.openid-connect-login-button-icon{display:inline-block;width:1.6em;height:1.6em;margin-right:0.5em;vertical-align:middle;background-color:currentColor;-webkit-mask:' . $mask . ';mask:' . $mask . '}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Removes the login form from the HTML DOM
	 *
	 * @return void
	 */
	public function remove_login_form() {
		?>
		<script type="text/javascript">
			(function() {
				var loginForm = document.getElementById("user_login").form;
				var parent = loginForm.parentNode;
				parent.removeChild(loginForm);
			})();
		</script>
		<?php
	}
}
