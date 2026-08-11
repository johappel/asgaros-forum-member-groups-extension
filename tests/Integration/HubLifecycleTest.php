<?php
/**
 * Integrationstests für den Hub-Lifecycle.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Core\Activator;
use AFSpaces\Core\Uninstaller;
use AFSpaces\Interface\SpacesUrls;

final class HubLifecycleTest extends IntegrationTestCase {

	/** @var int[] */
	private array $created_page_ids = array();

	private int $original_hub_page_id = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->original_hub_page_id = (int) get_option( SpacesUrls::HUB_PAGE_OPTION, 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->created_page_ids as $page_id ) {
			wp_delete_post( $page_id, true );
		}

		if ( $this->original_hub_page_id > 0 ) {
			update_option( SpacesUrls::HUB_PAGE_OPTION, $this->original_hub_page_id );
		} else {
			delete_option( SpacesUrls::HUB_PAGE_OPTION );
		}

		parent::tearDown();
	}

	public function test_stored_hub_page_is_reused_after_title_and_slug_change(): void {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'AFSpaces Lifecycle Test',
				'post_name'    => 'afspaces-lifecycle-test-' . wp_generate_uuid4(),
				'post_content' => '[afspaces]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		$this->assertIsInt( $page_id );
		$this->created_page_ids[] = $page_id;
		update_post_meta( $page_id, SpacesUrls::HUB_MANAGED_META, '1' );
		update_option( SpacesUrls::HUB_PAGE_OPTION, $page_id );

		wp_update_post(
			array(
				'ID'         => $page_id,
				'post_title' => 'Meine Arbeitsgruppen',
				'post_name'  => 'meine-arbeitsgruppen',
			)
		);

		$this->assertSame( $page_id, Activator::ensure_hub_page() );
		$this->assertSame( 'Meine Arbeitsgruppen', get_the_title( $page_id ) );
		$this->assertSame( 'meine-arbeitsgruppen', get_post_field( 'post_name', $page_id ) );
	}

	public function test_deleted_stored_hub_page_is_replaced_and_marked(): void {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'AFSpaces Deleted Hub Test',
				'post_content' => '[afspaces]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		$this->assertIsInt( $page_id );
		$this->created_page_ids[] = $page_id;
		update_post_meta( $page_id, SpacesUrls::HUB_MANAGED_META, '1' );
		update_option( SpacesUrls::HUB_PAGE_OPTION, $page_id );
		wp_delete_post( $page_id, true );

		$replacement = Activator::ensure_hub_page();

		$this->assertGreaterThan( 0, $replacement );
		$this->assertNotSame( $page_id, $replacement );
		$this->assertSame( '1', (string) get_post_meta( $replacement, SpacesUrls::HUB_MANAGED_META, true ) );
		$this->created_page_ids[] = $replacement;
	}

	public function test_foreign_page_is_not_eligible_for_cleanup(): void {
		$page_id = wp_insert_post(
			array(
				'post_title'  => 'Foreign AFSpaces Slug Test',
				'post_name'   => 'foreign-afspaces-slug-test-' . wp_generate_uuid4(),
				'post_status' => 'publish',
				'post_type'   => 'page',
			),
			true
		);
		$this->assertIsInt( $page_id );
		$this->created_page_ids[] = $page_id;

		$this->assertFalse( Uninstaller::is_managed_hub_page( $page_id ) );
		update_post_meta( $page_id, SpacesUrls::HUB_MANAGED_META, '1' );
		$this->assertTrue( Uninstaller::is_managed_hub_page( $page_id ) );
	}
}
