# Kit do Ateliê Nuvem · Sprint 9

Tudo que vocês precisam para começar sem inventar dado de mentira.
Este kit vai inteiro para a pasta `kit/` do repositório do time.

```
kit/
├── camadas/              SVG do tênis, completo e camada por camada
│   ├── tenis-completo.svg
│   └── cabedal.svg, biqueira.svg, calcanhar.svg, cadarco.svg,
│       emblema.svg, entressola.svg, solado.svg
├── dados/                fonte de verdade do catálogo do ateliê
│   ├── grupos.csv        7 grupos, na ordem das abas do estúdio
│   ├── componentes.csv   31 componentes com cor, acabamento, preço e estoque
│   ├── regras.csv        5 regras de incompatibilidade
│   └── produtos.csv      2 modelos: Nuvem Runner e Nuvem Mini
├── exemplos/
│   ├── configuracao-valida.json     preço esperado: R$ 589,40
│   ├── configuracao-proibida.json   precisa ser recusada (R01)
│   ├── configuracao-lua.json        a da corrida: R$ 539,80
│   └── criacao-*.svg                quatro criações prontas para a galeria
├── marca/                logo (SVG e PNG) e os mockups do manual de marca
├── contratos/
│   ├── schema.graphqls   contrato GraphQL (nomes de tipos e resolvers)
│   ├── api-rest.md       rotas REST, formato da configuração, erros
│   └── banco-de-dados.md tabelas, colunas e dono de cada uma
└── scripts/
    └── prova-final.sh    a prova que roda na demo. Rodem todo dia.
```

## Sobre o SVG

- Cada grupo do catálogo tem um `<g id="camada-<grupo>">`. Dentro dele, o elemento que recebe a cor
  tem o atributo `data-pintar`, cujo valor diz QUAL atributo pintar: `fill` nas peças, `stroke` no cadarço.
  Pintar é sempre: achar `#camada-<grupo> [data-pintar]` e fazer `setAttribute(valor-de-data-pintar, cor)`.
  Assim nem o JS nem o PHP precisam saber que o cadarço é diferente.
- `camada-gravacao` tem um `<text id="gravacao-texto">`. O texto entra ali.
- As camadas `sombra`, `detalhes` e `ilhoses` são fixas.
- A coluna `camada_svg` de `grupos.csv` aponta para o id da camada. Se o cliente pedir
  um grupo novo, entra uma linha no CSV e uma camada no SVG. **Nenhuma linha de PHP ou JS
  deveria mudar.** Guardem essa frase.

## Sobre os dados

- Valores em reais, sem impostos. Estoque em pares.
- `SOL-LUA-HOLO` é a edição limitada. Começa com 10 unidades. É ela que a corrida da prova final disputa.
- Um componente `padrao=1` por grupo. A combinação de todos os padrões é sempre válida
  e custa o preço base do modelo. É ela que aparece como "a partir de" na listagem.

## Sobre a prova final

```bash
cd kit/scripts
BASE_URL=https://magento.test ./prova-final.sh          # tudo
BASE_URL=https://magento.test SO=api ./prova-final.sh   # só uma etapa: api | graphql | corrida | carga
```

Precisa de `curl`, `jq` e `xargs` na máquina que roda o script. A loja precisa estar em
developer mode para a etapa de cache conseguir ler o cabeçalho `X-Magento-Cache-Debug`.
A meta é simples: **25 passaram, 0 falharam**, na frente do cliente.
