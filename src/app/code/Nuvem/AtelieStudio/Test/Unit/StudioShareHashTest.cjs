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
    const doc = mockDoc || {
        title: 'PDP Nuvem Runner',
        addEventListener: () => {},
        removeEventListener: () => {},
        querySelectorAll: () => [],
        querySelector: () => null,
        getElementById: () => null,
        createElement: (tag) => ({
            tagName: tag,
            style: {},
            setAttribute: () => {},
            focus: () => {},
            select: () => {}
        }),
        body: {
            appendChild: () => {},
            removeChild: () => {}
        },
        execCommand: () => true
    };
    const win = mockWindow || {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: ''
        },
        history: {
            replaceState: () => {}
        },
        addEventListener: () => {},
        removeEventListener: () => {}
    };
    const context = {
        define: (dependencies, factory) => {
            definition = factory({ extend: options => options }, ko);
        },
        document: doc,
        window: win,
        navigator: win.navigator || {},
        Buffer,
        setInterval: () => 1,
        clearInterval: () => {},
        setTimeout,
        clearTimeout,
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

function fullCatalogContract() {
    return {
        produto: {
            sku: 'NUVEM-RUNNER',
            nome: 'Nuvem Runner',
            preco_base: 449.90,
            prazo_producao_dias: 12,
            grade_tamanhos: [34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44],
            gravacao: {
                permitida: true,
                preco: 29.90,
                limite_caracteres: 12,
                placeholder: 'Ex: NUVEM'
            }
        },
        configuracao_inicial: {
            tamanho: 38,
            gravacao: null,
            selecoes: [
                { grupo: 'cabedal', componente: 'CAB-TRICO-LAVANDA' },
                { grupo: 'biqueira', componente: 'BIQ-LISA-CEU' },
                { grupo: 'calcanhar', componente: 'CAL-ACOLCHOADO-CEU' },
                { grupo: 'cadarco', componente: 'CAD-ELASTICO-MENTA' },
                { grupo: 'emblema', componente: 'EMB-NUVEM-PESSEGO' },
                { grupo: 'entressola', componente: 'ENT-EVA-NUVEM' },
                { grupo: 'solado', componente: 'SOL-TRANSLUCIDO-GELO' }
            ]
        },
        grupos: [
            {
                codigo: 'cabedal',
                nome: 'Cabedal',
                ordem: 1,
                camada_svg: 'camada-cabedal',
                componentes: [
                    { codigo: 'CAB-TRICO-LAVANDA', nome: 'Tricô Lavanda', cor_hex: '#B8A9C9', acabamento: 'trico', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'CAB-TRICO-MENTA', nome: 'Tricô Menta', cor_hex: '#A8E6CF', acabamento: 'trico', preco_adicional: 0, padrao: false, disponivel: true },
                    { codigo: 'CAB-CAMURCA-AREIA', nome: 'Camurça Areia', cor_hex: '#E8D8C8', acabamento: 'camurca', preco_adicional: 25.00, padrao: false, disponivel: true },
                    { codigo: 'CAB-COURO-BRANCO', nome: 'Couro Branco', cor_hex: '#F5F5F5', acabamento: 'couro', preco_adicional: 40.00, padrao: false, disponivel: true }
                ]
            },
            {
                codigo: 'biqueira',
                nome: 'Biqueira',
                ordem: 2,
                camada_svg: 'camada-biqueira',
                componentes: [
                    { codigo: 'BIQ-LISA-CEU', nome: 'Lisa Céu', cor_hex: '#A0C4E2', acabamento: 'liso', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'BIQ-TEXT-MENTA', nome: 'Texturizada Menta', cor_hex: '#A8E6CF', acabamento: 'texturizado', preco_adicional: 15.00, padrao: false, disponivel: true },
                    { codigo: 'BIQ-REFORC-AREIA', nome: 'Reforçada Areia', cor_hex: '#E8D8C8', acabamento: 'reforcado', preco_adicional: 20.00, padrao: false, disponivel: true },
                    { codigo: 'BIQ-CAMURCA-ROSA', nome: 'Camurça Rosa', cor_hex: '#F4C2C2', acabamento: 'camurca', preco_adicional: 25.00, padrao: false, disponivel: true }
                ]
            },
            {
                codigo: 'calcanhar',
                nome: 'Calcanhar',
                ordem: 3,
                camada_svg: 'camada-calcanhar',
                componentes: [
                    { codigo: 'CAL-ACOLCHOADO-CEU', nome: 'Acolchoado Céu', cor_hex: '#A0C4E2', acabamento: 'acolchoado', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'CAL-PUXADOR-LAVANDA', nome: 'Com Puxador Lavanda', cor_hex: '#B8A9C9', acabamento: 'puxador', preco_adicional: 15.00, padrao: false, disponivel: true },
                    { codigo: 'CAL-ESTAB-GRAFITE', nome: 'Estabilizador Grafite', cor_hex: '#4A4A4A', acabamento: 'estabilizador', preco_adicional: 30.00, padrao: false, disponivel: true },
                    { codigo: 'CAL-REFLET-PRATA', nome: 'Refletivo Prata', cor_hex: '#D3D3D3', acabamento: 'refletivo', preco_adicional: 35.00, padrao: false, disponivel: true }
                ]
            },
            {
                codigo: 'cadarco',
                nome: 'Cadarço',
                ordem: 4,
                camada_svg: 'camada-cadarco',
                componentes: [
                    { codigo: 'CAD-ELASTICO-MENTA', nome: 'Elástico Menta', cor_hex: '#A8E6CF', acabamento: 'elastico', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'CAD-CHATO-BRANCO', nome: 'Chato Branco', cor_hex: '#FFFFFF', acabamento: 'algodao', preco_adicional: 0, padrao: false, disponivel: true },
                    { codigo: 'CAD-REDONDO-PESSEGO', nome: 'Redondo Pêssego', cor_hex: '#FFD3B6', acabamento: 'poliester', preco_adicional: 10.00, padrao: false, disponivel: true },
                    { codigo: 'CAD-BICOLOR-LAV-CEU', nome: 'Bicolor Lavanda/Céu', cor_hex: '#B8A9C9', acabamento: 'bicolor', preco_adicional: 15.00, padrao: false, disponivel: true }
                ]
            },
            {
                codigo: 'emblema',
                nome: 'Emblema',
                ordem: 5,
                camada_svg: 'camada-emblema',
                componentes: [
                    { codigo: 'EMB-NUVEM-PESSEGO', nome: 'Nuvem Pêssego', cor_hex: '#FFD3B6', acabamento: 'emborrachado', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'EMB-BORRO-MENTA', nome: 'Bordado Menta', cor_hex: '#A8E6CF', acabamento: 'bordado', preco_adicional: 12.00, padrao: false, disponivel: true },
                    { codigo: 'EMB-MINIMAL-BRANCO', nome: 'Minimal Branco', cor_hex: '#FFFFFF', acabamento: 'estampado', preco_adicional: 0, padrao: false, disponivel: true },
                    { codigo: 'EMB-METAL-DOURADO', nome: 'Metálico Dourado', cor_hex: '#D4AF37', acabamento: 'metalico', preco_adicional: 25.00, padrao: false, disponivel: true }
                ]
            },
            {
                codigo: 'entressola',
                nome: 'Entressola',
                ordem: 6,
                camada_svg: 'camada-entressola',
                componentes: [
                    { codigo: 'ENT-EVA-NUVEM', nome: 'EVA Nuvem', cor_hex: '#FFFFFF', acabamento: 'eva', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'ENT-AMORTEC-ROSA', nome: 'Amortecedor Rosa', cor_hex: '#F4C2C2', acabamento: 'gel', preco_adicional: 35.00, padrao: false, disponivel: true },
                    { codigo: 'ENT-RECICLADA-CREME', nome: 'Reciclada Creme', cor_hex: '#F0EAD6', acabamento: 'sustentavel', preco_adicional: 20.00, padrao: false, disponivel: true },
                    { codigo: 'ENT-AR-VISIVEL', nome: 'Câmara de Ar Visível', cor_hex: '#E0FFFF', acabamento: 'camara-ar', preco_adicional: 45.00, padrao: false, disponivel: true }
                ]
            },
            {
                codigo: 'solado',
                nome: 'Solado',
                ordem: 7,
                camada_svg: 'camada-solado',
                componentes: [
                    { codigo: 'SOL-TRANSLUCIDO-GELO', nome: 'Translúcido Gelo', cor_hex: '#E0EEEE', acabamento: 'borracha-translucida', preco_adicional: 0, padrao: true, disponivel: true },
                    { codigo: 'SOL-TRATOR-MENTA', nome: 'Tratorado Menta', cor_hex: '#A8E6CF', acabamento: 'borracha-tratorada', preco_adicional: 25.00, padrao: false, disponivel: true },
                    { codigo: 'SOL-CREPE-NATURAL', nome: 'Crepe Natural', cor_hex: '#E3C565', acabamento: 'borracha-crepe', preco_adicional: 30.00, padrao: false, disponivel: true },
                    { codigo: 'SOL-LUA-HOLO', nome: 'Sola Lua Holográfica', cor_hex: '#E6E6FA', acabamento: 'holografico', preco_adicional: 60.00, padrao: false, disponivel: true }
                ]
            }
        ],
        regras: [
            {
                codigo: 'R01',
                tipo: 'bloqueia',
                origem: 'CAB-CAMURCA-AREIA',
                alvo_tipo: 'componente',
                alvo: 'SOL-LUA-HOLO',
                mensagem: 'A camurça natural não pode ser combinada com o solado holográfico por questões de vulcanização na fábrica.'
            },
            {
                codigo: 'R02',
                tipo: 'exige',
                origem: 'SOL-LUA-HOLO',
                alvo_tipo: 'acabamento',
                alvo: 'refletivo',
                mensagem: 'A edição Sola Lua exige acabamento refletivo no calcanhar por padrão de segurança visual da linha.'
            },
            {
                codigo: 'R03',
                tipo: 'bloqueia',
                origem: 'CAD-BICOLOR-LAV-CEU',
                alvo_tipo: 'componente',
                alvo: 'EMB-METAL-DOURADO',
                mensagem: 'O cadarço bicolor não é compatível com o emblema metálico dourado na prensagem do modelo.'
            },
            {
                codigo: 'R04',
                tipo: 'bloqueia',
                origem: 'ENT-RECICLADA-CREME',
                alvo_tipo: 'gravacao',
                alvo: 'gravacao',
                mensagem: 'A entressola sustentável reciclada não suporta gravação térmica a laser.'
            }
        ]
    };
}

test('F3.10: serializeShareHash produces valid Base64URL string with atelie= prefix and parseShareHash restores it', () => {
    const studio = createStudio(fullCatalogContract());

    const snapshot = {
        tamanho: 41,
        selecoes: {
            cabedal: 'CAB-TRICO-MENTA',
            biqueira: 'BIQ-TEXT-MENTA',
            calcanhar: 'CAL-PUXADOR-LAVANDA',
            cadarco: 'CAD-CHATO-BRANCO',
            emblema: 'EMB-BORRO-MENTA',
            entressola: 'ENT-AMORTEC-ROSA',
            solado: 'SOL-TRATOR-MENTA'
        },
        laserText: 'NUVEM 41'
    };

    const hashString = studio.serializeShareHash(snapshot);
    assert.match(hashString, /^atelie=[A-Za-z0-9_-]+$/, 'Deve iniciar com atelie= e conter apenas caracteres URL-safe');

    const parsed = studio.parseShareHash(hashString);
    assert.ok(parsed, 'O parse do hash não deve ser nulo');
    assert.equal(parsed.t, 41);
    assert.equal(parsed.s.cabedal, 'CAB-TRICO-MENTA');
    assert.equal(parsed.s.solado, 'SOL-TRATOR-MENTA');
    assert.equal(parsed.g, 'NUVEM 41');
});

test('F3.10: Safe hydration restores 100% of creation from URL hash on initialization', () => {
    const customSnapshot = {
        t: 42,
        s: {
            cabedal: 'CAB-COURO-BRANCO',
            biqueira: 'BIQ-REFORC-AREIA',
            calcanhar: 'CAL-REFLET-PRATA',
            cadarco: 'CAD-REDONDO-PESSEGO',
            emblema: 'EMB-MINIMAL-BRANCO',
            entressola: 'ENT-AMORTEC-ROSA',
            solado: 'SOL-CREPE-NATURAL'
        },
        g: 'CUSTOM'
    };

    const rawJson = JSON.stringify(customSnapshot);
    const b64 = Buffer.from(rawJson).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: '#atelie=' + b64
        },
        history: {
            replaceState: () => {}
        },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    assert.equal(studio.selectedSize(), 42, 'Tamanho 42 restaurado');
    assert.equal(studio.selections.cabedal(), 'CAB-COURO-BRANCO', 'Cabedal restaurado');
    assert.equal(studio.selections.biqueira(), 'BIQ-REFORC-AREIA', 'Biqueira restaurada');
    assert.equal(studio.selections.calcanhar(), 'CAL-REFLET-PRATA', 'Calcanhar restaurado');
    assert.equal(studio.selections.cadarco(), 'CAD-REDONDO-PESSEGO', 'Cadarço restaurado');
    assert.equal(studio.selections.emblema(), 'EMB-MINIMAL-BRANCO', 'Emblema restaurado');
    assert.equal(studio.selections.entressola(), 'ENT-AMORTEC-ROSA', 'Entressola restaurada');
    assert.equal(studio.selections.solado(), 'SOL-CREPE-NATURAL', 'Solado restaurado');
    assert.equal(studio.laserText(), 'CUSTOM', 'Gravação restaurada');
    assert.equal(studio.sharedNotice(), '', 'Nenhum erro de hash inválido emitido');

    // Comprova que o histórico inicia nesta criação compartilhada
    assert.equal(studio.canUndo(), false, 'Histórico inicia sem desfazer');
    assert.equal(studio.history()[0].tamanho, 42);
});

