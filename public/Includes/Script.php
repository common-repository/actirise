<?php
/**
 * Gestion of head script intégration
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

use Actirise\Includes\Helpers;
use Actirise\Includes\Cron;

/**
 * The public-specific functionality of the plugin.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/public/includes
 * @author     actirise <support@actirise.com>
 */
final class Script {
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */

	protected $plugin_name;
	/**
	 * The current version of the plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	private $version;

	/**
	 * No pub Class
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      NoPub    $no_pub    The no pub class.
	 */
	private $no_pub;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    2.0.0
	 * @param    string $plugin_name The name of this plugin.
	 * @param    string $version     The version of this plugin.
	 * @param    NoPub  $no_pub      The no pub class.
	 */
	public function __construct( $plugin_name, $version, $no_pub ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->no_pub      = $no_pub;

		if ( ACTIRISE_CRON !== 'true' ) {
			$this->update_fastcmp();
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function head_integration() {
		/** @var string $uuid  */
		$uuid     = $this->get_actirise_option( 'settings-uuid', '' );
		$type_tag = $this->get_actirise_option( 'settings-uuid-type', 'boot' );
		$custom1  = Helpers::get_custom_value( $this->get_actirise_option( 'custom1' ) );
		$custom2  = Helpers::get_custom_value( $this->get_actirise_option( 'custom2' ) );
		$custom3  = Helpers::get_custom_value( $this->get_actirise_option( 'custom3' ) );
		$custom4  = Helpers::get_custom_value( $this->get_actirise_option( 'custom4' ) );
		$custom5  = Helpers::get_custom_value( $this->get_actirise_option( 'custom5' ) );

		if ( $this->get_actirise_option( 'fastcmp-enabled', 'false' ) === '1' ) {
			$fastcmp_options = Helpers::get_fastcmp_options( true );

			if ( $fastcmp_options['privacyPolicy'] !== '' && $fastcmp_options['uuid'] !== '' ) {
				/** @var string $exclude_iab_vendors */
				$exclude_iab_vendors = wp_json_encode( $fastcmp_options['excludedIabVendors'] );
				/** @var string $exclude_google_vendors */
				$exclude_google_vendors = wp_json_encode( $fastcmp_options['excludedGoogleVendors'] );

				$custom_vendor        = '';
				$custom_vendor_string = '';

				if ( count( $fastcmp_options['customsVendor'] ) > 0 ) {
					$custom_vendor_string = '[';

					/** @var object{name: string, id: string} $customsVendor */
					foreach ( $fastcmp_options['customsVendor'] as $customsVendor ) {
						$custom_vendor_string .= '{name: "' . esc_attr( $customsVendor->name ) . '", id: "' . esc_attr( $customsVendor->id ) . '"},';
					}
					$custom_vendor_string  = substr( $custom_vendor_string, 0, -1 );
					$custom_vendor_string .= ']';

					$custom_vendor .= 'custom: {vendors: ' . $custom_vendor_string . '},';
				}

				$fastcmp_config = sprintf(
					"window.FAST_CMP_OPTIONS = { 
					domainUid: '%s',
					countryCode: '%s',
					policyUrl: '%s',
					displaySynchronous: false,
					publisherName: '%s',
					bootstrap: { 
						excludedIABVendors: %s, 
						excludedGoogleVendors: %s,
					},
					jurisdiction: '%s',
					%s
					%s
				};",
					esc_js( $fastcmp_options['uuid'] ),
					esc_js( $fastcmp_options['headOffice'] ),
					esc_js( $fastcmp_options['privacyPolicy'] ),
					esc_js( $fastcmp_options['name'] ),
					esc_js( $exclude_iab_vendors ),
					esc_js( $exclude_google_vendors ),
					esc_js( $fastcmp_options['targetedAudience'] ),
					$fastcmp_options['logo'] !== '' ? "publisherLogo: function (c) { return c.createElement('img', { src: '" . $fastcmp_options['logo'] . "', height: '40' }) }," : '',
					$custom_vendor
				);

				$stublight = $fastcmp_options['stubLight'];

				$fast_cmp_options_script = array(
					'data-no-optimize'         => '1',
					'data-wpmeteor-nooptimize' => 'true',
					'data-cfasync'             => 'false',
					'nowprocket'               => '',
				);

				if ( defined( 'WP_ROCKET_VERSION' ) === false ) {
					$fast_cmp_options_script['charset'] = 'UTF-8';
				}

				Helpers::wp_print_inline_script_tag(
					$fastcmp_config . "\n" . $stublight,
					$fast_cmp_options_script
				);

				$fast_cmp_stub = array(
					'async'                    => true,
					'data-no-optimize'         => '1',
					'data-wpmeteor-nooptimize' => 'true',
					'data-cfasync'             => 'false',
					'nowprocket'               => '',
					'src'                      => 'https://static.fastcmp.com/fast-cmp-stub.js',
					'charset'                  => 'UTF-8',
				);

				Helpers::wp_print_script_tag( $fast_cmp_stub );

				Helpers::wp_print_inline_style_tag(
					$this->fastcmp_style( $fastcmp_options ),
					array(
						'id' => 'fast-cmp-custom-styles',
					)
				);
			}
		}

		if ( $uuid === '' ) {
			return;
		}

		$presized_div_selected = $this->get_presized_slot_active();

		$no_pub = $this->no_pub->check_no_pub() ? 'no_pub: true,' : '';

		$flashbidjs = array(
			'data-cfasync' => 'false',
			'defer'        => '',
			'src'          => 'https://www.flashb.id/' . esc_js( $type_tag ) . '/' . esc_js( $uuid ) . '.js',
		);

		Helpers::wp_print_script_tag( $flashbidjs );

		$data_var = sprintf(
			"window._hbdbrk=window._hbdbrk||[];window._hbdbrk.push(['_vars', {page_type:'%s',pid:3,custom1:'%s',custom2:'%s',custom3:'%s',custom4:'%s',custom5:'%s',%s}]);",
			esc_attr( Helpers::get_page_type() ),
			esc_attr( $custom1 ),
			esc_attr( $custom2 ),
			esc_attr( $custom3 ),
			esc_attr( $custom4 ),
			esc_attr( $custom5 ),
			esc_attr( $no_pub )
		);

		$actirise_plugin = sprintf(
			"window.actirisePlugin=window.actirisePlugin||{};window.actirisePlugin.version='%s';window.actirisePlugin.adsTxtActive=%s;window.actirisePlugin.adsTxtLastUpdate='%s';window.actirisePlugin.presizedActive=%s;window.actirisePlugin.presizedSelected='%s';window.actirisePlugin.presizedEnabled='%s';window.actirisePlugin.presizedLastUpdate='%s';window.actirisePlugin.cron=%s;window.actirisePlugin.noPub='%s';window.actirisePlugin.fastcmp=%s;window.actirisePlugin.autoUpdate=%s;",
			esc_attr( $this->version ),
			esc_attr( $this->get_actirise_option( 'adstxt-active', 'false' ) ),
			esc_attr( $this->get_actirise_option( 'adstxt-lastupdate', 'false' ) ),
			esc_attr( $this->get_actirise_option( 'presizeddiv-active', 'false' ) ),
			wp_json_encode( $presized_div_selected ),
			wp_json_encode( $this->get_presized_slot_name_enabled() ),
			esc_attr( $this->get_actirise_option( 'presizeddiv-lastupdate', 'false' ) ),
			esc_attr( ( defined( 'ACTIRISE_CRON' ) && ACTIRISE_CRON === 'true' ) === true ? 'true' : 'false' ),
			wp_json_encode( array_values( $this->no_pub->get_all_no_pub_url() ) ),
			esc_attr( $this->get_actirise_option( 'fastcmp-enabled', 'false' ) === '1' ? 'true' : 'false' ),
			esc_attr( Helpers::has_auto_update() ? 'true' : 'false' )
		);

		Helpers::wp_print_inline_script_tag( $data_var . "\n" . $actirise_plugin );
	}

	/**
	 * Get fastcmp style.
	 *
	 * @since 2.4.0
	 * @param array{acceptButtonStyle: array{bg: string, font: string}, declineButtonStyle: array{bg: string, border: string, font: string}, parametersButtonStyle: array{bg: string, border: string, font: string}, customStyle: string} $fastcmp_options The fastcmp options.
	 * @return string
	 */
	public function fastcmp_style( $fastcmp_options ) {
		$custom_css = '';

		if ( $fastcmp_options['acceptButtonStyle']['bg'] !== '' || $fastcmp_options['acceptButtonStyle']['font'] !== '' ) {
			$custom_css .= '#fast-cmp-container button.fast-cmp-button-primary{';
			if ( $fastcmp_options['acceptButtonStyle']['bg'] !== '' ) {
				$custom_css .= 'background-color:' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			}
			if ( $fastcmp_options['acceptButtonStyle']['font'] !== '' ) {
				$custom_css .= 'color:' . esc_attr( $fastcmp_options['acceptButtonStyle']['font'] ) . '!important;';
			}
			$custom_css .= '}';
		}

		if ( $fastcmp_options['acceptButtonStyle']['bg'] !== '' ) {
			$custom_css .= '#fast-cmp-container #fast-cmp-home button.fast-cmp-button-secondary{';
			if ( $fastcmp_options['declineButtonStyle']['bg'] === 'transparent' ) {
				$custom_css .= 'box-shadow: inset 0 0 0 1px ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			} else {
				$custom_css .= 'box-shadow: none!important;';
			}
			$custom_css .= '}';
			$custom_css .= '#fast-cmp-container #fast-cmp-home button.fast-cmp-navigation-button{';
			if ( $fastcmp_options['parametersButtonStyle']['bg'] === 'transparent' ) {
				$custom_css .= 'box-shadow: inset 0 0 0 1px ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			} else {
				$custom_css .= 'box-shadow: none!important;';
			}
			$custom_css .= '}';
		}

		if ( $fastcmp_options['declineButtonStyle']['bg'] !== '' || $fastcmp_options['declineButtonStyle']['font'] !== '' ) {
			$custom_css .= '#fast-cmp-container #fast-cmp-home button.fast-cmp-button-secondary{';
			if ( $fastcmp_options['declineButtonStyle']['bg'] !== '' && $fastcmp_options['declineButtonStyle']['bg'] !== 'transparent' ) {
				$custom_css .= 'background-color:' . esc_attr( $fastcmp_options['declineButtonStyle']['bg'] ) . '!important;';
			}
			if ( $fastcmp_options['declineButtonStyle']['font'] !== '' ) {
				$custom_css .= 'color:' . esc_attr( $fastcmp_options['declineButtonStyle']['font'] ) . '!important;';
			}
			$custom_css .= '}';
		}

		if ( $fastcmp_options['declineButtonStyle']['border'] !== '' ) {
			$custom_css     .= '#fast-cmp-container #fast-cmp-home button.fast-cmp-button-secondary:hover{';
				$custom_css .= 'box-shadow: inset 0 0 0 1px ' . esc_attr( $fastcmp_options['declineButtonStyle']['border'] ) . '!important;';
			if ( $fastcmp_options['declineButtonStyle']['font'] !== '' ) {
				$custom_css .= 'color:' . esc_attr( $fastcmp_options['declineButtonStyle']['font'] ) . '!important;';
			}
			$custom_css .= '}';
		}

		if ( $fastcmp_options['parametersButtonStyle']['bg'] !== '' || $fastcmp_options['parametersButtonStyle']['font'] !== '' ) {
			$custom_css .= '#fast-cmp-container #fast-cmp-home button.fast-cmp-navigation-button{';
			if ( $fastcmp_options['parametersButtonStyle']['bg'] !== '' && $fastcmp_options['parametersButtonStyle']['bg'] !== 'transparent' ) {
				$custom_css .= 'background-color:' . esc_attr( $fastcmp_options['parametersButtonStyle']['bg'] ) . '!important;';
			}
			if ( $fastcmp_options['parametersButtonStyle']['font'] !== '' ) {
				$custom_css .= 'color:' . esc_attr( $fastcmp_options['parametersButtonStyle']['font'] ) . '!important;';
			}
			$custom_css .= '}';
		}

		if ( $fastcmp_options['parametersButtonStyle']['border'] !== '' ) {
			$custom_css .= '#fast-cmp-container #fast-cmp-home button.fast-cmp-navigation-button:hover{';
			$custom_css .= 'box-shadow: inset 0 0 0 1px ' . esc_attr( $fastcmp_options['parametersButtonStyle']['border'] ) . '!important;';
			if ( $fastcmp_options['parametersButtonStyle']['font'] !== '' ) {
				$custom_css .= 'color:' . esc_attr( $fastcmp_options['parametersButtonStyle']['font'] ) . '!important;';
			}
			$custom_css .= '}';
		}

		if ( $fastcmp_options['acceptButtonStyle']['bg'] !== '' ) {
			$custom_css .= '#fast-cmp-container a {';
			$custom_css .= 'color: ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			$custom_css .= '}';
			$custom_css .= '#fast-cmp-container .fast-cmp-layout-header .fast-cmp-navigation-button {';
			$custom_css .= 'background-color: ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			$custom_css .= 'color: white!important;';
			$custom_css .= '}';
			$custom_css .= '#fast-cmp-container #fast-cmp-consents .fast-cmp-layout-nav button.fast-cmp-navigation-button {';
			$custom_css .= 'color: ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			$custom_css .= 'box-shadow: inset 0 0 0 1px ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			$custom_css .= '}';
			$custom_css .= '#fast-cmp-form .fast-cmp-spinner {';
			$custom_css .= 'border-left-color: ' . esc_attr( $fastcmp_options['acceptButtonStyle']['bg'] ) . '!important;';
			$custom_css .= '}';
		}

		$fastcmp_options['customStyle'] = preg_replace( '/\s\s+/', '', $fastcmp_options['customStyle'] );

		if ( ! is_null( $fastcmp_options['customStyle'] ) && $fastcmp_options['customStyle'] !== '' ) {
			$custom_css .= trim( $fastcmp_options['customStyle'] );
		}

		return $custom_css;
	}

