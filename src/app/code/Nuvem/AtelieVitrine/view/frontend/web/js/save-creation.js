define([
    'uiComponent',
    'ko',
    'jquery',
    'mage/url'
], function (Component, ko, $, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Nuvem_AtelieVitrine/save-creation',
            configuration: null,
            studioData: null,
            listens: {
                studioData: 'restorePendingName'
            }
        },

        
        initObservable: function () {
            this._super();

            this.creationName = ko.observable('');
            this.isSaving = ko.observable(false);
            this.errorMessage = ko.observable('');
            this.savedLink = ko.observable('');

            return this;
        },

        initLinks: function () {
            this._super();

            this.restorePendingName();

            return this;
        },

        restorePendingName: function () {
            var pending;
            var product = (this.studioData || {}).produto || {};

            try {
                pending = JSON.parse(
                    window.sessionStorage.getItem('atelie_pending_creation') || 'null'
                );
            } catch (error) {
                return;
            }

            if (!pending || !pending.name || !product.sku) {
                return;
            }

            if (pending.sku !== product.sku ||
                pending.returnUrl !== window.location.href) {
                return;
            }

            this.creationName(pending.name);
        },


        save: function () {
            var self = this;
            var name = this.creationName().trim();
            var product = (this.studioData || {}).produto || {};
            var formKey = window.FORM_KEY ||
                $('input[name="form_key"]').first().val();

            if (!name) {
                this.errorMessage('Informe um nome para sua criação.');
                return;
            }

            if (!product.sku || !this.configuration) {
                this.errorMessage('Configuração do estúdio indisponível.');
                return;
            }

            if (!formKey) {
                this.errorMessage('Não foi possível identificar a sessão. Atualize a página.');
                return;
            }

            this.errorMessage('');
            this.savedLink('');

            this.isSaving(true);

            $.ajax({
                url: urlBuilder.build('atelie/creation/save'),
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: formKey,
                    sku: product.sku,
                    name: name,
                    configuration: JSON.stringify(this.configuration)
                }
            }).done(function (response) {
                if (response.success && response.hash) {
                    window.sessionStorage.removeItem('atelie_pending_creation');

                    self.savedLink(
                        urlBuilder.build('atelie/c/' + encodeURIComponent(response.hash))
                    );
                } else {
                    self.errorMessage(
                        response.message || 'Não foi possível salvar a criação.'
                    );
                }
            }).fail(function (xhr) {
                var response = xhr.responseJSON || {};
                
                if (xhr.status === 401) {
                    try {
                        window.sessionStorage.setItem(
                            'atelie_pending_creation',
                            JSON.stringify({
                                sku: product.sku,
                                name: name,
                                configuration: self.configuration,
                                returnUrl: window.location.href
                            })
                        );
                    } catch (error) {
                        self.errorMessage(
                            'Não foi possível preservar sua criação. Tente novamente.'
                        );
                        return;
                    }

                    window.location.assign(
                        urlBuilder.build(
                            'atelie/creation/login?sku=' + encodeURIComponent(product.sku)
                        )
                    );
                    return;
                }

                self.errorMessage(
                    response.message || 'Ocorreu um erro ao salvar a criação.'
                );
            }).always(function () {
                self.isSaving(false);
            });
        }
    });
});
