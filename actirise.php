<?php
/**
 * @since             2.0.0
 * @package           Actirise
 * @author            actirise.com
 * @copyright         All rights reserved Copyright (c) 2022, actirise.com
 *
 * @wordpress-plugin
 * Plugin Name:       Actirise
 * Description:       The publishers' monetization and business intelligence platform.
 * Plugin URI:        https://www.actirise.com
 * Version:           2.5.5
 * Author:            Actirise
 * Author URI:        https://www.actirise.com
 * Disclaimer:        Use at your own risk. No warranty expressed or implied is provided.
 * Text Domain:       actirise
 * Domain Path:       /languages
 * Requires PHP:      > 5.6
 * Requires at least: > 4.5
 * Tested up to:      6.7
 */

namespace Actirise;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'ACTIRISE_VERSION' ) ) {
	define( 'ACTIRISE_VERSION', '2.5.5' );
}

if ( ! defined( 'ACTIRISE_FILE' ) ) {
	define( 'ACTIRISE_FILE', __FILE__ );
}

if ( ! defined( 'ACTIRISE_DIR' ) ) {
	define( 'ACTIRISE_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ACTIRISE_BASENAME' ) ) {
	define( 'ACTIRISE_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'ACTIRISE_URL' ) ) {
	define( 'ACTIRISE_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ACTIRISE_PREFIX' ) ) {
	define( 'ACTIRISE_PREFIX', 'ACTIRISE_' );
}

if ( ! defined( 'ACTIRISE_TEMPLATES_DIR' ) ) {
	define( 'ACTIRISE_TEMPLATES_DIR', ACTIRISE_DIR . 'templates' );
}

if ( ! defined( 'ACTIRISE_COMPONENTS_DIR' ) ) {
	define( 'ACTIRISE_COMPONENTS_DIR', ACTIRISE_DIR . 'template-components/' );
}

if ( ! defined( 'ACTIRISE_URL_API' ) ) {
	define( 'ACTIRISE_URL_API', 'https://api.actirise.com/v1.0/' );
}

if ( ! defined( 'ACTIRISE_URL_API_V2' ) ) {
	define( 'ACTIRISE_URL_API_V2', 'https://api.actirise.com/v2.0/' );
}

if ( ! defined( 'ACTIRISE_URL_APIVERSION' ) ) {
	define( 'ACTIRISE_URL_APIVERSION', 'https://s3.fr-par.scw.cloud/actirise-wordpress/' );
}

if ( ! defined( 'ACTIRISE_URL_FLASHBID' ) ) {
	define( 'ACTIRISE_URL_FLASHBID', 'https://www.flashb.id/' );
}

if ( ! defined( 'ACTIRISE_CRON' ) ) {
	$cron = 'true';

	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		$cron = 'false';
	}

	if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
		$cron = 'true';
	}

	/** @const string ACTIRISE_CRON */
	define( 'ACTIRISE_CRON', $cron );
}

$autoload = ACTIRISE_DIR . 'vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	return;
}

require_once $autoload;


use Actirise\Includes\Core;

register_activation_hook( __FILE__, array( 'Actirise\Includes\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Actirise\Includes\Desactivator', 'desactivate' ) );

/**
 * Run plugin
 *
 * @since 2.0.0
 * @return void
 */
function actirise_run() {
	$plugin = new Core();
	$plugin->run();
}

actirise_run();
