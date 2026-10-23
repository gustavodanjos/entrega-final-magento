define([
    'ko',
    'jquery',
    'mage/url'
], function (ko, $, urlBuilder) {
    'use strict';

    var storageKey = 'atelie_pending_creation';

    return function (Studio) {
        return Studio.extend({
            initObservable: function () {
                this._super();

                var self = this;
                var pending = null;
                var product = (this.studioData || {}).produto || {};

                this.creationLoadError = ko.observable('');

                try {
                    pending = JSON.parse(
                        window.sessionStorage.getItem(storageKey) || 'null'
                    );
                } catch (error) {
                    pending = null;
                }

                if (
                    pending &&
                    pending.sku === product.sku &&
                    pending.returnUrl.split('#')[0] === window.location.href.split('#')[0] &&
                    pending.configuration
                ) {
                    this.applyCreationConfiguration(pending.configuration);
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

                this.loadCreationFromHash();

                return this;
            },

            applyCreationConfiguration: function (configuration) {
                var self = this;

                if (
                    !configuration ||
                    !Number.isInteger(configuration.tamanho) ||
                    !Array.isArray(configuration.selecoes)
                ) {
                    return;
                }

                this.selectedSize(configuration.tamanho);
                this.laserText(configuration.gravacao || '');

                configuration.selecoes.forEach(function (selection) {
                    if (self.selections[selection.grupo]) {
                        self.selections[selection.grupo](
                            selection.componente
                        );
                    }
                });
            },


            loadCreationFromHash: function () {
                var self = this;
                var match = window.location.hash.match(/^#c=([a-f0-9]{32})$/);
                var product = (this.studioData || {}).produto || {};

                if (!match) {
                    return;
                }

                $.ajax({
                    url: urlBuilder.build('atelie/creation/get'),
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        hash: match[1]
                    }
                }).done(function (response) {
                    var pending = null;

                    if (
                        !response.success ||
                        response.sku !== product.sku ||
                        !response.configuration
                    ) {
                        self.creationLoadError(
                            'Não foi possível carregar esta criação.'
                        );
                        return;
                    }

                    try {
                        pending = JSON.parse(
                            window.sessionStorage.getItem(storageKey) || 'null'
                        );
                    } catch (error) {
                        pending = null;
                    }

                    if (
                        pending &&
                        pending.sku === product.sku &&
                        pending.returnUrl.split('#')[0] === window.location.href.split('#')[0] &&
                        pending.configuration
                    ) {
                        // Keep the changes made before login.
                        self.applyCreationConfiguration(pending.configuration);
                    } else {
                        // Load the original saved creation.
                        self.applyCreationConfiguration(response.configuration);
                    }

                    self.creationLoadError('');
                }).fail(function () {
                    self.creationLoadError(
                        'Esta criação não está disponível ou você não tem permissão para acessá-la.'
                    );
                });
            }
        });
    };
});
