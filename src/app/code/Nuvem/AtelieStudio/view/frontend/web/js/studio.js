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
            this.disponibilidadeVersion = ko.observable(0);
            this.initCatalogObservables(catalog);

            var initialConfig = this.resolveInitialConfiguration(catalog, rawConfig.configuracao_inicial || {});
            this.initSelectionObservables(catalog, initialConfig);
            this.initComputed();
            this.autoResolveBlockedSelections();
            this.initHistory();
            this.initDocumentListeners();
            this.initAvailabilityPolling();
            this.initA11ySanitization();

            this.shareCreation = this.shareCreation.bind(this);
            this.dismissSharedNotice = this.dismissSharedNotice.bind(this);

            return this;
        },

        initA11ySanitization: function () {
            if (typeof document === 'undefined' || typeof document.querySelectorAll !== 'function') {
                return;
            }

            var sanitize = function () {
                var selector = '.nav-sections, .nav-sections-items, .nav-sections-item-title, .nav-sections-item-content, .navigation, .navigation ul, .navigation li, .navigation a';
                var badRoles = ['menu', 'menubar', 'menuitem', 'presentation', 'tablist', 'tab', 'tabpanel'];
                var elements = document.querySelectorAll(selector);
                for (var i = 0; i < elements.length; i++) {
                    var el = elements[i];
                    var role = el.getAttribute('role');
                    if (role && badRoles.indexOf(role) !== -1) {
                        el.removeAttribute('role');
                    }
                    if (el.hasAttribute('aria-selected')) {
                        el.removeAttribute('aria-selected');
                    }
                }
                var searchInput = document.getElementById('search');
                if (searchInput && searchInput.getAttribute('aria-haspopup') === 'false') {
                    searchInput.removeAttribute('aria-haspopup');
                }
            };

            sanitize();
            setTimeout(sanitize, 300);
            setTimeout(sanitize, 1000);

            if (typeof window !== 'undefined' && typeof window.MutationObserver !== 'undefined') {
                var isRunning = false;
                var observer = new MutationObserver(function () {
                    if (isRunning) return;
                    isRunning = true;
                    sanitize();
                    isRunning = false;
                });
                var navContainer = document.querySelector('.nav-sections') || document.body;
                if (navContainer) {
                    observer.observe(navContainer, {
                        attributes: true,
                        attributeFilter: ['role'],
                        subtree: true
                    });
                }
            }
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
                    return;
                }

                // Atalhos Ctrl+Z / Cmd+Z (Desfazer) e Ctrl+Y / Cmd+Shift+Z (Refazer)
                var isCtrlOrCmd = event.ctrlKey || event.metaKey;
                if (!isCtrlOrCmd) {
                    return;
                }

                var tag = (event.target && event.target.tagName) ? event.target.tagName.toLowerCase() : '';
                if (tag === 'input' || tag === 'textarea' || (event.target && event.target.isContentEditable)) {
                    return;
                }

                var key = event.key ? event.key.toLowerCase() : '';
                if (key === 'z') {
                    if (event.shiftKey) {
                        if (self.canRedo && self.canRedo()) {
                            event.preventDefault();
                            self.redo();
                        }
                    } else {
                        if (self.canUndo && self.canUndo()) {
                            event.preventDefault();
                            self.undo();
                        }
                    }
                } else if (key === 'y') {
                    if (self.canRedo && self.canRedo()) {
                        event.preventDefault();
                        self.redo();
                    }
                }
            };
            document.addEventListener('keydown', this._documentKeyHandler);
        },

        getConfigData: function () {
            return this.studioData
                || this.configurador
                || (this.config && (this.config.studioData || this.config.configurador))
                || (this.initialConfig && (this.initialConfig.studioData || this.initialConfig.configurador || (this.initialConfig.config && this.initialConfig.config.studioData)))
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
            this.isSubmitting = ko.observable(false);
            this.actionError = ko.observable('');
            this.isLaserFocused = ko.observable(false);
            this.isCopied = ko.observable(false);
            this.sharedNotice = ko.observable('');
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
            this._laserDebounceTimer = null;
            this.laserText.subscribe(function () {
                self.actionError('');
                if (self._isApplyingHistory) {
                    return;
                }
                if (self._laserDebounceTimer) {
                    clearTimeout(self._laserDebounceTimer);
                }
                self._laserDebounceTimer = setTimeout(function () {
                    self.pushSnapshot();
                }, 400);
            });

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
                self.disponibilidadeVersion();
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

            this.hasSoldOutSelection = ko.pureComputed(function () {
                self.disponibilidadeVersion();
                var selList = self.selectedComponentsList();
                return selList.some(function (opt) {
                    return self.isEsgotado(opt.codigo);
                });
            });

            this.isAddToCartDisabled = ko.pureComputed(function () {
                return self.isSubmitting() || self.hasSoldOutSelection() || !self.isValid();
            });

            this.engravingBlockedReason = ko.pureComputed(function () {
                return self.computeEngravingBlockReason(self.selectedComponentsList());
            });

            this.engravingBlocked = ko.pureComputed(function () {
                return self.engravingBlockedReason() !== '';
            });

            this.engravingBlocked.subscribe(function (isBlocked) {
                if (isBlocked) {
                    self.laserText('');
                }
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

            if (self.permiteGravacao() && !self.engravingBlocked() && self.laserText().trim() !== '') {
                if (!self.isEngravingFormatValid()) {
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
            if (!option || this.isEsgotado(option.codigo)) {
                return;
            }

            this.actionError('');

            if (this.isBlocked(option.codigo)) {
                var currentActive = this.activeTooltip();
                this.activeTooltip(currentActive === option.codigo ? null : option.codigo);
                return;
            }

            this.activeTooltip(null);
            if (this.selections[grupoCodigo]) {
                var prevCode = this.selections[grupoCodigo]();
                if (prevCode !== option.codigo) {
                    if (this._laserDebounceTimer) {
                        clearTimeout(this._laserDebounceTimer);
                        this._laserDebounceTimer = null;
                    }
                    this.selections[grupoCodigo](option.codigo);
                    this.autoResolveBlockedSelections();
                    if (this.engravingBlocked()) {
                        this.laserText('');
                    }
                    this.pushSnapshot();
                }
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

        onLaserFocus: function () {
            this.isLaserFocused(true);
        },

        onLaserBlur: function () {
            this.isLaserFocused(false);
            if (this._laserDebounceTimer) {
                clearTimeout(this._laserDebounceTimer);
                this._laserDebounceTimer = null;
            }
            this.pushSnapshot();
        },

        /**
         * F3.8: Retorna o tabindex para roving tabindex no radiogroup de amostras.
         * Garante que apenas a opção selecionada (ou a primeira válida) seja parada de Tab.
         *
         * @param {Object} option
         * @return {Number} 0 se for o tab-stop ativo, -1 caso contrário.
         */
        getSwatchTabIndex: function (option) {
            if (!option) {
                return -1;
            }
            var currentTab = this.activeTab();
            var selectedCode = this.selections[currentTab] ? this.selections[currentTab]() : null;
            var options = this.currentOptions();

            // 1. Se a opção for a atualmente selecionada e estiver disponível
            if (selectedCode && option.codigo === selectedCode && !this.isBlocked(option.codigo) && !this.isEsgotado(option.codigo)) {
                return 0;
            }

            // 2. Se a opção selecionada for bloqueada/esgotada ou inexistente, busca a primeira opção disponível
            var firstAvailable = null;
            for (var i = 0; i < options.length; i++) {
                var candidate = options[i];
                if (!this.isBlocked(candidate.codigo) && !this.isEsgotado(candidate.codigo)) {
                    firstAvailable = candidate;
                    break;
                }
            }

            if (firstAvailable && firstAvailable.codigo === option.codigo) {
                if (!selectedCode || this.isBlocked(selectedCode) || this.isEsgotado(selectedCode)) {
                    return 0;
                }
            }

            // 3. Fallback: se nenhuma opção estiver disponível, a primeira opção do grupo recebe tabindex=0
            if (!firstAvailable && options.length > 0 && options[0].codigo === option.codigo) {
                return 0;
            }

            return -1;
        },

        /**
         * F3.8: Navegação completa por teclado nas amostras do radiogroup.
         * Suporta setas direcionais (ArrowLeft/Right/Up/Down), Home, End, Espaço e Enter.
         *
         * @param {KeyboardEvent} event
         * @param {Object} option
         * @return {Boolean}
         */
        handleSwatchKeydown: function (event, option) {
            var key = event.key || event.keyCode;
            var options = this.currentOptions();
            if (!options || options.length === 0) {
                return true;
            }

            var currentIndex = -1;
            for (var i = 0; i < options.length; i++) {
                if (options[i].codigo === option.codigo) {
                    currentIndex = i;
                    break;
                }
            }
            if (currentIndex === -1) {
                return true;
            }

            // A. Espaço ou Enter: Seleciona o componente focado ou exibe tooltip se bloqueado
            if (key === ' ' || key === 'Space' || key === 'Spacebar' || key === 'Enter' || key === 32 || key === 13) {
                event.preventDefault();
                this.selectOption(this.activeTab(), option);
                return false;
            }

            // B. Setas Direcionais, Home e End
            var newIndex = currentIndex;
            var cols = 3;
            var handled = false;

            switch (key) {
                case 'ArrowRight':
                case 39:
                    newIndex = (currentIndex + 1) % options.length;
                    handled = true;
                    break;
                case 'ArrowLeft':
                case 37:
                    newIndex = (currentIndex - 1 + options.length) % options.length;
                    handled = true;
                    break;
                case 'ArrowDown':
                case 40:
                    if (currentIndex + cols < options.length) {
                        newIndex = currentIndex + cols;
                    } else {
                        newIndex = (currentIndex + cols) % options.length;
                    }
                    handled = true;
                    break;
                case 'ArrowUp':
                case 38:
                    if (currentIndex - cols >= 0) {
                        newIndex = currentIndex - cols;
                    } else {
                        var totalRows = Math.ceil(options.length / cols);
                        newIndex = currentIndex + (totalRows - 1) * cols;
                        while (newIndex >= options.length) {
                            newIndex -= cols;
                        }
                    }
                    handled = true;
                    break;
                case 'Home':
                case 36:
                    newIndex = 0;
                    handled = true;
                    break;
                case 'End':
                case 35:
                    newIndex = options.length - 1;
                    handled = true;
                    break;
                default:
                    return true;
            }

            if (handled) {
                event.preventDefault();
                var targetOption = options[newIndex];
                if (targetOption && event.target) {
                    var container = event.target.closest ? event.target.closest('[role="radiogroup"]') : null;
                    if (container) {
                        var buttons = container.querySelectorAll('[role="radio"]');
                        if (buttons && buttons[newIndex]) {
                            buttons[newIndex].focus();
                        }
                    }
                }
                return false;
            }

            return true;
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
            this.actionError('');
            var parsed = parseInt(size, 10);
            if (this.selectedSize() !== parsed) {
                if (this._laserDebounceTimer) {
                    clearTimeout(this._laserDebounceTimer);
                    this._laserDebounceTimer = null;
                }
                this.selectedSize(parsed);
                this.pushSnapshot();
            }
        },

        /**
         * Front-end validation before form submission (F3.5).
         * Bars submission if local configuration is invalid.
         *
         * @return {Boolean}
         */
        validate: function () {
            var errors = this.computeValidationErrors();
            if (errors && errors.length > 0) {
                this.actionError(errors[0]);
                return false;
            }
            this.actionError('');
            return true;
        },

        /**
         * Sets an error message returned by the server (F3.5).
         *
         * @param {String} message
         */
        setServerError: function (message) {
            this.actionError(message);
            this.isSubmitting(false);
        },

        /**
         * Clears all action errors.
         */
        clearErrors: function () {
            this.actionError('');
        },

        /**
         * Updates submitting state observable.
         *
         * @param {Boolean} isSubmitting
         */
        setSubmitting: function (isSubmitting) {
            this.isSubmitting(!!isSubmitting);
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
        },

        pollingIntervalMs: 20000,

        /**
         * F3.7: Inicializa o polling periódico de disponibilidade integrado à Page Visibility API.
         */
        initAvailabilityPolling: function () {
            var self = this;
            if (typeof window === 'undefined' || typeof document === 'undefined') {
                return;
            }

            this._isFetchingAvailability = false;
            this._lastPollTimestamp = Date.now();

            this._visibilityHandler = function () {
                self.handleVisibilityChange();
            };
            document.addEventListener('visibilitychange', this._visibilityHandler);

            this.startPolling();
        },

        /**
         * F3.7: Pausa e retoma o polling conforme a visibilidade da aba do navegador.
         */
        handleVisibilityChange: function () {
            if (typeof document === 'undefined') {
                return;
            }

            if (document.visibilityState === 'hidden') {
                this.stopPolling();
            } else if (document.visibilityState === 'visible') {
                var now = Date.now();
                if (!this._lastPollTimestamp || (now - this._lastPollTimestamp) >= this.pollingIntervalMs) {
                    this.fetchAvailability();
                }
                this.startPolling();
            }
        },

        /**
         * F3.7: Inicia o timer de 20s para atualização da disponibilidade.
         */
        startPolling: function () {
            var self = this;
            this.stopPolling();

            if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
                return;
            }

            this._pollingTimer = setInterval(function () {
                if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
                    self.stopPolling();
                    return;
                }
                self.fetchAvailability();
            }, this.pollingIntervalMs);
        },

        /**
         * F3.7: Pausa imediatamente o timer de polling.
         */
        stopPolling: function () {
            if (this._pollingTimer) {
                clearInterval(this._pollingTimer);
                this._pollingTimer = null;
            }
        },

        /**
         * F3.7: Resolve a URL REST de disponibilidade do SKU.
         */
        getAvailabilityUrl: function () {
            var url = this.getUrl('disponibilidade');
            var sku = (this.catalog && this.catalog.product && this.catalog.product.sku) || '';
            if (!url || url.indexOf('atelie/estoque/disponibilidade') !== -1) {
                url = '/rest/default/V1/atelie/disponibilidade/' + encodeURIComponent(sku);
            }
            return url;
        },

        /**
         * F3.7: Dispara a requisição HTTP GET para a rota de disponibilidade.
         * Respeita o servidor e utiliza APIs padronizadas sem dependência do jQuery.
         */
        fetchAvailability: function () {
            var self = this;
            if (this._isFetchingAvailability) {
                return;
            }

            if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
                return;
            }

            var url = this.getAvailabilityUrl();
            if (!url) {
                return;
            }

            this._isFetchingAvailability = true;
            this._lastPollTimestamp = Date.now();

            if (typeof fetch === 'function') {
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    self.applyAvailability(data);
                })
                .catch(function () {
                    // Silencioso para manter a navegação resiliente
                })
                .finally(function () {
                    self._isFetchingAvailability = false;
                });
            } else if (typeof XMLHttpRequest !== 'undefined') {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        self._isFetchingAvailability = false;
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                var data = JSON.parse(xhr.responseText);
                                self.applyAvailability(data);
                            } catch (e) {}
                        }
                    }
                };
                xhr.send();
            } else {
                this._isFetchingAvailability = false;
            }
        },

        /**
         * F3.7: Aplica os dados de disponibilidade aos componentes no Knockout.
         * Atualiza comp.disponivel, notifica o Knockout e emite role="alert" se a seleção atual esgotou.
         */
        applyAvailability: function (items) {
            if (!Array.isArray(items) || !this.catalog) {
                return;
            }

            var self = this;
            var soldOutSelected = [];

            items.forEach(function (item) {
                if (!item) {
                    return;
                }
                var code = item.componente || item.component || item.codigo;
                if (!code) {
                    return;
                }
                var isDisponivel = (item.disponivel !== false && item.available !== false);

                var comp = self.catalog.componentMap[code];
                if (comp) {
                    comp.disponivel = isDisponivel;

                    var grupoCodigo = comp.grupoCodigo;
                    var isSelected = self.selections[grupoCodigo] && self.selections[grupoCodigo]() === code;
                    if (isSelected && !isDisponivel) {
                        soldOutSelected.push(comp);
                    }
                }
            });

            this.disponibilidadeVersion(this.disponibilidadeVersion() + 1);
            if (this.grupos && typeof this.grupos.valueHasMutated === 'function') {
                this.grupos.valueHasMutated();
            }

            if (soldOutSelected.length > 0) {
                var nomes = soldOutSelected.map(function (c) {
                    return '"' + (c.nome || c.codigo) + '"';
                }).join(', ');
                var msg = (soldOutSelected.length === 1)
                    ? 'O componente ' + nomes + ' acabou de esgotar no estoque. Escolha outra opção para continuar.'
                    : 'Os componentes ' + nomes + ' acabaram de esgotar no estoque. Escolha outras opções para continuar.';
                this.actionError(msg);
            }
        },

        /**
         * F3.9: Inicializa a máquina de estados de histórico e os computeds reativos de Undo/Redo.
         */
        initHistory: function () {
            var self = this;
            this._isApplyingHistory = false;
            this.history = ko.observableArray([]);
            this.historyIndex = ko.observable(-1);

            this.canUndo = ko.pureComputed(function () {
                var idx = self.historyIndex();
                return typeof idx === 'number' && idx > 0;
            });

            this.canRedo = ko.pureComputed(function () {
                var h = self.history();
                var idx = self.historyIndex();
                return typeof idx === 'number' && idx >= 0 && Array.isArray(h) && idx < (h.length - 1);
            });

            this.undo = this.undo.bind(this);
            this.redo = this.redo.bind(this);
            this.surpriseMe = this.surpriseMe.bind(this);

            var initial = this.createSnapshot();
            this.history([initial]);
            this.historyIndex(0);
        },

        /**
         * F3.9: Captura um snapshot imutável do estado atual { selecoes, tamanho, laserText }.
         *
         * @return {Object}
         */
        createSnapshot: function () {
            var self = this;
            var selecoes = {};
            if (this.grupos) {
                var gruposList = this.grupos();
                for (var i = 0; i < gruposList.length; i++) {
                    var g = gruposList[i];
                    var code = self.selections && self.selections[g.codigo] ? self.selections[g.codigo]() : null;
                    if (code) {
                        selecoes[g.codigo] = code;
                    }
                }
            }
            return {
                selecoes: Object.freeze ? Object.freeze(Object.assign({}, selecoes)) : Object.assign({}, selecoes),
                tamanho: this.selectedSize ? this.selectedSize() : null,
                laserText: this.laserText ? String(this.laserText() || '') : ''
            };
        },

        /**
         * F3.9: Compara dois snapshots para evitar registrar estados idênticos consecutivos.
         *
         * @param {Object} snapA
         * @param {Object} snapB
         * @return {Boolean}
         */
        areSnapshotsEqual: function (snapA, snapB) {
            if (!snapA || !snapB) {
                return false;
            }
            if (snapA.tamanho !== snapB.tamanho) {
                return false;
            }
            if (snapA.laserText !== snapB.laserText) {
                return false;
            }
            var selA = snapA.selecoes || {};
            var selB = snapB.selecoes || {};
            var keysA = Object.keys(selA);
            var keysB = Object.keys(selB);
            if (keysA.length !== keysB.length) {
                return false;
            }
            for (var i = 0; i < keysA.length; i++) {
                var k = keysA[i];
                if (selA[k] !== selB[k]) {
                    return false;
                }
            }
            return true;
        },

        /**
         * F3.9: Adiciona um novo snapshot ao histórico.
         * Descarta ramificações futuras se o ponteiro estiver no meio da lista (árvore linear padrão).
         *
         * @param {Object} [snapshot]
         */
        pushSnapshot: function (snapshot) {
            if (this._isApplyingHistory) {
                return;
            }
            var newSnapshot = snapshot || this.createSnapshot();
            var history = this.history();
            var currentIndex = this.historyIndex();

            if (currentIndex >= 0 && currentIndex < history.length) {
                if (this.areSnapshotsEqual(history[currentIndex], newSnapshot)) {
                    return;
                }
            }

            var nextHistory = history.slice(0, currentIndex + 1);
            nextHistory.push(newSnapshot);

            this.history(nextHistory);
            this.historyIndex(nextHistory.length - 1);
        },

        /**
         * F3.9: Restaura fielmente um snapshot nos observáveis do Knockout.
         *
         * @param {Object} snapshot
         */
        restoreSnapshot: function (snapshot) {
            if (!snapshot) {
                return;
            }
            this._isApplyingHistory = true;
            try {
                var self = this;
                if (snapshot.selecoes && this.grupos) {
                    var gruposList = this.grupos();
                    for (var i = 0; i < gruposList.length; i++) {
                        var g = gruposList[i];
                        var code = snapshot.selecoes[g.codigo];
                        if (code && self.selections && self.selections[g.codigo]) {
                            self.selections[g.codigo](code);
                        }
                    }
                }
                if (snapshot.tamanho !== undefined && snapshot.tamanho !== null && this.selectedSize) {
                    this.selectedSize(snapshot.tamanho);
                }
                if (snapshot.laserText !== undefined && this.laserText) {
                    this.laserText(snapshot.laserText);
                }
                this.actionError('');
                this.activeTooltip(null);
            } finally {
                this._isApplyingHistory = false;
            }
        },

        /**
         * F3.9: Desfaz a última ação de personalização.
         */
        undo: function () {
            if (this._laserDebounceTimer) {
                clearTimeout(this._laserDebounceTimer);
                this._laserDebounceTimer = null;
            }
            if (!this.canUndo || !this.canUndo()) {
                return;
            }
            var newIndex = this.historyIndex() - 1;
            if (newIndex >= 0 && newIndex < this.history().length) {
                this.historyIndex(newIndex);
                this.restoreSnapshot(this.history()[newIndex]);
            }
        },

        /**
         * F3.9: Refaz a ação previamente desfeita.
         */
        redo: function () {
            if (this._laserDebounceTimer) {
                clearTimeout(this._laserDebounceTimer);
                this._laserDebounceTimer = null;
            }
            if (!this.canRedo || !this.canRedo()) {
                return;
            }
            var newIndex = this.historyIndex() + 1;
            if (newIndex >= 0 && newIndex < this.history().length) {
                this.historyIndex(newIndex);
                this.restoreSnapshot(this.history()[newIndex]);
            }
        },

        /**
         * F3.9: Embaralha uma lista utilizando o algoritmo Fisher-Yates.
         *
         * @param {Array} array
         * @return {Array}
         */
        shuffleArray: function (array) {
            var arr = array.slice();
            for (var i = arr.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var temp = arr[i];
                arr[i] = arr[j];
                arr[j] = temp;
            }
            return arr;
        },

        /**
         * F3.9: Validação de consistência parcial para poda antecipada de ramos conflitantes.
         *
         * @param {Object} currentMap - Mapa grupoCodigo -> componente
         * @return {Boolean}
         */
        isPartialCombinationValid: function (currentMap) {
            var self = this;
            var chosenList = [];
            var keys = Object.keys(currentMap);
            for (var k = 0; k < keys.length; k++) {
                chosenList.push(currentMap[keys[k]]);
            }

            function isCodeSelected(code) {
                return chosenList.some(function (opt) {
                    return self.normalize(opt.codigo) === self.normalize(code);
                });
            }

            function isFinishSelected(finish) {
                return chosenList.some(function (opt) {
                    return self.normalize(opt.acabamento) === self.normalize(finish);
                });
            }

            var regras = (this.catalog && this.catalog.regras) || [];
            for (var r = 0; r < regras.length; r++) {
                var regra = regras[r];
                var tipo = regra.tipo;
                var origem = regra.origem;
                var alvoTipo = regra.alvoTipo;
                var alvo = regra.alvo;

                if (tipo === 'bloqueia') {
                    if (alvoTipo === 'componente') {
                        if (isCodeSelected(origem) && isCodeSelected(alvo)) {
                            return false;
                        }
                    } else if (alvoTipo === 'acabamento') {
                        if (isCodeSelected(origem) && isFinishSelected(alvo)) {
                            return false;
                        }
                    }
                }
            }

            return true;
        },

        /**
         * F3.9: Validação final estrita da combinação completa.
         * Avalia todas as regras dinâmicas (bloqueia e exige) via computeInvalidSelections.
         *
         * @param {Object} currentMap - Mapa grupoCodigo -> componente
         * @return {Boolean}
         */
        isCompleteCombinationValid: function (currentMap) {
            var chosenList = [];
            var keys = Object.keys(currentMap);
            for (var k = 0; k < keys.length; k++) {
                chosenList.push(currentMap[keys[k]]);
            }

            var invalid = this.computeInvalidSelections(chosenList);
            return Object.keys(invalid || {}).length === 0;
        },

        /**
         * F3.9: Algoritmo de backtracking com descarte para evitar becos sem saída.
         * Percorre recursivamente os grupos ordenados e retrocede caso um ramo falhe.
         *
         * @param {Number} groupIndex
         * @param {Object} currentMap
         * @param {Array} grupos
         * @return {Object|null}
         */
        backtrackRandomCombination: function (groupIndex, currentMap, grupos) {
            if (groupIndex === grupos.length) {
                return this.isCompleteCombinationValid(currentMap) ? currentMap : null;
            }

            var self = this;
            var grupo = grupos[groupIndex];
            var allComponents = grupo.componentes || [];
            var candidates = [];

            for (var c = 0; c < allComponents.length; c++) {
                var comp = allComponents[c];
                if (!self.isEsgotado(comp.codigo)) {
                    candidates.push(comp);
                }
            }

            if (candidates.length === 0) {
                return null;
            }

            var randomizedCandidates = this.shuffleArray(candidates);

            for (var i = 0; i < randomizedCandidates.length; i++) {
                var candidate = randomizedCandidates[i];
                currentMap[grupo.codigo] = candidate;

                if (this.isPartialCombinationValid(currentMap)) {
                    var solution = this.backtrackRandomCombination(groupIndex + 1, currentMap, grupos);
                    if (solution) {
                        return solution;
                    }
                }

                delete currentMap[grupo.codigo];
            }

            return null;
        },

        /**
         * F3.9: Monta um tênis aleatório que NUNCA viola as regras de compatibilidade do catálogo.
         * Grava o estado resultante como um ponto único no histórico de desfazer.
         */
        surpriseMe: function () {
            if (this._laserDebounceTimer) {
                clearTimeout(this._laserDebounceTimer);
                this._laserDebounceTimer = null;
            }

            var self = this;
            var grupos = this.grupos ? this.grupos() : [];
            if (!grupos || grupos.length === 0) {
                return;
            }

            var solution = this.backtrackRandomCombination(0, {}, grupos);
            if (!solution) {
                this.actionError('Não foi possível gerar uma combinação automática com as opções disponíveis em estoque.');
                return;
            }

            this._isApplyingHistory = true;
            try {
                // Aplica seleções dos grupos
                grupos.forEach(function (g) {
                    if (solution[g.codigo] && self.selections && self.selections[g.codigo]) {
                        self.selections[g.codigo](solution[g.codigo].codigo);
                    }
                });

                // Verifica se a nova combinação bloqueia a gravação a laser
                if (this.engravingBlocked && this.engravingBlocked()) {
                    this.laserText('');
                }

                this.actionError('');
                this.activeTooltip(null);
            } finally {
                this._isApplyingHistory = false;
            }

            // Registra ponto único no histórico de desfazer
            this.pushSnapshot();
        },

        /**
         * F3.10: Codifica string UTF-8 para Base64 de forma compatível com Browser e Node.js.
         *
         * @param {String} str
         * @return {String}
         */
        base64EncodeUtf8: function (str) {
            if (typeof Buffer !== 'undefined') {
                return Buffer.from(str, 'utf-8').toString('base64');
            }
            if (typeof btoa === 'function') {
                return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (match, p1) {
                    return String.fromCharCode('0x' + p1);
                }));
            }
            return '';
        },

        /**
         * F3.10: Decodifica string Base64 para UTF-8 de forma compatível com Browser e Node.js.
         *
         * @param {String} base64Str
         * @return {String}
         */
        base64DecodeUtf8: function (base64Str) {
            if (typeof Buffer !== 'undefined') {
                return Buffer.from(base64Str, 'base64').toString('utf-8');
            }
            if (typeof atob === 'function') {
                var binary = atob(base64Str);
                var bytes = Array.prototype.map.call(binary, function (ch) {
                    return '%' + ('00' + ch.charCodeAt(0).toString(16)).slice(-2);
                }).join('');
                return decodeURIComponent(bytes);
            }
            return '';
        },

        /**
         * F3.10: Serializa snapshot de personalização em string URL-Safe Base64 com prefixo atelie=.
         *
         * @param {Object} [snapshot]
         * @return {String}
         */
        serializeShareHash: function (snapshot) {
            var snap = snapshot || this.createSnapshot();
            var payload = {
                t: snap.tamanho,
                s: snap.selecoes || {},
                g: (snap.laserText !== undefined && snap.laserText !== null) ? String(snap.laserText) : ''
            };
            var json = JSON.stringify(payload);
            var base64 = this.base64EncodeUtf8(json)
                .replace(/\+/g, '-')
                .replace(/\//g, '_')
                .replace(/=+$/, '');
            return 'atelie=' + base64;
        },

        /**
         * F3.10: Decodifica fragmento de hash com tolerância a falhas.
         * Suporta Base64URL, Base64 padrão e JSON codificado na URL.
         *
         * @param {String} rawHash
         * @return {Object|null}
         */
        parseShareHash: function (rawHash) {
            if (!rawHash || typeof rawHash !== 'string') {
                return null;
            }

            var clean = rawHash.trim();
            if (clean.charAt(0) === '#') {
                clean = clean.substring(1);
            }

            // Suporta prefixos 'atelie=' ou 'c='
            if (clean.indexOf('atelie=') === 0) {
                clean = clean.substring(7);
            } else if (clean.indexOf('c=') === 0) {
                clean = clean.substring(2);
            }

            if (!clean) {
                return null;
            }

            // Tenta decodificar se for JSON direto ou URL-encoded
            if (clean.charAt(0) === '{' || clean.indexOf('%7B') === 0) {
                try {
                    return JSON.parse(decodeURIComponent(clean));
                } catch (e) {
                    return null;
                }
            }

            // Converte Base64URL para Base64 padrão com padding
            var b64 = clean.replace(/-/g, '+').replace(/_/g, '/');
            while (b64.length % 4 !== 0) {
                b64 += '=';
            }

            try {
                var jsonStr = this.base64DecodeUtf8(b64);
                if (!jsonStr) {
                    return null;
                }
                return JSON.parse(jsonStr);
            } catch (err) {
                return null;
            }
        },

        /**
         * F3.10: Valida rigorosamente se a configuração compartilhada decodificada
         * é 100% autêntica, pertence à grade oficial do produto e respeita as regras de negócio.
         *
         * @param {Object} rawParsed
         * @param {Object} catalog
         * @return {Object|null}
         */
        validateSharedConfig: function (rawParsed, catalog) {
            if (!rawParsed || typeof rawParsed !== 'object' || !catalog || !catalog.product) {
                return null;
            }

            // 1. Validação do tamanho
            var tamanho = parseInt(rawParsed.t !== undefined ? rawParsed.t : rawParsed.tamanho, 10);
            var grade = Array.isArray(catalog.product.gradeTamanhos) ? catalog.product.gradeTamanhos : [];
            if (isNaN(tamanho) || grade.indexOf(tamanho) === -1) {
                return null;
            }

            // 2. Normalização do mapa de seleções
            var rawSelecoes = rawParsed.s || rawParsed.selecoes;
            var selecaoPorGrupo = {};
            if (Array.isArray(rawSelecoes)) {
                rawSelecoes.forEach(function (item) {
                    if (item && item.grupo && item.componente) {
                        selecaoPorGrupo[item.grupo] = String(item.componente);
                    }
                });
            } else if (rawSelecoes && typeof rawSelecoes === 'object') {
                Object.keys(rawSelecoes).forEach(function (k) {
                    selecaoPorGrupo[k] = String(rawSelecoes[k]);
                });
            } else {
                return null;
            }

            // 3. Validação de cada grupo obrigatório do catálogo
            var grupos = catalog.grupos || [];
            if (grupos.length === 0) {
                return null;
            }

            var chosenComponents = [];
            for (var i = 0; i < grupos.length; i++) {
                var grupo = grupos[i];
                var compCode = selecaoPorGrupo[grupo.codigo];
                if (!compCode) {
                    return null;
                }

                var comp = catalog.componentMap ? catalog.componentMap[compCode] : null;
                if (!comp) {
                    return null;
                }

                if (comp.grupoCodigo !== grupo.codigo) {
                    return null;
                }

                chosenComponents.push(comp);
            }

            // 4. Validação estrita das regras de compatibilidade (R01 a R06)
            var invalidMap = this.computeInvalidSelections(chosenComponents);
            if (invalidMap && Object.keys(invalidMap).length > 0) {
                return null;
            }

            // 5. Validação de Gravação a Laser
            var rawGravacao = rawParsed.g !== undefined ? rawParsed.g : (rawParsed.gravacao || rawParsed.laserText || '');
            var gravacaoStr = String(rawGravacao || '').trim();

            if (gravacaoStr.length > 0) {
                var gravacaoConfig = catalog.product.gravacao || {};
                if (!gravacaoConfig.permitida) {
                    return null;
                }
                var maxChars = gravacaoConfig.limiteCaracteres || 12;
                if (gravacaoStr.length > maxChars) {
                    return null;
                }

                // Verifica se há regra bloqueando gravação (ex: R04 Sola Reciclada)
                var blockReason = this.computeEngravingBlockReason(chosenComponents);
                if (blockReason) {
                    return null;
                }
            }

            return {
                tamanho: tamanho,
                gravacao: gravacaoStr,
                selecoes: grupos.map(function (g) {
                    return { grupo: g.codigo, componente: selecaoPorGrupo[g.codigo] };
                })
            };
        },

        /**
         * F3.10: Extrai fragmento de hash da URL atual no navegador.
         *
         * @return {String}
         */
        getSharedHashFromUrl: function () {
            if (typeof window === 'undefined' || !window.location) {
                return '';
            }
            var hash = window.location.hash || '';
            if (hash.charAt(0) === '#') {
                hash = hash.substring(1);
            }
            return hash;
        },

        /**
         * F3.10: Limpa hash corrompido ou inválido da URL sem recarregar a página.
         */
        clearInvalidHashFromUrl: function () {
            if (typeof window === 'undefined' || !window.location || !window.history || !window.history.replaceState) {
                return;
            }
            var cleanUrl = window.location.pathname + window.location.search;
            window.history.replaceState(null, (typeof document !== 'undefined' ? document.title : ''), cleanUrl);
        },

        /**
         * F3.10: Resolve a configuração inicial inspecionando o hash da URL (#hash).
         * Se válido, retorna a configuração decodificada.
         * Se inválido ou corrompido, emite aviso amigável e retorna a sugestão inicial padrão.
         *
         * @param {Object} catalog
         * @param {Object} defaultCfg
         * @return {Object}
         */
        resolveInitialConfiguration: function (catalog, defaultCfg) {
            var rawHash = this.getSharedHashFromUrl();
            if (!rawHash) {
                return defaultCfg;
            }

            // Ignora âncoras comuns que não são do estúdio
            if (rawHash.indexOf('atelie=') === -1 && rawHash.indexOf('c=') === -1 && rawHash.charAt(0) !== '{') {
                return defaultCfg;
            }

            var parsed = this.parseShareHash(rawHash);
            if (!parsed) {
                this.sharedNotice('Configuração compartilhada inválida. Carregamos a sugestão original.');
                this.clearInvalidHashFromUrl();
                return defaultCfg;
            }

            var validated = this.validateSharedConfig(parsed, catalog);
            if (!validated) {
                this.sharedNotice('Configuração compartilhada inválida. Carregamos a sugestão original.');
                this.clearInvalidHashFromUrl();
                return defaultCfg;
            }

            return validated;
        },

        /**
         * F3.10: Fecha o aviso de configuração compartilhada inválida.
         */
        dismissSharedNotice: function () {
            this.sharedNotice('');
        },

        /**
         * F3.10: Gera o link de compartilhamento com hash e copia para a área de transferência.
         * Atualiza a barra de endereços com history.replaceState e exibe feedback acessível temporário.
         */
        shareCreation: function () {
            var snapshot = this.createSnapshot();
            var hash = this.serializeShareHash(snapshot);
            var url = '';

            if (typeof window !== 'undefined' && window.location) {
                var base = window.location.origin + window.location.pathname + window.location.search;
                url = base + '#' + hash;

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, (typeof document !== 'undefined' ? document.title : ''), url);
                } else {
                    window.location.hash = hash;
                }
            }

            var self = this;
            var onSuccess = function () {
                self.isCopied(true);
                if (self._shareCopyTimer) {
                    clearTimeout(self._shareCopyTimer);
                }
                self._shareCopyTimer = setTimeout(function () {
                    self.isCopied(false);
                }, 3000);
            };

            var fallbackCopy = function () {
                try {
                    if (typeof document !== 'undefined' && typeof document.createElement === 'function') {
                        var textArea = document.createElement('textarea');
                        textArea.value = url;
                        textArea.style.position = 'fixed';
                        textArea.style.top = '-9999px';
                        textArea.style.left = '-9999px';
                        textArea.setAttribute('aria-hidden', 'true');
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        var successful = false;
                        if (typeof document.execCommand === 'function') {
                            successful = document.execCommand('copy');
                        }
                        document.body.removeChild(textArea);
                        if (successful) {
                            onSuccess();
                            return;
                        }
                    }
                } catch (e) {
                    // Silencioso em caso de restrição estrita
                }
            };

            if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(url).then(onSuccess).catch(fallbackCopy);
            } else {
                fallbackCopy();
            }
        },

        /**
         * Destrói timers e remove event listeners ao desmontar o componente.
         */
        destroy: function () {
            this.stopPolling();
            if (this._laserDebounceTimer) {
                clearTimeout(this._laserDebounceTimer);
                this._laserDebounceTimer = null;
            }
            if (this._shareCopyTimer) {
                clearTimeout(this._shareCopyTimer);
                this._shareCopyTimer = null;
            }
            if (typeof document !== 'undefined') {
                if (this._visibilityHandler) {
                    document.removeEventListener('visibilitychange', this._visibilityHandler);
                }
                if (this._documentClickHandler) {
                    document.removeEventListener('click', this._documentClickHandler);
                }
                if (this._documentKeyHandler) {
                    document.removeEventListener('keydown', this._documentKeyHandler);
                }
            }
            if (this._super) {
                this._super();
            }
        }
    });
});
