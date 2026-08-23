<?php
/**
 * Derives the CSS custom properties that make the FOYS form look like the
 * active WordPress theme.
 *
 * The FOYS stylesheets are all scoped to `.registration-form`, so everything
 * here is scoped the same way and cannot touch the rest of the site.
 *
 * @package FyosRegistrationForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the inline "theme bridge" variables.
 */
class FRF_Theme_CSS {

	/**
	 * Settings handler.
	 *
	 * @var FRF_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param FRF_Settings $settings Settings handler.
	 */
	public function __construct( FRF_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * The inline CSS printed after the plugin stylesheet.
	 *
	 * @return string
	 */
	public function build() {
		$accent   = $this->accent_color();
		$contrast = $this->accent_contrast( $accent );
		$radius   = $this->settings->get( 'border_radius', '' );

		// shade()/fade() need a real hex. When the accent is a var() chain that
		// only the browser can resolve, fall back to values that still look
		// deliberate next to it.
		$hover = $this->shade( $accent, -0.12 );
		$soft  = $this->fade( $accent, 0.25 );

		$vars = array(
			'--frf-accent'          => $accent,
			'--frf-accent-contrast' => $contrast,
			'--frf-accent-hover'    => '' !== $hover ? $hover : 'var(--frf-accent)',
			'--frf-accent-soft'     => '' !== $soft ? $soft : 'rgba(0,0,0,0.15)',
		);

		if ( '' !== $radius ) {
			$vars['--frf-radius'] = $radius;
		}

		$css = '.registration-form{';
		foreach ( $vars as $name => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$css .= $name . ':' . $value . ';';
		}
		$css .= '}';

		/**
		 * Filter the generated theme-bridge variables.
		 *
		 * @param string $css  The generated CSS.
		 * @param array  $vars The custom properties that produced it.
		 */
		return apply_filters( 'frf_theme_css', $css, $vars );
	}

	/**
	 * Accent colour: explicit setting first, then the theme, then a var chain
	 * that resolves in the browser for block themes.
	 *
	 * @return string CSS colour value.
	 */
	private function accent_color() {
		$configured = $this->settings->get( 'accent_color', '' );
		if ( '' !== $configured ) {
			return $configured;
		}

		$detected = $this->detect_theme_color();
		if ( '' !== $detected ) {
			return $detected;
		}

		// Nothing resolvable server-side: let the browser try the usual block
		// theme presets and fall back to the Bootstrap blue.
		return 'var(--wp--preset--color--primary,var(--wp--preset--color--accent,var(--wp--preset--color--foreground,#007bff)))';
	}

	/**
	 * Look for an accent colour in theme mods and in theme.json.
	 *
	 * @return string Hex colour, or an empty string.
	 */
	private function detect_theme_color() {
		foreach ( array( 'accent_color', 'primary_color', 'link_color' ) as $mod ) {
			$value = get_theme_mod( $mod );
			if ( is_string( $value ) && '' !== $value ) {
				$hex = sanitize_hex_color( $this->normalize_hex( $value ) );
				if ( $hex ) {
					return $hex;
				}
			}
		}

		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return '';
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( ! is_array( $palette ) ) {
			return '';
		}

		// theme.json returns origin-keyed groups (theme/custom/default) on
		// block themes, and a flat list when a plugin filters it.
		$groups = array();
		foreach ( array( 'custom', 'theme' ) as $origin ) {
			if ( ! empty( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ) {
				$groups[] = $palette[ $origin ];
			}
		}
		if ( empty( $groups ) && isset( $palette[0] ) ) {
			$groups[] = $palette;
		}

		foreach ( array( 'primary', 'accent', 'link' ) as $slug ) {
			foreach ( $groups as $colors ) {
				foreach ( $colors as $color ) {
					if ( isset( $color['slug'], $color['color'] ) && $slug === $color['slug'] ) {
						$hex = sanitize_hex_color( $this->normalize_hex( $color['color'] ) );
						if ( $hex ) {
							return $hex;
						}
					}
				}
			}
		}

		return '';
	}

	/**
	 * Readable text colour for a background.
	 *
	 * @param string $accent Accent colour (hex or a var() chain).
	 * @return string
	 */
	private function accent_contrast( $accent ) {
		$configured = $this->settings->get( 'accent_contrast', '' );
		if ( '' !== $configured ) {
			return $configured;
		}

		$rgb = $this->to_rgb( $accent );
		if ( null === $rgb ) {
			return '#ffffff';
		}

		// Relative luminance, sRGB coefficients.
		$luma = ( 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2] ) / 255;

		return $luma > 0.6 ? '#111111' : '#ffffff';
	}

	/**
	 * Darken or lighten a hex colour.
	 *
	 * @param string $color  Hex colour (or a var() chain, which is returned as-is).
	 * @param float  $amount Negative darkens, positive lightens, range -1..1.
	 * @return string
	 */
	private function shade( $color, $amount ) {
		$rgb = $this->to_rgb( $color );
		if ( null === $rgb ) {
			return '';
		}

		$target = $amount < 0 ? 0 : 255;
		$out    = array();
		foreach ( $rgb as $channel ) {
			$out[] = (int) round( $channel + ( $target - $channel ) * abs( $amount ) );
		}

		return sprintf( '#%02x%02x%02x', $out[0], $out[1], $out[2] );
	}

	/**
	 * Semi-transparent version of a colour, used for focus rings.
	 *
	 * @param string $color Hex colour.
	 * @param float  $alpha Alpha channel, 0..1.
	 * @return string
	 */
	private function fade( $color, $alpha ) {
		$rgb = $this->to_rgb( $color );
		if ( null === $rgb ) {
			return '';
		}
		return sprintf( 'rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * Parse a hex colour into RGB components.
	 *
	 * @param string $color Colour value.
	 * @return array|null [r, g, b] or null when it is not a plain hex colour.
	 */
	private function to_rgb( $color ) {
		$hex = ltrim( trim( (string) $color ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			return null;
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Theme mods sometimes store colours without the leading hash.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_hex( $value ) {
		$value = trim( (string) $value );
		if ( '' !== $value && '#' !== $value[0] && preg_match( '/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value ) ) {
			return '#' . $value;
		}
		return $value;
	}
}
