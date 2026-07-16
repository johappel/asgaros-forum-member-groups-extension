<?php
/**
 * Basis-TestCase für Integrationstests gegen echte WP + Asgaros.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Adapters\Asgaros\AsgarosAdapter;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\Requirements;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceManager;
use AFSpaces\Domain\SpacePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Gemeinsame Fixtures für Integrationstests.
 */
abstract class IntegrationTestCase extends TestCase {

	/**
	 * Test-Kategorie (Asgaros-Forum-Kategorie).
	 *
	 * @var int
	 */
	protected int $category_id = 232;

	/**
	 * Test-Gruppe (Asgaros-Usergroup).
	 *
	 * @var int
	 */
	protected int $group_id = 233;

	/**
	 * Test-Forum (Asgaros-Forum).
	 *
	 * @var int
	 */
	protected int $forum_id = 4249;

	/**
	 * @var SpaceRepository
	 */
	protected SpaceRepository $spaces;

	/**
	 * @var AsgarosAdapter
	 */
	protected AsgarosAdapter $asgaros;

	/**
	 * @var SpacePolicy
	 */
	protected SpacePolicy $policy;

	/**
	 * @var AuditRepository
	 */
	protected AuditRepository $audit;

	/**
	 * @var MemberService
	 */
	protected MemberService $members;

	/**
	 * Setzt die gemeinsamen Objekte auf.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->spaces  = new SpaceRepository();
		$req           = new Requirements();
		$this->asgaros = new AsgarosAdapter( $req );
		$this->policy  = new SpacePolicy( $this->spaces );
		$this->audit   = new AuditRepository();
		$this->members = new MemberService( $this->spaces, $this->asgaros, $this->policy, $this->audit );

		// Tabellen sicherstellen.
		$this->spaces->install();
		$this->audit->install();

		// Admin-Capabilities sicherstellen.
		Capabilities::register();
	}

	/**
	 * Legt einen Test-Space für das Forum an und gibt ihn zurück.
	 *
	 * @param int $owner_user_id Owner-Benutzer-ID.
	 * @return int Space-ID.
	 */
	protected function create_test_space( int $owner_user_id ): int {
		$space = new Space( array(
			'forum_id'         => $this->forum_id,
			'primary_group_id' => $this->group_id,
			'owner_user_id'    => $owner_user_id,
			'visibility'       => 'private',
			'status'           => 'active',
		) );
		$space_id = $this->spaces->create_space( $space );

		$manager = new SpaceManager( array(
			'space_id' => $space_id,
			'user_id'  => $owner_user_id,
			'role'     => SpaceManager::ROLE_OWNER,
		) );
		$this->spaces->add_manager( $manager );

		return $space_id;
	}

	/**
	 * Entfernt einen Benutzer aus der Testgruppe (Aufräumen).
	 *
	 * @param int $user_id Benutzer-ID.
	 * @return void
	 */
	protected function cleanup_user_from_group( int $user_id ): void {
		\AsgarosForumUserGroups::deleteUserGroupsOfUser( $user_id );
	}
}
