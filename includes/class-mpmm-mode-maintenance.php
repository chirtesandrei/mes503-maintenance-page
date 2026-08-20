<?php
/**
 * Main plugin class.
 *
 * @package MPMM_Mode_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the settings screen and the public maintenance response.
 */
final class MPMM_Mode_Maintenance {

	private const OPTION_NAME = 'mpmm_options';
	private const PAGE_SLUG   = 'mes503-maintenance-page';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create the plugin hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_maintenance_page' ), 0 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_notice' ), 100 );
		add_filter( 'plugin_action_links_' . plugin_basename( MPMM_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Add safe defaults on first activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, int|string>
	 */
	private static function defaults() {
		return array(
			'enabled'      => 0,
			'page_title'   => __( 'Website under maintenance', 'mes503-maintenance-page' ),
			'heading'      => __( 'We will be back shortly.', 'mes503-maintenance-page' ),
			'message'      => __( 'A short update is in progress. Please come back in a few moments.', 'mes503-maintenance-page' ),
			'accent_color' => '#6546e8',
			'logo_id'      => 0,
			'button_label' => '',
			'button_url'   => '',
			'retry_after'  => 3600,
		);
	}

	/**
	 * Return settings merged with defaults.
	 *
	 * @return array<string, int|string>
	 */
	private function get_options() {
		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::defaults() );
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Maintenance mode', 'mes503-maintenance-page' ),
			__( 'Maintenance mode', 'mes503-maintenance-page' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the option and its sanitizer.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'mpmm_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize and validate all settings.
	 *
	 * @param mixed $input Submitted option value.
	 * @return array<string, int|string>
	 */
	public function sanitize_options( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$output['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
		$output['page_title']   = $this->limit_text( isset( $input['page_title'] ) ? $input['page_title'] : $defaults['page_title'], 120 );
		$output['heading']      = $this->limit_text( isset( $input['heading'] ) ? $input['heading'] : $defaults['heading'], 120 );
		$output['message']      = $this->limit_textarea( isset( $input['message'] ) ? $input['message'] : $defaults['message'], 600 );
		$output['button_label'] = $this->limit_text( isset( $input['button_label'] ) ? $input['button_label'] : '', 80 );
		$output['logo_id']      = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;

		$accent                 = isset( $input['accent_color'] ) ? sanitize_hex_color( wp_unslash( $input['accent_color'] ) ) : '';
		$output['accent_color'] = $accent ? $accent : $defaults['accent_color'];

		$url                  = isset( $input['button_url'] ) ? esc_url_raw( wp_unslash( $input['button_url'] ), array( 'http', 'https' ) ) : '';
		$output['button_url'] = $url;

		$retry                 = isset( $input['retry_after'] ) ? absint( $input['retry_after'] ) : (int) $defaults['retry_after'];
		$output['retry_after'] = min( 86400, max( 300, $retry ) );

		if ( ! empty( $output['button_label'] ) && empty( $output['button_url'] ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'mpmm_button_url_missing',
				__( 'The button stays hidden until a valid URL is provided.', 'mes503-maintenance-page' ),
				'warning'
			);
		}

		return $output;
	}

	/**
	 * Sanitize and truncate a single-line value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param int   $limit Maximum length.
	 * @return string
	 */
	private function limit_text( $value, $limit ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/**
	 * Sanitize and truncate a multi-line value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param int   $limit Maximum length.
	 * @return string
	 */
	private function limit_textarea( $value, $limit ) {
		$value = sanitize_textarea_field( wp_unslash( (string) $value ) );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	/**
	 * Load the media selector and small admin stylesheet on this plugin page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'mpmm-admin', MPMM_URL . 'admin/css/admin.css', array(), MPMM_VERSION );
		wp_enqueue_script( 'mpmm-admin', MPMM_URL . 'admin/js/admin.js', array( 'jquery' ), MPMM_VERSION, true );
		wp_localize_script(
			'mpmm-admin',
			'mpmmAdmin',
			array(
				'frameTitle'  => __( 'Choose a logo', 'mes503-maintenance-page' ),
				'frameButton' => __( 'Use this logo', 'mes503-maintenance-page' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options     = $this->get_options();
		$preview_url = wp_nonce_url(
			add_query_arg( 'mpmm_preview', '1', home_url( '/' ) ),
			'mpmm_preview',
			'mpmm_nonce'
		);
		$logo_url    = $options['logo_id'] ? wp_get_attachment_image_url( (int) $options['logo_id'], 'medium' ) : '';
		?>
		<div class="wrap mpmm-wrap">
			<div class="mpmm-heading">
				<div>
					<p class="mpmm-kicker">MesPlugins.fr</p>
					<h1><?php esc_html_e( 'Maintenance mode', 'mes503-maintenance-page' ); ?></h1>
					<p><?php esc_html_e( 'A clear page for your visitors, normal access for administrators.', 'mes503-maintenance-page' ); ?></p>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'mes503-maintenance-page' ); ?></a>
			</div>

			<?php settings_errors( self::OPTION_NAME ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'mpmm_settings' ); ?>

				<div class="mpmm-panel mpmm-status-panel">
					<label class="mpmm-switch-row" for="mpmm-enabled">
						<span>
							<strong><?php esc_html_e( 'Enable maintenance mode', 'mes503-maintenance-page' ); ?></strong>
							<small><?php esc_html_e( 'Logged-in administrators keep seeing the website normally.', 'mes503-maintenance-page' ); ?></small>
						</span>
						<input id="mpmm-enabled" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" type="checkbox" value="1" <?php checked( 1, (int) $options['enabled'] ); ?> />
					</label>
				</div>

				<div class="mpmm-grid">
					<section class="mpmm-panel">
						<h2><?php esc_html_e( 'Content', 'mes503-maintenance-page' ); ?></h2>

						<label for="mpmm-page-title"><?php esc_html_e( 'Browser tab title', 'mes503-maintenance-page' ); ?></label>
						<input class="regular-text" id="mpmm-page-title" maxlength="120" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[page_title]" type="text" value="<?php echo esc_attr( $options['page_title'] ); ?>" />

						<label for="mpmm-heading"><?php esc_html_e( 'Main heading', 'mes503-maintenance-page' ); ?></label>
						<input class="regular-text" id="mpmm-heading" maxlength="120" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[heading]" type="text" value="<?php echo esc_attr( $options['heading'] ); ?>" />

						<label for="mpmm-message"><?php esc_html_e( 'Message', 'mes503-maintenance-page' ); ?></label>
						<textarea class="large-text" id="mpmm-message" maxlength="600" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[message]" rows="5"><?php echo esc_textarea( $options['message'] ); ?></textarea>

						<label for="mpmm-button-label"><?php esc_html_e( 'Optional button label', 'mes503-maintenance-page' ); ?></label>
						<input class="regular-text" id="mpmm-button-label" maxlength="80" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[button_label]" type="text" value="<?php echo esc_attr( $options['button_label'] ); ?>" />

						<label for="mpmm-button-url"><?php esc_html_e( 'Button URL', 'mes503-maintenance-page' ); ?></label>
						<input class="regular-text" id="mpmm-button-url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[button_url]" type="url" value="<?php echo esc_attr( $options['button_url'] ); ?>" placeholder="https://" />
					</section>

					<section class="mpmm-panel">
						<h2><?php esc_html_e( 'Appearance and search engines', 'mes503-maintenance-page' ); ?></h2>

						<label for="mpmm-accent-color"><?php esc_html_e( 'Accent color', 'mes503-maintenance-page' ); ?></label>
						<input id="mpmm-accent-color" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[accent_color]" type="color" value="<?php echo esc_attr( $options['accent_color'] ); ?>" />

						<label><?php esc_html_e( 'Optional logo', 'mes503-maintenance-page' ); ?></label>
						<input id="mpmm-logo-id" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[logo_id]" type="hidden" value="<?php echo esc_attr( (string) $options['logo_id'] ); ?>" />
						<div class="mpmm-logo-preview" id="mpmm-logo-preview">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
							<?php endif; ?>
						</div>
						<div class="mpmm-media-actions">
							<button class="button" id="mpmm-select-logo" type="button"><?php esc_html_e( 'Choose a logo', 'mes503-maintenance-page' ); ?></button>
							<button class="button-link-delete" id="mpmm-remove-logo" type="button" <?php echo $logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'mes503-maintenance-page' ); ?></button>
						</div>

						<label for="mpmm-retry-after"><?php esc_html_e( 'Suggested retry delay', 'mes503-maintenance-page' ); ?></label>
						<div class="mpmm-inline-field">
							<input id="mpmm-retry-after" max="86400" min="300" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[retry_after]" step="60" type="number" value="<?php echo esc_attr( (string) $options['retry_after'] ); ?>" />
							<span><?php esc_html_e( 'seconds', 'mes503-maintenance-page' ); ?></span>
						</div>
						<p class="description"><?php esc_html_e( 'The page returns an HTTP 503 status, a Retry-After header, and noindex instructions.', 'mes503-maintenance-page' ); ?></p>
					</section>
				</div>

				<?php submit_button( __( 'Save settings', 'mes503-maintenance-page' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Detect a request for a file that must keep its own response during
	 * maintenance, whatever the permalink structure.
	 *
	 * @return bool
	 */
	private function is_reserved_file_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$file = strtolower( basename( $path ) );

		return in_array( $file, array( 'robots.txt', 'favicon.ico' ), true );
	}

	/**
	 * Determine whether this request should receive the maintenance page.
	 *
	 * @return bool
	 */
	private function should_render_maintenance_page() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		/*
		 * robots.txt must never answer 5xx. A prolonged 5xx on that file makes
		 * search engines stop crawling the whole site, which is the opposite of
		 * what a temporary maintenance window should signal. The favicon is
		 * excluded too, so icon requests never receive a full HTML document.
		 *
		 * is_robots() and is_favicon() rely on rewrite rules that only exist
		 * once a permalink structure is set. On a site left with plain
		 * permalinks they both stay false, and robots.txt used to receive the
		 * 503 page. The request path is therefore checked as well.
		 */
		if ( is_robots() || is_favicon() || $this->is_reserved_file_request() ) {
			return false;
		}

		$is_preview = isset( $_GET['mpmm_preview'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['mpmm_preview'] ) );

		if ( $is_preview && current_user_can( 'manage_options' ) ) {
			$nonce = isset( $_GET['mpmm_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mpmm_nonce'] ) ) : '';

			return (bool) wp_verify_nonce( $nonce, 'mpmm_preview' );
		}

		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		$options = $this->get_options();

		return 1 === (int) $options['enabled'];
	}

	/**
	 * Render and terminate the public request when maintenance mode applies.
	 *
	 * @return void
	 */
	public function maybe_render_maintenance_page() {
		if ( ! $this->should_render_maintenance_page() ) {
			return;
		}

		$options   = $this->get_options();
		$accent    = sanitize_hex_color( (string) $options['accent_color'] );
		$accent    = $accent ? $accent : '#6546e8';
		$logo_url  = $options['logo_id'] ? wp_get_attachment_image_url( (int) $options['logo_id'], 'large' ) : '';
		$site_name = get_bloginfo( 'name' );

		wp_register_style(
			'mpmm-maintenance',
			MPMM_URL . 'public/css/maintenance.css',
			array(),
			MPMM_VERSION
		);
		wp_enqueue_style( 'mpmm-maintenance' );
		wp_add_inline_style( 'mpmm-maintenance', ':root{--mpmm-accent:' . esc_html( $accent ) . '}' );

		status_header( 503 );
		nocache_headers();
		header( 'Retry-After: ' . absint( $options['retry_after'] ) );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow, noarchive">
	<title><?php echo esc_html( $options['page_title'] ); ?></title>
	<?php wp_print_styles( 'mpmm-maintenance' ); ?>
</head>
<body>
	<main class="mpmm-card">
		<?php if ( $logo_url ) : ?>
			<img class="mpmm-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
		<?php else : ?>
			<div class="mpmm-mark" aria-hidden="true">M</div>
		<?php endif; ?>
		<p class="mpmm-kicker"><?php esc_html_e( 'Maintenance in progress', 'mes503-maintenance-page' ); ?></p>
		<h1><?php echo esc_html( $options['heading'] ); ?></h1>
		<p class="mpmm-message"><?php echo nl2br( esc_html( $options['message'] ) ); ?></p>
		<?php if ( ! empty( $options['button_label'] ) && ! empty( $options['button_url'] ) ) : ?>
			<a class="mpmm-button" href="<?php echo esc_url( $options['button_url'] ); ?>"><?php echo esc_html( $options['button_label'] ); ?></a>
		<?php endif; ?>
		<p class="mpmm-foot"><?php echo esc_html( $site_name ); ?></p>
	</main>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Add a visible admin bar status when maintenance mode is active.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin toolbar object.
	 * @return void
	 */
	public function add_admin_bar_notice( $admin_bar ) {
		$options = $this->get_options();

		if ( 1 !== (int) $options['enabled'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'mpmm-status',
				'title' => __( 'Maintenance mode is active', 'mes503-maintenance-page' ),
				'href'  => admin_url( 'options-general.php?page=' . self::PAGE_SLUG ),
				'meta'  => array( 'class' => 'mpmm-admin-bar-status' ),
			)
		);
	}

	/**
	 * Add a direct settings link to the plugins list.
	 *
	 * @param array<int, string> $links Existing links.
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'mes503-maintenance-page' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
