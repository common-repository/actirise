<?php
/**
 * This class allows the management of cache rules for the SGO plugin.
 *
 * @link       https://actirise.com
 * @since      2.0.0
 *
 * @package    actirise
 * @subpackage actirise/includes
 */

namespace Actirise\Includes\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

final class SGOCache {
	public function __construct() {
	}

	/**
	 * Initialize the class and start calling our hooks and filters.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_hooks() {
		add_filter( 'sgo_javascript_combine_excluded_external_paths', array( $this, 'exclude_external_script' ) );
		add_filter( 'sgo_javascript_combine_excluded_inline_content', array( $this, 'exclude_inline_script' ) );
	}

	/**
	 * Exclude Actirise script from JS combine
	 *
	 * @since 2.0.0
	 * @param array<string> $exclude_list
	 * @return array<string>
	 */
	public function exclude_external_script( $exclude_list ) {
		$exclude_list[] = 'flashb.id';
		$exclude_list[] = 'fastcmp.com';

		return $exclude_list;
	}

	/**
	 * Exclude Actirise script from JS combine
	 *
	 * @since 2.0.0
	 * @param array<string> $exclude_list
	 * @return array<string>
	 */
	public function exclude_inline_script( $exclude_list ) {
		$exclude_list[] = 'window._hbdbrk';
		$exclude_list[] = 'window.actirisePlugin';
		$exclude_list[] = 'window.FAST_CMP_OPTIONS';

		return $exclude_list;
	}
}
