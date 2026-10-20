<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Interface RuleInterface
 *
 * Represents a customization rule in Atelie Nuvem.
 *
 * @api
 */
interface RuleInterface
{
    public const REGRA_ID = 'regra_id';
    public const CODIGO = 'codigo';
    public const TIPO = 'tipo';
    public const ORIGEM_COMPONENTE_ID = 'origem_componente_id';
    public const ALVO_TIPO = 'alvo_tipo';
    public const ALVO = 'alvo';
    public const MENSAGEM = 'mensagem';
    public const ATIVO = 'ativo';

    public const TIPO_BLOQUEIA = 'bloqueia';
    public const TIPO_EXIGE = 'exige';

    public const ALVO_TIPO_COMPONENTE = 'componente';
    public const ALVO_TIPO_ACABAMENTO = 'acabamento';
    public const ALVO_TIPO_GRAVACAO = 'gravacao';

    /**
     * Get rule ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get regra ID
     *
     * @return int|null
     */
    public function getRegraId(): ?int;

    /**
     * Set regra ID
     *
     * @param int $regraId
     * @return $this
     */
    public function setRegraId(int $regraId): self;

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
     * Get rule type (bloqueia or exige)
     *
     * @return string|null
     */
    public function getTipo(): ?string;

    /**
     * Set rule type (bloqueia or exige)
     *
     * @param string $tipo
     * @return $this
     */
    public function setTipo(string $tipo): self;

    /**
     * Get origin component ID
     *
     * @return int|null
     */
    public function getOrigemComponenteId(): ?int;

    /**
     * Set origin component ID
     *
     * @param int $origemComponenteId
     * @return $this
     */
    public function setOrigemComponenteId(int $origemComponenteId): self;

    /**
     * Get target type (componente, acabamento, gravacao)
     *
     * @return string|null
     */
    public function getAlvoTipo(): ?string;

    /**
     * Set target type (componente, acabamento, gravacao)
     *
     * @param string $alvoTipo
     * @return $this
     */
    public function setAlvoTipo(string $alvoTipo): self;

    /**
     * Get target value (component code, finish name or *)
     *
     * @return string|null
     */
    public function getAlvo(): ?string;

    /**
     * Set target value (component code, finish name or *)
     *
     * @param string $alvo
     * @return $this
     */
    public function setAlvo(string $alvo): self;

    /**
     * Get message
     *
     * @return string|null
     */
    public function getMensagem(): ?string;

    /**
     * Set message
     *
     * @param string $mensagem
     * @return $this
     */
    public function setMensagem(string $mensagem): self;

    /**
     * Check if rule is active
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
