<?php
/**
 * Unit-Tests für das Dokument-Domainmodell.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Domain\SpaceDocument;
use PHPUnit\Framework\TestCase;

final class SpaceDocumentDomainTest extends TestCase {

	protected function tearDown(): void {
		global $afspaces_test_filters;
		$afspaces_test_filters = array();
		parent::tearDown();
	}

	public function test_default_title_strips_extension_and_separators(): void {
		$this->assertSame( 'Protokoll September', SpaceDocument::default_title_from_filename( 'Protokoll_September.pdf' ) );
		$this->assertSame( 'Konzept 2027', SpaceDocument::default_title_from_filename( 'Konzept-2027.docx' ) );
	}

	public function test_file_extension_is_normalized(): void {
		$this->assertSame( 'pdf', SpaceDocument::file_extension( 'Report.PDF' ) );
		$this->assertSame( '', SpaceDocument::file_extension( 'ohneendung' ) );
	}

	public function test_public_visibility_is_downgraded_when_not_enabled(): void {
		$this->assertSame( SpaceDocument::VISIBILITY_AUTHENTICATED, SpaceDocument::sanitize_visibility( 'public' ) );
		$this->assertSame( SpaceDocument::VISIBILITY_MEMBERS, SpaceDocument::sanitize_visibility( 'unknown' ) );
		$this->assertSame( SpaceDocument::VISIBILITY_AUTHENTICATED, SpaceDocument::sanitize_visibility( 'authenticated' ) );
	}

	public function test_public_visibility_allowed_when_filter_enables_it(): void {
		global $afspaces_test_filters;
		$afspaces_test_filters['afspaces_documents_public_enabled'] = array(
			static fn (): bool => true,
		);

		$this->assertSame( SpaceDocument::VISIBILITY_PUBLIC, SpaceDocument::sanitize_visibility( 'public' ) );
		$this->assertArrayHasKey( SpaceDocument::VISIBILITY_PUBLIC, SpaceDocument::visibility_options() );
	}

	public function test_visibility_options_exclude_public_by_default(): void {
		$options = SpaceDocument::visibility_options();
		$this->assertArrayHasKey( SpaceDocument::VISIBILITY_MEMBERS, $options );
		$this->assertArrayHasKey( SpaceDocument::VISIBILITY_AUTHENTICATED, $options );
		$this->assertArrayNotHasKey( SpaceDocument::VISIBILITY_PUBLIC, $options );
	}

	public function test_sanitize_title_and_topic_collapse_whitespace(): void {
		$this->assertSame( 'Viel Text', SpaceDocument::sanitize_title( "  Viel   Text  " ) );
		$this->assertSame( 'Protokolle', SpaceDocument::sanitize_topic( "Protokolle\n" ) );
	}
}
