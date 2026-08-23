<?php
/**
 * The [fyos_registration_form] shortcode.
 *
 * @package FyosRegistrationForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the <registration-form-entry> element the FOYS app mounts on.
 */
class FRF_Shortcode {

	const TAG = 'fyos_registration_form';

	/**
	 * Settings handler.
	 *
	 * @var FRF_Settings
	 */
	private $settings;

	/**
	 * Asset handler.
	 *
	 * @var FRF_Assets
	 */
	private $assets;

	/**
	 * How many forms were rendered on this request.
	 *
	 * @var int
	 */
	private $rendered = 0;

	/**
	 * Constructor.
	 *
	 * @param FRF_Settings $settings Settings handler.
	 * @param FRF_Assets   $assets   Asset handler.
	 */
	public function __construct( FRF_Settings $settings, FRF_Assets $assets ) {
		$this->settings = $settings;
		$this->assets   = $assets;
	}

	/**
	 * Register the shortcode.
	 */
	public function init() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 *   [fyos_registration_form configuration="085f4107-63a1-44d8-2e07-08dd3cbd1495"]
	 *   [fyos_registration_form configuration="..." container="..." bootstrap="no"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'configuration' => $this->settings->get( 'configuration', '' ),
				'container'     => $this->settings->get( 'container', '' ),
				'bootstrap'     => $this->settings->get( 'load_bootstrap', 0 ) ? 'yes' : 'no',
				'theme'         => $this->settings->get( 'load_theme_css', 0 ) ? 'yes' : 'no',
				'class'         => '',
			),
			$atts,
			self::TAG
		);

		$configuration = FRF_Settings::sanitize_guid( $atts['configuration'] );
		if ( '' === $configuration ) {
			return $this->notice(
				__( 'FOYS registration form: no valid configuration ID. Add configuration="…" to the shortcode, or set a default under Settings → FOYS Registration Form.', 'fyos-registration-form' )
			);
		}

		// The FOYS app mounts on the first <registration-form-entry> in the
		// document, so a second one on the same page would stay empty.
		$this->rendered++;
		if ( $this->rendered > 1 ) {
			return $this->notice(
				__( 'FOYS registration form: only one registration form can be shown per page.', 'fyos-registration-form' )
			);
		}

		$this->assets->enqueue(
			array(
				'bootstrap' => self::is_yes( $atts['bootstrap'] ),
				'theme'     => self::is_yes( $atts['theme'] ),
			)
		);

		$classes = 'frf-registration-form';
		if ( '' !== trim( $atts['class'] ) ) {
			$classes .= ' ' . trim( $atts['class'] );
		}

		$element_atts = ' configuration="' . esc_attr( $configuration ) . '"';

		$container = sanitize_text_field( $atts['container'] );
		if ( '' !== $container ) {
			$element_atts .= ' container="' . esc_attr( $container ) . '"';
		}

		$html  = '<div class="' . esc_attr( $classes ) . '">';
		$html .= '<registration-form-entry' . $element_atts . '></registration-form-entry>';
		$html .= '<noscript><p>' . esc_html__( 'This registration form needs JavaScript. Please enable it and reload the page.', 'fyos-registration-form' ) . '</p></noscript>';
		$html .= '</div>';

		/**
		 * Filter the rendered shortcode markup.
		 *
		 * @param string $html The markup.
		 * @param array  $atts The resolved shortcode attributes.
		 */
		return apply_filters( 'frf_shortcode_html', $html, $atts );
	}

	/**
	 * Truthiness for the yes/no shortcode attributes.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	public static function is_yes( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( 'yes', 'true', '1', 'on' ), true );
	}

	/**
	 * A configuration warning, shown only to users who can fix it.
	 *
	 * @param string $message Message text.
	 * @return string
	 */
	private function notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="frf-notice"><strong>' . esc_html( $message ) . '</strong></p>';
	}
}
