#!/usr/bin/env bash
# =====================================================================
#  Ateliê Nuvem · Prova final da Sprint 9
#  Ataca a loja de fora, pela API, como um cliente (ou um espertinho) faria.
#
#  Uso:
#    BASE_URL=https://magento.test ./prova-final.sh
#    BASE_URL=https://magento.test CORRIDA=60 CARGA=400 ./prova-final.sh
#
#  Requer: bash 4+, curl, jq, xargs. Loja em developer mode para a prova de cache.
# =====================================================================
set -uo pipefail

BASE_URL="${BASE_URL:-https://magento.test}"
STORE="${STORE:-default}"
PDP_PATH="${PDP_PATH:-/nuvem-runner.html}"
CORRIDA="${CORRIDA:-40}"            # carrinhos tentando fechar ao mesmo tempo
ESTOQUE_LUA="${ESTOQUE_LUA:-10}"    # estoque da Sola Lua antes da corrida
CARGA="${CARGA:-200}"               # requisições na prova de carga
PARALELO_CARGA="${PARALELO_CARGA:-20}"
PRECO_VALIDA="${PRECO_VALIDA:-589.40}"
NAO_INTERATIVO="${NAO_INTERATIVO:-0}"
SO="${SO:-}"                        # rodar só uma etapa: api | disponibilidade | graphql | corrida | carga

REST="$BASE_URL/rest/$STORE/V1"
DIR="$(cd "$(dirname "$0")" && pwd)"
EX="$DIR/../exemplos"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
CURL=(curl -sk --max-time 90)

ok=0; falha=0
passou(){ printf '  \033[32mPASSOU\033[0m  %s\n' "$1"; ok=$((ok+1)); }
falhou(){ printf '  \033[31mFALHOU\033[0m  %s\n' "$1"; [ -n "${2:-}" ] && printf '          %s\n' "$2"; falha=$((falha+1)); }
titulo(){ printf '\n\033[1m%s\033[0m\n' "$1"; }
roda(){ [ -z "$SO" ] || [ "$SO" = "$1" ]; }
igual(){ awk -v a="$1" -v b="$2" 'BEGIN{d=a-b; if(d<0)d=-d; exit !(d<0.005)}'; }

for bin in curl jq xargs awk; do
  command -v "$bin" >/dev/null || { echo "Falta o comando: $bin"; exit 2; }
done

# ---------------------------------------------------------------- helpers
req(){ # req METODO URL [JSON] -> corpo em $TMP/body, imprime o HTTP code
  local m="$1" u="$2" d="${3:-}"
  if [ -n "$d" ]; then
    "${CURL[@]}" -D "$TMP/headers" -o "$TMP/body" -w '%{http_code}' -X "$m" -H 'Content-Type: application/json' -d "$d" "$u"
  else
    "${CURL[@]}" -D "$TMP/headers" -o "$TMP/body" -w '%{http_code}' -X "$m" "$u"
  fi
}
corpo(){ head -c 400 "$TMP/body" | tr '\n' ' '; }
novo_carrinho(){ req POST "$REST/guest-carts" >/dev/null; jq -r . "$TMP/body"; }
itens(){ req GET "$REST/guest-carts/$1/items" >/dev/null; cat "$TMP/body"; }
qtd_linhas(){ itens "$1" | jq 'length'; }

