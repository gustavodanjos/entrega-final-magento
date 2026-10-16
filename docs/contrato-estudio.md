# Contrato do Front · Estúdio Ateliê Nuvem

Este documento formaliza o contrato de dados que o componente Knockout.js (`uiComponent`) da Frente 3 (`Nuvem_AtelieStudio`) consome do back-end (`ViewModel`). Qualquer alteração estrutural neste contrato exige alinhamento com as Frentes 1 (Núcleo) e 5 (Vitrine/GraphQL).

---

## 1. Origem dos Dados (ViewModel do Estúdio)

Na PDP do produto configurável do tipo `atelie`, o bloco do estúdio disponibiliza um JSON via `jsLayout` ou método do `ViewModel` (`Nuvem\AtelieStudio\ViewModel\Estudio`).

Esse payload unifica:
1. **Dados de Catálogo (`CatalogoAtelieInterface`):** Grupos de personalização ordenados, componentes disponíveis com acabamento/cores/preços adicionais e regras de restrição/dependência ativas.
2. **Atributos do Modelo (EAV do Produto):** SKU, nome, preço base, grade de tamanhos permitida (`tamanho_min` e `tamanho_max`), prazo de confecção e disponibilidade/regras de gravação personalizada.
3. **Configuração Inicial Padrão:** Estado de montagem inicial (componentes com `padrao = true` em cada grupo e tamanho padrão inicial).
4. **URLs e Endpoints:** Rotas para verificação de disponibilidade em tempo real, validação pré-carrinho, endpoint de preview/render e action nativa de adicionar ao carrinho.

---

## 2. Payload JSON Canônico (Exemplo: Nuvem Runner)

