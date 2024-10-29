<?php
/**
 * Fired furing plugin activation
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

use Actirise\Includes\Cron;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
final class Activator {

	/**
	 * Activate the plugin and schedule the cron.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public static function activate() {
		$cron = new Cron();
		$cron->update_adstxt();
		$cron->check_presized_div();
		$cron->refresh_token();
		$cron->get_fast_cmp();

		$cron->schedule();
	}
}
