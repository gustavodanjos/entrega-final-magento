define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function (Thumbnail) {
        return Thumbnail.extend({
            getSrc: function (item) {
                var images = customerData.get('cart')().atelie_items || {},
                    configured = images[item.item_id];

                return configured ? configured.image : this._super(item);
            }
        });
    };
});
