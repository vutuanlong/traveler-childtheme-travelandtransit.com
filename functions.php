<?php
/**
 * Created by PhpStorm.
 * User: MSI
 * Date: 21/08/2015
 * Time: 9:45 SA
 */

add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles', 20 );
function enqueue_parent_styles() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style', get_stylesheet_uri() );

	if ( is_singular( 'st_tours' ) ) {
		wp_enqueue_script( 'st-custom-single-room', get_stylesheet_directory_uri() . '/assets/js/custom-single-tours.js', [], '', true );
		$localize_array = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'_s'       => wp_create_nonce( 'st_frontend_security' ),
		];
		wp_localize_script( 'st-custom-single-room', 'st_params_custom', $localize_array );
	}
}

function register_metabox( $custom_metabox ) {
	/**
	 * Register our meta boxes using the
	 * ot_register_meta_box() function.
	 */
	if ( function_exists( 'ot_register_meta_box' ) ) {
		if ( ! empty( $custom_metabox ) ) {
			foreach ( $custom_metabox as $value ) {
				ot_register_meta_box( $value );
			}
		}
	}
}

function loadFrontInit() {
	require __DIR__ . '/front/class.admin.tours.php';
	require __DIR__ . '/front/class.tours.php';
	require __DIR__ . '/front/price.helper.new.php';
	require __DIR__ . '/front/cart.new.php';
	require __DIR__ . '/front/shortcode.php';
}
add_action( 'init', 'loadFrontInit' );

