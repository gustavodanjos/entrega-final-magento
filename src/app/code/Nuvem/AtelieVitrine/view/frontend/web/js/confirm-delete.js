define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function ($, confirmation) {
    'use strict';

    return function (config, element) {
        $(element).on('submit', function (event) {
            event.preventDefault();

            var form = element;

            confirmation({
                title: $.mage.__('Excluir criação'),
                content: $.mage.__('Deseja realmente excluir esta criação?'),
                actions: {
                    confirm: function () {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }
            });
        });
    };
});
