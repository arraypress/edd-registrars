<?php
/**
 * RecountTools tests.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\RecountTools;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Error;

/**
 * Covers RecountTools.
 */
final class RecountToolsTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		edd_registrars_reset();

		// The callback registry is static, so it outlives the instance.
		( new ReflectionClass( RecountTools::class ) )
			->getProperty( 'callback_tools' )
			->setValue( null, [] );
	}

	/**
	 * A valid callback-based tool.
	 *
	 * @param array $overrides Fields to change.
	 *
	 * @return array
	 */
	private function callback_tool( array $overrides = [] ): array {
		return $overrides + [
			'callback'       => static fn( $step ) => [],
			'count_callback' => static fn() => 0,
		];
	}

	/**
	 * Capture what a rendering method prints.
	 *
	 * @param callable $callable The method to run.
	 *
	 * @return string
	 */
	private function capture( callable $callable ): string {
		ob_start();
		$callable();

		return (string) ob_get_clean();
	}

	/**
	 * An empty array is rejected.
	 *
	 * @return void
	 */
	public function test_empty_tools_is_an_error(): void {
		$result = ( new RecountTools() )->register( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_tools', $result->get_error_code() );
	}

	/**
	 * A valid callback tool registers.
	 *
	 * @return void
	 */
	public function test_registers_a_callback_tool(): void {
		$this->assertTrue( ( new RecountTools() )->register( [ 'my-tool' => $this->callback_tool() ] ) );
		$this->assertNotNull( RecountTools::get_callback_tool( 'my-tool' ) );
	}

	/**
	 * Each invalid configuration is rejected with a code naming the problem.
	 *
	 * @return void
	 */
	public function test_validates_tool_configuration(): void {
		$cases = [
			'invalid_callback'       => $this->callback_tool( [ 'callback' => 'no_such_function_xyz' ] ),
			'invalid_count_callback' => $this->callback_tool( [ 'count_callback' => 'no_such_function_xyz' ] ),
			'invalid_tool_config'    => [ 'label' => 'Neither one thing nor the other' ],
			'missing_tool_class'     => [ 'class' => '', 'file' => __FILE__ ],
			'missing_tool_file'      => [ 'class' => 'Some_Class', 'file' => '' ],
		];

		foreach ( $cases as $expected => $tool ) {
			$result = ( new RecountTools() )->register( [ 'a-tool' => $tool ] );

			$this->assertInstanceOf( WP_Error::class, $result, $expected );
			$this->assertSame( $expected, $result->get_error_code() );
		}
	}

	/**
	 * A class-based tool registers.
	 *
	 * @return void
	 */
	public function test_registers_a_class_tool(): void {
		$this->assertTrue( ( new RecountTools() )->register( [
			'legacy' => [ 'class' => 'My_Batch_Processor', 'file' => __FILE__ ],
		] ) );
	}

	/**
	 * A class-based tool is not added to the callback registry.
	 *
	 * @return void
	 */
	public function test_class_tool_is_not_a_callback_tool(): void {
		( new RecountTools() )->register( [
			'legacy' => [ 'class' => 'My_Batch_Processor', 'file' => __FILE__ ],
		] );

		$this->assertNull( RecountTools::get_callback_tool( 'legacy' ) );
	}

	/**
	 * An unregistered key yields null rather than a notice.
	 *
	 * @return void
	 */
	public function test_unknown_callback_tool_is_null(): void {
		$this->assertNull( RecountTools::get_callback_tool( 'never-registered' ) );
	}

	/**
	 * The label is derived from the key when one is not given.
	 *
	 * @return void
	 */
	public function test_label_derived_from_key(): void {
		$tools = new RecountTools();
		$tools->register( [ 'recount-store-earnings' => $this->callback_tool() ] );

		$html = $this->capture( [ $tools, 'add_recount_tool_options' ] );

		$this->assertStringContainsString( '>Recount Store Earnings<', $html );
	}

	/**
	 * An explicit label wins.
	 *
	 * @return void
	 */
	public function test_explicit_label_is_used(): void {
		$tools = new RecountTools();
		$tools->register( [ 'x' => $this->callback_tool( [ 'label' => 'Rebuild The Ledger' ] ) ] );

		$this->assertStringContainsString(
			'>Rebuild The Ledger<',
			$this->capture( [ $tools, 'add_recount_tool_options' ] )
		);
	}

	/**
	 * batch_size defaults to 20.
	 *
	 * @return void
	 */
	public function test_batch_size_defaults(): void {
		( new RecountTools() )->register( [ 'x' => $this->callback_tool() ] );

		$this->assertSame( 20, RecountTools::get_callback_tool( 'x' )['batch_size'] );
	}

	/**
	 * An explicit batch_size is kept.
	 *
	 * @return void
	 */
	public function test_batch_size_override(): void {
		( new RecountTools() )->register( [ 'x' => $this->callback_tool( [ 'batch_size' => 100 ] ) ] );

		$this->assertSame( 100, RecountTools::get_callback_tool( 'x' )['batch_size'] );
	}

	/**
	 * The option markup follows EDD's own convention.
	 *
	 * EDD's built-in options carry the slug in data-type and the processor
	 * class in value (includes/admin/tools.php), and its JS keys off data-type.
	 * A callback tool is processed by the shared batch class.
	 *
	 * @return void
	 */
	public function test_option_markup_matches_edd(): void {
		$tools = new RecountTools();
		$tools->register( [
			'my-tool' => $this->callback_tool( [ 'label' => 'My Tool' ] ),
			'legacy'  => [ 'class' => 'My_Batch_Processor', 'file' => __FILE__, 'label' => 'Legacy' ],
		] );

		$html = $this->capture( [ $tools, 'add_recount_tool_options' ] );

		$this->assertStringContainsString(
			'<option data-type="my-tool" value="EDD_Batch_Callback_Processor" data-tool-key="my-tool">My Tool</option>',
			$html
		);
		$this->assertStringContainsString(
			'<option data-type="legacy" value="My_Batch_Processor" data-tool-key="legacy">Legacy</option>',
			$html
		);
	}

	/**
	 * The label is escaped into the option text.
	 *
	 * @return void
	 */
	public function test_option_label_is_escaped(): void {
		$tools = new RecountTools();
		$tools->register( [ 'x' => $this->callback_tool( [ 'label' => '<script>alert(1)</script>' ] ) ] );

		$html = $this->capture( [ $tools, 'add_recount_tool_options' ] );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A key containing a quote cannot break out of the attribute.
	 *
	 * @return void
	 */
	public function test_option_attributes_are_escaped(): void {
		$tools = new RecountTools();
		$tools->register( [ 'a" onclick="x' => $this->callback_tool() ] );

		$html = $this->capture( [ $tools, 'add_recount_tool_options' ] );

		$this->assertStringNotContainsString( 'onclick="x"', $html );
		$this->assertStringContainsString( '&quot;', $html );
	}

	/**
	 * Only tools carrying a description render one.
	 *
	 * @return void
	 */
	public function test_descriptions_render_only_when_present(): void {
		$tools = new RecountTools();
		$tools->register( [
			'described' => $this->callback_tool( [ 'description' => 'Recounts <strong>everything</strong>.' ] ),
			'bare'      => $this->callback_tool(),
		] );

		$html = $this->capture( [ $tools, 'add_recount_tool_descriptions' ] );

		$this->assertStringContainsString( '<span id="described">', $html );
		$this->assertStringNotContainsString( 'id="bare"', $html );
	}

	/**
	 * A description keeps post-safe markup but loses a script.
	 *
	 * @return void
	 */
	public function test_description_is_filtered(): void {
		$tools = new RecountTools();
		$tools->register( [
			'x' => $this->callback_tool( [ 'description' => 'Safe <strong>bold</strong><script>alert(1)</script>' ] ),
		] );

		$html = $this->capture( [ $tools, 'add_recount_tool_descriptions' ] );

		$this->assertStringContainsString( '<strong>bold</strong>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Registering hooks the three EDD actions.
	 *
	 * @return void
	 */
	public function test_registering_adds_hooks(): void {
		( new RecountTools() )->register( [ 'x' => $this->callback_tool() ] );

		foreach ( [ 'edd_recount_tool_options', 'edd_recount_tool_descriptions', 'edd_batch_export_class_include' ] as $hook ) {
			$this->assertCount( 1, edd_registrars_hooked( $hook ), $hook );
		}
	}

	/**
	 * A second register() call does not hook everything twice.
	 *
	 * Every callback on an action runs in turn, so a duplicated
	 * edd_recount_tool_options hook would print every option twice.
	 *
	 * @return void
	 */
	public function test_hooks_are_added_once(): void {
		$tools = new RecountTools();
		$tools->register( [ 'a' => $this->callback_tool() ] );
		$tools->register( [ 'b' => $this->callback_tool() ] );

		$this->assertCount( 1, edd_registrars_hooked( 'edd_recount_tool_options' ) );
	}

	/**
	 * A second register() call keeps the tools from the first.
	 *
	 * @return void
	 */
	public function test_second_register_merges(): void {
		$tools = new RecountTools();
		$tools->register( [ 'a' => $this->callback_tool( [ 'label' => 'Alpha' ] ) ] );
		$tools->register( [ 'b' => $this->callback_tool( [ 'label' => 'Beta' ] ) ] );

		$html = $this->capture( [ $tools, 'add_recount_tool_options' ] );

		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertStringContainsString( 'Beta', $html );
	}

	/**
	 * A failed registration does not hook anything.
	 *
	 * @return void
	 */
	public function test_invalid_registration_adds_no_hooks(): void {
		( new RecountTools() )->register( [ 'x' => [ 'nothing' => 'useful' ] ] );

		$this->assertCount( 0, edd_registrars_hooked( 'edd_recount_tool_options' ) );
	}

	/**
	 * The shared processor class name is ignored by the file includer.
	 *
	 * @return void
	 */
	public function test_include_batch_processor_ignores_the_shared_class(): void {
		$tools = new RecountTools();
		$tools->register( [ 'x' => $this->callback_tool() ] );

		$tools->include_batch_processor( 'EDD_Batch_Callback_Processor' );

		$this->assertTrue( true, 'returned without attempting an include' );
	}

	/**
	 * A class tool whose file is missing does not fatal.
	 *
	 * @return void
	 */
	public function test_include_batch_processor_tolerates_a_missing_file(): void {
		$tools = new RecountTools();
		$tools->register( [
			'legacy' => [ 'class' => 'Absent_Processor', 'file' => '/no/such/file.php' ],
		] );

		$tools->include_batch_processor( 'Absent_Processor' );

		$this->assertTrue( true, 'a missing file is checked before requiring' );
	}

}