# item_payload ARQUIVO CART [filtro-na-config] [filtro-no-payload]
item_payload(){
  local arq="$1" cart="$2" pre="${3:-.}" pos="${4:-.}"
  jq -c --arg cart "$cart" "$pre | {cartItem: {sku: .sku, qty: .qty, quote_id: \$cart,
      product_option: {extension_attributes: {atelie_configuracao: .configuracao}}}} | $pos" "$arq"
}
troca(){ # gera filtro jq que troca o componente de um grupo
  echo ".configuracao.selecoes |= map(if .grupo==\"$1\" then .componente=\"$2\" else . end)"
}
deve_recusar(){ # deve_recusar "descrição" ARQUIVO [pre] [pos]
  local cart code n
  cart=$(novo_carrinho)
  code=$(req POST "$REST/guest-carts/$cart/items" "$(item_payload "$2" "$cart" "${3:-.}" "${4:-.}")")
  local msg resp; msg=$(jq -r '.message // empty' "$TMP/body" 2>/dev/null); resp=$(corpo)
  n=$(qtd_linhas "$cart")
  if [ "$code" = "400" ] && [ "$n" = "0" ]; then passou "$1  ->  \"$msg\""
  else falhou "$1 (HTTP $code, linhas no carrinho: $n)" "resposta: $resp"; fi
}
gql(){ # gql 'query' [variaveis-json] -> corpo em $TMP/body
  local v="${2:-}"; [ -z "$v" ] && v='{}'
  jq -nc --arg q "$1" --argjson v "$v" '{query:$q, variables:$v}' > "$TMP/gq"
  "${CURL[@]}" -o "$TMP/body" -w '%{http_code}' -X POST -H 'Content-Type: application/json' \
    --data @"$TMP/gq" "$BASE_URL/graphql" >/dev/null
}
endereco(){
  local rid
  req GET "$REST/directory/countries/BR" >/dev/null
  rid=$(jq -r '[.available_regions[]? | select(.code=="SP") | .id][0] // empty' "$TMP/body")
  jq -nc --arg rid "$rid" '
    ({firstname:"Prova", lastname:"Final", street:["Rua das Nuvens, 100"], city:"São Paulo",
      region:"São Paulo", region_code:"SP", postcode:"01310-100", country_id:"BR",
      telephone:"11999990000", email:"prova@nuvem.test"}
     + (if $rid=="" then {} else {region_id: ($rid|tonumber)} end)) as $a
    | {addressInformation:{shipping_address:$a, billing_address:$a,
       shipping_carrier_code:"flatrate", shipping_method_code:"flatrate"}}'
}

verifica_disponibilidade(){
  local code
  code=$(req GET "$REST/atelie/disponibilidade/NUVEM-RUNNER")
  if [ "$code" = "200" ] && jq -e '
    type == "array" and length > 0 and all(.[];
      type == "object" and
      keys == ["componente", "disponivel", "ultimas_unidades"] and
      (.componente | type == "string" and length > 0) and
      (.disponivel | type == "boolean") and
      (.ultimas_unidades | type == "boolean")) and
    ([.. | objects | keys[] | select(test("qty|estoque|quantidade"; "i"))] | length == 0)
  ' "$TMP/body" >/dev/null 2>&1; then
    passou "API pública devolve somente componente e flags booleanos, sem quantidade"
  else
    falhou "Contrato de disponibilidade inválido (HTTP $code)" "$(corpo)"
  fi
  if tr -d '\r' < "$TMP/headers" | awk '
    tolower($0) ~ /^cache-control:/ {
      value=tolower($0)
      if (value ~ /no-store/ && value ~ /no-cache/ && value ~ /must-revalidate/ && value ~ /max-age=0/) control=1
    }
    tolower($0) ~ /^pragma: *no-cache *$/ { pragma=1 }
    tolower($0) ~ /^expires:/ { expires=1 }
    END { exit !(control && pragma && expires) }
  '; then
    passou "Disponibilidade pública tem cabeçalhos contra cache"
  else
    falhou "Disponibilidade pública sem os cabeçalhos contra cache esperados"
  fi
}

printf '\033[1mAteliê Nuvem · Prova final\033[0m   %s\n' "$BASE_URL"

