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

use WP_Error;
use Actirise\Includes\Api;
use Actirise\Includes\Cron;
use Actirise\Includes\Helpers;
use Actirise\Includes\Logger;
use ActirisePublic\Includes\AdsTxt;

/**
 * The admin-specific functionality of the plugin.
 *
 * Ajax request manager
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/admin/includes
 * @author     actirise <support@actirise.com>
 */
final class Ajax {
	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * All ajax events
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      array<string>    $events    All ajax events.
	 */
	private $events;

	/**
	 * Api class
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      Api    $api    Api class.
	 */
	private $api;

	/**
	 * Cron class
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      Cron    $cron    Cron class.
	 */
	private $cron;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 * @param    string $plugin_name          The name of this plugin.
	 */
	public function __construct( $plugin_name ) {
		$this->plugin_name = $plugin_name;

		$this->api  = new Api();
		$this->cron = new Cron();

		$this->events = array(
			'settings'                           => 'set_settings',
			'set_custom_var'                     => 'set_custom_var',
			'toggle_feature'                     => 'toggle_feature',
			'get_adstxt'                         => 'get_adstxt',
			'get_adstxt_custom'                  => 'get_adstxt_custom',
			'set_adstxt'                         => 'set_adstxt',
			'set_adstxt_notification_read'       => 'set_adstxt_notification_read',
			'set_presized_div'                   => 'set_presized_div',
			'set_presized_div_notification_read' => 'set_presized_div_notification_read',
			'get_bidders'                        => 'get_bidders',
			'get_articles_by_keywords'           => 'get_articles_by_keywords',
			'add_no_pub'                         => 'add_no_pub',
			'remove_no_pub'                      => 'remove_no_pub',
			'check_url_no_pub'                   => 'check_url_no_pub',
			'url_exists'                         => 'url_exists',
			'enabled_debug'                      => 'enabled_debug',
			'cta_cache'                          => 'cta_cache',
			'update_token_analytics'             => 'update_token_analytics',
			'toggle_fast_cmp'                    => 'toggle_fast_cmp',
			'options_fast_cmp'                   => 'options_fast_cmp',
			'login_analytics'                    => 'login_analytics',
			'register'                           => 'register',
			'update_currency'                    => 'update_currency',
			'get_logs'                           => 'get_logs',
		);

		add_filter( 'posts_where', array( $this, 'search_post_title' ), 10, 2 );
	}

	/**
	 * Dispatch ajax request
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function dispatch() {
		if ( $this->check_nonce() !== true ) {
			return;
		}

		$action = isset( $_POST['action_actirise'] ) ? sanitize_text_field( wp_unslash( $_POST['action_actirise'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_null( $action ) ) {
			$error = new WP_Error( '404', 'Action empty' );

			wp_send_json_error( $error );
		}

		if ( ! array_key_exists( $action, $this->events ) ) {
			$error = new WP_Error( '404', 'Action not found' );

			wp_send_json_error( $error );
		}

		$method = $this->events[ $action ];

		if ( ! method_exists( $this, $method ) ) {
			$error = new WP_Error( '404', 'Method not found' );

			wp_send_json_error( $error );
		}

		$this->$method();
	}

	/**
	 * Filter for search post title by LIKE
	 *
	 * @since    2.0.0
	 * @param string    $where
	 * @param \WP_Query $wp_query
	 * @return string
	 */
	public function search_post_title( $where, $wp_query ) {
		global $wpdb;

		$title = $wp_query->get( 'search_title' );

		if ( $title ) {
			/** @var string $title_sql */
			$title_sql = esc_sql( $wpdb->esc_like( $title ) );
			$where    .= ' AND ' . $wpdb->posts . ".post_title LIKE '%" . $title_sql . "%'";
		}

		return $where;
	}

