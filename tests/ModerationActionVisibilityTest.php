<?php
/**
 * Tests für die aktionsbezogene Moderations-UI-Deduplizierung.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Application\ModerationActionVisibility;
use PHPUnit\Framework\TestCase;

final class ModerationActionVisibilityTest extends TestCase {

	/**
	 * @dataProvider visibility_matrix
	 */
	public function test_visibility_uses_local_and_native_action_permissions( bool $local_allowed, bool $native_allowed, bool $expected ): void {
		$adapter = $this->createMock( AsgarosAdapterInterface::class );
		$adapter->method( 'can_perform_moderation_action' )->willReturn( $native_allowed );

		$visibility = new ModerationActionVisibility( $adapter );

		self::assertSame(
			$expected,
			$visibility->should_render_local_action(
				AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_DELETE,
				$local_allowed,
				42,
				99
			)
		);
	}

	/**
	 * @return array<string,array{bool,bool,bool}>
	 */
	public static function visibility_matrix(): array {
		return array(
			'normaler Benutzer'              => array( false, false, false ),
			'Arbeitsgruppenverantwortlicher' => array( true, false, true ),
			'globaler Moderator'             => array( true, true, false ),
			'Administrator'                  => array( true, true, false ),
			'nativ ohne AFSpaces-Recht'      => array( false, true, false ),
		);
	}

	public function test_topic_and_post_actions_are_compared_separately(): void {
		$adapter = $this->createMock( AsgarosAdapterInterface::class );
		$adapter->method( 'can_perform_moderation_action' )->willReturnCallback(
			static function ( string $action ): bool {
				return AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_DELETE === $action;
			}
		);

		$visibility = new ModerationActionVisibility( $adapter );

		self::assertFalse(
			$visibility->should_render_local_action(
				AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_DELETE,
				true,
				42,
				99
			)
		);
		self::assertTrue(
			$visibility->should_render_local_action(
				AsgarosAdapterInterface::MODERATION_ACTION_POST_DELETE,
				true,
				42,
				99,
				123
			)
		);
	}

	public function test_post_move_remains_visible_because_asgaros_has_no_equivalent(): void {
		$adapter = $this->createMock( AsgarosAdapterInterface::class );
		$adapter->expects( self::once() )
			->method( 'can_perform_moderation_action' )
			->with( AsgarosAdapterInterface::MODERATION_ACTION_POST_MOVE, 42, 99, 123 )
			->willReturn( false );

		$visibility = new ModerationActionVisibility( $adapter );

		self::assertTrue(
			$visibility->should_render_local_action(
				AsgarosAdapterInterface::MODERATION_ACTION_POST_MOVE,
				true,
				42,
				99,
				123
			)
		);
	}

	public function test_each_supported_action_is_checked_independently(): void {
		$actions = array(
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_PIN,
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_CLOSE,
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_OPEN,
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_DELETE,
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_MOVE,
			AsgarosAdapterInterface::MODERATION_ACTION_POST_DELETE,
			AsgarosAdapterInterface::MODERATION_ACTION_POST_MOVE,
		);
		$native_actions = array(
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_PIN,
			AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_CLOSE,
			AsgarosAdapterInterface::MODERATION_ACTION_POST_DELETE,
		);

		$adapter = $this->createMock( AsgarosAdapterInterface::class );
		$adapter->method( 'can_perform_moderation_action' )->willReturnCallback(
			static function ( string $action ) use ( $native_actions ): bool {
				return in_array( $action, $native_actions, true );
			}
		);
		$visibility = new ModerationActionVisibility( $adapter );

		foreach ( $actions as $action ) {
			self::assertSame(
				! in_array( $action, $native_actions, true ),
				$visibility->should_render_local_action( $action, true, 42, 99, 123 ),
				$action
			);
		}
	}
}
