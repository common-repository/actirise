<?php
/**
 * Actirise Ads.txt
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

use Actirise\Includes\Api;
use Actirise\Includes\Cron;
use Actirise\Includes\Helpers;
use Actirise\Includes\Logger;

/**
 * Actirise Ads.txt
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/public/includes
 * @author     actirise <support@actirise.com>
 */
final class AdsTxt {
	/**
	 * Ads.txt constructor.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		if ( ACTIRISE_CRON !== 'true' ) {
			$this->check_adstxt_update();
		}
	}

	/**
	 * The loader of this plugin.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function init() {
		if ( get_option( 'actirise-adstxt-active', false ) !== false && get_option( 'actirise-adstxt-active', false ) !== 'false' ) {
			$request = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;

			if ( $request === '/ads.txt' && get_option( 'actirise-adstxt-actirise' ) !== false && get_option( 'actirise-adstxt-file' ) !== 'true' ) {
				$this->render_adstxt();
			}
		}
	}

	/**
	 * Generate abd render ads.txt
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function render_adstxt() {
		header( 'Content-Type: text/plain' );
		header( 'Actirise: true' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Thu, 29 Dec 1994 12:25:00 GMT' );
		header( 'Pragma: no-cache' );

		/** @var string $lastUpdate */
		$lastUpdate = get_option( 'actirise-adstxt-lastupdate', strval( time() ) );
		header( 'ETag: W/"' . md5( $lastUpdate ) . '"' );

		$adstxt = self::get_adstxt( true );