	/**
	 * Save settings
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function set_settings() {
		$post_data = array(
			'uuid'       => isset( $_POST['uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['uuid'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'force'      => isset( $_POST['force'] ) ? sanitize_text_field( wp_unslash( $_POST['force'] ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'autoUpdate' => isset( $_POST['autoUpdate'] ) ? sanitize_text_field( wp_unslash( $_POST['autoUpdate'] ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['uuid'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$force = $post_data['force'] === 'true';

		$this->cron->set_auto_update( $post_data['autoUpdate'] === 'true' );

		$settings = $this->save_settings( $post_data['uuid'], $force );

		if ( $settings === true ) {
			$this->cron->update_adstxt();
			$this->cron->check_presized_div();

			if ( ACTIRISE_CRON === 'true' ) {
				$this->cron->schedule();
			}
		}

		if ( $settings === true && ! get_option( $this->plugin_name . '-init' ) ) {
			update_option( $this->plugin_name . '-init', true );
		}

		wp_send_json(
			$settings === true
			?
				array(
					'success' => true,
					'data'    => array(
						'type'        => get_option( $this->plugin_name . '-settings-uuid-type' ),
						'presizedDiv' => get_option( $this->plugin_name . '-presizeddiv-selected', array() ),
						'adstxt'      => get_option( $this->plugin_name . '-adstxt-actirise', '' ),
						'customv'     => array(
							'custom1' => get_option( $this->plugin_name . '-custom1', 'author_ID' ),
							'custom2' => get_option( $this->plugin_name . '-custom2', 'category_0_slug' ),
							'custom3' => get_option( $this->plugin_name . '-custom3', 'post_ID' ),
							'custom4' => get_option( $this->plugin_name . '-custom4', '' ),
							'custom5' => get_option( $this->plugin_name . '-custom5', '' ),
						),
					),
				)
			: __( 'Your settings were not saved correctly: ', 'actirise' ) . $settings
		);
	}

	/**
	 * Save custom var
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function set_custom_var() {
		$custom_var = isset( $_POST['customVar'] ) ? sanitize_text_field( wp_unslash( $_POST['customVar'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_null( $custom_var ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$json_custom_var = json_decode( stripslashes( $custom_var ) );

		if ( ! is_array( $json_custom_var ) ) {
			$error = new WP_Error( '500', 'Need array' );

			wp_send_json_error( $error );
		}

		/** @var array<\stdClass> $json_custom_var */
		foreach ( $json_custom_var as $custom ) {
			update_option( $this->plugin_name . '-' . $custom->name, $custom->value );
		}

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Enabled or disabled feature
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function toggle_feature() {
		$post_data = array(
			'feature'       => isset( $_POST['feature'] ) ? sanitize_text_field( wp_unslash( $_POST['feature'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'feature_value' => isset( $_POST['feature_value'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_value'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['feature'] ) || is_null( $post_data['feature_value'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		if ( ! in_array( $post_data['feature'], array( 'adstxt', 'presizeddiv' ), true ) ) {
			$error = new WP_Error( '500', 'Feature not found' );

			wp_send_json_error( $error );
		}

		if ( $post_data['feature'] === 'adstxt' ) {
			$path_ads_txt = ABSPATH . 'ads.txt';

			if ( $post_data['feature_value'] === 'true' ) {
				AdsTxt::update_file();

				$this->cron->update_adstxt();
			} elseif ( file_exists( $path_ads_txt ) ) {
					wp_delete_file( $path_ads_txt );

					update_option( $this->plugin_name . '-adstxt-file', false );
			}
		}

		update_option( $this->plugin_name . '-' . $post_data['feature'] . '-active', $post_data['feature_value'] );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Get ads.txt
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function get_adstxt() {
		if ( ! get_option( $this->plugin_name . '-settings-uuid' ) || ! get_option( $this->plugin_name . '-settings-uuid-type', 'boot' ) ) {
			$error = new WP_Error( '500', 'Error' );

			wp_send_json_error( $error );
		}

		$response = AdsTxt::get_from_api();

		if ( false !== $response ) {
			wp_send_json(
				array(
					'success' => true,
					'data'    => $response,
				)
			);
		} else {
			Logger::AddLog( 'get_adstxt false', 'admin/include/ajax', 'error' );
			$error = new WP_Error( '500', 'Error' );

			wp_send_json_error( $error );
		}
	}

	/**
	 * Get ads.txt custom
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function get_adstxt_custom() {
		$adstxt_custom = get_option( $this->plugin_name . '-adstxt-custom', array() );

		wp_send_json(
			array(
				'success' => true,
				'data'    => ! $adstxt_custom ? array() : $adstxt_custom,
			)
		);
	}

	/**
	 * Set ads.txt
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function set_adstxt() {
		$post_data = array(
			'adsTxtCustom' => isset( $_POST['adsTxtCustom'] ) ? sanitize_text_field( wp_unslash( $_POST['adsTxtCustom'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( empty( $post_data['adsTxtCustom'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		update_option( $this->plugin_name . '-adstxt-custom', $post_data['adsTxtCustom'] );

		AdsTxt::update_file();

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Set ads.txt notification read
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function set_adstxt_notification_read() {
		update_option( $this->plugin_name . '-adstxt-update', 'false' );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Save settings
	 *
	 * @since    2.0.0
	 * @param string $identifiant Identifiant.
	 * @param bool   $force       Force.
	 * @return string|bool
	 */
	private function save_settings( $identifiant, $force ) {
		if ( $force ) {
			update_option( $this->plugin_name . '-settings-uuid', $identifiant );
			update_option( $this->plugin_name . '-settings-uuid-type', 'universal' );

			return true;
		}

		$response_entity = $this->api->get( 'api', 'organizations/check/' . $identifiant );

		if ( is_wp_error( $response_entity ) ) {
			if ( $response_entity->get_error_code() === 404 ) {
				$response_website = $this->api->get(
					'api',
					'websites/check/' . $identifiant,
					array(
						'domain' => rawurlencode( Helpers::get_server_details()['host'] ),
					)
				);

				if ( is_wp_error( $response_website ) ) {
					if ( $response_website->get_error_code() === 404 ) {
						return __( 'This identifier does not exist', 'actirise' );
					} else {
						return 'Error system - EWUNKB';
					}
				} else {
					if ( ! is_array( $response_website ) ) {
						return 'Error system - EWUNKA';
					}

					/** @var array<string, array<mixed>> $response_website */
					if ( count( $response_website['datas'] ) !== 1 ) {
						return 'Error system - EWUNKC';
					}

					if ( $response_website['datas'][0] === 'OK' ) {
						update_option( $this->plugin_name . '-settings-uuid', $identifiant );
						update_option( $this->plugin_name . '-settings-uuid-type', 'boot' );

						return true;
					}

					return 'Error system - EWUNKD';
				}
			} else {
				return 'Error system - EUNKB2';
			}
		} else {
			/** @var array<string, array<mixed>> $response_entity */
			if ( isset( $response_entity['response'] ) && $response_entity['response']['code'] === 204 ) {
				update_option( $this->plugin_name . '-settings-uuid', $identifiant );
				update_option( $this->plugin_name . '-settings-uuid-type', 'universal' );

				return true;
			}

			if ( ! is_array( $response_entity ) ) {
				return 'Error system - EUNKA';
			}

			/** @var array<string, array<mixed>> $response_entity */
			if ( count( $response_entity['datas'] ) !== 1 ) {
				return 'Error system - EUNKC';
			}

			if ( $response_entity['datas'][0] === 'OK' ) {
				update_option( $this->plugin_name . '-settings-uuid', $identifiant );
				update_option( $this->plugin_name . '-settings-uuid-type', 'universal' );

				return true;
			}
		}

		return 'Error system - EUNKD';
	}

	/**
	 * Get settings
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function set_presized_div() {
		$post_data = array(
			'presizedDiv' => isset( $_POST['presizedDiv'] ) ? sanitize_text_field( wp_unslash( $_POST['presizedDiv'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( empty( $post_data['presizedDiv'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var array<object{slotName: string, active: boolean}> $presized_div_post */
		$presized_div_post = json_decode( stripslashes( $post_data['presizedDiv'] ) );
		$presized_div      = array();

		foreach ( $presized_div_post as $key => $value ) {
			$presized_div[] = array(
				'slotName' => $value->slotName,
				'active'   => $value->active,
			);
		}

		update_option( $this->plugin_name . '-presizeddiv-selected', $presized_div );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Get bidders active
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function get_bidders() {
		$uuid = get_option( $this->plugin_name . '-settings-uuid' );

		$args = array(
			'domain' => rawurlencode( Helpers::get_server_details()['host'] ),
		);

		if ( get_option( 'actirise-settings-uuid-type', 'boot' ) === 'universal' ) {
			$args['universal'] = 'true';
			$args['product']   = '3';
		}

		/** @var wp_error|array<mixed> $response */
		$response = $this->api->get(
			'api',
			'bidders/' . $uuid . '/active',
			$args
		);

		$error = null;

		if ( is_wp_error( $response ) ) {
			if ( $response->get_error_code() === 404 ) {
				$error = __( 'This identifier does not exist', 'actirise' );
			} elseif ( $response->get_error_code() === 204 ) {
					$error    = null;
					$response = array(
						'datas' => array(),
					);
			} else {
				$error = 'Error system - EWUNKB';
			}
		} elseif ( ! is_array( $response ) ) {
				$error = 'Error system - EWUNKA';
		}

		if ( $error !== null ) {
			wp_send_json_error( $response );
		}

		/** @var array{datas: mixed} $response */
		wp_send_json(
			array(
				'success' => true,
				'data'    => $response,
			)
		);
	}

	/**
	 * Get page, post, category, tag by keywords
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function get_articles_by_keywords() {
		$post_data = array(
			'keywords' => isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['keywords'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$keywords = $post_data['keywords'];

		$query_page = new \WP_Query(
			array(
				'post_type'    => 'any',
				'search_title' => $keywords,
				'cache_result' => false,
				'orderby'      => 'post_date',
			)
		);

		/** @var array<\WP_Post> $post */
		$post   = $query_page->posts;
		$result = array();

		foreach ( $post as $page ) {
			if ( $page->post_type !== 'attachment' ) {
				$image = get_the_post_thumbnail_url( $page->ID );

				if ( has_excerpt( $page ) ) {
					$page->post_content = wp_strip_all_tags( get_the_excerpt( $page ), true );
				}

				if ( $page->post_type === 'page' ) {
					$page->post_content = 'article';
				}

				$result[] = array(
					'id'            => $page->ID,
					'guid'          => $page->guid,
					'title'         => $page->post_title,
					'publishedDate' => $page->post_date,
					'summary'       => substr( $page->post_content, 0, 100 ),
					'image'         => $image,
					'type'          => $page->post_type,
				);
			}
		}

		$count = count( $result );

		$query_terms = get_terms(
			array(
				'taxonomy'   => array( 'post_tag', 'category' ),
				'name__like' => $keywords,
			)
		);

		$result_terms = array();
		/** @var array<\WP_Term> $query_terms */
		foreach ( $query_terms as &$terms ) {
			$term_link = get_term_link( $terms );

			if ( is_wp_error( $term_link ) ) {
				continue;
			}

			// $terms->url = $term_link;
			$result_terms[] = array(
				'id'      => $terms->term_id,
				'title'   => $terms->name,
				'summary' => substr( $terms->description, 0, 100 ),
				'type'    => $terms->taxonomy,
				'url'     => $term_link,
			);
		}

		$count += count( $result_terms );

		wp_send_json(
			array(
				'page'     => $result,
				'taxonomy' => $result_terms,
				'count'    => $count,
				'keywords' => $keywords,
				'success'  => true,
			)
		);
	}

	/**
	 * Add no pub item
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function add_no_pub() {
		$post_data = array(
			'article' => isset( $_POST['article'] ) ? sanitize_text_field( wp_unslash( $_POST['article'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'summary' => isset( $_POST['summary'] ) ? sanitize_text_field( wp_unslash( $_POST['summary'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['article'] ) || is_null( $post_data['summary'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var \stdClass $article */
		$article = json_decode( stripslashes( $post_data['article'] ) );
		/** @var string $summary */
		$summary = stripslashes( $post_data['summary'] );

		$article->summary = $summary;

		/** @var array<\stdClass> $no_pub */
		$no_pub = get_option( $this->plugin_name . '-nopub', array() );

		$no_pub[] = $article;

		foreach ( $no_pub as $key => $value ) {
			if ( ! isset( $value->url ) ) {
				unset( $no_pub[ $key ] );
			}
		}

		update_option( $this->plugin_name . '-nopub', $no_pub );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Remove no pub item
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function remove_no_pub() {
		$post_data = array(
			'id'   => isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'type' => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['id'] ) || is_null( $post_data['type'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var int $id */
		$id = intval( $post_data['id'] );
		/** @var string $type */
		$type = $post_data['type'];

		/** @var array<\stdClass> $no_pub */
		$no_pub = get_option( $this->plugin_name . '-nopub', array() );

		/** @var array<object{id: int, type: string, url: string}> $no_pub */
		foreach ( $no_pub as $key => $value ) {
			if ( ! isset( $value->url ) ) {
				unset( $no_pub[ $key ] );
			}
		}

		/** @var array<object{id: int, type: string}> $no_pub */
		foreach ( $no_pub as $key => $value ) {
			if ( $value->id === $id && $value->type === $type ) {
				unset( $no_pub[ $key ] );
			}
		}

		update_option( $this->plugin_name . '-nopub', $no_pub );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Check if url is valid and get informations (post, page, tag, category)
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function check_url_no_pub() {
		$post_data = array(
			'url' => isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['url'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$url = $post_data['url'];

		$result = new \stdClass();

		$post = url_to_postid( $url );

		if ( $post !== 0 ) {
			$post = get_post( $post );

			if ( $post !== null ) {
				$result->id            = $post->ID;
				$result->title         = $post->post_title;
				$result->publishedDate = $post->post_date;
				$result->summary       = $post->post_type === 'page' ? '' : substr( $post->post_content, 0, 100 );
				$result->image         = get_the_post_thumbnail_url( $post->ID );
				$result->type          = $post->post_type;
			}
		}

		$slug = explode( '/', $url );
		$slug = array_filter( $slug );

		if ( ! empty( $slug ) ) {
			$slug = end( $slug );

			$category = get_term_by( 'slug', $slug, 'category' );

			if ( $category !== false ) {
				/** @var \WP_Term $category */
				$result->id            = $category->term_id;
				$result->title         = $category->name;
				$result->publishedDate = '';
				$result->summary       = $category->description;
				$result->image         = null;
				$result->type          = 'category';
			}

			$tag = get_term_by( 'slug', $slug, 'post_tag' );

			if ( $tag !== false ) {
				/** @var \WP_Term $tag */
				$result->id            = $tag->term_id;
				$result->title         = $tag->name;
				$result->publishedDate = '';
				$result->summary       = $tag->description;
				$result->image         = null;
				$result->type          = 'post_tag';
			}
		}

		wp_send_json(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}

	/**
	 * Reset presized div notif
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function set_presized_div_notification_read() {
		update_option( $this->plugin_name . '-presizeddiv-notif', array() );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Check if url exist
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function url_exists() {
		$post_data = array(
			'id'   => isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'type' => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['id'] ) || is_null( $post_data['type'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var int $id */
		$id   = $post_data['id'];
		$type = $post_data['type'];

		$exist = false;

		if ( $type === 'category' || $type === 'post_tag' ) {
			$terms = get_term_by( 'ID', $id, $type );

			if ( $terms !== false ) {
				$exist = true;
			}
		} else {
			$post = get_post( $id );

			if ( $post !== 0 && $post !== null ) {
				if ( $post->post_status !== 'trash' ) {
					$exist = true;
				}
			}
		}

		wp_send_json(
			array(
				'success' => true,
				'exist'   => $exist,
			)
		);
	}

	/**
	 * Set debug mode
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function enabled_debug() {
		$post_data = array(
			'debug' => isset( $_POST['debug'] ) ? sanitize_text_field( wp_unslash( $_POST['debug'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['debug'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$debug = $post_data['debug'];

		update_option( $this->plugin_name . '-debug-enabled', $debug );

		if ( $debug === '1' ) {
			$this->cron->refresh_token();
		} else {
			$token = get_option( $this->plugin_name . '-debug-token' );

			/** @var array<string, string> $args */
			$args = array(
				'domain' => rawurlencode( Helpers::get_server_details()['host'] ),
				'token'  => $token,
			);

			$response = $this->api->delete( 'api', 'wordpress_tokens', $args );

			if ( is_wp_error( $response ) ) {
				Logger::AddLog( 'get_adstxt enabled_debug', 'admin/include/ajax', 'Error while deleting token' );
			}

			delete_option( $this->plugin_name . '-token' );
		}

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Set view CTA cache
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function cta_cache() {
		update_option( $this->plugin_name . '-cta-cache-200', true );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Update token for Actirise Api Analytics
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function update_token_analytics() {
		$post_data = array(
			'jwt'    => isset( $_POST['jwt'] ) ? sanitize_text_field( wp_unslash( $_POST['jwt'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'userId' => isset( $_POST['userId'] ) ? sanitize_text_field( wp_unslash( $_POST['userId'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( empty( $post_data['jwt'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		update_option( $this->plugin_name . '-settings-analytics-token', $post_data['jwt'] );
		update_option( $this->plugin_name . '-settings-analytics-userid', $post_data['userId'] );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Toggle fast cmp feature
	 *
	 * @since 2.2.0
	 * @return void
	 */
	private function toggle_fast_cmp() {
		$post_data = array(
			'enabled' => isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['enabled'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$fast_cmp_enabled = $post_data['enabled'];

		update_option( $this->plugin_name . '-fastcmp-enabled', $fast_cmp_enabled );

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Update options for fast cmp
	 *
	 * @since 2.2.0
	 * @return void
	 */
	private function options_fast_cmp() {
		$post_data = array(
			'options' => isset( $_POST['options'] ) ? sanitize_text_field( wp_unslash( $_POST['options'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( is_null( $post_data['options'] ) ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var array<mixed> $options */
		$options = json_decode( stripslashes( $post_data['options'] ) );

		foreach ( $options as $name => $value ) {
			update_option( $this->plugin_name . '-fastcmp-' . $name, $value );
		}

		wp_send_json(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Login to API v2
	 *
	 * @since 2.3.15
	 * @return void
	 */
	private function login_analytics() {
		$post_data = array(
			'email'    => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'password' => isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( $post_data['email'] === null || $post_data['password'] === null ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		$args = array(
			'email'    => $post_data['email'],
			'password' => $post_data['password'],
		);

		$response = $this->api->post( 'api', 'authentication_token', $args );

		if ( is_wp_error( $response ) ) {
			$error = new WP_Error( '500', 'Error while login wp_e' );

			wp_send_json_error( $error );
		}

		if ( ! is_array( $response ) ) {
			$error = new WP_Error( '500', 'Error while login not_a' );

			wp_send_json_error( $error );
		}

		if ( ! array_key_exists( 'token', $response ) ) {
			$error = new WP_Error( '500', 'Error while login arr_k_e' );

			wp_send_json_error( $error );
		}

		$token    = $response['token'];
		$userId   = $response['user'];
		$currency = $response['currency'];

		$userId = explode( '/', $userId );
		$userId = end( $userId );

		$responseCheckApi = $this->api->post(
			'api',
			'api_tokens',
			array(
				'name' => 'wordpress',
			),
			$token
		);

		if ( is_wp_error( $responseCheckApi ) ) {
			Logger::AddLog( 'login_analytics', 'admin/include/ajax', $responseCheckApi->get_error_message() );
			$error = new WP_Error( '500', 'Error while login wp_e_t' );

			wp_send_json_error( $error );
		}

		if ( ! is_array( $responseCheckApi ) ) {
			$error = new WP_Error( '500', 'Error while login not_a_t' );

			wp_send_json_error( $error );
		}

		if ( ! array_key_exists( 'token', $responseCheckApi ) ) {
			$error = new WP_Error( '500', 'Error while login arr_k_e_t' );

			wp_send_json_error( $error );
		}

		$reponseUuid = $this->api->get(
			'api',
			'organizations',
			array(
				'websites.domain' => rawurlencode( Helpers::get_server_details()['host'] ),
				'itemsPerPage'    => 1,
				'page'            => 1,
			),
			$token
		);

		if ( is_wp_error( $reponseUuid ) ) {
			$error = new WP_Error( '500', 'website 1' );

			wp_send_json_error( $error );
		}

		if ( ! is_array( $reponseUuid ) ) {
			$error = new WP_Error( '500', 'website 2' );

			wp_send_json_error( $error );
		}

		if ( count( $reponseUuid ) === 0 ) {
			$error = new WP_Error( '500', 'website 3' );

			wp_send_json_error( $error );
		}

		if ( ! array_key_exists( 'uuid', $reponseUuid[0] ) ) {
			$error = new WP_Error( '500', 'website 4' );

			wp_send_json_error( $error );
		}

		$uuid = $reponseUuid[0]['uuid'];

		wp_send_json(
			array(
				'success' => true,
				'data'    => array(
					'token'    => $responseCheckApi['token'],
					'uuid'     => $uuid,
					'userId'   => $userId,
					'currency' => $currency,
				),
			)
		);
	}

	/**
	 * Create new account
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function register() {
		$post_data = array(
			'firstname'       => isset( $_POST['firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['firstname'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'lastname'        => isset( $_POST['lastname'] ) ? sanitize_text_field( wp_unslash( $_POST['lastname'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'email'           => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'password'        => isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'type'            => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'companyName'     => isset( $_POST['companyName'] ) ? sanitize_text_field( wp_unslash( $_POST['companyName'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'companyWebsite'  => isset( $_POST['companyWebsite'] ) ? sanitize_text_field( wp_unslash( $_POST['companyWebsite'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'companyLocation' => isset( $_POST['companyLocation'] ) ? sanitize_text_field( wp_unslash( $_POST['companyLocation'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( $post_data['firstname'] === null || $post_data['lastname'] === null || $post_data['email'] === null || $post_data['password'] === null || $post_data['type'] === null ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var string $website_domain */
		$website_domain = wp_parse_url( get_bloginfo( 'url' ), PHP_URL_HOST );

		$args = array(
			'firstname'     => $post_data['firstname'],
			'lastname'      => $post_data['lastname'],
			'email'         => $post_data['email'],
			'plainPassword' => $post_data['password'],
			'type'          => $post_data['type'] === 'company' ? 'Company' : 'Individual',
			'name'          => get_bloginfo( 'name' ),
			'website'       => get_bloginfo( 'url' ),
			'currency'      => 'USD',
			'websites'      => array(
				array(
					'domain' => $website_domain,
					'name'   => get_bloginfo( 'name' ),
				),
			),
		);

		if ( $post_data['type'] === 'company' ) {
			$args['name']    = $post_data['companyName'];
			$args['website'] = $post_data['companyWebsite'];
		}

		$response = $this->api->post( 'api', 'wordpress/account', $args );

		if ( is_wp_error( $response ) ) {
			Logger::AddLog( 'Error while register wp_e', 'admin/include/ajax', 'error' );
			$error = new WP_Error( '500', 'Error while register wp_e' );

			wp_send_json_error( array( $error, $response, $args ) );
		}

		if ( ! is_array( $response ) ) {
			Logger::AddLog( 'Error while register not_a', 'admin/include/ajax', 'error' );
			$error = new WP_Error( '500', 'Error while register not_a' );

			wp_send_json_error( array( $error, $response ) );
		}

		wp_send_json(
			array(
				'success' => true,
				'data'    => $response,
			)
		);
	}

	/**
	 * Update currency settings
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function update_currency() {
		$post_data = array(
			'currency' => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		if ( $post_data['currency'] === null ) {
			$error = new WP_Error( '500', 'Post empty' );

			wp_send_json_error( $error );
		}

		/** @var string $user_id */
		$user_id = get_option( $this->plugin_name . '-settings-analytics-userid', '' );
		/** @var string $token */
		$token = get_option( $this->plugin_name . '-settings-analytics-token', '' );

		if ( $user_id !== '' && $token !== '' ) {
			$response = $this->api->patch(
				'api',
				'users/' . $user_id,
				array(
					'currency' => $post_data['currency'],
				),
				$token
			);

			if ( is_wp_error( $response ) ) {
				Logger::AddLog( 'Error while updating currency ' . $response->get_error_code(), 'admin/include/ajax', 'error' );
				$error = new WP_Error( '500', 'Error while updating currency' );

				wp_send_json_error( $error );
			}
		}

		update_option( $this->plugin_name . '-currency', $post_data['currency'] );

		wp_send_json(
			array(
				'success' => true,
				'data'    => $post_data['currency'],
			)
		);
	}

	/**
	 * Get logs
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function get_logs() {
		$logs = Logger::GetLogs();

		wp_send_json(
			array(
				'success' => true,
				'data'    => $logs,
			)
		);
	}

	/**
	 * Check nonce
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	private function check_nonce() {
		$nonce = isset( $_REQUEST['nonce'] ) ?
			sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) :
			'';

		if ( ! wp_verify_nonce( $nonce, 'actirise-settings' ) ) {
			$error = new WP_Error( '403', 'Security check is not valid' );

			wp_send_json_error( $error );
		}

		if ( false === check_ajax_referer( $this->plugin_name . '-settings', 'nonce', true ) ) {
			$error = new WP_Error( '403', 'Security check is not valid' );

			wp_send_json_error( $error );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$error = new WP_Error( '403', 'Security check is not valid' );

			wp_send_json_error( $error );
		}

		return true;
	}
}
