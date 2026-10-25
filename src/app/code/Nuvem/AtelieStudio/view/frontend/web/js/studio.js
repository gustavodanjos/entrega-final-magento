define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    /**
     * Custom Binding solicitado no backlog (F3.3).
     * Associa os grupos às camadas SVG estritamente via camada_svg do catálogo.
     * Atualiza a cor (fill/stroke) reativamente conforme a seleção do Knockout.
     */
    ko.bindingHandlers.atelieCamada = {
        init: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
            if (typeof ko.bindingHandlers.atelieCamada.update === 'function') {
                ko.bindingHandlers.atelieCamada.update(element, valueAccessor, allBindings, viewModel, bindingContext);
            }
        },
        update: function (element, valueAccessor, allBindings, viewModel, bindingContext) {
            var rawVal = valueAccessor ? ko.unwrap(valueAccessor()) : null;
            var camadaId = (typeof rawVal === 'string' && rawVal.length > 0)
                ? rawVal
                : (element ? element.id : '');
            if (!camadaId) {
                return;
            }

            var vm = (bindingContext && bindingContext.$data) || viewModel;
            if (!vm || typeof vm.getLayerColor !== 'function') {
                return;
            }

            var color = vm.getLayerColor(camadaId);
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
                if (attr !== 'fill' && attr !== 'stroke') {
                    attr = 'fill';
                }
                if (target.getAttribute(attr) !== color) {
                    target.setAttribute(attr, color);
                }
            }
        }
    };

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio'
        },

        initObservable: function () {
            this._super();

            var rawConfig = this.getConfigData();
            var catalog = this.normalizeCatalog(rawConfig);

            this.catalog = catalog;
            this.initCatalogObservables(catalog);
            this.initSelectionObservables(catalog, rawConfig.configuracao_inicial || {});
            this.initComputed();
            this.autoResolveBlockedSelections();
            this.initDocumentListeners();

            return this;
        },

        initDocumentListeners: function () {
            var self = this;
            if (typeof document === 'undefined') {
                return;
            }

            // Garante fechar tooltips ao clicar fora das amostras ou com Escape
            this._documentClickHandler = function (event) {
                if (self.activeTooltip() && (!event.target.closest || !event.target.closest('.atelie-swatch-wrapper'))) {
                    self.activeTooltip(null);
                }
            };
            document.addEventListener('click', this._documentClickHandler);

            this._documentKeyHandler = function (event) {
                if (event.key === 'Escape' && self.activeTooltip()) {
                    self.activeTooltip(null);
                }
            };
            document.addEventListener('keydown', this._documentKeyHandler);
        },

        getConfigData: function () {
            return this.studioData
                || this.configurador
                || (this.config && (this.config.studioData || this.config.configurador))
                || {};
        },

        /**
         * Normaliza o payload bruto do catálogo em entidades canônicas do domínio.
         * Garante contrato único e desacopla a UI de variações ad-hoc do JSON.
         */
        normalizeCatalog: function (raw) {
            var rawProduct = raw.produto || {};
            var rawGrupos = raw.grupos || [];
            var rawRegras = raw.regras || [];

            var product = {
                sku: String(rawProduct.sku || ''),
                nome: String(rawProduct.nome || 'Ateliê Nuvem'),
                precoBase: parseFloat(rawProduct.preco_base) || 0,
                prazoProducaoDias: parseInt(rawProduct.prazo_producao_dias, 10) || 0,
                gradeTamanhos: Array.isArray(rawProduct.grade_tamanhos) ? rawProduct.grade_tamanhos : [],
                gravacao: {
                    permitida: !!(rawProduct.gravacao && rawProduct.gravacao.permitida),
                    preco: parseFloat(rawProduct.gravacao && rawProduct.gravacao.preco) || 0,
                    limiteCaracteres: parseInt(rawProduct.gravacao && rawProduct.gravacao.limite_caracteres, 10) || 12,
                    placeholder: (rawProduct.gravacao && rawProduct.gravacao.placeholder) || 'Ex: NUVEM'
                }
            };

            var componentMap = {};
            var grupos = rawGrupos.map(function (g) {
                var rawComps = g.componentes || g.opcoes || g.itens || [];
                var componentes = rawComps.map(function (c) {
                    var precoAdicional = parseFloat(c.preco_adicional);
                    if (isNaN(precoAdicional)) {
                        precoAdicional = 0;
                    }
                    var comp = {
                        codigo: String(c.codigo),
                        nome: String(c.nome || c.codigo),
                        corHex: String(c.cor_hex || '#FFFFFF'),
                        acabamento: String(c.acabamento || ''),
                        precoAdicional: precoAdicional,
                        disponivel: c.disponivel !== false,
                        padrao: !!c.padrao,
                        grupoCodigo: String(g.codigo),
                        // Compatibilidade retroativa de propriedades
                        cor_hex: String(c.cor_hex || '#FFFFFF'),
                        preco_adicional: precoAdicional
                    };
                    componentMap[comp.codigo] = comp;
                    return comp;
                });

                return {
                    codigo: String(g.codigo),
                    nome: String(g.nome || g.codigo),
                    ordem: parseInt(g.ordem, 10) || 0,
                    camadaSvg: String(g.camada_svg || ''),
                    descricao: g.descricao || '',
                    componentes: componentes,
                    // Compatibilidade de acesso
                    itens: componentes
                };
            });

            // Ordena os grupos estritamente pela coluna ordem do banco
            grupos.sort(function (a, b) {
                return a.ordem - b.ordem;
            });

            var self = this;
            var regras = rawRegras.map(function (r) {
                var tipo = String(r.tipo || '').toLowerCase().trim();
                var alvoTipo = self.normalize(r.alvo_tipo || r.alvoTipo || '');
                return {
                    codigo: String(r.codigo || ''),
                    tipo: tipo,
                    origem: String(r.origem || '').trim(),
                    alvoTipo: alvoTipo,
                    alvo: String(r.alvo || '').trim(),
                    mensagem: String(r.mensagem || 'Combinação indisponível para esta configuração.')
                };
            });

            return {
                product: product,
                grupos: grupos,
                componentMap: componentMap,
                regras: regras
            };
        },

        initCatalogObservables: function (catalog) {
            this.grupos = ko.observableArray(catalog.grupos);
            this.productName = ko.observable(catalog.product.nome);
            this.prazoProducao = ko.observable(catalog.product.prazoProducaoDias);
            this.basePrice = ko.observable(catalog.product.precoBase);
            this.gradeTamanhos = ko.observableArray(catalog.product.gradeTamanhos);

            this.permiteGravacao = ko.observable(catalog.product.gravacao.permitida);
            this.laserPrice = ko.observable(catalog.product.gravacao.preco);
            this.laserLimit = ko.observable(catalog.product.gravacao.limiteCaracteres);
            this.laserPlaceholder = ko.observable(catalog.product.gravacao.placeholder);

            // A aba inicial é sempre o primeiro código disponível da lista ordenada
            var firstTab = catalog.grupos.length > 0 ? catalog.grupos[0].codigo : '';
            this.activeTab = ko.observable(firstTab);
            this.activeTooltip = ko.observable(null);
        },

        initSelectionObservables: function (catalog, cfgInicial) {
            var self = this;
            var selecoesIniciais = cfgInicial.selecoes || [];
            var selecaoPorGrupo = {};

            selecoesIniciais.forEach(function (s) {
                if (s && s.grupo && s.componente) {
                    selecaoPorGrupo[s.grupo] = s.componente;
                }
            });

            var tamanhoInicial = parseInt(cfgInicial.tamanho, 10);
            var gradeArr = this.gradeTamanhos();
            if (isNaN(tamanhoInicial) || gradeArr.indexOf(tamanhoInicial) === -1) {
                tamanhoInicial = gradeArr.indexOf(38) !== -1 ? 38 : (gradeArr[0] || 38);
            }
            this.selectedSize = ko.observable(tamanhoInicial);

            var gravacaoInicial = cfgInicial.gravacao || '';
            this.laserText = ko.observable(gravacaoInicial ? String(gravacaoInicial) : '');

            this.selections = {};
            catalog.grupos.forEach(function (grupo) {
                var codigoInicial = selecaoPorGrupo[grupo.codigo];

                var encontrado = (grupo.componentes || []).some(function (c) {
                    return c.codigo === codigoInicial;
                });

                if (!encontrado) {
                    var padrao = null;
                    var comps = grupo.componentes || [];
                    for (var p = 0; p < comps.length; p++) {
                        if (comps[p].padrao) {
                            padrao = comps[p];
                            break;
                        }
                    }
                    if (padrao) {
                        codigoInicial = padrao.codigo;
                    } else if (comps.length > 0) {
                        codigoInicial = comps[0].codigo;
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
                return lista[0] || { codigo: '', nome: '', componentes: [] };
            });

            this.currentOptions = ko.pureComputed(function () {
                return self.currentGroup().componentes || [];
            });

            this.selectedComponents = ko.pureComputed(function () {
                var map = {};
                self.grupos().forEach(function (grupo) {
                    var codigo = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (codigo && self.catalog.componentMap[codigo]) {
                        map[grupo.codigo] = self.catalog.componentMap[codigo];
                    }
                });
                return map;
            });

            this.selectedComponentsList = ko.pureComputed(function () {
                var list = [];
                var selMap = self.selectedComponents();
                Object.keys(selMap).forEach(function (k) {
                    list.push(selMap[k]);
                });
                return list;
            });

            this.blockedMap = ko.pureComputed(function () {
                return self.computeBlockedMap(self.selectedComponentsList());
            });

            this.invalidSelections = ko.pureComputed(function () {
                return self.computeInvalidSelections(self.selectedComponentsList());
            });

            this.esgotadoMap = ko.pureComputed(function () {
                var map = {};
                self.grupos().forEach(function (grupo) {
                    (grupo.componentes || []).forEach(function (opt) {
                        if (!opt.disponivel) {
                            map[opt.codigo] = true;
                        }
                    });
                });
                return map;
            });

            this.engravingBlockedReason = ko.pureComputed(function () {
                return self.computeEngravingBlockReason(self.selectedComponentsList());
            });

            this.engravingBlocked = ko.pureComputed(function () {
                return self.engravingBlockedReason() !== '';
            });

            this.mirrorPrice = ko.pureComputed(function () {
                var total = self.basePrice();

                self.grupos().forEach(function (grupo) {
                    var codigo = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;
                    if (codigo && self.catalog.componentMap[codigo]) {
                        total += self.catalog.componentMap[codigo].precoAdicional;
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

            this.formattedBasePrice = ko.pureComputed(function () {
                return self.formatPrice(self.basePrice());
            });

            this.extraPriceText = ko.pureComputed(function () {
                var diff = Math.round((self.mirrorPrice() - self.basePrice()) * 100) / 100;
                return diff > 0 ? '+ ' + self.formatPrice(diff) + ' em escolhas suas' : '';
            });

            this.laserRemainingChars = ko.pureComputed(function () {
                var max = self.laserLimit();
                return Math.max(0, max - self.laserText().length);
            });

            this.laserCounterText = ko.pureComputed(function () {
                var current = (self.laserText() || '').length;
                var max = self.laserLimit();
                return current + '/' + max;
            });

            this.validationErrors = ko.pureComputed(function () {
                return self.computeValidationErrors();
            });

            this.isValid = ko.pureComputed(function () {
                return self.validationErrors().length === 0;
            });

            this.engravingPreviewText = ko.pureComputed(function () {
                if (!self.permiteGravacao() || self.engravingBlocked()) {
                    return '';
                }
                var text = self.laserText() || '';
                if (text.length > self.laserLimit()) {
                    text = text.substring(0, self.laserLimit());
                }
                return text;
            });

            this.svgAriaLabel = ko.pureComputed(function () {
                return self.buildAriaLabel();
            });
        },

        computeBlockedMap: function (selectedList) {
            var self = this;
            var blocked = {};

            function isCodeSelected(code) {
                return selectedList.some(function (opt) {
                    return self.normalize(opt.codigo) === self.normalize(code);
                });
            }

            function isFinishSelected(finish) {
                return selectedList.some(function (opt) {
                    return self.normalize(opt.acabamento) === self.normalize(finish);
                });
            }

            self.catalog.regras.forEach(function (regra) {
                var tipo = regra.tipo;
                var origem = regra.origem;
                var alvoTipo = regra.alvoTipo;
                var alvo = regra.alvo;
                var msg = regra.mensagem;

                if (tipo === 'bloqueia') {
                    if (alvoTipo === 'componente') {
                        if (isCodeSelected(origem)) {
                            blocked[alvo] = msg;
                        }
                        if (isCodeSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    } else if (alvoTipo === 'acabamento') {
                        if (isCodeSelected(origem)) {
                            self.catalog.grupos.forEach(function (grupo) {
                                grupo.componentes.forEach(function (opt) {
                                    if (self.normalize(opt.acabamento) === self.normalize(alvo)) {
                                        blocked[opt.codigo] = msg;
                                    }
                                });
                            });
                        }
                        if (isFinishSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    }
                } else if (tipo === 'exige') {
                    if (alvoTipo === 'acabamento') {
                        if (!isFinishSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    } else if (alvoTipo === 'componente') {
                        if (!isCodeSelected(alvo)) {
                            blocked[origem] = msg;
                        }
                    } else if (alvoTipo === 'gravacao') {
                        var hasEngraving = !!(self.laserText && self.laserText().trim() !== '');
                        if (!hasEngraving) {
                            blocked[origem] = msg;
                        }
                    }
                }
            });

            return blocked;
        },

        computeInvalidSelections: function (selectedList) {
            var self = this;
            var invalid = {};

            function isCodeSelected(code) {
                return selectedList.some(function (opt) {
                    return self.normalize(opt.codigo) === self.normalize(code);
                });
            }

            function isFinishSelected(finish) {
                return selectedList.some(function (opt) {
                    return self.normalize(opt.acabamento) === self.normalize(finish);
                });
            }

            self.catalog.regras.forEach(function (regra) {
                var tipo = regra.tipo;
                var origem = regra.origem;
                var alvoTipo = regra.alvoTipo;
                var alvo = regra.alvo;
                var msg = regra.mensagem;

                if (tipo === 'bloqueia') {
                    if (alvoTipo === 'componente') {
                        if (isCodeSelected(origem) && isCodeSelected(alvo)) {
                            invalid[alvo] = msg;
                        }
                    } else if (alvoTipo === 'acabamento') {
                        if (isCodeSelected(origem) && isFinishSelected(alvo)) {
                            invalid[origem] = msg;
                        }
                    }
                } else if (tipo === 'exige') {
                    if (isCodeSelected(origem)) {
                        var alvoOk = false;
                        if (alvoTipo === 'acabamento') {
                            alvoOk = isFinishSelected(alvo);
                        } else if (alvoTipo === 'componente') {
                            alvoOk = isCodeSelected(alvo);
                        } else if (alvoTipo === 'gravacao') {
                            alvoOk = !!(self.laserText && self.laserText().trim() !== '');
                        }
                        if (!alvoOk) {
                            invalid[origem] = msg;
                        }
                    }
                }
            });

            return invalid;
        },

        computeEngravingBlockReason: function (selectedList) {
            var self = this;
            if (!self.permiteGravacao()) {
                return '';
            }

            var reason = '';
            self.catalog.regras.forEach(function (regra) {
                var alvoTipo = self.normalize(regra.alvoTipo);
                if (regra.tipo === 'bloqueia' && alvoTipo === 'gravacao') {
                    var origemSelecionada = selectedList.some(function (opt) {
                        return self.normalize(opt.codigo) === self.normalize(regra.origem);
                    });
                    if (origemSelecionada) {
                        reason = regra.mensagem;
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
                    var opt = self.catalog.componentMap[codigo];
                    if (opt && self.isEsgotado(opt.codigo)) {
                        errors.push('"' + (opt.nome || opt.codigo) + '" está esgotado. Escolha outra opção.');
                    }
                }
            });

            var invalidMap = self.invalidSelections();
            if (invalidMap) {
                Object.keys(invalidMap).forEach(function (k) {
                    errors.push(invalidMap[k]);
                });
            }

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
            return /^[A-Za-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u00FF0-9 \-]+$/.test(text);
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

        isEsgotado: function (code) {
            return !!this.esgotadoMap()[code];
        },

        isSelected: function (grupoCodigo, code) {
            var sel = this.selections[grupoCodigo];
            return !!(sel && sel() === code);
        },

        isExigeRule: function (code) {
            var self = this;
            var normCode = self.normalize(code);
            return self.catalog.regras.some(function (r) {
                return r.tipo === 'exige' && self.normalize(r.origem) === normCode;
            });
        },

        getOptionHint: function (code) {
            var self = this;
            var normCode = self.normalize(code);
            for (var i = 0; i < self.catalog.regras.length; i++) {
                var r = self.catalog.regras[i];
                if (r.tipo === 'exige' && self.normalize(r.origem) === normCode) {
                    return r.mensagem;
                }
            }
            return '';
        },

        selectOption: function (grupoCodigo, option) {
            if (!option) {
                return;
            }

            if (this.isEsgotado(option.codigo)) {
                return;
            }

            if (this.isBlocked(option.codigo)) {
                var currentActive = this.activeTooltip();
                this.activeTooltip(currentActive === option.codigo ? null : option.codigo);
                return;
            }

            this.activeTooltip(null);
            if (this.selections[grupoCodigo]) {
                this.selections[grupoCodigo](option.codigo);
                this.autoResolveBlockedSelections();
            }
        },

        /**
         * Auto-resolve seleções de grupos que se tornaram incompatíveis/bloqueadas
         * após a alteração de outro componente, revertendo-as para o padrão válido do grupo.
         */
        autoResolveBlockedSelections: function () {
            var self = this;
            var changed = true;
            var iterations = 0;

            // Itera até estabilizar as dependências (máximo de 3 passagens)
            while (changed && iterations < 3) {
                changed = false;
                iterations++;
                var blocked = self.blockedMap();

                for (var i = 0; i < self.grupos().length; i++) {
                    var grupo = self.grupos()[i];
                    var currentCode = self.selections[grupo.codigo] ? self.selections[grupo.codigo]() : null;

                    if (currentCode && blocked[currentCode]) {
                        var fallback = null;
                        var compsList = grupo.componentes || [];
                        for (var cIdx = 0; cIdx < compsList.length; cIdx++) {
                            var candidate = compsList[cIdx];
                            if (candidate.padrao && !blocked[candidate.codigo] && !self.isEsgotado(candidate.codigo)) {
                                fallback = candidate;
                                break;
                            }
                        }
                        if (!fallback) {
                            for (var cIdx2 = 0; cIdx2 < compsList.length; cIdx2++) {
                                var candidate2 = compsList[cIdx2];
                                if (!blocked[candidate2.codigo] && !self.isEsgotado(candidate2.codigo)) {
                                    fallback = candidate2;
                                    break;
                                }
                            }
                        }
                        if (fallback && fallback.codigo !== currentCode) {
                            self.selections[grupo.codigo](fallback.codigo);
                            changed = true;
                        }
                    }
                }
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

        getGroupDescription: function (grupo) {
            if (!grupo) {
                return '';
            }
            if (grupo.descricao) {
                return grupo.descricao;
            }
            var descriptions = {
                'cabedal': 'a parte de cima, que abraça o pé',
                'biqueira': 'a ponta que protege os dedos',
                'calcanhar': 'apoio traseiro e firmeza',
                'cadarco': 'amarração e ajuste no peito do pé',
                'emblema': 'o detalhe da nossa nuvem artesanal',
                'entressola': 'o amortecimento e base do conforto',
                'solado': 'o contato direto com o chão'
            };
            return descriptions[grupo.codigo] || '';
        },

        getOptionColor: function (opt) {
            if (!opt) {
                return '#E6E1F2';
            }
            return opt.corHex || opt.cor_hex || '#E6E1F2';
        },

        /**
         * F3.3: Localiza o grupo correspondente a uma camada SVG específica.
         * Associa estritamente pelo atributo camada_svg retornado pelo catálogo/banco.
         */
        getGroupByCamada: function (camadaId) {
            if (!camadaId) {
                return null;
            }
            var grupos = this.grupos ? this.grupos() : [];
            // Prioriza camada_svg (camadaSvg) definida no banco
            for (var i = 0; i < grupos.length; i++) {
                var g = grupos[i];
                var camadaSvg = g.camada_svg || g.camadaSvg;
                if (camadaSvg && camadaSvg === camadaId) {
                    return g;
                }
            }
            // Fallback de compatibilidade caso dados de teste não incluam camada_svg explícita
            for (var j = 0; j < grupos.length; j++) {
                var fallbackG = grupos[j];
                if (fallbackG.codigo === camadaId || ('camada-' + fallbackG.codigo) === camadaId) {
                    return fallbackG;
                }
            }
            return null;
        },

        /**
         * F3.3: Obtém a cor atual selecionada para a camada indicada.
         * Garante dependência reativa do Knockout sem acoplamento a grupos específicos.
         */
        getLayerColor: function (camadaId) {
            if (!camadaId) {
                return null;
            }
            var grupo = this.getGroupByCamada(camadaId);
            if (!grupo) {
                return null;
            }

            if (this.selections && this.selections[grupo.codigo]) {
                this.selections[grupo.codigo]();
            }
            var selectedMap = this.selectedComponents ? this.selectedComponents() : {};
            var selectedOpt = selectedMap[grupo.codigo];

            return selectedOpt ? (selectedOpt.corHex || selectedOpt.cor_hex || null) : null;
        },

        getOptionPrice: function (opt) {
            if (!opt) {
                return 0;
            }
            if (typeof opt.precoAdicional === 'number') {
                return opt.precoAdicional;
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
            var str = String(s).toLowerCase();
            if (typeof str.normalize === 'function') {
                return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
            }
            return str.replace(/[áàãâä]/g, 'a')
                .replace(/[éèêë]/g, 'e')
                .replace(/[íìîï]/g, 'i')
                .replace(/[óòõôö]/g, 'o')
                .replace(/[úùûü]/g, 'u')
                .replace(/[ç]/g, 'c')
                .trim();
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

            var canEngrave = this.permiteGravacao() && !this.engravingBlocked();
            var gravacaoVal = (canEngrave && this.laserText().trim() !== '') ? this.laserText() : null;

            return {
                tamanho: parseInt(this.selectedSize(), 10) || null,
                gravacao: gravacaoVal,
                selecoes: selecoes
            };
        },

        getUrl: function (key) {
            var cfg = this.getConfigData();
            return (cfg.urls && cfg.urls[key]) || '';
        },

        buildAriaLabel: function () {
            var parts = [];
            var nome = this.productName ? this.productName() : '';
            if (nome) {
                parts.push(nome);
            }

            var selections = this.selectedComponents ? this.selectedComponents() : {};
            var listaGrupos = this.grupos ? this.grupos() : [];
            listaGrupos.forEach(function (grupo) {
                var opt = selections[grupo.codigo];
                if (opt && opt.nome) {
                    parts.push(grupo.nome + ': ' + opt.nome);
                }
            });

            var laser = this.laserText ? String(this.laserText() || '').trim() : '';
            if (laser !== '' && (!this.engravingBlocked || !this.engravingBlocked())) {
                parts.push('Gravação: ' + laser);
            }

            return parts.length ? (parts.join('. ') + '.') : 'Pré-visualização do tênis personalizado.';
        }
    });
});
