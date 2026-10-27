<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Model;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use InvalidArgumentException;
use JsonException;
use Magento\Framework\App\DeploymentConfig;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\AtelieStudio\Api\PreviewInterface;

/**
 * Server-side preview renderer and URL builder for configured sneakers.
 */
class Preview implements PreviewInterface
{
    // phpcs:disable Generic.Files.LineLength
    private const BASE_SVG = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 620 290" width="620" height="290">
<defs>
<style>
@import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@700&amp;display=swap');
</style>
</defs>
<g id="camada-sombra"><ellipse cx="314" cy="262" rx="262" ry="12" fill="#2E2A3B" opacity="0.10"/></g>
<g id="camada-solado" class="atelie-camada" data-grupo="solado"><path id="solado-cor" data-pintar="fill" d="M62,234 L562,234 Q570,246 552,252 L84,252 Q60,250 62,234 Z" fill="#DCCFF7" stroke="#2E2A3B" stroke-width="3" stroke-linejoin="round"/></g>
<g id="camada-entressola" class="atelie-camada" data-grupo="entressola"><path id="entressola-cor" data-pintar="fill" d="M64,212 L566,212 Q572,224 562,234 L62,234 Q57,223 64,212 Z" fill="#FBFAFF" stroke="#2E2A3B" stroke-width="3" stroke-linejoin="round"/></g>
<g id="camada-cabedal" class="atelie-camada" data-grupo="cabedal"><path id="cabedal-cor" data-pintar="fill" d="M66,212 C58,170 60,122 78,92 Q122,96 160,116 Q186,126 200,108 L214,78 Q226,70 237,82 L252,102 Q330,140 412,160 Q500,176 548,190 Q570,199 566,212 Z" fill="#C6F0DC" stroke="#2E2A3B" stroke-width="3" stroke-linejoin="round"/></g>
<g id="camada-calcanhar" class="atelie-camada" data-grupo="calcanhar"><path id="calcanhar-cor" data-pintar="fill" d="M66,212 C58,170 60,122 78,92 Q100,93 120,101 Q108,156 144,212 Z" fill="#F7C6E4" stroke="#2E2A3B" stroke-width="3" stroke-linejoin="round"/></g>
<g id="camada-biqueira" class="atelie-camada" data-grupo="biqueira"><path id="biqueira-cor" data-pintar="fill" d="M474,170 Q512,178 548,190 Q570,199 566,212 L466,212 Q452,190 474,170 Z" fill="#BEE3F8" stroke="#2E2A3B" stroke-width="3" stroke-linejoin="round"/></g>
<g id="camada-emblema" class="atelie-camada" data-grupo="emblema"><path id="emblema-cor" data-pintar="fill" d="M258,198 a14,14 0 0,1 6,-27 a21,21 0 0,1 39,-8 a16,16 0 0,1 26,12 a13,13 0 0,1 -3,23 Z" fill="#FFD8C2" stroke="#2E2A3B" stroke-width="3" stroke-linejoin="round"/></g>
<g id="camada-cadarco" class="atelie-camada" data-grupo="cadarco"><g stroke="#2E2A3B" stroke-width="9" stroke-linecap="round"><line x1="262" y1="114" x2="288" y2="100"/><line x1="291" y1="126" x2="317" y2="112"/><line x1="320" y1="137" x2="346" y2="124"/><line x1="349" y1="147" x2="375" y2="135"/><line x1="378" y1="156" x2="404" y2="145"/></g><g id="cadarco-cor" data-pintar="stroke" stroke="#FFFFFF" stroke-width="5.5" stroke-linecap="round"><line x1="262" y1="114" x2="288" y2="100"/><line x1="291" y1="126" x2="317" y2="112"/><line x1="320" y1="137" x2="346" y2="124"/><line x1="349" y1="147" x2="375" y2="135"/><line x1="378" y1="156" x2="404" y2="145"/></g></g>
<g id="camada-detalhes" fill="none" stroke="#2E2A3B" stroke-linecap="round"><path d="M86,104 Q124,108 158,126" stroke-width="1.6" stroke-dasharray="5 5" opacity="0.55"/><path d="M120,104 Q112,156 146,206" stroke-width="1.6" stroke-dasharray="5 5" opacity="0.55"/><path d="M478,176 Q458,192 470,206" stroke-width="1.6" stroke-dasharray="5 5" opacity="0.55"/><line x1="96" y1="240" x2="106" y2="248" stroke-width="2" opacity="0.35"/><line x1="122" y1="240" x2="132" y2="248" stroke-width="2" opacity="0.35"/><line x1="148" y1="240" x2="158" y2="248" stroke-width="2" opacity="0.35"/><line x1="174" y1="240" x2="184" y2="248" stroke-width="2" opacity="0.35"/><line x1="200" y1="240" x2="210" y2="248" stroke-width="2" opacity="0.35"/><line x1="226" y1="240" x2="236" y2="248" stroke-width="2" opacity="0.35"/><line x1="252" y1="240" x2="262" y2="248" stroke-width="2" opacity="0.35"/><line x1="278" y1="240" x2="288" y2="248" stroke-width="2" opacity="0.35"/><line x1="304" y1="240" x2="314" y2="248" stroke-width="2" opacity="0.35"/><line x1="330" y1="240" x2="340" y2="248" stroke-width="2" opacity="0.35"/><line x1="356" y1="240" x2="366" y2="248" stroke-width="2" opacity="0.35"/><line x1="382" y1="240" x2="392" y2="248" stroke-width="2" opacity="0.35"/><line x1="408" y1="240" x2="418" y2="248" stroke-width="2" opacity="0.35"/><line x1="434" y1="240" x2="444" y2="248" stroke-width="2" opacity="0.35"/><line x1="460" y1="240" x2="470" y2="248" stroke-width="2" opacity="0.35"/><line x1="486" y1="240" x2="496" y2="248" stroke-width="2" opacity="0.35"/><line x1="512" y1="240" x2="522" y2="248" stroke-width="2" opacity="0.35"/><line x1="538" y1="240" x2="548" y2="248" stroke-width="2" opacity="0.35"/></g>
<g id="camada-ilhoses" fill="#FBFAFF" stroke="#2E2A3B" stroke-width="2"><circle cx="260" cy="117" r="3.2"/><circle cx="289" cy="129" r="3.2"/><circle cx="318" cy="140" r="3.2"/><circle cx="347" cy="150" r="3.2"/><circle cx="376" cy="159" r="3.2"/></g>
<g id="camada-gravacao"><text id="gravacao-texto" x="96" y="228" font-family="Quicksand, Quicksand Light, Nunito, sans-serif" font-size="13" font-weight="700" letter-spacing="2.5" fill="#2E2A3B"></text></g>
</svg>
SVG;
    // phpcs:enable Generic.Files.LineLength

