define([
    'jquery',
    'uiRegistry',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function ($, registry, customerData, $t) {
    'use strict';

    return function (targetWidget) {
        $.widget('mage.catalogAddToCart', targetWidget, {
            options: {
                atelieSubmitButtonSelector: '.atelie-studio__submit'
            },

            /**
             * @inheritdoc
             */
            _create: function () {
                this._super();
                this._bindAtelieEvents();
            },

            /**
             * Bind Atelie-specific event listeners for add to cart lifecycle.
             * @private
             */
            _bindAtelieEvents: function () {
                var self = this;

                if (this.element.data('atelie-events-initialized')) {
                    return;
                }
                this.element.data('atelie-events-initialized', 1);

                $(document).on('ajax:addToCart', function (e, data) {
                    if (data && data.form && self.isAtelieForm(data.form)) {
                        var res = data.response || {};
                        // In Magento catalog-add-to-cart.js, ajax:addToCart is dispatched before checking
                        // res.backUrl. If backUrl or error is present, this is a redirect/failure, not a success.
                        if (res.backUrl || res.error) {
                            return;
                        }

                        if (res.messages && !res.minicart) {
                            self.handleServerError(data.form, res.messages);
                            return;
                        }

                        self.handleServerSuccess(data.form, data.response);
                    }
                });

                $(document).on('ajax:addToCart:error', function (e, data) {
                    if (data && data.form && self.isAtelieForm(data.form)) {
                        self.handleServerError(data.form);
                    }
                });
            },

            /**
             * Checks if the given form belongs to the Atelie Studio product view.
             *
             * @param {jQuery} form
             * @return {Boolean}
             */
            isAtelieForm: function (form) {
                if (!form || !form.length) {
                    return false;
                }
                var formId = form.attr('id') || '';
                return form.find('input[name^="atelie"]').length > 0 ||
                    $('[form="' + formId + '"][name^="atelie"]').length > 0 ||
                    $('[data-bind*="atelieStudio"]').length > 0 ||
                    !!registry.get('atelieStudio');
            },

            /**
             * Gets the Atelie Studio Knockout component instance.
             *
             * @return {Object|null}
             */
            getStudioComponent: function () {
                return registry.get('atelieStudio') || null;
            },

            /**
             * Handler for the form 'submit' event.
             * Bars submission if local state is invalid before making any server request.
             *
             * @inheritdoc
             * @param {jQuery} form
             */
            submitForm: function (form) {
                if (this.isAtelieForm(form)) {
                    var studio = this.getStudioComponent();
                    if ((studio && studio.isSubmitting && studio.isSubmitting()) || form.data('atelie-submitting')) {
                        return false;
                    }
                    if (studio && typeof studio.validate === 'function') {
                        var isValid = studio.validate();
                        if (!isValid) {
                            // Local configuration is invalid: bar the submit before hitting the server
                            return false;
                        }
                    }
                    form.data('atelie-submitting', 1);
                    if (studio && typeof studio.clearErrors === 'function') {
                        studio.clearErrors();
                    }
                }

                return this._super(form);
            },

            /**
             * Handles AJAX form submission.
             *
             * @inheritdoc
             * @param {jQuery} form
             */
            ajaxSubmit: function (form) {
                if (this.isAtelieForm(form)) {
                    var formId = form.attr('id') || '';
                    var studio = this.getStudioComponent();

                    if (studio && typeof studio.setSubmitting === 'function') {
                        studio.setSubmitting(true);
                    }

                    // Ensure all form-associated inputs with form="product_addtocart_form"
                    // are physically present inside the form element so new FormData(form[0])
                    // reliably includes them across all browsers and test environments.
                    $('[form="' + formId + '"][name^="atelie"]').each(function () {
                        var input = this;
                        var existing = form.find('input[name="' + input.name + '"]');
                        if (existing.length === 0) {
                            $('<input type="hidden">')
                                .attr('name', input.name)
                                .val($(input).val())
                                .appendTo(form);
                        } else {
                            existing.val($(input).val());
                        }
                    });
                }

                return this._super(form);
            },

            /**
             * Disables add to cart button during submission to prevent double clicks.
             *
             * @inheritdoc
             * @param {jQuery} form
             */
            disableAddToCartButton: function (form) {
                this._super(form);

                if (this.isAtelieForm(form)) {
                    var studio = this.getStudioComponent();
                    if (studio && typeof studio.setSubmitting === 'function') {
                        studio.setSubmitting(true);
                    }
                    $(this.options.atelieSubmitButtonSelector).prop('disabled', true).addClass('disabled');
                }
            },

            /**
             * Resets the studio submit button state cleanly.
             *
             * @param {jQuery} [form]
             */
            resetSubmitButton: function (form) {
                var studio = this.getStudioComponent();
                if (studio && typeof studio.setSubmitting === 'function') {
                    studio.setSubmitting(false);
                }
                if (form) {
                    form.removeData('atelie-submitting');
                }
                $(this.options.atelieSubmitButtonSelector)
                    .prop('disabled', false)
                    .removeClass('disabled is-loading is-disabled')
                    .blur();
            },

            /**
             * Re-enables add to cart button after submission finishes.
             *
             * @inheritdoc
             * @param {jQuery} form
             */
            enableAddToCartButton: function (form) {
                this._super(form);

                if (this.isAtelieForm(form)) {
                    this.resetSubmitButton(form);
                }
            },

            /**
             * Intercepts Magento's native redirect.
             * Magento Add controller returns {"backUrl": "..."} when an error occurs.
             * For Atelie products, we intercept this redirect to prevent page reload,
             * extract the server message, and position it below the studio button.
             *
             * @inheritdoc
             * @param {String} url
             */
            _redirect: function (url) {
                if (this.isAtelieForm(this.element)) {
                    this.handleServerError(this.element);
                    return;
                }

                return this._super(url);
            },

            /**
             * Handles server refusal or error without reloading the page.
             *
             * @param {jQuery} form
             * @param {String|null} [rawMessage]
             */
            handleServerError: function (form, rawMessage) {
                var self = this,
                    studio = this.getStudioComponent();

                this.resetSubmitButton(form);

                if (rawMessage) {
                    var parsedText = $('<div>').html(rawMessage).text().trim();
                    if (parsedText && studio && typeof studio.setServerError === 'function') {
                        studio.setServerError(parsedText);
                    }
                    customerData.set('messages', { messages: [] });
                    $(self.options.messagesSelector).empty();
                    $('.page.messages').empty();
                    return;
                }

                // Retrieve error message added to messageManager by Magento Add controller
                customerData.reload(['messages'], true).done(function (data) {
                    var messagesData = (data && data.messages) || customerData.get('messages')(),
                        errorText = '';

                    if (messagesData && Array.isArray(messagesData.messages) && messagesData.messages.length) {
                        var errorItems = messagesData.messages.filter(function (m) {
                            return m.type === 'error' || !m.type;
                        });
                        if (errorItems.length) {
                            errorText = errorItems.map(function (m) { return m.text; }).join(' ');
                        }
                    }

                    if (!errorText) {
                        errorText = $t('Não foi possível adicionar o produto ao carrinho. Verifique sua personalização.');
                    }

                    if (studio && typeof studio.setServerError === 'function') {
                        studio.setServerError(errorText);
                    }

                    // Clear messages from global container so they never appear loose at the top of the page
                    customerData.set('messages', { messages: [] });
                    $(self.options.messagesSelector).empty();
                    $('.page.messages').empty();
                    self.resetSubmitButton(form);
                }).fail(function () {
                    var fallbackMsg = $t('Não foi possível adicionar o produto ao carrinho. Verifique sua personalização.');
                    if (studio && typeof studio.setServerError === 'function') {
                        studio.setServerError(fallbackMsg);
                    }
                    $(self.options.messagesSelector).empty();
                    $('.page.messages').empty();
                    self.resetSubmitButton(form);
                });
            },

            /**
             * Handles successful server response.
             *
             * @param {jQuery} form
             * @param {Object} response
             */
            handleServerSuccess: function (form, response) {
                var self = this,
                    studio = this.getStudioComponent();

                if (studio && typeof studio.clearErrors === 'function') {
                    studio.clearErrors();
                }
                this.resetSubmitButton(form);

                // Reload customer cart & messages data and open the minicart dropdown
                customerData.reload(['cart', 'messages'], true).done(function () {
                    self.openMinicart();
                });
            },

            /**
             * Automatically opens the minicart dropdown dialog.
             */
            openMinicart: function () {
                var minicart = $(this.options.minicartSelector);
                if (!minicart.length) {
                    minicart = $('[data-block="minicart"]');
                }

                if (minicart.length) {
                    var dropdownDialog = minicart.find('[data-role="dropdownDialog"]');
                    if (dropdownDialog.length && dropdownDialog.data('mageDropdownDialog')) {
                        try {
                            dropdownDialog.dropdownDialog('open');
                        } catch (err) {
                            minicart.find('.action.showcart').trigger('click');
                        }
                    } else {
                        minicart.find('.action.showcart').trigger('click');
                    }
                }
            }
        });

        return $.mage.catalogAddToCart;
    };
});