# ================================================================ 1. API REST
if roda api; then
titulo "1. O caminho feliz pela API REST"
cart=$(novo_carrinho)
[ -n "$cart" ] && [ "$cart" != "null" ] || { echo "Não consegui criar carrinho em $REST. A loja está de pé?"; exit 2; }
code=$(req POST "$REST/guest-carts/$cart/items" "$(item_payload "$EX/configuracao-valida.json" "$cart")")
if [ "$code" = "200" ]; then passou "Configuração válida entra no carrinho"
else falhou "Configuração válida foi recusada (HTTP $code)" "$(corpo)"; fi
req GET "$REST/guest-carts/$cart/totals" >/dev/null
p=$(jq -r '.items[0].price // 0' "$TMP/body")
if igual "$p" "$PRECO_VALIDA"; then passou "Preço calculado pelo servidor: R\$ $p"
else falhou "Preço esperado R\$ $PRECO_VALIDA, veio R\$ $p"; fi
n=$(itens "$cart" | jq '[.[0].product_option.extension_attributes.atelie_configuracao.selecoes[]?] | length')
if [ "${n:-0}" -ge 7 ] 2>/dev/null; then passou "A configuração volta na leitura do item ($n seleções)"
else falhou "A leitura do item não devolveu a configuração completa (seleções: $n)"; fi

titulo "2. Tentativas de burlar as regras (todas precisam ser recusadas)"
V="$EX/configuracao-valida.json"
deve_recusar "R01: camurça com solado holográfico" "$EX/configuracao-proibida.json"
deve_recusar "R02: cadarço elástico sem cabedal de tricô" "$V" "$(troca cabedal CAB-TELA-MENTA)"
deve_recusar "R04: câmara de ar sem solado translúcido" "$V" "$(troca entressola ENT-AR-VISIVEL) | $(troca solado SOL-BORRACHA-MENTA) | .configuracao.gravacao = null"
deve_recusar "R05: gravação sobre a câmara de ar" "$V" "$(troca entressola ENT-AR-VISIVEL)"
deve_recusar "Componente que não existe" "$V" "$(troca cabedal CAB-OURO-24K)"
deve_recusar "Componente no grupo errado" "$V" "$(troca cabedal SOL-BORRACHA-MENTA)"
deve_recusar "Grupo obrigatório faltando" "$V" '.configuracao.selecoes |= map(select(.grupo!="solado"))'
deve_recusar "Tamanho fora da grade" "$V" '.configuracao.tamanho = 50'
deve_recusar "Gravação com HTML" "$V" '.configuracao.gravacao = "<script>alert(1)</script>"'
deve_recusar "Gravação acima do limite" "$V" '.configuracao.gravacao = "NUVEMNUVEMNUVEM"'
deve_recusar "Gravação em produto que não permite (Nuvem Mini)" "$V" '.sku = "NUVEM-MINI" | .configuracao.tamanho = 30'

titulo "3. Preço adulterado e linhas do carrinho"
cart=$(novo_carrinho)
code=$(req POST "$REST/guest-carts/$cart/items" "$(item_payload "$V" "$cart" . '.cartItem.price = 1 | .cartItem.custom_price = 1')")
req GET "$REST/guest-carts/$cart/totals" >/dev/null
p=$(jq -r '.items[0].price // 0' "$TMP/body")
if igual "$p" "$PRECO_VALIDA"; then passou "Preço enviado no payload foi ignorado (R\$ $p)"
else falhou "Preço adulterado passou: R\$ $p (HTTP $code)"; fi
req POST "$REST/guest-carts/$cart/items" "$(item_payload "$V" "$cart")" >/dev/null
req POST "$REST/guest-carts/$cart/items" "$(item_payload "$V" "$cart" "$(troca cabedal CAB-TRICO-PESSEGO)")" >/dev/null
linhas=$(qtd_linhas "$cart"); total=$(itens "$cart" | jq '[.[].qty] | add')
if [ "$linhas" = "2" ] && [ "$total" = "3" ]; then passou "Mesma configuração soma quantidade, configuração diferente abre linha nova"
else falhou "Esperava 2 linhas e 3 pares, veio $linhas linhas e $total pares"; fi

