define(['Magento_Ui/js/grid/columns/column'], function (Column) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Nuvem_AtelieAdmin/grid/cells/color'
        },

        getColor: function (row) {
            var color = row[this.index];

            return /^#[0-9a-f]{6}$/i.test(color) ? color : 'transparent';
        }
    });
});
