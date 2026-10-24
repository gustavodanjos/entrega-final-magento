<?php
declare(strict_types=1);

namespace Nuvem\AtelieStudio\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Psr\Log\LoggerInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Model\Config;

class Estudio implements ArgumentInterface
{
    /**
     * @param Registry $registry
     * @param AtelieCatalogInterface $atelieCatalog
     * @param ComponentRepositoryInterface $componentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param PriceCurrencyInterface $priceCurrency
     * @param Json $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly AtelieCatalogInterface $atelieCatalog,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Json $jsonSerializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get the current product from the registry.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    /**
     * Build the canonical studio data for the front-end.
     *
     * @return array
     */
    public function getStudioData(): array
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }

        try {
            $catalog = $this->atelieCatalog->getBySku($product->getSku());
        } catch (\Exception $e) {
            $catalog = [];
        }

        $engravingPrice = (float) $this->scopeConfig->getValue(
            Config::XML_PATH_ENGRAVING_PRICE,
            ScopeInterface::SCOPE_STORE
        );
        $engravingLimit = (int) $this->scopeConfig->getValue(
            Config::XML_PATH_ENGRAVING_LIMIT,
            ScopeInterface::SCOPE_STORE
        );

        $sizeMin = (int) ($catalog['tamanho_min'] ?? 34);
        $sizeMax = (int) ($catalog['tamanho_max'] ?? 44);
        $sizes = range($sizeMin, $sizeMax);

        // 1. Restaurar do carrinho (edição) se existir
        $initialSelections = null;
        $initialSize = null;
        $initialEngraving = null;

        $preconfigured = $product->getPreconfiguredValues();
        $atelieData = $preconfigured ? $preconfigured->getData('atelie') : null;

        if (is_array($atelieData) && !empty($atelieData['selecoes'])) {
            $initialSelections = $atelieData['selecoes'];
            $initialSize = isset($atelieData['tamanho']) ? (int) $atelieData['tamanho'] : null;
            $initialEngraving = isset($atelieData['gravacao']) && $atelieData['gravacao'] !== ''
                ? (string) $atelieData['gravacao']
                : null;
        }

        // 2. Fallback: padrões
        if ($initialSelections === null) {
            $initialSelections = [];
            foreach ($catalog['grupos'] ?? [] as $group) {
                $defaultComponentCode = null;
                foreach ($group['componentes'] ?? [] as $component) {
                    if (!empty($component['padrao'])) {
                        $defaultComponentCode = $component['codigo'];
                        break;
                    }
                }
                if (!$defaultComponentCode && !empty($group['componentes'][0]['codigo'])) {
                    $defaultComponentCode = $group['componentes'][0]['codigo'];
                }

                if ($defaultComponentCode) {
                    $initialSelections[] = [
                        'grupo' => $group['codigo'],
                        'componente' => $defaultComponentCode,
                    ];
                }
            }
        }

        if ($initialSize === null) {
            $initialSize = in_array(38, $sizes, true) ? 38 : $sizes[0];
        }

        // 3. Enriquecer componentes com estoque (para estado "esgotado" no front)
        $estoqueMap = $this->getEstoqueMap();
        $gruposEnriquecidos = $catalog['grupos'] ?? [];
        foreach ($gruposEnriquecidos as &$grupoRef) {
            foreach ($grupoRef['componentes'] as &$componenteRef) {
                $codigo = $componenteRef['codigo'] ?? '';
                $estoque = $estoqueMap[$codigo] ?? 0;
                // F2.5: nunca expor quantidade exata. Apenas disponibilidade.
                $componenteRef['disponivel'] = $estoque > 0;
            }
            unset($componenteRef);
        }
        unset($grupoRef);

        $basePrice = 0.0;
        if ($product) {
            // Base do modelo, SEM custom options de configuração.
            // getFinalPrice() em contexto de edição de carrinho já inclui
            // os adicionais da configuração — somar de novo no JS duplicaria.
            $basePrice = (float) ($catalog['preco_base'] ?? 0);
            if ($basePrice <= 0) {
                $basePrice = (float) (
                    $product->getPrice()
                    ?: $product->getData('price')
                    ?: 0.0
                );
            }
            if ($basePrice <= 0 && $product->getPriceInfo()) {
                $basePrice = (float) $product->getPriceInfo()
                    ->getPrice('regular_price')
                    ->getValue();
            }
        }
        if ($basePrice <= 0) {
            $this->logger->warning(
                'Atelie studio: base price could not be resolved for SKU ' . $product->getSku()
            );
        }

        return [
            'produto' => [
                'sku' => $catalog['sku'] ?? $product->getSku(),
                'nome' => $catalog['nome'] ?? $product->getName(),
                'preco_base' => $basePrice,
                'prazo_producao_dias' => (int) ($catalog['prazo_producao_dias'] ?? 12),
                'grade_tamanhos' => $sizes,
                'gravacao' => [
                    'permitida' => !empty($catalog['gravacao']),
                    'preco' => $catalog['gravacao']['preco'] ?? $engravingPrice,
                    'limite_caracteres' => $catalog['gravacao']['limite'] ?? $engravingLimit,
                    'placeholder' => 'Ex: NUVEM',
                ],
            ],
            'configuracao_inicial' => [
                'tamanho' => $initialSize,
                'gravacao' => $initialEngraving,
                'selecoes' => $initialSelections,
            ],
            'grupos' => $gruposEnriquecidos,
            'regras' => $catalog['regras'] ?? [],
            'urls' => [
                'disponibilidade' => $this->urlBuilder->getUrl('atelie/estoque/disponibilidade'),
                'validar' => $this->urlBuilder->getUrl('atelie/configuracao/validar'),
                'preview' => $this->urlBuilder->getUrl('atelie/preview/render'),
                'adicionar_carrinho' => $this->urlBuilder->getUrl('checkout/cart/add'),
            ],
        ];
    }

    /**
     * Merge the studio data into the jsLayout for Magento_Ui.
     *
     * @param array|string $jsLayout
     * @return string
     */
    public function getMergedJsLayout(array|string $jsLayout = []): string
    {
        if (is_string($jsLayout)) {
            $decoded = json_decode($jsLayout, true);
            $jsLayout = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($jsLayout)) {
            $jsLayout = [];
        }

        $studioData = $this->getStudioData();

        if (!isset($jsLayout['components']['atelieStudio'])) {
            $jsLayout['components']['atelieStudio'] = [
                'component' => 'Nuvem_AtelieStudio/js/studio'
            ];
        }

        $jsLayout['components']['atelieStudio']['config']['studioData'] = $studioData;
        $jsLayout['components']['atelieStudio']['config']['configurador'] = $studioData;
        $jsLayout['components']['atelieStudio']['studioData'] = $studioData;
        $jsLayout['components']['atelieStudio']['configurador'] = $studioData;

        return (string) json_encode($jsLayout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the studio data as a JSON string.
     *
     * @return string
     */
    public function getStudioJson(): string
    {
        return (string) json_encode($this->getStudioData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    /**
     * Mapa código => estoque, para o front representar o estado "esgotado".
     *
     * @return array<string, int>
     */
    private function getEstoqueMap(): array
    {
        $map = [];
        try {
            $criteria = $this->searchCriteriaBuilder
                ->setPageSize(1000)
                ->create();
            $results = $this->componentRepository->getList($criteria);
            foreach ($results->getItems() as $component) {
                $map[$component->getCodigo()] = $component->getEstoque();
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                'Atelie studio: failed to load component stock: ' . $e->getMessage()
            );
        }
        return $map;
    }
}
