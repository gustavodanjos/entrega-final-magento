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

            var config = this.getConfigData();

            this.initCatalog(config);
            this.initObservables(config);
            this.initSelections(config);
            this.initComputed();

            return this;
        },

        getConfigData: function () {
            return this.studioData
                || this.configurador
                || (this.config && (this.config.studioData || this.config.configurador))
                || {};
        },

        initCatalog: function (config) {
            var grupos = config.grupos || [];

            grupos = grupos.map(function (grupo) {
                var itens = grupo.componentes || grupo.opcoes || grupo.itens || [];
                grupo.itens = itens;
                return grupo;
            });

            grupos.sort(function (a, b) {
                return (parseInt(a.ordem, 10) || 0) - (parseInt(b.ordem, 10) || 0);
            });

            this.grupos = ko.observableArray(grupos);
            this.regras = config.regras || [];

            var produto = config.produto || {};
            this.productName = ko.observable(produto.nome || 'Ateliê Nuvem');
            this.prazoProducao = ko.observable(parseInt(produto.prazo_producao_dias, 10) || 0);

            var basePrice = parseFloat(produto.preco_base);
            this.basePrice = ko.observable(!isNaN(basePrice) ? basePrice : 0);

            var grade = produto.grade_tamanhos || [];
            this.gradeTamanhos = ko.observableArray(grade);

            var gravacao = produto.gravacao || {};
            this.permiteGravacao = ko.observable(!!gravacao.permitida);
            this.laserPrice = ko.observable(parseFloat(gravacao.preco) || 0);
            this.laserLimit = ko.observable(parseInt(gravacao.limite_caracteres, 10) || 12);
            this.laserPlaceholder = ko.observable(gravacao.placeholder || 'Ex: NUVEM');

            this.activeTab = ko.observable(grupos.length > 0 ? grupos[0].codigo : '');
        },

        initObservables: function (config) {
            var cfgInicial = config.configuracao_inicial || {};

            var tamanhoInicial = parseInt(cfgInicial.tamanho, 10);
            if (isNaN(tamanhoInicial) || this.gradeTamanhos.indexOf(tamanhoInicial) === -1) {
                var gradeArr = this.gradeTamanhos();
                tamanhoInicial = gradeArr.indexOf(38) !== -1 ? 38 : (gradeArr[0] || null);
            }
            this.selectedSize = ko.observable(tamanhoInicial);

            var gravacaoInicial = cfgInicial.gravacao || '';
            this.laserText = ko.observable(gravacaoInicial ? String(gravacaoInicial) : '');

            this.activeTooltip = ko.observable(null);
        },

        initSelections: function (config) {
            var self = this;
            var cfgInicial = config.configuracao_inicial || {};
            var selecoesIniciais = cfgInicial.selecoes || [];

            var selecaoPorGrupo = {};
            selecoesIniciais.forEach(function (s) {
                selecaoPorGrupo[s.grupo] = s.componente;
            });

            this.selections = {};

            this.grupos().forEach(function (grupo) {
                var codigoInicial = selecaoPorGrupo[grupo.codigo];

                var encontrado = (grupo.itens || []).some(function (c) {
                    return c.codigo === codigoInicial;
                });

                if (!encontrado) {
                    var padrao = (grupo.itens || []).find(function (c) { return c.padrao; });
                    if (padrao) {
                        codigoInicial = padrao.codigo;
                    } else if (grupo.itens.length > 0) {
                        codigoInicial = grupo.itens[0].codigo;
                    } else {
                        codigoInicial = '';
                    }
                }

                self.selections[grupo.codigo] = ko.observable(codigoInicial);
            });
        },

        initComputed: function () {
            var self = this;

            this.currentGroup = ko.pureComputed(function () {
                var tab = self.activeTab();
                var lista = self.grupos();
                for (var i = 0; i < lista.length; i++) {
                    if (lista[i].codigo === tab) {
                        return lista[i];
                    }
                }
                return lista[0] || { codigo: '', nome: '', itens: [] };
            });

            this.currentOptions = ko.pureComputed(function () {
                return self.currentGroup().itens || [];
            });

            this.selectedComponents = ko.pureComputed(function () {
                var map = {};
                self.grupos().forEach(function (grupo) {
                    var codigo = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (codigo) {
                        var opt = (grupo.itens || []).find(function (c) { return c.codigo === codigo; });
                        if (opt) {
                            map[grupo.codigo] = opt;
                        }
                    }
                });
                return map;
            });

            this.blockedMap = ko.pureComputed(function () {
                return self.computeBlockedMap(self.selectedComponents());
            });

            this.invalidSelections = ko.pureComputed(function () {
                return self.computeInvalidSelections(self.selectedComponents());
            });

            this.esgotadoMap = ko.pureComputed(function () {
                var map = {};
                self.grupos().forEach(function (grupo) {
                    (grupo.itens || []).forEach(function (opt) {
                        if (opt.disponivel === false) {
                            map[opt.codigo] = true;
                        }
                    });
                });
                return map;
            });

            this.engravingBlockedReason = ko.pureComputed(function () {
                return self.computeEngravingBlockReason(self.selectedComponents());
            });

            this.engravingBlocked = ko.pureComputed(function () {
                return self.engravingBlockedReason() !== '';
            });

            this.mirrorPrice = ko.pureComputed(function () {
                var total = self.basePrice();

                self.grupos().forEach(function (grupo) {
                    var codigo = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (!codigo) {
                        return;
                    }
                    var opt = (grupo.itens || []).find(function (c) { return c.codigo === codigo; });
                    if (opt) {
                        total += self.getOptionPrice(opt);
                    }
                });

                if (self.permiteGravacao() && !self.engravingBlocked() && self.laserText().trim() !== '') {
                    total += self.laserPrice();
                }

                return Math.round(total * 100) / 100;
            });

            this.formattedMirrorPrice = ko.pureComputed(function () {
                return self.formatPrice(self.mirrorPrice());
            });

            this.laserRemainingChars = ko.pureComputed(function () {
                var max = self.laserLimit();
                return Math.max(0, max - self.laserText().length);
            });

            this.validationErrors = ko.pureComputed(function () {
                return self.computeValidationErrors();
            });

            this.isValid = ko.pureComputed(function () {
                return self.validationErrors().length === 0;
            });
        },

        computeBlockedMap: function (selectedMap) {
            var self = this;
            var blocked = {};

            var selectedList = [];
            _.each(selectedMap, function (opt) {
                selectedList.push(opt);
            });

            function isSelected(value, tipoAlvo) {
                return selectedList.some(function (opt) {
                    if (tipoAlvo === 'acabamento') {
                        return self.normalize(opt.acabamento) === self.normalize(value);
                    }
                    return self.normalize(opt.codigo) === self.normalize(value);
                });
            }

            self.regras.forEach(function (regra) {
                var tipo = self.normalize(regra.tipo);
                var origem = regra.origem;
                var alvoTipo = self.normalize(regra.alvo_tipo || regra.alvoTipo || '');
                var alvo = regra.alvo;
                var msg = regra.mensagem || 'Combinação indisponível para esta configuração.';

                if (tipo === 'bloqueia') {
                    if (alvoTipo === 'componente') {
                        if (isSelected(origem, 'componente') && !isSelected(alvo, 'componente')) {
                            blocked[alvo] = msg;
                        } else if (isSelected(alvo, 'componente') && !isSelected(origem, 'componente')) {
                            blocked[origem] = msg;
                        }
                    } else if (alvoTipo === 'acabamento') {
                        if (isSelected(origem, 'componente')) {
                            self.grupos().forEach(function (grupo) {
                                (grupo.itens || []).forEach(function (opt) {
                                    if (self.normalize(opt.acabamento) === self.normalize(alvo)) {
                                        blocked[opt.codigo] = msg;
                                    }
                                });
                            });
                        }
                    }
                } else if (tipo === 'exige') {
                    if (alvoTipo === 'acabamento') {
                        if (!isSelected(alvo, 'acabamento')) {
                            blocked[origem] = msg;
                        }
                    } else if (alvoTipo === 'componente') {
                        if (!isSelected(alvo, 'componente')) {
                            blocked[origem] = msg;
                        }
                    }
                }
            });

            return blocked;
        },

        computeInvalidSelections: function (selectedMap) {
            var self = this;
            var invalid = {};

            var selectedList = [];
            _.each(selectedMap, function (opt) {
                selectedList.push(opt);
            });

            function isSelected(value, tipoAlvo) {
                return selectedList.some(function (opt) {
                    if (tipoAlvo === 'acabamento') {
                        return self.normalize(opt.acabamento) === self.normalize(value);
                    }
                    return self.normalize(opt.codigo) === self.normalize(value);
                });
            }

            self.regras.forEach(function (regra) {
                var tipo = self.normalize(regra.tipo);
                var origem = regra.origem;
                var alvoTipo = self.normalize(regra.alvo_tipo || regra.alvoTipo || '');
                var alvo = regra.alvo;
                var msg = regra.mensagem || 'Combinação inválida.';

                if (tipo === 'bloqueia' && alvoTipo === 'componente') {
                    if (isSelected(origem, 'componente') && isSelected(alvo, 'componente')) {
                        invalid[alvo] = msg;
                    }
                } else if (tipo === 'exige') {
                    if (isSelected(origem, 'componente')) {
                        var alvoOk = (alvoTipo === 'acabamento')
                            ? isSelected(alvo, 'acabamento')
                            : isSelected(alvo, 'componente');
                        if (!alvoOk) {
                            invalid[origem] = msg;
                        }
                    }
                }
            });

            return invalid;
        },

        computeEngravingBlockReason: function (selectedMap) {
            var self = this;

            if (!self.permiteGravacao()) {
                return '';
            }

            var selectedList = [];
            _.each(selectedMap, function (opt) {
                selectedList.push(opt);
            });

            var reason = '';

            self.regras.forEach(function (regra) {
                var tipo = self.normalize(regra.tipo);
                var origem = regra.origem;
                var alvoTipo = self.normalize(regra.alvo_tipo || regra.alvoTipo || '');

                if (tipo === 'bloqueia' && alvoTipo === 'gravacao') {
                    var origemSelecionada = selectedList.some(function (opt) {
                        return self.normalize(opt.codigo) === self.normalize(origem);
                    });
                    if (origemSelecionada) {
                        reason = regra.mensagem || 'Esta combinação não permite gravação.';
                    }
                }
            });

            return reason;
        },

        computeValidationErrors: function () {
            var self = this;
            var errors = [];

            self.grupos().forEach(function (grupo) {
                var codigo = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                if (!codigo) {
                    errors.push('Escolha uma opção para ' + (grupo.nome || grupo.codigo) + '.');
                } else {
                    var opt = (grupo.itens || []).find(function (c) { return c.codigo === codigo; });
                    if (opt && self.isEsgotado(opt.codigo)) {
                        errors.push('"' + (opt.nome || opt.codigo) + '" está esgotado. Escolha outra opção.');
                    }
                }
            });

            _.each(self.invalidSelections(), function (msg) {
                errors.push(msg);
            });

            var tamanho = self.selectedSize();
            if (!tamanho || self.gradeTamanhos.indexOf(tamanho) === -1) {
                errors.push('Escolha um tamanho.');
            }

            if (self.permiteGravacao() && self.laserText().trim() !== '') {
                if (self.engravingBlocked()) {
                    errors.push(self.engravingBlockedReason());
                } else if (!self.isEngravingFormatValid()) {
                    errors.push('A gravação deve conter apenas letras, números, espaço e hífen.');
                } else if (self.laserText().length > self.laserLimit()) {
                    errors.push('A gravação excede o limite de ' + self.laserLimit() + ' caracteres.');
                }
            }

            return errors;
        },

        isEngravingFormatValid: function () {
            var text = this.laserText();
            if (text === '') {
                return true;
            }
            return /^[A-Za-zÀ-ÖØ-öø-ÿ0-9 \-]+$/u.test(text);
        },

        isBlocked: function (code) {
            return !!this.blockedMap()[code];
        },

        getBlockReason: function (code) {
            return this.blockedMap()[code] || '';
        },

        isInvalid: function (code) {
            return !!this.invalidSelections()[code];
        },

        /**
         * Estado "esgotado" — derivado do estoque carregado no JSON.
         * A F3.7 (polling) pode atualizar o estoque e este computed reage.
         */
        isEsgotado: function (code) {
            return !!this.esgotadoMap()[code];
        },

        isSelected: function (grupoCodigo, code) {
            var sel = this.selections[grupoCodigo];
            return !!(sel && sel() === code);
        },

        selectOption: function (grupoCodigo, option) {
            if (!option) {
                return;
            }

            if (this.isEsgotado(option.codigo) && !this.isSelected(grupoCodigo, option.codigo)) {
                return;
            }

            if (this.isBlocked(option.codigo) && !this.isSelected(grupoCodigo, option.codigo)) {
                this.activeTooltip(option.codigo);
                return;
            }

            this.activeTooltip(null);
            if (this.selections[grupoCodigo]) {
                this.selections[grupoCodigo](option.codigo);
            }
        },

        switchTab: function (grupoCodigo) {
            this.activeTab(grupoCodigo);
            this.activeTooltip(null);
        },

        handleTabKeydown: function (event, grupoCodigo) {
            var key = event.key;
            var grupos = this.grupos();
            var currentIndex = -1;
            for (var i = 0; i < grupos.length; i++) {
                if (grupos[i].codigo === grupoCodigo) { currentIndex = i; break; }
            }
            if (currentIndex === -1) { return true; }

            var newIndex;
            switch (key) {
                case 'ArrowRight':
                case 'ArrowDown':
                    newIndex = (currentIndex + 1) % grupos.length;
                    break;
                case 'ArrowLeft':
                case 'ArrowUp':
                    newIndex = (currentIndex - 1 + grupos.length) % grupos.length;
                    break;
                case 'Home':
                    newIndex = 0;
                    break;
                case 'End':
                    newIndex = grupos.length - 1;
                    break;
                default:
                    return true;
            }

            event.preventDefault();
            var newGroup = grupos[newIndex];
            this.switchTab(newGroup.codigo);

            var tabList = event.target.closest('[role="tablist"]');
            if (tabList) {
                var tabs = tabList.querySelectorAll('[role="tab"]');
                if (tabs[newIndex]) { tabs[newIndex].focus(); }
            }
            return false;
        },

        selectSize: function (size) {
            this.selectedSize(parseInt(size, 10));
        },

        closeTooltip: function () {
            this.activeTooltip(null);
        },

        getOptionColor: function (opt) {
            if (!opt) {
                return '#E6E1F2';
            }
            return opt.cor_hex || '#E6E1F2';
        },

        getOptionPrice: function (opt) {
            if (!opt) {
                return 0;
            }
            var v = parseFloat(opt.preco_adicional);
            return isNaN(v) ? 0 : v;
        },

        formatPrice: function (v) {
            var num = Math.round((parseFloat(v) || 0) * 100) / 100;
            return 'R$ ' + num.toFixed(2).replace('.', ',');
        },

        normalize: function (s) {
            if (s === null || s === undefined) {
                return '';
            }
            return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        },

        getPostData: function () {
            var self = this;
            var selecoes = [];
            this.grupos().forEach(function (grupo) {
                var codigo = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                if (codigo) {
                    selecoes.push({ grupo: grupo.codigo, componente: codigo });
                }
            });

            return {
                tamanho: parseInt(this.selectedSize(), 10) || null,
                gravacao: (this.permiteGravacao() && this.laserText().trim() !== '') ? this.laserText() : null,
                selecoes: selecoes
            };
        },

        getUrl: function (key) {
            var cfg = this.getConfigData();
            return (cfg.urls && cfg.urls[key]) || '';
        }
    });
});
