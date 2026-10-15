# Ateliê Nuvem · Contrato REST

Contrato combinado na Frente 0. Muda só com PR aprovado pelo time inteiro.

## Formato da configuração (o mesmo em todo lugar)

O tênis configurado é sempre descrito assim, seja no carrinho, no pedido, na criação salva, no GraphQL ou no REST:

```json
{
  "tamanho": 38,
  "gravacao": "LUIZ",
  "selecoes": [
    { "grupo": "cabedal",    "componente": "CAB-TRICO-LAVANDA" },
    { "grupo": "biqueira",   "componente": "BIQ-LISA-CEU" },
    { "grupo": "calcanhar",  "componente": "CAL-ACOLCHOADO-CEU" },
    { "grupo": "cadarco",    "componente": "CAD-ELASTICO-MENTA" },
    { "grupo": "emblema",    "componente": "EMB-NUVEM-PESSEGO" },
    { "grupo": "entressola", "componente": "ENT-EVA-NUVEM" },
    { "grupo": "solado",     "componente": "SOL-TRANSLUCIDO-GELO" }
  ]
}
```

Por que `selecoes` é uma lista e não um objeto `{ "cabedal": "CAB-..." }`? Porque a Web API do Magento só serializa tipos declarados em interfaces PHP. Mapa com chave livre não tem como ser descrito numa interface. Lista de objetos tipados tem.

Interfaces PHP (Frente 0, módulo `Nuvem_Atelie`):

- `Nuvem\Atelie\Api\Data\ConfiguracaoInterface`: `getTamanho(): int`, `getGravacao(): ?string`, `getSelecoes(): SelecaoInterface[]` e os setters.
- `Nuvem\Atelie\Api\Data\SelecaoInterface`: `getGrupo(): string`, `getComponente(): string` e os setters.

## Rotas

| Método | Rota | Acesso | O que faz |
|---|---|---|---|
| GET | `/V1/atelie/configurador/:sku` | anonymous | Grupos, componentes, preços e regras (o mesmo conteúdo do `atelieConfigurador` do GraphQL) |
| GET | `/V1/atelie/disponibilidade/:sku` | anonymous | `[{componente, disponivel, ultimas_unidades}]`. **Nunca** devolve quantidade |
| POST | `/V1/atelie/validar` | anonymous | Corpo `{sku, configuracao}`. Devolve `{valido, preco, erros[]}` sem mexer em carrinho |
| POST | `/V1/guest-carts/:cartId/items` | anonymous | Rota do **core**. A configuração vai em `product_option.extension_attributes.atelie_configuracao` |
| POST | `/V1/carts/mine/items` | self | Mesma coisa para cliente logado |
| GET | `/V1/guest-carts/:cartId/items` | anonymous | Rota do core. Cada item do Ateliê devolve a configuração em `product_option.extension_attributes.atelie_configuracao` |
| GET | `/V1/atelie/componentes` | `Nuvem_Atelie::componentes` | Lista com `searchCriteria` (admin) |
| PUT | `/V1/atelie/componentes/:codigo` | `Nuvem_Atelie::componentes_editar` | Atualiza um componente (admin) |

## Adicionar ao carrinho pela API

```http
POST /rest/default/V1/guest-carts/{cartId}/items
Content-Type: application/json

{
  "cartItem": {
    "sku": "NUVEM-RUNNER",
    "qty": 1,
    "quote_id": "{cartId}",
    "product_option": {
      "extension_attributes": {
        "atelie_configuracao": { "tamanho": 38, "gravacao": "LUIZ", "selecoes": [ ... ] }
      }
    }
  }
}
```

## Erros

| Situação | HTTP | Corpo |
|---|---|---|
| Regra violada, componente inválido, tamanho fora da grade, gravação inválida | 400 | `{"message": "<mensagem da regra, em português, pronta para o cliente>"}` |
| Componente esgotado na hora de fechar o pedido | 400 | `{"message": "O componente X esgotou enquanto você finalizava. Troque e tente de novo."}` |
| SKU que não é do tipo `atelie` | 400 | `{"message": "..."}` |
| Qualquer coisa que termine em 500 | | **é bug**. A prova final reprova |

Regra de ouro: o servidor nunca confia em preço, disponibilidade ou validação que venha do navegador. O navegador ajuda o cliente. O servidor decide.
