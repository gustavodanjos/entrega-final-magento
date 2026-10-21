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
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\Config;

class Estudio implements ArgumentInterface
{
    /**
     * Estudio constructor.
     *
     * @param Registry $registry
     * @param AtelieCatalogInterface $atelieCatalog
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param PriceCurrencyInterface $priceCurrency
     * @param Json $jsonSerializer
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly AtelieCatalogInterface $atelieCatalog,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Json $jsonSerializer
    ) {
    }

    /**
     * Retrieve current product instance.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    /**
     * Get studio configuration data.
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
        $defaultSize = in_array(38, $sizes, true) ? 38 : $sizes[0];

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

        // 1. Tenta obter o preco dinamico diretamente do produto do Magento
        $basePrice = 0.0;
        if ($product) {
            $finalPrice = $product->getFinalPrice();
            $price = $product->getPrice();

            if ($finalPrice !== null && (float) $finalPrice > 0) {
                $basePrice = (float) $finalPrice;
            } elseif ($price !== null && (float) $price > 0) {
                $basePrice = (float) $price;
            } elseif ($product->getPriceInfo() && $product->getPriceInfo()->getPrice('final_price')) {
                $amount = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                if ($amount > 0) {
                    $basePrice = $amount;
                }
            }
        }

        // 2. Tenta obter o preco canonico retornado pelo AtelieCatalog
        if ($basePrice <= 0.0 && !empty($catalog['preco_base']) && (float) $catalog['preco_base'] > 0) {
            $basePrice = (float) $catalog['preco_base'];
        }

        // 3. Fallback canonico do briefing/manual de marca caso produto e catalogo retornem 0
        if ($basePrice <= 0.0) {
            $sku = (string) ($catalog['sku'] ?? ($product ? $product->getSku() : ''));
            $basePrice = (stripos($sku, 'MINI') !== false) ? 329.90 : 449.90;
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
                'tamanho' => $defaultSize,
                'gravacao' => null,
                'selecoes' => $initialSelections,
            ],
            'grupos' => $catalog['grupos'] ?? [],
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
     * Merge studio data into jsLayout.
     *
     * @param array|string $jsLayout
     * @return string
     */
    public function getMergedJsLayout(array|string $jsLayout = []): string
    {
        if (is_string($jsLayout)) {
            $jsLayout = $this->jsonSerializer->unserialize($jsLayout);
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
        $jsLayout['components']['atelieStudio']['studioData'] = $studioData;

        return $this->jsonSerializer->serialize($jsLayout);
    }

    /**
     * Get studio data serialized as JSON.
     *
     * @return string
     */
    public function getStudioJson(): string
    {
        return $this->jsonSerializer->serialize($this->getStudioData());
    }
}
