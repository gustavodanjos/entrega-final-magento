define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function () {
            var url = element.getAttribute('data-copy-url');

            if (!navigator.clipboard) {
                window.prompt('Copie o link:', url);
                return;
            }

            navigator.clipboard.writeText(url).then(function () {
                element.textContent = 'Copiado!';
            }).catch(function () {
                window.prompt('Copie o link:', url);
            });
        });
    };
});
