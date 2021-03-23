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
	function __construct( $settings, $client_wrapper ) {
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
	static public function register( $settings, $client_wrapper ) {
		$login_form = new self( $settings, $client_wrapper );

		// Alter the login form as dictated by settings.
		add_filter( 'login_message', array( $login_form, 'handle_login_page' ), 99 );

		// Add a shortcode for the login button.
		add_shortcode( 'openid_connect_generic_login_button', array( $login_form, 'make_login_button' ) );

		$login_form->handle_redirect_login_type_auto();
	}

	/**
	 * Auto Login redirect.
	 *
	 * @return void
	 */
	function handle_redirect_login_type_auto() {

		if ( 'wp-login.php' == $GLOBALS['pagenow']
			&& ( 'auto' == $this->settings->login_type || ! empty( $_GET['force_redirect'] ) )
			// Don't send users to the IDP on logout or post password protected authentication.
			&& ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'logout', 'postpass' ) ) )
			&& ! isset( $_POST['wp-submit'] ) ) {
			if ( ! isset( $_GET['login-error'] ) ) {
				$redirect_to = $this->get_redirect_to();
				if ( empty( $redirect_to ) ) {
					return;
				}
				wp_redirect( $this->client_wrapper->get_authentication_url( array( 'redirect_to' => $redirect_to ) ) );
				exit;
			} else {
				add_action( 'login_footer', array( $this, 'remove_login_form' ), 99 );
			}
		}

	}

	/**
	 * Get the client login redirect.
	 *
	 * @return string
	 */
	function get_redirect_to() {
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' == $GLOBALS['pagenow'] && isset( $_GET['action'] ) && 'logout' === $_GET['action'] ) {
			return '';
		}

		// Default redirect to the homepage.
		$redirect_url = home_url( esc_url( add_query_arg( null, null ) ) );

		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' == $GLOBALS['pagenow'] ) {
			// If using the login form, default redirect to the admin dashboard.
			$redirect_url = admin_url();
		}

		// Record the URL of the redirect_to if set to redirect back to origin page.
		if ( $this->settings->redirect_user_back ) {
			if ( isset( $_REQUEST['redirect_to'] ) ) {
				$redirect_url = esc_url_raw( $_REQUEST['redirect_to'] );
			}
		}

		// This hook is being deprecated with the move away from cookies.
		$redirect_url = apply_filters_deprecated(
			'openid-connect-generic-cookie-redirect-url',
			array( $redirect_url ),
			'3.8.2',
			'openid-connect-generic-client-redirect-to'
		);

		// This is the new hook to use with the transients version of redirection.
		return apply_filters( 'openid-connect-generic-client-redirect-to', $redirect_url );
	}

	/**
	 * Implements filter login_message.
	 *
	 * @param string $message The text message to display on the login page.
	 *
	 * @return string
	 */
	function handle_login_page( $message ) {

		if ( isset( $_GET['login-error'] ) ) {
			$error_message = ! empty( $_GET['message'] ) ? $_GET['message'] : 'Unknown error.';
			$message .= $this->make_error_output( $_GET['login-error'], $error_message );
		}

		// Login button is appended to existing messages in case of error.
		$message .= $this->make_login_button();

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
	function make_error_output( $error_code, $error_message ) {

		ob_start();
		?>
		<div id="login_error">
			<strong><?php printf( __( 'ERROR (%1$s)', 'daggerhart-openid-connect-generic' ), $error_code ); ?>: </strong>
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
	function make_login_button( $atts = array() ) {
		$button_text = __( 'Login with OpenID Connect', 'daggerhart-openid-connect-generic' );
		if ( ! empty( $atts['button_text'] ) ) {
			$button_text = $atts['button_text'];
		}

		$text = apply_filters( 'openid-connect-generic-login-button-text', $button_text );
		if ( empty( $atts['redirect_to'] ) ) {
			$atts['redirect_to'] = $this->get_redirect_to();
		}
		$href = $this->client_wrapper->get_authentication_url( $atts );

		ob_start();
		?>
		<div class="openid-connect-login-button" style="margin: 1em 0; text-align: center;">
			<a class="button button-large" href="<?php print esc_url( $href ); ?>"><?php print $text; ?></a>
		</div>
		<?php
		return wp_kses_post( ob_get_clean() );
	}

	/**
	 * Removes the login form from the HTML DOM
	 *
	 * @return void
	 */
	function remove_login_form() {
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
