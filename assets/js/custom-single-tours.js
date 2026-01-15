;(function ($) {
    "use strict";

    let SingleTourDetailNew = {
        jQuerybody: jQuery('body'),
        renderHtmlTour(){
            var form = jQuery('form.tour-booking-form');
            var data = jQuery('form.tour-booking-form').serializeArray();
            jQuery('.loader-wrapper').hide();
            data.push({
                name: 'security',
                value: st_params._s
            });
            for (var i = 0; i < data.length; i++) {
                if(data[i].name === 'action'){
                    data[i]['value'] = 'st_format_tour_price';
                }
            };
            jQuery.ajax({
                method: "post",
                dataType: 'json',
                data: data,
                url: st_params.ajax_url,
                beforeSend: function () {
                    jQuery('div.message-wrapper').html("");
                    jQuery('.loader-wrapper').show();
                    jQuery('.message_box').html('');
                },
                success: function (response) {
                    jQuery('.loader-wrapper').hide();
                    if (response) {
                        if (response.price_html) {
							if (jQuery('.total-price-book').length > 0) {
                                jQuery('.total-price-book').html(response.price_html);
                            }
							if (jQuery('.form-head .price').length > 0) {
                                jQuery('.form-head .price').html(response.price_html);
							}
                            if (jQuery('.st-tour-booking__price--item').length > 0) {
                                jQuery('.st-tour-booking__price--item').html(response.price_html);
                            }
                            if (jQuery('.hotel-target-book-mobile').length > 0) {
                                jQuery('.hotel-target-book-mobile .price-wrapper').html(response.price_html);
                            }
                            jQuery('.message_box').html('');
                            jQuery('div.message-wrapper').html("");
                        } else {
                            if(response.message){
                                jQuery('#form-booking-inpage .message-wrapper').html('<div class="alert alert-danger"> <button type="button" class="close" data-bs-dismiss="alert" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button> '+response.message+ ' </div>');
                            } else {
                                if(response.price_from){
                                    if (jQuery('.form-head .price').length >= 0) {
                                        jQuery('.form-head .price').html(response.price_from);

                                    }
                                }
                            }

                        }
                    }
                }
            });
        },

        init: function () {
            let _that = this;
            let iex = 0;

            jQuery(document).on('click','.li_select',function(e){
                // e.preventDefault();
                var self = $(this),
                    package_value = self.attr('data-value'),
                    package_adult_price = self.attr('data-price-adult'),
                    package_child_price = self.attr('data-price-children');
                    jQuery('.st-form-package_new input[name="package_price_adult"]').val(package_adult_price).change();
                    jQuery('.st-form-package_new input[name="package_price_child"]').val(package_child_price).change();
                    jQuery('.st-form-package_new input[name="package_select"]').val(package_value).change();
                    _that.renderHtmlTour();
            });
        }
    }


    jQuery(document).ready(function(){
        if ( jQuery('.st_combobox-list-display').length ){
            SingleTourDetailNew.init();
        }
    });
})(jQuery);