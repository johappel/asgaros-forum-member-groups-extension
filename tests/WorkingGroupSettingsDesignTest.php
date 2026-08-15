<?php
/**
 * Regressionstests für die konsolidierte Arbeitsgruppen-Einstellungsansicht.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use PHPUnit\Framework\TestCase;

final class WorkingGroupSettingsDesignTest extends TestCase {

	private function source( string $file ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );
	}

	public function test_normal_settings_use_one_common_form_and_keep_navigation_separate(): void {
		$source = $this->source( 'src/Interface/WorkingGroupSettingsView.php' );

		self::assertSame( 1, substr_count( $source, 'value="save_working_group_settings"' ) );
		self::assertStringContainsString( 'Änderungen speichern', $source );
		self::assertStringNotContainsString( 'Arbeitsgruppe bearbeiten', $source );
		self::assertStringNotContainsString( 'Hier bearbeitest du die gemeinsamen Einstellungen deiner Arbeitsgruppe.', $source );
		self::assertStringNotContainsString( 'afspaces-working-group-details-heading', $source );
		self::assertStringContainsString( 'Worum geht es?', $source );
		self::assertStringContainsString( 'Wie können andere mit der Gruppe Kontakt aufnehmen?', $source );
		self::assertStringContainsString( 'Darstellung', $source );
		self::assertStringContainsString( 'Zugang und Mitgliedschaft', $source );
		self::assertStringContainsString( 'Verantwortliche', $source );
		self::assertStringContainsString( 'Verwaltung', $source );
		self::assertStringContainsString( 'Gefahrenbereich', $source );
		self::assertStringContainsString( 'SpaceCreationSettings::VISIBILITY_PUBLIC !== $visibility', $source );
		self::assertStringContainsString( 'Verantwortliche in der Mitgliederverwaltung bearbeiten', $source );
		self::assertStringNotContainsString( 'save_working_group_meta', $source );
		self::assertStringNotContainsString( 'name="join_requests_enabled"', $source );
		self::assertStringNotContainsString( 'Verantwortung und Moderation', $source );
		self::assertLessThan( strpos( $source, '<form method="post" class="afspaces-working-group-form' ), strpos( $source, 'Arbeitsgruppe ansehen' ) );
	}

	public function test_combined_action_maps_join_mode_and_validates_before_writes(): void {
		$controller = $this->source( 'src/Interface/FrontendController.php' );
		$service = $this->source( 'src/Application/WorkingGroupService.php' );
		$lifecycle = $this->source( 'src/Application/SpaceLifecycleService.php' );

		self::assertStringContainsString( "'save_working_group_settings' === \$action", $controller );
		self::assertStringContainsString( "WorkingGroupMeta::JOIN_POLICY_REQUEST === \$join_policy", $controller );
		self::assertStringContainsString( 'Bitte wähle eine gültige Beitrittsoption.', $controller );
		self::assertStringContainsString( 'Eine öffentliche Sichtbarkeit ist für Arbeitsgruppen derzeit nicht verfügbar.', $controller );
		self::assertLessThan( strpos( $controller, '$this->space_lifecycle->rename( $space_id' ), strpos( $controller, '$this->working_groups->validate_metadata' ) );
		self::assertLessThan( strpos( $controller, '$this->space_lifecycle->change_visibility( $space_id' ), strpos( $controller, '$this->space_lifecycle->validate_visibility' ) );
		self::assertStringContainsString( 'public function validate_metadata(', $service );
		self::assertStringContainsString( 'public function validate_name(', $lifecycle );
		self::assertStringContainsString( 'public function validate_visibility(', $lifecycle );
		self::assertStringContainsString( "'save_working_group_meta' === \$action", $controller );
		self::assertStringContainsString( "'rename_space' === \$action", $controller );
		self::assertStringContainsString( "'change_space_visibility' === \$action", $controller );
	}
}
