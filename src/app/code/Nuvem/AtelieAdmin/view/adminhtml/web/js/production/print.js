define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function () {
            window.print();
        });
    };
});
