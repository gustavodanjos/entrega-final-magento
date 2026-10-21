define([
    'uiComponent',
    'ko',
    'underscore',
    'mage/translate',
    'Magento_Catalog/js/price-utils'
], function (Component, ko, _, $t, priceUtils) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio',
            studioData: {},
            activeGroup: null,
            selectedSize: null,
            engravingText: ''
        },

        initObservable: function () {
            this._super();
            this.observe(['activeGroup', 'selectedSize', 'engravingText']);

            this.selections = {};
            this.groups = (this.studioData && this.studioData.grupos) ? this.studioData.grupos : [];
            this.product = (this.studioData && this.studioData.produto) ? this.studioData.produto : {};
            this.rules = (this.studioData && this.studioData.regras) ? this.studioData.regras : [];

            var initialConfig = (this.studioData && this.studioData.configuracao_inicial) ? this.studioData.configuracao_inicial : {};
            var initialSelectionsMap = {};

            if (initialConfig.selecoes && Array.isArray(initialConfig.selecoes)) {
                _.each(initialConfig.selecoes, function (item) {
                    initialSelectionsMap[item.grupo] = item.componente;
                });
            }

            _.each(this.groups, function (group) {
                var defaultCode = initialSelectionsMap[group.codigo];
                if (!defaultCode) {
                    var defaultComp = _.find(group.componentes || [], function (c) {
                        return c.padrao;
                    });
                    defaultCode = defaultComp ? defaultComp.codigo : (group.componentes && group.componentes[0] ? group.componentes[0].codigo : null);
                }
                this.selections[group.codigo] = ko.observable(defaultCode);
            }, this);

            if (this.groups.length > 0 && !this.activeGroup()) {
                this.activeGroup(this.groups[0].codigo);
            }

            if (initialConfig.tamanho) {
                this.selectedSize(initialConfig.tamanho);
            } else if (this.product.grade_tamanhos && this.product.grade_tamanhos.length > 0) {
                this.selectedSize(this.product.grade_tamanhos[0]);
            }

            if (initialConfig.gravacao) {
                this.engravingText(initialConfig.gravacao);
            }

            this.blockedMap = ko.pureComputed(this.computeBlockedMap, this);
            this.mirrorPrice = ko.pureComputed(this.calculatePrice, this);
            this.formattedMirrorPrice = ko.pureComputed(function () {
                return this.formatCurrency(this.mirrorPrice());
            }, this);

            this.canonicalSelections = ko.pureComputed(this.computeCanonicalSelections, this);

            this.remainingEngravingChars = ko.pureComputed(function () {
                var limit = (this.product.gravacao && this.product.gravacao.limite_caracteres) ? this.product.gravacao.limite_caracteres : 12;
                return Math.max(0, limit - (this.engravingText() || '').length);
            }, this);

            return this;
        },

        selectGroup: function (groupCode) {
            this.activeGroup(groupCode);
        },

        isActiveGroup: function (groupCode) {
            return this.activeGroup() === groupCode;
        },

        selectComponent: function (groupCode, component) {
            if (this.isComponentBlocked(groupCode, component.codigo)) {
                return;
            }
            if (this.selections[groupCode]) {
                this.selections[groupCode](component.codigo);
            }
        },

        isComponentSelected: function (groupCode, componentCode) {
            return this.selections[groupCode] ? this.selections[groupCode]() === componentCode : false;
        },

        selectSize: function (size) {
            this.selectedSize(size);
        },

        isSizeSelected: function (size) {
            return this.selectedSize() === size;
        },

        findComponent: function (groupCode, componentCode) {
            var group = _.find(this.groups, function (g) {
                return g.codigo === groupCode;
            });
            if (!group || !group.componentes) {
                return null;
            }
            return _.find(group.componentes, function (c) {
                return c.codigo === componentCode;
            }) || null;
        },

        calculatePrice: function () {
            var total = parseFloat(this.product.preco_base || 0);

            _.each(this.groups, function (group) {
                var selectedCode = this.selections[group.codigo] ? this.selections[group.codigo]() : null;
                if (selectedCode) {
                    var component = this.findComponent(group.codigo, selectedCode);
                    if (component && component.preco_adicional) {
                        total += parseFloat(component.preco_adicional);
                    }
                }
            }, this);

            var text = (this.engravingText() || '').trim();
            if (text.length > 0 && this.product.gravacao && this.product.gravacao.permitida) {
                total += parseFloat(this.product.gravacao.preco || 0);
            }

            return Math.round(total * 100) / 100;
        },

        computeBlockedMap: function () {
            var blocked = {};
            var activeComponents = {};
            var activeFinishes = {};

            _.each(this.groups, function (group) {
                var selectedCode = this.selections[group.codigo] ? this.selections[group.codigo]() : null;
                if (selectedCode) {
                    activeComponents[selectedCode] = true;
                    var comp = this.findComponent(group.codigo, selectedCode);
                    if (comp && comp.acabamento) {
                        activeFinishes[comp.acabamento] = true;
                    }
                }
            }, this);

            _.each(this.rules, function (rule) {
                var isOriginActive = (rule.origem_tipo === 'componente' && activeComponents[rule.origem_codigo]) ||
                                     (rule.origem_tipo === 'acabamento' && activeFinishes[rule.origem_codigo]);

                if (isOriginActive && rule.tipo === 'bloqueia') {
                    if (rule.alvo_tipo === 'componente') {
                        blocked[rule.alvo_codigo] = rule.mensagem || $t('Combinação indisponível');
                    } else if (rule.alvo_tipo === 'acabamento') {
                        blocked['finish:' + rule.alvo_codigo] = rule.mensagem || $t('Acabamento incompatível');
                    } else if (rule.alvo_tipo === 'gravacao') {
                        blocked['gravacao'] = rule.mensagem || $t('Gravação incompatível com esta seleção');
                    }
                }
            }, this);

            return blocked;
        },

        isComponentBlocked: function (groupCode, componentCode) {
            var map = this.blockedMap();
            if (map[componentCode]) {
                return true;
            }
            var comp = this.findComponent(groupCode, componentCode);
            return !!(comp && comp.acabamento && map['finish:' + comp.acabamento]);
        },

        getBlockedReason: function (groupCode, componentCode) {
            var map = this.blockedMap();
            if (map[componentCode]) {
                return map[componentCode];
            }
            var comp = this.findComponent(groupCode, componentCode);
            return (comp && comp.acabamento && map['finish:' + comp.acabamento]) ? map['finish:' + comp.acabamento] : '';
        },

        isEngravingBlocked: function () {
            return !!this.blockedMap()['gravacao'];
        },

        getEngravingBlockedReason: function () {
            return this.blockedMap()['gravacao'] || '';
        },

        computeCanonicalSelections: function () {
            var list = [];
            var index = 0;
            _.each(this.groups, function (group) {
                var code = this.selections[group.codigo] ? this.selections[group.codigo]() : null;
                if (code) {
                    list.push({
                        index: index++,
                        grupo: group.codigo,
                        componente: code
                    });
                }
            }, this);
            return list;
        },

        formatCurrency: function (value) {
            var priceFormat = (window.checkoutConfig && window.checkoutConfig.priceFormat) ? window.checkoutConfig.priceFormat : {
                pattern: 'R$ %s',
                precision: 2,
                requiredPrecision: 2,
                decimalSymbol: ',',
                groupSymbol: '.',
                groupLength: 3,
                integerRequired: 1
            };
            return priceUtils.formatPrice(value, priceFormat);
        },

        formatExtraPrice: function (component) {
            var extra = parseFloat(component.preco_adicional || 0);
            return extra > 0 ? '+ ' + this.formatCurrency(extra) : '';
        }
    });
});