    /**
     * @param DeploymentConfig $deploymentConfig
     * @param GroupRepositoryInterface $groupRepository
     * @param ComponentRepositoryInterface $componentRepository
     */
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly ComponentRepositoryInterface $componentRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getUrl(string $serializedConfiguration): string
    {
        return '/atelie/preview/svg?c=' . rawurlencode($serializedConfiguration)
            . '&h=' . $this->generateHmac($serializedConfiguration);
    }

    /**
     * Generate the HMAC SHA256 signature for a serialized configuration string.
     *
     * @param string $serializedConfiguration
     * @return string
     */
    public function generateHmac(string $serializedConfiguration): string
    {
        return hash_hmac('sha256', $serializedConfiguration, $this->getCryptKey());
    }

    /**
     * Validate an HMAC SHA256 signature against the serialized configuration.
     *
     * @param string $serializedConfiguration
     * @param string $hmac
     * @return bool
     */
    public function isValidHmac(string $serializedConfiguration, string $hmac): bool
    {
        if ($hmac === '') {
            return false;
        }

        return hash_equals($this->generateHmac($serializedConfiguration), $hmac);
    }

    /**
     * @inheritdoc
     */
    public function renderSvg(string $serializedConfiguration): string
    {
        $configuration = $this->parseAndValidateConfiguration($serializedConfiguration);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $loaded = $dom->loadXML(self::BASE_SVG, LIBXML_NONET);
        if (!$loaded) {
            throw new InvalidArgumentException('Could not parse base SVG template.');
        }

        $xpath = new DOMXPath($dom);

        $this->paintSelections($dom, $xpath, $configuration['selecoes']);
        $this->applyLaserEngraving($dom, $xpath, $configuration);

        $output = $dom->saveXML($dom->documentElement);
        if ($output === false) {
            throw new InvalidArgumentException('Failed to generate SVG output.');
        }

        return $output;
    }

