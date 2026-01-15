<?php
/**
 * @package    WordPress
 * @subpackage Traveler
 * @since      1.0
 *
 * Class STTour
 *
 * Created by ShineTheme
 */
if ( ! class_exists( 'STTourNew' ) ) {
	class STTourNew {

		function __construct() {
			add_action( 'wp_loaded', [ $this, 'after_wp_is_loaded' ] );
		}

		/**
		 *
		 *
		 * @update 1.1.3
		 * */
		function after_wp_is_loaded() {
			// tour booking form ajax
			remove_all_actions( 'wp_ajax_tours_add_to_cart' );
			remove_all_actions( 'wp_ajax_nopriv_tours_add_to_cart' );

			// tour booking form ajax
			add_action( 'wp_ajax_tours_add_to_cart', [ $this, 'ajax_tours_add_to_cart_new' ] );
			add_action( 'wp_ajax_nopriv_tours_add_to_cart', [ $this, 'ajax_tours_add_to_cart_new' ] );

			// Change price ajax
			remove_all_actions( 'wp_ajax_st_format_tour_price' );
			remove_all_actions( 'wp_ajax_nopriv_st_format_tour_price' );
			add_action( 'wp_ajax_st_format_tour_price', [ $this, 'st_format_tour_price_new' ] );
			add_action( 'wp_ajax_nopriv_st_format_tour_price', [ $this, 'st_format_tour_price_new' ] );
		}


		public function st_format_tour_price_new() {
			$pass_validate = true;
			$item_id       = STInput::request( 'item_id', '' );
			ob_start();
			?>
			<span
				class="label">
				<?php echo esc_html__( 'from', 'traveler-childtheme' ) ?>
			</span>
			<span
				class="value">
				<?php
				echo STTour::get_price_html( $item_id );
				?>
			</span>
			<?php
				$price_from = ob_get_contents();
				ob_clean();
				ob_end_flush();
			?>
			<?php
			if ( $item_id <= 0 || get_post_type( $item_id ) != 'st_tours' ) {
				wp_send_json([
					'message'    => __( 'This tour is not available.', 'traveler-childtheme' ),
					'price_from' => $price_from,
				]);
				die();
			}
			$tour_origin   = TravelHelper::post_origin( $item_id, 'st_tours' );
			$tour_price_by = get_post_meta( $tour_origin, 'tour_price_by', true );
			$number        = 1;
			$adult_number  = intval( STInput::request( 'adult_number', 0 ) );
			$child_number  = intval( STInput::request( 'child_number', 0 ) );
			$infant_number = intval( STInput::request( 'infant_number', 0 ) );

			// custom add
			$package_name         = trim( STInput::request( 'package_select', '' ) );
			$data['package_name'] = $package_name;

			$starttime             = STInput::request( 'starttime_tour', '' );
			$data['adult_number']  = $adult_number;
			$data['child_number']  = $child_number;
			$data['infant_number'] = $infant_number;
			$data['starttime']     = $starttime;
			$min_number            = intval( get_post_meta( $item_id, 'min_people', true ) );
			if ( $min_number <= 0 ) {
				$min_number = 1;
			}
			$max_number         = intval( get_post_meta( $item_id, 'max_people', true ) );
			$type_tour          = get_post_meta( $item_id, 'type_tour', true );
			$data['type_tour']  = $type_tour;
			$data['price_type'] = STTour::get_price_type( $item_id );
			$today              = date( 'Y-m-d' );
			$check_in           = TravelHelper::convertDateFormat( STInput::request( 'check_in', '' ) );
			$check_out          = TravelHelper::convertDateFormat( STInput::request( 'check_out', '' ) );

			if ( ! $adult_number and ! $child_number and ! $infant_number ) {
				wp_send_json([
					'message'    => __( 'Please select at least one person.', 'traveler-childtheme' ),
					'price_from' => $price_from,
				]);
				die();
			}
			if ( $adult_number + $child_number + $infant_number < $min_number ) {
				wp_send_json([
					'message'    => sprintf( __( 'Min number of people for this tour is %d people', 'traveler-childtheme' ), $min_number ),
					'price_from' => $price_from,
				]);
				die();
			}
			/**
			 * @since 1.2.8
			 *        Only check limit people when max_people > 0 (unlimited)
			 * */
			if ( $max_number > 0 ) {
				if ( $adult_number + $child_number + $infant_number > $max_number ) {
					wp_send_json([
						'message'    => sprintf( __( 'Max of people for this tour is %d people', 'traveler-childtheme' ), $max_number ),
						'price_from' => $price_from,
					]);
					die();
				}
			}
			if ( ! $check_in || ! $check_out ) {
				wp_send_json( [ 'message' => __( 'Select a day in the calendar.', 'traveler-childtheme' ) ] );
				die();
			}
			if ( $tour_price_by != 'fixed_depart' ) {
				$compare = TravelHelper::dateCompare( $today, $check_in );
				if ( $compare < 0 ) {
					wp_send_json( [ 'message' => __( 'This tour has expired', 'traveler-childtheme' ) ] );
					die();
				}
			}
			$booking_period = intval( get_post_meta( $item_id, 'tours_booking_period', true ) );
			$period         = STDate::dateDiff( $today, $check_in );
			if ( $period < $booking_period ) {
				wp_send_json([
					'message'    => sprintf( __( 'This tour allow minimum booking is %d day(s)', 'traveler-childtheme' ), $booking_period ),
					'price_from' => $price_from,
				]);
				die();
			}
			if ( $tour_price_by != 'fixed_depart' ) {
				$tour_available = TourHelper::checkAvailableTour( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );

				if ( ! $tour_available ) {
					wp_send_json([
						'message'    => __( 'The check in, check out day is invalid or this tour not available.', 'traveler-childtheme' ),
						'price_from' => $price_from,
					]);
					die();
				}
			}
			if ( $tour_price_by != 'fixed_depart' ) {
				if ( $max_number > 0 ) {
					$free_people = $max_number;
					if ( empty( trim( $starttime ) ) ) {
						$result = TourHelper::_get_free_peple( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
					} else {
						$result = TourHelper::_get_free_peple_by_time( $tour_origin, strtotime( $check_in ), strtotime( $check_out ), $starttime );
					}
					if ( $tour_price_by == 'fixed' ) {
						if ( ! empty( $result ) && ! empty( trim( $starttime ) ) ) {
							wp_send_json([
								'message'    => sprintf( __( 'This tour is not available.', 'traveler-childtheme' ) ),
								'price_from' => $price_from,
							]);
						}
					}
					if ( is_array( $result ) && count( $result ) ) {
						$free_people = intval( $result['free_people'] );
					}

					/**
					 * @since 1.2.8
					 *        Only check limit people when max_people > 0 (unlimited)
					 * */
					if ( $free_people < ( $adult_number + $child_number + $infant_number ) ) {
						if ( empty( trim( $starttime ) ) ) {
							wp_send_json([
								'message'    => sprintf( __( 'This tour is only available for %d people', 'traveler-childtheme' ), $free_people ),
								'price_from' => $price_from,
							]);
						} else {
							wp_send_json([
								'message'    => sprintf( __( 'This tour is only available for %1$d people at %2$s', 'traveler-childtheme' ), $free_people, $starttime ),
								'price_from' => $price_from,
							]);
						}
						$pass_validate = false;
						return false;
					}
				}
			} else {
				/**
				 * Get Free people
				 * If adult + child + infant < total -> return true
				 * else return false
				 */
				if ( $max_number > 0 ) {
					$free_people = TourHelper::getFreePeopleTourFixedDepart( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
					if ( $free_people < ( $adult_number + $child_number + $infant_number ) ) {
						wp_send_json([
							'message'    => sprintf( __( 'This tour is only available for %d people', 'traveler-childtheme' ), $free_people ),
							'price_from' => $price_from,
						]);
					}
				}
			}
			$extras              = STInput::request( 'extra_price', [] );
			$extra_price         = STTour::geExtraPrice( $extras );
			$data['extras']      = $extras;
			$data['extra_price'] = $extra_price;
			$data['guest_title'] = STInput::post( 'guest_title' );
			$data['guest_name']  = STInput::post( 'guest_name' );
			// Hotel package
			$hotel_packages      = STInput::request( 'hotel_package', [] );
			$package_hotels      = [];
			$arr_hotel_temp      = [];
			$package_hotel_price = 0;
			if ( ! empty( $hotel_packages ) ) {
				foreach ( $hotel_packages as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $arr_hotel_temp, $v[0] );
					}
				}
				if ( ! empty( $arr_hotel_temp ) ) {
					$hp = 0;
					foreach ( $arr_hotel_temp as $k => $v ) {
						$sub_hotel_package = json_decode( stripcslashes( $v ) );
						if ( intval( $arr_hotel_temp[ $k - 1 ] ) > 0 ) {
							if ( ( $k > 0 ) && is_object( $sub_hotel_package ) ) {
								$sub_hotel_package->qty = intval( $arr_hotel_temp[ $k - 1 ] );
							}
							if ( is_object( $sub_hotel_package ) ) {
								$package_hotels[ $hp ] = $sub_hotel_package;
								++$hp;
							}
						}
					}
				}
				$package_hotel_price = STTour::_get_hotel_package_price( $package_hotels );
			}

			$data['package_hotel']       = $package_hotels;
			$data['package_hotel_price'] = $package_hotel_price;
			// Activity package
			$activity_packages = STInput::request( 'activity_package', [] );
			$arr_activity_temp = [];
			if ( ! empty( $activity_packages ) ) {
				foreach ( $activity_packages as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $arr_activity_temp, $v[0] );
					}
				}
			}
			$package_activities = [];
			if ( ! empty( $arr_activity_temp ) ) {
				$hp = 0;
				foreach ( $arr_activity_temp as $k => $v ) {
					$sub_activity_package = json_decode( stripcslashes( $v ) );
					if ( intval( $arr_activity_temp[ $k - 1 ] ) > 0 ) {
						if ( ( $k > 0 ) && is_object( $sub_activity_package ) ) {
							$sub_activity_package->qty = $arr_activity_temp[ $k - 1 ];
						}
						if ( is_object( $sub_activity_package ) ) {
							$package_activities[ $hp ] = $sub_activity_package;
							++$hp;
						}
					}
				}
			}
			$package_activity_price         = STTour::_get_activity_package_price( $package_activities );
			$data['package_activity']       = $package_activities;
			$data['package_activity_price'] = $package_activity_price;
			// Car package
			$car_name_packages_temp = STInput::request( 'car_name', [] );
			$car_name_packages      = [];
			if ( ! empty( $car_name_packages_temp ) ) {
				foreach ( $car_name_packages_temp as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $car_name_packages, $v[0] );
					}
				}
			}
			$car_price_packages_temp = STInput::request( 'car_price', [] );
			$car_price_packages      = [];
			if ( ! empty( $car_price_packages_temp ) ) {
				foreach ( $car_price_packages_temp as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $car_price_packages, $v[0] );
					}
				}
			}
			$car_quantity_packages_temp = STInput::request( 'car_quantity', [] );
			$car_quantity_packages      = [];
			if ( ! empty( $car_quantity_packages_temp ) ) {
				foreach ( $car_quantity_packages_temp as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $car_quantity_packages, $v[0] );
					}
				}
			}
			$package_cars              = STTour::_convert_data_car_package( $car_name_packages, $car_price_packages, $car_quantity_packages );
			$package_car_price         = STTour::_get_car_package_price( $package_cars );
			$data['package_car']       = $package_cars;
			$data['package_car_price'] = $package_car_price;
			// Flight package
			$flight_packages = STInput::request( 'flight_package', [] );

			$arr_flight_temp = [];
			if ( ! empty( $flight_packages ) ) {
				foreach ( $flight_packages as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $arr_flight_temp, $v[0] );
					}
				}
			}
			$package_flight = [];

			if ( ! empty( $arr_flight_temp ) ) {
				$hp = 0;
				foreach ( $arr_flight_temp as $k => $v ) {
					$sub_flight_package = json_decode( stripcslashes( $v ) );
					if ( intval( $arr_flight_temp[ $k + 1 ] ) > 0 ) {
						if ( is_object( $sub_flight_package ) ) {
							$sub_flight_package->qty = $arr_flight_temp[ $k + 1 ];
						}
						if ( is_object( $sub_flight_package ) ) {
							$package_flight[ $hp ] = $sub_flight_package;
							++$hp;
						}
					}
				}
			}
			$package_flight_price = STTour::_get_flight_package_price( $package_flight );
			// End flight package
			$price_type = STTour::get_price_type( $tour_origin );
			if ( $price_type == 'person' || $price_type == 'fixed_depart' ) {
				$data_price = STPrice::getPriceByPeopleTour( $tour_origin, strtotime( $check_in ), strtotime( $check_out ), $adult_number, $child_number, $infant_number );
			} else {
				$data_price = STPriceNew::getPriceByFixedTour( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
			}
			$total_price = $data_price['total_price'];

			$data['ori_price'] = $total_price + $extra_price + $package_hotel_price + $package_activity_price + $package_car_price + $package_flight_price;
			$price_new_html    = TravelHelper::format_money( $data['ori_price'] );
			$html              = '<div id="total-text"><h5>' . __( 'Total', 'traveler-childtheme' ) . '</h5></div>
					<div id="total-value">
						<div class="st-price-origin d-flex align-self-end">
							<span class="price"><span class="value"><span class="text-lg lh1em item "> ' . esc_html( $price_new_html ) . '</span></span></span>
						</div>
					</div>';

			$data = [
				'price'      => $total_price,
				'price_html' => $html,
			];
			wp_send_json( $data );
		}
		/*
		 * @updated 1.2.3
		 */
		public function do_add_to_cart_new() {

			$pass_validate = true;
			$item_id       = STInput::request( 'item_id', '' );
			if ( $item_id <= 0 || get_post_type( $item_id ) != 'st_tours' ) {
				STTemplate::set_message( __( 'This tour is not available..', 'traveler-childtheme' ), 'danger' );
				$pass_validate = false;
				return false;
			}
			$tour_origin   = TravelHelper::post_origin( $item_id, 'st_tours' );
			$tour_price_by = get_post_meta( $tour_origin, 'tour_price_by', true );
			$number        = 1;
			$adult_number  = intval( STInput::request( 'adult_number', 0 ) );
			$child_number  = intval( STInput::request( 'child_number', 0 ) );
			$infant_number = intval( STInput::request( 'infant_number', 0 ) );
			$starttime     = STInput::request( 'starttime_tour', '' );

			// custom add
			$package_name         = trim( STInput::request( 'package_select', '' ) );
			$data['package_name'] = $package_name;

			$data['adult_number']  = $adult_number;
			$data['child_number']  = $child_number;
			$data['infant_number'] = $infant_number;
			$data['starttime']     = $starttime;
			$min_number            = intval( get_post_meta( $item_id, 'min_people', true ) );
			if ( $min_number <= 0 ) {
				$min_number = 1;
			}
			$max_number         = intval( get_post_meta( $item_id, 'max_people', true ) );
			$type_tour          = get_post_meta( $item_id, 'type_tour', true );
			$data['type_tour']  = $type_tour;
			$data['price_type'] = STTour::get_price_type( $item_id );
			$today              = date( 'Y-m-d' );
			// echo STInput::request( 'check_in', '' );die;
			$check_in  = TravelHelper::convertDateFormat( STInput::request( 'check_in', '' ) );
			$check_out = TravelHelper::convertDateFormat( STInput::request( 'check_out', '' ) );

			$format_check_in = date( 'Y-m-d', strtotime( $check_in ) );
			$starttime_data  = AvailabilityHelper::_get_starttime_tour_by_date( $item_id, strtotime( $format_check_in ) );

			if ( ! empty( $starttime_data[0]->starttime ) && empty( $starttime ) ) {
				STTemplate::set_message( __( 'Start time is over.', 'traveler-childtheme' ), 'danger' );
				$pass_validate = false;
				return false;
			}

			if ( ! $adult_number and ! $child_number and ! $infant_number ) {
				STTemplate::set_message( __( 'Please select at least one person.', 'traveler-childtheme' ), 'danger' );
				$pass_validate = false;
				return false;
			}
			if ( $adult_number + $child_number + $infant_number < $min_number ) {
				STTemplate::set_message( sprintf( __( 'Min number of people for this tour is %d people', 'traveler-childtheme' ), $min_number ), 'danger' );
				$pass_validate = false;
				return false;
			}

			if ( empty( STInput::request( 'disable_require_name' ) ) ) {
				if ( ! st_validate_guest_name( $tour_origin, $adult_number, $child_number, 0 ) ) {
					STTemplate::set_message( __( 'Please enter the Guest Name', 'traveler-childtheme' ), 'danger' );
					$pass_validate = false;
					return false;
				}
			}

			/**
			 * @since 1.2.8
			 *        Only check limit people when max_people > 0 (unlimited)
			 * */
			if ( $max_number > 0 ) {
				if ( $adult_number + $child_number + $infant_number > $max_number ) {
					STTemplate::set_message( sprintf( __( 'Max of people for this tour is %d people', 'traveler-childtheme' ), $max_number ), 'danger' );
					$pass_validate = false;
					return false;
				}
			}
			if ( ! $check_in || ! $check_out ) {
				STTemplate::set_message( __( 'Select a day in the calendar.', 'traveler-childtheme' ), 'danger' );
				$pass_validate = false;
				return false;
			}
			if ( $tour_price_by != 'fixed_depart' ) {
				$compare = TravelHelper::dateCompare( $today, $check_in );
				if ( $compare < 0 ) {
					STTemplate::set_message( __( 'This tour has expired', 'traveler-childtheme' ), 'danger' );
					$pass_validate = false;
					return false;
				}
			}
			$booking_period = intval( get_post_meta( $item_id, 'tours_booking_period', true ) );
			$period         = STDate::dateDiff( $today, $check_in );
			if ( $period < $booking_period ) {
				STTemplate::set_message( sprintf( __( 'This tour allow minimum booking is %d day(s)', 'traveler-childtheme' ), $booking_period ), 'danger' );
				$pass_validate = false;
				return false;
			}
			if ( $tour_price_by != 'fixed_depart' ) {
				$tour_available = TourHelper::checkAvailableTour( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
				if ( ! $tour_available ) {
					STTemplate::set_message( __( 'The check in, check out day is invalid or this tour not available.', 'traveler-childtheme' ), 'danger' );
					$pass_validate = false;
					return false;
				}
			}
			if ( $tour_price_by != 'fixed_depart' ) {
				if ( $max_number > 0 ) {
					$free_people = $max_number;
					if ( empty( trim( $starttime ) ) ) {
						$result = TourHelper::_get_free_peple( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
					} else {
						$result = TourHelper::_get_free_peple_by_time( $tour_origin, strtotime( $check_in ), strtotime( $check_out ), $starttime );
					}
					if ( $tour_price_by == 'fixed' ) {
						if ( ! empty( $result ) && ! empty( trim( $starttime ) ) ) {
							STTemplate::set_message( sprintf( __( 'This tour is not available.', 'traveler-childtheme' ) ), 'danger' );
							$pass_validate = false;
							return false;
						}
					}
					if ( is_array( $result ) && count( $result ) ) {
						$free_people = intval( $result['free_people'] );
					}
					/**
					 * @since 1.2.8
					 *        Only check limit people when max_people > 0 (unlimited)
					 * */
					if ( $free_people < ( $adult_number + $child_number + $infant_number ) ) {
						if ( empty( trim( $starttime ) ) ) {
							STTemplate::set_message( sprintf( __( 'This tour is only available for %d people', 'traveler-childtheme' ), $free_people ), 'danger' );
						} else {
							STTemplate::set_message( sprintf( __( 'This tour is only available for %1$d people at %2$s', 'traveler-childtheme' ), $free_people, $starttime ), 'danger' );
						}
						$pass_validate = false;
						return false;
					}
				}
			} else {
				/**
				 * Get Free people
				 * If adult + child + infant < total -> return true
				 * else return false
				 */
				if ( $max_number > 0 ) {
					$free_people = TourHelper::getFreePeopleTourFixedDepart( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
					if ( $free_people < ( $adult_number + $child_number + $infant_number ) ) {
						STTemplate::set_message( sprintf( __( 'This tour is only available for %d people', 'traveler-childtheme' ), $free_people ), 'danger' );
						$pass_validate = false;
						return false;
					}
				}
			}
			/**
			 * Validate Guest Name
			 *
			 * @since 2.1.2
			 * @author dannie
			 */
			/*
			if(!st_validate_guest_name($tour_origin,$adult_number,$child_number,$infant_number))
				{
				STTemplate::set_message(esc_html__('Please enter the Guest Name','traveler-childtheme'), 'danger');
				$pass_validate = FALSE;
				return FALSE;
				} */
			$extras              = STInput::request( 'extra_price', [] );
			$extra_price         = STTour::geExtraPrice( $extras );
			$data['extras']      = $extras;
			$data['extra_price'] = $extra_price;
			$data['guest_title'] = STInput::post( 'guest_title' );
			$data['guest_name']  = STInput::post( 'guest_name' );
			// Hotel package
			$hotel_packages = STInput::request( 'hotel_package', [] );
			$package_hotels = [];
			$arr_hotel_temp = [];
			if ( ! empty( $hotel_packages ) ) {
				foreach ( $hotel_packages as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $arr_hotel_temp, $v[0] );
					}
				}
			}
			$package_hotels = [];
			if ( ! empty( $arr_hotel_temp ) ) {
				$hp = 0;
				foreach ( $arr_hotel_temp as $k => $v ) {
					$sub_hotel_package = json_decode( stripcslashes( $v ) );
					if ( intval( $arr_hotel_temp[ $k - 1 ] ) > 0 ) {
						if ( ( $k > 0 ) && is_object( $sub_hotel_package ) ) {
							$sub_hotel_package->qty = intval( $arr_hotel_temp[ $k - 1 ] );
						}
						if ( is_object( $sub_hotel_package ) ) {
							$package_hotels[ $hp ] = $sub_hotel_package;
							++$hp;
						}
					}
				}
			}
			$package_hotel_price         = STTour::_get_hotel_package_price( $package_hotels );
			$data['package_hotel']       = $package_hotels;
			$data['package_hotel_price'] = $package_hotel_price;
			// Activity package
			$activity_packages = STInput::request( 'activity_package', [] );
			$arr_activity_temp = [];
			if ( ! empty( $activity_packages ) ) {
				foreach ( $activity_packages as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $arr_activity_temp, $v[0] );
					}
				}
			}
			$package_activities = [];
			if ( ! empty( $arr_activity_temp ) ) {
				$hp = 0;
				foreach ( $arr_activity_temp as $k => $v ) {
					$sub_activity_package = json_decode( stripcslashes( $v ) );
					if ( intval( $arr_activity_temp[ $k - 1 ] ) > 0 ) {
						if ( ( $k > 0 ) && is_object( $sub_activity_package ) ) {
							$sub_activity_package->qty = $arr_activity_temp[ $k - 1 ];
						}
						if ( is_object( $sub_activity_package ) ) {
							$package_activities[ $hp ] = $sub_activity_package;
							++$hp;
						}
					}
				}
			}
			$package_activity_price         = STTour::_get_activity_package_price( $package_activities );
			$data['package_activity']       = $package_activities;
			$data['package_activity_price'] = $package_activity_price;
			// Car package
			$car_name_packages_temp = STInput::request( 'car_name', [] );
			$car_name_packages      = [];
			if ( ! empty( $car_name_packages_temp ) ) {
				foreach ( $car_name_packages_temp as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $car_name_packages, $v[0] );
					}
				}
			}
			$car_price_packages_temp = STInput::request( 'car_price', [] );
			$car_price_packages      = [];
			if ( ! empty( $car_price_packages_temp ) ) {
				foreach ( $car_price_packages_temp as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $car_price_packages, $v[0] );
					}
				}
			}
			$car_quantity_packages_temp = STInput::request( 'car_quantity', [] );
			$car_quantity_packages      = [];
			if ( ! empty( $car_quantity_packages_temp ) ) {
				foreach ( $car_quantity_packages_temp as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $car_quantity_packages, $v[0] );
					}
				}
			}
			$package_cars              = STTour::_convert_data_car_package( $car_name_packages, $car_price_packages, $car_quantity_packages );
			$package_car_price         = STTour::_get_car_package_price( $package_cars );
			$data['package_car']       = $package_cars;
			$data['package_car_price'] = $package_car_price;
			// Flight package
			$flight_packages = STInput::request( 'flight_package', [] );

			$arr_flight_temp = [];
			if ( ! empty( $flight_packages ) ) {
				foreach ( $flight_packages as $k => $v ) {
					if ( ! empty( $v ) ) {
						array_push( $arr_flight_temp, $v[0] );
					}
				}
			}
			$package_flight = [];

			if ( ! empty( $arr_flight_temp ) ) {
				$hp = 0;
				foreach ( $arr_flight_temp as $k => $v ) {
					$sub_flight_package = json_decode( stripcslashes( $v ) );
					if ( intval( $arr_flight_temp[ $k + 1 ] ) > 0 ) {
						if ( is_object( $sub_flight_package ) ) {
							$sub_flight_package->qty = $arr_flight_temp[ $k + 1 ];
						}
						if ( is_object( $sub_flight_package ) ) {
							$package_flight[ $hp ] = $sub_flight_package;
							++$hp;
						}
					}
				}
			}
			$package_flight_price         = STTour::_get_flight_package_price( $package_flight );
			$data['package_flight']       = $package_flight;
			$data['package_flight_price'] = $package_flight_price;
			// End flight package
			$price_type = STTour::get_price_type( $tour_origin );

			if ( $price_type == 'person' ) {
				// custom change
				$data_price = STPrice::getPriceByPeopleTour( $tour_origin, strtotime( $check_in ), strtotime( $check_out ), $adult_number, $child_number, $infant_number );
			} else {
				$data_price = STPriceNew::getPriceByFixedTour( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
			}

			$total_price       = $data_price['total_price'];
			$data['check_in']  = date( 'm/d/Y', strtotime( $check_in ) );
			$data['check_out'] = date( 'm/d/Y', strtotime( $check_out ) );
			if ( $price_type == 'fixed_depart' ) {
				$people_price                 = [];
				$people_price['adult_price']  = get_post_meta( $tour_origin, 'adult_price', true );
				$people_price['child_price']  = get_post_meta( $tour_origin, 'child_price', true );
				$people_price['infant_price'] = get_post_meta( $tour_origin, 'infant_price', true );
				$data                         = wp_parse_args( $data, $people_price );
			} elseif ( $price_type == 'person' ) {
				// custom change
				$people_price = STPrice::getPeoplePrice( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
				$data         = wp_parse_args( $data, $people_price );
			} else {
				$fixed_price = STPriceNew::getFixedPrice( $tour_origin, strtotime( $check_in ), strtotime( $check_out ) );
				$data        = wp_parse_args( $data, $fixed_price );
			}

			$data['ori_price']     = $total_price + $extra_price + $package_hotel_price + $package_activity_price + $package_car_price + $package_flight_price;
			$data['sale_price']    = $total_price + $extra_price + $package_hotel_price + $package_activity_price + $package_car_price + $package_flight_price;
			$data['commission']    = TravelHelper::get_commission( $item_id );
			$data['data_price']    = $data_price;
			$data['discount_rate'] = STPrice::get_discount_rate( $tour_origin, strtotime( $check_in ) );
			$data['discount_type'] = get_post_meta( $tour_origin, 'discount_type', true );
			if ( $pass_validate ) {
				$data['duration'] = get_post_meta( $tour_origin, 'duration_day', true );
				if ( $pass_validate ) {
					STCart::add_cart( $tour_origin, $number, $data['sale_price'], $data );
				}
			}
			return $pass_validate;
		}

		/*
		 * @return json
		 * hook tours_add_to_cart
		 */
		public function ajax_tours_add_to_cart_new() {
			if ( STInput::request( 'action' ) == 'tours_add_to_cart' ) {
				$response             = [];
				$response['status']   = 0;
				$response['message']  = '';
				$response['redirect'] = '';
				if ( $this->do_add_to_cart_new() ) {
					$link                 = STCart::get_cart_link();
					$response['redirect'] = $link;
					$response['status']   = 1;
					echo json_encode( $response );
					wp_die();
				} else {
					$message             = STTemplate::message();
					$response['message'] = $message;
					echo json_encode( $response );
					wp_die();
				}
			}
		}

	}


	new STTourNew;
}
