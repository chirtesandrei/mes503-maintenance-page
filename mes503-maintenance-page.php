<?php
/**
 * Plugin Name:       Mes503 Maintenance Page
 * Plugin URI:        https://mesplugins.fr/plugins/mode-maintenance/
 * Description:       Show visitors a clear maintenance page while administrators keep working normally.
 * Version:           0.1.4
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            MesPlugins.fr
 * Author URI:        https://mesplugins.fr/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mes503-maintenance-page
 * Domain Path:       /languages
 *
 * @package MPMM_Mode_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MPMM_VERSION', '0.1.4' );
define( 'MPMM_FILE', __FILE__ );
define( 'MPMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPMM_URL', plugin_dir_url( __FILE__ ) );

require_once MPMM_DIR . 'includes/class-mpmm-mode-maintenance.php';

register_activation_hook( MPMM_FILE, array( 'MPMM_Mode_Maintenance', 'activate' ) );

MPMM_Mode_Maintenance::instance();
