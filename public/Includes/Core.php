<?php
/**
 * The public-specific functionality of the plugin.
 *
 * @link       https://actirise.com
 * @since      2.0.0
 *
 * @package    actirise
 * @subpackage actirise/public/includes
 */

namespace ActirisePublic\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Actirise\Includes\AbstractCore;
use Actirise\Includes\Loader;
use ActirisePublic\Includes\Script;
use ActirisePublic\Includes\AdsTxt;
use ActirisePublic\Includes\PresizedDiv;
use ActirisePublic\Includes\NoPub;
use ActirisePublic\Includes\Debug;

/**
 * The public-specific functionality of the plugin.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/public/includes
 * @author     actirise <support@actirise.com>
 */
final class Core extends AbstractCore {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since       2.0.0
	 * @param       string $plugin_name    The name of this plugin.
	 * @param       string $plugin_prefix  The prefix of this plugin.
	 * @param       string $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $plugin_prefix, $version ) {
		$this->plugin_name   = $plugin_name;
		$this->plugin_prefix = $plugin_prefix;
		$this->version       = $version;
		$this->loader        = new Loader();
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function init() {
		$no_pub = new NoPub( $this->plugin_name );

		$script = new Script( $this->plugin_name, $this->version, $no_pub );
		$this->loader->add_action( 'wp_head', $script, 'head_integration', 1 );

		$adstxt = new AdsTxt();
		$adstxt->init();

		$presized_div = new PresizedDiv( $no_pub );
		$presized_div->render();

		$debug = new Debug( $this->plugin_name );
		$debug->init();

		$this->loader->run();
	}
}
