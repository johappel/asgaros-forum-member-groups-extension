<?php
/**
 * Unit-Tests für die erweiterbare Benutzeridentität.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Application\UserIdentityService;
use PHPUnit\Framework\TestCase;

final class UserIdentityServiceTest extends TestCase {

	protected function setUp(): void {
		global $afspaces_test_filters, $afspaces_test_users;
		$afspaces_test_filters = array();
		$afspaces_test_users   = array(
			7 => (object) array(
				'ID'           => 7,
				'display_name' => 'WordPress Name',
				'user_login'   => 'wp-login',
			),
			8 => (object) array(
				'ID'           => 8,
				'display_name' => 'Second Name',
				'user_login'   => 'second-login',
			),
		);
	}

	protected function tearDown(): void {
		global $afspaces_test_filters, $afspaces_test_users;
		$afspaces_test_filters = array();
		$afspaces_test_users   = array();
	}

	public function test_uses_asgaros_name_filter_and_afspaces_override(): void {
		global $afspaces_test_filters;
		$afspaces_test_filters['asgarosforum_filter_username'][] = static function ( string $name ): string {
			return 'Efabi Name';
		};
		$afspaces_test_filters['afspaces_user_display_name'][] = static function ( string $name ): string {
			return $name . ' (extern)';
		};

		$service = new UserIdentityService();

		$this->assertSame( 'Efabi Name (extern)', $service->get_display_name( 7 ) );
	}

	public function test_avatar_hook_is_sanitized_into_img_markup(): void {
		global $afspaces_test_filters;
		$afspaces_test_filters['afspaces_user_avatar_url'][] = static function (): string {
			return 'https://profiles.example/avatar.jpg?size=40';
		};

		$html = ( new UserIdentityService() )->get_avatar_html( 7, 40 );

		$this->assertStringContainsString( 'src="https://profiles.example/avatar.jpg?size=40"', $html );
		$this->assertStringContainsString( 'alt="WordPress Name"', $html );
		$this->assertStringContainsString( 'class="avatar"', $html );
	}

	public function test_external_search_results_are_deduplicated_and_keep_user_ids(): void {
		global $afspaces_test_filters;
		$afspaces_test_filters['afspaces_user_search_results'][] = static function ( array $result ): array {
			$result['user_ids'] = array( 7, 7, 8 );
			$result['total']    = 2;
			return $result;
		};

		$result = ( new UserIdentityService() )->search_users( 'Efabi', 1, 20 );

		$this->assertSame( 2, $result['total'] );
		$this->assertSame( array( 7, 8 ), array_column( $result['members'], 'user_id' ) );
		$this->assertSame( 'WordPress Name', $result['members'][0]['display_name'] );
	}

	public function test_search_paginates_after_merging_the_candidate_window(): void {
		global $afspaces_test_filters;
		$afspaces_test_filters['afspaces_user_search_results'][] = static function ( array $result, string $term, int $page, int $per_page, int $candidate_limit ): array {
			$result['user_ids'] = array( 8, 7 );
			$result['total']    = 2;
			return $result;
		};

		$result = ( new UserIdentityService() )->search_users( 'Name', 2, 1 );

		$this->assertSame( array( 7 ), array_column( $result['members'], 'user_id' ) );
		$this->assertSame( 2, $result['total'] );
	}
}
