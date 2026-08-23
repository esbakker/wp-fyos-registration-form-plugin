<?php
/**
 * Plugin settings (Settings → FOYS Registration Form).
 *
 * @package FyosRegistrationForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores and renders the plugin options.
 */
class FRF_Settings {

	const OPTION_KEY = 'frf_settings';
	const PAGE_SLUG  = 'fyos-registration-form';

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'configuration'   => '',
			'container'       => '',
			'load_bootstrap'  => 1,
			'load_theme_css'  => 1,
			'accent_color'    => '',
			'accent_contrast' => '',
			'border_radius'   => '',
			'custom_css'      => '',
		);
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @return array
	 */
	public function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * A single setting.
	 *
	 * @param string $key      Setting name.
	 * @param mixed  $fallback Returned when the setting is empty.
	 * @return mixed
	 */
	public function get( $key, $fallback = '' ) {
		$all = $this->all();
		if ( ! isset( $all[ $key ] ) || '' === $all[ $key ] ) {
			return $fallback;
		}
		return $all[ $key ];
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FRF_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add a "Settings" link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'fyos-registration-form' ) . '</a>' );
		return $links;
	}

	/**
	 * Add the options page.
	 */
	public function add_menu() {
		add_options_page(
			__( 'FOYS Registration Form', 'fyos-registration-form' ),
			__( 'FOYS Registration Form', 'fyos-registration-form' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option and its sanitizer.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_KEY,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Raw form input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['configuration']   = self::sanitize_guid( isset( $input['configuration'] ) ? $input['configuration'] : '' );
		$out['container']       = sanitize_text_field( isset( $input['container'] ) ? $input['container'] : '' );
		$out['load_bootstrap']  = empty( $input['load_bootstrap'] ) ? 0 : 1;
		$out['load_theme_css']  = empty( $input['load_theme_css'] ) ? 0 : 1;
		$out['accent_color']    = self::sanitize_color( isset( $input['accent_color'] ) ? $input['accent_color'] : '' );
		$out['accent_contrast'] = self::sanitize_color( isset( $input['accent_contrast'] ) ? $input['accent_contrast'] : '' );
		$out['border_radius']   = self::sanitize_length( isset( $input['border_radius'] ) ? $input['border_radius'] : '' );
		$out['custom_css']      = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';

		return $out;
	}

	/**
	 * Keep only well-formed GUIDs; anything else becomes empty.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_guid( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value ) ) {
			return strtolower( $value );
		}
		return '';
	}

	/**
	 * Accept a hex colour or a CSS custom property reference.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$hex = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}
		if ( preg_match( '/^var\(\s*--[A-Za-z0-9_-]+\s*\)$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Accept a simple CSS length (px, rem, em, %) or a bare 0.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_length( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^[0-9]+(\.[0-9]+)?(px|rem|em|%)?$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Render the options page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s   = $this->all();
		$key = self::OPTION_KEY;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FOYS Registration Form', 'fyos-registration-form' ); ?></h1>

			<p>
				<?php esc_html_e( 'Put a form on a page with the shortcode:', 'fyos-registration-form' ); ?>
				<code>[fyos_registration_form configuration="00000000-0000-0000-0000-000000000000"]</code>
			</p>
			<p class="description">
				<?php esc_html_e( 'Only one registration form can be shown per page: the FOYS app mounts on the first one it finds.', 'fyos-registration-form' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( self::OPTION_KEY ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="frf-configuration"><?php esc_html_e( 'Default configuration ID', 'fyos-registration-form' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text code" id="frf-configuration"
								name="<?php echo esc_attr( $key ); ?>[configuration]"
								value="<?php echo esc_attr( $s['configuration'] ); ?>"
								placeholder="085f4107-63a1-44d8-2e07-08dd3cbd1495" />
							<p class="description">
								<?php esc_html_e( 'Used when the shortcode is written without a configuration attribute.', 'fyos-registration-form' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="frf-container"><?php esc_html_e( 'Default container', 'fyos-registration-form' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text code" id="frf-container"
								name="<?php echo esc_attr( $key ); ?>[container]"
								value="<?php echo esc_attr( $s['container'] ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Optional. The form sends this as the X-Container header on every FOYS API call. Leave empty unless FOYS asked you to set it.', 'fyos-registration-form' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Stylesheets', 'fyos-registration-form' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" value="1"
										name="<?php echo esc_attr( $key ); ?>[load_bootstrap]"
										<?php checked( 1, (int) $s['load_bootstrap'] ); ?> />
									<?php esc_html_e( 'Load the FOYS Bootstrap stylesheet (recommended)', 'fyos-registration-form' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'The FOYS stylesheet is scoped to .registration-form, so it cannot leak into the rest of your theme. Only turn it off if your theme already ships Bootstrap 4.', 'fyos-registration-form' ); ?>
								</p>
								<br />
								<label>
									<input type="checkbox" value="1"
										name="<?php echo esc_attr( $key ); ?>[load_theme_css]"
										<?php checked( 1, (int) $s['load_theme_css'] ); ?> />
									<?php esc_html_e( 'Restyle the form to match this WordPress theme', 'fyos-registration-form' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Applies your theme fonts, colours and corner rounding to the form.', 'fyos-registration-form' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="frf-accent"><?php esc_html_e( 'Accent colour', 'fyos-registration-form' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text code" id="frf-accent"
								name="<?php echo esc_attr( $key ); ?>[accent_color]"
								value="<?php echo esc_attr( $s['accent_color'] ); ?>" placeholder="#0a7d3c" />
							<p class="description">
								<?php esc_html_e( 'Buttons, links and focus rings. Leave empty to detect it from the theme.', 'fyos-registration-form' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="frf-accent-contrast"><?php esc_html_e( 'Accent text colour', 'fyos-registration-form' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text code" id="frf-accent-contrast"
								name="<?php echo esc_attr( $key ); ?>[accent_contrast]"
								value="<?php echo esc_attr( $s['accent_contrast'] ); ?>" placeholder="#ffffff" />
							<p class="description">
								<?php esc_html_e( 'Label colour on accent-coloured buttons.', 'fyos-registration-form' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="frf-radius"><?php esc_html_e( 'Corner radius', 'fyos-registration-form' ); ?></label>
						</th>
						<td>
							<input type="text" class="small-text code" id="frf-radius"
								name="<?php echo esc_attr( $key ); ?>[border_radius]"
								value="<?php echo esc_attr( $s['border_radius'] ); ?>" placeholder="4px" />
							<p class="description">
								<?php esc_html_e( 'Rounding for inputs and buttons, for example 0, 4px or 0.5rem.', 'fyos-registration-form' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="frf-custom-css"><?php esc_html_e( 'Extra CSS', 'fyos-registration-form' ); ?></label>
						</th>
						<td>
							<textarea id="frf-custom-css" rows="8" class="large-text code"
								name="<?php echo esc_attr( $key ); ?>[custom_css]"><?php echo esc_textarea( $s['custom_css'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Printed after the plugin styles. Selectors are scoped to the form automatically, so .btn { … } only affects buttons inside the form and cannot reach the rest of the site. Rules you prefix yourself are left as they are.', 'fyos-registration-form' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcode attributes', 'fyos-registration-form' ); ?></h2>
			<table class="widefat striped" style="max-width:60em">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'fyos-registration-form' ); ?></th>
						<th><?php esc_html_e( 'Description', 'fyos-registration-form' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>configuration</code></td>
						<td><?php esc_html_e( 'The form GUID from FOYS. Required, unless a default is set above.', 'fyos-registration-form' ); ?></td>
					</tr>
					<tr>
						<td><code>container</code></td>
						<td><?php esc_html_e( 'Optional X-Container value sent with every FOYS API request.', 'fyos-registration-form' ); ?></td>
					</tr>
					<tr>
						<td><code>bootstrap</code></td>
						<td><?php esc_html_e( 'yes (default) or no: load the scoped FOYS Bootstrap stylesheet on this page.', 'fyos-registration-form' ); ?></td>
					</tr>
					<tr>
						<td><code>theme</code></td>
						<td><?php esc_html_e( 'yes (default) or no: apply the WordPress theme styling on this page.', 'fyos-registration-form' ); ?></td>
					</tr>
					<tr>
						<td><code>class</code></td>
						<td><?php esc_html_e( 'Extra CSS class(es) on the wrapper element.', 'fyos-registration-form' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
