# Contrato de Integração · F2.1 (Preço) ↔ F1.2 / F1.3 (Núcleo)

Este documento formaliza o que a Frente 2 (Preço) já entrega, o que a Frente 1 (Núcleo) precisa entregar para o preço valer em produção, e como verificar o casamento entre as duas frentes. Qualquer mudança estrutural nestes pontos exige alinhamento entre as frentes (ver `docs/CODEOWNERS`).

**Status no momento da escrita:**

| Frente | Item | Estado |
|---|---|---|
| F1.1 | Catálogo real (`Nuvem\Atelie\Model\AtelieCatalog`) | ✅ PR #13 em `dev` |
| F1.4 | Validador de configuração | ✅ código pronto (PR #14) |
| F1.2 | Tipo de produto `atelie` | ❌ pendente (Núcleo) |
| F1.3 | API de carrinho / persistência da configuração | ❌ pendente (Núcleo) |
| F2.1 | Price model + calculadora de preço | ✅ pronta neste branch |

Sem F1.2 e F1.3, nenhum item chega ao carrinho com configuração: o preço cai no *fallback* base (sem gravação e sem adicionais). A E2E da `kit/scripts/prova-final.sh` só é possível quando as duas aterrissarem.

---

## 1. Fluxo de preço (já implementado pela F2.1)

1. O item de carrinho carrega o produto com a opção `atelie_configuracao` (JSON — ver §3).
2. O tipo `atelie` resolve o price model `Nuvem\AtelieSales\Model\Product\Price` declarado nativamente na linha `priceModel` de `Nuvem/Atelie/etc/product_types.xml` — o mesmo mecanismo core que resolve, por exemplo, o price model do `configurable`. Por ser a declaração do tipo, todos os caminhos de compra (PDP, quote/totals, checkout, pedido) passam pelo mesmo cálculo.
3. `Price::getFinalPrice()` lê a configuração via `readConfiguration()` (`Nuvem\AtelieSales\Model\Product\Price`). Sem configuração → preço base do core, fim.
4. Com configuração → `Nuvem\AtelieSales\Model\PriceCalculator::calculate()` monta o preço cheio a partir do catálogo (`AtelieCatalogInterface::getBySku`): `preco_base + Σ preco_adicional(das seleções) + preco_da_gravacao`.
5. O preço final é **delta**: `parent::getFinalPrice($qty, $product) + (calculado − preco_base_do_catálogo)`. Assim especiais, *tiers* e regras de catálogo do core seguem incidindo apenas sobre a base — nunca sobre adicionais/gravação.
6. O preço enviado no payload (`cartItem.price` / `custom_price`) é ignorado: o servidor sempre recalcula.

**Garantias testadas (unit):** configuração kit completa = **R$ 589,40**; mesma configuração com especial de 10% na base = **R$ 544,41**; gravação em produto sem suporte e componentes desconhecidos → `LocalizedException` (falha barulhenta); JSON corrompido → `InvalidArgumentException`.

**Desempenho:** `Price` e `PriceCalculator` memoizam `getBySku` por SKU dentro do request (as instâncias são singletons por request do DI). Não é cache persistente: uma nova request recarrega do banco.

---

## 2. Contrato do tipo de produto (entrega F1.2)

Arquivo: `Nuvem/Atelie/etc/product_types.xml` (módulo **F1** — `Nuvem_Atelie`).

```xml
<type name="atelie"
      label="Ateliê Product"
      modelInstance="Nuvem\Atelie\Model\Product\Type\Atelie"
      composite="false"
      isQty="false"
      canUseQtyDecimals="false"
      sortOrder="70">
    <priceModel instance="Nuvem\AtelieSales\Model\Product\Price" />
    <indexerModel instance="Nuvem\Atelie\Model\ResourceModel\Product\Indexer\Price\AtelieProductPrice" />
</type>
```

Regras:

- **A linha `priceModel` deste arquivo aponta para a F2.1:** `instance="Nuvem\AtelieSales\Model\Product\Price"`. A classe pertence ao módulo `Nuvem_AtelieSales` (F2.1); a declaração fica aqui porque este é o arquivo dono do type `atelie` — o mesmo padrão do `indexerModel` que a F1.3 ajustou neste arquivo. O módulo `AtelieSales` **não** redeclara o `<type>` (a XSD exige `label`/`modelInstance` completos; duplicar a definição entre módulos gera merge ambíguo) e depende de `Nuvem_Atelie` via `sequence` em `module.xml`, garantindo a classe quando o type a instanciar.
- Atributos EAV usados pelo catálogo (com *fallback* nos dados do kit quando ausentes): `atelie_tamanho_min`, `atelie_tamanho_max`, `atelie_prazo_producao`, `atelie_permite_gravacao`.
- Produtos `NUVEM-RUNNER` e `NUVEM-MINI` precisam existir com `type_id = atelie` e o atributo `price` preenchido — **`preco_base` do catálogo é o atributo `price` do produto** (`AtelieCatalog::loadProductData`), a mesma fonte que o core usa na base do delta. Se os produtos não existirem, o catálogo cai nos valores do kit (`DEFAULT_PRODUCTS`) — o E2E do carrinho, porém, exige o produto real.

---

## 3. Contrato da opção `atelie_configuracao` (entrega F1.3)

**Código da opção:** `atelie_configuracao` — no *quote item* e no *product custom option*, exatamente este nome (é o que `Price::readConfiguration()` consulta e o que a leitura REST da prova espera em `product_option.extension_attributes.atelie_configuracao`).

**Valor:** string JSON no formato do `ConfigurationSerializer` — **sempre gerada com `Nuvem\Atelie\Model\ConfigurationSerializer::serialize()`**, nunca montada à mão (ele normaliza: gravação vazia → `null`, seleções ordenadas por grupo, grupo duplicado rejeitado — duas configurações iguais produzem exatamente o mesmo string, o que sustenta a fusão de linhas iguais).

```json
{
  "tamanho": 38,
  "gravacao": "LUIZ",
  "selecoes": [
    { "grupo": "cabedal", "componente": "CAB-TRICO-LAVANDA" },
    { "grupo": "biqueira", "componente": "BIQ-LISA-CEU" },
    { "grupo": "calcanhar", "componente": "CAL-ACOLCHOADO-CEU" },
    { "grupo": "cadarco", "componente": "CAD-ELASTICO-MENTA" },
    { "grupo": "emblema", "componente": "EMB-NUVEM-PESSEGO" },
    { "grupo": "entressola", "componente": "ENT-EVA-NUVEM" },
    { "grupo": "solado", "componente": "SOL-TRANSLUCIDO-GELO" }
  ]
}
```

**Caminho que a F1.3 precisa implementar:**

1. Receber `product_option.extension_attributes.atelie_configuracao` (REST) ou `configuracao` (GraphQL `AtelieConfiguracaoInput`) no *add to cart*.
2. Validar/normalizar e **serializar com `ConfigurationSerializer::serialize()`**.
3. Gravar no *quote item* como opção de produto de código `atelie_configuracao` (via *buyRequest* + *cart item processor* do tipo `atelie`).
4. Na leitura do item, devolver o mesmo objeto em `product_option.extension_attributes.atelie_configuracao` (a prova verifica `selecoes` ≥ 7).
5. **Fusão de linhas:** duas linhas do mesmo produto só podem somar quantidade quando os *buyRequests* (e portanto os strings serializados) são idênticos; configuração diferente abre linha nova.

**Por que funciona sem mais nada no lado da F2.1:** no *totals*, o core rehidrata o produto do item com as opções salvas (`Quote\Item\Collection::_assignOptions` → `AbstractItem::getProduct()`), então `getCustomOption('atelie_configuracao')` existe quando `getFinalPrice()` roda. A opção **não** gera preço customizado pelo core (`_applyOptionsPrice` só considera `option_ids`): o valor é sempre o delta calculado pela F2.1. JSON inválido/corrompido falha barulhento (`InvalidArgumentException`) — intencional, para nunca cobrar preço errado em silêncio.

---

## 4. Checklist de aceite quando F1.2 + F1.3 aterrissarem

Rodar `kit/scripts/prova-final.sh` (obedece `BASE_URL`, `PRECO_VALIDA=589.40` por padrão):

1. **Etapa 1 — caminho feliz REST:** item entra com HTTP 200; `items[0].price == 589.40`; leitura do item devolve a configuração com ≥ 7 seleções.
2. **Etapa 2 — burlas:** todas as 11 tentativas recusadas com HTTP 400 e carrinho vazio (validação F1.4 através da API F1.3).
3. **Etapa 3 — preço adulterado e linhas:** `price`/`custom_price` = 1 no payload é ignorado (`items[0].price == 589.40`); mesma configuração soma quantidade, configuração diferente abre linha nova (2 linhas / 3 pares).
4. **Etapa 4 — GraphQL:** `atelieConfigurador` devolve ≥ 7 grupos e ≥ 5 regras; `addAtelieProductToCart` rejeita a configuração proibida e aceita a válida com o mesmo preço do REST.
5. **Etapas 5–6 — corrida e carga:** `CORRIDA=… CARGA=… ./kit/scripts/prova-final.sh`.

Antes disso, o verificador unitário do casamento F2.1↔F1.1 é `Nuvem\AtelieSales\Test\Unit\Model\PriceCalculatorWithRealCatalogTest` (calculadora real + `AtelieCatalog` real, com o shape tipado dos models do catálogo).

---

## 5. Fora do escopo da F2.1

- Validar regras de restrição (F1.4) e pré-carrinho (F1.3/F5) — a calculadora só calcula; quem recusa é o validador.
- Disponibilidade/estoque (F2.3) e pedido (F2.2).
- Cache persistente do catálogo — se a F1.1/Outras adicionar cache por request no `AtelieCatalog`, a F2.1 herda automaticamente (a preferência da interface continua valendo).
