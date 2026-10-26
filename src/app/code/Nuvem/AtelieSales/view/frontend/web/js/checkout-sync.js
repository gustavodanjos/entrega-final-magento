define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/model/quote'
], function ($, customerData, getTotals, quote) {
    'use strict';

    return function () {
        var cart = customerData.get('cart'),
            signature,
            pending = false,
            dirty = false,
            containsAtelie = hasAtelieItems(cart());

        function hasAtelieItems(data) {
            return Object.keys(data.atelie_items || {}).length > 0;
        }

        function getSignature(data) {
            return JSON.stringify([data.atelie_items, data.subtotalAmount, data.items]);
        }

        function refreshTotals() {
            if (pending) {
                dirty = true;
                return;
            }
            pending = true;
            getTotals([]).fail(function () {
                signature = null;
            }).always(function () {
                pending = false;
                if (dirty) {
                    dirty = false;
                    refreshTotals();
                }
            });
        }

        function sync(data) {
            var next = getSignature(data),
                relevant = containsAtelie || hasAtelieItems(data);

            containsAtelie = hasAtelieItems(data);
            if (!relevant || !quote.totals() || next === signature) {
                return;
            }
            signature = next;
            refreshTotals();
        }

        cart.subscribe(sync);
        sync(cart());
        $(window).on('storage.atelieCheckout', function (event) {
            var original = event.originalEvent,
                previous,
                next;

            if (original.key !== 'mage-cache-storage') {
                return;
            }
            try {
                previous = JSON.parse(original.oldValue || '{}').cart || {};
                next = JSON.parse(original.newValue || '{}').cart || {};
                if ((hasAtelieItems(previous) || hasAtelieItems(next)) &&
                    getSignature(previous) !== getSignature(next)) {
                    customerData.reload(['cart'], false);
                }
            } catch (error) {
                if (containsAtelie) {
                    customerData.reload(['cart'], false);
                }
            }
        });
        $(window).on('focus.atelieCheckout', function () {
            if (containsAtelie || hasAtelieItems(cart())) {
                customerData.reload(['cart'], false);
            }
        });
    };
});
