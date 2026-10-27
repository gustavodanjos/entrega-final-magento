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
        addEventListener: () => {},
        removeEventListener: () => {},
        querySelectorAll: () => [],
        querySelector: () => null,
        getElementById: () => null
    };
    const win = mockWindow || {
        addEventListener: () => {},
        removeEventListener: () => {}
    };
    const context = {
        define: (dependencies, factory) => {
            definition = factory({ extend: options => options }, ko);
        },
        document: doc,
        window: win,
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
                alvo: 'GRAVACAO',
                mensagem: 'A entressola reciclada não aceita gravação a laser devido à textura granulada do material.'
            },
            {
                codigo: 'R05',
                tipo: 'bloqueia',
                origem: 'CAB-COURO-BRANCO',
                alvo_tipo: 'componente',
                alvo: 'BIQ-TEXT-MENTA',
                mensagem: 'O cabedal em couro branco exige acabamento liso ou reforçado na biqueira.'
            },
            {
                codigo: 'R06',
                tipo: 'exige',
                origem: 'ENT-AR-VISIVEL',
                alvo_tipo: 'componente',
                alvo: 'SOL-TRANSLUCIDO-GELO',
                mensagem: 'A câmara de ar só aparece com o solado translúcido.'
            }
        ]
    };
}

// -------------------------------------------------------------------------------------
// TESTE 1: Máquina de Estados (Observables, canUndo e canRedo)
// -------------------------------------------------------------------------------------
test('F3.9: History state machine initializes with canUndo=false and canRedo=false', () => {
    const data = fullCatalogContract();
    const studio = createStudio(data);

    assert.equal(studio.history().length, 1);
    assert.equal(studio.historyIndex(), 0);
    assert.equal(studio.canUndo(), false);
    assert.equal(studio.canRedo(), false);
});

