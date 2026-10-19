<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Interface ComponentInterface
 *
 * Represents a customization component in Atelie Nuvem.
 *
 * @api
 */
interface ComponentInterface
{
    public const COMPONENTE_ID = 'componente_id';
    public const GRUPO_ID = 'grupo_id';
    public const CODIGO = 'codigo';
    public const NOME = 'nome';
    public const COR_HEX = 'cor_hex';
    public const ACABAMENTO = 'acabamento';
    public const PRECO_ADICIONAL = 'preco_adicional';
    public const ESTOQUE = 'estoque';
    public const PADRAO = 'padrao';
    public const ATIVO = 'ativo';
    public const ORDEM = 'ordem';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get component ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get componente ID
     *
     * @return int|null
     */
    public function getComponenteId(): ?int;

    /**
     * Set componente ID
     *
     * @param int $componenteId
     * @return $this
     */
    public function setComponenteId(int $componenteId): self;

    /**
     * Get group ID
     *
     * @return int|null
     */
    public function getGrupoId(): ?int;

    /**
     * Set group ID
     *
     * @param int $grupoId
     * @return $this
     */
    public function setGrupoId(int $grupoId): self;

    /**
     * Get code
     *
     * @return string|null
     */
    public function getCodigo(): ?string;

    /**
     * Set code
     *
     * @param string $codigo
     * @return $this
     */
    public function setCodigo(string $codigo): self;

    /**
     * Get name
     *
     * @return string|null
     */
    public function getNome(): ?string;

    /**
     * Set name
     *
     * @param string $nome
     * @return $this
     */
    public function setNome(string $nome): self;

    /**
     * Get hexadecimal color code
     *
     * @return string|null
     */
    public function getCorHex(): ?string;

    /**
     * Set hexadecimal color code
     *
     * @param string $corHex
     * @return $this
     */
    public function setCorHex(string $corHex): self;

    /**
     * Get finish type
     *
     * @return string|null
     */
    public function getAcabamento(): ?string;

    /**
     * Set finish type
     *
     * @param string $acabamento
     * @return $this
     */
    public function setAcabamento(string $acabamento): self;

    /**
     * Get additional price
     *
     * @return float
     */
    public function getPrecoAdicional(): float;

    /**
     * Set additional price
     *
     * @param float $precoAdicional
     * @return $this
     */
    public function setPrecoAdicional(float $precoAdicional): self;

    /**
     * Get stock quantity
     *
     * @return int
     */
    public function getEstoque(): int;

    /**
     * Set stock quantity
     *
     * @param int $estoque
     * @return $this
     */
    public function setEstoque(int $estoque): self;

    /**
     * Check if default component for group
     *
     * @return bool
     */
    public function isPadrao(): bool;

    /**
     * Set default flag
     *
     * @param bool|int $padrao
     * @return $this
     */
    public function setPadrao($padrao): self;

    /**
     * Check if component is active
     *
     * @return bool
     */
    public function isAtivo(): bool;

    /**
     * Set active status
     *
     * @param bool|int $ativo
     * @return $this
     */
    public function setAtivo($ativo): self;

    /**
     * Get display order
     *
     * @return int
     */
    public function getOrdem(): int;

    /**
     * Set display order
     *
     * @param int $ordem
     * @return $this
     */
    public function setOrdem(int $ordem): self;

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at timestamp
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
