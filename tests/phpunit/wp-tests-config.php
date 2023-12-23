<?php
// a helper function to lookup "env_FILE", "env", then fallback
function getenv_docker($env, $default) {
	if ($fileEnv = getenv($env . '_FILE')) {
		return rtrim(file_get_contents($fileEnv), "\r\n");
	}
	else if (($val = getenv($env)) !== false) {
		return $val;
	}
	else {
		return $default;
	}
}


/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', '/app/wp/' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'default' );

/*
 * Test with multisite enabled.
 * Alternatively, use the tests/phpunit/multisite.xml configuration file.
 */
// define( 'WP_TESTS_MULTISITE', true );

/*
 * Force known bugs to be run.
 * Tests with an associated Trac ticket that is still open are normally skipped.
 */
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

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
// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', true );

// ** MySQL settings ** //

/*
 * This configuration file will be used by the copy of WordPress being tested.
 * wordpress/wp-config.php will be ignored.
 *
 * WARNING WARNING WARNING!
 * These tests will DROP ALL TABLES in the database with the prefix named below.
 * DO NOT use a production database or one that is shared with something else.
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv_docker('WORDPRESS_TEST_DB_NAME', 'wordpress_test') );

/** MySQL database username */
define( 'DB_USER', getenv_docker('WORDPRESS_DB_USER', 'root') );

/** MySQL database password */
define( 'DB_PASSWORD', getenv_docker('WORDPRESS_DB_PASSWORD', 'password') );

/** MySQL hostname */
define( 'DB_HOST', getenv_docker('WORDPRESS_DB_HOST', 'db') );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', getenv_docker('WORDPRESS_DB_CHARSET', 'utf8') );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', getenv_docker('WORDPRESS_DB_COLLATE', '') );

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = getenv_docker('WORDPRESS_TABLE_PREFIX', 'wptests_');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         getenv_docker('WORDPRESS_AUTH_KEY',         '96f6bcee44eacbd3e9a5c3c78f52d615bbe550f1') );
define( 'SECURE_AUTH_KEY',  getenv_docker('WORDPRESS_SECURE_AUTH_KEY',  'cd0555ee6a32c91e064a499ebb3fd5ceea62e2a3') );
define( 'LOGGED_IN_KEY',    getenv_docker('WORDPRESS_LOGGED_IN_KEY',    '34b7c03618d67a8d626cd5c78508adfe542e6349') );
define( 'NONCE_KEY',        getenv_docker('WORDPRESS_NONCE_KEY',        '848d7098eb38eca67857d6e0ee5b3fba5962c7c1') );
define( 'AUTH_SALT',        getenv_docker('WORDPRESS_AUTH_SALT',        '881a5f5689e7c265b8dd8c6ae4fdff54888e06fa') );
define( 'SECURE_AUTH_SALT', getenv_docker('WORDPRESS_SECURE_AUTH_SALT', '2275b260e683602a91c8d19f6a05a6362bd5a91f') );
define( 'LOGGED_IN_SALT',   getenv_docker('WORDPRESS_LOGGED_IN_SALT',   '7cf0086a484ed4eabd1ce1b09a6a59a11297c61f') );
define( 'NONCE_SALT',       getenv_docker('WORDPRESS_NONCE_SALT',       '2fc8e8507b204f6e69e2c750a5ceaed9f5951736') );
// (See also https://wordpress.stackexchange.com/a/152905/199287)

define( 'WP_PHP_BINARY', 'php' );
define( 'WP_TESTS_DOMAIN', 'localhost:8889' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WPLANG', '' );

// If we're behind a proxy server and using HTTPS, we need to alert WordPress of that fact
// see also http://codex.wordpress.org/Administration_Over_SSL#Using_a_Reverse_Proxy
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
	$_SERVER['HTTPS'] = 'on';
}
// (we include this by default because reverse proxying is extremely common in container environments)

if ($configExtra = getenv_docker('WORDPRESS_CONFIG_EXTRA', '')) {
	eval($configExtra);
}

defined( 'PHP_INI_MEMORY_LIMIT' ) || define( 'PHP_INI_MEMORY_LIMIT', '512M' );
/* That's all, stop editing! Happy publishing. */

/** Sets up WordPress vars and included files. */
