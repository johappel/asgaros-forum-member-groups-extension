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

// Forum erstellen (Post vom Typ asgarosforum_forum, parent_id = Kategorie).
$existing_forum = get_posts( array(
	'post_type'   => 'asgarosforum_forum',
	'title'       => $forum_name,
	'numberposts' => 1,
) );

if ( ! empty( $existing_forum ) ) {
	$forum = $existing_forum[0];
	echo "Forum existiert bereits: {$forum->ID}\n";
} else {
	$forum_id = wp_insert_post( array(
		'post_type'    => 'asgarosforum_forum',
		'post_title'   => $forum_name,
		'post_status'  => 'publish',
		'post_parent'  => 0,
		'menu_order'   => 1,
	) );
	// Asgaros speichert die Kategorie-Zuordnung in post_parent_id (eigene Spalte).
	global $wpdb;
	$wpdb->update(
		$wpdb->posts,
		array( 'post_parent' => $category_id ),
		array( 'ID' => $forum_id ),
		array( '%d' ),
		array( '%d' )
	);
	echo "Forum erstellt: {$forum_id}\n";
	$forum = get_post( $forum_id );
}
$forum_id = (int) $forum->ID;

echo "\nTestdaten bereit:\n";
echo "  Kategorie: {$category_id}\n";
echo "  Gruppe:    {$group_id}\n";
echo "  Forum:     {$forum_id}\n";
echo "  (diese IDs in tests/Integration/IntegrationTestCase.php eintragen)\n";