test('F3.10: Corrupted / invalid base64 hash falls back gracefully to default without throwing JS errors', () => {
    let replacedUrl = null;
    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: '#atelie=!!THIS_IS_CORRUPTED_BASE64_$%#!!'
        },
        history: {
            replaceState: (state, title, url) => {
                replacedUrl = url;
            }
        },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    // Deve carregar os padrões da loja
    assert.equal(studio.selectedSize(), 38, 'Fallback para tamanho padrão 38');
    assert.equal(studio.selections.cabedal(), 'CAB-TRICO-LAVANDA', 'Fallback para cabedal padrão');
    assert.equal(studio.laserText(), '', 'Fallback para sem gravação');

    // Deve exibir aviso amigável discreto
    assert.equal(studio.sharedNotice(), 'Configuração compartilhada inválida. Carregamos a sugestão original.');

    // Deve limpar o hash corrompido da barra de endereços
    assert.equal(replacedUrl, '/nuvem-runner.html');
});

test('F3.10: Tampered hash with invalid shoe size falls back to default', () => {
    const tampered = {
        t: 99, // Tamanho 99 não existe na grade do produto [34..44]
        s: {
            cabedal: 'CAB-TRICO-LAVANDA',
            biqueira: 'BIQ-LISA-CEU',
            calcanhar: 'CAL-ACOLCHOADO-CEU',
            cadarco: 'CAD-ELASTICO-MENTA',
            emblema: 'EMB-NUVEM-PESSEGO',
            entressola: 'ENT-EVA-NUVEM',
            solado: 'SOL-TRANSLUCIDO-GELO'
        },
        g: ''
    };

    const b64 = Buffer.from(JSON.stringify(tampered)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: '#atelie=' + b64
        },
        history: { replaceState: () => {} },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    assert.equal(studio.selectedSize(), 38, 'Fallback para o tamanho 38');
    assert.equal(studio.sharedNotice(), 'Configuração compartilhada inválida. Carregamos a sugestão original.');
});