    /**
     * Parse and validate the serialized configuration string.
     *
     * @param string $serializedConfiguration
     * @return array
     * @phpstan-return array{
     *     tamanho: int,
     *     gravacao: string|null,
     *     selecoes: list<array{grupo: string, componente: string}>
     * }
     * @throws InvalidArgumentException
     */
    private function parseAndValidateConfiguration(string $serializedConfiguration): array
    {
        try {
            $data = json_decode($serializedConfiguration, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'The serialized configuration is not valid JSON.',
                0,
                $exception
            );
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('The serialized configuration must be a JSON object.');
        }

        if (!isset($data['selecoes']) || !is_array($data['selecoes'])) {
            throw new InvalidArgumentException('The serialized configuration must contain a "selecoes" array.');
        }

        return [
            'tamanho' => (int) ($data['tamanho'] ?? 0),
            'gravacao' => isset($data['gravacao']) && $data['gravacao'] !== null ? (string) $data['gravacao'] : null,
            'selecoes' => $data['selecoes'],
        ];
    }

    /**
     * Paint each configured layer with the corresponding component hex color.
     *
     * @param DOMDocument $dom
     * @param DOMXPath $xpath
     * @param array $selections
     * @phpstan-param array<int, array{grupo?: string, componente?: string}> $selections
     * @return void
     * @throws InvalidArgumentException
     */
    private function paintSelections(DOMDocument $dom, DOMXPath $xpath, array $selections): void
    {
        foreach ($selections as $selection) {
            if (!is_array($selection)
                || empty($selection['grupo'])
                || empty($selection['componente'])
            ) {
                continue;
            }

            $groupCode = (string) $selection['grupo'];
            $componentCode = (string) $selection['componente'];

            $camadaSvg = $this->resolveCamadaSvg($groupCode);
            $corHex = $this->resolveComponentColor($componentCode);

            $elements = $xpath->query(
                "//*[@id='{$camadaSvg}']//*[@data-pintar] | //*[@id='{$camadaSvg}'][@data-pintar]"
            );

            if ($elements !== false) {
                /** @var DOMElement $element */
                foreach ($elements as $element) {
                    if (!$element instanceof DOMElement) {
                        continue;
                    }

                    $mode = $element->getAttribute('data-pintar');
                    if ($mode === 'fill') {
                        $element->setAttribute('fill', $corHex);
                    } elseif ($mode === 'stroke') {
                        $element->setAttribute('stroke', $corHex);
                    }
                }
            }
        }
    }

    /**
     * Resolve the SVG layer identifier for a given group code.
     *
     * @param string $groupCode
     * @return string
     */
    private function resolveCamadaSvg(string $groupCode): string
    {
        try {
            $group = $this->groupRepository->getByCodigo($groupCode);
            $camada = (string) $group->getCamadaSvg();
            if ($camada !== '') {
                return $camada;
            }
        } catch (Exception $exception) {
            unset($exception);
        }

        return 'camada-' . $groupCode;
    }

    /**
     * Resolve the hexadecimal color for a given component code.
     *
     * @param string $componentCode
     * @return string
     * @throws InvalidArgumentException
     */
    private function resolveComponentColor(string $componentCode): string
    {
        try {
            $component = $this->componentRepository->getByCodigo($componentCode);
            $corHex = (string) $component->getCorHex();
            if ($corHex !== '') {
                return $corHex;
            }
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                sprintf('Component "%s" could not be loaded.', $componentCode),
                0,
                $exception
            );
        }

        throw new InvalidArgumentException(
            sprintf('Component "%s" does not have a valid hex color.', $componentCode)
        );
    }

    /**
     * Apply laser engraving text onto the dedicated text node.
     *
     * @param DOMDocument $dom
     * @param DOMXPath $xpath
     * @param array $configuration
     * @phpstan-param array<string, mixed> $configuration
     * @return void
     */
    private function applyLaserEngraving(DOMDocument $dom, DOMXPath $xpath, array $configuration): void
    {
        $engraving = isset($configuration['gravacao']) ? (string) $configuration['gravacao'] : '';
        $textNodes = $xpath->query('//*[@id="gravacao-texto"]');

        if ($textNodes !== false && $textNodes->length > 0) {
            /** @var DOMElement $textNode */
            $textNode = $textNodes->item(0);

            while ($textNode->firstChild !== null) {
                $textNode->removeChild($textNode->firstChild);
            }

            if ($engraving !== '') {
                $textNode->appendChild($dom->createTextNode($engraving));
            }
        }
    }

    /**
     * Retrieve the native Magento encryption key.
     *
     * @return string
     */
    private function getCryptKey(): string
    {
        $key = (string) $this->deploymentConfig->get('crypt/key');
        if ($key === '') {
            $key = 'nuvem-atelie-default-crypt-key';
        }

        $parts = preg_split('/\s+/', trim($key));
        return !empty($parts[0]) ? $parts[0] : $key;
    }
}
