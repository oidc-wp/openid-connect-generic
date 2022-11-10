<?php
/**
 * Global Hellō Login functions.
 *
 * @package   Hello_Login
 * @author    Marius Scurtescu <marius.scurtescu@hello.coop>
 * @copyright 2022 Hello Coop
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

function hello_login_enqueue_scripts_and_styles() {
	wp_enqueue_script( 'hello-button', 'https://cdn.hello.coop/js/hello-btn.js' );
	wp_enqueue_style( 'hello-button', 'https://cdn.hello.coop/css/hello-btn.css' );
}

add_action( 'wp_enqueue_scripts', 'hello_login_enqueue_scripts_and_styles' );
add_action( 'login_enqueue_scripts', 'hello_login_enqueue_scripts_and_styles' );
add_action( 'admin_enqueue_scripts', 'hello_login_enqueue_scripts_and_styles' );