test('F3.10: Tampered hash violating business rules (R01: Camurça + Sola Lua) is rejected with graceful fallback', () => {
    const tamperedCombination = {
        t: 38,
        s: {
            cabedal: 'CAB-CAMURCA-AREIA', // Camurça Areia
            biqueira: 'BIQ-LISA-CEU',
            calcanhar: 'CAL-REFLET-PRATA',
            cadarco: 'CAD-ELASTICO-MENTA',
            emblema: 'EMB-NUVEM-PESSEGO',
            entressola: 'ENT-EVA-NUVEM',
            solado: 'SOL-LUA-HOLO'        // Sola Lua (Violação da regra R01!)
        },
        g: ''
    };

    const b64 = Buffer.from(JSON.stringify(tamperedCombination)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: '#atelie=' + b64
        },
        history: { replaceState: () => {} },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    // Não deve aceitar a combinação proibida
    assert.equal(studio.selections.cabedal(), 'CAB-TRICO-LAVANDA');
    assert.equal(studio.selections.solado(), 'SOL-TRANSLUCIDO-GELO');
    assert.equal(studio.sharedNotice(), 'Configuração compartilhada inválida. Carregamos a sugestão original.');
});

test('F3.10: Tampered hash violating R04 (Reciclada + Gravação) is rejected with graceful fallback', () => {
    const tamperedEngraving = {
        t: 38,
        s: {
            cabedal: 'CAB-TRICO-LAVANDA',
            biqueira: 'BIQ-LISA-CEU',
            calcanhar: 'CAL-ACOLCHOADO-CEU',
            cadarco: 'CAD-ELASTICO-MENTA',
            emblema: 'EMB-NUVEM-PESSEGO',
            entressola: 'ENT-RECICLADA-CREME', // Entressola Reciclada (Proíbe gravação!)
            solado: 'SOL-TRANSLUCIDO-GELO'
        },
        g: 'FORBIDDEN'
    };

    const b64 = Buffer.from(JSON.stringify(tamperedEngraving)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: '#atelie=' + b64
        },
        history: { replaceState: () => {} },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    assert.equal(studio.selections.entressola(), 'ENT-EVA-NUVEM', 'Fallback para entressola padrão');
    assert.equal(studio.laserText(), '', 'Fallback para gravação vazia');
    assert.equal(studio.sharedNotice(), 'Configuração compartilhada inválida. Carregamos a sugestão original.');
});

