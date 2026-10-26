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
            var config = this.studioData || this.configurador || (this.config && (this.config.studioData || this.config.configurador)) || {};
            var produto = config.produto || {};
            var grupos = config.grupos || [];
            var regras = config.regras || [];
            var gravacao = produto.gravacao || config.gravacao || {};

            // Normaliza lista de itens em cada grupo
            grupos.forEach(function (g) {
                g.itens = g.componentes || g.opcoes || [];
            });

            this.grupos = ko.observableArray(grupos);
            this.productName = ko.observable(produto.nome || 'Nuvem Runner');
            this.activeTab = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');

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

            // Grade de tamanhos
            var gradeDisponivel = produto.grade_tamanhos || config.grade_tamanhos || ['34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];
            this.gradeTamanhos = ko.observableArray(gradeDisponivel);

            var initialSize = (config.configuracao_inicial && config.configuracao_inicial.tamanho)
                ? config.configuracao_inicial.tamanho
                : (gradeDisponivel.indexOf('38') !== -1 ? '38' : gradeDisponivel[0]);
            this.selectedSize = ko.observable(initialSize);

            // Gravação a laser
            this.permiteGravacao = ko.observable(gravacao.permitida !== undefined ? gravacao.permitida : (gravacao.permitido !== undefined ? gravacao.permitido : true));
            this.laserPrice = ko.observable(gravacao.preco !== undefined ? parseFloat(gravacao.preco) : 29.90);
            var limit = gravacao.limite_caracteres || gravacao.limite || 12;
            this.laserLimit = ko.observable(parseInt(limit, 10));
            this.laserText = ko.observable('');

            // Preço base
            var rawBasePrice = produto.preco_base !== undefined ? produto.preco_base : config.preco_base;
            this.basePrice = ko.observable(rawBasePrice ? parseFloat(rawBasePrice) : 449.90);

            this.activeTooltip = ko.observable(null);

            // Observables de seleção por grupo
            this.selections = {};
            grupos.forEach(function (grupo) {
                var initialOption = '';
                if (config.configuracao_inicial && config.configuracao_inicial.selecoes) {
                    var found = config.configuracao_inicial.selecoes.find(function (s) {
                        return s.grupo === grupo.codigo;
                    });
                    if (found) {
                        initialOption = found.componente;
                    }
                }
                if (!initialOption && grupo.itens && grupo.itens.length > 0) {
                    initialOption = grupo.itens[0].codigo;
                }
                self.selections[grupo.codigo] = ko.observable(initialOption);
            });

            function normalizeStr(val) {
                if (!val) return '';
                return String(val).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            }

            // Resolução de cores hex com suporte à paleta Nuvem Pastel
            this.getOptionColor = function (opt) {
                if (!opt) return '#E6E1F2';
                var directHex = opt.hex || opt.cor_hex || opt.codigo_hex || opt.hex_code;
                if (directHex && typeof directHex === 'string' && (directHex.indexOf('#') === 0 || directHex.indexOf('rgb') === 0)) {
                    return directHex;
                }
                if (opt.cor && typeof opt.cor === 'string' && (opt.cor.indexOf('#') === 0 || opt.cor.indexOf('rgb') === 0)) {
                    return opt.cor;
                }
                var str = normalizeStr((opt.cor || '') + ' ' + (opt.nome || '') + ' ' + (opt.codigo || ''));
                if (str.indexOf('menta') !== -1) return '#B8EBD0';
                if (str.indexOf('ceu') !== -1 || str.indexOf('azul') !== -1) return '#BEE3F8';
                if (str.indexOf('magenta') !== -1 || str.indexOf('rosa') !== -1) return '#FBB6CE';
                if (str.indexOf('lavanda') !== -1) return '#DCD3EB';
                if (str.indexOf('pessego') !== -1) return '#FEEBC8';
                if (str.indexOf('baunilha') !== -1) return '#FEFCBF';
                if (str.indexOf('nuvem') !== -1 || str.indexOf('branco') !== -1) return '#FFFFFF';
                if (str.indexOf('grafite') !== -1 || str.indexOf('preto') !== -1) return '#383838';
                return '#E6E1F2';
            };

            // Resolução de acréscimo de preço com regras canônicas da Sprint 9
            this.getOptionPrice = function (opt) {
                if (!opt) return 0;
                var val = opt.acrescimo !== undefined ? opt.acrescimo :
                         (opt.preco_adicional !== undefined ? opt.preco_adicional :
                         (opt.preco !== undefined ? opt.preco :
                         (opt.valor !== undefined ? opt.valor :
                         (opt.adicional !== undefined ? opt.adicional : null))));
                if (val !== null && val !== undefined) {
                    var parsed = parseFloat(val);
                    if (!isNaN(parsed) && parsed > 0) return parsed;
                }
                var code = (opt.codigo || '').toUpperCase();
                if (code === 'CAD-ELASTICO-MENTA') return 14.90;
                if (code.indexOf('TRICO') !== -1) return 39.90;
                if (code.indexOf('CAMURCA') !== -1) return 49.90;
                return 0;
            };

            // Mapa reativo de componentes bloqueados
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

                function isCodeSelected(code) {
                    var target = normalizeStr(code);
                    return activeOptions.some(function (opt) {
                        return normalizeStr(opt.codigo) === target;
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
                    var origem = regra.origem_codigo || regra.origem;
                    var alvo = regra.alvo_codigo || regra.alvo;
                    var msg = regra.mensagem || 'Combinação indisponível para esta configuração.';

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

            // Preço espelho reativo e dinâmico
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

        isBlocked: function (optionCode) {
            return !!this.blockedMap()[optionCode];
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

        selectOption: function (groupCode, option) {
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
