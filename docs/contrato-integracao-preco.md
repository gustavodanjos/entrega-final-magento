# Contrato de integração de preço · F2.1 e F2.2

## Estado da integração

Verificado em 22/10/2026: F2.1 e F1.5 estão integradas à `dev`, pelos PRs #20 (`ee433f2`) e #24 (`0aa5367`). A consolidação da F2.2 incorpora essa versão da `dev`, preservando a calculadora e o price model nativos. **As dependências da F2.1 e da F1.5 estão atendidas na `dev`.**

| Frente | Entrega | Estado |
| --- | --- | --- |
| F1.1 | Catálogo real e repositórios | Integrada à `dev` |
| F1.2 | Atributos e produtos | Integrada à `dev` |
| F1.3 | Tipo `atelie`, validação/persistência pelo buy request e opções do pedido | Integrada à `dev` |
| F1.4 | Validador de configuração | Integrada à `dev` |
| F1.5 | Processador REST e extension attribute do carrinho | Integrada à `dev` pelo PR #24; conflitos resolvidos na F2.2 |
| F2.1 | Calculadora, price model e proteção contra preço adulterado | Integrada à `dev` pelo PR #20 |
| F3.2 | Configurador Knockout | Incorporado à F2.2; PR #22 ainda aberto |
| F3.6 | Preview personalizado no servidor | Pendente; consumidor usa `PreviewInterface` |

Estes estados são um retrato da integração, não substituem a consulta aos PRs antes de um novo merge. GraphQL, estoque real e geração de pedidos não fazem parte do aceite desta integração F2.2. Multiloja fica fora do escopo; não constitui bloqueio nem nova dependência da F2.1.

## Preço calculado no servidor

1. O tipo `atelie` valida o buy request e guarda a opção `atelie_configuracao`, produzida pelo `ConfigurationSerializer`.
2. `Nuvem/Atelie/etc/product_types.xml` declara nativamente o price model `Nuvem\AtelieSales\Model\Product\Price`. A F2.2 não redeclara o tipo nem define `custom_price`.
3. `Price::getFinalPrice()` lê a opção do produto reidratada pelo quote e chama `PriceCalculatorInterface`, cuja preference é a calculadora real da F2.1.
4. A calculadora soma a base do catálogo, os adicionais selecionados e a gravação. O price model aplica o delta ao preço final do core:

```text
preço final = preço final da base pelo core + (calculadora(configuração) − base do catálogo)
```

Especiais, tiers e regras de catálogo continuam seguindo o core sobre a base. Adicionais e gravação são somados depois. A base do catálogo vem do atributo `price` do produto; o kit válido tem base de 449,90, adicionais de 109,60 e gravação de 29,90.

| Configuração do kit | Preço unitário esperado |
| --- | --- |
| Com gravação | R$ 589,40 |
| Sem gravação | R$ 559,50 |
| Com gravação e especial de 10% sobre a base | R$ 544,41 |

`cartItem.price` não determina a cobrança. O plugin `StripTamperedPricePlugin` remove `price`, `custom_price` e `customPrice` antes do processamento REST, incluindo os formatos de item/lote e os campos na raiz de serviços de carrinho/quote entregues pela F1.5. A F2.2 mantém o resultado do servidor nas três superfícies, sem calcular preço em renderers ou templates.

Sem configuração, o price model preserva o preço base do core. JSON corrompido falha com `InvalidArgumentException`; componentes desconhecidos e gravação não permitida falham com `LocalizedException`. Os caches da calculadora e do price model duram apenas o request.

## Configuração compartilhada entre preço e ficha

A opção canônica `atelie_configuracao` é uma string JSON gerada por `ConfigurationSerializer::serialize()`. Ele normaliza gravação vazia para `null`, ordena seleções por grupo e rejeita grupos duplicados.

```json
{
  "tamanho": 38,
  "gravacao": "LUIZ",
  "selecoes": [
    {"grupo": "cabedal", "componente": "CAB-TRICO-LAVANDA"},
    {"grupo": "biqueira", "componente": "BIQ-LISA-CEU"},
    {"grupo": "calcanhar", "componente": "CAL-ACOLCHOADO-CEU"},
    {"grupo": "cadarco", "componente": "CAD-ELASTICO-MENTA"},
    {"grupo": "emblema", "componente": "EMB-NUVEM-PESSEGO"},
    {"grupo": "entressola", "componente": "ENT-EVA-NUVEM"},
    {"grupo": "solado", "componente": "SOL-TRANSLUCIDO-GELO"}
  ]
}
```