test('F3.10: Tampered hash with non-existent component code is rejected with graceful fallback', () => {
    const fakeComponent = {
        t: 38,
        s: {
            cabedal: 'CAB-HACKED-CODE',
            biqueira: 'BIQ-LISA-CEU',
            calcanhar: 'CAL-ACOLCHOADO-CEU',
            cadarco: 'CAD-ELASTICO-MENTA',
            emblema: 'EMB-NUVEM-PESSEGO',
            entressola: 'ENT-EVA-NUVEM',
            solado: 'SOL-TRANSLUCIDO-GELO'
        },
        g: ''
    };

    const b64 = Buffer.from(JSON.stringify(fakeComponent)).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: '#atelie=' + b64
        },
        history: { replaceState: () => {} },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    assert.equal(studio.selections.cabedal(), 'CAB-TRICO-LAVANDA');
    assert.equal(studio.sharedNotice(), 'Configuração compartilhada inválida. Carregamos a sugestão original.');
});

test('F3.10: shareCreation updates browser hash and copies URL with navigator.clipboard', async () => {
    let copiedText = '';
    let updatedHistoryUrl = '';

    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: ''
        },
        history: {
            replaceState: (state, title, url) => {
                updatedHistoryUrl = url;
            }
        },
        navigator: {
            clipboard: {
                writeText: async (text) => {
                    copiedText = text;
                }
            }
        },
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), null, mockWin);

    // Customiza o tênis
    studio.selections.cabedal('CAB-TRICO-MENTA');
    studio.selectedSize(40);
    studio.laserText('AMOR');

    studio.shareCreation();

    // Aguarda microtask para Promise do writeText
    await new Promise(resolve => setTimeout(resolve, 50));

    assert.ok(updatedHistoryUrl.startsWith('https://magento.test/nuvem-runner.html#atelie='));
    assert.equal(copiedText, updatedHistoryUrl);
    assert.equal(studio.isCopied(), true, 'isCopied deve ser true após cópia bem sucedida');

    // Valida que a URL copiada pode ser desfeita de volta na mesma criação
    const hashPart = copiedText.split('#')[1];
    const parsed = studio.parseShareHash(hashPart);
    assert.equal(parsed.t, 40);
    assert.equal(parsed.s.cabedal, 'CAB-TRICO-MENTA');
    assert.equal(parsed.g, 'AMOR');
});

