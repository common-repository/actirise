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

use Actirise\Includes\AbstractCore;
use Actirise\Includes\Api;
use Actirise\Includes\Cron;
use Actirise\Includes\Helpers;
use Actirise\Includes\I18n;
use ActiriseAdmin\Includes\View;
use ActiriseAdmin\Includes\Ajax;
use ActirisePublic\Includes\AdsTxt;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two hooks to
 * enqueue the admin-facing stylesheet and JavaScript.
 * As you add hooks and methods, update this description.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/admin/includes
 * @author     actirise <support@actirise.com>
 */
final class Core extends AbstractCore {
	/**
	 * The ajax of the plugin.
	 *
	 * @since    2.0.0
	 * @access   public
	 * @var      Ajax    $ajax    The ajax of the plugin.
	 */
	public $ajax;

	/**
	 * The views of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      View    $views    The views of the plugin.
	 */
	private $views;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 * @param    string                    $plugin_name                            The name of this plugin.
	 * @param    string                    $plugin_prefix                          The unique prefix of this plugin.
	 * @param    string                    $version                                The version of this plugin.
	 * @param    \Actirise\Includes\I18n   $i18n           The i18n of this plugin.
	 * @param    \Actirise\Includes\Loader $loader     The loader of this plugin.
	 */
	public function __construct( $plugin_name, $plugin_prefix, $version, $i18n, $loader ) {
		$this->plugin_name   = $plugin_name;
		$this->plugin_prefix = $plugin_prefix;
		$this->version       = $version;
		$this->i18n          = $i18n;
		$this->loader        = $loader;

		$this->views = new View();
		$this->ajax  = new Ajax( $plugin_name );

		$this->register_ajax_event();
		$this->settings_init();
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    2.0.0
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		wp_register_script( $this->plugin_name, ACTIRISE_URL . 'admin/assets/js/main-' . $this->version . '.js', array(), $this->version, true );
		wp_register_style( $this->plugin_name, ACTIRISE_URL . 'admin/assets/css/main-' . $this->version . '.css', array(), $this->version );


		$this->localize_script( $hook_suffix );

		wp_enqueue_script( $this->plugin_name );
		wp_enqueue_style( $this->plugin_name );

		add_filter( 'script_loader_tag', array( $this, 'transform_to_module' ), 10, 3 );
	}

	/**
	 * Transform script to module
	 *
	 * @param string $tag The script tag.
	 * @param string $handle The script handle.
	 * @param string $src The script src.
	 *
	 * @return string
	 */
	public function transform_to_module( $tag, $handle, $src ) {
		if ( 'actirise' === $handle ) {
			$tag = '<script src="' . esc_url( $src ) . '" type="module"></script>';
		}

		return $tag;
	}

