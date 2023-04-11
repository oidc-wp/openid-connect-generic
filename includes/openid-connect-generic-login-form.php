<?php
/**
 * Login form and login button handlong class.
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
 * Login form and login button handlong.
 *
 * @package OpenID_Connect_Generic
 * @category  Login
 */
class OpenID_Connect_Generic_Login_Form {

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
	 * The class constructor.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings       A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Client_Wrapper  $client_wrapper A plugin client wrapper object instance.
	 */
	public function __construct( $settings, $client_wrapper ) {
		$this->settings = $settings;
		$this->client_wrapper = $client_wrapper;
	}

	/**
	 * Create an instance of the OpenID_Connect_Generic_Login_Form class.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings       A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Client_Wrapper  $client_wrapper A plugin client wrapper object instance.
	 *
	 * @return void
	 */
	public static function register( $settings, $client_wrapper ) {
		$login_form = new self( $settings, $client_wrapper );

		// Alter the login form as dictated by settings.
		add_filter( 'login_message', array( $login_form, 'handle_login_page' ), 99 );

		// Add a shortcode for the login button.
		add_shortcode( 'openid_connect_generic_login_button', array( $login_form, 'make_login_button' ) );

		$login_form->handle_redirect_login_type_auto();
		$login_form->handle_wp_login_and_signup();
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
		$message .= $this->make_login_button();

		return $message;
	}

	/**
	 * Disables built-in login functionality.
	 *
	 * @return void
	 */
	public function handle_wp_login_and_signup() {

		if ( $this->settings->disable_wp_login_and_signup ) {
			// Login functionality (login, signup, password reset) may be implemented on only page, not only wp-login.php;
			// therefore, listen for these hooks globally.
			add_filter( 'authenticate', array( $this, 'disable_authenticate' ), 99, 3 );
			add_filter( 'lostpassword_errors', array( $this, 'disable_lostpassword' ), 99, 2 );
			add_filter( 'registration_errors', array( $this, 'disable_registration' ), 99, 3 );

			// Hide the login form and links to reset password and signup. This is just comsmetic change to prevent user confusion.
			if ( 'wp-login.php' == $GLOBALS['pagenow'] ) {
				add_action( 'login_footer', array( $this, 'remove_login_form_and_links' ), 99 );
			}
		}

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
				'button_text' => __( 'Login with OpenID Connect', 'daggerhart-openid-connect-generic' ),
			),
			$atts,
			'openid_connect_generic_login_button'
		);

		$text = apply_filters( 'openid-connect-generic-login-button-text', $atts['button_text'] );
		$text = esc_html( $text );

		$href = $this->client_wrapper->get_authentication_url( $atts );
		$href = esc_url_raw( $href );

		$login_button = <<<HTML
<div class="openid-connect-login-button" style="margin: 1em 0; text-align: center;">
	<a class="button button-large" href="{$href}">{$text}</a>
</div>
HTML;

		return $login_button;

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
		</script>
		<?php
	}

	/**
	 * Removes the login form from the HTML DOM
	 *
	 * @return void
	 */
	public function remove_login_form_and_links() {
		?>
		<script type="text/javascript">
			(function() {
				var loginForm = document.getElementById("user_login").form;
				var parent = loginForm.parentNode;
				parent.removeChild(loginForm);
				var linksElem = document.getElementById("nav");
				if (linksElem) { linksElem.parentNode.removeChild(linksElem); }
			})();
		</script>
		<?php
	}

	/**
	 * Disables built-in login using username/password
	 *
	 * @param null|WP_User|WP_Error $user     Authenticated user, error or null.
	 * @param string                $username Username or email address.
	 * @param string                $password User password.
	 *
	 * @return null|WP_User|WP_Error
	 */
	public function disable_authenticate( $user, $username, $password ) {

		// We cannot completely disable wp-login.php page, because it is also used
		// to display error messages. Only return error is login attempt with username/password is made.
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		} else {
			return new WP_Error( 'builtin-login-disabled', __( 'Built-in login is disabled.', 'daggerhart-openid-connect-generic' ) );
		}

	}

	/**
	 * Disable built-in password reset functionality.
	 *
	 * @param WP_Error      $errors    A WP_Error object containing any errors generated by using invalid credentials.
	 * @param WP_User|false $user_data WP_User object if found, false if the user does not exist.
	 *
	 * @return WP_Error
	 */
	public function disable_lostpassword( $errors, $user_data ) {

		// Remove any previous errors to prevent possible information disclosure (e.g. existing email/username).
		$errors = new WP_Error( 'builtin-lostpassword-disabled', __( 'Built-in password reset is disabled.', 'daggerhart-openid-connect-generic' ) );
		return $errors;

	}

	/**
	 * Disable built-in signup functionality.
	 *
	 * @param WP_Error $errors               A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email           User's email.
	 *
	 * @return WP_Error
	 */
	public function disable_registration( $errors, $sanitized_user_login, $user_email ) {

		// Remove any previous errors to prevent possible information disclosure (e.g. existing email/username).
		$errors = new WP_Error( 'builtin-signup-disabled', __( 'Built-in signup is disabled.', 'daggerhart-openid-connect-generic' ) );
		return $errors;

	}

}
