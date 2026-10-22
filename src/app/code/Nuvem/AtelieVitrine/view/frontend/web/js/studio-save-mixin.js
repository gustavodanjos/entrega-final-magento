define([
    'ko'
], function (ko) {
    'use strict';

    var storageKey = 'atelie_pending_creation';

    return function (Studio) {
        return Studio.extend({
            initObservable: function () {
                this._super();

                var self = this;
                var pending = null;
                var product = (this.studioData || {}).produto || {};

                try {
                    pending = JSON.parse(
                        window.sessionStorage.getItem(storageKey) || 'null'
                    );
                } catch (error) {
                    pending = null;
                }

                if (pending &&
                    pending.sku === product.sku &&
                    pending.returnUrl === window.location.href &&
                    pending.configuration) {

                    var configuration = pending.configuration;

                    this.selectedSize(configuration.tamanho);

                    this.laserText(configuration.gravacao || '');

                    (configuration.selecoes || []).forEach(function (selection) {
                        if (self.selections[selection.grupo]) {
                            self.selections[selection.grupo](selection.componente);
                        }
                    });
                }

                this.observe(['creationConfiguration']);

                this.creationConfigurationComputed = ko.computed(function () {
                    var selections = self.grupos().map(function (group) {
                        return {
                            grupo: group.codigo,
                            componente: self.selections[group.codigo]()
                        };
                    });

                    var engraving = self.laserText().trim();

                    return {
                        tamanho: parseInt(self.selectedSize(), 10),
                        gravacao: self.permiteGravacao() && engraving
                            ? engraving
                            : null,
                        selecoes: selections
                    };
                });

                this.creationConfiguration(
                    this.creationConfigurationComputed()
                );

                this.creationConfigurationComputed.subscribe(function (configuration) {
                    self.creationConfiguration(configuration);
                });

                return this;
            }
        });
    };
});