verifica_disponibilidade
fi

if [ "$SO" = "disponibilidade" ]; then
  titulo "Disponibilidade pública"
  verifica_disponibilidade
fi

# ================================================================ 2. GraphQL
if roda graphql; then
titulo "4. GraphQL"
gql 'query{ atelieConfigurador(sku:"NUVEM-RUNNER"){ grupos{ codigo componentes{ codigo } } regras{ codigo } } }'
g=$(jq '.data.atelieConfigurador.grupos | length' "$TMP/body" 2>/dev/null); r=$(jq '.data.atelieConfigurador.regras | length' "$TMP/body" 2>/dev/null)
if [ "${g:-0}" -ge 7 ] 2>/dev/null && [ "${r:-0}" -ge 5 ] 2>/dev/null; then passou "atelieConfigurador devolve $g grupos e $r regras"
else falhou "atelieConfigurador incompleto" "$(corpo)"; fi
M='mutation($cart:String!,$sku:String!,$cfg:AtelieConfiguracaoInput!){ addAtelieProductToCart(input:{cart_id:$cart, sku:$sku, quantity:1, configuracao:$cfg}){ cart{ items{ quantity prices{ price{ value } } } } } }'
cart=$(novo_carrinho)
gql "$M" "$(jq -c --arg c "$cart" '{cart:$c, sku:.sku, cfg:.configuracao}' "$EX/configuracao-proibida.json")"
erros=$(jq '.errors | length' "$TMP/body" 2>/dev/null); msg=$(jq -r '.errors[0].message // empty' "$TMP/body" 2>/dev/null)
n=$(qtd_linhas "$cart")
if [ "${erros:-0}" -ge 1 ] 2>/dev/null && [ "$n" = "0" ]; then passou "Combinação proibida recusada via GraphQL -> \"$msg\""
else falhou "GraphQL aceitou a combinação proibida (linhas: $n)" "$(corpo)"; fi
cart=$(novo_carrinho)
gql "$M" "$(jq -c --arg c "$cart" '{cart:$c, sku:.sku, cfg:.configuracao}' "$EX/configuracao-valida.json")"
p=$(jq -r '.data.addAtelieProductToCart.cart.items[0].prices.price.value // 0' "$TMP/body" 2>/dev/null)
if igual "$p" "$PRECO_VALIDA"; then passou "Configuração válida via GraphQL com o mesmo preço do REST (R\$ $p)"
else falhou "Preço via GraphQL: R\$ $p (esperado $PRECO_VALIDA)" "$(corpo)"; fi
fi

# ================================================================ 3. Corrida
if roda corrida; then
titulo "5. A corrida pela Sola Lua ($CORRIDA clientes, $ESTOQUE_LUA unidades)"
echo "  Antes de seguir, zere o palco no terminal do Magento:"
echo "    bin/magento atelie:estoque:definir SOL-LUA-HOLO $ESTOQUE_LUA"
[ "$NAO_INTERATIVO" = "1" ] || read -rp "  Enter quando estiver pronto... " _
END=$(endereco); : > "$TMP/carrinhos"
for i in $(seq 1 "$CORRIDA"); do
  c=$(novo_carrinho)
  req POST "$REST/guest-carts/$c/items" "$(item_payload "$EX/configuracao-lua.json" "$c")" >/dev/null
  req POST "$REST/guest-carts/$c/shipping-information" "$END" >/dev/null
  echo "$c" >> "$TMP/carrinhos"; printf '\r  carrinhos prontos: %d/%d' "$i" "$CORRIDA"
