<?php
/**
 * This class allows the management of cron jobs for the proper functioning of our plugin.
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

use ActirisePublic\Includes\AdsTxt;
use ActirisePublic\Includes\PresizedDiv;
use ActirisePublic\Includes\Debug;
use Actirise\Includes\Api;
use Actirise\Includes\Helpers;
use Actirise\Includes\Logger;

/**
 * This class allows the management of cron jobs for the proper functioning of our plugin.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 *
 * @phpstan-import-type PresizedDivSlot from PresizedDiv
 */
final class Cron {
	/**
	 * Schedule cron jobs
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function schedule() {
		if ( ACTIRISE_CRON === 'true' ) {
			if ( ! wp_next_scheduled( 'actirise_cron_update_adstxt' ) ) {
				wp_schedule_event( time(), 'hourly', 'actirise_cron_update_adstxt' );
				$this->update_adstxt();
			}

			if ( ! wp_next_scheduled( 'actirise_cron_update_presized_div' ) ) {
				wp_schedule_event( time(), 'hourly', 'actirise_cron_update_presized_div' );
				$this->check_presized_div();
			}

			if ( ! wp_next_scheduled( 'actirise_cron_update_fast_cmp' ) ) {
				wp_schedule_event( time(), 'hourly', 'actirise_cron_update_fast_cmp' );
				$this->get_fast_cmp();
			}
		}
	}

	/**
	 * Update ads.txt file
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function update_adstxt() {
		if ( get_option( 'actirise-settings-uuid' ) ) {
			$adsTxt = AdsTxt::get_from_api();

			if ( $adsTxt !== false && get_option( 'actirise-adstxt-actirise' ) !== $adsTxt ) {
				update_option( 'actirise-adstxt-actirise', $adsTxt );
				update_option( 'actirise-adstxt-update', 'true' );

				if ( get_option( 'actirise-adstxt-active', 'false' ) === 'true' ) {
					AdsTxt::update_file();
				}
			}

			if ( $adsTxt === false ) {
				Logger::AddLog( 'adstxt get_from_api is false', 'include/cron', 'error' );
			}

			if ( get_option( 'actirise-adstxt-actirise' ) === $adsTxt ) {
				Logger::AddLog( 'adstxt are identical', 'include/cron', 'error' );
			}

			update_option( 'actirise-adstxt-lastupdate', time() );
		}
	}

	/**
	 * Check for presized div
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function check_presized_div() {
		if ( get_option( 'actirise-settings-uuid' ) ) {
			/** @var array<PresizedDivSlot> | bool $presizedDiv */
			$presizedDiv = PresizedDiv::get_from_api();

			if ( $presizedDiv !== false ) {
				/** @var array<array{slotName: string, active: bool}> $presizedDivActive */
				$presizedDivActive = get_option( 'actirise-presizeddiv-selected', array() );
				/** @var array<\stdclass> $presizedDivNotif */
				$presizedDivNotif = get_option( 'actirise-presizeddiv-notif', array() );

				if ( ! is_array( $presizedDivActive ) ) {
					$presizedDivActive = array();
				}

				/** @var array<PresizedDivSlot> $presizedDiv */
				foreach ( $presizedDiv as $presizedDivItem ) {
					/** @var string $slotName */
					$slotName = $presizedDivItem['slotName'];
					if ( ! in_array( $slotName, array_column( $presizedDivActive, 'slotName' ), true ) ) {
						$presizedDivActive[] = array(
							'slotName' => $slotName,
							'active'   => false,
						);

						$presizedDivNotif[] = $slotName;
					}
				}

				/** @var array{slotName: string, active: bool} $presizedDivActiveItem */
				foreach ( $presizedDivActive as $key => $presizedDivActiveItem ) {
					if ( ! in_array( $presizedDivActiveItem['slotName'], array_column( $presizedDiv, 'slotName' ), true ) ) {
						unset( $presizedDivActive[ $key ] );
					}
				}

				update_option( 'actirise-presizeddiv-selected', $presizedDivActive );
				update_option( 'actirise-presizeddiv-actirise', $presizedDiv );
				update_option( 'actirise-presizeddiv-notif', $presizedDivNotif );
			} else {
				update_option( 'actirise-presizeddiv-actirise', array() );
			}