	/**
	 * Register the page for the admin area.
	 *
	 * @since    2.0.0
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function add_plugin_admin_menu( $hook_suffix ) {
		$adstxt_update_available = get_option( $this->plugin_name . '-adstxt-active', 'false' ) === 'false' && get_option( $this->plugin_name . '-adstxt-update', 'false' ) === 'true';
		$count_mod               = 0;

		if ( $adstxt_update_available ) {
			++$count_mod;
		}

		if ( get_option( $this->plugin_name . '-cta-cache-200', false ) === false ) {
			++$count_mod;
		}

		add_menu_page( 'Actirise', $count_mod > 0 ? 'Actirise <span class="awaiting-mod">' . strval( $count_mod ) . '</span>' : 'Actirise', 'manage_options', 'actirise-settings', array( $this, 'view_settings' ), plugins_url( 'actirise/admin/assets/images/icon-actirise.png' ), 2 );
	}

	/**
	 * Register the page for the admin area.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function view_settings() {
		$this->views->render( 'page-settings', array() );
	}

	/**
	 * Register the settings link in the plugins list.
	 *
	 * @since    2.0.0
	 * @param array<string> $links The current links.
	 * @return array<string>
	 */
	public function add_action_links( $links ) {
		$settings_link = '<a href="' . $this->get_page_url() . '">' . __( 'Settings', 'actirise' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Check if plugin is configured.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function check_settings() {
		if ( ( get_option( $this->plugin_name . '-settings-uuid' ) === null || get_option( $this->plugin_name . '-settings-uuid' ) === false ) ) {
			$this->views->admin_notice( 'notice-uuid-setting', array( 'url' => $this->get_page_url() ) );
		}
	}

	/**
	 * Run the migrations of the plugin.
	 *
	 * @since    2.4.0
	 * @return void
	 */
	public function migrations() {
		if ( get_option( $this->plugin_name . '-init-plugin' ) === '1' ) {
			$migrations = new Migrations( $this->plugin_name, $this->version );
			$migrations->migrate();
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    2.0.0
	 * @access   public
	 * @return   void
	 */
	public function set_locale() {
		$plugin_i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

		$this->i18n = $plugin_i18n;
	}

	/**
	 * Register actirise js object
	 *
	 * @since    2.0.0
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	private function localize_script( $hook_suffix ) {
		$current_user = wp_get_current_user();

		wp_localize_script(
			$this->plugin_name,
			'actiriseJS',
			array(
				'api_url'       => ACTIRISE_URL_API,
				'api_url_v2'    => ACTIRISE_URL_API_V2,
				'api_token'     => get_option( $this->plugin_name . '-settings-analytics-token', '' ),
				'api_userid'    => get_option( $this->plugin_name . '-settings-analytics-userid', '' ),
				'currency'      => get_option( $this->plugin_name . '-currency', 'USD' ),
				'plugin_url'    => plugins_url( $this->plugin_name ),
				'current_theme' => wp_get_theme()->get( 'Name' ),
				'nonce'         => wp_create_nonce( $this->plugin_name . '-settings' ),
				'init'          => get_option( $this->plugin_name . '-init', 'false' ),
				'uuid'          => get_option( $this->plugin_name . '-settings-uuid' ),
				'type'          => get_option( $this->plugin_name . '-settings-uuid-type', 'boot' ),
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'siteurl'       => site_url(),
				'domain'        => wp_parse_url( site_url() ),
				'cron'          => defined( 'ACTIRISE_CRON' ) && ACTIRISE_CRON === 'true',
				'tidy'          => extension_loaded( 'tidy' ),
				'bidders'       => array(),
				'noPub'         => get_option( $this->plugin_name . '-nopub', array() ),
				'debug'         => get_option( $this->plugin_name . '-debug-enabled', '1' ),
				'country_code'  => substr( get_bloginfo( 'language', 'raw' ), 3 ),
				'website_name'  => get_bloginfo( 'name' ),
				'website_url'   => get_bloginfo( 'url' ),
				'email'         => $current_user->user_email,
				'auto_update'   => Helpers::has_auto_update() ? 'true' : 'false',
				'customVar'     => array(
					'form'     => $this->get_custom_v_form(),
					'selected' => array(
						'custom1' => get_option( $this->plugin_name . '-custom1', 'author_ID' ),
						'custom2' => get_option( $this->plugin_name . '-custom2', 'category_0_slug' ),
						'custom3' => get_option( $this->plugin_name . '-custom3', 'post_ID' ),
						'custom4' => get_option( $this->plugin_name . '-custom4', '' ),
						'custom5' => get_option( $this->plugin_name . '-custom5', '' ),
					),
				),
				'version'       => array(
					'current' => ACTIRISE_VERSION,
					'wp'      => get_bloginfo( 'version' ),
				),
				'adstxt'        => array(
					'actirise' => AdsTxt::get_adstxt( false ),
					'file'     => get_option( $this->plugin_name . '-adstxt-file', 'false' ) === 'true',
					'custom'   => get_option( $this->plugin_name . '-adstxt-custom', array() ),
					'enabled'  => get_option( $this->plugin_name . '-adstxt-active', 'false' ) === 'true',
					'update'   => get_option( $this->plugin_name . '-adstxt-update', 'false' ) === 'true',
				),
				'presizedDiv'   => array(
					'actirise' => get_option( $this->plugin_name . '-presizeddiv-actirise', array() ),
					'selected' => get_option( $this->plugin_name . '-presizeddiv-selected', array() ),
					'enabled'  => get_option( $this->plugin_name . '-presizeddiv-active', 'false' ) === 'true',
					'notif'    => get_option( $this->plugin_name . '-presizeddiv-notif', array() ),
				),
				'cache'         => array(
					'wprocket'     => defined( 'WP_ROCKET_VERSION' ),
					'wpmeteor'     => defined( 'WPMETEOR_VERSION' ),
					'litespeed'    => defined( 'LSCWP_V' ),
					'w3totalcache' => defined( 'W3TC_VERSION' ),
					'notif'        => get_option( $this->plugin_name . '-cta-cache-200', false ) === false,
				),
				'fastcmp'       => Helpers::get_fastcmp_options( false ),
			)
		);
	}

	/**
	 * Register the ajax event for the admin area.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function register_ajax_event() {
		$this->loader->add_action( 'wp_ajax_' . $this->get_plugin_prefix() . 'ajax_action', $this->ajax, 'dispatch' );
	}

	/**
	 * Get all custom v available
	 *
	 * @since 2.0.0
	 * @return array<mixed> All custom v available
	 */
	private function get_custom_v_form() {
		$baseArray = array(
			array(
				'name'  => __( 'Not used', 'actirise' ),
				'value' => '',
			),
			array(
				'name'  => __( 'Author', 'actirise' ),
				'value' => 'author_ID',
			),
			array(
				'name'  => __( 'Post', 'actirise' ),
				'value' => array(
					array(
						'name'  => __( 'ID', 'actirise' ),
						'value' => 'post_ID',
					),
					array(
						'name'  => __( 'Slug', 'actirise' ),
						'value' => 'post_post_name',
					),
					array(
						'name'  => __( 'Type', 'actirise' ),
						'value' => 'post_post_type',
					),
				),
			),
			array(
				'name'  => __( 'Category Level 1', 'actirise' ),
				'value' => array(
					array(
						'name'  => __( 'ID', 'actirise' ),
						'value' => 'category_0_cat_ID',
					),
					array(
						'name'  => __( 'Slug', 'actirise' ),
						'value' => 'category_0_slug',
					),
					array(
						'name'  => __( 'Title', 'actirise' ),
						'value' => 'category_0_name',
					),
					array(
						'name'  => __( 'Parent', 'actirise' ),
						'value' => 'category_0_category_parent',
					),
				),
			),
			array(
				'name'  => __( 'Category Level 2', 'actirise' ),
				'value' => array(
					array(
						'name'  => __( 'ID', 'actirise' ),
						'value' => 'category_1_cat_ID',
					),
					array(
						'name'  => __( 'Slug', 'actirise' ),
						'value' => 'category_1_slug',
					),
					array(
						'name'  => __( 'Title', 'actirise' ),
						'value' => 'category_1_name',
					),
					array(
						'name'  => __( 'Parent', 'actirise' ),
						'value' => 'category_1_category_parent',
					),
				),
			),
			array(
				'name'  => __( 'Tag Level 1', 'actirise' ),
				'value' => array(
					array(
						'name'  => __( 'ID', 'actirise' ),
						'value' => 'tag_0_term_id',
					),
					array(
						'name'  => __( 'Slug', 'actirise' ),
						'value' => 'tag_0_slug',
					),
					array(
						'name'  => __( 'Title', 'actirise' ),
						'value' => 'tag_0_name',
					),
					array(
						'name'  => __( 'Parent', 'actirise' ),
						'value' => 'tag_0_parent',
					),
				),
			),
			array(
				'name'  => __( 'Tag Level 2', 'actirise' ),
				'value' => array(
					array(
						'name'  => __( 'ID', 'actirise' ),
						'value' => 'tag_1_term_id',
					),
					array(
						'name'  => __( 'Slug', 'actirise' ),
						'value' => 'tag_1_slug',
					),
					array(
						'name'  => __( 'Title', 'actirise' ),
						'value' => 'tag_1_name',
					),
					array(
						'name'  => __( 'Parent', 'actirise' ),
						'value' => 'tag_1_parent',
					),
				),
			),
		);

		$customFields = $this->get_custom_fields();

		if ( $customFields !== null && count( $customFields ) > 0 ) {
			$customFields = array_map(
				function ( $field ) {
					return array(
						'name'  => ucfirst( $field['meta_key'] ),
						'value' => 'customFields_' . $field['meta_key'],
					);
				},
				$customFields
			);

			$baseArray[] = array(
				'name'  => __( 'Custom Field', 'actirise' ),
				'value' => $customFields,
			);
		}

		return $baseArray;
	}

	/**
	 * Get all custom fields.
	 *
	 * @since 2.0.0
	 * @return array<array<string>>
	 */
	private function get_custom_fields() {
		global $wpdb;

		$cache_key = 'actirise_cache_custom_fields';
		$_posts    = wp_cache_get( $cache_key );

		if ( false === $_posts ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$_posts = $wpdb->get_results(
				"SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE meta_key NOT LIKE '\_%'",
				ARRAY_A
			);

			wp_cache_set( $cache_key, $_posts, '', 3600 );
		}

		return $_posts;
	}

	/**
	 * Init settings option
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function settings_init() {
		$cron = new Cron();

		if ( get_option( $this->plugin_name . '-fastcmp-uuid', '' ) === '' ) {
			$cron->get_fast_cmp();
		}

		if ( get_option( $this->plugin_name . '-presizeddiv-init' ) !== '1' ) {
			$cron->check_presized_div();

			update_option( $this->plugin_name . '-presizeddiv-init', true );
		}

		if ( get_option( $this->plugin_name . '-debug-enabled' ) === false ) {
			update_option( $this->plugin_name . '-debug-enabled', '1' );
		}

		if ( get_option( $this->plugin_name . '-init-plugin' ) === '1' ) {
			if (
				get_option( $this->plugin_name . '-settings-analytics-userid', '' ) !== '' &&
				get_option( $this->plugin_name . '-settings-analytics-token', '' ) !== ''
			) {
				$this->get_user_currency();
			}

			return;
		}

		if ( ! get_option( $this->plugin_name . '-custom1' ) ) {
			update_option( $this->plugin_name . '-custom1', 'author_ID' );
		}

		if ( ! get_option( $this->plugin_name . '-custom2' ) ) {
			update_option( $this->plugin_name . '-custom2', 'category_0_slug' );
		}

		if ( ! get_option( $this->plugin_name . '-custom3' ) ) {
			update_option( $this->plugin_name . '-custom3', 'post_ID' );
		}

		// enabled auto update
		if ( version_compare( get_bloginfo( 'version' ), '5.5', '>=' ) ) {
			$cron->set_auto_update( true );
		}

		update_option( $this->plugin_name . '-init-plugin', true );
	}

	/**
	 * Get page url.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function get_page_url() {
		return add_query_arg( array( 'page' => 'actirise-settings' ), admin_url( 'admin.php' ) );
	}

	/**
	 * Get user currency
	 *
	 * @since 2.3.15
	 * @return void
	 */
	private function get_user_currency() {
		/** @var string $token */
		$token = get_option( $this->plugin_name . '-settings-analytics-token', '' );

		if ( $token === '' ) {
			return;
		}

		$api      = new Api();
		$response = $api->get( 'api', 'users/' . get_option( $this->plugin_name . '-settings-analytics-userid', '' ), array(), $token );

		if ( is_wp_error( $response ) ) {
			return;
		}

		/** @var array<object{currency: string}> $response */
		if ( isset( $response['currency'] ) ) {
			update_option( $this->plugin_name . '-currency', $response['currency'] );
		}
	}
}
