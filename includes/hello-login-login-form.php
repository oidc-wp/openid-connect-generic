<?php
/**
 * Login form and login button handlong class.
 *
 * @package   Hello_Login
 * @category  Login
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Hello_Login_Login_Form class.
 *
 * Login form and login button handlong.
 *
 * @package Hello_Login
 * @category  Login
 */
class Hello_Login_Login_Form {

	/**
	 * Plugin settings object.
	 *
	 * @var Hello_Login_Option_Settings
	 */
	private $settings;

	/**
	 * Plugin client wrapper instance.
	 *
	 * @var Hello_Login_Client_Wrapper
	 */
	private $client_wrapper;

	/**
	 * The class constructor.
	 *
	 * @param Hello_Login_Option_Settings $settings       A plugin settings object instance.
	 * @param Hello_Login_Client_Wrapper  $client_wrapper A plugin client wrapper object instance.
	 */
	public function __construct( $settings, $client_wrapper ) {
		$this->settings = $settings;
		$this->client_wrapper = $client_wrapper;
	}

	/**
	 * Create an instance of the Hello_Login_Login_Form class.
	 *
	 * @param Hello_Login_Option_Settings $settings       A plugin settings object instance.
	 * @param Hello_Login_Client_Wrapper  $client_wrapper A plugin client wrapper object instance.
	 *
	 * @return void
	 */
	public static function register( $settings, $client_wrapper ) {
		$login_form = new self( $settings, $client_wrapper );

		// Alter the login form as dictated by settings.
		add_filter( 'login_message', array( $login_form, 'handle_login_page' ), 99 );

		// Add a shortcode for the login button.
		add_shortcode( 'hello_login_button', array( $login_form, 'make_login_button' ) );

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

		if ( ! empty( $this->settings->client_id ) ) {
			// Login button is appended to existing messages in case of error.
			$message .= $this->make_login_button();

			// Login form toggle is appended right after the button
			$message .= $this->make_login_form_toggle();
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
			<strong><?php printf( esc_html__( 'ERROR (%1$s)', 'hello-login' ), esc_html( $error_code ) ); ?>: </strong>
			<?php print esc_html( $error_message ); ?>
		</div>
		<?php
		return wp_kses_post( ob_get_clean() );
	}

	/**
	 * Create a login button (link).
	 *
	 * @param array $atts Array of optional attributes to override login button
	 * functionality when used by shortcode.
	 *
	 * @return string
	 */
	public function make_login_button( $atts = array() ) {

		$atts = shortcode_atts(
				array(
						'button_text' => __( 'ō   Continue with Hellō', 'hello-login' ),
				),
				$atts,
				'hello_login_button'
		);

		$href = $this->client_wrapper->get_authentication_url( $atts );
		$href = esc_url_raw( $href );

		ob_start();
		?>
		<div class="hello-container" style="display: block; text-align: center;">
			<button class="hello-btn" onclick="window.location.href = '<?php print esc_attr( $href ); ?>'"></button>
			<button class="hello-about"></button>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Create a toggle for the login form.
	 *
	 * @return string
	 */
	public function make_login_form_toggle() {
		wp_enqueue_script( 'hello-username-password-form', plugin_dir_url( __DIR__ ) . 'js/scripts-login.js' );
		wp_enqueue_style( 'hello-username-password-form', plugin_dir_url( __DIR__ ) . 'css/styles-login.css' );

		ob_start();
		?>
		<button id="login-form-toggle" onclick="toggleLoginForm()"></button>
		<?php

		return ob_get_clean();
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
