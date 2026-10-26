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
    fs.readFileSync(path.resolve(__dirname, '../../src/lib/web/knockoutjs/knockout.js'), 'utf8'),
    {define, setTimeout, clearTimeout}
);

const source = fs.readFileSync(
    path.resolve(__dirname, '../../src/app/code/Nuvem/AtelieStudio/view/frontend/web/js/studio.js'), 'utf8'
);

function studio(data) {
    let definition;
    vm.runInNewContext(source, {
        define: (dependencies, factory) => {
            definition = factory({extend: options => options}, ko);
        }
    });
    const instance = Object.assign(Object.create(definition), {
        studioData: data,
        _super() { return this; }
    });
    instance.initObservable();
    return instance;
}

function contract() {
    return {
        produto: {
            preco_base: 449.90,
            grade_tamanhos: [36, 37, 38],
            gravacao: {permitida: true, preco: 29.90, limite_caracteres: 12}
        },
        configuracao_inicial: {
            tamanho: 38,
            gravacao: null,
            selecoes: [{grupo: 'cabedal', componente: 'CAB-MENTA'}]
        },
        grupos: [{
            codigo: 'cabedal', nome: 'Cabedal',
            componentes: [
                {codigo: 'CAB-LAVANDA', nome: 'Lavanda', cor_hex: '#DCCFF7', preco_adicional: 39.90},
                {codigo: 'CAB-MENTA', nome: 'Menta', cor_hex: '#C6F0DC', preco_adicional: 0}
            ]
        }],
        regras: []
    };
}

test('uses the ViewModel contract for options, size, engraving and initial selections', () => {
    const data = contract();
    const instance = studio(data);
    assert.equal(instance.grupos().length, 1);
    assert.equal(instance.getOptionColor(instance.currentOptions()[0]), '#DCCFF7');
    assert.equal(instance.getOptionPrice(instance.currentOptions()[0]), 39.90);
    assert.equal(instance.selectedSize(), 38);
    assert.equal(instance.selections.cabedal(), 'CAB-MENTA');
    assert.equal(instance.laserLimit(), 12);
    assert.equal(instance.permiteGravacao(), true);
    assert.equal(instance.mirrorPrice(), 449.90);
});

test('updates mirror price when a component and engraving change', () => {
    const instance = studio(contract());
    instance.selectOption('cabedal', instance.currentOptions()[0]);
    instance.laserText('LUIZ');
    assert.ok(Math.abs(instance.mirrorPrice() - 519.70) < 0.001);
});

test('honors a product without engraving and its own size range', () => {
    const data = contract();
    data.produto.grade_tamanhos = [28, 29, 30];
    data.produto.gravacao.permitida = false;
    data.configuracao_inicial.tamanho = 30;
    const instance = studio(data);
    instance.laserText('LUIZ');
    assert.equal(instance.selectedSize(), 30);
    assert.equal(instance.permiteGravacao(), false);
    assert.equal(instance.mirrorPrice(), 449.90);
});

test('blocks selection and triggers tooltip when clicking an incompatible option', () => {
    const data = contract();
    data.grupos.push({
        codigo: 'solado', nome: 'Solado',
        componentes: [
            {codigo: 'SOL-BORRACHA', nome: 'Borracha', cor_hex: '#DCCFF7', preco_adicional: 0, padrao: 1},
            {codigo: 'SOL-LUA', nome: 'Lua', cor_hex: '#E9E4FF', preco_adicional: 89.90, padrao: 0}
        ]
    });
    data.configuracao_inicial.selecoes.push({grupo: 'solado', componente: 'SOL-BORRACHA'});
    data.regras = [
        {codigo: 'R01', tipo: 'bloqueia', origem: 'CAB-LAVANDA', alvo_tipo: 'componente', alvo: 'SOL-LUA', mensagem: 'Incompativel'}
    ];

    const instance = studio(data);
    // Select CAB-LAVANDA
    instance.selectOption('cabedal', instance.currentOptions()[0]);
    assert.equal(instance.selections.cabedal(), 'CAB-LAVANDA');
    assert.equal(instance.isBlocked('SOL-LUA'), true);

    // Try to click SOL-LUA (blocked)
    const solLuaOption = {codigo: 'SOL-LUA', nome: 'Lua', cor_hex: '#E9E4FF', preco_adicional: 89.90};
    instance.selectOption('solado', solLuaOption);

    // Selection must NOT change, and tooltip must activate
    assert.equal(instance.selections.solado(), 'SOL-BORRACHA');
    assert.equal(instance.activeTooltip(), 'SOL-LUA');
    assert.equal(instance.getBlockReason('SOL-LUA'), 'Incompativel');
});

