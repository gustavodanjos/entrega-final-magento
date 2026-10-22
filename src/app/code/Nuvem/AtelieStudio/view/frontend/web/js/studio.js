define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    /**
     * Custom binding para repintura reativa das camadas SVG (fill nas peças e stroke no cadarço).
     */
    if (!ko.bindingHandlers.atelieCamada) {
        ko.bindingHandlers.atelieCamada = {
            update: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
                var groupCode = ko.unwrap(valueAccessor());
                if (!groupCode) {
                    return;
                }

                var vm = (bindingContext && bindingContext.$data) || viewModel;
                if (!vm) {
                    return;
                }

                // Garante dependência explícita de Knockout
                if (vm.selections && vm.selections[groupCode]) {
                    vm.selections[groupCode]();
                }

                var color = vm.getLayerColor(groupCode);
                if (!color) {
                    return;
                }

                var targets = element.querySelectorAll ? element.querySelectorAll('[data-pintar]') : [];
                if (targets.length === 0 && element.hasAttribute && element.hasAttribute('data-pintar')) {
                    targets = [element];
                }

                for (var i = 0; i < targets.length; i++) {
                    var target = targets[i];
                    var attr = target.getAttribute('data-pintar') || 'fill';
                    target.setAttribute(attr, color);
                }
            }
        };
    }

    /**
     * Custom binding para gravação a laser no SVG.
     * Cria o elemento <text> no namespace SVG programaticamente, contornando o bug do renderer.js do Magento 2.
     */
    if (!ko.bindingHandlers.atelieGravacao) {
        ko.bindingHandlers.atelieGravacao = {
            init: function (element) {
                var textNode = element.querySelector('#gravacao-texto');
                if (!textNode) {
                    textNode = element.ownerDocument.createElementNS('http://www.w3.org/2000/svg', 'text');
                    textNode.setAttribute('id', 'gravacao-texto');
                    textNode.setAttribute('x', '96');
                    textNode.setAttribute('y', '228');
                    textNode.setAttribute('font-family', 'Quicksand, Nunito, sans-serif');
                    textNode.setAttribute('font-size', '13');
                    textNode.setAttribute('font-weight', '700');
                    textNode.setAttribute('letter-spacing', '2.5');
                    textNode.setAttribute('fill', '#2E2A3B');
                    element.appendChild(textNode);
                }
            },
            update: function (element, valueAccessor) {
                var text = ko.unwrap(valueAccessor()) || '';
                var textNode = element.querySelector('#gravacao-texto');
                if (textNode) {
                    textNode.textContent = text;
                }
            }
        };
    }

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio'
        },

        initObservable: function () {
            this._super();

            var self = this;
            var config = this.studioData || (this.config && this.config.studioData) || this.config || {};
            var produto = config.produto || {};
            var gravacao = produto.gravacao || {};
            var initial = config.configuracao_inicial || {};
            var grupos = config.grupos || [];

            this.config = config;
            this.grupos = ko.observableArray(grupos);
            this.activeGroupCode = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');

            this.selectedSize = ko.observable(
                initial.tamanho || (produto.grade_tamanhos && produto.grade_tamanhos.length > 0 ? produto.grade_tamanhos[0] : 38)
            );

            this.selections = {};
            grupos.forEach(function (grupo) {
                var initialComp = null;
                if (initial.selecoes && initial.selecoes.length > 0) {
                    var found = initial.selecoes.find(function (s) {
                        return s.grupo === grupo.codigo;
                    });
                    if (found) {
                        initialComp = found.componente;
                    }
                }
                if (!initialComp && grupo.componentes && grupo.componentes.length > 0) {
                    var defaultComp = grupo.componentes.find(function (c) {
                        return c.padrao;
                    });
                    initialComp = defaultComp ? defaultComp.codigo : grupo.componentes[0].codigo;
                }
                self.selections[grupo.codigo] = ko.observable(initialComp);
            });

            this.currentGroup = ko.pureComputed(function () {
                var code = self.activeGroupCode();
                return grupos.find(function (g) {
                    return g.codigo === code;
                }) || null;
            });

            this.currentOptions = ko.pureComputed(function () {
                var group = self.currentGroup();
                return group && group.componentes ? group.componentes : [];
            });

            var isGravacaoPermitida = false;
            if (gravacao.permitida !== undefined) {
                isGravacaoPermitida = Boolean(gravacao.permitida);
            } else if (config.permite_gravacao !== undefined) {
                isGravacaoPermitida = Boolean(config.permite_gravacao);
            }

            this.permiteGravacao = ko.observable(isGravacaoPermitida);
            this.laserPrice = ko.observable(gravacao.preco !== undefined ? parseFloat(gravacao.preco) : 29.90);
            this.laserLimit = ko.observable(parseInt(gravacao.limite_caracteres || gravacao.limite || 12, 10));

            var initialLaserText = (initial.gravacao !== undefined && initial.gravacao !== null) ? String(initial.gravacao) : '';
            this.laserText = ko.observable(initialLaserText);

            var rawBasePrice = produto.preco_base !== undefined ? produto.preco_base : config.preco_base;
            this.basePrice = ko.observable(rawBasePrice !== undefined && rawBasePrice !== null ? parseFloat(rawBasePrice) : 449.90);

            this.regras = config.regras || [];

            function isCodeSelected(code) {
                if (!code) {
                    return false;
                }
                return Object.keys(self.selections).some(function (groupCode) {
                    return self.selections[groupCode]() === code;
                });
            }

            function isRequirementSatisfied(requirement) {
                if (!requirement) {
                    return true;
                }
                if (isCodeSelected(requirement)) {
                    return true;
                }
                var reqLower = requirement.toLowerCase();
                return Object.keys(self.selections).some(function (groupCode) {
                    var selCode = (self.selections[groupCode]() || '').toLowerCase();
                    return selCode.indexOf(reqLower) !== -1;
                });
            }

            this.blockedMap = ko.pureComputed(function () {
                var blocked = {};
                var regras = self.regras || [];

                regras.forEach(function (regra) {
                    var tipo = (regra.tipo || '').toLowerCase();
                    var alvoTipo = (regra.alvo_tipo || '').toLowerCase();
                    var origem = regra.origem_codigo || regra.origem || regra.componente_origem;
                    var alvo = regra.alvo_codigo || regra.alvo || regra.componente_alvo || regra.exige;
                    var msg = regra.mensagem || regra.descricao || 'Combinação indisponível para esta configuração.';

                    if (!origem) {
                        return;
                    }

                    var isGravacaoTarget = alvoTipo === 'gravacao' || alvo === '*' || alvo === 'GRAVACAO' || alvo === 'gravacao';

                    if (tipo === 'bloqueia') {
                        if (isGravacaoTarget) {
                            if (isCodeSelected(origem)) {
                                blocked['gravacao'] = msg;
                            }
                            if (self.laserText().trim().length > 0) {
                                blocked[origem] = msg;
                            }
                        } else if (alvo) {
                            if (isCodeSelected(origem)) {
                                blocked[alvo] = msg;
                            } else if (isCodeSelected(alvo)) {
                                blocked[origem] = msg;
                            }
                        }
                    } else if (tipo === 'exige') {
                        if (alvo && !isRequirementSatisfied(alvo)) {
                            blocked[origem] = msg;
                        }
                    }
                });
                return blocked;
            });

            this.isLaserBlocked = ko.pureComputed(function () {
                return Boolean(self.blockedMap()['gravacao']);
            });

            this.laserBlockReason = ko.pureComputed(function () {
                return self.blockedMap()['gravacao'] || '';
            });

            this.safeLaserText = ko.pureComputed(function () {
                return self.isLaserBlocked() ? '' : self.laserText();
            });

            this.mirrorPrice = ko.pureComputed(function () {
                var total = self.basePrice();
                grupos.forEach(function (grupo) {
                    var selectedCode = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (!selectedCode) {
                        return;
                    }
                    var opt = (grupo.componentes || []).find(function (c) {
                        return (c.codigo || c.code) === selectedCode;
                    });
                    if (opt) {
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

            this.svgAriaLabel = ko.pureComputed(function () {
                var parts = [produto.nome || config.nome || 'Tênis Nuvem Runner'];
                grupos.forEach(function (grupo) {
                    var selCode = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (!selCode) {
                        return;
                    }
                    var comp = (grupo.componentes || []).find(function (c) {
                        return (c.codigo || c.code) === selCode;
                    });
                    if (comp && comp.nome) {
                        parts.push(grupo.nome + ': ' + comp.nome);
                    }
                });
                if (self.permiteGravacao() && !self.isLaserBlocked() && self.laserText().trim().length > 0) {
                    parts.push('Gravação: ' + self.laserText().trim());
                }
                return parts.join(', ');
            });

            return this;
        },

        getOptionColor: function (opt) {
            if (!opt) {
                return '#E6E1F2';
            }
            var directHex = opt.hex || opt.cor_hex || opt.codigo_hex || opt.hex_code || opt.cor || opt.color;
            if (directHex && typeof directHex === 'string' && (directHex.indexOf('#') === 0 || directHex.indexOf('rgb') === 0)) {
                return directHex;
            }
            return '#E6E1F2';
        },

        getLayerColor: function (groupCodeSearch) {
            if (!groupCodeSearch) {
                return '';
            }
            var search = groupCodeSearch.toLowerCase();
            var grupos = this.config.grupos || [];
            
            var foundGroup = grupos.find(function(g) {
                var code = g.codigo || g.nome || '';
                return code.toLowerCase() === search;
            });
            if (!foundGroup) {
                return '';
            }

            var obs = this.selections[foundGroup.codigo];
            if (!obs) {
                return '';
            }

            var selectedCompCode = obs();
            if (!selectedCompCode) {
                return '';
            }

            var comp = (foundGroup.componentes || []).find(function(c) {
                return (c.codigo || c.code) === selectedCompCode;
            });
            return this.getOptionColor(comp);
        },

        getOptionPrice: function (opt) {
            if (!opt) {
                return 0;
            }
            var val = opt.acrescimo !== undefined ? opt.acrescimo :
                     (opt.preco_adicional !== undefined ? opt.preco_adicional :
                     (opt.preco_extra !== undefined ? opt.preco_extra : opt.adicional));
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

        isSoldOut: function (opt) {
            return opt && opt.estoque !== undefined && opt.estoque <= 0;
        },

        isSelected: function (groupCode, optionCode) {
            return this.selections && this.selections[groupCode] && this.selections[groupCode]() === optionCode;
        },

        selectOption: function (groupCode, option) {
            if (this.isBlocked(option.codigo) || this.isSoldOut(option)) {
                return;
            }
            if (this.selections && this.selections[groupCode]) {
                this.selections[groupCode](option.codigo);
            }
        },

        selectGroup: function (groupCode) {
            this.activeGroupCode(groupCode);
        },

        selectSize: function (size) {
            this.selectedSize(size);
        },

        getSelectedComponent: function (groupCode) {
            return (this.selections && this.selections[groupCode]) ? this.selections[groupCode]() : '';
        }
    });
});
