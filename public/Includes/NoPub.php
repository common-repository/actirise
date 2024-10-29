<?php
/**
 * Actirise NoPub
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

/**
 * Actirise NoPub
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/public/includes
 * @author     actirise <support@actirise.com>
 */
final class NoPub {
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * NoPub constructor.
	 *
	 * @since    2.0.0
	 * @param    string $plugin_name The name of this plugin.
	 */
	public function __construct( $plugin_name ) {
		$this->plugin_name = $plugin_name;
	}

	/**
	 * Get page type
	 *
	 * @return string
	 * @since 2.0.0
	 */
	public function get_page_type() {
		$page_type = '';

		if ( is_page() ) {
			$page_type = 'page';
		} elseif ( is_single() ) {
			$page_type = 'post';
		} elseif ( is_category() ) {
			$page_type = 'category';
		} elseif ( is_tag() ) {
			$page_type = 'post_tag';
		}

		return $page_type;
	}

	/**
	 * Check if page is no pub
	 *
	 * @return bool
	 * @since 2.0.0
	 */
	public function check_no_pub() {
		if ( $this->get_page_type() === '' ) {
			return false;
		}

		/** @var array<\stdClass> $no_pub */
		$no_pub = get_option( $this->plugin_name . '-nopub', array() );
		if ( ! is_array( $no_pub ) ) {
			return false;
		}

		if ( count( $no_pub ) === 0 ) {
			return false;
		}

		$no_pub = array_filter(
			$no_pub,
			function ( $item ) {
				return isset( $item->type ) && $item->type === $this->get_page_type();
			}
		);

		if ( count( $no_pub ) === 0 ) {
			return false;
		}

		$no_pub = array_filter(
			$no_pub,
			function ( $item ) {
				return isset( $item->id ) && $item->id === get_queried_object_id();
			}
		);

		if ( count( $no_pub ) === 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get all no pub url
	 *
	 * @since 2.0.0
	 * @return array<mixed>
	 */
	public function get_all_no_pub_url() {
		/** @var array<\stdClass> $no_pub */
		$no_pub = get_option( $this->plugin_name . '-nopub', array() );

		if ( ! is_array( $no_pub ) ) {
			return array();
		}

		$no_pub_clean = array_map(
			function ( $item ) {
				return $item->url;
			},
			$no_pub
		);

		return $no_pub_clean;
	}
}
