'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const ko = {};
const define = (dependencies, factory) => factory(ko);
define.amd = true;

vm.runInNewContext(
    fs.readFileSync(path.resolve(__dirname, '../../../../../../lib/web/knockoutjs/knockout.js'), 'utf8'),
    { define, setTimeout, clearTimeout }
);

const studioSource = fs.readFileSync(
    path.resolve(__dirname, '../../view/frontend/web/js/studio.js'),
    'utf8'
);

function createStudio(data, mockDoc, mockWindow) {
    let definition;
    const context = {
        define: (dependencies, factory) => {
            definition = factory({ extend: options => options }, ko);
        },
        document: mockDoc,
        window: mockWindow,
        setInterval,
        clearInterval,
        Date
    };
    vm.runInNewContext(studioSource, context);
    const instance = Object.assign(Object.create(definition), {
        studioData: data,
        _super() { return this; }
    });
    instance.initObservable();
    return instance;
}

function sampleContract() {
    return {
        produto: {
            sku: 'NUVEM-RUNNER',
            nome: 'Nuvem Runner',
            preco_base: 449.90,
            grade_tamanhos: [36, 37, 38],
            gravacao: { permitida: true, preco: 29.90, limite_caracteres: 12 }
        },
        configuracao_inicial: {
            tamanho: 38,
            gravacao: null,
            selecoes: [
                { grupo: 'cabedal', componente: 'CAB-MENTA' },
                { grupo: 'solado', componente: 'SOL-LUA' }
            ]
        },
        grupos: [
            {
                codigo: 'cabedal',
                nome: 'Cabedal',
                ordem: 1,
                componentes: [
                    { codigo: 'CAB-LAVANDA', nome: 'Lavanda', cor_hex: '#DCCFF7', preco_adicional: 39.90, disponivel: true },
                    { codigo: 'CAB-MENTA', nome: 'Menta', cor_hex: '#C6F0DC', preco_adicional: 0, disponivel: true }
                ]
            },
            {
                codigo: 'solado',
                nome: 'Solado',
                ordem: 2,
                componentes: [
                    { codigo: 'SOL-BORRACHA', nome: 'Borracha', cor_hex: '#DCCFF7', preco_adicional: 0, disponivel: true },
                    { codigo: 'SOL-LUA', nome: 'Sola Lua', cor_hex: '#E9E4FF', preco_adicional: 89.90, disponivel: true }
                ]
            }
        ],
        regras: [],
        urls: {
            disponibilidade: '/rest/default/V1/atelie/disponibilidade/NUVEM-RUNNER'
        }
    };
}

test('F3.7: updates component availability reactively on applyAvailability', () => {
    const data = sampleContract();
    const instance = createStudio(data);

    assert.equal(instance.isEsgotado('SOL-LUA'), false);
    assert.equal(instance.catalog.componentMap['SOL-LUA'].disponivel, true);

    // Backend availability response marks SOL-LUA as sold out
    instance.applyAvailability([
        { componente: 'SOL-LUA', disponivel: false }
    ]);

    assert.equal(instance.catalog.componentMap['SOL-LUA'].disponivel, false);
    assert.equal(instance.isEsgotado('SOL-LUA'), true);
});

test('F3.7: alerts user and disables add-to-cart when selected component sells out', () => {
    const data = sampleContract();
    const instance = createStudio(data);

    assert.equal(instance.selections.solado(), 'SOL-LUA');
    assert.equal(instance.hasSoldOutSelection(), false);
    assert.equal(instance.isAddToCartDisabled(), false);
    assert.equal(instance.actionError(), '');

    // Sola Lua sells out while user had it selected
    instance.applyAvailability([
        { componente: 'SOL-LUA', disponivel: false }
    ]);

    assert.equal(instance.hasSoldOutSelection(), true);
    assert.equal(instance.isAddToCartDisabled(), true);
    assert.ok(instance.actionError().includes('acabou de esgotar no estoque'));
    assert.ok(instance.actionError().includes('Sola Lua'));
    assert.equal(instance.validate(), false);
});

test('F3.7: re-enables add-to-cart and clears alert when customer selects an available component', () => {
    const data = sampleContract();
    const instance = createStudio(data);

    // Sola Lua sells out
    instance.applyAvailability([
        { componente: 'SOL-LUA', disponivel: false }
    ]);
    assert.equal(instance.isAddToCartDisabled(), true);

    // Customer switches to Solado Borracha (available)
    const solBorracha = instance.catalog.componentMap['SOL-BORRACHA'];
    instance.selectOption('solado', solBorracha);

    assert.equal(instance.selections.solado(), 'SOL-BORRACHA');
    assert.equal(instance.hasSoldOutSelection(), false);
    assert.equal(instance.actionError(), '');
    assert.equal(instance.isAddToCartDisabled(), false);
    assert.equal(instance.validate(), true);
});

