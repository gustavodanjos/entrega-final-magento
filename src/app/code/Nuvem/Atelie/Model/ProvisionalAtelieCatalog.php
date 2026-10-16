<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\AtelieCatalogInterface;

/**
 * Provisional catalog backed by kit data, so no front waits for the real one (F1.1).
 *
 * Kit sources: kit/dados/produtos.csv, kit/dados/grupos.csv,
 * kit/dados/componentes.csv and kit/dados/regras.csv.
 */
class ProvisionalAtelieCatalog implements AtelieCatalogInterface
{
    private const PRODUCTS = [
        'NUVEM-RUNNER' => [
            'nome' => 'Nuvem Runner',
            'preco_base' => 449.90,
            'tamanho_min' => 34,
            'tamanho_max' => 44,
            'prazo_producao_dias' => 12,
            'gravacao' => ['preco' => 29.90, 'limite' => 12],
        ],
        'NUVEM-MINI' => [
            'nome' => 'Nuvem Mini',
            'preco_base' => 329.90,
            'tamanho_min' => 26,
            'tamanho_max' => 33,
            'prazo_producao_dias' => 10,
            'gravacao' => null,
        ],
    ];

    private const GROUPS = [
        [
            'codigo' => 'cabedal',
            'nome' => 'Cabedal',
            'ordem' => 10,
            'camada_svg' => 'camada-cabedal',
            'componentes' => [
                ['codigo' => 'CAB-TELA-MENTA', 'nome' => 'Tela Menta', 'cor_hex' => '#C6F0DC', 'acabamento' => 'tela', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'CAB-TELA-CEU', 'nome' => 'Tela Céu', 'cor_hex' => '#BEE3F8', 'acabamento' => 'tela', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'CAB-TELA-ROSA', 'nome' => 'Tela Rosa Magenta', 'cor_hex' => '#F7C6E4', 'acabamento' => 'tela', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'CAB-TRICO-LAVANDA', 'nome' => 'Tricô Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'trico', 'preco_adicional' => 39.90, 'padrao' => false],
                ['codigo' => 'CAB-TRICO-PESSEGO', 'nome' => 'Tricô Pêssego', 'cor_hex' => '#FFD8C2', 'acabamento' => 'trico', 'preco_adicional' => 39.90, 'padrao' => false],
                ['codigo' => 'CAB-CAMURCA-BAUNILHA', 'nome' => 'Camurça Baunilha', 'cor_hex' => '#FFF1B8', 'acabamento' => 'camurca', 'preco_adicional' => 59.90, 'padrao' => false],
            ],
        ],
        [
            'codigo' => 'biqueira',
            'nome' => 'Biqueira',
            'ordem' => 20,
            'camada_svg' => 'camada-biqueira',
            'componentes' => [
                ['codigo' => 'BIQ-LISA-CEU', 'nome' => 'Lisa Céu', 'cor_hex' => '#BEE3F8', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'BIQ-LISA-MENTA', 'nome' => 'Lisa Menta', 'cor_hex' => '#C6F0DC', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'BIQ-LISA-NUVEM', 'nome' => 'Lisa Nuvem', 'cor_hex' => '#FBFAFF', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'BIQ-VERNIZ-ROSA', 'nome' => 'Verniz Rosa', 'cor_hex' => '#F2A7D3', 'acabamento' => 'verniz', 'preco_adicional' => 24.90, 'padrao' => false],
            ],
        ],
        [
            'codigo' => 'calcanhar',
            'nome' => 'Calcanhar',
            'ordem' => 30,
            'camada_svg' => 'camada-calcanhar',
            'componentes' => [
                ['codigo' => 'CAL-LISO-ROSA', 'nome' => 'Liso Rosa Magenta', 'cor_hex' => '#F7C6E4', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'CAL-LISO-LAVANDA', 'nome' => 'Liso Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'CAL-LISO-MENTA', 'nome' => 'Liso Menta', 'cor_hex' => '#C6F0DC', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'CAL-ACOLCHOADO-CEU', 'nome' => 'Acolchoado Céu', 'cor_hex' => '#A9D6F2', 'acabamento' => 'acolchoado', 'preco_adicional' => 19.90, 'padrao' => false],
            ],
        ],
        [
            'codigo' => 'cadarco',
            'nome' => 'Cadarço',
            'ordem' => 40,
            'camada_svg' => 'camada-cadarco',
            'componentes' => [
                ['codigo' => 'CAD-ALGODAO-NUVEM', 'nome' => 'Algodão Nuvem', 'cor_hex' => '#FFFFFF', 'acabamento' => 'algodao', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'CAD-ALGODAO-ROSA', 'nome' => 'Algodão Rosa', 'cor_hex' => '#F7C6E4', 'acabamento' => 'algodao', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'CAD-ALGODAO-CEU', 'nome' => 'Algodão Céu', 'cor_hex' => '#BEE3F8', 'acabamento' => 'algodao', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'CAD-ELASTICO-MENTA', 'nome' => 'Elástico Menta', 'cor_hex' => '#9EDFC0', 'acabamento' => 'elastico', 'preco_adicional' => 14.90, 'padrao' => false],
            ],
        ],
        [
            'codigo' => 'emblema',
            'nome' => 'Emblema Nuvem',
            'ordem' => 50,
            'camada_svg' => 'camada-emblema',
            'componentes' => [
                ['codigo' => 'EMB-NUVEM-PESSEGO', 'nome' => 'Nuvem Pêssego', 'cor_hex' => '#FFD8C2', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'EMB-NUVEM-BAUNILHA', 'nome' => 'Nuvem Baunilha', 'cor_hex' => '#FFF1B8', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'EMB-NUVEM-LAVANDA', 'nome' => 'Nuvem Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'EMB-NUVEM-GLITTER', 'nome' => 'Nuvem Glitter', 'cor_hex' => '#F5E6FF', 'acabamento' => 'glitter', 'preco_adicional' => 29.90, 'padrao' => false],
            ],
        ],
        [
            'codigo' => 'entressola',
            'nome' => 'Entressola',
            'ordem' => 60,
            'camada_svg' => 'camada-entressola',
            'componentes' => [
                ['codigo' => 'ENT-EVA-NUVEM', 'nome' => 'EVA Nuvem', 'cor_hex' => '#FBFAFF', 'acabamento' => 'eva', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'ENT-EVA-BAUNILHA', 'nome' => 'EVA Baunilha', 'cor_hex' => '#FFF1B8', 'acabamento' => 'eva', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'ENT-EVA-CEU', 'nome' => 'EVA Céu', 'cor_hex' => '#BEE3F8', 'acabamento' => 'eva', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'ENT-AR-VISIVEL', 'nome' => 'Câmara de Ar Visível', 'cor_hex' => '#E8F6FF', 'acabamento' => 'ar', 'preco_adicional' => 49.90, 'padrao' => false],
            ],
        ],
        [
            'codigo' => 'solado',
            'nome' => 'Solado',
            'ordem' => 70,
            'camada_svg' => 'camada-solado',
            'componentes' => [
                ['codigo' => 'SOL-BORRACHA-LAVANDA', 'nome' => 'Borracha Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'borracha', 'preco_adicional' => 0.00, 'padrao' => true],
                ['codigo' => 'SOL-BORRACHA-MENTA', 'nome' => 'Borracha Menta', 'cor_hex' => '#C6F0DC', 'acabamento' => 'borracha', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'SOL-BORRACHA-GRAFITE', 'nome' => 'Borracha Grafite', 'cor_hex' => '#4A4458', 'acabamento' => 'borracha', 'preco_adicional' => 0.00, 'padrao' => false],
                ['codigo' => 'SOL-TRANSLUCIDO-GELO', 'nome' => 'Translúcido Gelo', 'cor_hex' => '#EAF4FB', 'acabamento' => 'translucido', 'preco_adicional' => 34.90, 'padrao' => false],
                ['codigo' => 'SOL-LUA-HOLO', 'nome' => 'Lua Holográfico (edição limitada)', 'cor_hex' => '#E9E4FF', 'acabamento' => 'holografico', 'preco_adicional' => 89.90, 'padrao' => false],
            ],
        ],
    ];

    private const RULES = [
        [
            'codigo' => 'R01',
            'tipo' => 'bloqueia',
            'origem' => 'CAB-CAMURCA-BAUNILHA',
            'alvo_tipo' => 'componente',
            'alvo' => 'SOL-LUA-HOLO',
            'mensagem' => 'A cola do solado holográfico mancha a camurça. Escolha outro solado ou outro cabedal.',
        ],
        [
            'codigo' => 'R02',
            'tipo' => 'exige',
            'origem' => 'CAD-ELASTICO-MENTA',
            'alvo_tipo' => 'acabamento',
            'alvo' => 'trico',
            'mensagem' => 'O cadarço elástico só segura em cabedal de tricô.',
        ],
        [
            'codigo' => 'R03',
            'tipo' => 'bloqueia',
            'origem' => 'EMB-NUVEM-GLITTER',
            'alvo_tipo' => 'componente',
            'alvo' => 'BIQ-VERNIZ-ROSA',
            'mensagem' => 'Glitter com verniz pesa no visual. Fique com um dos dois.',
        ],
        [
            'codigo' => 'R04',
            'tipo' => 'exige',
            'origem' => 'ENT-AR-VISIVEL',
            'alvo_tipo' => 'componente',
            'alvo' => 'SOL-TRANSLUCIDO-GELO',
            'mensagem' => 'A câmara de ar só aparece com o solado translúcido.',
        ],
        [
            'codigo' => 'R05',
            'tipo' => 'bloqueia',
            'origem' => 'ENT-AR-VISIVEL',
            'alvo_tipo' => 'gravacao',
            'alvo' => '*',
            'mensagem' => 'Não dá para gravar sobre a câmara de ar.',
        ],
    ];

    public function getBySku(string $sku): array
    {
        if (!isset(self::PRODUCTS[$sku])) {
            throw new NoSuchEntityException(
                __('Product with SKU "%1" does not exist.', $sku)
            );
        }

        return [
            'sku' => $sku,
            ...self::PRODUCTS[$sku],
            'grupos' => self::GROUPS,
            'regras' => self::RULES,
        ];
    }
}
