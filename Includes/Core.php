<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
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

use Actirise\Includes\Loader;
use Actirise\Includes\Cron;
use Actirise\Includes\AbstractCore;
use Actirise\Includes\CacheRules;
use ActiriseAdmin\Includes\Core as AdminCore;
use ActirisePublic\Includes\Core as PublicCore;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
final class Core extends AbstractCore {
	private $plugin_cron;
	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		if ( defined( 'ACTIRISE_VERSION' ) ) {
			$this->version = ACTIRISE_VERSION;
		} else {
			$this->version = '2.0.0';
		}

		$this->plugin_name   = 'actirise';
		$this->plugin_prefix = 'actirise_';

		$this->loader = new Loader();

		if ( is_admin() ) {
			$this->define_admin_hooks();
		} else {
			$this->define_public_hooks();
		}

		$this->plugin_cron = new Cron();

		if ( ACTIRISE_CRON === 'true' ) {
			$this->define_cron_hooks();
		}

		$this->define_cache_rules();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_admin_hooks() {
		$plugin_admin = new AdminCore( $this->get_plugin_name(), $this->get_plugin_prefix(), $this->get_version(), $this->get_i18n(), $this->get_loader() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( isset( $_GET['page'] ) && $_GET['page'] === 'actirise-settings' ) ) {
			$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts', 10, 1 );
		} else {
			$plugin_admin->check_settings();
		}

		$this->loader->add_filter( 'plugin_action_links_' . ACTIRISE_BASENAME, $plugin_admin, 'add_action_links' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'migrations' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'set_locale' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_public_hooks() {
		$plugin_public = new PublicCore( $this->get_plugin_name(), $this->get_plugin_prefix(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_public, 'init' );
	}

	/**
	 * Register all of the hooks related to the cron functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_cron_hooks() {
		$this->loader->add_action( 'actirise_cron_update_adstxt', $this->plugin_cron, 'update_adstxt' );
		$this->loader->add_action( 'actirise_cron_update_presized_div', $this->plugin_cron, 'check_presized_div' );
		$this->loader->add_action( 'actirise_cron_update_debug_token', $this->plugin_cron, 'refresh_token' );
		$this->loader->add_action( 'actirise_cron_update_fast_cmp', $this->plugin_cron, 'get_fast_cmp' );

		$this->plugin_cron->schedule();
	}

	/**
	 * Register all of the hooks related to the cache rules functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @return   void
	 */
	private function define_cache_rules() {
		$plugin_cache_rules = new CacheRules();

		$this->loader->add_action( 'init', $plugin_cache_rules, 'init' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    2.0.0
	 * @return   void
	 */
	public function run() {
		$this->loader->run();
	}
}
