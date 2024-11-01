<?php
/**
 * Actirise Debug
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

use WP_Error;
use Actirise\Includes\Api;
use Actirise\Includes\Helpers;
use Actirise\Includes\Logger;

/**
 * Actirise Debug
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/public/includes
 * @author     actirise <support@actirise.com>
 */
final class Debug {
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */

	protected $plugin_name;

	/**
	 * Debug constructor.
	 *
	 * @since    2.0.0
	 * @param    string $plugin_name The name of this plugin.
	 */
	public function __construct( $plugin_name ) {
		$this->plugin_name = $plugin_name;
	}

	/**
	 * The loader of this plugin.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function init() {
		$enabled = get_option( $this->plugin_name . '-debug-enabled', '1' ) === '1';
		if ( ! $enabled ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;
		$method  = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : false;
		/** @var string $token */
		$token = get_option( $this->plugin_name . '-debug-token', '' );

		if ( $token !== '' && $request === '/debug' && $method === 'POST' ) {
			if ( $this->validate_token() ) {
				$this->render_debug();
			}
		}
	}

	/**
	 * Generate abd render debug
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function render_debug() {
		// return 200
		header( 'Content-Type: application/json' );
		header( 'Actirise: true' );
		header( 'HTTP/1.1 200 OK' );

		// return empty json
		wp_send_json_success(
			array(
				'wordpress' => array(
					'name'         => get_bloginfo( 'name' ),
					'description'  => get_bloginfo( 'description' ),
					'url'          => get_bloginfo( 'url' ),
					'wpurl'        => get_bloginfo( 'wpurl' ),
					'charset'      => get_bloginfo( 'charset' ),
					'version'      => get_bloginfo( 'version' ),
					'html_type'    => get_bloginfo( 'html_type' ),
					'rtl'          => is_rtl(),
					'language'     => get_bloginfo( 'language' ),
					'theme'        => $this->get_theme_name(),
					'pingback_url' => get_bloginfo( 'pingback_url' ),
					'plugins'      => $this->get_plugin_info(),
					'database'     => array(
						'charset' => $GLOBALS['wpdb']->charset,
						'collate' => $GLOBALS['wpdb']->collate,
					),
					'network'      => array(
						'MULTISITE'                  => defined( 'MULTISITE' ) ? constant( 'MULTISITE' ) : false,
						'ALLOW_SUBDIRECTORY_INSTALL' => defined( 'ALLOW_SUBDIRECTORY_INSTALL' ) ? constant( 'ALLOW_SUBDIRECTORY_INSTALL' ) : false,
						'BLOG_ID_CURRENT_SITE'       => defined( 'BLOG_ID_CURRENT_SITE' ) ? constant( 'BLOG_ID_CURRENT_SITE' ) : false,
						'DOMAIN_CURRENT_SITE'        => defined( 'DOMAIN_CURRENT_SITE' ) ? constant( 'DOMAIN_CURRENT_SITE' ) : false,
						'DIEONDBERROR'               => defined( 'DIEONDBERROR' ) ? constant( 'DIEONDBERROR' ) : false,
						'ERRORLOGFILE'               => defined( 'ERRORLOGFILE' ) ? constant( 'ERRORLOGFILE' ) : false,
						'BLOGUPLOADDIR'              => defined( 'BLOGUPLOADDIR' ) ? constant( 'BLOGUPLOADDIR' ) : false,
						'NOBLOGREDIRECT'             => defined( 'NOBLOGREDIRECT' ) ? constant( 'NOBLOGREDIRECT' ) : false,
						'PATH_CURRENT_SITE'          => defined( 'PATH_CURRENT_SITE' ) ? constant( 'PATH_CURRENT_SITE' ) : false,
						'UPLOADBLOGSDIR'             => defined( 'UPLOADBLOGSDIR' ) ? constant( 'UPLOADBLOGSDIR' ) : false,
						'SITE_ID_CURRENT_SITE'       => defined( 'SITE_ID_CURRENT_SITE' ) ? constant( 'SITE_ID_CURRENT_SITE' ) : false,
						'SUBDOMAIN_INSTALL'          => defined( 'SUBDOMAIN_INSTALL' ) ? constant( 'SUBDOMAIN_INSTALL' ) : false,
						'UPLOADS'                    => defined( 'UPLOADS' ) ? constant( 'UPLOADS' ) : false,
						'WPMU_ACCEL_REDIRECT'        => defined( 'WPMU_ACCEL_REDIRECT' ) ? constant( 'WPMU_ACCEL_REDIRECT' ) : false,
						'WPMU_SENDFILE'              => defined( 'WPMU_SENDFILE' ) ? constant( 'WPMU_SENDFILE' ) : false,
						'WP_ALLOW_MULTISITE'         => defined( 'WP_ALLOW_MULTISITE' ) ? constant( 'WP_ALLOW_MULTISITE' ) : false,
					),
				),
				'actirise' => array(
					'version'     => ACTIRISE_VERSION,
					'channel'     => get_option( $this->plugin_name . '-update-channel', 'stable' ),
					'init'        => get_option( $this->plugin_name . '-init', 'false' ),
					'uuid'        => get_option( $this->plugin_name . '-settings-uuid' ),
					'type'        => get_option( $this->plugin_name . '-settings-uuid-type', 'boot' ),
					'cron'        => defined( 'ACTIRISE_CRON' ) && ACTIRISE_CRON === 'true',
					'noPub'       => get_option( $this->plugin_name . '-nopub', array() ),
					'customVar'   => array(
						'custom_fields' => $this->get_custom_fields(),
						'selected'      => array(
							'custom1' => get_option( $this->plugin_name . '-custom1', 'author_ID' ),
							'custom2' => get_option( $this->plugin_name . '-custom2', 'category_0_slug' ),
							'custom3' => get_option( $this->plugin_name . '-custom3', 'post_ID' ),
							'custom4' => get_option( $this->plugin_name . '-custom4', '' ),
							'custom5' => get_option( $this->plugin_name . '-custom5', '' ),
						),
					),
					'adstxt'      => array(
						'actirise' => get_option( $this->plugin_name . '-adstxt-actirise', '' ),
						'custom'   => get_option( $this->plugin_name . '-adstxt-custom', array() ),
						'enabled'  => get_option( $this->plugin_name . '-adstxt-active', 'false' ) === 'true',
						'update'   => get_option( $this->plugin_name . '-adstxt-update', 'false' ) === 'true',
						'file'     => get_option( $this->plugin_name . '-adstxt-file', 'false' ),
					),
					'presizedDiv' => array(
						'actirise' => get_option( $this->plugin_name . '-presizeddiv-actirise', array() ),
						'selected' => get_option( $this->plugin_name . '-presizeddiv-selected', array() ),
						'enabled'  => get_option( $this->plugin_name . '-presizeddiv-active', 'false' ) === 'true',
						'notif'    => get_option( $this->plugin_name . '-presizeddiv-notif', array() ),
					),
					'cache'       => array(
						'wprocket'  => defined( 'WP_ROCKET_VERSION' ),
						'wpmeteor'  => defined( 'WPMETEOR_VERSION' ),
						'litespeed' => defined( 'LSCWP_V' ),
					),
					'fastcmp'     => Helpers::get_fastcmp_options( false ),
					'api'         => array(
						'api_url'     => ACTIRISE_URL_API,
						'api_url_v2'  => ACTIRISE_URL_API_V2,
						'api_token'   => !empty(get_option( $this->plugin_name . '-settings-analytics-token', '' )) ? true : false,
						'api_userid'  => get_option( $this->plugin_name . '-settings-analytics-userid', '' ),
						'currency'    => get_option( $this->plugin_name . '-currency', 'USD' ),
					),
					'autoUpdate'  => get_option( $this->plugin_name . '-auto-update', 'false' ) === 'true',
					'logs'        => Logger::GetLogs(),
				),
				'server' => array(
					'php' => array(
						'version'    => phpversion(),
						'extensions' => get_loaded_extensions(),
					),
					'webserver' => array(
						'server_software'    => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : false,
						'server_protocol'    => isset( $_SERVER['SERVER_PROTOCOL'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) ) : false,
						'request_time'       => isset( $_SERVER['REQUEST_TIME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME'] ) ) : false,
						'request_time_float' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME_FLOAT'] ) ) : false,
					),
				),
			)
		);
	}

	/**
	 * Send token to Actirise API
	 *
	 * @since 2.5.5
	 * @param string         $token
	 * @return bool
	 */
	public static function send_token_to_api( $token ) {
		$args = array(
			'domain'   => rawurlencode( Helpers::get_server_details()['host'] ),
			'token'    => $token,
		);

		$api_url  = 'wordpress_tokens';
		$api      = new Api();
		$response = $api->post( 'api', $api_url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::AddLog( 'send_token_to_api wp error ' . $response->get_error_code(), 'public/include/debug', 'error' );

			return false;
		}

		if ( ! is_array( $response ) ) {
			Logger::AddLog( 'send_token_to_api is not array', 'public/include/debug', 'error' );

			return false;
		}

		if ( ! isset( $response['token'] ) ) {
			Logger::AddLog( 'send_token_to_api is not isset', 'public/include/debug', 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Get list of plugin installed and activated
	 *
	 * @since 2.0.0
	 * @return array<mixed>
	 */
	private function get_plugin_info() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		/** @var array<string> $active_plugins */
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins = array();

		foreach ( $all_plugins as $key => $value ) {
			$is_active = in_array( $key, $active_plugins );

			$plugins[ $key ] = array(
				'name'    => $value['Name'],
				'version' => $value['Version'],
				'active'  => $is_active,
			);
		}

		// sort by name and active
		uasort(
			$plugins,
			function ( $a, $b ) {
				if ( $a['active'] === $b['active'] ) {
					return 0;
				}
				return ( $a['active'] > $b['active'] ) ? -1 : 1;
			}
		);

		return $plugins;
	}

	/**
	 * Get list of custom fields
	 *
	 * @since 2.0.0
	 * @return array<string, string>
	 */
	private function get_custom_fields() {
		global $wpdb;

		$cache_key     = 'actirise_cache_custom_fields';
		$custom_fields = wp_cache_get( $cache_key );

		if ( false === $custom_fields ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$custom_fields = $wpdb->get_results(
				"SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE meta_key NOT LIKE '\_%'",
				ARRAY_A
			);

			wp_cache_set( $cache_key, $custom_fields, '', 3600 );
		}

		if ( $custom_fields !== null && count( $custom_fields ) > 0 ) {
			$custom_fields = array_map(
				function ( $field ) {
					return array(
						'name'  => ucfirst( $field['meta_key'] ),
						'value' => 'custom_fields_' . $field['meta_key'],
					);
				},
				$custom_fields
			);
		}

		return $custom_fields;
	}

	/**
	 * Get theme name
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function get_theme_name() {
		if ( is_child_theme() ) {
			$theme = wp_get_theme()->template;

			return wp_get_theme( $theme )->name;
		} else {
			return wp_get_theme()->name;
		}
	}

	/**
	 * Check if the token is valid
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	private function validate_token() {
		$post_data = array(
			'token' => isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( $post_data['token'] === null ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var string $token */
		$token = $post_data['token'];
		/** @var string $current_token */
		$current_token = get_option( $this->plugin_name . '-debug-token', '' );
		$current_token = Helpers::hash_token( $current_token );

		if ( $token !== $current_token ) {
			Logger::AddLog( 'validate_token token invalide', 'public/include/debug', 'error' );
			$error = new WP_Error( '500', 'Token invalid' );

			wp_send_json_error( $error );
		}

		return true;
	}

	/**
	 * Generate and send the token if it doesn't exist
	 *
	 * @since    2.5.5
	 * @return void
	 */
	public function check_token() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		/** @var string $token */
		$token = get_option( $this->plugin_name . '-debug-token', '' );

		if ( $token === '' || substr( $token, 0, 2 ) !== 'V2' ) {
			$token = Helpers::generate_token( 'V2_' );
			if ( $this->send_token_to_api( $token ) ) {
				update_option( $this->plugin_name . '-debug-token', $token );
			}
		}

		return;
	}
}