```json
{
  "produto": {
    "sku": "NUVEM-RUNNER",
    "nome": "Nuvem Runner",
    "preco_base": 449.90,
    "prazo_producao_dias": 12,
    "grade_tamanhos": [34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44],
    "gravacao": {
      "permitida": true,
      "preco": 29.90,
      "limite_caracteres": 12,
      "placeholder": "Ex: NUVEM"
    }
  },
  "configuracao_inicial": {
    "tamanho": 38,
    "gravacao": null,
    "selecoes": [
      { "grupo": "cabedal", "componente": "CAB-TRICO-LAVANDA" },
      { "grupo": "biqueira", "componente": "BIQ-LISA-CEU" },
      { "grupo": "calcanhar", "componente": "CAL-ACOLCHOADO-CEU" },
      { "grupo": "cadarco", "componente": "CAD-ELASTICO-MENTA" },
      { "grupo": "emblema", "componente": "EMB-NUVEM-PESSEGO" },
      { "grupo": "entressola", "componente": "ENT-EVA-NUVEM" },
      { "grupo": "solado", "componente": "SOL-TRANSLUCIDO-GELO" }
    ]
  },
  "grupos": [
    {
      "codigo": "cabedal",
      "nome": "Cabedal",
      "ordem": 1,
      "camada_svg": "camada-cabedal",
      "componentes": [
        {
          "codigo": "CAB-TRICO-LAVANDA",
          "nome": "Tricô Lavanda",
          "cor_hex": "#B8A9C9",
          "acabamento": "trico",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "CAB-TRICO-MENTA",
          "nome": "Tricô Menta",
          "cor_hex": "#A8E6CF",
          "acabamento": "trico",
          "preco_adicional": 0.00,
          "padrao": false
        },
        {
          "codigo": "CAB-CAMURCA-AREIA",
          "nome": "Camurça Areia",
          "cor_hex": "#E8D8C8",
          "acabamento": "camurca",
          "preco_adicional": 25.00,
          "padrao": false
        },
        {
          "codigo": "CAB-COURO-BRANCO",
          "nome": "Couro Branco",
          "cor_hex": "#F5F5F5",
          "acabamento": "couro",
          "preco_adicional": 40.00,
          "padrao": false
        }
      ]
    },
    {
      "codigo": "biqueira",
      "nome": "Biqueira",
      "ordem": 2,
      "camada_svg": "camada-biqueira",
      "componentes": [
        {
          "codigo": "BIQ-LISA-CEU",
          "nome": "Lisa Céu",
          "cor_hex": "#A0C4E2",
          "acabamento": "liso",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "BIQ-TEXT-MENTA",
          "nome": "Texturizada Menta",
          "cor_hex": "#A8E6CF",
          "acabamento": "texturizado",
          "preco_adicional": 15.00,
          "padrao": false
        },
        {
          "codigo": "BIQ-REFORC-AREIA",
          "nome": "Reforçada Areia",
          "cor_hex": "#E8D8C8",
          "acabamento": "reforcado",
          "preco_adicional": 20.00,
          "padrao": false
        },
        {
          "codigo": "BIQ-CAMURCA-ROSA",
          "nome": "Camurça Rosa",
          "cor_hex": "#F4C2C2",
          "acabamento": "camurca",
          "preco_adicional": 25.00,
          "padrao": false
        }
      ]
    },
    {
      "codigo": "calcanhar",
      "nome": "Calcanhar",
      "ordem": 3,
      "camada_svg": "camada-calcanhar",
      "componentes": [
        {
          "codigo": "CAL-ACOLCHOADO-CEU",
          "nome": "Acolchoado Céu",
          "cor_hex": "#A0C4E2",
          "acabamento": "acolchoado",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "CAL-PUXADOR-LAVANDA",
          "nome": "Com Puxador Lavanda",
          "cor_hex": "#B8A9C9",
          "acabamento": "puxador",
          "preco_adicional": 15.00,
          "padrao": false
        },
        {
          "codigo": "CAL-ESTAB-GRAFITE",
          "nome": "Estabilizador Grafite",
          "cor_hex": "#4A4A4A",
          "acabamento": "estabilizador",
          "preco_adicional": 30.00,
          "padrao": false
        },
        {
          "codigo": "CAL-REFLET-PRATA",
          "nome": "Refletivo Prata",
          "cor_hex": "#D3D3D3",
          "acabamento": "refletivo",
          "preco_adicional": 35.00,
          "padrao": false
        }
      ]
    },
    {
      "codigo": "cadarco",
      "nome": "Cadarço",
      "ordem": 4,
      "camada_svg": "camada-cadarco",
      "componentes": [
        {
          "codigo": "CAD-ELASTICO-MENTA",
          "nome": "Elástico Menta",
          "cor_hex": "#A8E6CF",
          "acabamento": "elastico",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "CAD-CHATO-BRANCO",
          "nome": "Chato Branco",
          "cor_hex": "#FFFFFF",
          "acabamento": "algodao",
          "preco_adicional": 0.00,
          "padrao": false
        },
        {
          "codigo": "CAD-REDONDO-PESSEGO",
          "nome": "Redondo Pêssego",
          "cor_hex": "#FFD3B6",
          "acabamento": "poliester",
          "preco_adicional": 10.00,
          "padrao": false
        },
        {
          "codigo": "CAD-BICOLOR-LAV-CEU",
          "nome": "Bicolor Lavanda/Céu",
          "cor_hex": "#B8A9C9",
          "acabamento": "bicolor",
          "preco_adicional": 15.00,
          "padrao": false
        }
      ]
    },
    {
      "codigo": "emblema",
      "nome": "Emblema",
      "ordem": 5,
      "camada_svg": "camada-emblema",
      "componentes": [
        {
          "codigo": "EMB-NUVEM-PESSEGO",
          "nome": "Nuvem Pêssego",
          "cor_hex": "#FFD3B6",
          "acabamento": "emborrachado",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "EMB-BORRO-MENTA",
          "nome": "Bordado Menta",
          "cor_hex": "#A8E6CF",
          "acabamento": "bordado",
          "preco_adicional": 12.00,
          "padrao": false
        },
        {
          "codigo": "EMB-MINIMAL-BRANCO",
          "nome": "Minimal Branco",
          "cor_hex": "#FFFFFF",
          "acabamento": "estampado",
          "preco_adicional": 0.00,
          "padrao": false
        },
        {
          "codigo": "EMB-METAL-DOURADO",
          "nome": "Metálico Dourado",
          "cor_hex": "#D4AF37",
          "acabamento": "metalico",
          "preco_adicional": 25.00,
          "padrao": false
        }
      ]
    },
    {
      "codigo": "entressola",
      "nome": "Entressola",
      "ordem": 6,
      "camada_svg": "camada-entressola",
      "componentes": [
        {
          "codigo": "ENT-EVA-NUVEM",
          "nome": "EVA Nuvem",
          "cor_hex": "#FFFFFF",
          "acabamento": "eva",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "ENT-AMORTEC-ROSA",
          "nome": "Amortecedor Rosa",
          "cor_hex": "#F4C2C2",
          "acabamento": "gel",
          "preco_adicional": 35.00,
          "padrao": false
        },
        {
          "codigo": "ENT-RECICLADA-CREME",
          "nome": "Reciclada Creme",
          "cor_hex": "#F0EAD6",
          "acabamento": "sustentavel",
          "preco_adicional": 20.00,
          "padrao": false
        }
      ]
    },
    {
      "codigo": "solado",
      "nome": "Solado",
      "ordem": 7,
      "camada_svg": "camada-solado",
      "componentes": [
        {
          "codigo": "SOL-TRANSLUCIDO-GELO",
          "nome": "Translúcido Gelo",
          "cor_hex": "#E0EEEE",
          "acabamento": "borracha-translucida",
          "preco_adicional": 0.00,
          "padrao": true
        },
        {
          "codigo": "SOL-TRATOR-MENTA",
          "nome": "Tratorado Menta",
          "cor_hex": "#A8E6CF",
          "acabamento": "borracha-tratorada",
          "preco_adicional": 25.00,
          "padrao": false
        },
        {
          "codigo": "SOL-CREPE-NATURAL",
          "nome": "Crepe Natural",
          "cor_hex": "#E3C565",
          "acabamento": "borracha-crepe",
          "preco_adicional": 30.00,
          "padrao": false
        },
        {
          "codigo": "SOL-LUA-HOLO",
          "nome": "Sola Lua Holográfica",
          "cor_hex": "#E6E6FA",
          "acabamento": "holografico",
          "preco_adicional": 60.00,
          "padrao": false
        }
      ]
    }
  ],
  "regras": [
    {
      "codigo": "R01",
      "tipo": "BLOQUEIA",
      "origem": "CAB-CAMURCA-AREIA",
      "alvo_tipo": "COMPONENTE",
      "alvo": "SOL-LUA-HOLO",
      "mensagem": "A camurça natural não pode ser combinada com o solado holográfico por questões de vulcanização na fábrica."
    },
    {
      "codigo": "R02",
      "tipo": "EXIGE",
      "origem": "SOL-LUA-HOLO",
      "alvo_tipo": "ACABAMENTO",
      "alvo": "refletivo",
      "mensagem": "A edição Sola Lua exige acabamento refletivo no calcanhar por padrão de segurança visual da linha."
    },
    {
      "codigo": "R03",
      "tipo": "BLOQUEIA",
      "origem": "CAD-BICOLOR-LAV-CEU",
      "alvo_tipo": "COMPONENTE",
      "alvo": "EMB-METAL-DOURADO",
      "mensagem": "O cadarço bicolor não é compatível com o emblema metálico dourado na prensagem do modelo."
    },
    {
      "codigo": "R04",
      "tipo": "BLOQUEIA",
      "origem": "ENT-RECICLADA-CREME",
      "alvo_tipo": "GRAVACAO",
      "alvo": "GRAVACAO",
      "mensagem": "A entressola reciclada não aceita gravação a laser devido à textura granulada do material."
    },
    {
      "codigo": "R05",
      "tipo": "BLOQUEIA",
      "origem": "CAB-COURO-BRANCO",
      "alvo_tipo": "COMPONENTE",
      "alvo": "BIQ-TEXT-MENTA",
      "mensagem": "O cabedal em couro branco exige acabamento liso ou reforçado na biqueira."
    }
  ],
  "urls": {
    "adicionar_carrinho": "/checkout/cart/add",
    "disponibilidade": "/rest/default/V1/atelie/disponibilidade/NUVEM-RUNNER",
    "validar": "/rest/default/V1/atelie/validar",
    "preview": "/atelie/preview/svg"
  }
}
```

