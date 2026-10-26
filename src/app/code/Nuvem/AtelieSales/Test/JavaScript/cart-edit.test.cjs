const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function observable(initial) {
    let value = initial;
    return function (next) {
        if (arguments.length) { value = next; }
        return value;
    };
}

function load(relativePath, dependencies, globals = {}) {
    let exported;
    const file = path.resolve(__dirname, '../../../', relativePath);
    vm.runInNewContext(fs.readFileSync(file, 'utf8'), {
        define: (names, factory) => { exported = factory(...dependencies); },
        ...globals
    }, {filename: file});
    return exported;
}

function studio(response, syncOutcomes = [true]) {
    const requests = [];
    let reloads = 0;
    const $ = () => ({serializeArray: () => [
        {name: 'id', value: 999}, {name: 'qty', value: 99},
        {name: 'form_key', value: 'test-key'}, {name: 'atelie[gravacao]', value: ''}
    ]});
    $.ajax = options => {
        requests.push(options);
        return {
            done(callback) { callback(response); return this; },
            fail() { return this; },
            always(callback) { callback(); return this; }
        };
    };
    const customerData = {
        invalidate: sections => assert.deepEqual(Array.from(sections), ['cart']),
        reload: () => {
            reloads++;
            const success = syncOutcomes.length ? syncOutcomes.shift() : true;
            return {
                done(callback) { if (success) { callback(); } return this; },
                fail(callback) { if (!success) { callback(); } return this; }
            };
        }
    };
    const methods = load('AtelieStudio/view/frontend/web/js/studio.js', [
        {extend: value => value}, {observable}, $, customerData, value => value
    ]);
    const instance = {
        ...methods, saving: observable(false), saveCompleted: observable(false),
        cartSyncFailed: observable(false), saveMessage: observable(''), saveFailed: observable(false), editError: '',
        getConfigData: () => ({item_id: 42, qty: 3, urls: {update_item: '/checkout/cart/updateItemOptions/id/42/'}})
    };
    const event = {target: {id: 'product_addtocart_form'}, preventDefault() {}, stopImmediatePropagation() {}};
    return {instance, requests, event, reloads: () => reloads};
}

test('successful edit posts the original id and quantity, retaining empty engraving', () => {
    const {instance, requests, event, reloads} = studio({success: true, messages: ['Saved']});
    instance.submitEdit(event);
    assert.equal(requests.length, 1);
    const fields = Object.fromEntries(requests[0].data.map(field => [field.name, field.value]));
    assert.equal(fields.id, 42);
    assert.equal(fields.qty, 3);
    assert.equal(fields['atelie[gravacao]'], '');
    assert.equal(instance.saveCompleted(), true);
    assert.equal(instance.saving(), false);
    assert.equal(reloads(), 1);
    assert.equal(instance.cartSyncFailed(), false);
});

test('validation error stays visible and does not refresh or resubmit the cart', () => {
    const {instance, event, reloads} = studio({success: false, messages: ['Invalid combination']});
    instance.submitEdit(event);
    assert.equal(instance.saveFailed(), true);
    assert.equal(instance.saveMessage(), 'Invalid combination');
    assert.equal(instance.saveCompleted(), false);
    assert.equal(reloads(), 0);
});

test('pending submissions and corrupt initial configuration cannot submit', () => {
    const {instance, requests, event} = studio({success: true});
    instance.saving(true);
    instance.submitEdit(event);
    instance.saving(false);
    instance.editError = 'Corrupt configuration';
    instance.submitEdit(event);
    assert.equal(requests.length, 0);
});

test('checkout thumbnail reads the current customer section on every binding evaluation', () => {
    const cart = observable({atelie_items: {42: {image: '/old-preview'}}});
    const mixin = load('AtelieSales/view/frontend/web/js/view/summary/thumbnail-mixin.js', [{get: () => cart}]);
    const methods = mixin({extend: value => value});
    assert.equal(methods.getSrc({item_id: 42}), '/old-preview');
    cart({atelie_items: {42: {image: '/new-preview'}}});
    assert.equal(methods.getSrc({item_id: 42}), '/new-preview');
});


