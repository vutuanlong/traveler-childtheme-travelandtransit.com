<?php
$icon_duration_single_tour = st()->get_option('icon_duration_single_tour', '<i class="stt-icon-clock"></i>');
$icon_tourtype_single_tour = st()->get_option('icon_tourtype_single_tour', '<i class="stt-icon-footprint"></i>');
$icon_groupsize_single_tour = st()->get_option('icon_groupsize_single_tour', '<i class="stt-icon-user2"></i>');
$icon_language_single_tour = st()->get_option('icon_language_single_tour', '<i class="stt-icon-language"></i>');
?>
<!--Tour Info-->
<div class="st-service-feature">
    <div class="row">
        <div class="col-6 col-sm-6 col-md-3">
            <div class="item d-flex align-items-lg-center">
                <div class="icon">
                    <?php echo htmlspecialchars_decode($icon_duration_single_tour);?>
                </div>
                <div class="info">
                    <div class="name"><?php echo __('Duration', 'traveler'); ?></div>
                    <p class="value">
                        <?php
                        $duration = get_post_meta(get_the_ID(), 'duration_day', true);
                        echo esc_html($duration);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-6 col-md-3">
            <div class="item d-flex align-items-lg-center">
                <div class="icon">
                    <?php echo htmlspecialchars_decode($icon_tourtype_single_tour);?>
                </div>
                <div class="info">
                    <div class="name"><?php echo __('Tour Type', 'traveler'); ?></div>
                    <p class="value">
                        <?php

                        if ($tour_type == 'daily_tour') {
                            echo __('Daily Tour', 'traveler');
                        } else {
                            echo __('Specific Tour', 'traveler');
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-6 col-md-3">
            <div class="item d-flex align-items-lg-center">
                <div class="icon">
                    <?php echo htmlspecialchars_decode($icon_duration_single_tour);?>
                </div>
                <div class="info">
                    <div class="name"><?php echo __('Start Time', 'traveler'); ?></div>
                    <p class="value">
                        <?php
                        $start_time = get_post_meta(get_the_ID(), 'start_time_custom', true);
                        echo esc_html($start_time);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-6 col-md-3">
            <div class="item d-flex align-items-lg-center">
                <div class="icon">
                    <?php echo htmlspecialchars_decode($icon_language_single_tour);?>
                </div>
                <div class="info">
                    <div class="name"><?php echo __('Languages', 'traveler'); ?></div>
                    <p class="value">
                        <?php
                        $term_list = wp_get_post_terms(get_the_ID(), 'languages');
                        if(empty($term_list)){
                            $term_list = wp_get_post_terms(get_the_ID(), 'language');
                        }

                        $str_term_arr = [];
                        if (!is_wp_error($term_list) && !empty($term_list)) {
                            foreach ($term_list as $k => $v) {
                                array_push($str_term_arr, $v->name);
                            }

                            echo implode(', ', $str_term_arr);
                        } else {
                            echo '___';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--End Tour info-->