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
		$visibility_source = $this->source( 'src/Interface/CreateSpaceView.php' );

		self::assertSame( 1, substr_count( $source, 'value="save_working_group_settings"' ) );
		self::assertStringContainsString( 'Änderungen speichern', $source );
		self::assertStringNotContainsString( 'Arbeitsgruppe bearbeiten', $source );
		self::assertStringNotContainsString( 'Hier bearbeitest du die gemeinsamen Einstellungen deiner Arbeitsgruppe.', $source );
		self::assertStringNotContainsString( 'afspaces-working-group-details-heading', $source );
		self::assertStringContainsString( 'Worum geht es?', $source );
		self::assertStringContainsString( 'Wie können andere mit der Gruppe Kontakt aufnehmen?', $source );
		self::assertStringContainsString( 'Darstellung', $source );
		self::assertStringContainsString( 'Zugang und Mitgliedschaft', $source );
		self::assertStringContainsString( 'Wer darf die Beiträge dieser Arbeitsgruppe lesen?', $source );
		self::assertStringContainsString( 'Lesen', $source );
		self::assertStringContainsString( 'visibility_label( $visibility )', $source );
		self::assertStringContainsString( 'Nur Mitglieder der Arbeitsgruppe', $visibility_source );
		self::assertStringContainsString( 'Alle angemeldeten Personen', $visibility_source );
		self::assertStringContainsString( 'Sie werden dadurch nicht automatisch Mitglieder der Arbeitsgruppe.', $visibility_source );
		self::assertStringContainsString( 'Schreiben können nur Personen mit den dafür vorgesehenen Rechten.', $visibility_source );
		self::assertStringContainsString( 'Neue Mitglieder können nur von berechtigten Personen eingeladen werden.', $source );
		self::assertStringContainsString( 'Die Arbeitsgruppe nimmt derzeit keine weiteren Mitglieder auf.', $source );
		self::assertStringContainsString( 'Angemeldete Personen können eine Mitgliedschaft anfragen.', $source );
		self::assertStringContainsString( 'Wer kann Mitglied werden?', $source );
		self::assertStringContainsString( 'Mitgliedschaft', $source );
		self::assertStringContainsString( 'name="visibility"', $source );
		self::assertStringContainsString( 'name="join_policy"', $source );
		self::assertStringContainsString( 'aria-describedby', $source );
		self::assertStringNotContainsString( 'Wer kann das Forum sehen?', $source );
		self::assertStringNotContainsString( 'Privat (nur Mitglieder)', $source );
		self::assertStringNotContainsString( 'Geschützt (alle angemeldeten Personen)', $source );
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
