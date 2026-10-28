define([
    'Magento_PageBuilder/js/mass-converter/widget-directive-abstract',
    'Magento_PageBuilder/js/utils/object'
], function (WidgetDirectiveAbstract, objectUtil) {
    'use strict';

    function WidgetDirective() {}

    WidgetDirective.prototype = Object.create(
        WidgetDirectiveAbstract.prototype
    );

    WidgetDirective.prototype.constructor = WidgetDirective;

    WidgetDirective.prototype.fromDom = function (data, config) {
        var attributes = WidgetDirectiveAbstract.prototype
            .fromDom.call(this, data, config);

        data.title = attributes.title || data.title || '';
        data.limit = attributes.limit || data.limit || '6';
        data.order = attributes.order || data.order || 'recent';

        return data;
    };

    WidgetDirective.prototype.toDom = function (data, config) {
        var limit = parseInt(data.limit, 10);
        var order = data.order || 'recent';
        var title = String(data.title || '');

        if (!isFinite(limit)) {
            limit = 6;
        }

        limit = Math.max(1, Math.min(24, limit));

        if (['recent', 'oldest', 'popular'].indexOf(order) === -1) {
            order = 'recent';
        }

        // Avoid breaking the quoted CMS widget directive.
        title = title.replace(/[{}"]/g, '').substring(0, 150);

        objectUtil.set(data, config.html_variable, this.buildDirective({
            type: 'Nuvem\\AtelieVitrine\\Block\\Widget\\Creations',
            template: 'Nuvem_AtelieVitrine::widget/creations.phtml',
            type_name: 'Vitrine de Criações Nuvem',
            title: title,
            limit: limit,
            order: order
        }));

        return data;
    };

    return WidgetDirective;
});
