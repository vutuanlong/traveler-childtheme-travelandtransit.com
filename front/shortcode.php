<?php
// Shortcode Email

if ( ! function_exists( 'st_email_booking_custom_package' ) ) {
	function st_email_booking_custom_package() {
		global $order_id;
		if ( $order_id ) {
			$post_id         = get_post_meta( $order_id, 'item_id', true );
			$post_type       = get_post_type( $post_id );
			$value_cart_info = get_post_meta( $order_id, 'st_cart_info', true );
			$value           = $value_cart_info[ $post_id ];
			$tour_id         = $value['data']['st_booking_id'];
			$package_select  = isset( $value['data']['package_name'] ) ? $value['data']['package_name'] : '';
			$price_ori       = 0;

			if ( $post_type !== 'st_tours' ) {
				return;
			}

			if ( get_post_meta( $tour_id, 'tour_price_by', true ) == 'fixed' && ! empty( $package_select ) ) {
				$people_price_package = STPriceNew::getPeoplePriceByPackage( $post_id, $package_select );
				$price_ori            = $people_price_package['package_price_fixed'];
			}

			if ( ! empty( $people_price_package ) ) {
				return '<table style="margin-left: -3px;">
						<tr>
							<td style="padding-top: 10px;">
							<strong>' . __( 'Package', 'traveler-childtheme' ) . ': </strong>
						</td>
                        <tr>
                            <td style="padding-top: 10px;">
                                ' . $package_select . '
                            </td>
							<td style="padding-top: 10px;">
								' . TravelHelper::format_money( $price_ori ) . '
							</td>
                        </tr>
                    </table>';
			}
		}
		return '';
	}
}
st_reg_shortcode( 'st_email_booking_custom_package', 'st_email_booking_custom_package' );
