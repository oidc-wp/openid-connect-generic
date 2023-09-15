<?php
/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/../vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

// @phpstan-ignore-next-line
tests_add_filter( 'muplugins_loaded', function() {
    // test set up, plugin activation, etc.
    require dirname( __DIR__ ) . '/../openid-connect-generic.php';
} );

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
