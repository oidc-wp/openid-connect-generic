<?php
/**
 * WordPress options handling class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2023 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenId_Connect_Generic_Option_Settings class.
 *
 * WordPress options handling.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 *
 * Legacy Settings:
 *
 * @property string $ep_login    The login endpoint.
 * @property string $ep_token    The token endpoint.
 * @property string $ep_userinfo The userinfo endpoint.
 *
 * OAuth Client Settings:
 *
 * @property string $login_type           How the client (login form) should provide login options.
 * @property string $client_id            The ID the client will be recognized as when connecting the to Identity provider server.
 * @property string $client_secret        The secret key the IDP server expects from the client.
 * @property string $scope                The list of scopes this client should access.
 * @property string $endpoint_login       The IDP authorization endpoint URL.
 * @property string $endpoint_userinfo    The IDP User information endpoint URL.
 * @property string $endpoint_token       The IDP token validation endpoint URL.
 * @property string $endpoint_end_session The IDP logout endpoint URL.
 * @property string $acr_values           The Authentication contract as defined on the IDP.
 *
 * Non-standard Settings:
 *
 * @property bool   $no_sslverify           The flag to enable/disable SSL verification during authorization.
 * @property int    $http_request_timeout   The timeout for requests made to the IDP. Default value is 5.
 * @property string $identity_key           The key in the user claim array to find the user's identification data.
 * @property string $nickname_key           The key in the user claim array to find the user's nickname.
 * @property string $email_format           The key(s) in the user claim array to formulate the user's email address.
 * @property string $displayname_format     The key(s) in the user claim array to formulate the user's display name.
 * @property bool   $identify_with_username The flag which indicates how the user's identity will be determined.
 * @property int    $state_time_limit       The valid time limit of the state, in seconds. Defaults to 180 seconds.
 *
 * Plugin Settings:
 *
 * @property bool $enforce_privacy          The flag to indicates whether a user us required to be authenticated to access the site.
 * @property bool $alternate_redirect_uri   The flag to indicate whether to use the alternative redirect URI.
 * @property bool $token_refresh_enable     The flag whether to support refresh tokens by IDPs.
 * @property bool $link_existing_users      The flag to indicate whether to link to existing WordPress-only accounts or greturn an error.
 * @property bool $create_if_does_not_exist The flag to indicate whether to create new users or not.
 * @property bool $redirect_user_back       The flag to indicate whether to redirect the user back to the page on which they started.
 * @property bool $redirect_on_logout       The flag to indicate whether to redirect to the login screen on session expiration.
 * @property bool $enable_logging           The flag to enable/disable logging.
 * @property int  $log_limit                The maximum number of log entries to keep.
 */
class OpenID_Connect_Generic_Option_Settings {

	/**
	 * WordPress option name/key.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'openid_connect_generic_settings';

	/**
	 * Stored option values array.
	 *
	 * @var array<mixed>
	 */
	private $values;

	/**
	 * Default plugin settings values.
	 *
	 * @var array<mixed>
	 */
	private $default_settings;

	/**
	 * List of settings that can be defined by environment variables.
	 *
	 * @var array<string,string>
	 */
	private $environment_settings = array(
		'client_id'                 => 'OIDC_CLIENT_ID',
		'client_secret'             => 'OIDC_CLIENT_SECRET',
		'endpoint_end_session'      => 'OIDC_ENDPOINT_LOGOUT_URL',
		'endpoint_login'            => 'OIDC_ENDPOINT_LOGIN_URL',
		'endpoint_token'            => 'OIDC_ENDPOINT_TOKEN_URL',
		'endpoint_userinfo'         => 'OIDC_ENDPOINT_USERINFO_URL',
		'login_type'                => 'OIDC_LOGIN_TYPE',
		'scope'                     => 'OIDC_CLIENT_SCOPE',
		'create_if_does_not_exist'  => 'OIDC_CREATE_IF_DOES_NOT_EXIST',
		'enforce_privacy'           => 'OIDC_ENFORCE_PRIVACY',
		'link_existing_users'       => 'OIDC_LINK_EXISTING_USERS',
		'redirect_on_logout'        => 'OIDC_REDIRECT_ON_LOGOUT',
		'redirect_user_back'        => 'OIDC_REDIRECT_USER_BACK',
		'acr_values'                => 'OIDC_ACR_VALUES',
		'enable_logging'            => 'OIDC_ENABLE_LOGGING',
		'log_limit'                 => 'OIDC_LOG_LIMIT',
	);

	/**
	 * The class constructor.
	 *
	 * @param array<mixed> $default_settings  The default plugin settings values.
	 * @param bool         $granular_defaults The granular defaults.
	 */
	public function __construct( $default_settings = array(), $granular_defaults = true ) {
		$this->default_settings = $default_settings;
		$this->values = array();

		$this->values = (array) get_option( self::OPTION_NAME, $this->default_settings );

		// For each defined environment variable/constant be sure the settings key is set.
		foreach ( $this->environment_settings as $key => $constant ) {
			if ( defined( $constant ) ) {
				$this->__set( $key, constant( $constant ) );
			}
		}

		if ( $granular_defaults ) {
			$this->values = array_replace_recursive( $this->default_settings, $this->values );
		}
	}

	/**
	 * Magic getter for settings.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( isset( $this->values[ $key ] ) ) {
			return $this->values[ $key ];
		}
	}

	/**
	 * Magic setter for settings.
	 *
	 * @param string $key   The array key/option name.
	 * @param mixed  $value The option value.
	 *
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->values[ $key ] = $value;
	}

	/**
	 * Magic method to check is an attribute isset.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->values[ $key ] );
	}

	/**
	 * Magic method to clear an attribute.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return void
	 */
	public function __unset( $key ) {
		unset( $this->values[ $key ] );
	}

	/**
	 * Get the plugin settings array.
	 *
	 * @return array
	 */
	public function get_values() {
		return $this->values;
	}

	/**
	 * Get the plugin WordPress options name.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return self::OPTION_NAME;
	}

	/**
	 * Save the plugin options to the WordPress options table.
	 *
	 * @return void
	 */
	public function save() {

		// For each defined environment variable/constant be sure it isn't saved to the database.
		foreach ( $this->environment_settings as $key => $constant ) {
			if ( defined( $constant ) ) {
				$this->__unset( $key );
			}
		}

		update_option( self::OPTION_NAME, $this->values );
	}
}
