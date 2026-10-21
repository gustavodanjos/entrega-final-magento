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
            var config = this.studioData || {};
            var produto = config.produto || {};
            var initial = config.configuracao_inicial || {};
            var grupos = (config.grupos || []).map(function (grupo) {
                return Object.assign({}, grupo, {
                    opcoes: (grupo.componentes || []).map(function (componente) {
                        return Object.assign({}, componente, {
                            hex: componente.cor_hex,
                            acrescimo: componente.preco_adicional
                        });
                    })
                });
            });
            var regras = config.regras || [];
            var gravacao = produto.gravacao || {};

            this.grupos = ko.observableArray(grupos);
            this.activeTab = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');
            
            // Dados dinamicos fornecidos pelo ViewModel
            var gradeDisponivel = produto.grade_tamanhos || [];
            this.gradeTamanhos = ko.observableArray(gradeDisponivel);
            this.selectedSize = ko.observable(
                gradeDisponivel.indexOf(initial.tamanho) !== -1 ? initial.tamanho : gradeDisponivel[0]
            );

            this.permiteGravacao = ko.observable(!!gravacao.permitida);
            this.laserPrice = ko.observable(gravacao.preco !== undefined ? parseFloat(gravacao.preco) : 29.90);
            this.laserLimit = ko.observable(
                gravacao.limite_caracteres !== undefined ? parseInt(gravacao.limite_caracteres, 10) : 12
            );
            this.laserText = ko.observable(initial.gravacao || '');
            this.basePrice = ko.observable(parseFloat(produto.preco_base) || 0.0);
            this.activeTooltip = ko.observable(null);

            // Observables de selecao por grupo
            this.selections = {};
            grupos.forEach(function (grupo) {
                var selection = (initial.selecoes || []).find(function (item) {
                    return item.grupo === grupo.codigo;
                });
                var initialOption = selection ? selection.componente :
                    (grupo.opcoes.length > 0 ? grupo.opcoes[0].codigo : '');
                self.selections[grupo.codigo] = ko.observable(initialOption);
            });

            // Funcao auxiliar para normalizar strings (remove acentos e espacos)
            function normalizeStr(val) {
                if (!val) return '';
                return String(val)
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            }

            // Mapa reativo de componentes bloqueados (suporta bloqueia e exige com acabamento)
            this.blockedMap = ko.computed(function () {
                var blocked = {};

                // Recupera a lista de opcoes atualmente selecionadas com seus objetos completos
                var activeOptions = [];
                Object.keys(self.selections).forEach(function (gCode) {
                    var selectedCode = self.selections[gCode]();
                    var grupo = grupos.find(function (g) { return g.codigo === gCode; });
                    if (grupo && grupo.opcoes) {
                        var opt = grupo.opcoes.find(function (o) { return o.codigo === selectedCode; });
                        if (opt) activeOptions.push(opt);
                    }
                });

                function isCodeSelected(code) {
                    return activeOptions.some(function (opt) {
                        return normalizeStr(opt.codigo) === normalizeStr(code);
                    });
                }

                function isRequirementSatisfied(target) {
                    var normalizedTarget = normalizeStr(target);
                    return activeOptions.some(function (opt) {
                        var optCode = normalizeStr(opt.codigo);
                        var optAcab = normalizeStr(opt.acabamento);
                        return optCode === normalizedTarget || (optAcab && optAcab.indexOf(normalizedTarget) !== -1);
                    });
                }

                regras.forEach(function (regra) {
                    var origem = regra.origem_codigo || regra.origem;
                    var alvo = regra.alvo_codigo || regra.alvo || regra.alvo_acabamento;
                    var msg = regra.mensagem || 'Combinação indisponível para esta configuração.';

                    if (regra.tipo === 'bloqueia') {
                        if (isCodeSelected(origem)) {
                            blocked[alvo] = msg;
                        } else if (isCodeSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    } else if (regra.tipo === 'exige') {
                        // Se a exigencia (por codigo ou acabamento) nao estiver entre os selecionados, bloqueia a origem
                        if (!isRequirementSatisfied(alvo)) {
                            blocked[origem] = msg;
                        }
                    }
                });

                return blocked;
            });

            // Preco espelho reativo
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

                if (self.permiteGravacao() && self.laserText().trim().length > 0) {
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
            return this.selections[groupCode]() === optionCode;
        },

        selectOption: function (groupCode, option) {
            if (this.isBlocked(option.codigo)) {
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
