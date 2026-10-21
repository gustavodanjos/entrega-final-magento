define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio'
        },

        initObservable: function () {
            this._super();

            var self = this;
            var config = this.configurador || {};
            var grupos = config.grupos || [];
            var regras = config.regras || [];

            this.grupos = ko.observableArray(grupos);
            this.activeTab = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');
            this.selectedSize = ko.observable('38');
            this.laserText = ko.observable('');
            this.laserPrice = ko.observable(29.90);
            this.basePrice = ko.observable(config.preco_base ? parseFloat(config.preco_base) : 449.90);
            this.activeTooltip = ko.observable(null);

            // Mapeia observables reativos por grupo
            this.selections = {};
            grupos.forEach(function (grupo) {
                var initialOption = (grupo.opcoes && grupo.opcoes.length > 0) ? grupo.opcoes[0].codigo : '';
                self.selections[grupo.codigo] = ko.observable(initialOption);
            });

            // Mapa reativo de componentes bloqueados (suporte a bloqueia e exige)
            this.blockedMap = ko.computed(function () {
                var blocked = {};

                function isSelected(code) {
                    return Object.keys(self.selections).some(function (gCode) {
                        return self.selections[gCode]() === code;
                    });
                }

                regras.forEach(function (regra) {
                    var origem = regra.origem_codigo || regra.origem;
                    var alvo = regra.alvo_codigo || regra.alvo;
                    var msg = regra.mensagem || 'Combinação indisponível para esta configuração.';

                    if (regra.tipo === 'bloqueia') {
                        if (isSelected(origem)) {
                            blocked[alvo] = msg;
                        } else if (isSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    } else if (regra.tipo === 'exige') {
                        if (!isSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    }
                });

                return blocked;
            });

            // Preço espelho calculado dinamicamente
            this.mirrorPrice = ko.computed(function () {
                var total = self.basePrice();

                grupos.forEach(function (grupo) {
                    var selectedCode = self.selections[grupo.codigo]();
                    var found = (grupo.opcoes || []).find(function (opt) {
                        return opt.codigo === selectedCode;
                    });
                    if (found && found.acrescimo) {
                        total += parseFloat(found.acrescimo);
                    }
                });

                if (self.laserText().trim().length > 0) {
                    total += self.laserPrice();
                }

                return total;
            });

            this.formattedMirrorPrice = ko.computed(function () {
                return self.mirrorPrice().toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                });
            });

            this.laserRemainingChars = ko.computed(function () {
                var max = 12;
                return Math.max(0, max - self.laserText().length);
            });

            return this;
        },

        isBlocked: function (optionCode) {
            return !!this.blockedMap()[optionCode];
        },

        getBlockReason: function (optionCode) {
            return this.blockedMap()[optionCode] || '';
        },

        isSelected: function (groupCode, optionCode) {
            return this.selections[groupCode]() === optionCode;
        },

        selectOption: function (groupCode, option) {
            if (this.isBlocked(option.codigo)) {
                // Bloqueado não é desabilitado: abre/fecha balão com o motivo
                var current = this.activeTooltip();
                this.activeTooltip(current === option.codigo ? null : option.codigo);
                return;
            }

            this.activeTooltip(null);
            this.selections[groupCode](option.codigo);
        },

        closeTooltip: function () {
            this.activeTooltip(null);
        },

        setActiveTab: function (tabCode) {
            this.activeTab(tabCode);
            this.activeTooltip(null);
        }
    });
});
