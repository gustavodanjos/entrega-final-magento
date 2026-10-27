define([
    'ko',
    'mage/url'
], function (ko, urlBuilder) {
    'use strict';

    var storageKey = 'atelie_pending_creation';

    return function (Studio) {
        return Studio.extend({
            // F5.3: hashes de criacoes salvas pertencem ao modulo AtelieVitrine.
            // Nao devem ser interpretados como Base64 da F3.10.
            resolveInitialConfiguration: function (catalog, defaultCfg) {
                if (/^#c=[a-f0-9]{32}$/i.test(window.location.hash)) {
                    return defaultCfg;
                }

                return this._super(catalog, defaultCfg);
            },

            initObservable: function () {
                this._super();

                var self = this;
                var pending = null;
                var product = this.getConfigData().produto || {};

                this.creationLoadError = this.sharedNotice;

                try {
                    pending = JSON.parse(
                        window.sessionStorage.getItem(storageKey) || 'null'
                    );
                } catch (error) {
                    pending = null;
                }

                if (
                    !window.location.hash &&
                    pending &&
                    typeof pending.returnUrl === 'string' &&
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
                var validated = this.validateSharedConfig(configuration, this.catalog);
                if (!validated) {
                    this.creationLoadError('Esta criação usa opções que não estão mais disponíveis.');
                    return false;
                }
                var selections = {};
                validated.selecoes.forEach(function (selection) {
                    selections[selection.grupo] = selection.componente;
                });
                this.restoreSnapshot({
                    tamanho: validated.tamanho,
                    laserText: validated.gravacao || '',
                    selecoes: selections
                });
                this.history([this.createSnapshot()]);
                this.historyIndex(0);
                this.creationLoadError('');
                return true;
            },

            loadCreationFromHash: function () {
                var self = this;
                var match = window.location.hash.match(/^#c=([a-f0-9]{32})$/);
                var product = this.getConfigData().produto || {};

                if (!match) {
                    return;
                }

                var endpoint = new URL(
                    urlBuilder.build('atelie/creation/get'),
                    window.BASE_URL || window.location.origin + '/'
                );
                endpoint.searchParams.set('hash', match[1]);
                this.creationLoadError('Carregando a criação para você remixar...');
                fetch(endpoint.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Creation unavailable');
                    }
                    return response.json();
                }).then(function (response) {
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

                    self.applyCreationConfiguration(response.configuration);
                }).catch(function () {
                    self.creationLoadError(
                        'Esta criação não está disponível ou você não tem permissão para acessá-la.'
                    );
                });
            }
        });
    };
});
