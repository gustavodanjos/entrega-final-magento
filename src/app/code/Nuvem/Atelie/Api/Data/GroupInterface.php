<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Interface GroupInterface
 *
 * Represents a customization group in Atelie Nuvem.
 *
 * @api
 */
interface GroupInterface
{
    public const GRUPO_ID = 'grupo_id';
    public const CODIGO = 'codigo';
    public const NOME = 'nome';
    public const ORDEM = 'ordem';
    public const CAMADA_SVG = 'camada_svg';
    public const ATIVO = 'ativo';

    /**
     * Get group ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get grupo ID
     *
     * @return int|null
     */
    public function getGrupoId(): ?int;

    /**
     * Set grupo ID
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
     * Get order
     *
     * @return int
     */
    public function getOrdem(): int;

    /**
     * Set order
     *
     * @param int $ordem
     * @return $this
     */
    public function setOrdem(int $ordem): self;

    /**
     * Get SVG layer ID
     *
     * @return string|null
     */
    public function getCamadaSvg(): ?string;

    /**
     * Set SVG layer ID
     *
     * @param string $camadaSvg
     * @return $this
     */
    public function setCamadaSvg(string $camadaSvg): self;

    /**
     * Check if group is active
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
}
