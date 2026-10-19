<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Nuvem\Atelie\Api\Data\RuleInterface;

/**
 * Class Rule
 *
 * Model representing a customization rule.
 */
class Rule extends AbstractModel implements RuleInterface, IdentityInterface
{
    public const CACHE_TAG = 'nuvem_atelie_regra';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'nuvem_atelie_regra';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Rule::class);
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
     * Get regra ID
     *
     * @return int|null
     */
    public function getRegraId(): ?int
    {
        $val = $this->getData(self::REGRA_ID);
        return $val !== null ? (int) $val : null;
    }

    /**
     * Set regra ID
     *
     * @param int $regraId
     * @return $this
     */
    public function setRegraId(int $regraId): RuleInterface
    {
        return $this->setData(self::REGRA_ID, $regraId);
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
    public function setCodigo(string $codigo): RuleInterface
    {
        return $this->setData(self::CODIGO, $codigo);
    }

    /**
     * Get rule type (bloqueia or exige)
     *
     * @return string|null
     */
    public function getTipo(): ?string
    {
        return $this->getData(self::TIPO);
    }

    /**
     * Set rule type (bloqueia or exige)
     *
     * @param string $tipo
     * @return $this
     */
    public function setTipo(string $tipo): RuleInterface
    {
        return $this->setData(self::TIPO, $tipo);
    }

    /**
     * Get origin component ID
     *
     * @return int|null
     */
    public function getOrigemComponenteId(): ?int
    {
        $val = $this->getData(self::ORIGEM_COMPONENTE_ID);
        return $val !== null ? (int) $val : null;
    }

    /**
     * Set origin component ID
     *
     * @param int $origemComponenteId
     * @return $this
     */
    public function setOrigemComponenteId(int $origemComponenteId): RuleInterface
    {
        return $this->setData(self::ORIGEM_COMPONENTE_ID, $origemComponenteId);
    }

    /**
     * Get target type (componente, acabamento, gravacao)
     *
     * @return string|null
     */
    public function getAlvoTipo(): ?string
    {
        return $this->getData(self::ALVO_TIPO);
    }

    /**
     * Set target type (componente, acabamento, gravacao)
     *
     * @param string $alvoTipo
     * @return $this
     */
    public function setAlvoTipo(string $alvoTipo): RuleInterface
    {
        return $this->setData(self::ALVO_TIPO, $alvoTipo);
    }

    /**
     * Get target value (component code, finish name or *)
     *
     * @return string|null
     */
    public function getAlvo(): ?string
    {
        return $this->getData(self::ALVO);
    }

    /**
     * Set target value (component code, finish name or *)
     *
     * @param string $alvo
     * @return $this
     */
    public function setAlvo(string $alvo): RuleInterface
    {
        return $this->setData(self::ALVO, $alvo);
    }

    /**
     * Get message
     *
     * @return string|null
     */
    public function getMensagem(): ?string
    {
        return $this->getData(self::MENSAGEM);
    }

    /**
     * Set message
     *
     * @param string $mensagem
     * @return $this
     */
    public function setMensagem(string $mensagem): RuleInterface
    {
        return $this->setData(self::MENSAGEM, $mensagem);
    }

    /**
     * Check if rule is active
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
    public function setAtivo($ativo): RuleInterface
    {
        return $this->setData(self::ATIVO, (bool) $ativo);
    }
}