		echo esc_html( apply_filters( 'actirise_ads_txt_content', $adstxt ) );
		die();
	}

	/**
	 * Clean duplicate line
	 *
	 * @since    2.4.0
	 * @param string $adstxt
	 * @param string $lines
	 *
	 * @return string
	 */
	private static function cleanDuplicateLine( $adstxt, &$lines ) {
		$lines = explode( "\n", $lines );
		$lines = array_unique( $lines );

		foreach ( $lines as $key => &$line ) {
			$explodedLine = explode( ',', $line );

			if ( count( $explodedLine ) > 0 ) {
				$explodedLine[0] = strtolower( $explodedLine[0] );
			}

			if ( count( $explodedLine ) >= 2 ) {
				$explodedLine[2] = strtoupper( $explodedLine[2] );
			}

			$line = implode( ',', $explodedLine );

			if ( $line !== '' ) {
				if ( false !== strpos( $adstxt, $line ) ) {
					unset( $lines[ $key ] );
				}
			}
		}

		$lines = implode( "\n", $lines );

		return $lines;
	}

		/**
		 * Get ads.txt formated
		 *
		 * @since    2.0.0
		 * @param bool $custom
		 * @return string
		 */
	public static function get_adstxt( $custom = false ) {
		/** @var string $adstxtActirise */
		$adstxtActirise = get_option( 'actirise-adstxt-actirise', '' );

		preg_match( '/OWNERDOMAIN=(.*)\n/', $adstxtActirise, $matches );
		$ownerDomain = '';

		if ( isset( $matches[1] ) ) {
			$ownerDomain = $matches[1];
		}

		/** @var string $adstxtActirise */
		$adstxtActirise = preg_replace( '/MANAGERDOMAIN=.*\n/', '', $adstxtActirise );
		/** @var string $adstxtActirise */
		$adstxtActirise = preg_replace( '/OWNERDOMAIN=.*\n/', '', $adstxtActirise );
		/** @var string $adstxtActirise */
		$adstxtActirise = preg_replace( '/## START actirise.com ##\n/', '', $adstxtActirise );
		/** @var string $adstxtActirise */
		$adstxtActirise = preg_replace( '/## END actirise.com ##/', '', $adstxtActirise );

		/** @var string $adstxt */
		$adstxt  = '#---------------------------- Monetized by Actirise WordPress Plugin ----------------------------';
		$adstxt .= "\n";
		$adstxt .= "\n";
		$adstxt .= 'contact=hello@actirise.com';
		$adstxt .= "\n";
		$adstxt .= "\n";

		if ( $ownerDomain !== '' ) {
			$adstxt .= 'OWNERDOMAIN=' . $ownerDomain;
		}

		$adstxt .= "\n";
		$adstxt .= 'MANAGERDOMAIN=flashb.id';
		$adstxt .= "\n";

		/** @var string $adstxt */
		$adstxt .= $adstxtActirise;
		$adstxt .= '#--------------------------------------- End Actirise.com ---------------------------------------';

		if ( $custom ) {
			/** @var string $adstxtCustom */
			$adstxtCustom = get_option( 'actirise-adstxt-custom' );
			/** @var array<\stdClass> $adstxtCustomArray */
			$adstxtCustomArray = array();

			if ( $adstxtCustom ) {
				/** @var array<\stdClass> $adstxtCustomArray */
				$adstxtCustomArray = json_decode( $adstxtCustom );
			}

			if ( count( $adstxtCustomArray ) > 0 ) {
				$adstxt .= "\n";

				foreach ( $adstxtCustomArray as $adstxtItem ) {
					$cleaned_line = self::cleanDuplicateLine( $adstxt, $adstxtItem->value );

					if ( $cleaned_line !== '' ) {
						$adstxt .= "\n";
						$adstxt .= '## START ' . $adstxtItem->title . " ##\n";
						$adstxt .= $cleaned_line . "\n";
						$adstxt .= '## END ' . $adstxtItem->title . " ##\n";
					}
				}
			}
		}

		return $adstxt;
	}

	/**
	 * Get ads.txt from API
	 *
	 * @since    2.0.0
	 *
	 * @return bool|string
	 */
	public static function get_from_api() {
		$args = array(
			'domain' => rawurlencode( Helpers::get_server_details()['host'] ),
		);

		if ( get_option( 'actirise-settings-uuid-type', 'boot' ) === 'universal' ) {
			$args['universal'] = 'true';
			$args['product']   = '3';
		}

		/** @var string $uuid */
		$uuid     = get_option( 'actirise-settings-uuid' );
		$api_url  = 'adstxt_files/' . $uuid;
		$api      = new Api();
		$response = $api->get( 'api', $api_url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::AddLog( 'get_from_api error ' . $response->get_error_code(), 'public/include/adstxt', 'error' );
			return false;
		}

		if ( ! is_array( $response ) ) {
			Logger::AddLog( 'get_from_api response is not array ', 'public/include/adstxt', 'error' );
			return false;
		}

		if ( ! isset( $response['datas'] ) ) {
			Logger::AddLog( 'get_from_api response is empty ', 'public/include/adstxt', 'error' );
			return false;
		}

		$arrayResponse = array( $response['datas'] );

		if ( count( $arrayResponse ) !== 1 ) {
			Logger::AddLog( 'get_from_api responselength is up to 1 ', 'public/include/adstxt', 'error' );
			return false;
		}

		if ( empty( $arrayResponse[0] ) ) {
			Logger::AddLog( 'get_from_api responselength[0] is not exist ', 'public/include/adstxt', 'error' );
			return false;
		}

		return $response['datas'];
	}

	/**
	 * Update ads.txt file
	 *
	 * @since    2.4.0
	 * @return void
	 */
	public static function update_file() {
		Logger::AddLog( 'update_file start', 'public/include/adstxt', 'debug' );
		try {
			$wp_fs = Helpers::get_wp_fs();

			if ( is_wp_error( $wp_fs ) ) {
				Logger::AddLog( 'update_file wp_fs error', 'public/include/adstxt', 'error' );

				update_option( 'actirise-adstxt-file', false );

				return;
			}

			$path_ads_txt = ABSPATH . 'ads.txt';

			if ( $wp_fs->exists( $path_ads_txt ) && ! $wp_fs->is_writable( $path_ads_txt ) ) {
				Logger::AddLog( 'update_file not writable', 'public/include/adstxt', 'error' );

				update_option( 'actirise-adstxt-file', false );

				return;
			}

			$success = $wp_fs->put_contents( $path_ads_txt, self::get_adstxt( true ), FS_CHMOD_FILE );

			Logger::AddLog( 'update_file success: ' . $success, 'public/include/adstxt', 'debug' );

			update_option( 'actirise-adstxt-file', $success );
		} catch ( \Exception $e ) {
			Logger::AddLog( 'update_file catch: ', 'public/include/adstxt', 'error' );

			update_option( 'actirise-adstxt-file', false );
		}
	}

	/**
	 * Check if ads.txt need to be updated
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function check_adstxt_update() {
		if ( get_option( 'actirise-adstxt-lastupdate' ) === false ) {
			update_option( 'actirise-adstxt-lastupdate', time() - 3601 );
		}

		$lastUpdate = get_option( 'actirise-adstxt-lastupdate' );
		$now        = time();
		$diff       = $now - $lastUpdate;

		if ( $diff > 3600 ) {
			$cron = new Cron();
			$cron->update_adstxt();
		}
	}
}
