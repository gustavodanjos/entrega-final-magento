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
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    /**
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

        // Resolucao de preco direto do produto (sem fallbacks fixos arbitrarios)
        $basePrice = 0.0;
        $finalPrice = $product->getFinalPrice();
        if ($finalPrice !== null && (float) $finalPrice > 0) {
            $basePrice = (float) $finalPrice;
        } elseif ($product->getPrice() !== null && (float) $product->getPrice() > 0) {
            $basePrice = (float) $product->getPrice();
        } elseif ($product->getPriceInfo() && $product->getPriceInfo()->getPrice('final_price')) {
            $basePrice = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        }

        if ($basePrice <= 0.0 && !empty($catalog['preco_base'])) {
            $basePrice = (float) $catalog['preco_base'];
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
     * @return string
     */
    public function getStudioJson(): string
    {
        return $this->jsonSerializer->serialize($this->getStudioData());
    }
}