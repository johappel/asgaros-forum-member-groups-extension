<?php
/**
 * Regressionstests fuer Issue #21.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use PHPUnit\Framework\TestCase;

final class Issue21DesignTest extends TestCase {

	public function test_working_group_settings_no_longer_offer_directory_visibility(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/WorkingGroupSettingsView.php' );

		self::assertStringNotContainsString( 'directory_visibility', $source );
		self::assertStringNotContainsString( 'Sichtbarkeit in Übersichten', $source );
		self::assertStringContainsString( 'Zugriff auf das Forum', $source );
		self::assertStringContainsString( 'Arbeitsgruppe ansehen', $source );
	}

	public function test_detail_heading_is_rendered_above_space_navigation(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/SpacesHubController.php' );

		self::assertStringContainsString( "__( 'Arbeitsgruppe: %s', 'afspaces' )", $source );
		self::assertStringContainsString( 'class="afspaces-space-context-title"', $source );
		self::assertLessThan(
			strpos( $source, '<nav class="afspaces-hub-nav afspaces-space-nav"' ),
			strpos( $source, 'class="afspaces-space-context-title"' )
		);
		self::assertStringNotContainsString( 'WorkingGroupTerminology::manage_context( $room_name )', $source );
	}

	public function test_space_management_headings_do_not_repeat_the_group_name(): void {
		$views = array(
			array( 'MembersView.php', 'Mitglieder verwalten', 'Mitglieder verwalten - %s' ),
			array( 'InvitationsView.php', 'Einladungen zur Arbeitsgruppe', 'Einladungen - %s' ),
			array( 'JoinRequestsView.php', 'Beitrittsanfragen', 'Beitrittsanfragen - %s' ),
			array( 'ModerationView.php', 'Moderation', 'Moderation - %s' ),
			array( 'ModerationView.php', 'Beiträge moderieren', 'Beiträge moderieren - %s' ),
		);

		foreach ( $views as $view ) {
			$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/' . $view[0] );
			self::assertStringContainsString( $view[1], $source );
			self::assertStringNotContainsString( $view[2], $source );
		}
	}

	public function test_space_subnavigation_uses_compact_neutral_hover_style(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces.css' );

		self::assertStringContainsString( '.afspaces-space-nav .afspaces-hub-tab:hover', $source );
		self::assertStringContainsString( 'font-size: 16px;', $source );
		self::assertStringContainsString( 'color: #50575e;', $source );
		self::assertStringContainsString( 'text-decoration: underline;', $source );
		self::assertStringContainsString( 'background: transparent !important;', $source );
		self::assertStringContainsString( 'border-bottom: 3px solid #224c75;', $source );
		self::assertStringContainsString( 'border-radius: 16px;', $source );
		self::assertStringContainsString( '.afspaces-invite-link-form > button[type="submit"]', $source );
		self::assertStringContainsString( 'margin-top: 0.5rem;', $source );
	}

	public function test_owner_role_is_translated_to_german_ui_term(): void {
		$service = (string) file_get_contents( dirname( __DIR__ ) . '/src/Application/WorkingGroupService.php' );
		$members = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/MembersView.php' );

		self::assertStringContainsString( 'Besitzer:in', $service );
		self::assertStringContainsString( 'Besitzer:in', $members );
		self::assertStringNotContainsString( "__( 'Owner', 'afspaces' )", $service );
		self::assertStringNotContainsString( "__( 'Owner', 'afspaces' )", $members );
	}
}
