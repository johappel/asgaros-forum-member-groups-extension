<?php
/**
 * Unit-Tests fuer sichtbare Arbeitsgruppen-Begriffe.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\WorkingGroupTerminology;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Interface/WorkingGroupTerminology.php';

final class WorkingGroupTerminologyTest extends TestCase {

	public function test_known_labels_resolve_to_working_group_terms(): void {
		$this->assertSame( 'Arbeitsgruppe', WorkingGroupTerminology::label( WorkingGroupTerminology::SINGULAR ) );
		$this->assertSame( 'Arbeitsgruppen', WorkingGroupTerminology::label( WorkingGroupTerminology::PLURAL ) );
		$this->assertSame( 'Meine Arbeitsgruppen', WorkingGroupTerminology::label( WorkingGroupTerminology::MY_PLURAL ) );
		$this->assertSame( 'Arbeitsgruppen entdecken', WorkingGroupTerminology::label( WorkingGroupTerminology::DISCOVER ) );
		$this->assertSame( 'Arbeitsgruppenverantwortliche', WorkingGroupTerminology::label( WorkingGroupTerminology::MANAGER_PLURAL ) );
	}

	public function test_membership_count_uses_german_member_labels(): void {
		$this->assertSame( '1 Mitglied', WorkingGroupTerminology::membership_count( 1 ) );
		$this->assertSame( '3 Mitglieder', WorkingGroupTerminology::membership_count( 3 ) );
	}

	public function test_manage_context_uses_working_group_copy(): void {
		$this->assertSame( 'Arbeitsgruppe verwalten: Team Nord', WorkingGroupTerminology::manage_context( 'Team Nord' ) );
	}
}