			update_option( 'actirise-presizeddiv-lastupdate', time() );
		}
	}

	/**
	 * Get FastCmp stub light
	 *
	 * @since    2.3.0
	 * @return void
	 */
	public function get_fast_cmp() {
		$this->get_fastcmp_stublight();
		$this->get_fastcmp_vendors();

		if ( get_option( 'actirise-fastcmp-uuid', '' ) === '' ) {
			$this->get_fastcmp_uuid();
		}
	}

	/**
	 * Set auto update
	 *
	 * @since    2.4.0
	 * @param bool $enabled
	 * @return void
	 */
	public function set_auto_update( $enabled ) {
		if ( ACTIRISE_CRON !== 'true' ) {
			return;
		}

		if ( $enabled === Helpers::has_auto_update() ) {
			return;
		}

		/** @var string $plugin_file */
		$plugin_file = plugin_basename( ACTIRISE_FILE );
		/** @var array<string> $auto_updates */
		$auto_updates = get_site_option( 'auto_update_plugins', array() );

		if ( $enabled ) {
			$plugins = array_unique( array_merge( $auto_updates, array( plugin_basename( ACTIRISE_FILE ) ) ) );
		} else {
			$plugins = array_values( array_diff( $auto_updates, array( plugin_basename( ACTIRISE_FILE ) ) ) );
		}

		update_site_option( 'auto_update_plugins', $plugins );
	}

	/**
	 * Get FastCmp stub light
	 *
	 * @since    2.2.0
	 * @return void
	 */
	private function get_fastcmp_stublight() {
		$stubLightUrl = 'https://static.fastcmp.com/fast-cmp-stub-light.js';

		$response = wp_remote_request(
			$stubLightUrl,
			array(
				'method'    => 'GET',
				'timeout'   => 10,
  			'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::AddLog( 'get_fastcmp_stublight error ' . $response->get_error_code(), 'include/cron', 'error' );
			return;
		}

		/** @var string|boolean $stubLight */
		$stubLight = wp_remote_retrieve_body( $response );

		if ( $stubLight === false ) {
			return;
		}

		update_option( 'actirise-fastcmp-stub-light', $stubLight );
	}

	/**
	 * Get FastCmp vendors
	 *
	 * @since    2.2.0
	 * @return void
	 */
	private function get_fastcmp_vendors() {
		$vendorsApiUrl = 'https://eu.fastcmp.com/wp/vendor-list';

		$response = wp_remote_request(
			$vendorsApiUrl,
			array(
				'method'    => 'GET',
				'timeout'   => 10,
  			'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		/** @var string|boolean $vendors */
		$vendors = wp_remote_retrieve_body( $response );

		if ( $vendors === false ) {
			return;
		}

		/** @var string $vendors */
		$vendorsJson = json_decode( $vendors, true );

		if ( $vendorsJson === null ) {
			return;
		}

		update_option( 'actirise-fastcmp-vendors', $vendorsJson );
	}

	/**
	 * Get FastCmp uuid
	 *
	 * @since    2.2.0
	 * @return void
	 */
	private function get_fastcmp_uuid() {
		$fastCMPUrl = 'https://eu.fastcmp.com/wp/domain-uid?domain=';

		$domain = rawurlencode( Helpers::get_server_details()['host'] );
		$domain = str_replace( 'www.', '', $domain );

		$response = wp_remote_request(
			$fastCMPUrl . $domain,
			array(
				'method'    => 'GET',
				'timeout'   => 10,
  			'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		/** @var string|boolean $uuid */
		$uuid = wp_remote_retrieve_body( $response );

		if ( $uuid === false ) {
			return;
		}

		/** @var string $uuid */
		$uuidJson = json_decode( $uuid, true );

		/** @var null|array{domain?: string, domainUid?: string, error?: string} $uuidJson */
		if ( $uuidJson === null ) {
			return;
		}

		/** @var array{domain?: string, domainUid?: string, error?: string} $uuidJson */
		if ( isset( $uuidJson['error'] ) || ! isset( $uuidJson['domainUid'] ) ) {
			return;
		}

		update_option( 'actirise-fastcmp-uuid', $uuidJson['domainUid'] );
	}
}
