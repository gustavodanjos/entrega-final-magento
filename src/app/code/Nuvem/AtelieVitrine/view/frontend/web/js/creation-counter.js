define([
    'uiComponent',
    'Magento_Customer/js/customer-data'
], function (Component, customerData) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();

            this.creations = customerData.get('atelie-criacoes');
            this.customer = customerData.get('customer');

            customerData.reload(['customer', 'atelie-criacoes'], true);

            return this;
        }
    });
});