	/**
	 * Get actirise options.
	 *
	 * @since 2.0.0
	 * @param string $option_name The option name.
	 * @param string $defaultOption The default value.
	 *
	 * @return string
	 */
	private function get_actirise_option( $option_name, $defaultOption = '' ) {
		/** @var string $option */
		$option = get_option( $this->plugin_name . '-' . $option_name, $defaultOption );

		return $option;
	}

	/**
	 * Get presized slot active.
	 *
	 * @since 2.0.0
	 * @return array<mixed>
	 */
	private function get_presized_slot_active() {
		$presizedDiv = get_option( $this->plugin_name . '-presizeddiv-selected', array() );

		if ( ! is_array( $presizedDiv ) ) {
			$presizedDiv = array();
		}

		return $presizedDiv;
	}

	/**
	 * Get presized slot name enabled
	 *
	 * @since 2.3.15
	 * @return array<string>
	 */
	private function get_presized_slot_name_enabled() {
		$presizedDiv = get_option( $this->plugin_name . '-presizeddiv-actirise', array() );

		if ( ! is_array( $presizedDiv ) ) {
			$presizedDiv = array();
		}

		$presizedDiv = array_map(
			function ( $value ) {
				return $value['slotName'];
			},
			$presizedDiv
		);

		return $presizedDiv;
	}

	/**
	 * Update fastcmp.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	private function update_fastcmp() {
		if ( get_option( 'actirise-fastcmp-lastupdate' ) === false ) {
			update_option( 'actirise-fastcmp-lastupdate', time() - 3601 );
		}

		$lastUpdate = get_option( 'actirise-fastcmp-lastupdate' );
		$now        = time();
		$diff       = $now - $lastUpdate;

		if ( $diff > 3600 ) {
			$cron = new Cron();
			$cron->get_fast_cmp();
		}
	}
}
