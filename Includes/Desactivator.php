<?php
/**
 * Fired furing plugin desactivation
 *
 * @link       https://actirise.com
 * @since      2.0.0
 *
 * @package    actirise
 * @subpackage actirise/includes
 */

namespace Actirise\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Fired during plugin desactivation.
 *
 * This class defines all code necessary to run during the plugin's desactivation.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
final class Desactivator {

	/**
	 * Desactivate the plugin and remove schedule cron.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public static function desactivate() {
		if ( ACTIRISE_CRON === 'true' ) {
			wp_clear_scheduled_hook( 'actirise_cron_update_adstxt' );
			wp_clear_scheduled_hook( 'actirise_cron_update_presized_div' );
			wp_clear_scheduled_hook( 'actirise_cron_update_debug_token' );
			wp_clear_scheduled_hook( 'actirise_cron_update_fast_cmp' );
		}

		if ( get_option( 'actirise-adstxt-file' ) === 'true' ) {
			$ads_txt_file = ABSPATH . 'ads.txt';

			if ( file_exists( $ads_txt_file ) ) {
				wp_delete_file( $ads_txt_file );
			}
		}
	}
}
