<?php
/**
 * Helpers class.
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
use WP_Filesystem_Base;

/**
 * Helpers class.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/includes
 * @author     actirise <support@actirise.com>
 */
class Helpers {
	/**
	 * Basic implementation of the wp_sanitize_script_attributes function introduced in the WordPress version 5.7.0.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string|bool> $attributes Key-value pairs representing `<script>` tag attributes.
	 * @return string String made of sanitized `<script>` tag attributes.
	 */
	public static function wp_sanitize_script_attributes( $attributes ) {
		$attributes_string = '';

		foreach ( $attributes as $attribute_name => $attribute_value ) {
			if ( is_bool( $attribute_value ) ) {
				if ( $attribute_value ) {
					$attributes_string .= ' ' . esc_attr( $attribute_name );
				}
			} else {
				$attributes_string .= sprintf( ' %1$s="%2$s"', esc_attr( $attribute_name ), esc_attr( $attribute_value ) );
			}
		}

		return $attributes_string;
	}

	/**
	 * A fallback for the wp_get_script_tag function introduced in the WordPress version 5.7.0.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string|bool> $attributes Key-value pairs representing `<script>` tag attributes.
	 * @return string String containing `<script>` opening and closing tags.
	 */
	public static function wp_get_script_tag( $attributes ) {
		return sprintf( "<script %s></script>\n", self::wp_sanitize_script_attributes( $attributes ) );
	}