// -------------------------------------------------------------------------------------
// TESTE 2: Comprovação de 5 cliques sucessivos em "Desfazer" restauram os 5 estados anteriores
// -------------------------------------------------------------------------------------
test('F3.9: Clicking 5 times on Desfazer faithfully restores each of the 5 previous states', () => {
    const data = fullCatalogContract();
    const studio = createStudio(data);

    // Estado 0 (Inicial)
    const state0 = studio.createSnapshot();
    assert.equal(state0.selecoes.cabedal, 'CAB-TRICO-LAVANDA');
    assert.equal(state0.selecoes.biqueira, 'BIQ-LISA-CEU');
    assert.equal(state0.selecoes.calcanhar, 'CAL-ACOLCHOADO-CEU');
    assert.equal(state0.tamanho, 38);
    assert.equal(state0.laserText, '');

    // Ação 1: Seleciona Cabedal Menta
    studio.selectOption('cabedal', studio.catalog.componentMap['CAB-TRICO-MENTA']);
    const state1 = studio.createSnapshot();
    assert.equal(state1.selecoes.cabedal, 'CAB-TRICO-MENTA');

    // Ação 2: Seleciona Biqueira Reforçada Areia
    studio.selectOption('biqueira', studio.catalog.componentMap['BIQ-REFORC-AREIA']);
    const state2 = studio.createSnapshot();
    assert.equal(state2.selecoes.biqueira, 'BIQ-REFORC-AREIA');

    // Ação 3: Seleciona Calcanhar Puxador Lavanda
    studio.selectOption('calcanhar', studio.catalog.componentMap['CAL-PUXADOR-LAVANDA']);
    const state3 = studio.createSnapshot();
    assert.equal(state3.selecoes.calcanhar, 'CAL-PUXADOR-LAVANDA');

    // Ação 4: Altera Tamanho para 40
    studio.selectSize(40);
    const state4 = studio.createSnapshot();
    assert.equal(state4.tamanho, 40);

    // Ação 5: Digita gravação a laser e executa blur
    studio.laserText('NUVEM-RUN');
    studio.onLaserBlur();
    const state5 = studio.createSnapshot();
    assert.equal(state5.laserText, 'NUVEM-RUN');

    // Verificamos que o histórico acumulou 6 estados (0 a 5) e ponteiro está em 5
    assert.equal(studio.history().length, 6);
    assert.equal(studio.historyIndex(), 5);
    assert.equal(studio.canUndo(), true);
    assert.equal(studio.canRedo(), false);

    // 1º DESFAZER: Deve restaurar fielmente o Estado 4 (sem gravação)
    studio.undo();
    assert.equal(studio.historyIndex(), 4);
    assert.equal(studio.laserText(), state4.laserText);
    assert.equal(studio.selectedSize(), 40);
    assert.equal(studio.selections.calcanhar(), 'CAL-PUXADOR-LAVANDA');
    assert.equal(studio.canUndo(), true);
    assert.equal(studio.canRedo(), true);

    // 2º DESFAZER: Deve restaurar fielmente o Estado 3 (tamanho 38)
    studio.undo();
    assert.equal(studio.historyIndex(), 3);
    assert.equal(studio.selectedSize(), 38);
    assert.equal(studio.selections.calcanhar(), 'CAL-PUXADOR-LAVANDA');
    assert.equal(studio.selections.biqueira(), 'BIQ-REFORC-AREIA');

    // 3º DESFAZER: Deve restaurar fielmente o Estado 2 (calcanhar original)
    studio.undo();
    assert.equal(studio.historyIndex(), 2);
    assert.equal(studio.selections.calcanhar(), 'CAL-ACOLCHOADO-CEU');
    assert.equal(studio.selections.biqueira(), 'BIQ-REFORC-AREIA');

    // 4º DESFAZER: Deve restaurar fielmente o Estado 1 (biqueira original)
    studio.undo();
    assert.equal(studio.historyIndex(), 1);
    assert.equal(studio.selections.biqueira(), 'BIQ-LISA-CEU');
    assert.equal(studio.selections.cabedal(), 'CAB-TRICO-MENTA');

    // 5º DESFAZER: Deve restaurar fielmente o Estado 0 Inicial (cabedal original)
    studio.undo();
    assert.equal(studio.historyIndex(), 0);
    assert.equal(studio.selections.cabedal(), 'CAB-TRICO-LAVANDA');
    assert.equal(studio.selections.biqueira(), 'BIQ-LISA-CEU');
    assert.equal(studio.selections.calcanhar(), 'CAL-ACOLCHOADO-CEU');
    assert.equal(studio.selectedSize(), 38);
    assert.equal(studio.laserText(), '');

    // Agora o histórico está no início: canUndo deve ser false, canRedo deve ser true
    assert.equal(studio.canUndo(), false);
    assert.equal(studio.canRedo(), true);
});

// -------------------------------------------------------------------------------------
// TESTE 3: Redo e Poda da Árvore Linear de Histórico
// -------------------------------------------------------------------------------------
test('F3.9: Redo traverses forward and new action discards redo tree', () => {
    const data = fullCatalogContract();
    const studio = createStudio(data);

    studio.selectOption('cabedal', studio.catalog.componentMap['CAB-TRICO-MENTA']); // Index 1
    studio.selectOption('cadarco', studio.catalog.componentMap['CAD-CHATO-BRANCO']);  // Index 2

    assert.equal(studio.historyIndex(), 2);

    studio.undo(); // Volta para Index 1
    assert.equal(studio.historyIndex(), 1);
    assert.equal(studio.selections.cadarco(), 'CAD-ELASTICO-MENTA');

    studio.redo(); // Avança para Index 2
    assert.equal(studio.historyIndex(), 2);
    assert.equal(studio.selections.cadarco(), 'CAD-CHATO-BRANCO');

    // Volta para Index 1 e toma um novo caminho
    studio.undo();
    assert.equal(studio.historyIndex(), 1);

    // Nova Ação: Altera Emblema para Bordado Menta
    studio.selectOption('emblema', studio.catalog.componentMap['EMB-BORRO-MENTA']);

    // O estado futuro (cadarco chato branco) deve ter sido descartado
    assert.equal(studio.historyIndex(), 2);
    assert.equal(studio.history().length, 3);
    assert.equal(studio.canRedo(), false);
    assert.equal(studio.selections.emblema(), 'EMB-BORRO-MENTA');
    assert.equal(studio.selections.cadarco(), 'CAD-ELASTICO-MENTA');
});

