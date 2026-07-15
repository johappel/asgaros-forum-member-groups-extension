<?php
/**
 * Plugin Name: Asgaros Forum Spaces
 * Plugin URI:  https://example.com/afspaces
 * Description: Barrierearme Frontend-Verwaltung für Mitglieder, Einladungen und private Forenräume in Asgaros Forum.
 * Version:     0.1.0
 * Author:      AFSpaces
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: afspaces
 * Domain Path: /languages
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Konstanten.
if ( ! defined( 'AFSPACES_FILE' ) ) {
	define( 'AFSPACES_FILE', __FILE__ );
}
if ( ! defined( 'AFSPACES_PATH' ) ) {
	define( 'AFSPACES_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'AFSPACES_URL' ) ) {
	define( 'AFSPACES_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'AFSPACES_VERSION' ) ) {
	define( 'AFSPACES_VERSION', '0.1.0' );
}
if ( ! defined( 'AFSPACES_DB_VERSION' ) ) {
	define( 'AFSPACES_DB_VERSION', 1 );
}
// Minimale Asgaros-Version, gegen die MVP 1 entwickelt wird. Wird in COMPATIBILITY.md präzisiert.
if ( ! defined( 'AFSPACES_MIN_ASGAROS_VERSION' ) ) {
	define( 'AFSPACES_MIN_ASGAROS_VERSION', '3.0.0' );
}

require_once AFSPACES_PATH . 'includes/autoloader.php';
require_once AFSPACES_PATH . 'includes/functions.php';

// Plugin-Lebenszyklus registrieren.
register_activation_hook( AFSPACES_FILE, array( 'AFSpaces\\Core\\Activator', 'activate' ) );
register_deactivation_hook( AFSPACES_FILE, array( 'AFSpaces\\Core\\Deactivator', 'deactivate' ) );
register_uninstall_hook( AFSPACES_FILE, array( 'AFSpaces\\Core\\Uninstaller', 'uninstall' ) );

// Plugin starten, sobald alle Basis-Plugins geladen sind.
add_action( 'plugins_loaded', array( 'AFSpaces\\Plugin', 'init' ) );
