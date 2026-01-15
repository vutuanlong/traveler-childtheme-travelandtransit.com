<?php
$order_id     = '';
$confirm_link = '';
if ( ! class_exists( 'STCartNew' ) ) {
	class STCartNew extends STCart {

		static $coupon_error;
		static function init() {

			// tour booking form ajax from dashboard
			remove_all_actions( 'wp_ajax_booking_form_submit' );
			remove_all_actions( 'wp_ajax_nopriv_booking_form_submit' );
			add_action( 'wp_ajax_booking_form_submit', [ __CLASS__, 'ajax_submit_form' ] );
			add_action( 'wp_ajax_nopriv_booking_form_submit', [ __CLASS__, 'ajax_submit_form' ] );

			// tour booking form ajax when click submit
			remove_all_actions( 'wp_ajax_booking_form_direct_submit' );
			remove_all_actions( 'wp_ajax_nopriv_booking_form_direct_submit' );
			add_action( 'wp_ajax_booking_form_direct_submit', [ __CLASS__, 'direct_submit_form' ] );
			add_action( 'wp_ajax_nopriv_booking_form_direct_submit', [ __CLASS__, 'direct_submit_form' ] );
		}

		static function direct_submit_form() {
			$cart = STInput::post( 'st_cart' );
			$cart = base64_decode( $cart );
			self::set_cart( 'st_cart', unserialize( $cart ) );
			$return = self::booking_form_submit();
			echo json_encode( $return );
			die;
		}

		static function booking_form_submit( $item_id = '' ) {
			$selected           = 'st_submit_form';
			$first_item_id      = self::get_booking_id();
			$create_account_opt = false;
			// travelport_api
			// All gateway available
			$gateways = STPaymentGateways::get_payment_gateways();
			if ( empty( $gateways ) ) {
				return [
					'status'  => false,
					'message' => __( 'Sorry! No payment gateway available', 'traveler-childtheme' ),
				];
			}
			$payment_gateway_id   = STInput::post( 'st_payment_gateway', $selected );
			$payment_gateway_used = STPaymentGateways::get_gateway( $payment_gateway_id, $first_item_id );
			if ( ! $payment_gateway_id or ! $payment_gateway_used ) {
				$payment_gateway_name = apply_filters( 'st_payment_gateway_' . $payment_gateway_id . '_name', $payment_gateway_id );
				return [
					'status'  => false,
					'message' => sprintf( __( 'Sorry! Payment Gateway: <code>%s</code> is not available for this item!', 'traveler-childtheme' ), $payment_gateway_name ),
				];
			}
			// Action before submit form
			do_action( 'st_before_form_submit_run' );
			$form_validate = true;
			$booking_by    = STInput::post( 'booking_by', '' );
			if ( $booking_by != 'partner' ) {
				if ( ! self::check_cart() and ! STInput::post( 'order_id' ) ) {
					return [
						'status'  => false,
						'message' => __( 'Your cart is currently empty.', 'traveler-childtheme' ),
						'code'    => '1',
					];
				}
			} elseif ( ! self::check_cart() and ! STInput::post( 'order_id' ) ) {
					return [
						'status'  => 'partner',
						'message' => '',
						'code'    => '1',
					];
			}
			if ( $coupon_code = STInput::request( 'coupon_code' ) ) {
				$status = self::do_apply_coupon( $coupon_code );
				if ( ! $status['status'] ) {
					return [
						'status'  => false,
						'message' => $status['message'],
					];
				}
			}
			$is_guest_booking  = st()->get_option( 'is_guest_booking', 'on' );
			$is_user_logged_in = is_user_logged_in();
			if ( ! empty( $is_guest_booking ) and $is_guest_booking == 'off' and ! $is_user_logged_in ) {
				$page_checkout = st()->get_option( 'page_checkout' );
				$page_login    = st()->get_option( 'page_user_login' );
				if ( empty( $page_login ) ) {
					$page_login = home_url();
				} else {
					$page_login = get_permalink( $page_login );
				}
				$page_login = add_query_arg( [ 'st_url_redirect' => get_permalink( $page_checkout ) ], $page_login );
				return [
					'status'   => true,
					'redirect' => esc_url( $page_login ),
				];
			}

			$default = [
				'st_note'         => '',
				'term_condition'  => '',
				'create_account'  => false,
				'paypal_checkout' => false,
			];
			extract( wp_parse_args( $_POST, $default ) );
			// Term and condition
			if ( ! $term_condition ) {
				return [
					'status'  => false,
					'message' => __( 'Please accept our terms and conditions', 'traveler-childtheme' ),
				];
			}
			$form_validate = self::validate_checkout_fields();
			if ( $form_validate ) {
				// Allow to hook before save order
				$form_validate = apply_filters( 'st_checkout_form_validate', $form_validate );
			}
			if ( $form_validate ) {
				$form_validate = $payment_gateway_used->_pre_checkout_validate();
			}
			if ( ! $form_validate ) {
				$message = [
					'status'        => false,
					'message'       => STTemplate::get_message_content(),
					'form_validate' => 'false',
				];
				STTemplate::clear();
				return $message;
			}
			$order_id = STInput::post( 'order_id' );

			// if order is already posted as order_id, we only need to make payment for it
			if ( $order_id && $order_id != 'false' ) {
				return STPaymentGateways::do_checkout( $payment_gateway_used, $order_id );
			}
			$st_secret_key_captcha = st()->get_option( 'st_secret_key_captcha', '6LdQ4fsUAAAAAOi1Y9yU4py-jx36gCN703stk9y1' );
			if ( st()->get_option( 'booking_enable_captcha', 'off' ) == 'on' ) {
				$recaptcha_url      = 'https://www.google.com/recaptcha/api/siteverify';
				$recaptcha_secret   = $st_secret_key_captcha;
				$recaptcha_response = isset( $_POST['st_captcha'] ) ? $_POST['st_captcha'] : '';
				$recaptcha          = file_get_contents( $recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response );
				$recaptcha          = json_decode( $recaptcha );
				$recaptcha          = (array) $recaptcha;
				if ( isset( $recaptcha['score'] ) && ( $recaptcha['score'] >= 0.5 ) ) {
					// Verified - send email
				} else {
					$errors    = $recaptcha['error-codes'];
					$mes_error = '';
					foreach ( $errors as $key => $err ) {
						$mes_error .= esc_html__( 'Error captcha:', 'traveler-childtheme' ) . ' ' . $err . '<br>';
					}
					return [
						'status'        => false,
						'message'       => $mes_error,
						'captcha_check' => true,
					];
				}
			}
			$post = [
				'post_title'  => __( 'Order', 'traveler-childtheme' ) . ' - ' . date( get_option( 'date_format' ) ) . ' @ ' . date( get_option( 'time_format' ) ),
				'post_type'   => 'st_order',
				'post_status' => 'publish',
			];

			// custom new
			$data_price = STPriceNew::getDataPrice();

			// save the order
			$insert_post = wp_insert_post( $post );

			if ( $insert_post ) {
				$cart        = self::get_items();
				$cart_detail = STCart::get_carts();

				$fields         = self::get_checkout_fields();
				$transaction_id = STInput::post( 'vina_stripe_payment_method_id' );

				if ( ! empty( $fields ) ) {
					foreach ( $fields as $key => $value ) {
						update_post_meta( $insert_post, $key, STInput::post( $key ) );
					}
				}
				if ( ! is_user_logged_in() ) {
					$user_name = STInput::post( 'st_email' );
					$user_id   = username_exists( $user_name );
					// Now Create Account if user agree
					if ( ( st()->get_option( 'guest_create_acc_required', 'off' ) == 'on' ) and ( st()->get_option( 'st_booking_enabled_create_account', 'off' ) == 'on' ) and ( st()->get_option( 'is_guest_booking', 'off' ) == 'on' ) ) {
						$create_account_opt = true;
					}

					if ( $create_account || $create_account_opt ) {
						if ( ! $user_id && email_exists( $user_name ) == false ) {
							$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
							$userdata        = [
								'user_login' => $user_name,
								'user_pass'  => $random_password,
								'user_email' => $user_name,
								'first_name' => STInput::post( 'st_first_name' ),
								// When creating an user, `user_pass` is expected.
								'last_name'  => STInput::post( 'st_last_name' ),
								// When creating an user, `user_pass` is expected.
							];
							$user_id = wp_insert_user( $userdata );
							// Create User Success, send the nofitication
							wp_send_new_user_notifications( $user_id );
						}
					}
				} else {
					$user_id = get_current_user_id();
				}
				if ( $user_id ) {
					// Now Update the Post Meta
					update_post_meta( $insert_post, 'id_user', $user_id );
					// Update User Meta
					update_user_meta( $user_id, 'st_phone', STInput::post( 'st_phone' ) );
					update_user_meta( $user_id, 'first_name', STInput::post( 'st_first_name' ) );
					update_user_meta( $user_id, 'last_name', STInput::post( 'st_last_name' ) );
					update_user_meta( $user_id, 'st_address', STInput::post( 'st_address' ) );
					update_user_meta( $user_id, 'st_address2', STInput::post( 'st_address2' ) );
					update_user_meta( $user_id, 'st_city', STInput::post( 'st_city' ) );
					update_user_meta( $user_id, 'st_province', STInput::post( 'st_province' ) );
					update_user_meta( $user_id, 'st_zip_code', STInput::post( 'st_zip_code' ) );
					update_user_meta( $user_id, 'st_apt_unit', STInput::post( 'st_apt_unit' ) );
					update_user_meta( $user_id, 'st_country', STInput::post( 'st_country' ) );
				}
				self::saveOrderItems( $insert_post );
				do_action( 'st_save_order_other_table', $insert_post );
				update_post_meta( $insert_post, 'st_tax', STPrice::getTax() );
				update_post_meta( $insert_post, 'st_tax_percent', STPrice::getTax() );
				update_post_meta( $insert_post, 'st_is_tax_included_listing_page', STCart::is_tax_included_listing_page() ? 'on' : 'off' );
				update_post_meta( $insert_post, 'currency', TravelHelper::get_current_currency() );
				update_post_meta( $insert_post, 'coupon_code', STCart::get_coupon_code() );
				update_post_meta( $insert_post, 'coupon_amount', STCart::get_coupon_amount() );
				$status_order = 'pending';
				if ( $payment_gateway_id === 'st_submit_form' ) {
					if ( st()->get_option( 'enable_email_confirm_for_customer', 'on' ) !== 'off' ) {
						$status_order = 'incomplete';
					}
				}
				update_post_meta( $insert_post, 'status', $status_order );
				update_post_meta( $insert_post, 'st_cart_info', $cart );
				update_post_meta( $insert_post, 'st_cart_detail', $cart_detail );
				update_post_meta( $insert_post, 'total_price', STPriceNew::getTotal() );
				update_post_meta( $insert_post, 'ip_address', STInput::ip_address() );
				update_post_meta( $insert_post, 'transaction_id', $transaction_id );
				update_post_meta( $insert_post, 'order_token_code', wp_hash( $insert_post ) );
				update_post_meta( $insert_post, 'data_prices', $data_price );
				update_post_meta( $insert_post, 'booking_by', STInput::post( 'booking_by', '' ) );
				update_post_meta( $insert_post, 'payment_method', $payment_gateway_id );
				update_post_meta( $insert_post, 'payment_method_name', STPaymentGateways::get_gatewayname( $payment_gateway_id ) );
				do_action( 'st_booking_success', $insert_post );
				// Now gateway do the rest
				$res = STPaymentGateways::do_checkout( $payment_gateway_used, $insert_post );
				// destroy cart
				STCart::destroy_cart();
				return $res;

			} else {
				return [
					'status'  => false,
					'message' => __( 'Can not save order.', 'traveler-childtheme' ),
				];
			}
		}

		/**
		 * @update 1.1.10
		 * @return array|void
		 */
		static function ajax_submit_form() {
			$item_id       = STInput::post( 'item_id' );
			$car_post_type = STInput::post( 'car_post_type' );
			// check origin is already taken
			if ( STInput::post( 'order_id' ) && strtolower( STInput::post( 'order_id' ) ) != 'false' ) {
				return self::booking_form_submit( $item_id );
			}
			// Add to cart then submit form
			$sc = STInput::request( 'sc', '' );
			if ( ! $item_id ) {
				$name = '';
				if ( $sc == 'add-hotel-booking' ) {
					$name = __( 'Hotel', 'traveler-childtheme' );
				} elseif ( $sc == 'add-rental-booking' ) {
					$name = __( 'Rental', 'traveler-childtheme' );
				} elseif ( $sc == 'add-car-booking' ) {
					$name = __( 'Car', 'traveler-childtheme' );
				} elseif ( $sc == 'add-tour-booking' ) {
					$name = __( 'Tour', 'traveler-childtheme' );
				} elseif ( $sc == 'add-activity-booking' ) {
					$name = __( 'Activity', 'traveler-childtheme' );
				}
				$return = [
					'status'  => false,
					'message' => sprintf( __( 'Please choose a %s item ', 'traveler-childtheme' ), $name ),
				];
			} else {
				$post_type   = get_post_type( $item_id );
				$number_room = STInput::post( 'number_room' ) ? STInput::post( 'number_room' ) : false;
				if ( ! $number_room ) {
					$number_room = STInput::post( 'room_num_search' ) ? STInput::post( 'room_num_search' ) : 1;
				}
				self::destroy_cart();
				$validate = true;
				if ( $car_post_type === 'car_transfer' ) {
					if ( class_exists( 'STCarTransfer' ) ) {
						$class    = new STCarTransfer();
						$validate = $class->do_add_to_cart();
					}
					if ( $validate ) {
						$return = self::booking_form_submit( $item_id );
					} else {
						$return = [
							'status'  => false,
							'message' => STTemplate::get_message_content(),
						];
						STTemplate::clear();
					}
				} else {
					switch ( $post_type ) {
						case 'st_hotel':
							if ( class_exists( 'STHotel' ) ) {
								$hotel    = new STHotel();
								$validate = $hotel->do_add_to_cart();
							}
							break;
						case 'hotel_room':
							if ( class_exists( 'STHotel' ) ) {
								$hotel    = new STHotel();
								$validate = $hotel->do_add_to_cart();
							}
							break;
						case 'st_cars':
							if ( class_exists( 'STCars' ) ) {
								$car      = new STCars();
								$validate = $car->do_add_to_cart();
							}
							break;
						case 'st_activity':
							if ( class_exists( 'STActivity' ) ) {
								$class    = STActivity::inst();
								$validate = $class->do_add_to_cart();
							}
							break;
						case 'st_tours':
							if ( class_exists( 'STTour' ) ) {
								$class    = new STTourNew();
								$validate = $class->do_add_to_cart_new();
							}
							break;
						case 'st_rental':
							if ( class_exists( 'STRental' ) ) {
								$class    = STRental::inst();
								$validate = $class->do_add_to_cart();
							}
							break;
					}
					if ( $validate ) {
						$return = self::booking_form_submit( $item_id );
					} else {
						$return = [
							'status'  => false,
							'message' => STTemplate::get_message_content(),
						];
						STTemplate::clear();
					}
				}
			}
			echo json_encode( $return );
			die;
		}

	}
	STCartNew::init();
}
