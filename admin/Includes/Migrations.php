<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://actirise.com
 * @since      2.4.0
 *
 * @package    actirise
 * @subpackage actirise/admin/includes
 */

namespace ActiriseAdmin\Includes;

use ActirisePublic\Includes\AdsTxt;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * The admin-specific functionality of the plugin.
 *
 * This class executes the migrations of the plugin.
 *
 * @since      2.4.0
 * @package    actirise
 * @subpackage actirise/admin/includes
 * @author     actirise <support@actirise.com>
 */
final class Migrations {
	/**
	 * The ID of this plugin.
	 *
	 * @since    2.4.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    2.4.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The migrations of the plugin.
	 *
	 * @since    2.4.0
	 * @access   private
	 * @var      array<string, array<int, string>>    $migrations    The migrations of the plugin.
	 */
	private $migrations = array(
		'2.4.0' => array(
			'actirise_migrate_240',
		),
		'2.5.1' => array(
			'actirise_migrate_251',
		),
		'2.5.3' => array(
			'actirise_migrate_253',
		),
	);

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.4.0
	 * @param    string $plugin_name          The name of this plugin.
	 * @param    string $version              The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Run the migrations of the plugin.
	 *
	 * @since    2.4.0
	 * @return void
	 */
	public function migrate() {
		/** @var array<string> $migration */
		$migration = get_option( $this->plugin_name . '-migrations', array() );

		foreach ( $this->migrations as $version => $methods ) {
			if ( ! isset( $migration[ $version ] ) ) {
				foreach ( $methods as $method ) {
					$this->$method();
				}

				$migration[ $version ] = true;
			}
		}

		update_option( $this->plugin_name . '-migrations', $migration );
	}

	/**
	 * Migrate to version 2.4.0
	 *
	 * @since    2.4.0
	 * @return void
	 */
	private function actirise_migrate_240() {
		if ( get_option( $this->plugin_name . '-adstxt-active', 'false' ) !== 'true' || get_option( $this->plugin_name . '-adstxt-actirise', '' ) === '' ) {
			return;
		}

		$path_ads_txt = ABSPATH . 'ads.txt';

		if ( file_exists( $path_ads_txt ) ) {
			return;
		}

		AdsTxt::update_file();
	}

	/**
	 * Migrate to version 2.5.1
	 *
	 * @since    2.5.1
	 * @return void
	 */
	private function actirise_migrate_251() {
		$old_logs = get_option( $this->plugin_name . '_logs', false );

		if ( $old_logs !== false ) {
			update_option( $this->plugin_name . '-logs', $old_logs );
			delete_option( $this->plugin_name . '_logs' );
		}
	}

	/**
	 * Migrate to version 2.5.3
	 *
	 * @since    2.5.3
	 * @return void
	 */
	private function actirise_migrate_253() {
		delete_option( $this->plugin_name . '-api_version' );
		delete_option( $this->plugin_name . '-api-lastupdate' );

		wp_clear_scheduled_hook( 'actirise_cron_get_api_version' );
	}
}