// -------------------------------------------------------------------------------------
// TESTE 4: Surpreenda-me cria PONTO ÚNICO no histórico (1 clique para desfazer o tênis todo)
// -------------------------------------------------------------------------------------
test('F3.9: Surprise me registers the whole sneaker as a single undo point', () => {
    const data = fullCatalogContract();
    const studio = createStudio(data);

    // Define um tamanho específico de calçado do cliente
    studio.selectSize(39);
    const initialSnap = studio.createSnapshot();
    assert.equal(studio.selectedSize(), 39);
    const initialIndex = studio.historyIndex();

    // Aciona Surpreenda-me
    studio.surpriseMe();

    // O tamanho do calçado do cliente JAMAIS deve ser alterado pelo Surpreenda-me
    assert.equal(studio.selectedSize(), 39, 'Surpreenda-me deve preservar a numeração do pé do cliente');

    // Deve ter incrementado o histórico em exatamente 1 ponto
    assert.equal(studio.historyIndex(), initialIndex + 1);
    assert.equal(studio.canUndo(), true);
    assert.equal(studio.canRedo(), false);

    // Desfaz o Surpreenda-me em 1 único clique
    studio.undo();

    assert.equal(studio.historyIndex(), initialIndex);
    assert.deepEqual(studio.createSnapshot().selecoes, initialSnap.selecoes);
    assert.equal(studio.selectedSize(), 39);
    assert.equal(studio.canRedo(), true); // Refazer deve ficar ativo ao desfazer!

    // Refaz o Surpreenda-me
    studio.redo();
    assert.equal(studio.historyIndex(), initialIndex + 1);
    assert.equal(studio.selectedSize(), 39);
    assert.equal(studio.canRedo(), false); // Refazer volta a ficar pálido no fim da linha
});

