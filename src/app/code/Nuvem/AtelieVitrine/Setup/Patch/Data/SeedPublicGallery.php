<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\AtelieVitrine\Model\SavedCreationFactory;
use Nuvem\AtelieVitrine\Model\SavedCreationRepository;

class SeedPublicGallery implements DataPatchInterface
{
    private const HASHES = [
        '5a898583dd33f1826e9572d1f6d6cd61',
        '8a1a3bee65fcfb5686541134f32f688b',
        'ed45ef7847b99c6037b93c7f33530d66',
        'c908452deb99c99c8a514ce4f5c235d5',
        '5247535f24c4bd6481558e51db93e0c5',
        '5abae4d1e99469f5c59929564ec025c7',
        'bff063fa5ebeb54cabda94cde5566afd',
        '398395b4d4d02b2551f6ef8d712ddefb',
        '37a56e2b5feefe13e07b5d733df13d36',
        'a0f6b300c2afa5cc9d15355d912cdb65',
        '60edcb0b39bba67c9bd42f9d17370d0e',
        'd7ae40780a32f538544df3bbeeda2a27',
        'b7ef2c2dfaba2cee3454d7f8be03a166',
        '0a11f815ad7a9add8f7519aec6853b0f',
        '1c410aa7842f1db76029fa901524fd55',
        '769637837641ef8032525dd2afc19c60'
    ];

    private const NAMES = [
        'Algodão-doce', 'Lua cheia', 'Jardim', 'Brisa',
        'Aurora', 'Céu de abril', 'Doce encontro', 'Luar',
        'Primavera', 'Maré mansa', 'Abraço', 'Constelação',
        'Pôr do sol', 'Dia de nuvem', 'Flor de algodão', 'Caminho de luz'
    ];

    /**
     * Initialize dependencies.
     *
     * @param SavedCreationFactory $creationFactory
     * @param SavedCreationRepository $repository
     * @param ConfigurationSerializer $serializer
     * @param ConfigurationValidatorInterface $validator
     */
    public function __construct(
        private readonly SavedCreationFactory $creationFactory,
        private readonly SavedCreationRepository $repository,
        private readonly ConfigurationSerializer $serializer,
        private readonly ConfigurationValidatorInterface $validator
    ) {
    }

    /**
     * Apply the data migration.
     *
     * @return self
     */
    public function apply(): self
    {
        $palettes = [[
            ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
            ['grupo' => 'biqueira', 'componente' => 'BIQ-LISA-CEU'],
            ['grupo' => 'calcanhar', 'componente' => 'CAL-ACOLCHOADO-CEU'],
            ['grupo' => 'cadarco', 'componente' => 'CAD-ELASTICO-MENTA'],
            ['grupo' => 'emblema', 'componente' => 'EMB-NUVEM-PESSEGO'],
            ['grupo' => 'entressola', 'componente' => 'ENT-EVA-NUVEM'],
            ['grupo' => 'solado', 'componente' => 'SOL-TRANSLUCIDO-GELO']
        ],
        [
            ['grupo' => 'cabedal', 'componente' => 'CAB-TELA-CEU'],
            ['grupo' => 'biqueira', 'componente' => 'BIQ-LISA-NUVEM'],
            ['grupo' => 'calcanhar', 'componente' => 'CAL-LISO-LAVANDA'],
            ['grupo' => 'cadarco', 'componente' => 'CAD-ALGODAO-NUVEM'],
            ['grupo' => 'emblema', 'componente' => 'EMB-NUVEM-BAUNILHA'],
            ['grupo' => 'entressola', 'componente' => 'ENT-EVA-NUVEM'],
            ['grupo' => 'solado', 'componente' => 'SOL-LUA-HOLO']
        ]];
        foreach (self::NAMES as $index => $name) {
            $hash = self::HASHES[$index];
            if ($this->creationExists($hash)) {
                continue;
            }
            $configuration = $this->serializer->unserialize(json_encode([
                'tamanho' => 36 + ($index % 7),
                'gravacao' => null,
                'selecoes' => $palettes[$index % count($palettes)]
            ], JSON_THROW_ON_ERROR));
            $validation = $this->validator->validate('NUVEM-RUNNER', $configuration);
            if (!$validation->isValid()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Invalid gallery example: %1', implode('; ', $validation->getMessages()))
                );
            }
            $creation = $this->creationFactory->create();
            $creation->setData([
                'hash' => $hash,
                'customer_id' => null,
                'sku' => 'NUVEM-RUNNER',
                'nome' => $name,
                'configuracao' => $this->serializer->serialize($configuration),
                'publica' => 1,
                'curtidas' => ($index * 7) % 43,
                'created_at' => sprintf('2026-01-%02d 12:00:00', $index + 1)
            ]);
            $this->repository->save($creation);
        }
        return $this;
    }

    /**
     * Check whether the editorial example already exists.
     *
     * @param string $hash
     * @return bool
     */
    private function creationExists(string $hash): bool
    {
        try {
            $this->repository->getByHash($hash);
            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    /**
     * Declare prerequisite migrations.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [\Nuvem\Atelie\Setup\Patch\Data\CreateAtelieProducts::class];
    }

    /**
     * Return migration aliases.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
