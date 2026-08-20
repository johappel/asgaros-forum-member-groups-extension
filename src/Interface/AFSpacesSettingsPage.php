<?php
/**
 * Zentrale WordPress-Adminseite für AFSpaces-Einstellungen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\AFSpacesSettingsPage' ) ) {

	/**
	 * Bündelt die AFSpaces-Optionsseiten unter dem Asgaros-Adminmenü.
	 */
	final class AFSpacesSettingsPage {

		public const PAGE_SLUG = 'afspaces-settings';

		private const PARENT_SLUG = 'asgarosforum-structure';

		private const TAB_PARAM = 'tab';

		/**
		 * @var array<string,string>
		 */
		private const LEGACY_TABS = array(
			'afspaces-appearance'    => 'appearance',
			'afspaces-look-and-feel' => 'appearance',
			'afspaces-creation'      => 'creation',
			'afspaces-search'        => 'search',
			'afspaces-installation'  => 'extras',
		);

		private AppearanceSettingsPage $appearance;
		private SpaceCreationSettingsPage $creation;
		private SearchSettingsPage $search;
		private InstallationSettingsPage $installation;

		public function __construct(
			AppearanceSettingsPage $appearance,
			SpaceCreationSettingsPage $creation,
			SearchSettingsPage $search,
			InstallationSettingsPage $installation
		) {
			$this->appearance = $appearance;
			$this->creation = $creation;
			$this->search = $search;
			$this->installation = $installation;
		}

		/**
		 * Registriert Menü und Legacy-Weiterleitungen.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
			add_action( 'admin_init', array( $this, 'redirect_legacy_pages' ), 1 );
		}

		/**
		 * Registriert AFSpaces als Untermenü von Asgaros Forum.
		 *
		 * @return void
		 */
		public function register_menu(): void {
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Arbeitsgruppen-Einstellungen', 'afspaces' ),
				__( 'Arbeitsgruppen', 'afspaces' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
		}

		/**
		 * Leitet alte direkte Optionsseiten auf den passenden Tab um.
		 *
		 * @return void
		 */
		public function redirect_legacy_pages(): void {
			if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
			if ( ! isset( self::LEGACY_TABS[ $page ] ) ) {
				return;
			}

			$url = add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					self::TAB_PARAM => self::LEGACY_TABS[ $page ],
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $url, 301 );
			exit;
		}

		/**
		 * Rendert die zentrale Seite mit nativen WordPress-Tabs.
		 *
		 * @return void
		 */
		public function render_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$tab = $this->current_tab();
			$tabs = $this->tabs();
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Arbeitsgruppen-Einstellungen', 'afspaces' ); ?></h1>
				<p class="description">
					<?php echo esc_html__( 'Zentrale Einstellungen für die Frontend-Verwaltung von Arbeitsgruppen, Einladungen und Suche.', 'afspaces' ); ?>
				</p>

				<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Arbeitsgruppen-Einstellungsbereiche', 'afspaces' ); ?>">
					<?php foreach ( $tabs as $key => $label ) : ?>
						<a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->tab_url( $key ) ); ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>

				<?php settings_errors(); ?>

				<section aria-labelledby="afspaces-settings-section-title">
					<h2 id="afspaces-settings-section-title" class="screen-reader-text"><?php echo esc_html( $tabs[ $tab ] ); ?></h2>
					<?php $this->render_tab( $tab ); ?>
				</section>
			</div>
			<?php
		}

		/**
		 * @return array<string,string>
		 */
		private function tabs(): array {
			return array(
				'appearance'   => __( 'Darstellung', 'afspaces' ),
				'creation'     => __( 'Raumgründung', 'afspaces' ),
				'search'       => __( 'Suche', 'afspaces' ),
				'extras'       => __( 'Extras', 'afspaces' ),
			);
		}

		/**
		 * @return string
		 */
		private function current_tab(): string {
			$tab = isset( $_GET[ self::TAB_PARAM ] ) ? sanitize_key( (string) $_GET[ self::TAB_PARAM ] ) : 'appearance';
			if ( 'installation' === $tab ) {
				$tab = 'extras';
			}
			return array_key_exists( $tab, $this->tabs() ) ? $tab : 'appearance';
		}

		/**
		 * @param string $tab Tab-Schlüssel.
		 * @return string
		 */
		private function tab_url( string $tab ): string {
			return add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					self::TAB_PARAM => $tab,
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * @param string $tab Tab-Schlüssel.
		 * @return void
		 */
		private function render_tab( string $tab ): void {
			switch ( $tab ) {
				case 'creation':
					$this->creation->render_page( true );
					break;
				case 'search':
					$this->search->render_page( true );
					break;
				case 'extras':
					$this->installation->render_page( true );
					break;
				case 'appearance':
				default:
					$this->appearance->render_page( true );
					break;
			}
		}
	}
}
