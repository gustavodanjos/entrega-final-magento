define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    function normalizeStr(val) {
        if (!val) {
            return '';
        }
        return String(val)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio'
        },

        initObservable: function () {
            this._super();
            var self = this;

            var config = this.studioData || (this.config && this.config.studioData) || {};
            var produto = config.produto || {};
            var grupos = config.grupos || [];
            var regras = config.regras || [];
            var gravacao = produto.gravacao || config.gravacao || {};

            grupos.forEach(function (group) {
                group.itens = group.componentes || group.opcoes || group.itens || [];
            });

            this.grupos = ko.observableArray(grupos);
            this.productName = ko.observable(produto.nome || produto.name || 'Nuvem Runner');
            this.activeTab = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');
            this.activeTooltip = ko.observable(null);

            this.currentGroup = ko.computed(function () {
                var tab = self.activeTab();
                var list = self.grupos();
                for (var i = 0; i < list.length; i++) {
                    if (list[i].codigo === tab) {
                        return list[i];
                    }
                }
                return list[0] || { codigo: '', nome: '', itens: [] };
            });

            this.currentOptions = ko.computed(function () {
                return self.currentGroup().itens || [];
            });

            var gradeDisponivel = produto.grade_tamanhos || config.grade_tamanhos || ['34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];
            this.gradeTamanhos = ko.observableArray(gradeDisponivel);

            var initialSize = (config.configuracao_inicial && config.configuracao_inicial.tamanho)
                ? config.configuracao_inicial.tamanho
                : (gradeDisponivel.indexOf('38') !== -1 ? '38' : gradeDisponivel[0]);
            this.selectedSize = ko.observable(initialSize);

            var isGravacaoPermitida = gravacao.permitida !== undefined
                ? Boolean(gravacao.permitida)
                : (gravacao.permitido !== undefined ? Boolean(gravacao.permitido) : true);
            this.permiteGravacao = ko.observable(isGravacaoPermitida);
            this.laserPrice = ko.observable(gravacao.preco !== undefined ? parseFloat(gravacao.preco) : 29.90);
            this.laserLimit = ko.observable(parseInt(gravacao.limite_caracteres || gravacao.limite || 12, 10));
            this.laserText = ko.observable('');

            var rawBasePrice = produto.preco_base !== undefined ? produto.preco_base : config.preco_base;
            this.basePrice = ko.observable(rawBasePrice !== undefined && rawBasePrice !== null ? parseFloat(rawBasePrice) : 0.0);

            this.selections = {};
            grupos.forEach(function (grupo) {
                var initialOption = '';
                if (config.configuracao_inicial) {
                    var initSel = config.configuracao_inicial.selecoes || config.configuracao_inicial;
                    if (Array.isArray(initSel)) {
                        var found = initSel.find(function (s) {
                            return (s.grupo || s.grupo_codigo) === grupo.codigo;
                        });
                        if (found) {
                            initialOption = found.componente || found.componente_codigo;
                        }
                    } else if (typeof initSel === 'object' && initSel[grupo.codigo]) {
                        initialOption = initSel[grupo.codigo];
                    }
                }
                if (!initialOption && grupo.itens && grupo.itens.length > 0) {
                    initialOption = grupo.itens[0].codigo;
                }
                self.selections[grupo.codigo] = ko.observable(initialOption);
            });

            this.blockedMap = ko.computed(function () {
                var blocked = {};
                var activeOptions = [];

                grupos.forEach(function (g) {
                    var selectedCode = self.selections[g.codigo] ? self.selections[g.codigo]() : null;
                    if (selectedCode && g.itens) {
                        var opt = g.itens.find(function (o) { return o.codigo === selectedCode; });
                        if (opt) {
                            activeOptions.push(opt);
                        }
                    }
                });

                function isCodeSelected(target) {
                    var normalizedTarget = normalizeStr(target);
                    return activeOptions.some(function (opt) {
                        return normalizeStr(opt.codigo) === normalizedTarget;
                    });
                }

                function isRequirementSatisfied(target) {
                    var normalizedTarget = normalizeStr(target);
                    return activeOptions.some(function (opt) {
                        var optCode = normalizeStr(opt.codigo);
                        var optAcab = normalizeStr(opt.acabamento);
                        return optCode === normalizedTarget || optAcab === normalizedTarget;
                    });
                }

                regras.forEach(function (regra) {
                    var origem = regra.origem_codigo || regra.origem || regra.componente_origem;
                    var alvo = regra.alvo_codigo || regra.alvo || regra.componente_alvo || regra.exige;
                    var msg = regra.mensagem || regra.descricao || 'Combinação indisponível para esta configuração.';

                    if (!origem || !alvo) {
                        return;
                    }

                    if (regra.tipo === 'bloqueia') {
                        if (isCodeSelected(origem)) {
                            blocked[alvo] = msg;
                        } else if (isCodeSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    } else if (regra.tipo === 'exige') {
                        if (!isRequirementSatisfied(alvo)) {
                            blocked[origem] = msg;
                        }
                    }
                });

                return blocked;
            });

            this.mirrorPrice = ko.computed(function () {
                var total = self.basePrice();
                grupos.forEach(function (grupo) {
                    var selectedCode = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    var found = (grupo.itens || []).find(function (opt) {
                        return opt.codigo === selectedCode;
                    });
                    if (found) {
                        total += self.getOptionPrice(found);
                    }
                });

                if (self.permiteGravacao() && self.laserText().trim().length > 0) {
                    total += self.laserPrice();
                }

                return Math.round(total * 100) / 100;
            });

            this.formattedMirrorPrice = ko.computed(function () {
                var val = self.mirrorPrice();
                return 'R$ ' + val.toFixed(2).replace('.', ',');
            });

            this.laserRemainingChars = ko.computed(function () {
                var max = self.laserLimit();
                return Math.max(0, max - self.laserText().length);
            });

            return this;
        },

        getOptionColor: function (opt) {
            if (!opt) {
                return '#E6E1F2';
            }
            var directHex = opt.hex || opt.cor_hex || opt.codigo_hex || opt.hex_code || opt.cor || opt.color;
            if (directHex && typeof directHex === 'string') {
                if (directHex.indexOf('#') === 0 || directHex.indexOf('rgb') === 0) {
                    return directHex;
                }
            }
            return '#E6E1F2';
        },

        getOptionPrice: function (opt) {
            if (!opt) {
                return 0;
            }
            var val = opt.acrescimo !== undefined ? opt.acrescimo :
                     (opt.preco_adicional !== undefined ? opt.preco_adicional :
                     (opt.preco_extra !== undefined ? opt.preco_extra :
                     (opt.adicional !== undefined ? opt.adicional : null)));

            if (val !== null && val !== undefined) {
                var parsed = parseFloat(val);
                if (!isNaN(parsed) && parsed > 0) {
                    return parsed;
                }
            }
            return 0;
        },

        isBlocked: function (optionCode) {
            return Boolean(this.blockedMap()[optionCode]);
        },

        getBlockReason: function (optionCode) {
            return this.blockedMap()[optionCode] || '';
        },

        isSelected: function (groupCode, optionCode) {
            if (!this.selections || !this.selections[groupCode]) {
                return false;
            }
            return this.selections[groupCode]() === optionCode;
        },

        getSelectedComponent: function (groupCode) {
            if (this.selections && this.selections[groupCode]) {
                return this.selections[groupCode]();
            }
            return '';
        },

        selectOption: function (groupCode, option, event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (this.isBlocked(option.codigo)) {
                var current = this.activeTooltip();
                this.activeTooltip(current === option.codigo ? null : option.codigo);
                return;
            }

            this.activeTooltip(null);
            if (this.selections[groupCode]) {
                this.selections[groupCode](option.codigo);
            }
        },

        switchTab: function (groupCode) {
            this.activeTab(groupCode);
            this.activeTooltip(null);
        },

        selectSize: function (size) {
            this.selectedSize(size);
        },

        closeTooltip: function () {
            this.activeTooltip(null);
        }
    });
});