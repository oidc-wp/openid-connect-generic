<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * This has been slightly modified (to read environment variables) for use in Docker.
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

/**
 * A helper function to lookup "env_FILE", "env", then fallback
 *
 * @param string          $env     The environment variable name.
 * @param string|int|bool $default The default value to use if no value found.
 *
 * @return string|int|bool
 */
function getenv_docker( $env, $default ) {
	if ( $fileEnv = getenv( $env . '_FILE' ) ) {
		return rtrim( strval( file_get_contents( $fileEnv ) ), '\r\n' );
	}
	if ( ( $val = getenv( $env ) ) !== false ) {
		return $val;
	}

	return $default;
}

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv_docker( 'WORDPRESS_DB_NAME', 'wordpress' ) );

/** MySQL database username */
define( 'DB_USER', getenv_docker( 'WORDPRESS_DB_USER', 'root' ) );

/** MySQL database password */
define( 'DB_PASSWORD', getenv_docker( 'WORDPRESS_DB_PASSWORD', '' ) );

/**
 * Docker image fallback values above are sourced from the official WordPress installation wizard:
 * https://github.com/WordPress/WordPress/blob/f9cc35ebad82753e9c86de322ea5c76a9001c7e2/wp-admin/setup-config.php#L216-L230
 * (However, using "example username" and "example password" in your database is strongly discouraged.  Please use strong, random credentials!)
 */

/** MySQL hostname */
define( 'DB_HOST', getenv_docker( 'WORDPRESS_DB_HOST', 'mysql' ) );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', getenv_docker( 'WORDPRESS_DB_CHARSET', 'utf8' ) );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', getenv_docker( 'WORDPRESS_DB_COLLATE', '' ) );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         getenv_docker( 'WORDPRESS_AUTH_KEY',         '96f6bcee44eacbd3e9a5c3c78f52d615bbe550f1' ) );
define( 'SECURE_AUTH_KEY',  getenv_docker( 'WORDPRESS_SECURE_AUTH_KEY',  'cd0555ee6a32c91e064a499ebb3fd5ceea62e2a3' ) );
define( 'LOGGED_IN_KEY',    getenv_docker( 'WORDPRESS_LOGGED_IN_KEY',    '34b7c03618d67a8d626cd5c78508adfe542e6349' ) );
define( 'NONCE_KEY',        getenv_docker( 'WORDPRESS_NONCE_KEY',        '848d7098eb38eca67857d6e0ee5b3fba5962c7c1' ) );
define( 'AUTH_SALT',        getenv_docker( 'WORDPRESS_AUTH_SALT',        '881a5f5689e7c265b8dd8c6ae4fdff54888e06fa' ) );
define( 'SECURE_AUTH_SALT', getenv_docker( 'WORDPRESS_SECURE_AUTH_SALT', '2275b260e683602a91c8d19f6a05a6362bd5a91f' ) );
define( 'LOGGED_IN_SALT',   getenv_docker( 'WORDPRESS_LOGGED_IN_SALT',   '7cf0086a484ed4eabd1ce1b09a6a59a11297c61f' ) );
define( 'NONCE_SALT',       getenv_docker( 'WORDPRESS_NONCE_SALT',       '2fc8e8507b204f6e69e2c750a5ceaed9f5951736' ) );
// (See also https://wordpress.stackexchange.com/a/152905/199287)

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = getenv_docker( 'WORDPRESS_TABLE_PREFIX', 'wp_' );

// Configure site domain name for Codespaces if present.
$is_codespaces = boolval( getenv( 'CODESPACES' ) );
$codespace_name = getenv_docker( 'CODESPACE_NAME', '' );
$codespace_domain = getenv_docker( 'GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN', '' );
if ( $is_codespaces ) {
	$site_domain = $codespace_name . '-8080.' . $codespace_domain;
	define( 'WP_HOME', 'https://' . $codespace_name . '-8080.' . $codespace_domain );
} else {
	$site_domain = 'localhost';
	define( 'WP_HOME', 'http://localhost:8080' );
}

defined( 'WP_SITEURL' ) || define( 'WP_SITEURL', rtrim( WP_HOME, '/' ) . '/wp' );

// If we're behind a proxy server and using HTTPS, we need to alert WordPress of that fact
// see also http://codex.wordpress.org/Administration_Over_SSL#Using_a_Reverse_Proxy
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
	$_SERVER['HTTP_HOST'] = $site_domain;
}
// (we include this by default because reverse proxying is extremely common in container environments)

if ( $configExtra = getenv_docker( 'WORDPRESS_CONFIG_EXTRA', '' ) ) {
	eval( $configExtra );
}

define( 'WP_LOCAL_DEV', true );
defined( 'WP_ENVIRONMENT_TYPE' ) || define( 'WP_ENVIRONMENT_TYPE', 'development' );
define( 'PHP_INI_MEMORY_LIMIT', '512M' );
define( 'WP_MEMORY_LIMIT', '512M' );

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );

define( 'SMTP_HOST', 'mailhog' );
define( 'SMTP_PORT', 1025 );
define( 'SMTP_FROM', 'wordpress@forumone.com' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/wp/' );

/**
 * Define content constants only if needed, or network install screen will complain for no reason
 */
$custom_content_dir = realpath( __DIR__ . '/wp-content' ) !== realpath( ABSPATH . '/wp-content' );
if ( $custom_content_dir && ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', realpath( __DIR__ . '/wp-content' ) );
}
if ( $custom_content_dir && ! ( defined( 'MULTISITE' ) && MULTISITE ) && ! defined( 'WP_CONTENT_URL' ) ) {
	define( 'WP_CONTENT_URL', rtrim( WP_HOME, '/' ) . '/wp-content' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