test('checkout synchronizes meaningful cross-tab changes and ignores section timestamp churn', () => {
    let data = {atelie_items: {42: {image: '/old-preview'}}, subtotalAmount: 100, items: []};
    let subscriber;
    let reloads = 0;
    let totalRequests = 0;
    const events = {};
    const cart = () => data;
    cart.subscribe = callback => { subscriber = callback; };
    const $ = () => ({on: (event, handler) => { events[event] = handler; }});
    const customerData = {get: () => cart, reload: () => { reloads++; }};
    const getTotals = () => {
        totalRequests++;
        return {fail() { return this; }, always(callback) { callback(); return this; }};
    };
    const initialize = load('AtelieSales/view/frontend/web/js/checkout-sync.js', [
        $, customerData, getTotals, {totals: () => ({})}
    ], {window: {}});
    initialize();
    assert.equal(totalRequests, 1);
    subscriber({...data, data_id: 999});
    assert.equal(totalRequests, 1);
    events['storage.atelieCheckout']({originalEvent: {
        key: 'mage-cache-storage',
        oldValue: JSON.stringify({cart: {...data, data_id: 1}}),
        newValue: JSON.stringify({cart: {...data, data_id: 2}})
    }});
    assert.equal(reloads, 0);
    const next = {...data, atelie_items: {42: {image: '/new-preview'}}};
    events['storage.atelieCheckout']({originalEvent: {
        key: 'mage-cache-storage', oldValue: JSON.stringify({cart: data}), newValue: JSON.stringify({cart: next})
    }});
    assert.equal(reloads, 1);
    data = next;
    subscriber(data);
    assert.equal(totalRequests, 2);
    events['focus.atelieCheckout']();
    assert.equal(reloads, 2);
});


test('a failed cart refresh offers a retry that never resubmits the saved configuration', () => {
    const {instance, requests, event, reloads} = studio({success: true, messages: ['Saved']}, [false, true]);
    instance.submitEdit(event);
    assert.equal(instance.saveCompleted(), true);
    assert.equal(instance.saveFailed(), false);
    assert.equal(instance.cartSyncFailed(), true);
    assert.match(instance.saveMessage(), /alterações foram salvas/);
    instance.refreshCart();
    assert.equal(instance.cartSyncFailed(), false);
    assert.match(instance.saveMessage(), /resumo do carrinho foi atualizado/);
    assert.equal(requests.length, 1);
    assert.equal(reloads(), 2);
});


test('ordinary carts keep their checkout flow, including after the last Atelie item is removed', () => {
    let data = {items: [], subtotalAmount: 100, atelie_items: {}};
    let subscriber;
    let reloads = 0;
    let totalRequests = 0;
    const events = {};
    const cart = () => data;
    cart.subscribe = callback => { subscriber = callback; };
    const $ = () => ({on: (event, handler) => { events[event] = handler; }});
    load('AtelieSales/view/frontend/web/js/checkout-sync.js', [
        $, {get: () => cart, reload: () => { reloads++; }},
        () => {
            totalRequests++;
            return {fail() { return this; }, always(callback) { callback(); return this; }};
        },
        {totals: () => ({})}
    ], {window: {}})();
    events['focus.atelieCheckout']();
    subscriber({...data, subtotalAmount: 200});
    assert.equal(reloads, 0);
    assert.equal(totalRequests, 0);
    data = {...data, atelie_items: {42: {image: '/preview'}}};
    subscriber(data);
    assert.equal(totalRequests, 1);
    data = {...data, atelie_items: {}};
    subscriber(data);
    assert.equal(totalRequests, 2);
    events['focus.atelieCheckout']();
    assert.equal(reloads, 0);
});
