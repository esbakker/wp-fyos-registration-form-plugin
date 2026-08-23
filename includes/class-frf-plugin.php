<?php
/**
 * Main plugin orchestrator.
 *
 * @package FyosRegistrationForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's pieces together.
 */
class FRF_Plugin {

	/**
	 * Settings handler.
	 *
	 * @var FRF_Settings
	 */
	private $settings;

	/**
	 * Theme bridge CSS builder.
	 *
	 * @var FRF_Theme_CSS
	 */
	private $theme_css;

	/**
	 * Asset handler.
	 *
	 * @var FRF_Assets
	 */
	private $assets;

	/**
	 * Shortcode handler.
	 *
	 * @var FRF_Shortcode
	 */
	private $shortcode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings  = new FRF_Settings();
		$this->theme_css = new FRF_Theme_CSS( $this->settings );
		$this->assets    = new FRF_Assets( $this->settings, $this->theme_css );
		$this->shortcode = new FRF_Shortcode( $this->settings, $this->assets );
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		load_plugin_textdomain( 'fyos-registration-form', false, dirname( plugin_basename( FRF_PLUGIN_FILE ) ) . '/languages' );

		$this->settings->init();
		$this->assets->init();
		$this->shortcode->init();
	}

	/**
	 * Settings handler, for third-party code.
	 *
	 * @return FRF_Settings
	 */
	public function settings() {
		return $this->settings;
	}
}
