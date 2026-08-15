<?php
/**
 * Unit-Tests für den Link vom AFSpaces-Profil zum Mitgliederprofil.
 *
 * @package AFSpacesTests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\UserIdentityService;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Interface\ProfileView;
use PHPUnit\Framework\TestCase;

final class ProfileViewTest extends TestCase {

	protected function setUp(): void {
		global $afspaces_test_filters, $afspaces_test_users, $afspaces_test_current_user_id;
		$afspaces_test_filters          = array();
		$afspaces_test_current_user_id = 7;
		$afspaces_test_users            = array(
			7 => (object) array(
				'ID'           => 7,
				'display_name' => 'Simone Wustrack',
				'user_login'   => 'simone',
			),
			8 => (object) array(
				'ID'           => 8,
				'display_name' => 'Andere Person',
				'user_login'   => 'andere-person',
			),
		);
	}

	protected function tearDown(): void {
		global $afspaces_test_filters, $afspaces_test_users;
		$afspaces_test_filters = array();
		$afspaces_test_users   = array();
	}

	public function test_own_profile_link_is_rendered_before_empty_group_state(): void {
		$this->add_profile_url_filter( 'https://example.test/profil/simone-wustrack/' );

		$html = $this->view()->render( 7 );

		$this->assertStringContainsString( '<a href="https://example.test/profil/simone-wustrack/">', $html );
		$this->assertStringContainsString( 'Zu meinem Mitgliederprofil', $html );
		$this->assertLessThan( strpos( $html, 'afspaces-empty' ), strpos( $html, 'afspaces-profile-member-link' ) );
	}

	public function test_foreign_profile_uses_name_in_member_profile_link(): void {
		$this->add_profile_url_filter( 'https://example.test/profil/andere-person/' );

		$html = $this->view()->render( 8 );

		$this->assertStringContainsString( 'Zum Mitgliederprofil von Andere Person', $html );
		$this->assertStringContainsString( 'href="https://example.test/profil/andere-person/"', $html );
	}

	public function test_empty_profile_url_does_not_render_placeholder_link(): void {
		$html = $this->view()->render( 7 );

		$this->assertStringNotContainsString( 'afspaces-profile-member-link', $html );
		$this->assertStringNotContainsString( 'Mitgliederprofil', $html );
	}

	private function view(): ProfileView {
		$spaces = $this->createMock( SpaceRepository::class );
		$spaces->method( 'list_spaces' )->willReturn( array() );

		return new ProfileView(
			$spaces,
			$this->createMock( AsgarosAdapterInterface::class ),
			$this->createMock( WorkingGroupService::class ),
			new UserIdentityService()
		);
	}

	private function add_profile_url_filter( string $url ): void {
		global $afspaces_test_filters;
		$afspaces_test_filters['afspaces_user_profile_url'][] = static function () use ( $url ): string {
			return $url;
		};
	}
}