	/**
	 * A fallback for the wp_print_script_tag function introduced in the WordPress version 5.7.0.
	 *
	 * @since 2.3.0
	 *
	 * @param array<string|bool> $attributes Key-value pairs representing `<script>` tag attributes.
	 * @return void
	 */
	public static function wp_print_script_tag( $attributes ) {
		echo self::wp_get_script_tag( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * A fallback for the wp_get_inline_script_tag function introduced in the WordPress version 5.7.0.
	 *
	 * @since 2.3.0
	 *
	 * @param string             $javascript Inline JavaScript code.
	 * @param array<string|bool> $attributes  Optional. Key-value pairs representing `<script>` tag attributes.
	 * @return string String containing inline JavaScript code wrapped around `<script>` tag.
	 */
	public static function wp_get_inline_script_tag( $javascript, $attributes = array() ) {
		$javascript = "\n" . trim( $javascript, "\n\r " ) . "\n";
		return sprintf( "<script%s>%s</script>\n", self::wp_sanitize_script_attributes( $attributes ), $javascript );
	}

	/**
	 * A fallback for the wp_get_inline_script_tag function introduced in the WordPress version 5.7.0.
	 *
	 * @since 2.3.0
	 *
	 * @param string             $javascript Inline JavaScript code.
	 * @param array<string|bool> $attributes Optional. Key-value pairs representing `<script>` tag attributes.
	 * @return void
	 */
	public static function wp_print_inline_script_tag( $javascript, $attributes = array() ) {
		echo self::wp_get_inline_script_tag( $javascript, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Create a `<style>` tag with the provided inline CSS code.
	 *
	 * @since 2.3.0
	 *
	 * @param string             $style Inline JavaScript code.
	 * @param array<string|bool> $attributes  Optional. Key-value pairs representing `<style>` tag attributes.
	 * @return string String containing inline JavaScript code wrapped around `<style>` tag.
	 */
	public static function wp_get_inline_style_tag( $style, $attributes = array() ) {
		$style = "\n" . trim( $style, "\n\r " ) . "\n";
		return sprintf( "<style%s>%s</style>\n", self::wp_sanitize_script_attributes( $attributes ), $style );
	}

	/**
	 * Create a `<style>` tag with the provided inline CSS code.
	 *
	 * @since 2.3.0
	 *
	 * @param string             $style Inline JavaScript code.
	 * @param array<string|bool> $attributes Optional. Key-value pairs representing `<style>` tag attributes.
	 * @return void
	 */
	public static function wp_print_inline_style_tag( $style, $attributes = array() ) {
		if ( ! isset( $attributes['type'] ) ) {
			$attributes['type'] = 'text/css';
		}

		echo self::wp_get_inline_style_tag( $style, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get page type.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function get_page_type() {
		$page_type = 'notfound';

		if ( is_home() || is_front_page() ) {
			$page_type = 'home';
		} elseif ( is_page() ) {
			$page_type = 'page';
		} elseif ( is_single() ) {
			$page_type = 'article';
		} elseif ( is_category() ) {
			$page_type = 'category';
		} elseif ( is_tag() ) {
			$page_type = 'tag';
		} elseif ( is_tax() ) {
			$page_type = 'tax';
		} elseif ( is_archive() ) {
			$page_type = 'archive';
		} elseif ( is_search() ) {
			$page_type = 'search';
		} elseif ( is_404() ) {
			$page_type = 'notfound';
		}

		return $page_type;
	}

	/**
	 * Get page url.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public static function get_url() {
		return esc_url_raw( self::get_server_details()['host'] . self::get_server_details()['uri'] );
	}

	/**
	 * Get all options for FastCmp
	 *
	 * @since 2.2.0
	 * @param bool $light If true, return only the light options.
	 * @return array{name: string, enabled: boolean, headOffice: string, logo: string, privacyPolicy: string, targetedAudience: string, excludedIabVendors: array<int>, excludedGoogleVendors: array<int>, stubLight: string, vendors: array<mixed>, uuid: string, customsVendor: array{name: string, id: string}, lastUpdate: int, acceptButtonStyle: array{bg: string, font: string}, declineButtonStyle: array{bg: string, font: string, border: string}, parametersButtonStyle: array{bg: string, font: string, border: string}, customStyle: string}
	 */
	public static function get_fastcmp_options( $light ) {
		$defaultCountryCode = substr( get_bloginfo( 'language', 'raw' ), 3 );

		/** @var string $image */
		$image = get_option( 'actirise-fastcmp-logo', '' );

		if ( $image === '' ) {
				/** @var int|boolean $logoId */
			$logoId = get_theme_mod( 'custom_logo' );

			if ( $logoId ) {
				/** @var int $logoId */
				$imageAttachment = wp_get_attachment_image_src( $logoId, 'full' );

				if ( $imageAttachment !== false && count( $imageAttachment ) > 0 ) {
					/** @var string $image */
					$image = $imageAttachment[0];
				}
			}
		}

		$privacyPolicyOption = get_option( 'actirise-fastcmp-privacypolicy', '' );
		/** @var string $privacyPolicy */
		$privacyPolicy = $privacyPolicyOption === '' ? get_privacy_policy_url() : $privacyPolicyOption;
		/** @var string $name */
		$name = get_option( 'actirise-fastcmp-name', get_bloginfo( 'name', 'raw' ) );
		/** @var boolean $enabled */
		$enabled = get_option( 'actirise-fastcmp-enabled', 'false' ) === '1';
		/** @var string $headOffice */
		$headOffice = get_option( 'actirise-fastcmp-headoffice', $defaultCountryCode );
		/** @var array<int> $excludedIabVendors */
		$excludedIabVendors = get_option( 'actirise-fastcmp-excludediabvendors', array() );
		/** @var array<int> $excludedGoogleVendors */
		$excludedGoogleVendors = get_option( 'actirise-fastcmp-excludedgooglevendors', array() );
		/** @var string $stubLight */
		$stubLight = get_option( 'actirise-fastcmp-stub-light', '' );
		/** @var string $targetedAudience */
		$targetedAudience = get_option( 'actirise-fastcmp-targetedaudience', 'tcfeuv2' );
		/** @var array<mixed> $vendors */
		$vendors = $light ? array() : get_option( 'actirise-fastcmp-vendors', array() );
		/** @var string $uuid */
		$uuid = get_option( 'actirise-fastcmp-uuid', '' );
		/** @var array{name: string, id: string} $customsVendor */
		$customsVendor = get_option( 'actirise-fastcmp-customsvendor', array() );
		/** @var int $lastUpdate */
		$lastUpdate = get_option( 'actirise-fastcmp-lastupdate', 0 );
		/** @var array{bg: string, font: string} $acceptButtonStyle */
		$acceptButtonStyle = get_option(
			'actirise-fastcmp-acceptButtonStyle',
			array(
				'bg'   => '#0071f2',
				'font' => '#ffffff',
			)
		);
		/** @var string $acceptButtonStyleString */
		$acceptButtonStyleString = wp_json_encode( $acceptButtonStyle );
		/** @var array{bg: string, font: string} $acceptButtonStyle */
		$acceptButtonStyle = json_decode( $acceptButtonStyleString, true );

		/** @var array{bg: string, font: string, border: string} $declineButtonStyle */
		$declineButtonStyle = get_option(
			'actirise-fastcmp-declineButtonStyle',
			array(
				'bg'     => 'transparent',
				'font'   => '#0071f2',
				'border' => '#0071f2',
			)
		);
		/** @var string $declineButtonStyleString */
		$declineButtonStyleString = wp_json_encode( $declineButtonStyle );
		/** @var array{bg: string, font: string, border: string} $declineButtonStyle */
		$declineButtonStyle = json_decode( $declineButtonStyleString, true );

		/** @var array{bg: string, font: string, border: string} $parametersButtonStyle */
		$parametersButtonStyle = get_option(
			'actirise-fastcmp-parametersButtonStyle',
			array(
				'bg'     => 'transparent',
				'font'   => '#0071f2',
				'border' => '#0071f2',
			)
		);
		/** @var string $parametersButtonStyleString */
		$parametersButtonStyleString = wp_json_encode( $parametersButtonStyle );
		/** @var array{bg: string, font: string, border: string} $parametersButtonStyle */
		$parametersButtonStyle = json_decode( $parametersButtonStyleString, true );

		/** @var string $customStyle */
		$customStyle = get_option( 'actirise-fastcmp-customStyle', '' );

		return array(
			'name'                  => $name,
			'enabled'               => $enabled,
			'headOffice'            => $headOffice,
			'logo'                  => $image,
			'privacyPolicy'         => $privacyPolicy,
			'targetedAudience'      => $targetedAudience,
			'excludedIabVendors'    => $excludedIabVendors,
			'excludedGoogleVendors' => $excludedGoogleVendors,
			'customsVendor'         => $customsVendor,
			'stubLight'             => $stubLight,
			'vendors'               => $vendors,
			'uuid'                  => $uuid,
			'lastUpdate'            => $lastUpdate,
			'acceptButtonStyle'     => $acceptButtonStyle,
			'declineButtonStyle'    => $declineButtonStyle,
			'parametersButtonStyle' => $parametersButtonStyle,
			'customStyle'           => $customStyle,
		);
	}

	/**
	 * Get server details.
	 *
	 * @since 2.3.0
	 * @return array<string>
	 */
	public static function get_server_details() {
		$data = array();

		$data['host']   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$data['scheme'] = isset( $_SERVER['REQUEST_SCHEME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_SCHEME'] ) ) : '';
		$data['uri']    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( $data['host'] !== '' ) {
			/** @var string $host */
			$host         = preg_replace( '/^www\.(.*)$/', '$1', strtolower( $data['host'] ) );
			$data['host'] = $host;
		}

		return $data;
	}

	/**
	 * Check if plugin auto update is active.
	 *
	 * @since    2.4.0
	 * @return bool
	 */
	public static function has_auto_update() {
		/** @var string $plugin_file */
		$plugin_file = plugin_basename( ACTIRISE_FILE );
		/** @var array<string> $auto_updates */
		$auto_updates = get_site_option( 'auto_update_plugins', array() );
		/** @var bool $auto_updates_enabled */
		$auto_updates_enabled = in_array( $plugin_file, $auto_updates, true );

		return $auto_updates_enabled;
	}

	/**
	 * Get all custom value.
	 *
	 * @since    2.0.0
	 * @param    string $typeCustom The type of custom.
	 * @return   string
	 */
	public static function get_custom_value( $typeCustom ) {
		if ( $typeCustom === '' || ( is_home() || is_front_page() ) ) {
			return '';
		}

		$customExploded = explode( '_', $typeCustom, 2 );
		$typeCustomKey  = $customExploded[0];
		$levelCustom    = '0';
		$key            = $customExploded[1];
		$value          = '';

		if ( $typeCustomKey === 'category' || $typeCustomKey === 'tag' ) {
			$customExploded = explode( '_', $typeCustom, 3 );
			$levelCustom    = $customExploded[1];
			$key            = $customExploded[2];
		}

		if ( ! get_the_ID() ) {
			return '';
		}

		$postValue = get_post( get_the_ID() );

		if ( ! $postValue ) {
			return '';
		}

		switch ( $typeCustomKey ) {
			case 'post':
				if ( is_single() || is_page() ) {
					if ( $postValue->$key ) {
						$value = $postValue->$key;
					}
				}
				break;
			case 'category':
				$category = get_the_category();

				if ( $category && isset( $category[ $levelCustom ] ) ) {
					$value = $category[ $levelCustom ]->$key;
				}
				break;
			case 'tag':
				if ( get_the_ID() !== false ) {
					$tags = get_the_tags( get_the_ID() );

					if ( $tags && ! is_wp_error( $tags ) && isset( $tags[ $levelCustom ] ) ) {
						$value = $tags[ $levelCustom ]->$key;
					}
				}

				break;
			case 'author':
				if ( is_single() || is_page() ) {
					/** @var int $authorID */
					$authorID = $postValue->post_author;
					/** @var string $value */
					$value = get_the_author_meta( 'display_name', $authorID );

					if ( $value === '' ) {
						/** @var string $value */
						$value = get_the_author_meta( $key, $authorID );
					}
				}
				break;
			case 'customFields':
				if ( get_the_ID() !== false ) {
					/** @var string $value */
					$value = get_post_meta( get_the_ID(), $key, true );
				}

				break;
			default:
				$value = '';
				break;
		}

		return $value;
	}

	/**
	 * Get WordPress filesystem.
	 *
	 * @since 2.5.0
	 * @return WP_Filesystem_Base|WP_Error
	 */
	public static function get_wp_fs() {
		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			$initialized = self::init_wp_fs();

			if ( false === $initialized ) {
				return new WP_Error( 'WP_FS_HELPER', 'The WordPress filesystem could not be initialized.' );
			}
		}

		return $wp_filesystem;
	}

	/**
	 * Init WP_Filesystem.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	protected static function init_wp_fs() {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$method      = get_filesystem_method();
		$initialized = false;

		if ( 'direct' === $method ) {
			$initialized = WP_Filesystem();
		} elseif ( false !== $method ) {
			// See https://core.trac.wordpress.org/changeset/56341.
			ob_start();
			$credentials = request_filesystem_credentials( '' );
			ob_end_clean();

			$initialized = $credentials && WP_Filesystem( $credentials );
		}

		return is_null( $initialized ) ? false : $initialized;
	}
}
