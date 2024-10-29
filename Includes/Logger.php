<?php
/**
 * Logger class.
 *
 * @link       https://actirise.com
 * @since      2.5.0
 *
 * @package    actirise
 * @subpackage actirise/includes
 */

namespace Actirise\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Logger class.
 *
 * @since      2.5.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
class Logger {
	/**
	 * Add log.
	 *
	 * @since 2.5.0
	 * @param string $message
	 * @param string $file
	 * @param string $type
	 * @return void
	 */
	public static function AddLog( $message, $file, $type ) {
		self::AddLogToDB( $message, $file, $type );
	}

	/**
	 * Get logs.
	 *
	 * @since 2.5.0
	 * @return array<array<'datelog'|'file'|'message'|'type', string>>
	 */
	public static function GetLogs() {
		/** @var array<array<'datelog'|'file'|'message'|'type', string>> $logs */
		$logs = get_option( 'actirise-logs', array() );

		if ( empty( $logs ) ) {
			return array();
		}

		if ( ! is_array( $logs ) ) {
			return array();
		}

		$logs = self::ClearOldLogs( $logs );

		$cleanLogs = array();

		foreach ( $logs as $log ) {
			$cleanLogs[] = array(
				'datelog' => $log['datelog'],
				'file'    => $log['file'],
				'message' => $log['message'],
				'type'    => $log['type'],
			);
		}

		return $cleanLogs;
	}

	/**
	 * Clear old logs.
	 *
	 * @since 2.5.0
	 * @param array<array<'datelog'|'file'|'message'|'type', string>> $logs
	 * @return array<array<'datelog'|'file'|'message'|'type', string>>
	 */
	public static function ClearOldLogs( $logs ) {
		$logs = array_filter(
			$logs,
			/**
			 * @param array<'datelog'|'file'|'message'|'type', string> $log
			 * @return bool
			 */
			function ( $log ) {
				$now     = new \DateTime();
				$logDate = new \DateTime( $log['datelog'] );

				$diff = $now->diff( $logDate );

				if ( $diff->days > 7 ) {
					return false;
				}

				return true;
			}
		);

		// a maximum of 100 lines must remain
		if ( count( $logs ) > 100 ) {
			array_splice( $logs, 0, count( $logs ) - 100 );
		}

		return $logs;
	}

	/**
	 * Add log to database.
	 *
	 * @since 2.5.0
	 * @param string $message
	 * @param string $file
	 * @param string $type
	 * @return void
	 */
	private static function AddLogToDB( $message, $file, $type ) {
		/** @var array<array<'datelog'|'file'|'message'|'type', string>> $currentLog */
		$currentLog = get_option( 'actirise-logs', array() );

		$currentLog = self::ClearOldLogs( $currentLog );

		array_push(
			$currentLog,
			array(
				'message' => $message,
				'file'    => $file,
				'type'    => $type,
				'datelog' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		update_option( 'actirise-logs', $currentLog );
	}
}
