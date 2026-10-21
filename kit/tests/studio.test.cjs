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
    assert.equal(instance.grupos()[0].opcoes[0].hex, '#DCCFF7');
    assert.equal(instance.grupos()[0].opcoes[0].acrescimo, 39.90);
    assert.equal(instance.selectedSize(), 38);
    assert.equal(instance.selections.cabedal(), 'CAB-MENTA');
    assert.equal(instance.laserLimit(), 12);
    assert.equal(instance.permiteGravacao(), true);
    assert.equal(instance.mirrorPrice(), 449.90);
});

test('updates mirror price when a component and engraving change', () => {
    const instance = studio(contract());
    instance.selectOption('cabedal', instance.grupos()[0].opcoes[0]);
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