test('auto-resolves blocked selection when required option changes', () => {
    const data = contract();
    data.grupos.push({
        codigo: 'solado', nome: 'Solado',
        componentes: [
            {codigo: 'SOL-BORRACHA', nome: 'Borracha', cor_hex: '#DCCFF7', preco_adicional: 0, padrao: 1},
            {codigo: 'SOL-TRANSLUCIDO', nome: 'Translúcido', cor_hex: '#EAF4FB', preco_adicional: 34.90, padrao: 0}
        ]
    });
    data.grupos.push({
        codigo: 'entressola', nome: 'Entressola',
        componentes: [
            {codigo: 'ENT-EVA', nome: 'EVA', cor_hex: '#FBFAFF', preco_adicional: 0, padrao: 1},
            {codigo: 'ENT-AR', nome: 'Câmara de Ar', cor_hex: '#E8F6FF', preco_adicional: 49.90, padrao: 0}
        ]
    });
    data.configuracao_inicial.selecoes.push({grupo: 'solado', componente: 'SOL-TRANSLUCIDO'});
    data.configuracao_inicial.selecoes.push({grupo: 'entressola', componente: 'ENT-AR'});
    data.regras = [
        {codigo: 'R04', tipo: 'exige', origem: 'ENT-AR', alvo_tipo: 'componente', alvo: 'SOL-TRANSLUCIDO', mensagem: 'Precisa de solado translúcido'}
    ];

    const instance = studio(data);
    assert.equal(instance.selections.entressola(), 'ENT-AR');
    assert.equal(instance.isBlocked('ENT-AR'), false);

    // User switches solado to Borracha (which violates R04 for ENT-AR)
    const solBorracha = {codigo: 'SOL-BORRACHA', nome: 'Borracha', cor_hex: '#DCCFF7', preco_adicional: 0};
    instance.selectOption('solado', solBorracha);

    // entressola must auto-resolve to default ENT-EVA so blocked option is never selected
    assert.equal(instance.selections.solado(), 'SOL-BORRACHA');
    assert.equal(instance.selections.entressola(), 'ENT-EVA');
    assert.equal(instance.isBlocked('ENT-AR'), true);
});

test('preview text and getLayerColor reactivity', () => {
    const data = contract();
    const instance = studio(data);
    assert.equal(instance.getLayerColor('camada-cabedal'), '#C6F0DC');

    // Switch to lavanda
    instance.selectOption('cabedal', instance.currentOptions()[0]);
    assert.equal(instance.getLayerColor('camada-cabedal'), '#DCCFF7');

    // Laser text
    instance.laserText('<script>');
    assert.equal(instance.engravingPreviewText(), '<script>');
});

test('associates groups to layers via camada_svg and updates element color via atelieCamada', () => {
    const data = contract();
    data.grupos[0].camada_svg = 'custom-camada-id';
    const instance = studio(data);

    // Group resolved by camada_svg
    assert.equal(instance.getGroupByCamada('custom-camada-id').codigo, 'cabedal');
    assert.equal(instance.getLayerColor('custom-camada-id'), '#C6F0DC');

    // Test atelieCamada bindingHandler update
    let appliedColor = null;
    let appliedAttr = null;
    const fakeElement = {
        id: 'custom-camada-id',
        querySelectorAll: () => [fakeElement],
        getAttribute: (attr) => attr === 'data-pintar' ? 'fill' : appliedColor,
        setAttribute: (attr, val) => {
            appliedAttr = attr;
            appliedColor = val;
        }
    };

    ko.bindingHandlers.atelieCamada.update(fakeElement, () => 'custom-camada-id', {}, instance);
    assert.equal(appliedAttr, 'fill');
    assert.equal(appliedColor, '#C6F0DC');

    // Switch option
    instance.selectOption('cabedal', instance.currentOptions()[0]);
    ko.bindingHandlers.atelieCamada.update(fakeElement, () => 'custom-camada-id', {}, instance);
    assert.equal(appliedColor, '#DCCFF7');
});