test('F3.10: shareCreation falls back to execCommand when clipboard API is unavailable', () => {
    let execCommandCalled = false;
    let appendedTextareaValue = '';

    const mockDoc = {
        title: 'PDP Nuvem Runner',
        addEventListener: () => {},
        removeEventListener: () => {},
        querySelectorAll: () => [],
        querySelector: () => null,
        getElementById: () => null,
        createElement: (tag) => {
            const el = {
                tagName: tag,
                style: {},
                setAttribute: () => {},
                focus: () => {},
                select: () => {}
            };
            return el;
        },
        body: {
            appendChild: (el) => {
                appendedTextareaValue = el.value;
            },
            removeChild: () => {}
        },
        execCommand: (cmd) => {
            if (cmd === 'copy') {
                execCommandCalled = true;
                return true;
            }
            return false;
        }
    };

    const mockWin = {
        location: {
            origin: 'https://magento.test',
            pathname: '/nuvem-runner.html',
            search: '',
            hash: ''
        },
        history: {
            replaceState: () => {}
        },
        navigator: {}, // Sem clipboard API
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    const studio = createStudio(fullCatalogContract(), mockDoc, mockWin);
    studio.shareCreation();

    assert.equal(execCommandCalled, true, 'execCommand copy deve ser invocado no fallback');
    assert.ok(appendedTextareaValue.startsWith('https://magento.test/nuvem-runner.html#atelie='));
    assert.equal(studio.isCopied(), true, 'isCopied é ativado via fallback');
});
