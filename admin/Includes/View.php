<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://actirise.com
 * @since      2.0.0
 *
 * @package    actirise
 * @subpackage actirise/admin/includes
 */

namespace ActiriseAdmin\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * The admin-specific functionality of the plugin.
 *
 * View manager
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/admin/includes
 * @author     actirise <support@actirise.com>
 */
final class View {
	/**
	 * View path
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $viewPath    View path
	 */
	private $viewPath;

	/**
	 * Initialize the class
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->viewPath = ACTIRISE_DIR . 'admin/views/';
	}

	/**
	 * Render view
	 *
	 * @since    2.0.0
	 *
	 * @param string               $view View name.
	 * @param array<string, mixed> $data Data to pass to view.
	 * @return void
	 */
	public function render( $view, $data = array() ) {
		foreach ( $data as $key => $val ) {
			$$key = $val;
		}

		$file = $this->viewPath . $view . '.php';

		include $file;
	}

	/**
	 * Render view in admin notice
	 *
	 * @since    2.0.0
	 *
	 * @param string               $view View name.
	 * @param array<string, mixed> $args Data to pass to view.
	 * @return void
	 */
	public function admin_notice( $view, $args = array() ) {
		add_action(
			'admin_notices',
			function () use ( $view, $args ) {
				$this->render( $view, $args );
			}
		);
	}
}
