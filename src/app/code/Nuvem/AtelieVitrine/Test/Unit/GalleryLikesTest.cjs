'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function gallery(fetch) {
    let initialize, click;
    const count = {textContent: '2'};
    const message = {textContent: ''};
    const attributes = {'aria-pressed': 'false'};
    const button = {
        disabled: true,
        dataset: {likeHash: 'a'.repeat(32), creationName: 'Lua cheia'},
        setAttribute: (name, value) => { attributes[name] = value; },
        getAttribute: name => attributes[name],
        querySelector: () => count,
        addEventListener: (name, handler) => { click = handler; }
    };
    vm.runInNewContext(fs.readFileSync(
        path.resolve(__dirname, '../../view/frontend/web/js/gallery-likes.js'), 'utf8'
    ), {
        define: (dependencies, factory) => { assert.equal(dependencies.length, 0); initialize = factory(); },
        document: {cookie: 'form_key=unit-form-key'},
        window: {location: {reload: () => {}}},
        URLSearchParams,
        fetch
    });
    initialize({
        stateUrl: 'https://shop.test/galeria/like/state/',
        saveUrl: 'https://shop.test/galeria/like/save/',
        likedOnly: false
    }, {querySelectorAll: () => [button], querySelector: () => message});
    return {button, count, message, click: () => click()};
}

const settled = () => new Promise(resolve => setImmediate(resolve));

test('nested liked pages use absolute server-provided endpoints without waiting for mage/url', async () => {
    const requests = [];
    const ui = gallery(async (url, options) => {
        requests.push({url, options});
        return {ok: true, json: async () => ({creations: {['a'.repeat(32)]: {liked: true, count: 7}}})};
    });
    await settled();
    assert.match(requests[0].url, /^https:\/\/shop.test\/galeria\/like\/state\/\?/);
    assert.equal(requests[0].options.cache, 'no-store');
    assert.equal(ui.button.disabled, false);
    assert.equal(ui.button.getAttribute('aria-pressed'), 'true');
    assert.equal(ui.count.textContent, 7);
});

test('visitors can like with a form key without a login redirect or a client-supplied identity', async () => {
    const requests = [];
    const ui = gallery(async (url, options) => {
        requests.push({url, options});
        return {ok: true, json: async () => options.method === 'POST'
            ? {liked: true, count: 3}
            : {creations: {['a'.repeat(32)]: {liked: false, count: 2}}}};
    });
    await settled();
    ui.click();
    await settled();
    assert.equal(requests[1].url, 'https://shop.test/galeria/like/save/');
    assert.equal(requests[1].options.body.get('form_key'), 'unit-form-key');
    assert.equal(requests[1].options.body.get('liked'), '1');
    assert.equal(requests[1].options.body.has('customer_id'), false);
    assert.equal(requests[1].options.body.has('visitor_token'), false);
    assert.equal(ui.button.getAttribute('aria-pressed'), 'true');
    assert.equal(ui.count.textContent, 3);
});

test('failed state loading keeps the control disabled and explains how to retry', async () => {
    const ui = gallery(async () => ({ok: false}));
    await settled();
    assert.equal(ui.button.disabled, true);
    assert.match(ui.message.textContent, /Atualize a página/);
});
