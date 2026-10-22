<?php
/**
 * Nuvem AtelieStudio Module
 *
 * @category Nuvem
 * @package  Nuvem_AtelieStudio
 * @author   Jhonatan Lobo
 */
declare(strict_types=1);

namespace Nuvem\AtelieStudio\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
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
        return $this->registry->registry('current_product');
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
            $jsLayout['components']['atelieStudio'] = [
                'component' => 'Nuvem_AtelieStudio/js/studio'
            ];
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

        // Restaura valores pre-configurados do carrinho caso seja edicao
        $initialGravacao = null;
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
            if (isset($atelieData['gravacao']) && $atelieData['gravacao'] !== '') {
                $initialGravacao = (string) $atelieData['gravacao'];
            }
            if (!empty($atelieData['tamanho'])) {
                $preSize = (int) $atelieData['tamanho'];
                if (in_array($preSize, $sizes, true)) {
                    $defaultSize = $preSize;
                }
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
                        $groupKey = $sel['grupo'];
                        if (isset($preSelections[$groupKey])) {
                            $sel['componente'] = $preSelections[$groupKey];
                        }
                    }
                    unset($sel);
                }
            }
        }

        // Resolucao dinamica de preco do produto
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

        if ($basePrice <= 0.0 && !empty($catalog['preco_base'])) {
            $basePrice = (float) $catalog['preco_base'];
        }

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
                'gravacao' => $initialGravacao,
                'selecoes' => $initialSelections,
            ],
            'grupos' => $catalog['grupos'] ?? [],
            'regras' => $catalog['regras'] ?? [],
            'urls' => [
                'disponibilidade' => $this->urlBuilder->getUrl('atelie/estoque/disponibilidade'),
                'validar' => $this->urlBuilder->getUrl('atelie/configuracao/validar'),
                'preview' => $this->urlBuilder->getUrl('atelie/preview/render'),
            ],
        ];
    }
}
