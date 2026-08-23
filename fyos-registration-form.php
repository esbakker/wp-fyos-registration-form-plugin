<?php
/**
 * Plugin Name:       FOYS Registration Form
 * Plugin URI:        https://github.com/esbakker/wp-fyos-registration-form-plugin
 * Description:       Embeds a FOYS (registration-form.foys.tech) registration form on any page through the [fyos_registration_form] shortcode, and restyles it to match the active WordPress theme.
 * Version:           1.0.5
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Eddy
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fyos-registration-form
 *
 * @package FyosRegistrationForm
 */

defined( 'ABSPATH' ) || exit;

define( 'FRF_VERSION', '1.0.5' );
define( 'FRF_PLUGIN_FILE', __FILE__ );
define( 'FRF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FRF_PLUGIN_DIR . 'includes/class-frf-settings.php';
require_once FRF_PLUGIN_DIR . 'includes/class-frf-theme-css.php';
require_once FRF_PLUGIN_DIR . 'includes/class-frf-assets.php';
require_once FRF_PLUGIN_DIR . 'includes/class-frf-shortcode.php';
require_once FRF_PLUGIN_DIR . 'includes/class-frf-plugin.php';

/**
 * Boot the plugin.
 *
 * @return FRF_Plugin
 */
function frf_plugin() {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new FRF_Plugin();
	}
	return $plugin;
}

add_action( 'plugins_loaded', array( frf_plugin(), 'init' ) );
