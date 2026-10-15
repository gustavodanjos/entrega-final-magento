# Ateliê Nuvem · Contrato do banco

Tudo em `db_schema.xml` (declarative schema) com `db_schema_whitelist.json` gerado por
`bin/magento setup:db-declaration:generate-whitelist --module-name=<Módulo>`.
Dono de cada tabela entre parênteses. Mudar coluna de tabela de outra frente = PR com o dono revisando.

## nuvem_atelie_grupo (F1, módulo Nuvem_Atelie)
| coluna | tipo | observação |
|---|---|---|
| grupo_id | int unsigned identity | PK |
| codigo | varchar(32) | único. `cabedal`, `solado`... |
| nome | varchar(64) | rótulo para o cliente |
| ordem | smallint | ordem das abas no estúdio |
| camada_svg | varchar(64) | id do `<g>` no SVG (`camada-cabedal`) |
| ativo | boolean | default 1 |

## nuvem_atelie_componente (F1)
| coluna | tipo | observação |
|---|---|---|
| componente_id | int unsigned identity | PK |
| grupo_id | int unsigned | FK para grupo, `onDelete="NO ACTION"`: não se apaga grupo com componente |
| codigo | varchar(40) | único. `CAB-TRICO-LAVANDA` |
| nome | varchar(80) | |
| cor_hex | varchar(7) | `#DCCFF7` |
| acabamento | varchar(32) | `tela`, `trico`, `camurca`... (as regras usam) |
| preco_adicional | decimal(12,4) | |
| estoque | int unsigned | **nunca negativo**. Só muda por `UPDATE ... WHERE estoque >= :qtd` |
| padrao | boolean | um por grupo |
| ativo | boolean | |
| ordem | smallint | |
| updated_at | timestamp | `on_update="true"` |

## nuvem_atelie_regra (F1)
| coluna | tipo | observação |
|---|---|---|
| regra_id | int unsigned identity | PK |
| codigo | varchar(16) | único. `R01` |
| tipo | varchar(16) | `bloqueia` ou `exige` |
| origem_componente_id | int unsigned | FK para componente, CASCADE |
| alvo_tipo | varchar(16) | `componente`, `acabamento` ou `gravacao` |
| alvo | varchar(40) | código do componente, nome do acabamento ou `*` |
| mensagem | varchar(255) | texto que o cliente lê |
| ativo | boolean | |

## nuvem_atelie_movimentacao (F2, módulo Nuvem_AtelieSales)
O razão do estoque. Toda mudança de estoque gera uma linha. A soma das linhas de um componente explica o estoque atual.
| coluna | tipo | observação |
|---|---|---|
| movimentacao_id | bigint unsigned identity | PK |
| componente_id | int unsigned | FK |
| tipo | varchar(16) | `reserva`, `compensacao`, `devolucao`, `ajuste` |
| quantidade | int | negativo sai, positivo volta |
| quote_id | int unsigned nullable | |
| order_id | int unsigned nullable | |
| order_item_id | int unsigned nullable | |
| observacao | varchar(255) | |
| created_at | timestamp | default CURRENT_TIMESTAMP |
Índices: `componente_id`, `order_id`.

## nuvem_atelie_criacao (F5, módulo Nuvem_AtelieVitrine)
| coluna | tipo | observação |
|---|---|---|
| criacao_id | int unsigned identity | PK |
| hash | varchar(32) | único. Vai na URL. **Nunca** expor o id sequencial |
| customer_id | int unsigned nullable | FK `customer_entity`, `SET NULL` |
| sku | varchar(64) | |
| nome | varchar(80) | "Meu algodão-doce" |
| configuracao | text | JSON no formato do contrato |
| publica | boolean | default 0 |
| curtidas | int unsigned | default 0 |
| created_at / updated_at | timestamp | |
Índice: (`publica`, `created_at`).

## nuvem_atelie_ficha (F4, módulo Nuvem_AtelieAdmin)
Ficha de produção que a fábrica recebe. Criada pelo consumidor da fila.
| coluna | tipo | observação |
|---|---|---|
| ficha_id | int unsigned identity | PK |
| order_id | int unsigned | |
| order_item_id | int unsigned | **único**: reprocessar a mensagem não pode duplicar ficha |
| status | varchar(16) | `fila`, `produzindo`, `pronta`, `enviada` |
| conteudo | text | JSON: snapshot da configuração com nomes e cores |
| created_at / updated_at | timestamp | |

## nuvem_atelie_popularidade_idx (F5, diferencial)
Tabela de índice. Escrita só pelo indexador.
| coluna | tipo |
|---|---|
| componente_id | int unsigned PK |
| pedidos | int unsigned |
