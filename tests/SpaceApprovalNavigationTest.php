<?php
/**
 * Unit-Tests für die Sichtbarkeit offener Freigaben in der Navigation.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceLifecycleService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Domain\SpaceLifecycle;
use PHPUnit\Framework\TestCase;

/**
 * Repository-Stub für die zustandsabhängige Freigabezählung.
 */
final class StubApprovalRepository extends SpaceRepository {

	public int $pending_count = 0;

	public function __construct() {}

	public function count_spaces_by_status( string $status ): int {
		return SpaceLifecycle::STATUS_PENDING === $status ? $this->pending_count : 0;
	}
}

/**
 * Prüft die serverseitige Entscheidungsgrundlage für den Navigations-Button.
 */
final class SpaceApprovalNavigationTest extends TestCase {

	private StubApprovalRepository $repository;

	private SpaceLifecycleService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new StubApprovalRepository();
		$this->service    = new SpaceLifecycleService(
			$this->repository,
			$this->createMock( AsgarosAdapterInterface::class ),
			$this->createMock( SpaceMetaRepository::class ),
			$this->createMock( AuditRepository::class )
		);

		global $afspaces_user_can_callback;
		$afspaces_user_can_callback = static function ( int $user_id, string $capability ): bool {
			return 42 === $user_id && Capabilities::MODERATE_SPACE === $capability;
		};
	}

	public function test_user_without_approval_management_right_sees_no_button(): void {
		$this->repository->pending_count = 3;

		$this->assertSame( 0, $this->service->count_pending_for_actor( 7 ) );
	}

	public function test_authorized_user_without_open_approval_sees_no_button(): void {
		$this->repository->pending_count = 0;

		$this->assertSame( 0, $this->service->count_pending_for_actor( 42 ) );
	}

	public function test_authorized_user_with_one_open_approval_sees_button(): void {
		$this->repository->pending_count = 1;

		$this->assertSame( 1, $this->service->count_pending_for_actor( 42 ) );
	}

	public function test_multiple_open_approvals_produce_the_correct_counter(): void {
		$this->repository->pending_count = 2;

		$this->assertSame( 2, $this->service->count_pending_for_actor( 42 ) );
	}

	public function test_completed_approval_reduces_counter_to_zero(): void {
		$this->repository->pending_count = 1;
		$this->assertSame( 1, $this->service->count_pending_for_actor( 42 ) );

		$this->repository->pending_count = 0;

		$this->assertSame( 0, $this->service->count_pending_for_actor( 42 ) );
	}

	public function test_pending_list_uses_the_same_permission_check(): void {
		$this->expectException( \AFSpaces\Core\DomainException::class );

		$this->service->list_pending( 7 );
	}
}
