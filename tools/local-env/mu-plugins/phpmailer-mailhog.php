<?php
/**
 * Plugin Name: MailHog PhpMailer Setup
 * Description: Establishes a connection between the PhpMailer library and the MailHog local-dev Docker container.
 *
 * @package OpenID_Connect_Generic_MuPlugins
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Provides the configuration for PhpMailer to use MailHog.
 *
 * @param PHPMailer $phpmailer The PHPMailer instance.
 *
 * @return void
 */
function mailhog_phpmailer_setup( PHPMailer $phpmailer ) {

	defined( 'SMTP_HOST' ) || define( 'SMTP_HOST', 'mailhog' );
	// PHPMailer doesn't follow WordPress naming conventions so this can be ignored.
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$phpmailer->Host = SMTP_HOST;

	defined( 'SMTP_PORT' ) || define( 'SMTP_PORT', 1025 );
	// PHPMailer doesn't follow WordPress naming conventions so this can be ignored.
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$phpmailer->Port = SMTP_PORT;

	$phpmailer->IsSMTP();

}

add_action( 'phpmailer_init', 'mailhog_phpmailer_setup', 10, 2 );
