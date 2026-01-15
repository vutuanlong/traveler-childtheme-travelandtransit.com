<?php
if ( ! class_exists( 'STAdminTourNew' ) ) {
	class STAdminTourNew {
		public function __construct() {
			add_action( 'wp_loaded', [ $this, 'after_wp_is_loaded' ] );
		}
		public function after_wp_is_loaded() {
			add_action( 'current_screen', [ $this, 'init_metabox_new' ] );
		}

		public function init_metabox_new() {
			$screen = get_current_screen();
			if ( $screen->id != 'st_tours' ) {
				return false;
			}
			$metabox[] = [
				'id'       => 'tour_metabox_custom',
				'title'    => __( 'Tour Setting Custom', 'traveler-childtheme' ),
				'desc'     => '',
				'pages'    => [ 'st_tours' ],
				'context'  => 'normal',
				'priority' => 'high',
				'fields'   => [
					[
						'label' => __( 'Custom Tour Price', 'traveler-childtheme' ),
						'id'    => 'Custom_tour_price',
						'type'  => 'tab',
					],

					[
						'label'     => __( 'Services package', 'traveler-childtheme' ),
						'std'       => 0,
						'condition' => 'tour_price_by:is(fixed)',
						'type'      => 'list-item',
						'id'        => 'package_list',
						'desc'      => __( 'Attached service package', 'traveler-childtheme' ),
						'settings'  => [
							[
								'id'    => 'package_price_fixed',
								'label' => __( 'Price', 'traveler-childtheme' ),
								'type'  => 'text',
							],
						],
					],
				],
			];

			register_metabox( $metabox );
		}
	}
	new STAdminTourNew;
}
