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
	 * Whether the "or" divider has been printed on this page.
	 *
	 * @var bool
	 */
	private $divider_used = false;

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

		// Add the login button to the WooCommerce login forms, or skip My Account's form with auto login.
		if ( 'auto' === $settings->woocommerce_login ) {
			add_action( 'template_redirect', array( $login_form, 'handle_redirect_woocommerce_auto' ) );
			add_filter( 'woocommerce_logout_default_redirect_url', array( $login_form, 'woocommerce_logout_redirect_url' ) );
		} elseif ( in_array( $settings->woocommerce_login, array( 'start', 'end' ), true ) ) {
			add_action( 'woocommerce_login_form_' . $settings->woocommerce_login, array( $login_form, 'print_login_button' ) );
			add_action( 'wp_footer', array( $login_form, 'print_divider_styles' ) );
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
	 * WooCommerce Auto Login redirect.
	 *
	 * Sends logged out visitors on the WooCommerce My Account page straight to the
	 * identity provider instead of showing the WooCommerce login form.
	 *
	 * @return void
	 */
	public function handle_redirect_woocommerce_auto() {
		if ( is_user_logged_in()
			|| ! function_exists( 'is_account_page' ) || ! is_account_page()
			// Keep the WooCommerce lost password and reset password forms.
			|| is_wc_endpoint_url( 'lost-password' )
			// Don't redirect form submissions.
			|| ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		wp_redirect( $this->client_wrapper->get_authentication_url( array( 'redirect_to' => wc_get_page_permalink( 'myaccount' ) ) ) );
		exit;
	}

	/**
	 * Send users to the home page after logging out of WooCommerce.
	 *
	 * WooCommerce returns to My Account by default, which would send them straight back to
	 * the identity provider. Other logout redirects are left alone.
	 *
	 * @param string $redirect_url The URL WooCommerce redirects to after logging out.
	 *
	 * @return string
	 */
	public function woocommerce_logout_redirect_url( $redirect_url ) {
		if ( wc_get_page_permalink( 'myaccount' ) === $redirect_url ) {
			return home_url( '/' );
		}
		return $redirect_url;
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

		// Use admin-configured button text, or fall back to default.
		$default_button_text = ! empty( trim( $this->settings->login_button_text ?? '' ) )
			? $this->settings->login_button_text
			: __( 'Login with OpenID Connect', 'daggerhart-openid-connect-generic' );

		$atts = shortcode_atts(
			array(
				'button_text' => $default_button_text,
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
	<a class="button button-large" href="{$href}">{$text}</a>
</div>
HTML;

		return $login_button;
	}

	/**
	 * Print the login button with an "or" divider between it and the login form.
	 *
	 * Used on the WooCommerce My Account and checkout login forms, and returns the user to
	 * the page they logged in from.
	 *
	 * @return void
	 */
	public function print_login_button() {
		$this->divider_used = true;

		$divider = '<div class="openid-connect-login-divider"><span>' . esc_html__( 'or', 'daggerhart-openid-connect-generic' ) . '</span></div>';
		$button  = $this->make_login_button( array( 'redirect_to' => get_permalink() ) );

		// At the top of the form the divider goes under the button, at the bottom it goes above.
		$output = 'woocommerce_login_form_start' === current_action() ? $button . $divider : $divider . $button;

		echo wp_kses_post( $output );
	}

	/**
	 * Print the CSS for the "or" divider.
	 *
	 * @return void
	 */
	public function print_divider_styles() {
		if ( ! $this->divider_used ) {
			return;
		}
		echo '<style id="openid-connect-login-divider">.openid-connect-login-divider{display:flex;align-items:center;gap:0.75em;margin:1.25em 0;color:#767676;font-size:0.9em}.openid-connect-login-divider::before,.openid-connect-login-divider::after{content:"";flex:1;border-top:1px solid #ddd}</style>';
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
