<?php
/**
 * This class manages the presized divs.
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
 * This class manages the presized divs.
 *
 * @since      2.0.0
 * @package    actirise
 * @subpackage actirise/public/includes
 * @author     actirise <support@actirise.com>
 *
 * @phpstan-type PresizedDivXpathConfigInjection array{hierarchy: string, xpath: array<string>}
 * @phpstan-type PresizedDivXpathConfigTarget array{devices: array<string>, page?: array{'operator': string, 'value': string}, variables?: array{'operator': string, 'value': string, 'name': string}, url?: array{'operator': string, 'value': string, 'name': string}}
 * @phpstan-type PresizedDivXpathConfig array{injection: array<PresizedDivXpathConfigInjection>, target?: PresizedDivXpathConfigTarget, htmlCode: string}
 * @phpstan-type PresizedDivSlot array{slotName: string, htmlCode: string, cssCode: string, devices: array<int, string>, xpathConfig: array<PresizedDivXpathConfig>}
 */
final class PresizedDiv {
	/**
	 * Presized divs
	 *
	 * @since    2.0.0
	 * @var NoPub
	 */
	private $no_pub;

	/**
	 * PresizedDiv constructor.
	 *
	 * @since    2.0.0
	 * @param NoPub $no_pub NoPub.
	 */
	public function __construct( $no_pub ) {
		$this->no_pub = $no_pub;

		if ( $GLOBALS['pagenow'] === 'wp-login.php' ) {
			return;
		}

		if ( ACTIRISE_CRON !== 'true' ) {
			$this->update_presized_div();
		}
	}

	/**
	 * Render presized divs
	 *
	 * @since    2.0.0
	 * @return void
	 */
	public function render() {
		if ( get_option( 'actirise-presizeddiv-active', 'false' ) === 'false' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['presized_div'] ) && $_GET['presized_div'] === 'false' ) {
			return;
		}

		$activeSlot = $this->get_active_slots();

		if ( empty( $activeSlot ) ) {
			return;
		}

		$priority = 10;

		add_action(
			'template_redirect',
			function () use ( $activeSlot ) {
				$headers         = headers_list();
				$has_html_header = false;

				foreach ( $headers as $header ) {
					if ( strpos( $header, 'Content-Type: text/html' ) !== false ) {
						$has_html_header = true;
						break;
					}
				}

				if ( ! $has_html_header ) {
					return;
				}

				if ( ! $this->isAuthorizedPage() ) {
					return;
				}

				if ( $this->no_pub->check_no_pub() ) {
					return;
				}

				ob_start(
					function ( $buffer ) use ( $activeSlot ) {
						if ( empty( $buffer ) ) {
							return $buffer;
						}

						/** @var string $buffer */
						if ( $this->is_amp_page( $buffer ) ) {
							return $buffer;
						}

						$extractedHtml = $this->extract_html( $buffer );
						$body_presized = $this->render_presized_div( $extractedHtml['body'], $activeSlot );
						$new_body      = $this->build_html( $extractedHtml['element'], $body_presized );

						return $new_body;
					}
				);
			},
			$priority
		);

		add_action(
			'shutdown',
			function () {
				if ( ob_get_length() ) {
					ob_end_flush();
				}
			},
			-1 * $priority
		);

