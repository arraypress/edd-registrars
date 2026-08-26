<?php
/**
 * Helper functions for EDD Email Tags registration
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 */

declare( strict_types=1 );

use ArrayPress\EDD\Register\EmailTags;
use ArrayPress\EDD\Register\EmailTag;

if ( ! function_exists( 'edd_register_custom_email_tag' ) ) :
	/**
	 * Register a single email tag.
	 *
	 * @param string $tag  Tag identifier.
	 * @param array  $args {
	 *     Tag arguments.
	 *
	 *     @type string   $description Tag description.
	 *     @type string   $label       Tag label.
	 *     @type callable $callback    Callback function.
	 *     @type array    $contexts    Allowed contexts. Default ['order'].
	 *     @type array    $recipients  Allowed recipients. Default [].
	 * }
	 * @param string $file Plugin file path. Default uses calling file.
	 *
	 * @return EmailTags|false Factory instance or false on failure.
	 */
	function edd_register_custom_email_tag( string $tag, array $args, string $file = '' ) {
		if ( empty( $tag ) || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}

		if ( empty( $file ) ) {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
			$file      = $backtrace[0]['file'] ?? '';
		}

		if ( empty( $file ) ) {
			return false;
		}

		$defaults = [
			'description' => '',
			'label'       => ucfirst( str_replace( '_', ' ', $tag ) ),
			'callback'    => null,
			'contexts'    => [ 'order' ],
			'recipients'  => [],
		];

		$args = wp_parse_args( $args, $defaults );

		return EmailTags::register( $file )
		                ->tag( $tag )
		                ->description( $args['description'] )
		                ->label( $args['label'] )
		                ->callback( $args['callback'] )
		                ->contexts( $args['contexts'] )
		                ->recipients( $args['recipients'] )
		                ->register();
	}
endif;

if ( ! function_exists( 'edd_register_custom_email_tags' ) ) :
	/**
	 * Register multiple email tags.
	 *
	 * @param array  $tags Array of tags keyed by tag identifier.
	 * @param string $file Plugin file path. Default uses calling file.
	 *
	 * @return EmailTags|false Factory instance or false on failure.
	 */
	function edd_register_custom_email_tags( array $tags, string $file = '' ) {
		if ( empty( $tags ) ) {
			return false;
		}

		if ( empty( $file ) ) {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
			$file      = $backtrace[0]['file'] ?? '';
		}

		if ( empty( $file ) ) {
			return false;
		}

		$factory = EmailTags::register( $file );

		foreach ( $tags as $tag => $args ) {
			if ( empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
				continue;
			}

			$defaults = [
				'description' => '',
				'label'       => ucfirst( str_replace( '_', ' ', $tag ) ),
				'callback'    => null,
				'contexts'    => [ 'order' ],
				'recipients'  => [],
			];

			$args = wp_parse_args( $args, $defaults );

			$factory->tag( $tag )
			        ->description( $args['description'] )
			        ->label( $args['label'] )
			        ->callback( $args['callback'] )
			        ->contexts( $args['contexts'] )
			        ->recipients( $args['recipients'] )
			        ->register();
		}

		return $factory;
	}
endif;
