define([], function () {
    'use strict';

    return function (config, element) {
        var buttons = Array.prototype.slice.call(element.querySelectorAll('[data-like-hash]'));
        var message = element.querySelector('[data-like-message]');
        var parameters = new URLSearchParams();

        function update(button, state) {
            button.setAttribute('aria-pressed', state.liked ? 'true' : 'false');
            button.setAttribute('aria-label', (state.liked ? 'Descurtir ' : 'Curtir ') + button.dataset.creationName);
            button.querySelector('[data-like-count]').textContent = state.count;
        }

        buttons.forEach(function (button) {
            parameters.append('hashes[]', button.dataset.likeHash);
            button.addEventListener('click', function () {
                var cookie = document.cookie.match(/(?:^|; )form_key=([^;]+)/);
                if (!cookie) {
                    message.textContent = 'Atualize a página para curtir esta criação.';
                    return;
                }
                button.disabled = true;
                message.textContent = '';
                fetch(config.saveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    body: new URLSearchParams({
                        hash: button.dataset.likeHash,
                        liked: button.getAttribute('aria-pressed') === 'true' ? '0' : '1',
                        form_key: decodeURIComponent(cookie[1])
                    })
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to save like');
                    }
                    return response.json();
                }).then(function (state) {
                    update(button, state);
                    if (config.likedOnly && !state.liked) {
                        window.location.reload();
                    }
                }).catch(function () {
                    message.textContent = 'Não foi possível salvar sua curtida. Tente novamente.';
                }).finally(function () {
                    button.disabled = false;
                });
            });
        });
        if (!buttons.length) {
            return;
        }
        fetch(config.stateUrl + '?' + parameters.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to load likes');
            }
            return response.json();
        }).then(function (state) {
            buttons.forEach(function (button) {
                if (state.creations[button.dataset.likeHash]) {
                    update(button, state.creations[button.dataset.likeHash]);
                    button.disabled = false;
                }
            });
        }).catch(function () {
            message.textContent = 'Não foi possível carregar as curtidas. Atualize a página para tentar novamente.';
        });
    };
});
