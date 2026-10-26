define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (Component, ko, $, customerData, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieStudio/studio'
        },

        initObservable: function () {
            this._super();

            var rawConfig = this.getConfigData();
            var catalog = this.normalizeCatalog(rawConfig);

            this.editing = !!rawConfig.editing;
            this.editError = rawConfig.edit_error || '';
            this.saving = ko.observable(false);
            this.saveMessage = ko.observable(this.editError);
            this.saveFailed = ko.observable(!!this.editError);
            this.saveCompleted = ko.observable(false);
            this.cartSyncFailed = ko.observable(false);
            if (this.editing) {
                this.editSubmitHandler = this.submitEdit.bind(this);
                document.addEventListener('submit', this.editSubmitHandler, true);
            }
            this.catalog = catalog;
            this.initCatalogObservables(catalog);
            this.initSelectionObservables(catalog, rawConfig.configuracao_inicial || {});
            this.initComputed();

            return this;
        },

        submitEdit: function (event) {
            if (event.target.id !== 'product_addtocart_form') {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (this.saving() || this.editError) {
                return;
            }
            var self = this,
                config = this.getConfigData(),
                fields = $(event.target).serializeArray().filter(function (field) {
                    return ['id', 'qty', 'isAjax'].indexOf(field.name) === -1;
                });

            fields.push({name: 'id', value: config.item_id});
            fields.push({name: 'qty', value: config.qty});
            fields.push({name: 'isAjax', value: 1});
            this.saving(true);
            this.saveCompleted(false);
            this.cartSyncFailed(false);
            this.saveMessage('');
            $.ajax({url: config.urls.update_item, type: 'POST', dataType: 'json', data: fields})
                .done(function (response) {
                    self.saveFailed(!response.success);
                    self.saveMessage((response.messages || []).join(' ') || $t('Unable to save changes.'));
                    if (response.success) {
                        self.saveCompleted(true);
                        self.refreshCart();
                    }
                }).fail(function () {
                    self.saveFailed(true);
                    self.saveMessage($t('Unable to confirm the update. Refresh the cart before trying again.'));
                }).always(function () {
                    self.saving(false);
                });
        },

        refreshCart: function () {
            var self = this,
                retrying = this.cartSyncFailed();

            this.cartSyncFailed(false);
            customerData.invalidate(['cart']);
            return customerData.reload(['cart'], true).done(function () {
                if (retrying) {
                    self.saveMessage($t('Alterações salvas. O resumo do carrinho foi atualizado.'));
                }
            }).fail(function () {
                self.cartSyncFailed(true);
                self.saveMessage($t('Suas alterações foram salvas, mas o resumo do carrinho não foi atualizado. Tente atualizar o resumo novamente.'));
            });
        },

        destroy: function () {
            if (this.editSubmitHandler) {
                document.removeEventListener('submit', this.editSubmitHandler, true);
            }
            return this._super();
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

            var regras = rawRegras.map(function (r) {
                return {
                    codigo: String(r.codigo || ''),
                    tipo: String(r.tipo || '').toLowerCase().trim(),
                    origem: String(r.origem || ''),
                    alvoTipo: String(r.alvo_tipo || r.alvoTipo || '').toLowerCase().trim(),
                    alvo: String(r.alvo || ''),
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
            if (!this.editing && (isNaN(tamanhoInicial) || gradeArr.indexOf(tamanhoInicial) === -1)) {
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

                if (!encontrado && !self.editing) {
                    var padrao = (grupo.componentes || []).find(function (c) { return c.padrao; });
                    if (padrao) {
                        codigoInicial = padrao.codigo;
                    } else if (grupo.componentes.length > 0) {
                        codigoInicial = grupo.componentes[0].codigo;
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
                        if (isCodeSelected(origem) && !isCodeSelected(alvo)) {
                            blocked[alvo] = msg;
                        } else if (isCodeSelected(alvo) && !isCodeSelected(origem)) {
                            blocked[origem] = msg;
                        }
                    } else if (alvoTipo === 'acabamento') {
                        if (isCodeSelected(origem)) {
                            self.catalog.grupos.forEach(function (grupo) {
                                grupo.componentes.forEach(function (opt) {
                                    if (self.normalize(opt.acabamento) === self.normalize(alvo) && !isCodeSelected(opt.codigo)) {
                                        blocked[opt.codigo] = msg;
                                    }
                                });
                            });
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

                if (tipo === 'bloqueia' && alvoTipo === 'componente') {
                    if (isCodeSelected(origem) && isCodeSelected(alvo)) {
                        invalid[alvo] = msg;
                    }
                } else if (tipo === 'exige') {
                    if (isCodeSelected(origem)) {
                        var alvoOk = (alvoTipo === 'acabamento')
                            ? isFinishSelected(alvo)
                            : isCodeSelected(alvo);
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
                if (regra.tipo === 'bloqueia' && regra.alvoTipo === 'gravacao') {
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
