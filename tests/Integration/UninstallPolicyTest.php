<?php
/**
 * Integrationstest für den nicht-destruktiven Default der Deinstallation.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Core\Uninstaller;

final class UninstallPolicyTest extends IntegrationTestCase {

	public function test_uninstall_without_opt_in_keeps_afspaces_options(): void {
		$cleanup_before = get_option( 'afspaces_cleanup_on_uninstall', null );
		$sentinel_before = get_option( 'afspaces_uninstall_policy_test', null );

		update_option( 'afspaces_cleanup_on_uninstall', false );
		update_option( 'afspaces_uninstall_policy_test', 'preserve-me' );

		try {
			Uninstaller::uninstall();
			$this->assertSame( 'preserve-me', get_option( 'afspaces_uninstall_policy_test' ) );
		} finally {
			if ( null === $cleanup_before ) {
				delete_option( 'afspaces_cleanup_on_uninstall' );
			} else {
				update_option( 'afspaces_cleanup_on_uninstall', $cleanup_before );
			}
			if ( null === $sentinel_before ) {
				delete_option( 'afspaces_uninstall_policy_test' );
			} else {
				update_option( 'afspaces_uninstall_policy_test', $sentinel_before );
			}
		}
	}
}
