<?php
declare(strict_types=1);

namespace Nuvem\AtelieStudio\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;

/**
 * ViewModel responsavel por entregar os dados canonicos do estudio ao componente Knockout.
 */
class Estudio implements ArgumentInterface
{
    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var AtelieCatalogInterface
     */
    private AtelieCatalogInterface $atelieCatalog;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @param Registry $registry
     * @param AtelieCatalogInterface $atelieCatalog
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param Json $jsonSerializer
     */
    public function __construct(
        Registry $registry,
        AtelieCatalogInterface $atelieCatalog,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        Json $jsonSerializer
    ) {
        $this->registry = $registry;
        $this->atelieCatalog = $atelieCatalog;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Obter o produto atual do registo.
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Mesclar os dados de configuracao do estudio na arvore jsLayout.
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
            $jsLayout['components']['atelieStudio'] = ['component' => 'Nuvem_AtelieStudio/js/studio'];
        }
        if (!isset($jsLayout['components']['atelieStudio']['config'])) {
            $jsLayout['components']['atelieStudio']['config'] = [];
        }

        $jsLayout['components']['atelieStudio']['config'] = array_replace_recursive(
            $jsLayout['components']['atelieStudio']['config'],
            $studioData
        );
        $jsLayout['components']['atelieStudio']['config']['studioData'] = $studioData;
        $jsLayout['components']['atelieStudio']['studioData'] = $studioData;

        return $this->jsonSerializer->serialize($jsLayout);
    }

    /**
     * Obter a arvore canonica de dados do estudio para o produto.
     *
     * @return array
     */
    public function getStudioData(): array
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }

        $sku = (string) $product->getSku();
        $catalog = $this->atelieCatalog->getBySku($sku);

        $basePrice = (float) $product->getFinalPrice();

        $sizes = [];
        $minSize = (int) $product->getData('atelie_tamanho_min');
        $maxSize = (int) $product->getData('atelie_tamanho_max');
        if ($minSize > 0 && $maxSize >= $minSize) {
            $sizes = range($minSize, $maxSize);
        } else {
            $sizes = (stripos($sku, 'MINI') !== false) ? range(26, 33) : range(34, 44);
        }

        $defaultSize = !empty($sizes) ? $sizes[0] : null;
        $initialGravacao = null;
        $initialSelections = [];

        if (!empty($catalog['grupos'])) {
            foreach ($catalog['grupos'] as $grupo) {
                $defaultCode = null;
                if (!empty($grupo['componentes'])) {
                    foreach ($grupo['componentes'] as $comp) {
                        if (!empty($comp['padrao'])) {
                            $defaultCode = $comp['codigo'];
                            break;
                        }
                    }
                    if (!$defaultCode) {
                        $defaultCode = $grupo['componentes'][0]['codigo'];
                    }
                }
                if ($defaultCode) {
                    $initialSelections[] = ['grupo' => $grupo['codigo'], 'componente' => $defaultCode];
                }
            }
        }

        $buyRequestData = [];
        $preconfigured = $product->getPreconfiguredValues();
        if ($preconfigured instanceof DataObject) {
            $buyRequestData = $preconfigured->getData();
        } elseif ($product->getCustomOption('info_buyRequest')) {
            $val = $product->getCustomOption('info_buyRequest')->getValue();
            $buyRequestData = is_string($val) ? ($this->jsonSerializer->unserialize($val) ?? []) : (array) $val;
        }

        $atelieData = $buyRequestData['atelie'] ?? $buyRequestData;
        if (is_array($atelieData)) {
            if (!empty($atelieData['gravacao'])) {
                $initialGravacao = (string) $atelieData['gravacao'];
            }
            if (!empty($atelieData['tamanho']) && in_array((int) $atelieData['tamanho'], $sizes, true)) {
                $defaultSize = (int) $atelieData['tamanho'];
            }
            if (!empty($atelieData['selecoes']) && is_array($atelieData['selecoes'])) {
                $preSelections = [];
                foreach ($atelieData['selecoes'] as $item) {
                    if (isset($item['grupo'], $item['componente'])) {
                        $preSelections[$item['grupo']] = (string) $item['componente'];
                    }
                }
                if (!empty($preSelections)) {
                    foreach ($initialSelections as &$sel) {
                        if (isset($preSelections[$sel['grupo']])) {
                            $sel['componente'] = $preSelections[$sel['grupo']];
                        }
                    }
                    unset($sel);
                }
            }
        }

        $engravingPrice = (float) $this->scopeConfig->getValue('atelie/engraving/price') ?: 29.90;
        $engravingLimit = (int) $this->scopeConfig->getValue('atelie/engraving/limit') ?: 12;
        
        $engravingAllowedData = $product->getData('atelie_permite_gravacao');
        $engravingAllowed = ($engravingAllowedData !== null)
            ? (bool)$engravingAllowedData
            : (stripos($sku, 'MINI') === false);

        return [
            'produto' => [
                'sku' => $sku,
                'preco_base' => $basePrice,
                'grade_tamanhos' => $sizes,
                'gravacao' => [
                    'permitida' => $engravingAllowed,
                    'preco' => $engravingPrice,
                    'limite_caracteres' => $engravingLimit
                ]
            ],
            'configuracao_inicial' => [
                'tamanho' => $defaultSize,
                'gravacao' => $initialGravacao,
                'selecoes' => $initialSelections
            ],
            'grupos' => $catalog['grupos'] ?? [],
            'regras' => $catalog['regras'] ?? [],
            'urls' => [
                'disponibilidade' => $this->urlBuilder->getUrl('atelie/estoque/disponibilidade'),
                'validar' => $this->urlBuilder->getUrl('atelie/configuracao/validar'),
                'preview' => $this->urlBuilder->getUrl('atelie/preview/render'),
                'adicionar_carrinho' => $this->urlBuilder->getUrl('checkout/cart/add')
            ]
        ];
    }
}