		if ( $this->no_pub->check_no_pub() ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			function () use ( $activeSlot ) {
				$this->add_css( $activeSlot );
			},
			$priority
		);
	}

	/**
	 * Inject CSS for Presized Div
	 *
	 * @since    2.0.0
	 * @param array<PresizedDivSlot> $activeSlot Active slots.
	 *
	 * @return void
	 */
	public function add_css( $activeSlot ) {
		/** @var string $last_update */
		$last_update = get_option( 'actirise-presizeddiv-lastupdate', time() );

		wp_register_style( 'actirise-presized', false, array(), strval( $last_update ) );
		wp_enqueue_style( 'actirise-presized', '', array(), strval( $last_update ) );

		/** @var array<string> $cssCode */
		$cssCode = array();
		foreach ( $activeSlot as $slot ) {
			$cssCode[] = $slot['cssCode'];
		}

		$clearCss = array_map(
			function ( $css ) {
				/** @var string $css */
				return str_replace( array( '<style type="text/css">', '</style>' ), '', $css );
			},
			$cssCode
		);

		$css = implode( '', $clearCss );

		wp_add_inline_style( 'actirise-presized', $css );
	}

	/**
	 * Extract html
	 *
	 * @since    2.3.17
	 *
	 * @param string $cleanBuffer Clean buffer.
	 *
	 * @return  array{body: string, element: array{elementsReplaced: array<int, array{attr: string, content: string}>, elementsReplacedStyle: array<int, array{attr: string, content: string}>, elementsReplacedAffilizz: array<int, array{attr: string, content: string}>, headElement: string}}
	 */
	private function extract_html( $cleanBuffer ) {
		/** @var string $headElement */
		$headElement = '';
		/** @var array<int, array{attr: string, content: string}> $elementsReplaced */
		$elementsReplaced = array();
		/** @var array<int, array{attr: string, content: string}> $elementsReplacedStyle */
		$elementsReplacedStyle = array();
		/** @var array<int, array{attr: string, content: string}> $elementsReplacedAffilizz */
		$elementsReplacedAffilizz = array();

		$indexElement         = 0;
		$indexElementStyle    = 0;
		$indexElementAffilizz = 0;

		/** @var string $cleanBuffer */
		$cleanBuffer = preg_replace_callback(
			'/<head>(.*)<\/head>/s',
			function ( $matches ) use ( &$headElement ) {
				/** @var string $headElement */
				$headElement = $matches[1];

				return '<head><title></title></head>';
			},
			$cleanBuffer
		);

		/** @var string $cleanBuffer */
		$cleanBuffer = preg_replace_callback(
			'/<\s*script(?<attr>\s*[^>]*?)?>(?<content>.*?)?<\s*\/\s*script\s*>/ims',
			function ( $matches ) use ( &$elementsReplaced, &$indexElement ) {
				/** @var int $indexElement */
				$indexElement++;

				/** @var array{attr: string, content: string} $matches */
				$elementsReplaced[ $indexElement ] = array(
					'attr'    => $matches['attr'],
					'content' => $matches['content'],
				);

				return '<div data-actirise-script="actirise-template-div-' . $indexElement . '"></div>';
			},
			/** @var string $cleanBuffer */
			$cleanBuffer
		);

		/** @var string $cleanBuffer */
		$cleanBuffer = preg_replace_callback(
			'/<\s*style(?<attr>\s*[^>]*?)?>(?<content>.*?)?<\s*\/\s*style\s*>/ims',
			function ( $matches ) use ( &$elementsReplacedStyle, &$indexElementStyle ) {
				$indexElementStyle++;

				/** @var array{attr: string, content: string} $matches */
				$elementsReplacedStyle[ $indexElementStyle ] = array(
					'attr'    => $matches['attr'],
					'content' => $matches['content'],
				);

				return '<div data-actirise-style="actirise-template-div-' . $indexElementStyle . '"></div>';
			},
			/** @var string $cleanBuffer */
			$cleanBuffer
		);

		/** @var string $cleanBuffer */
		$cleanBuffer = preg_replace_callback(
			'/<affilizz-rendering-component(.*?)>(.*?)<\/affilizz-rendering-component>/is',
			function ( $matches ) use ( &$elementsReplacedAffilizz, &$indexElementAffilizz ) {
				$indexElementAffilizz++;

				$elementsReplacedAffilizz[ $indexElementAffilizz ] = array(
					'attr'    => $matches[1],
					'content' => $matches[2],
				);

				return '<div data-actirise-affilizz="actirise-template-div-' . $indexElementAffilizz . '"></div>';
			},
			/** @var string $cleanBuffer */
			$cleanBuffer
		);

		return array(
			'body'    => $cleanBuffer,
			'element' => array(
				'elementsReplaced'         => $elementsReplaced,
				'elementsReplacedStyle'    => $elementsReplacedStyle,
				'elementsReplacedAffilizz' => $elementsReplacedAffilizz,
				'headElement'              => $headElement,
			),
		);
	}

	/**
	 * Build final html
	 *
	 * @since    2.3.17
	 *
	 * @param array{elementsReplaced: array<int, array{attr: string, content: string}>, elementsReplacedStyle: array<int, array{attr: string, content: string}>, elementsReplacedAffilizz: array<int, array{attr: string, content: string}>, headElement: string} $extractedHtml Buffer.
	 * @param string                                                                                                                                                                                                                                              $htmlParsed Buffer.
	 *
	 * @return string
	 */
	private function build_html( $extractedHtml, $htmlParsed ) {
		$elementsReplaced         = $extractedHtml['elementsReplaced'];
		$elementsReplacedStyle    = $extractedHtml['elementsReplacedStyle'];
		$elementsReplacedAffilizz = $extractedHtml['elementsReplacedAffilizz'];
		$headElement              = $extractedHtml['headElement'];

		foreach ( $elementsReplaced as $indexElement => $element ) {
			/** @var string $htmlParsed */
			$htmlParsed = str_replace( '<div data-actirise-script="actirise-template-div-' . $indexElement . '"></div>', '<script' . $element['attr'] . '>' . $element['content'] . '</script>', $htmlParsed );
		}

		foreach ( $elementsReplacedStyle as $indexElementStyle => $elementStyle ) {
			/** @var string $htmlParsed */
			$htmlParsed = str_replace( '<div data-actirise-style="actirise-template-div-' . $indexElementStyle . '"></div>', '<style' . $elementStyle['attr'] . '>' . $elementStyle['content'] . '</style>', $htmlParsed );
		}

		foreach ( $elementsReplacedAffilizz as $indexElementAffilizz => $elementAffilizz ) {
			/** @var string $htmlParsed */
			$htmlParsed = str_replace( '<div data-actirise-affilizz="actirise-template-div-' . $indexElementAffilizz . '"></div>', '<affilizz-rendering-component' . $elementAffilizz['attr'] . '>' . $elementAffilizz['content'] . '</affilizz-rendering-component>', $htmlParsed );
		}

		/** @var string $htmlParsed */
		$htmlParsed = str_replace( '<title></title>', $headElement, $htmlParsed );

		return $htmlParsed;
	}

	/**
	 * Render presized divs
	 *
	 * @since    2.0.0
	 *
	 * @param string                 $cleanBuffer Buffer.
	 * @param array<PresizedDivSlot> $slots Slots.
	 *
	 * @return string Buffer
	 */
	private function render_presized_div( $cleanBuffer, $slots ) {
		if ( extension_loaded( 'tidy' ) ) {
			$tidy = new \tidy();

			$tidy = \tidy_parse_string(
				/** @var string $cleanBuffer */
				$cleanBuffer,
				array(
					'drop-empty-elements' => false,
					'drop-empty-paras'    => false,
				)
			);

			if ( $tidy ) {
				$tidy->cleanRepair();

				/** @var string $cleanBuffer */
				$cleanBuffer = $tidy;
			}
		}

		$cleanBuffer = '<?xml encoding="UTF-8">' . $cleanBuffer;

		$dom = new \DOMDocument();

		$dom->formatOutput = false;

		libxml_use_internal_errors( true );
		$dom->loadHTML( $cleanBuffer );

		libxml_clear_errors();

		$this->cleanEncoding( $dom );

		$xpathDom = new \DOMXPath( $dom );

		foreach ( $slots as $slot ) {
			if ( $slot['htmlCode'] === '' ) {
				continue;
			}

			$found = array();
			foreach ( $slot['xpathConfig'] as $xpathConfig ) {
				$html = ! empty( $xpathConfig['htmlCode'] ) ? $xpathConfig['htmlCode'] : $slot['htmlCode'];

				if ( ! empty( $xpathConfig['htmlCode'] ) && in_array( $html, $found, true ) ) {
					continue;
				}

				if ( $this->check_if_allowed( $xpathConfig ) ) {
					foreach ( $xpathConfig['injection'] as $injections ) {
						foreach ( $injections['xpath'] as $xpath ) {
							if ( empty( $xpath ) ) {
								continue;
							}

							$findElementQuery = $xpathDom->query( $xpath );

							if ( $findElementQuery !== false ) {
								$findElement = $findElementQuery->item( 0 );

								if ( ! is_null( $findElement ) ) {
									$this->render_html( $html, $dom, $findElement, $injections['hierarchy'] );

									if ( ! empty( $xpathConfig['htmlCode'] ) ) {
										$found[] = $html;
									}
								}
							}
						}
					}
				}
			}
		}

		/** @var string $htmlParsed */
		$htmlParsed = $dom->saveHTML();

		return $htmlParsed;
	}

	/**
	 * Clean encoding
	 *
	 * @since    2.0.0
	 * @param \DOMDocument $document Dom.
	 *
	 * @return \DOMDocument
	 */
	private function cleanEncoding( $document ) {
		/** @var \DOMNode $item */
		foreach ( $document->childNodes as $item ) {
			if ( $item->nodeType === XML_PI_NODE ) {
				$document->removeChild( $item );
			}
		}

		$document->encoding = 'UTF-8';

		return $document;
	}

	/**
	 * Render html balise
	 *
	 * @since    2.0.0
	 *
	 * @param string       $element Element.
	 * @param \DOMDocument $dom Dom.
	 * @param \DOMElement  $referenceNode Reference node.
	 * @param string       $placement Placement.
	 * @return void
	 */
	private function render_html( $element, $dom, &$referenceNode, $placement = 'before' ) {
		$elementFragment = $dom->createDocumentFragment();

		if ( $elementFragment !== false ) {
			$elementFragment->appendXML( $element );

			if ( 'before' === $placement ) {
				if ( $referenceNode->parentNode !== null ) {
					$referenceNode->parentNode->insertBefore( $elementFragment, $referenceNode );
				}
			} elseif ( 'after' === $placement ) {
				if ( $referenceNode->parentNode !== null && $referenceNode->nextSibling !== null ) {
					$referenceNode->parentNode->insertBefore( $elementFragment, $referenceNode->nextSibling );
				}
			} elseif ( 'prepend' === $placement ) {
				if ( $referenceNode->firstChild !== null ) {
					$referenceNode->insertBefore( $elementFragment, $referenceNode->firstChild );
				}
			} elseif ( 'append' === $placement ) {
				$referenceNode->insertBefore( $elementFragment );
			}
		}
	}

	/**
	 * Check if slot is allowed
	 *
	 * @since    2.0.0
	 *
	 * @param PresizedDivXpathConfig $configs Configs.
	 *
	 * @return bool
	 */
	private function check_if_allowed( $configs ) {
		if ( ! isset( $configs['target'] ) ) {
			return true;
		}

		// default allowed by type
		$allowedPage      = true;
		$allowedVariables = true;
		$allowedUrl       = true;

		if ( isset( $configs['target']['page'] ) ) {
			$allowedPage = $this->checkAllowedPage( $configs['target']['page'] );
		}

		if ( isset( $configs['target']['variables'] ) ) {
			$allowedVariables = $this->checkAllowedVariables( $configs['target']['variables'] );
		}

		if ( isset( $configs['target']['url'] ) ) {
			$allowedUrl = $this->checkAllowedUrl( $configs['target']['url'] );
		}

		return $allowedPage && $allowedVariables && $allowedUrl;
	}

	/**
	 * Check if page is allowed
	 *
	 * @since    2.0.0
	 * @param array<mixed> $config Config.
	 *
	 * @return bool
	 */
	private function checkAllowedPage( $config ) {
		$allowed = false;

		switch ( $config['operator'] ) {
			case 'eq':
				if ( $config['value'] === get_query_var( 'paged' ) ) {
					$allowed = true;
				}
				break;
			case 'ne':
				if ( $config['value'] !== get_query_var( 'paged' ) ) {
					$allowed = true;
				}
				break;
			case 'gte':
				if ( get_query_var( 'paged' ) >= $config['value'] ) {
					$allowed = true;
				}
				break;
			case 'lte':
				if ( get_query_var( 'paged' ) <= $config['value'] ) {
					$allowed = true;
				}
				break;
			case 'lt':
				if ( get_query_var( 'paged' ) < $config['value'] ) {
					$allowed = true;
				}
				break;
			case 'gt':
				if ( get_query_var( 'paged' ) > $config['value'] ) {
					$allowed = true;
				}
				break;
		}

		return $allowed;
	}

	/**
	 * Check if variables are allowed
	 *
	 * @since    2.0.0
	 * @param array{'operator': string, 'value': string, 'name': string} $config Config.
	 *
	 * @return bool
	 */
	private function checkAllowedVariables( $config ) {
		$allowed         = false;
		$currentPageType = Helpers::get_page_type();
		/** @var array{'operator': string, 'value': string, 'name': string} $config */
		$config = array_filter(
			$config,
			function ( $variable ) {
				/** @var array{'operator': string, 'value': string, 'name': string} $variable */
				return $variable['name'] === 'page_type' || strpos( $variable['name'], 'custom' ) !== false;
			}
		);

		/** @var array{'operator': string, 'value': string, 'name': string} $variable */
		foreach ( $config as $variable ) {
			if ( $variable['name'] === 'page_type' ) {
				if ( $variable['operator'] === 'match' ) {
					$variable['value'] = $this->cleanMatch( $variable['value'] );

					if ( preg_match( $variable['value'], $currentPageType ) ) {
						$allowed = true;
					}
				}

				if ( $variable['operator'] === 'eq' ) {
					if ( $currentPageType === $variable['value'] ) {
						$allowed = true;
					}
				}

				if ( $variable['operator'] === 'ne' ) {
					if ( $currentPageType !== $variable['value'] ) {
						$allowed = true;
					}
				}
			}

			if ( strpos( $variable['name'], 'custom' ) !== false ) {
				/** @var string $customOption */
				$customOption = get_option( 'actirise-' . $variable['name'], '' );

				if ( $customOption !== '' ) {
					$customValue = Helpers::get_custom_value( $customOption );

					if ( $variable['operator'] === 'match' ) {
						$variable['value'] = $this->cleanMatch( $variable['value'] );

						if ( preg_match( $variable['value'], $customValue ) ) {
							$allowed = true;
						}
					}

					if ( $variable['operator'] === 'eq' ) {
						if ( $customValue === $variable['value'] ) {
							$allowed = true;
						}
					}

					if ( $variable['operator'] === 'ne' ) {
						if ( $customValue !== $variable['value'] ) {
							$allowed = true;
						}
					}
				}
			}
		}

		return $allowed;
	}

	/**
	 * Check if url is allowed
	 *
	 * @since    2.0.0
	 * @param array{operator: string, value: string, name: string} $config Config.
	 *
	 * @return bool
	 */
	private function checkAllowedUrl( $config ) {
		$allowed    = false;
		$currentUrl = Helpers::get_url();

		if ( $config['operator'] === 'match' ) {
			$config['value'] = $this->cleanMatch( $config['value'] );

			if ( preg_match( $config['value'], $currentUrl ) ) {
				$allowed = true;
			}
		}

		if ( $config['operator'] === 'eq' ) {
			if ( $currentUrl === $config['value'] ) {
				$allowed = true;
			}
		}

		if ( $config['operator'] === 'ne' ) {
			if ( $currentUrl !== $config['value'] ) {
				$allowed = true;
			}
		}

		return $allowed;
	}

	/**
	 * Get active slots
	 *
	 * @since    2.0.0
	 *
	 * @return array<PresizedDivSlot> Active slots
	 */
	private function get_active_slots() {
		/** @var array{slotName: string, active: bool} $presizedDivActive */
		$presizedDivActive = get_option( 'actirise-presizeddiv-selected', array() );

		if ( empty( $presizedDivActive ) ) {
			return array();
		}

		/** @var array<PresizedDivSlot>$presizedDiv */
		$presizedDiv = get_option( 'actirise-presizeddiv-actirise', array() );

		/** @var array<string> $activeSlotName */
		$activeSlotName = array();

		/** @var array{slotName: string, active: bool} $div */
		foreach ( $presizedDivActive as $div ) {
			if ( $div['active'] ) {
				$activeSlotName[] = $div['slotName'];
			}
		}

		/** @var array<PresizedDivSlot> $activeSlot */
		$activeSlot = array();

		foreach ( $presizedDiv as $div ) {
			if ( in_array( $div['slotName'], $activeSlotName, true ) ) {
				$activeSlot[] = $div;
			}
		}

		return $activeSlot;
	}

	/**
	 * Update presized div
	 *
	 * @since    2.0.0
	 * @return void
	 */
	private function update_presized_div() {
		if ( get_option( 'actirise-presizeddiv-lastupdate' ) === false ) {
			update_option( 'actirise-presizeddiv-lastupdate', time() - 3601 );
		}

		$lastUpdate = get_option( 'actirise-presizeddiv-lastupdate' );
		$now        = time();
		$diff       = $now - $lastUpdate;

		if ( $diff > 3600 ) {
			$cron = new Cron();
			$cron->check_presized_div();
		}
	}

	/**
	 * Get presized divs from API
	 *
	 * @since    2.0.0
	 *
	 * @return array<\stdClass>|boolean Presized divs or false if error
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
		$api_url  = 'div_presized/' . $uuid;
		$api      = new Api();
		$response = $api->get( 'api', $api_url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::AddLog( 'get_from_api error ' . $response->get_error_code(), 'public/include/presizeddiv', 'error' );
			return false;
		}

		if ( ! is_array( $response ) ) {
			Logger::AddLog( 'get_from_api is not array', 'public/include/presizeddiv', 'error' );
			return false;
		}

		if ( ! isset( $response['configPresizedDiv'] ) ) {
			Logger::AddLog( 'get_from_api is not isset', 'public/include/presizeddiv', 'error' );
			return false;
		}

		$array_response = array( $response['configPresizedDiv'] );

		if ( count( $array_response ) !== 1 ) {
			return false;
		}

		if ( empty( $array_response[0] ) ) {
			Logger::AddLog( 'get_from_api empty', 'public/include/presizeddiv', 'error' );
			return false;
		}

		return $array_response[0];
	}

	/**
	 * Clean match for preg_match
	 *
	 * @since    2.0.0
	 * @param string $matchPattern
	 *
	 * @return string Cleaned match
	 */
	private function cleanMatch( $matchPattern ) {
		if ( substr( $matchPattern, 0, 1 ) === '(' ) {
			$matchPattern = substr( $matchPattern, 1 );
		}

		if ( substr( $matchPattern, -1 ) === ')' ) {
			$matchPattern = substr( $matchPattern, 0, -1 );
		}

		if ( substr( $matchPattern, 0, 1 ) === '^' ) {
			$matchPattern = substr( $matchPattern, 1 );
		}

		if ( substr( $matchPattern, -1 ) === '$' ) {
			$matchPattern = substr( $matchPattern, 0, -1 );
		}

		return '#' . $matchPattern . '#';
	}

	/**
	 * Check if page is authorized for rendering presized div
	 *
	 * @since       2.0.0
	 * @return boolean True if page is authorized
	 */
	private function isAuthorizedPage() {
		$authorized = false;

		if ( $this->is_woocommerce_active() ) {
			if ( \is_woocommerce() || \is_cart() || \is_checkout() || \is_account_page() ) {
				return false;
			}
		}

		if ( is_home() || is_front_page() ) {
			$authorized = true;
		} elseif ( is_page() ) {
			$authorized = true;
		} elseif ( is_single() ) {
			$authorized = true;
		} elseif ( is_category() ) {
			$authorized = true;
		} elseif ( is_tag() ) {
			$authorized = true;
		} elseif ( is_tax() ) {
			$authorized = true;
		} elseif ( is_archive() ) {
			$authorized = true;
		} elseif ( is_search() ) {
			$authorized = true;
		} elseif ( is_404() ) {
			$authorized = true;
		}

		return $authorized;
	}

	/**
	 * Is WooCommerce Installed
	 *
	 * @since    2.0.0
	 * @return bool
	 */
	private function is_woocommerce_active() {
		// @codeCoverageIgnoreStart
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// @codeCoverageIgnoreEnd
		return is_plugin_active( 'woocommerce/woocommerce.php' ) && function_exists( 'is_woocommerce' );
	}

	/**
	 * Check if page is AMP
	 *
	 * @since    2.3.7
	 * @param string $buffer Buffer.
	 *
	 * @return bool
	 */
	private function is_amp_page( $buffer ) {
		return strpos( $buffer, 'ampproject.org' ) !== false;
	}
}
