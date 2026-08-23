<?php
/**
 * Registers and enqueues the FOYS bundle plus the theme-bridge stylesheet.
 *
 * @package FyosRegistrationForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset handling for the embedded FOYS single page app.
 */
class FRF_Assets {

	const HANDLE_VENDOR_CSS = 'frf-foys-vendors-css';
	const HANDLE_APP_CSS    = 'frf-foys-app-css';
	const HANDLE_THEME_CSS  = 'frf-theme-css';
	const HANDLE_VENDOR_JS  = 'frf-foys-vendors-js';
	const HANDLE_APP_JS     = 'frf-foys-app-js';
	const HANDLE_PORTAL_JS  = 'frf-portal-js';

	/**
	 * Default host serving the FOYS bundle.
	 */
	const DEFAULT_BASE_URL = 'https://registration-form.foys.tech';

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
	 * Constructor.
	 *
	 * @param FRF_Settings  $settings  Settings handler.
	 * @param FRF_Theme_CSS $theme_css Theme bridge CSS builder.
	 */
	public function __construct( FRF_Settings $settings, FRF_Theme_CSS $theme_css ) {
		$this->settings  = $settings;
		$this->theme_css = $theme_css;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_early' ), 20 );
	}

	/**
	 * Base URL of the FOYS bundle, without a trailing slash.
	 *
	 * @return string
	 */
	public static function base_url() {
		/**
		 * Filter the host that serves the FOYS registration form bundle.
		 *
		 * @param string $base_url Default https://registration-form.foys.tech
		 */
		$url = apply_filters( 'frf_base_url', self::DEFAULT_BASE_URL );
		return untrailingslashit( esc_url_raw( $url ) );
	}

	/**
	 * Register every handle. Nothing is printed until something enqueues them.
	 */
	public function register() {
		$base = self::base_url();

		// Bootstrap 4.6.1 + BootstrapVue, both scoped to .registration-form by
		// FOYS, so this cannot bleed into the theme.
		wp_register_style( self::HANDLE_VENDOR_CSS, $base . '/chunk-vendors.css', array(), null );
		// No dependency on the vendor sheet: it is optional, and WordPress
		// keeps the enqueue order for handles without dependencies.
		wp_register_style( self::HANDLE_APP_CSS, $base . '/app.css', array(), null );

		wp_register_style(
			self::HANDLE_THEME_CSS,
			FRF_PLUGIN_URL . 'assets/css/frf-theme.css',
			array( self::HANDLE_APP_CSS ),
			FRF_VERSION
		);

		wp_register_script( self::HANDLE_VENDOR_JS, $base . '/chunk-vendors.js', array(), null, true );
		wp_register_script( self::HANDLE_APP_JS, $base . '/app.js', array( self::HANDLE_VENDOR_JS ), null, true );

		// Puts BootstrapVue's body-level overlays back inside the CSS scope.
		wp_register_script(
			self::HANDLE_PORTAL_JS,
			FRF_PLUGIN_URL . 'assets/js/frf-portal.js',
			array(),
			FRF_VERSION,
			true
		);
	}

	/**
	 * Enqueue during wp_enqueue_scripts when the shortcode is already visible
	 * in the queried post, so the stylesheets land in <head> instead of the
	 * footer.
	 */
	public function maybe_enqueue_early() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, FRF_Shortcode::TAG ) ) {
			return;
		}

		$bootstrap = (bool) $this->settings->get( 'load_bootstrap', 0 );
		$theme     = (bool) $this->settings->get( 'load_theme_css', 0 );

		// Honour bootstrap="no" / theme="no" on the shortcode itself, otherwise
		// this early pass would enqueue what the shortcode asked us to skip.
		$atts = $this->first_shortcode_atts( $post->post_content );
		if ( isset( $atts['bootstrap'] ) ) {
			$bootstrap = FRF_Shortcode::is_yes( $atts['bootstrap'] );
		}
		if ( isset( $atts['theme'] ) ) {
			$theme = FRF_Shortcode::is_yes( $atts['theme'] );
		}

		$this->enqueue(
			array(
				'bootstrap' => $bootstrap,
				'theme'     => $theme,
			)
		);
	}

	/**
	 * Attributes of the first [fyos_registration_form] in a piece of content.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function first_shortcode_atts( $content ) {
		$pattern = get_shortcode_regex( array( FRF_Shortcode::TAG ) );

		if ( ! preg_match( '/' . $pattern . '/s', $content, $match ) ) {
			return array();
		}

		$atts = shortcode_parse_atts( isset( $match[3] ) ? $match[3] : '' );

		return is_array( $atts ) ? $atts : array();
	}

	/**
	 * Enqueue the bundle.
	 *
	 * @param array $args {
	 *     @type bool $bootstrap Load the scoped FOYS Bootstrap stylesheet.
	 *     @type bool $theme     Load the WordPress theme bridge.
	 * }
	 */
	public function enqueue( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'bootstrap' => true,
				'theme'     => true,
			)
		);

		if ( ! wp_style_is( self::HANDLE_APP_CSS, 'registered' ) ) {
			$this->register();
		}

		if ( $args['bootstrap'] ) {
			wp_enqueue_style( self::HANDLE_VENDOR_CSS );
		}
		wp_enqueue_style( self::HANDLE_APP_CSS );

		if ( $args['theme'] ) {
			wp_enqueue_style( self::HANDLE_THEME_CSS );
			$this->add_inline_css();
		}

		wp_enqueue_script( self::HANDLE_APP_JS );

		// Only useful while the scoped FOYS stylesheet is in charge.
		if ( $args['bootstrap'] ) {
			wp_enqueue_script( self::HANDLE_PORTAL_JS );
		}
	}

	/**
	 * Attach the generated variables and the admin's extra CSS once.
	 */
	private function add_inline_css() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$css = $this->theme_css->build();

		// The admin's extra CSS is scoped for them, so a stray `a { … }` cannot
		// restyle the whole site.
		$extra = (string) $this->settings->get( 'custom_css', '' );
		if ( '' !== trim( $extra ) ) {
			$css .= "\n" . FRF_Theme_CSS::scope( $extra );
		}

		wp_add_inline_style( self::HANDLE_THEME_CSS, $css );
	}
}
