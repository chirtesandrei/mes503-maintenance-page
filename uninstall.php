<?php
/**
 * Remove the plugin settings on uninstall.
 *
 * @package MPMM_Mode_Maintenance
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$mpmm_option_name = 'mpmm_options';

if ( is_multisite() ) {
	$mpmm_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $mpmm_site_ids as $mpmm_site_id ) {
		switch_to_blog( (int) $mpmm_site_id );
		delete_option( $mpmm_option_name );
		restore_current_blog();
	}
} else {
	delete_option( $mpmm_option_name );
}
