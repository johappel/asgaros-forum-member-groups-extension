<?php
/**
 * Tests für die Trennung von Lese- und Schreibrecht.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Application\ForumContentWritePolicy;
use AFSpaces\Core\SpaceCreationSettings;
use PHPUnit\Framework\TestCase;

final class ForumContentWritePolicyTest extends TestCase {

	/**
	 * @dataProvider write_matrix
	 */
	public function test_read_visibility_does_not_grant_write_access( string $visibility, bool $member, bool $moderator, bool $expected ): void {
		self::assertSame( $expected, ForumContentWritePolicy::can_write( $visibility, $member, $moderator ) );
	}

	/**
	 * @return array<string,array{string,bool,bool,bool}>
	 */
	public static function write_matrix(): array {
		return array(
			'protected nonmember'     => array( SpaceCreationSettings::VISIBILITY_PROTECTED, false, false, false ),
			'protected member'        => array( SpaceCreationSettings::VISIBILITY_PROTECTED, true, false, true ),
			'protected moderator'     => array( SpaceCreationSettings::VISIBILITY_PROTECTED, false, true, true ),
			'private nonmember'       => array( SpaceCreationSettings::VISIBILITY_PRIVATE, false, false, false ),
			'private member'          => array( SpaceCreationSettings::VISIBILITY_PRIVATE, true, false, true ),
			'public nonmember'        => array( SpaceCreationSettings::VISIBILITY_PUBLIC, false, false, true ),
		);
	}
}
