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
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InvitationService;
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
	protected int $forum_id = 5;

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
	 * @var InvitationRepository
	 */
	protected InvitationRepository $invitation_repository;

	/**
	 * @var InvitationService
	 */
	protected InvitationService $invitation_service;

	/**
	 * In diesem Testlauf angelegte Space-IDs.
	 *
	 * @var int[]
	 */
	private array $created_space_ids = array();

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
		$this->invitation_repository = new InvitationRepository();
		$this->members = new MemberService( $this->spaces, $this->asgaros, $this->policy, $this->audit );
		$this->invitation_service = new InvitationService(
			$this->spaces,
			$this->invitation_repository,
			$this->asgaros,
			$this->policy,
			$this->audit
		);

		// Tabellen sicherstellen.
		$this->spaces->install();
		$this->audit->install();
		$this->invitation_repository->install();

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
		$forum_id = $this->forum_id + count( $this->created_space_ids );

		$space = new Space( array(
			'forum_id'         => $forum_id,
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
		$this->created_space_ids[] = $space_id;

		return $space_id;
	}

	/**
	 * Räumt pro Testlauf erzeugte Datensätze wieder auf.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( ! empty( $this->created_space_ids ) ) {
			global $wpdb;
			$space_ids = array_values( array_unique( array_map( 'intval', $this->created_space_ids ) ) );
			$placeholders = implode( ', ', array_fill( 0, count( $space_ids ), '%d' ) );

			$tables = array(
				$wpdb->prefix . 'afspaces_invitations',
				$wpdb->prefix . 'afspaces_audit',
				$wpdb->prefix . 'afspaces_space_managers',
			);

			foreach ( $tables as $table ) {
				$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				if ( $exists !== $table ) {
					continue;
				}

				$sql = $wpdb->prepare( "DELETE FROM {$table} WHERE space_id IN ({$placeholders});", $space_ids );
				$wpdb->query( $sql );
			}

			$space_sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}afspaces_spaces WHERE id IN ({$placeholders});", $space_ids );
			$wpdb->query( $space_sql );
		}

		$this->created_space_ids = array();
		parent::tearDown();
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
