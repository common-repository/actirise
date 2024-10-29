<?php
/**
 * Class for managing interactions with Actirise APIs
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

use WP_Error;
use Actirise\Includes\Logger;

/**
 * Class for managing interactions with Actirise APIs
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
final class Api {

	/**
	 * The base uri of the api.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      array<string, string>    $base_uris    list of base uri.
	 */
	private $base_uris = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->base_uris = array(
			'api'      => ACTIRISE_URL_API_V2,
			'flashbid' => ACTIRISE_URL_FLASHBID,
		);
	}

	/**
	 * Get the list of ad networks.
	 *
	 * @since    2.0.0
	 * @param    string                             $key_uri    The key of the base uri.
	 * @param    string                             $uri        The uri.
	 * @param    array<string, string>|array<mixed> $args       The arguments.
	 * @param    bool|string                        $auth       The auth.
	 * @return   mixed|array<mixed>|WP_Error    The list of ad networks.
	 */
	public function get( $key_uri, $uri, $args = array(), $auth = false ) {
		return $this->request( 'GET', $key_uri, $uri, $args, $auth );
	}

	/**
	 * Post to Actirise API
	 *
	 * @since    2.0.0
	 * @param    string                             $key_uri    The key of the base uri.
	 * @param    string                             $uri        The uri.
	 * @param    array<string, string>|array<mixed> $args       The arguments.
	 * @param    bool|string                        $auth       The auth.
	 * @return   mixed|array<mixed>|WP_Error    The response.
	 */
	public function post( $key_uri, $uri, $args = array(), $auth = false ) {
		return $this->request( 'POST', $key_uri, $uri, $args, $auth );
	}

	/**
	 * Patch to Actirise API
	 *
	 * @since    2.0.0
	 * @param    string                             $key_uri    The key of the base uri.
	 * @param    string                             $uri        The uri.
	 * @param    array<string, string>|array<mixed> $args       The arguments.
	 * @param    bool|string                        $auth       The auth.
	 * @return   mixed|array<mixed>|WP_Error    The response.
	 */
	public function patch( $key_uri, $uri, $args = array(), $auth = false ) {
		return $this->request( 'PATCH', $key_uri, $uri, $args, $auth );
	}

	/**
	 * Delete to Actirise API
	 *
	 * @since    2.0.0
	 * @param    string                             $key_uri    The key of the base uri.
	 * @param    string                             $uri        The uri.
	 * @param    array<string, string>|array<mixed> $args       The arguments.
	 * @param    bool|string                        $auth       The auth.
	 * @return   mixed|array<mixed>|WP_Error    The response.
	 */
	public function delete( $key_uri, $uri, $args = array(), $auth = false ) {
		return $this->request( 'DELETE', $key_uri, $uri, $args, $auth );
	}

	/**
	 * Get base url in array
	 *
	 * @since    2.0.0
	 * @param    string $key    The key of the base uri.
	 * @return   string    The base uri.
	 */
	private function get_base_uri( $key ) {
		return isset( $this->base_uris[ $key ] ) ? $this->base_uris[ $key ] : '';
	}

	/**
	 * Request to Actirise API
	 *
	 * @since    2.0.0
	 * @param    string                             $type       The type of request.
	 * @param    string                             $key_uri   The base uri.
	 * @param    string                             $uri        The uri.
	 * @param    array<string, string>|array<mixed> $args       The arguments.
	 * @param    bool|string                        $auth       The auth.
	 * @return   mixed|array<mixed>|WP_Error    The response.
	 */
	private function request( $type, $key_uri, $uri, $args, $auth = false ) {
		$base_uri = $this->get_base_uri( $key_uri );

		if ( $base_uri === '' ) {
			Logger::AddLog( 'Invalid base uri for ' . $key_uri . ' (' . $base_uri . ')', 'include/api', 'error' );

			return new WP_Error( 500, 'Invalid base uri for ' . $key_uri . ' (' . $base_uri . ')' );
		}

		$options_request = array(
			'method'    => $type,
			'timeout'   => 30,
			'sslverify' => true,
		);

		if ( strpos( $uri, 'adstxt_files' ) !== false ) {
			$options_request['headers'] = array(
				'Accept' => 'text/plain',
			);
		}

		$url = $base_uri . $uri;

		if ( 'GET' === $type ) {
			$url = add_query_arg( $args, $url );
		}

		if ( strpos( $uri, 'adstxt_files' ) === false ) {
			$options_request['headers'] = array(
				'Content-Type' => 'PATCH' === $type ? 'application/merge-patch+json' : 'application/json',
			);

			if ( 'PATCH' !== $type ) {
				$options_request['headers']['Accept'] = 'application/json';
			}

			if ( strpos( $uri, 'wordpress/account' ) !== false ) {
				$options_request['headers']['Bounce-Path'] = '/validate?id={id}&token={token}';
			}
		}

		if ( $auth ) {
			$options_request['headers']['Authorization'] = 'Bearer ' . $auth;
		}

		if ( 'PATCH' === $type || 'POST' === $type || 'DELETE' === $type ) {
			$json_encode = wp_json_encode( $args, JSON_UNESCAPED_SLASHES );

			if ( false === $json_encode ) {
				return new WP_Error( 500, 'Invalid json' );
			}

			/** @var string $json_encode */
			$options_request['body'] = $json_encode;
		}

		$response = wp_remote_request( $url, $options_request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code === 204 ) {
			return $response;
		}

		if ( $response_code !== 200 && $response_code !== 204 && $response_code !== 201 && $uri !== 'wordpress/account' ) {
			return new WP_Error( $response_code, wp_remote_retrieve_response_message( $response ) );
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return new WP_Error( 500, 'Empty body' );
		}

		if ( strpos( $uri, 'adstxt_files' ) !== false ) {
			return array( 'datas' => $body );
		}

		$data = json_decode( $body, true );

		if ( is_null( $data ) ) {
			return new WP_Error( 500, 'Invalid json' );
		}

		return $data;
	}
}
