define([
    'uiComponent',
    'ko',
    'underscore'
], function (Component, ko, _) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio'
        },

        initObservable: function () {
            this._super();

            var config = this.studioData || (this.config && this.config.studioData) || this.config || {};
            var produto = config.produto || {};
            var gravacao = produto.gravacao || {};
            var initial = config.configuracao_inicial || {};
            var grupos = config.grupos || [];

            this.grupos = ko.observableArray(grupos);
            this.gradeTamanhos = ko.observableArray(produto.grade_tamanhos || []);
            this.activeGroupCode = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');
            this.hoveredBlockedCode = ko.observable(null); 

            this.selectedSize = ko.observable(
                initial.tamanho || (this.gradeTamanhos().length > 0 ? this.gradeTamanhos()[0] : null)
            );

            this.selections = {};
            var self = this;

            grupos.forEach(function (grupo) {
                var initialComp = null;
                if (initial.selecoes && initial.selecoes.length > 0) {
                    var found = initial.selecoes.find(function (s) { return s.grupo === grupo.codigo; });
                    if (found) initialComp = found.componente;
                }
                if (!initialComp && grupo.componentes && grupo.componentes.length > 0) {
                    var defaultComp = grupo.componentes.find(function (c) { return c.padrao; });
                    initialComp = defaultComp ? defaultComp.codigo : grupo.componentes[0].codigo;
                }
                self.selections[grupo.codigo] = ko.observable(initialComp);
            });

            this.permiteGravacao = ko.observable(Boolean(gravacao.permitida));
            this.laserPrice = ko.observable(parseFloat(gravacao.preco || 0));
            this.laserLimit = ko.observable(parseInt(gravacao.limite_caracteres || 12, 10));
            this.laserText = ko.observable(initial.gravacao !== undefined && initial.gravacao !== null ? String(initial.gravacao) : '');
            this.basePrice = ko.observable(parseFloat(produto.preco_base || 0));
            this.regras = config.regras || [];

            this.currentGroup = ko.pureComputed(function () {
                var code = self.activeGroupCode();
                return grupos.find(function (g) { return g.codigo === code; }) || null;
            });

            this.currentOptions = ko.pureComputed(function () {
                var group = self.currentGroup();
                return group && group.componentes ? group.componentes : [];
            });

            var getSelectedComponentsData = function () {
                var selected = [];
                grupos.forEach(function (grupo) {
                    var code = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (code) {
                        var comp = (grupo.componentes || []).find(function (c) { return c.codigo === code; });
                        if (comp) selected.push(comp);
                    }
                });
                return selected;
            };

            this.blockedMap = ko.pureComputed(function () {
                var blocked = {};
                var regras = self.regras || [];
                var selectedComponents = getSelectedComponentsData();

                regras.forEach(function (regra) {
                    var tipo = (regra.tipo || '').toLowerCase();
                    var origem = regra.origem_codigo || regra.origem;
                    var alvo = regra.alvo_codigo || regra.alvo;
                    var alvoTipo = (regra.alvo_tipo || '').toLowerCase();
                    var msg = regra.mensagem || 'Combinação indisponível.';

                    if (!origem) return;
                    var originIsSelected = selectedComponents.some(function (comp) { return comp.codigo === origem; });

                    if (tipo === 'bloqueia') {
                        if (alvoTipo === 'gravacao' || alvo === '*' || alvo === 'GRAVACAO' || alvo === 'gravacao') {
                            if (originIsSelected) blocked['gravacao'] = msg;
                        } else if (alvo) {
                            if (originIsSelected) {
                                blocked[alvo] = msg;
                            } else {
                                var targetIsSelected = selectedComponents.some(function (comp) { return comp.codigo === alvo; });
                                if (targetIsSelected) blocked[origem] = msg;
                            }
                        }
                    } else if (tipo === 'exige') {
                        // Correção de UX: Independente de estar selecionado, se o alvo não está satisfeito, bloqueia a origem
                        var requirementSatisfied = false;
                        if (alvoTipo === 'acabamento') {
                            requirementSatisfied = selectedComponents.some(function (comp) { return comp.acabamento === alvo; });
                        } else if (alvoTipo === 'componente' && alvo) {
                            requirementSatisfied = selectedComponents.some(function (comp) { return comp.codigo === alvo; });
                        }
                        if (!requirementSatisfied) {
                            blocked[origem] = msg;
                        }
                    }
                });
                return blocked;
            });

            this.blockedMap.subscribe(function (blocked) {
                _.each(self.selections, function (obs, grupoCode) {
                    var currentCode = obs();
                    if (currentCode && blocked[currentCode]) {
                        var grupo = grupos.find(function (g) { return g.codigo === grupoCode; });
                        if (grupo && grupo.componentes) {
                            var fallback = grupo.componentes.find(function (c) { return !blocked[c.codigo]; });
                            obs(fallback ? fallback.codigo : null);
                        }
                    }
                });
            });

            this.isLaserBlocked = ko.pureComputed(function () {
                return Boolean(self.blockedMap()['gravacao']);
            });

            this.laserBlockReason = ko.pureComputed(function () {
                return self.blockedMap()['gravacao'] || '';
            });

            this.mirrorPrice = ko.pureComputed(function () {
                var total = self.basePrice();
                getSelectedComponentsData().forEach(function (opt) {
                    if (!self.isBlocked(opt.codigo)) {
                        total += self.getOptionPrice(opt);
                    }
                });
                if (self.permiteGravacao() && !self.isLaserBlocked() && self.laserText().trim().length > 0) {
                    total += self.laserPrice();
                }
                return parseFloat(total.toFixed(2));
            });

            this.formattedMirrorPrice = ko.pureComputed(function () {
                return 'R$ ' + self.mirrorPrice().toFixed(2).replace('.', ',');
            });

            this.laserRemainingChars = ko.pureComputed(function () {
                return Math.max(0, self.laserLimit() - self.laserText().length);
            });

            this.selectionsFormatted = ko.pureComputed(function () {
                var formatted = [];
                var index = 0;
                _.each(self.selections, function (obs, grupoCode) {
                    var comp = obs();
                    if (comp && !self.isBlocked(comp)) {
                        formatted.push({
                            index: index++,
                            grupo: grupoCode,
                            componente: comp
                        });
                    }
                });
                return formatted;
            });

            return this;
        },

        getOptionColor: function (opt) {
            if (!opt) return '#E6E1F2';
            return opt.cor_hex || opt.hex || '#E6E1F2';
        },

        getOptionPrice: function (opt) {
            if (!opt) return 0;
            var parsed = parseFloat(opt.preco_adicional || 0);
            return !isNaN(parsed) ? parsed : 0;
        },

        isBlocked: function (optionCode) {
            return Boolean(this.blockedMap()[optionCode]);
        },

        isSoldOut: function (opt) {
            return false; 
        },

        showBlockReason: function (optionCode) {
            if (this.isBlocked(optionCode)) {
                this.hoveredBlockedCode(optionCode);
            }
        },

        hideBlockReason: function () {
            this.hoveredBlockedCode(null);
        },

        getBlockReason: function (optionCode) {
            return this.blockedMap()[optionCode] || '';
        },

        isSelected: function (groupCode, optionCode) {
            if (this.isBlocked(optionCode)) return false; 
            return this.selections[groupCode] && this.selections[groupCode]() === optionCode;
        },

        selectOption: function (groupCode, option) {
            if (this.isBlocked(option.codigo)) {
                this.showBlockReason(option.codigo); 
                return;
            }
            if (this.selections[groupCode]) {
                this.selections[groupCode](option.codigo);
            }
        },

        selectGroup: function (groupCode) {
            this.activeGroupCode(groupCode);
            this.hideBlockReason(); 
        },

        selectSize: function (size) {
            this.selectedSize(size);
        },

        getSelectedComponent: function (groupCode) {
            return this.selections[groupCode] ? this.selections[groupCode]() : '';
        }
    });
});