done; echo
fechar(){
  curl -sk --max-time 180 -o "$TMPD/r_$1" -w '%{http_code}\n' -X POST -H 'Content-Type: application/json' \
    -d '{"email":"corrida@nuvem.test","paymentMethod":{"method":"checkmo"}}' \
    "$REST/guest-carts/$1/payment-information" > "$TMPD/c_$1"
}
export -f fechar; export TMPD="$TMP" REST
inicio=$(date +%s)
xargs -P "$CORRIDA" -I{} bash -c 'fechar "$@"' _ {} < "$TMP/carrinhos"
dur=$(( $(date +%s) - inicio ))
pedidos=$(cat "$TMP"/c_* | grep -c '^200$'); recus=$(cat "$TMP"/c_* | grep -c '^4'); quebrou=$(cat "$TMP"/c_* | grep -c '^5\|^000')
echo "  $pedidos pedidos, $recus recusas limpas, $quebrou erros de servidor, em ${dur}s"
if [ "$pedidos" = "$ESTOQUE_LUA" ]; then passou "Exatamente $ESTOQUE_LUA pedidos fecharam"
else falhou "Fecharam $pedidos pedidos para $ESTOQUE_LUA unidades"; fi
if [ "$quebrou" = "0" ]; then passou "Ninguém viu erro 500: quem perdeu a corrida recebeu mensagem"
else falhou "$quebrou requisições terminaram em 5xx ou timeout"; fi
req GET "$REST/atelie/disponibilidade/NUVEM-RUNNER" >/dev/null
disp=$(jq -r '[.[]? | select(.componente=="SOL-LUA-HOLO") | .disponivel][0]' "$TMP/body")
if [ "$disp" = "false" ]; then passou "Sola Lua aparece como esgotada na API pública"
else falhou "Sola Lua ainda aparece disponível ($disp)"; fi
cat <<SQL
  Confira o razão de movimentação no banco (deve fechar com o estoque):
    SELECT tipo, SUM(quantidade) FROM nuvem_atelie_movimentacao m
      JOIN nuvem_atelie_componente c ON c.componente_id = m.componente_id
     WHERE c.codigo = 'SOL-LUA-HOLO' GROUP BY tipo;
SQL
fi

# ================================================================ 4. Carga
if roda carga; then
titulo "6. Carga na página do produto ($CARGA requisições, $PARALELO_CARGA em paralelo)"
"${CURL[@]}" -o /dev/null "$BASE_URL$PDP_PATH"; "${CURL[@]}" -o /dev/null "$BASE_URL$PDP_PATH"
seq "$CARGA" | xargs -P "$PARALELO_CARGA" -I{} curl -sk --max-time 60 -o /dev/null -D - \
  -w 'RESULTADO %{http_code} %{time_total}\n' "$BASE_URL$PDP_PATH" > "$TMP/carga"
erros=$(grep '^RESULTADO' "$TMP/carga" | awk '$2>=500 || $2=="000"' | wc -l | tr -d ' ')
hits=$(grep -ci '^x-magento-cache-debug: *hit' "$TMP/carga"); deb=$(grep -ci '^x-magento-cache-debug' "$TMP/carga")
p95=$(grep '^RESULTADO' "$TMP/carga" | awk '{print $3}' | sort -n | awk '{a[NR]=$1} END{i=int(NR*0.95); if(i<1)i=1; printf "%.3f", a[i]}')
echo "  p95: ${p95}s · cache HIT: $hits de $deb respostas com cabeçalho de debug"
if [ "$erros" = "0" ]; then passou "Nenhum erro de servidor sob carga"; else falhou "$erros respostas com erro sob carga"; fi
if [ "$deb" = "0" ]; then echo "  (sem X-Magento-Cache-Debug: rode em developer mode para medir o cache)"
elif [ $((hits * 100 / deb)) -ge 95 ]; then passou "Página do produto servida do cache (${hits}/${deb})"
else falhou "Cache da página do produto abaixo de 95% (${hits}/${deb}). Algo está furando o FPC."; fi
fi

titulo "Resultado: $ok passaram, $falha falharam"
[ "$falha" = "0" ]