// -------------------------------------------------------------------------------------
// TESTE 5: Teste de Estresse de 1.000 chamadas consecutivas do "Surpreenda-me"
// Comprova ZERO violações das regras do catálogo e execução em milissegundos
// -------------------------------------------------------------------------------------
test('F3.9: Stress Test - 1,000 iterations of surpriseMe generate 0 invalid combinations', () => {
    const data = fullCatalogContract();
    const studio = createStudio(data);

    let invalidCount = 0;
    const ruleViolations = {
        R01: 0, // Camurça com solado holográfico
        R02: 0, // Solado holográfico sem calcanhar refletivo
        R03: 0, // Cadarço bicolor com emblema metálico dourado
        R04: 0, // Entressola reciclada com gravação a laser
        R05: 0, // Couro branco com biqueira texturizada
        R06: 0  // Câmara de ar sem solado translúcido
    };

    const startTime = Date.now();
    const iterations = 1000;

    for (let i = 0; i < iterations; i++) {
        studio.surpriseMe();

        // 1. Validação pelo próprio método de integridade do catálogo
        const validationErrors = studio.computeValidationErrors();
        if (validationErrors.length > 0) {
            invalidCount++;
        }

        // 2. Verificação explícita regra a regra para comprovação cabal de ZERO burlas
        const cabedal = studio.selections.cabedal();
        const biqueira = studio.selections.biqueira();
        const calcanhar = studio.selections.calcanhar();
        const cadarco = studio.selections.cadarco();
        const emblema = studio.selections.emblema();
        const entressola = studio.selections.entressola();
        const solado = studio.selections.solado();
        const laser = studio.laserText();

        // R01: Camurça Areia bloqueia Sola Lua Holográfica
        if (cabedal === 'CAB-CAMURCA-AREIA' && solado === 'SOL-LUA-HOLO') {
            ruleViolations.R01++;
            invalidCount++;
        }

        // R02: Sola Lua Holográfica exige acabamento refletivo no calcanhar (CAL-REFLET-PRATA)
        if (solado === 'SOL-LUA-HOLO') {
            const calcanharComp = studio.catalog.componentMap[calcanhar];
            if (!calcanharComp || calcanharComp.acabamento !== 'refletivo') {
                ruleViolations.R02++;
                invalidCount++;
            }
        }

        // R03: Cadarço bicolor bloqueia emblema metálico dourado
        if (cadarco === 'CAD-BICOLOR-LAV-CEU' && emblema === 'EMB-METAL-DOURADO') {
            ruleViolations.R03++;
            invalidCount++;
        }

        // R04: Entressola reciclada bloqueia gravação a laser
        if (entressola === 'ENT-RECICLADA-CREME' && laser.trim() !== '') {
            ruleViolations.R04++;
            invalidCount++;
        }

        // R05: Cabedal couro branco bloqueia biqueira texturizada
        if (cabedal === 'CAB-COURO-BRANCO' && biqueira === 'BIQ-TEXT-MENTA') {
            ruleViolations.R05++;
            invalidCount++;
        }

        // R06: Câmara de ar exige solado translúcido
        if (entressola === 'ENT-AR-VISIVEL' && solado !== 'SOL-TRANSLUCIDO-GELO') {
            ruleViolations.R06++;
            invalidCount++;
        }
    }

    const durationMs = Date.now() - startTime;
    const avgPerCall = (durationMs / iterations).toFixed(3);

    console.log(`\n======================================================`);
    console.log(`[TESTE DE ESTRESSE SURPREENDA-ME - 1.000 ITERAÇÕES]`);
    console.log(`Tempo total: ${durationMs}ms (média: ${avgPerCall}ms por chamada)`);
    console.log(`Combinações inválidas geradas: ${invalidCount}`);
    console.log(`Violações de Regra R01 (Camurça + Sola Lua): ${ruleViolations.R01}`);
    console.log(`Violações de Regra R02 (Sola Lua sem Refletivo): ${ruleViolations.R02}`);
    console.log(`Violações de Regra R03 (Bicolor + Metálico): ${ruleViolations.R03}`);
    console.log(`Violações de Regra R04 (Reciclada + Gravação): ${ruleViolations.R04}`);
    console.log(`Violações de Regra R05 (Couro Branco + Texturizada): ${ruleViolations.R05}`);
    console.log(`Violações de Regra R06 (Câmara de Ar sem Translúcido): ${ruleViolations.R06}`);
    console.log(`======================================================\n`);

    assert.equal(invalidCount, 0, `Esperado 0 combinações inválidas, mas foram geradas ${invalidCount}`);
    assert.equal(ruleViolations.R01, 0);
    assert.equal(ruleViolations.R02, 0);
    assert.equal(ruleViolations.R03, 0);
    assert.equal(ruleViolations.R04, 0);
    assert.equal(ruleViolations.R05, 0);
    assert.equal(ruleViolations.R06, 0);
    assert.ok(durationMs < 5000, `Execução demorou muito (${durationMs}ms), esperado < 5000ms`);
});

// -------------------------------------------------------------------------------------
// TESTE 6: Respeito a itens esgotados no "Surpreenda-me"
// -------------------------------------------------------------------------------------
test('F3.9: Surprise me never selects sold-out components', () => {
    const data = fullCatalogContract();
    const studio = createStudio(data);

    // Marca todos os cabedais como esgotados EXCETO o Tricô Lavanda
    studio.applyAvailability([
        { componente: 'CAB-TRICO-MENTA', disponivel: false },
        { componente: 'CAB-CAMURCA-AREIA', disponivel: false },
        { componente: 'CAB-COURO-BRANCO', disponivel: false }
    ]);

    // Executa 20 sorteios
    for (let i = 0; i < 20; i++) {
        studio.surpriseMe();
        assert.equal(studio.selections.cabedal(), 'CAB-TRICO-LAVANDA');
        assert.equal(studio.isEsgotado(studio.selections.cabedal()), false);
    }
});
