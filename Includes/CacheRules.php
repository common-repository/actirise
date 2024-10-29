<?php
/**
 * This class allows the management of cache rules for the proper functioning of our plugin.
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

use Actirise\Includes\Cache\SGOCache;

/**
 * This class allows the management of cache rules for the proper functioning of our plugin.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
final class CacheRules {
	/**
	 * Initialize the class
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize the class and start calling our hooks and filters.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function init() {
		$this->sgo();
	}

	/**
	 * Add hooks for SG Optimizer.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function sgo() {
		$sgo = new SGOCache();
		$sgo->add_hooks();
	}
}
