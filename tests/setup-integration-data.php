<?php
/**
 * Richtet Testdaten in der echten WP Local + Asgaros-Instanz ein.
 *
 * Legt eine Test-Kategorie mit Forum und zugeordneter Gruppe an,
 * sofern noch nicht vorhanden. Idempotent.
 *
 * Aufruf:
 *   php -c tests/php-cli.ini tests/setup-integration-data.php
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

$wp_load = 'C:\\Users\\Joachim\\Local Sites\\forums\\app\\public\\wp-load.php';
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', '127.0.0.1:10016' );
}
require_once $wp_load;

if ( ! class_exists( 'AsgarosForum' ) ) {
	fwrite( STDERR, "Asgaros Forum ist nicht aktiv.\n" );
	exit( 1 );
}

global $asgarosforum;
$cat_name  = 'AFSpaces Testkategorie';
$forum_name = 'AFSpaces Testforum';

// Kategorie suchen/erstellen (Term in Taxonomie asgarosforum-category).
$existing = get_terms( array(
	'taxonomy'   => 'asgarosforum-category',
	'name'       => $cat_name,
	'hide_empty' => false,
) );

if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
	$category = $existing[0];
	echo "Kategorie existiert bereits: {$category->term_id}\n";
} else {
	$category = wp_insert_term( $cat_name, 'asgarosforum-category' );
	if ( is_wp_error( $category ) ) {
		fwrite( STDERR, 'Kategorie-Fehler: ' . $category->get_error_message() . "\n" );
		exit( 1 );
	}
	echo "Kategorie erstellt: {$category['term_id']}\n";
}
$category_id = (int) ( is_array( $category ) ? $category['term_id'] : $category->term_id );

// Gruppe erstellen (Term in Taxonomie asgarosforum-usergroup, parent = Kategorie).
$group_name = 'AFSpaces Testgruppe';
$existing_group = get_terms( array(
	'taxonomy'   => 'asgarosforum-usergroup',
	'name'       => $group_name,
	'hide_empty' => false,
) );

if ( ! empty( $existing_group ) && ! is_wp_error( $existing_group ) ) {
	$group = $existing_group[0];
	echo "Gruppe existiert bereits: {$group->term_id}\n";
} else {
	$group = wp_insert_term( $group_name, 'asgarosforum-usergroup', array( 'parent' => $category_id ) );
	if ( is_wp_error( $group ) ) {
		fwrite( STDERR, 'Gruppen-Fehler: ' . $group->get_error_message() . "\n" );
		exit( 1 );
	}
	echo "Gruppe erstellt: {$group['term_id']}\n";
}
$group_id = (int) ( is_array( $group ) ? $group['term_id'] : $group->term_id );

// Gruppe der Kategorie zuordnen (term_meta 'usergroups').
update_term_meta( $category_id, 'usergroups', array( $group_id ) );
echo "Gruppe {$group_id} der Kategorie {$category_id} zugeordnet.\n";

// Forum erstellen (in der Asgaros-eigenen Tabelle wp_forum_forums,
// parent_id = Kategorie-ID). Asgaros speichert Foren NICHT als WP-Posts.
global $asgarosforum;
$existing_forum = $asgarosforum->db->get_row(
	$asgarosforum->db->prepare(
		"SELECT id FROM {$asgarosforum->tables->forums} WHERE name = %s AND parent_id = %d LIMIT 1;",
		$forum_name,
		$category_id
	),
	ARRAY_A
);

if ( ! empty( $existing_forum ) ) {
	$forum_id = (int) $existing_forum['id'];
	echo "Forum existiert bereits: {$forum_id}\n";
} else {
	$forum_id = $asgarosforum->content->insert_forum(
		$category_id,
		$forum_name,
		'Testforum für AFSpaces',
		0,
		'fas fa-comments',
		1,
		'normal'
	);
	echo "Forum erstellt: {$forum_id}\n";
}

echo "\nTestdaten bereit:\n";
echo "  Kategorie: {$category_id}\n";
echo "  Gruppe:    {$group_id}\n";
echo "  Forum:     {$forum_id}\n";
echo "  (diese IDs in tests/Integration/IntegrationTestCase.php eintragen)\n";
