<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Nuvem\Atelie\Api\Data\ComponentInterface;

/**
 * Class Component
 *
 * Model representing a customization component.
 */
class Component extends AbstractModel implements ComponentInterface, IdentityInterface
{
    public const CACHE_TAG = 'nuvem_atelie_componente';

    public const STOREFRONT_CACHE_TAG_PREFIX = 'atelie_comp_';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'nuvem_atelie_componente';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Component::class);
    }

    /**
     * Get identities for cache
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $identities = [self::CACHE_TAG . '_' . $this->getId()];
        $codigo = $this->getCodigo();
        if ($codigo !== null && $codigo !== '') {
            $identities[] = self::STOREFRONT_CACHE_TAG_PREFIX . $codigo;
        }

        return $identities;
    }

    /**
     * Get componente ID
     *
     * @return int|null
     */
    public function getComponenteId(): ?int
    {
        $val = $this->getData(self::COMPONENTE_ID);
        return $val !== null ? (int) $val : null;
    }

    /**
     * Set componente ID
     *
     * @param int $componenteId
     * @return $this
     */
    public function setComponenteId(int $componenteId): ComponentInterface
    {
        return $this->setData(self::COMPONENTE_ID, $componenteId);
    }

    /**
     * Get group ID
     *
     * @return int|null
     */
    public function getGrupoId(): ?int
    {
        $val = $this->getData(self::GRUPO_ID);
        return $val !== null ? (int) $val : null;
    }

    /**
     * Set group ID
     *
     * @param int $grupoId
     * @return $this
     */
    public function setGrupoId(int $grupoId): ComponentInterface
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
    public function setCodigo(string $codigo): ComponentInterface
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
    public function setNome(string $nome): ComponentInterface
    {
        return $this->setData(self::NOME, $nome);
    }

    /**
     * Get hexadecimal color code
     *
     * @return string|null
     */
    public function getCorHex(): ?string
    {
        return $this->getData(self::COR_HEX);
    }

    /**
     * Set hexadecimal color code
     *
     * @param string $corHex
     * @return $this
     */
    public function setCorHex(string $corHex): ComponentInterface
    {
        return $this->setData(self::COR_HEX, $corHex);
    }

    /**
     * Get finish type
     *
     * @return string|null
     */
    public function getAcabamento(): ?string
    {
        return $this->getData(self::ACABAMENTO);
    }

    /**
     * Set finish type
     *
     * @param string $acabamento
     * @return $this
     */
    public function setAcabamento(string $acabamento): ComponentInterface
    {
        return $this->setData(self::ACABAMENTO, $acabamento);
    }

    /**
     * Get additional price
     *
     * @return float
     */
    public function getPrecoAdicional(): float
    {
        return (float) $this->getData(self::PRECO_ADICIONAL);
    }

    /**
     * Set additional price
     *
     * @param float $precoAdicional
     * @return $this
     */
    public function setPrecoAdicional(float $precoAdicional): ComponentInterface
    {
        return $this->setData(self::PRECO_ADICIONAL, $precoAdicional);
    }

    /**
     * Get stock quantity
     *
     * @return int
     */
    public function getEstoque(): int
    {
        return (int) $this->getData(self::ESTOQUE);
    }

    /**
     * Set stock quantity
     *
     * @param int $estoque
     * @return $this
     */
    public function setEstoque(int $estoque): ComponentInterface
    {
        return $this->setData(self::ESTOQUE, $estoque);
    }

    /**
     * Check if default component for group
     *
     * @return bool
     */
    public function isPadrao(): bool
    {
        return (bool) $this->getData(self::PADRAO);
    }

    /**
     * Set default flag
     *
     * @param bool|int $padrao
     * @return $this
     */
    public function setPadrao($padrao): ComponentInterface
    {
        return $this->setData(self::PADRAO, (bool) $padrao);
    }

    /**
     * Check if component is active
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
    public function setAtivo($ativo): ComponentInterface
    {
        return $this->setData(self::ATIVO, (bool) $ativo);
    }

    /**
     * Get display order
     *
     * @return int
     */
    public function getOrdem(): int
    {
        return (int) $this->getData(self::ORDEM);
    }

    /**
     * Set display order
     *
     * @param int $ordem
     * @return $this
     */
    public function setOrdem(int $ordem): ComponentInterface
    {
        return $this->setData(self::ORDEM, $ordem);
    }

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Set updated at timestamp
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): ComponentInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
