<?php
/**
 * The function_exists guards around the helper functions.
 *
 * A guard that names a different function than the one it wraps is not a
 * syntax error and nothing warns about it. It simply means that if anything
 * else on the site defines the name in the guard, this function is never
 * declared at all -- an undefined-function fatal at the first call, in a
 * plugin that has nothing to do with the conflict.
 *
 * This used to read one file and check two helpers, because it belonged to
 * one of the eight packages that became this one. It reads every entrypoint
 * now, which is twenty-two.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class FunctionGuardsTest
 */
final class FunctionGuardsTest extends TestCase {

	/**
	 * Every file Composer loads for its global functions.
	 *
	 * Read from composer.json rather than listed here: an entrypoint added
	 * to the package and not to this list is exactly the one whose guards
	 * nobody would check.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function entrypoints(): array {
		$composer = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/composer.json' ), true );
		$cases    = [];

		foreach ( (array) ( $composer['autoload']['files'] ?? [] ) as $file ) {
			$cases[ basename( $file ) ] = [ $file ];
		}

		return $cases;
	}

	/**
	 * Guard name paired with the function declared inside it.
	 *
	 * Both spellings are in use here -- `if ( ... ) { }` and `if ( ... ) :
	 * endif;` -- because the packages this was assembled from did not agree.
	 * Pairing a guard with the next function declared after it works for
	 * either, and does not care which.
	 *
	 * @param string $file Path relative to the package root.
	 *
	 * @return array<string, string>
	 */
	private function guards( string $file ): array {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );
		$guards = [];

		preg_match_all(
			'/function_exists\(\s*[\'"]([^\'"]+)[\'"]\s*\)(.*?)\bfunction\s+([a-zA-Z0-9_]+)\s*\(/s',
			$source,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $match ) {
			$guards[ $match[1] ] = $match[3];
		}

		return $guards;
	}

	/**
	 * Every helper in the file is guarded, and no guard is left over.
	 *
	 * @param string $file Entrypoint.
	 */
	#[DataProvider( 'entrypoints' )]
	public function test_every_helper_is_guarded( string $file ): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );

		preg_match_all( '/^\s*function\s+([a-zA-Z0-9_]+)\s*\(/m', $source, $declared );

		$this->assertNotEmpty( $declared[1], $file . ' declares no helpers.' );
		$this->assertSame(
			count( $declared[1] ),
			count( $this->guards( $file ) ),
			$file . ' declares a helper that nothing guards.'
		);
	}

	/**
	 * Each guard names the function it wraps.
	 *
	 * @param string $file Entrypoint.
	 */
	#[DataProvider( 'entrypoints' )]
	public function test_each_guard_names_the_function_it_wraps( string $file ): void {
		foreach ( $this->guards( $file ) as $guarded => $declared ) {
			$this->assertSame(
				$guarded,
				$declared,
				sprintf( '%s: the guard checks "%s" but declares "%s".', $file, $guarded, $declared )
			);
		}
	}

	/**
	 * The public API is the one the eight packages had between them.
	 *
	 * Listed rather than derived, because the point of the merge was that
	 * none of these names change: a plugin that required one of the old
	 * packages calls exactly these.
	 */
	public function test_the_whole_public_api_survived_the_merge(): void {
		$declared = [];

		foreach ( self::entrypoints() as $case ) {
			$declared = array_merge( $declared, array_values( $this->guards( $case[0] ) ) );
		}

		sort( $declared );

		$this->assertSame(
			[
				'edd_register_commission_columns',
				'edd_register_custom_email_tag',
				'edd_register_custom_email_tags',
				'edd_register_custom_exporter',
				'edd_register_custom_exporters',
				'edd_register_custom_log_view',
				'edd_register_customer_columns',
				'edd_register_customer_stat',
				'edd_register_customer_stats',
				'edd_register_discount_columns',
				'edd_register_download_columns',
				'edd_register_download_settings_fields',
				'edd_register_export_columns',
				'edd_register_license_columns',
				'edd_register_log_order_link',
				'edd_register_order_columns',
				'edd_register_recount_tools',
				'edd_register_sections',
				'edd_register_sidebar_metaboxes',
				'edd_register_single_price_fields',
				'edd_register_subscription_columns',
				'edd_register_variable_price_fields',
			],
			$declared
		);
	}
}