O formulário envia `atelie[tamanho]`, `atelie[gravacao]` e `atelie[selecoes]`. REST recebe o objeto em `product_option.extension_attributes.atelie_configuracao`; o processador F1.5 converte os campos para o buy request da F1.3. A leitura REST devolve a configuração pelo mesmo extension attribute. O `CartItemProcessor::processOptions()` da F1.5 também sincroniza a configuração canônica como custom option do item e do produto, mantendo a fonte utilizada pela ficha F2.2 e pelo preço F2.1. A resolução conserva a prioridade existente: extension attributes, opção do item/produto e buy request.

Configurações equivalentes do mesmo produto somam quantidade; configurações diferentes mantêm linhas distintas. A ficha lê a opção do item por `OptionListBuilder`; o preço lê a opção do produto reidratada pelo Magento. Ambos devem conservar o mesmo conteúdo após salvar/recarregar e recalcular totais.

O helper da ficha integra `ConfigurationPool` nas áreas `frontend` e `webapi_rest`. Carrinho e minicart escapam texto na saída; o helper escapa os valores consumidos pelo binding HTML do checkout. O teste de HTML armazenado verifica a saída, sem liberar HTML na validação de entrada.

## Como verificar

Na loja padrão em BRL, com produtos e componentes do kit instalados:

```bash
bin/clinotty php bin/magento setup:di:compile
bin/clinotty vendor/bin/phpunit -c phpunit.xml.dist
bin/clinotty vendor/bin/phpcs app/code/Nuvem
node --test kit/tests/studio.test.cjs
bin/clinotty php app/code/Nuvem/AtelieSales/Test/Smoke/checkout-configuration.php frontend
bin/clinotty php app/code/Nuvem/AtelieSales/Test/Smoke/checkout-configuration.php webapi_rest
```

Executar cada perfil abaixo em processo próprio, sequencialmente, nas duas áreas:

```bash
bin/clinotty php app/code/Nuvem/AtelieSales/Test/Smoke/cart-price-configuration.php frontend --run guest
bin/clinotty php app/code/Nuvem/AtelieSales/Test/Smoke/cart-price-configuration.php frontend --run customer
bin/clinotty php app/code/Nuvem/AtelieSales/Test/Smoke/cart-price-configuration.php webapi_rest --run guest
bin/clinotty php app/code/Nuvem/AtelieSales/Test/Smoke/cart-price-configuration.php webapi_rest --run customer
```

O novo smoke é exclusivo do ambiente local descartável. Cria carrinhos e cliente de teste, processa payload com preço adulterado, recarrega por repositories novos, confere preço/subtotal/ficha e modifica temporariamente o especial do produto para testar o desconto sobre a base. Tudo fica em uma transação revertida em `finally`; nenhum pedido é criado. Processos separados isolam os caches Magento entre cenários promocionais. No perfil de cliente, o contexto de sessão é preparado como AJAX, assim como o checkout, sem desativar a autorização de carrinho.

Na resolução do merge F1.5/F2.2, os testes mantêm o stub próprio da F2.2 e a fixture da F1.5 apenas como fallback quando a interface gerada não pode ser carregada. As regressões cobrem serialização por aliases REST/inglês e persistência da mesma configuração no item e produto.

Os smokes verificam serviços reais, persistência e DI; não substituem HTTP nem navegador. Conferir também visitante e cliente autenticada em desktop/mobile: carrinho, minicart e checkout, preço unitário/subtotal, gravação ausente, quantidade e totais REST. A imagem personalizada continua com aceite pendente até a F3.6 entregar o preview real.

A `kit/scripts/prova-final.sh` permite `SO=api`, `SO=burlas` e `SO=preco` para verificações HTTP parciais. Aprovação dessas etapas não significa aprovação de GraphQL, corrida de estoque ou da prova completa.
