'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function loadMixin(hash, fetch, pending = null) {
    let definition;
    vm.runInNewContext(fs.readFileSync(
        path.resolve(__dirname, '../../view/frontend/web/js/studio-save-mixin.js'), 'utf8'
    ), {
        define: (dependencies, factory) => factory({}, {build: value => '/' + value})({
            extend: value => { definition = value; }
        }),
        URL,
        window: {location: {hash, origin: 'https://shop.test'}, sessionStorage: {getItem: () => JSON.stringify(pending)}},
        fetch
    });
    return definition;
}

test('saved creation hashes bypass the embedded configuration parser', () => {
    const mixin = loadMixin('#c=' + 'a'.repeat(32));
    const initial = {tamanho: 38};
    assert.equal(mixin.resolveInitialConfiguration.call({
        _super: () => assert.fail('Saved hash must not be parsed as inline configuration')
    }, {}, initial), initial);
});

test('inline share hashes keep the existing studio behavior', () => {
    const mixin = loadMixin('#atelie=encoded');
    const catalog = {}, initial = {}, expected = {};
    assert.equal(mixin.resolveInitialConfiguration.call({
        _super: (c, i) => { assert.equal(c, catalog); assert.equal(i, initial); return expected; }
    }, catalog, initial), expected);
});

test('remix restores all selections, size and engraving and resets undo history', () => {
    const mixin = loadMixin('');
    let snapshot, reset = false, message;
    const configuration = {tamanho: 40, gravacao: 'BIA', selecoes: [
        {grupo: 'cabedal', componente: 'CAB-TELA-CEU'},
        {grupo: 'solado', componente: 'SOL-LUA-HOLO'}
    ]};
    const applied = mixin.applyCreationConfiguration.call({
        catalog: {}, validateSharedConfig: value => value,
        restoreSnapshot: value => { snapshot = value; },
        createSnapshot: () => snapshot, history: value => { reset = value[0] === snapshot; },
        historyIndex: value => assert.equal(value, 0), creationLoadError: value => { message = value; }
    }, configuration);
    assert.equal(applied, true);
    assert.deepEqual(JSON.parse(JSON.stringify(snapshot)), {
        tamanho: 40, laserText: 'BIA', selecoes: {cabedal: 'CAB-TELA-CEU', solado: 'SOL-LUA-HOLO'}
    });
    assert.equal(reset, true);
    assert.equal(message, '');
});

test('unavailable saved configurations do not partially overwrite the studio', () => {
    const mixin = loadMixin('');
    let message;
    assert.equal(mixin.applyCreationConfiguration.call({
        catalog: {}, validateSharedConfig: () => null,
        restoreSnapshot: () => assert.fail('Invalid remix must not be applied'),
        creationLoadError: value => { message = value; }
    }, {}), false);
    assert.match(message, /não estão mais disponíveis/);
});

test('saved hash fetch uses the server configuration and the canonical SKU accessor', async () => {
    let applied;
    const configuration = {tamanho: 38, gravacao: null, selecoes: []};
    const mixin = loadMixin('#c=' + 'b'.repeat(32), async url => {
        assert.match(url, /atelie\/creation\/get\?hash=b{32}/);
        return {ok: true, json: async () => ({success: true, sku: 'NUVEM-RUNNER', configuration})};
    });
    mixin.loadCreationFromHash.call({
        getConfigData: () => ({produto: {sku: 'NUVEM-RUNNER'}}),
        applyCreationConfiguration: value => { applied = value; },
        creationLoadError: message => { assert.match(message, /Carregando/); }
    });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(applied, configuration);
});


test('a previous draft cannot override a creation explicitly selected for remix', async () => {
    const configuration = {tamanho: 37, gravacao: null, selecoes: []};
    const draft = {sku: 'NUVEM-RUNNER', returnUrl: 'https://shop.test/nuvem-runner.html',
        configuration: {tamanho: 38, gravacao: 'OLD', selecoes: []}};
    let applied;
    const mixin = loadMixin('#c=' + 'c'.repeat(32), async () => ({
        ok: true, json: async () => ({success: true, sku: 'NUVEM-RUNNER', configuration})
    }), draft);
    mixin.loadCreationFromHash.call({
        getConfigData: () => ({produto: {sku: 'NUVEM-RUNNER'}}),
        applyCreationConfiguration: value => { applied = value; },
        creationLoadError: () => {}
    });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(applied, configuration);
});

test('a failed remix displays a visible load error instead of silently leaving the default', async () => {
    const messages = [];
    const mixin = loadMixin('#c=' + 'd'.repeat(32), async () => ({ok: false}));
    mixin.loadCreationFromHash.call({
        getConfigData: () => ({produto: {sku: 'NUVEM-RUNNER'}}),
        applyCreationConfiguration: () => assert.fail('A failed request must not apply a configuration'),
        creationLoadError: message => messages.push(message)
    });
    await new Promise(resolve => setImmediate(resolve));
    assert.match(messages[0], /Carregando/);
    assert.match(messages.at(-1), /não está disponível/);
});
