<?php
if ( ! class_exists( 'STPriceNew' ) ) {
	class STPriceNew extends STPrice {

		static function getPriceByFixedTour( $tour_id = '', $check_in = '', $check_out = '', $package_name = false ) {
			$total_price = 0;
			$tour_id     = intval( $tour_id );
			$fixed_price = self::getFixedPrice( $tour_id, $check_in, $check_out, $package_name );

			if ( ! empty( $fixed_price ) ) {
				$total_price = $fixed_price['base_price'];
			}
			if ( $total_price < 0 ) {
				$total_price = 0;
			}
			$total_price = STPrice::getSaleTourSalePrice( $tour_id, $total_price, false, $check_in );
			$data        = [
				'total_price'        => $total_price,
				'total_price_origin' => $fixed_price['base_price'],
			];
			return $data;
		}
		static function getFixedPrice( $tour_id, $check_in, $check_out, $package_name = false ) {
			$data_price = [
				'base_price' => 0,
			];
			$res        = ST_Tour_Availability::inst()
						->where( 'post_id', $tour_id )
						->where( 'check_in', $check_in )
						->where( 'check_out', $check_out )
						->where( 'status', 'available' )
						->get()->result();
			if ( ! empty( $res ) ) {
				$data_price['base_price'] = $res[0]['price'];
				// new custom
				$packegeTitle = STInput::request( 'package_select', '' );
				if ( ! $packegeTitle ) {
					$packegeTitle = $package_name;
				}
				if ( $packegeTitle && $people_price_package = self::getPeoplePriceByPackage( $tour_id, $packegeTitle ) ) {
					$data_price['base_price'] = $people_price_package['package_price_fixed'];
				}
			}
			return $data_price;
		}

		static function getPeoplePriceByPackage( $tour_id, $title_package ) {
			$list_package = get_post_meta( $tour_id, 'package_list', true );

			if ( $list_package && isset( $list_package[0] ) && $list_package[0]['title'] ) {
				foreach ( $list_package as $packed ) :
					if ( trim( $packed['title'] ) == trim( $title_package ) ) {
						return $packed;
					}
				endforeach;
			}
			return false;
		}

		static function getTotal( $div_room = false, $disable_coupon = false, $disable_deposit = false ) {
			$cart  = STCart::get_carts();
			$total = 0;
			if ( is_array( $cart ) && count( $cart ) ) {
				foreach ( $cart as $key => $val ) {

					$post_id = intval( $key );
					if ( empty( $val['data']['deposit_money'] ) ) {
						$val['data']['deposit_money'] = [];
					}
					if ( get_post_type( $post_id ) == 'st_hotel' or get_post_type( $post_id ) == 'hotel_room' ) {
						$room_id        = intval( ! empty( $val['data']['room_id'] ) ? $val['data']['room_id'] : 0 );
						$check_in       = ! empty( $val['data']['check_in'] ) ? $val['data']['check_in'] : '';
						$check_out      = ! empty( $val['data']['check_out'] ) ? $val['data']['check_out'] : '';
						$number_room    = ! empty( intval( $val['number'] ) ) ? intval( $val['number'] ) : 0;
						$numberday      = STDate::dateDiff( $check_in, $check_out );
						$adult_number   = ! empty( $val['data']['adult_number'] ) ? intval( $val['data']['adult_number'] ) : 0;
						$child_number   = ! empty( $val['data']['child_number'] ) ? intval( $val['data']['child_number'] ) : 0;
						$sale_price     = STPrice::getRoomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$extras         = ! empty( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price    = STPrice::getExtraPrice( $room_id, $extras, $number_room, $numberday );
						$price_with_tax = STPrice::getPriceWithTax( $sale_price + $extra_price );
						if ( ! $disable_coupon ) {
							$price_coupon    = STPrice::getCouponPrice();
							$price_with_tax -= $price_coupon;
						}
						$deposit_price = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) && ! $disable_deposit ) {
							$total = $deposit_price;
						} else {
							$total = $price_with_tax;
						}
						if ( $div_room ) {
							$total /= $number_room;
						}
					}
					if ( get_post_type( $post_id ) == 'st_rental' ) {
						$rental_id      = intval( $key );
						$check_in       = ! empty( $val['data']['check_in'] ) ? $val['data']['check_in'] : '';
						$check_out      = ! empty( $val['data']['check_out'] ) ? $val['data']['check_out'] : '';
						$item_price     = STPrice::getRentalPriceOnlyCustomPrice( $rental_id, strtotime( $check_in ), strtotime( $check_out ) );
						$numberday      = STDate::dateDiff( $check_in, $check_out );
						$sale_price     = STPrice::getSalePrice( $rental_id, strtotime( $check_in ), strtotime( $check_out ) );
						$sale_price     = ! empty( $sale_price['total_price'] ) ? floatval( $sale_price['total_price'] ) : 0;
						$extras         = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price    = STPrice::getExtraPrice( $rental_id, $extras, 1, $numberday );
						$price_with_tax = STPrice::getPriceWithTax( $sale_price + $extra_price );
						if ( ! $disable_coupon ) {
							$price_coupon    = STPrice::getCouponPrice();
							$price_with_tax -= $price_coupon;
						}
						$deposit_price = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) && ! $disable_deposit ) {
							$total = $deposit_price;
						} else {
							$total = $price_with_tax;
						}
					}
					if ( get_post_type( $post_id ) == 'st_activity' ) {
						$check_in       = isset( $val['data']['check_in'] ) ? $val['data']['check_in'] : '';
						$check_out      = isset( $val['data']['check_out'] ) ? $val['data']['check_out'] : '';
						$adult_number   = isset( $val['data']['adult_number'] ) ? intval( $val['data']['adult_number'] ) : 0;
						$child_number   = isset( $val['data']['child_number'] ) ? intval( $val['data']['child_number'] ) : 0;
						$infant_number  = isset( $val['data']['infant_number'] ) ? intval( $val['data']['infant_number'] ) : 0;
						$data_prices    = self::getPriceByPeopleTour( $post_id, strtotime( $check_in ), strtotime( $check_out ), $adult_number, $child_number, $infant_number );
						$origin_price   = floatval( $data_prices['total_price'] );
						$type_activity  = isset( $val['data']['type_activity'] ) ? $val['data']['type_activity'] : '';
						$extras         = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price    = STActivity::inst()->geExtraPrice( $extras );
						$sale_price     = $origin_price;
						$price_with_tax = self::getPriceWithTax( $sale_price + $extra_price );
						if ( ! $disable_coupon ) {
							$coupon_price    = self::getCouponPrice();
							$price_with_tax -= $coupon_price;
						}
						$deposit_price = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) && ! $disable_deposit ) {
							$total = $deposit_price;
						} else {
							$total = $price_with_tax;
						}
					}
					if ( get_post_type( $post_id ) == 'st_tours' ) {
						$check_in      = isset( $val['data']['check_in'] ) ? $val['data']['check_in'] : '';
						$check_out     = isset( $val['data']['check_out'] ) ? $val['data']['check_out'] : '';
						$adult_number  = isset( $val['data']['adult_number'] ) ? intval( $val['data']['adult_number'] ) : 0;
						$child_number  = isset( $val['data']['child_number'] ) ? intval( $val['data']['child_number'] ) : 0;
						$infant_number = isset( $val['data']['infant_number'] ) ? intval( $val['data']['infant_number'] ) : 0;
						$price_type    = STTour::get_price_type( $post_id );
						if ( $price_type == 'person' || $price_type == 'fixed_depart' ) {
							$data_prices = self::getPriceByPeopleTour( $post_id, strtotime( $check_in ), strtotime( $check_out ), $adult_number, $child_number, $infant_number );
						} else {
							$package_name = isset( $val['data']['package_name'] ) ? trim( $val['data']['package_name'] ) : false;
							$data_prices  = self::getPriceByFixedTour( $post_id, strtotime( $check_in ), strtotime( $check_out ), $package_name );
						}
						$origin_price           = floatval( $data_prices['total_price'] );
						$tour_type              = isset( $val['data']['type_tour'] ) ? $val['data']['type_tour'] : '';
						$extras                 = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price            = STTour::geExtraPrice( $extras );
						$hotel_packages         = isset( $val['data']['package_hotel'] ) ? $val['data']['package_hotel'] : [];
						$hotel_package_price    = STTour::_get_hotel_package_price( $hotel_packages );
						$activity_packages      = isset( $val['data']['package_activity'] ) ? $val['data']['package_activity'] : [];
						$activity_package_price = STTour::_get_activity_package_price( $activity_packages );
						$car_packages           = isset( $val['data']['package_car'] ) ? $val['data']['package_car'] : [];
						$car_package_price      = STTour::_get_car_package_price( $car_packages );
						$flight_packages        = isset( $val['data']['package_flight'] ) ? $val['data']['package_flight'] : [];
						$flight_package_price   = STTour::_get_flight_package_price( $flight_packages );
						$sale_price             = $origin_price;
						$price_with_tax         = self::getPriceWithTax( $sale_price + $extra_price + $hotel_package_price + $activity_package_price + $car_package_price + $flight_package_price );
						if ( ! $disable_coupon ) {
							$coupon_price    = self::getCouponPrice();
							$price_with_tax -= $coupon_price;
						}
						$deposit_price = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) && ! $disable_deposit ) {
							$total = $deposit_price;
						} else {
							$total = $price_with_tax;
						}
					}
					if ( get_post_type( $post_id ) == 'st_cars' ) {
						$car_id         = intval( $key );
						$price_with_tax = $val['data']['price_with_tax'];
						$deposit_price  = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) && ! $disable_deposit ) {
							$total = $deposit_price;
						} else {
							$total = $price_with_tax;
						}
					}
					// Flight
					if ( get_post_type( $post_id ) == 'st_flight' && ! empty( $val['data']['total_price'] ) ) {
						$total = $val['data']['total_price'];
					}
					if ( ! empty( $val['data']['booking_fee_price'] ) ) {
						$total = $total + $val['data']['booking_fee_price'];
					}
					if ( $key == 'car_transfer' ) {
						$price_with_tax = $val['data']['price_with_tax'];
						$deposit_price  = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) && ! $disable_deposit ) {
							$total = $deposit_price;
						} else {
							$total = $price_with_tax;
						}
					}
					if ( $key == 'travelport_api' ) {
						$total = $val['price'];
					}
				}
			}
			return TravelHelper::convert_money( $total, false, false );
		}

		static function getDataPrice() {
			$cart           = STCart::get_carts();
			$data_price     = [
				'origin_price'       => '',
				'sale_price'         => '',
				'coupon_price'       => '',
				'total_price'        => '',
				'deposit_price'      => '',
				'booking_fee_price'  => '',
				'total_price_origin' => '',
			];
			$price_with_tax = 0;
			$origin_price   = $sale_price = $coupon_price = $total_price = $deposit_price = $booking_fee_price = $total_bulk_discount = 0;
			if ( is_array( $cart ) && count( $cart ) ) {
				foreach ( $cart as $key => $val ) {
					if ( ! empty( $val['data']['booking_fee_price'] ) ) {
						$booking_fee_price = $val['data']['booking_fee_price'];
					}
					if ( ! isset( $val['data']['deposit_money'] ) ) {
						$val['data']['deposit_money'] = [];
					}
					if ( get_post_type( $key ) == 'st_hotel' ) {
						$room_id            = intval( $val['data']['room_id'] );
						$check_in           = $val['data']['check_in'];
						$check_out          = $val['data']['check_out'];
						$number_room        = intval( $val['number'] );
						$adult_number       = intval( $val['data']['adult_number'] );
						$child_number       = intval( $val['data']['child_number'] );
						$numberday          = STDate::dateDiff( $check_in, $check_out );
						$origin_price       = self::getRoomPriceOnlyCustomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$total_price_origin = self::getRoomPriceOnlyCustomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$sale_price         = self::getRoomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$extras             = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price        = STPrice::getExtraPrice( $room_id, $extras, $number_room, $numberday );
						$coupon_price       = self::getCouponPrice();
						$price_with_tax     = self::getPriceWithTax( $sale_price + $extra_price );
						$price_with_tax    -= $coupon_price;
						$deposit_price      = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
					}
					if ( get_post_type( $key ) == 'hotel_room' ) {
						$room_id            = intval( $val['data']['room_id'] );
						$check_in           = $val['data']['check_in'];
						$check_out          = $val['data']['check_out'];
						$number_room        = intval( $val['number'] );
						$numberday          = STDate::dateDiff( $check_in, $check_out );
						$adult_number       = intval( $val['data']['adult_number'] );
						$child_number       = intval( $val['data']['child_number'] );
						$origin_price       = self::getRoomPriceOnlyCustomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$total_price_origin = self::getRoomPriceOnlyCustomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$sale_price         = self::getRoomPrice( $room_id, strtotime( $check_in ), strtotime( $check_out ), $number_room, $adult_number, $child_number );
						$extras             = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price        = STPrice::getExtraPrice( $room_id, $extras, $number_room, $numberday );
						$coupon_price       = self::getCouponPrice();
						$price_with_tax     = self::getPriceWithTax( $sale_price + $extra_price );
						$price_with_tax    -= $coupon_price;
						$deposit_price      = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
					}
					if ( get_post_type( $key ) == 'st_rental' ) {
						$rental_id          = intval( $key );
						$check_in           = $val['data']['check_in'];
						$check_out          = $val['data']['check_out'];
						$numberday          = STDate::dateDiff( $check_in, $check_out );
						$origin_price       = STPrice::getRentalPriceOnlyCustomPrice( $rental_id, strtotime( $check_in ), strtotime( $check_out ) );
						$total_price_origin = STPrice::getRentalPriceOnlyCustomPrice( $rental_id, strtotime( $check_in ), strtotime( $check_out ) );
						$sale_price         = STPrice::getSalePrice( $rental_id, strtotime( $check_in ), strtotime( $check_out ) );
						$sale_price         = ! empty( $sale_price['total_price'] ) ? floatval( $sale_price['total_price'] ) : 0;
						$extras             = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price        = STPrice::getExtraPrice( $rental_id, $extras, 1, $numberday );
						$coupon_price       = self::getCouponPrice();
						$price_with_tax     = self::getPriceWithTax( $sale_price + $extra_price );
						$price_with_tax    -= $coupon_price;
						$deposit_price      = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
					}
					if ( get_post_type( $key ) == 'st_tours' ) {
						$post_id       = intval( $key );
						$check_in      = $val['data']['check_in'];
						$check_out     = $val['data']['check_out'];
						$adult_number  = intval( $val['data']['adult_number'] );
						$child_number  = intval( $val['data']['child_number'] );
						$infant_number = intval( $val['data']['infant_number'] );
						$price_type    = STTour::get_price_type( $post_id );
						if ( $price_type == 'person' || $price_type == 'fixed_depart' ) {
							$data_prices = self::getPriceByPeopleTour( $post_id, strtotime( $check_in ), strtotime( $check_out ), $adult_number, $child_number, $infant_number );
						} else {
							$package_name = isset( $val['data']['package_name'] ) ? trim( $val['data']['package_name'] ) : false;
							$data_prices  = self::getPriceByFixedTour( $post_id, strtotime( $check_in ), strtotime( $check_out ), $package_name );
						}

						$origin_price       = floatval( $data_prices['total_price'] );
						$total_price_origin = floatval( $data_prices['total_price_origin'] );
						if ( get_post_type( $post_id ) == 'st_tours' ) {
							$tour_type = $val['data']['type_tour'];
						} elseif ( get_post_type( $post_id ) == 'st_activity' ) {
							$tour_type = $val['data']['type_activity'];
						}
						$sale_price  = $origin_price;
						$extras      = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price = STTour::geExtraPrice( $extras );
						// Hotel package
						$hotel_packages         = isset( $val['data']['package_hotel'] ) ? $val['data']['package_hotel'] : [];
						$hotel_package_price    = STTour::_get_hotel_package_price( $hotel_packages );
						$activity_packages      = isset( $val['data']['package_activity'] ) ? $val['data']['package_activity'] : [];
						$activity_package_price = STTour::_get_activity_package_price( $activity_packages );
						$car_packages           = isset( $val['data']['package_car'] ) ? $val['data']['package_car'] : [];
						$car_package_price      = STTour::_get_car_package_price( $car_packages );
						$flight_packages        = isset( $val['data']['package_flight'] ) ? $val['data']['package_flight'] : [];
						$flight_package_price   = STTour::_get_flight_package_price( $flight_packages );
						$coupon_price           = self::getCouponPrice();
						$price_with_tax         = self::getPriceWithTax( $sale_price + $extra_price + $hotel_package_price + $activity_package_price + $car_package_price + $flight_package_price );
						$price_with_tax        -= $coupon_price;
						$deposit_price          = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
						if ( $price_type == 'person' ) {
							$data_price['adult_price']  = $data_prices['adult_price'];
							$data_price['child_price']  = $data_prices['child_price'];
							$data_price['infant_price'] = $data_prices['infant_price'];
						}
						$data_price['price_type'] = $price_type;
						$total_bulk_discount      = $val['data']['data_price']['total_bulk_discount'];
					}
					if ( get_post_type( $key ) == 'st_activity' ) {
						$post_id            = intval( $key );
						$check_in           = $val['data']['check_in'];
						$check_out          = $val['data']['check_out'];
						$adult_number       = intval( $val['data']['adult_number'] );
						$child_number       = intval( $val['data']['child_number'] );
						$infant_number      = intval( $val['data']['infant_number'] );
						$data_prices        = self::getPriceByPeopleTour( $post_id, strtotime( $check_in ), strtotime( $check_out ), $adult_number, $child_number, $infant_number );
						$origin_price       = floatval( $data_prices['total_price'] );
						$total_price_origin = floatval( $data_prices['total_price_origin'] );
						if ( get_post_type( $post_id ) == 'st_tours' ) {
							$tour_type = $val['data']['type_tour'];
						} elseif ( get_post_type( $post_id ) == 'st_activity' ) {
							$tour_type = $val['data']['type_activity'];
						}
						$sale_price      = $origin_price;
						$extras          = isset( $val['data']['extras'] ) ? $val['data']['extras'] : [];
						$extra_price     = STActivity::inst()->geExtraPrice( $extras );
						$coupon_price    = self::getCouponPrice();
						$price_with_tax  = self::getPriceWithTax( $sale_price + $extra_price );
						$price_with_tax -= $coupon_price;
						$deposit_price   = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
						$data_price['adult_price']  = $data_prices['adult_price'];
						$data_price['child_price']  = $data_prices['child_price'];
						$data_price['infant_price'] = $data_prices['infant_price'];
						$total_bulk_discount        = $val['data']['data_price']['total_bulk_discount'];
					}
					if ( get_post_type( $key ) == 'st_cars' ) {
						$post_id                = intval( $key );
						$check_in_timestamp     = $val['data']['check_in_timestamp'];
						$check_out_timestamp    = $val['data']['check_out_timestamp'];
						$item_price             = floatval( $val['data']['item_price'] );
						$price_equipment        = floatval( $val['data']['price_equipment'] );
						$unit                   = st()->get_option( 'cars_price_unit', 'day' );
						$numberday              = STCars::get_date_diff( $check_in_timestamp, $check_out_timestamp, $unit );
						$data_price['distance'] = st()->get_option( 'cars_price_by_distance', 'kilometer' );
						if ( $unit == 'distance' ) {
							$origin_price                  = $item_price * $val['data']['distance'];
							$data_price['number_distance'] = $val['data']['distance'];
						} else {
							$origin_price = $item_price * $numberday;
						}
						$total_price_origin   = $origin_price;
						$location_id_pick_up  = isset( $val['data']['location_id_pick_up'] ) ? $val['data']['location_id_pick_up'] : '';
						$location_id_drop_off = isset( $val['data']['location_id_drop_off'] ) ? $val['data']['location_id_drop_off'] : '';
						$sale_price           = STPrice::getSaleCarPrice( $post_id, $item_price, $check_in_timestamp, $check_out_timestamp, $location_id_pick_up, $location_id_drop_off );
						$coupon_price         = self::getCouponPrice();
						$price_with_tax       = STPrice::getPriceWithTax( $sale_price + $price_equipment );
						$price_with_tax      -= $coupon_price;
						$deposit_price        = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
						$data_price['price_equipment'] = $price_equipment;
						$data_price['unit']            = $unit;
					}
					if ( get_post_type( $key ) == 'st_flight' ) {
						$total_price                = $val['data']['total_price'];
						$origin_price               = $val['data']['depart_price'];
						$total_price_origin         = $val['data']['depart_price'];
						$sale_price                 = $val['data']['depart_price'];
						$data_price['return_price'] = $val['data']['return_price'];
						$coupon_price               = '';
						$deposit_price              = '';
					}
					if ( $key == 'car_transfer' ) {
						$origin_price       = $val['data']['ori_price'];
						$total_price_origin = $val['data']['ori_price'];
						$sale_price         = $val['data']['sale_price'];
						$price_with_tax     = STPrice::getPriceWithTax( $sale_price );
						$coupon_price       = self::getCouponPrice();
						$price_with_tax    -= $coupon_price;
						$price_detail       = $val['data']['price'];
						$price_return       = $val['data']['price_return'];
						$has_return         = $val['data']['has_return'];
						$deposit_price      = self::getDepositPrice( $val['data']['deposit_money'], $price_with_tax );
						if ( isset( $val['data']['deposit_money'] ) ) {
							$total_price = $deposit_price;
						} else {
							$total_price = $price_with_tax;
						}
					}
					if ( ! empty( $val['data']['total_bulk_discount'] ) ) {
						$total_bulk_discount = $val['data']['total_bulk_discount'];
					}
					$discount_rate = $val['data']['discount_rate'];
					$discount_type = $val['data']['discount_type'];

				}
			}

			if ( isset( $has_return ) && ( $has_return === 'yes' ) ) {
				$data_price['price_return'] = $price_return;
				$data_price['price']        = $price_detail;
			}
			$data_price['origin_price']         = $origin_price;
			$data_price['total_price_origin']   = $total_price_origin;
			$data_price['sale_price']           = $sale_price;
			$data_price['coupon_price']         = $coupon_price;
			$data_price['price_with_tax']       = $total_price;
			$data_price['total_price_with_tax'] = $price_with_tax; // tong order gom thue chua tru deposit
			$data_price['total_price']          = $total_price + $booking_fee_price;
			$data_price['deposit_price']        = $deposit_price;
			$data_price['booking_fee_price']    = $booking_fee_price;
			$data_price['total_bulk_discount']  = $total_bulk_discount;
			$data_price['discount_rate']        = $discount_rate;
			$data_price['discount_type']        = $discount_type;
			return $data_price;
		}
	}
}
new STPriceNew();
