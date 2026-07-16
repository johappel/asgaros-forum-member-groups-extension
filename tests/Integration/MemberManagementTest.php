<?php
/**
 * Integrationstests: Mitglieder-Verwaltung gegen echte WP + Asgaros.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Core\DomainException;

/**
 * Testet Hinzufügen/Entfernen, Audit und Pagination.
 */
final class MemberManagementTest extends IntegrationTestCase {

	/**
	 * Erstellt einen temporären WP-Benutzer für den Test.
	 *
	 * @param string $suffix Eindeutiges Suffix.
	 * @return int Benutzer-ID.
	 */
	private function make_user( string $suffix ): int {
		$login = 'afspaces_test_' . $suffix;
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			return (int) $user->ID;
		}
		$user_id = wp_create_user( $login, 'password', $suffix . '@example.com' );
		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	/**
	 * Test: Hinzufügen erzeugt Asgaros-Gruppenzuordnung.
	 */
	public function test_add_creates_asgaros_group_assignment(): void {
		$actor = $this->make_user( 'actor_add' );
		$target = $this->make_user( 'target_add' );
		$space_id = $this->create_test_space( $actor );

		$this->members->add_member( $space_id, $actor, $target );

		$this->assertTrue(
			\AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ),
			'Benutzer muss in der Asgaros-Gruppe sein.'
		);

		$this->cleanup_user_from_group( $target );
	}

	/**
	 * Test: Entfernen löscht Asgaros-Gruppenzuordnung.
	 */
	public function test_remove_deletes_asgaros_group_assignment(): void {
		$actor = $this->make_user( 'actor_rem' );
		$target = $this->make_user( 'target_rem' );
		$space_id = $this->create_test_space( $actor );

		$this->members->add_member( $space_id, $actor, $target );
		$this->assertTrue( \AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ) );

		$this->members->remove_member( $space_id, $actor, $target );

		$this->assertFalse(
			\AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ),
			'Benutzer darf nicht mehr in der Asgaros-Gruppe sein.'
		);
	}

	/**
	 * Test: Audit-Eintrag wird erzeugt.
	 */
	public function test_audit_entry_is_created(): void {
		$actor = $this->make_user( 'actor_audit' );
		$target = $this->make_user( 'target_audit' );
		$space_id = $this->create_test_space( $actor );

		$this->members->add_member( $space_id, $actor, $target );

		$entries = $this->audit->list_for_space( $space_id, 10 );
		$this->assertNotEmpty( $entries, 'Mindestens ein Audit-Eintrag erwartet.' );

		$found = false;
		foreach ( $entries as $entry ) {
			if ( 'member_added' === $entry['action'] && (int) $entry['target_user_id'] === $target ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Audit-Eintrag für member_added erwartet.' );

		$this->cleanup_user_from_group( $target );
	}

	/**
	 * Test: Benutzerlisten sind paginiert.
	 */
	public function test_group_members_are_paginated(): void {
		// Einen Benutzer hinzufügen, damit die Gruppe nicht leer ist.
		$uniq   = uniqid( '', false );
		$actor  = $this->make_user( 'actor_pag_' . $uniq );
		$target = $this->make_user( 'target_pag_' . $uniq );
		$space_id = $this->create_test_space( $actor );
		$this->members->add_member( $space_id, $actor, $target );

		// Sicherstellen, dass der Benutzer wirklich in der Gruppe ist.
		$this->assertTrue(
			\AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ),
			'Benutzer muss in der Gruppe sein, bevor die Liste geprüft wird.'
		);

		$result = $this->asgaros->list_group_members( $this->group_id, array(
			'page'     => 1,
			'per_page' => 5,
		) );

		$this->assertArrayHasKey( 'members', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertLessThanOrEqual( 5, count( $result['members'] ), 'Pro Seite max. 5 Mitglieder.' );
		$this->assertGreaterThanOrEqual( 1, $result['total'], 'Mindestens 1 Mitglied erwartet.' );

		$this->cleanup_user_from_group( $target );
	}

	/**
	 * Test: Inkompatible Asgaros-Version deaktiviert Schreiboperationen.
	 */
	public function test_incompatible_asgaros_disables_writes(): void {
		// Mock Requirements mit nicht unterstützter Version.
		$req = $this->createMock( \AFSpaces\Core\Requirements::class );
		$req->method( 'is_asgaros_active' )->willReturn( true );
		$req->method( 'is_asgaros_version_supported' )->willReturn( false );

		$adapter = new \AFSpaces\Adapters\Asgaros\AsgarosAdapter( $req );

		$this->expectException( DomainException::class );
		$adapter->add_user_to_group( 1, $this->group_id );
	}
}
