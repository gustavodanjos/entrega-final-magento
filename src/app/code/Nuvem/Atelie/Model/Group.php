<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Nuvem\Atelie\Api\Data\GroupInterface;

/**
 * Class Group
 *
 * Model representing a customization group.
 */
class Group extends AbstractModel implements GroupInterface, IdentityInterface
{
    public const CACHE_TAG = 'nuvem_atelie_grupo';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'nuvem_atelie_grupo';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Group::class);
    }

    /**
     * Get identities for cache
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get grupo ID
     *
     * @return int|null
     */
    public function getGrupoId(): ?int
    {
        $val = $this->getData(self::GRUPO_ID);
        return $val !== null ? (int) $val : null;
    }

    /**
     * Set grupo ID
     *
     * @param int $grupoId
     * @return $this
     */
    public function setGrupoId(int $grupoId): GroupInterface
    {
        return $this->setData(self::GRUPO_ID, $grupoId);
    }

    /**
     * Get code
     *
     * @return string|null
     */
    public function getCodigo(): ?string
    {
        return $this->getData(self::CODIGO);
    }

    /**
     * Set code
     *
     * @param string $codigo
     * @return $this
     */
    public function setCodigo(string $codigo): GroupInterface
    {
        return $this->setData(self::CODIGO, $codigo);
    }

    /**
     * Get name
     *
     * @return string|null
     */
    public function getNome(): ?string
    {
        return $this->getData(self::NOME);
    }

    /**
     * Set name
     *
     * @param string $nome
     * @return $this
     */
    public function setNome(string $nome): GroupInterface
    {
        return $this->setData(self::NOME, $nome);
    }

    /**
     * Get order
     *
     * @return int
     */
    public function getOrdem(): int
    {
        return (int) $this->getData(self::ORDEM);
    }

    /**
     * Set order
     *
     * @param int $ordem
     * @return $this
     */
    public function setOrdem(int $ordem): GroupInterface
    {
        return $this->setData(self::ORDEM, $ordem);
    }

    /**
     * Get SVG layer ID
     *
     * @return string|null
     */
    public function getCamadaSvg(): ?string
    {
        return $this->getData(self::CAMADA_SVG);
    }

    /**
     * Set SVG layer ID
     *
     * @param string $camadaSvg
     * @return $this
     */
    public function setCamadaSvg(string $camadaSvg): GroupInterface
    {
        return $this->setData(self::CAMADA_SVG, $camadaSvg);
    }

    /**
     * Check if group is active
     *
     * @return bool
     */
    public function isAtivo(): bool
    {
        return (bool) $this->getData(self::ATIVO);
    }

    /**
     * Set active status
     *
     * @param bool|int $ativo
     * @return $this
     */
    public function setAtivo($ativo): GroupInterface
    {
        return $this->setData(self::ATIVO, (bool) $ativo);
    }
}