---

## 3. Diretrizes de Consumo no Front-end (Knockout.js / uiComponent)

1. **Estrutura Canônica das Seleções:**
   * O array `selecoes` deve ser sempre mantido como lista tipada de pares `grupo` e `componente`:
     `[ { "grupo": "cabedal", "componente": "CAB-..." }, ... ]`.
   * **Nunca** serializar ou enviar como mapa associativo `{ "cabedal": "CAB-..." }`, pois as interfaces do Magento (`SelecaoInterface[]`) recusam mapas arbitrários.
2. **Pintura Dinâmica do SVG:**
   * Cada grupo possui um elemento `<g id="camada-<grupo>">` correspondente a `camada_svg`.
   * O elemento interno possui o atributo `data-pintar` (`fill` nas peças e `stroke` no cadarço).
   * Lógica padrão de estilização:
     ```javascript
     const layerElement = svgElement.querySelector('#' + grupo.camada_svg + ' [data-pintar]');
     if (layerElement) {
         const attr = layerElement.getAttribute('data-pintar');
         layerElement.setAttribute(attr, componente.cor_hex);
     }
     ```
3. **Gravação de Texto a Laser:**
   * Elemento SVG: `<text id="gravacao-texto">` dentro da camada `<g id="camada-gravacao">`.
   * Atualizado reativamente pelo observable da gravação.