test('F3.7: blocks selecting an option that is sold out', () => {
    const data = sampleContract();
    const instance = createStudio(data);

    // Mark Lavanda as sold out
    instance.applyAvailability([
        { componente: 'CAB-LAVANDA', disponivel: false }
    ]);

    // Currently selected is CAB-MENTA
    assert.equal(instance.selections.cabedal(), 'CAB-MENTA');

    // Attempt to select sold out Lavanda
    const cabLavanda = instance.catalog.componentMap['CAB-LAVANDA'];
    instance.selectOption('cabedal', cabLavanda);

    // Selection must remain CAB-MENTA
    assert.equal(instance.selections.cabedal(), 'CAB-MENTA');
});

test('F3.7: Page Visibility API integration pauses polling when hidden and resumes when visible', () => {
    let visibilityCallback = null;
    const mockDoc = {
        visibilityState: 'visible',
        addEventListener: (event, handler) => {
            if (event === 'visibilitychange') {
                visibilityCallback = handler;
            }
        },
        removeEventListener: () => {}
    };
    const mockWin = {};

    const data = sampleContract();
    const instance = createStudio(data, mockDoc, mockWin);

    assert.equal(instance.pollingIntervalMs, 20000);
    assert.ok(instance._pollingTimer !== null, 'Polling timer must be active when visible');

    // User hides tab
    mockDoc.visibilityState = 'hidden';
    instance.handleVisibilityChange();
    assert.equal(instance._pollingTimer, null, 'Polling timer must be stopped when tab is hidden');

    // User returns to tab
    mockDoc.visibilityState = 'visible';
    instance.handleVisibilityChange();
    assert.ok(instance._pollingTimer !== null, 'Polling timer must be restored when tab is visible');

    instance.destroy();
    assert.equal(instance._pollingTimer, null);
});

test('F3.7: resolves REST availability endpoint correctly', () => {
    const data = sampleContract();
    const instance = createStudio(data);

    const url = instance.getAvailabilityUrl();
    assert.equal(url, '/rest/default/V1/atelie/disponibilidade/NUVEM-RUNNER');
});

test('F3.7: verifies XHR/fetch is called every 20s and paused while tab is hidden', async () => {
    let fetchCalls = 0;
    const requestedUrls = [];
    let visibilityHandler = null;

    const mockDoc = {
        visibilityState: 'visible',
        addEventListener: (event, handler) => {
            if (event === 'visibilitychange') {
                visibilityHandler = handler;
            }
        },
        removeEventListener: () => {}
    };

    const mockFetch = async (url) => {
        fetchCalls++;
        requestedUrls.push(url);
        return {
            ok: true,
            status: 200,
            json: async () => [{ componente: 'SOL-LUA', disponivel: false }]
        };
    };

    let timerCallback = null;
    let timerCleared = false;
    let activeIntervalMs = null;
    const mockSetInterval = (fn, ms) => {
        timerCallback = fn;
        activeIntervalMs = ms;
        timerCleared = false;
        return 999;
    };
    const mockClearInterval = (id) => {
        timerCleared = true;
        timerCallback = null;
    };

    let definition;
    const context = {
        define: (dependencies, factory) => {
            definition = factory({ extend: options => options }, ko);
        },
        document: mockDoc,
        window: {},
        fetch: mockFetch,
        setInterval: mockSetInterval,
        clearInterval: mockClearInterval,
        Date
    };
    vm.runInNewContext(studioSource, context);
    const instance = Object.assign(Object.create(definition), {
        studioData: sampleContract(),
        _super() { return this; }
    });
    instance.initObservable();

    // 1. Initial state: timer scheduled for 20000ms (20s)
    assert.equal(activeIntervalMs, 20000);
    assert.equal(fetchCalls, 0);

    // 2. 20s tick occurs -> 1st call made
    timerCallback();
    await new Promise(resolve => setTimeout(resolve, 10));
    assert.equal(fetchCalls, 1);
    assert.equal(requestedUrls[0], '/rest/default/V1/atelie/disponibilidade/NUVEM-RUNNER');
    // Live update check: SOL-LUA was marked false in response!
    assert.equal(instance.isEsgotado('SOL-LUA'), true);

    // 3. Another 20s tick occurs -> 2nd call made
    timerCallback();
    await new Promise(resolve => setTimeout(resolve, 10));
    assert.equal(fetchCalls, 2);

    // 4. Tab becomes hidden (user switches tab or minimizes)
    mockDoc.visibilityState = 'hidden';
    visibilityHandler();

    // Timer MUST be cleared immediately
    assert.equal(timerCleared, true);
    assert.equal(instance._pollingTimer, null);

    // Calling fetchAvailability while hidden must NOT make any call
    instance.fetchAvailability();
    assert.equal(fetchCalls, 2, 'No fetch must occur when tab is hidden');

    // 5. User returns to tab (visible)
    mockDoc.visibilityState = 'visible';
    visibilityHandler();

    // Timer is resumed
    assert.equal(activeIntervalMs, 20000);
    assert.ok(instance._pollingTimer !== null);

    instance.destroy();
});
