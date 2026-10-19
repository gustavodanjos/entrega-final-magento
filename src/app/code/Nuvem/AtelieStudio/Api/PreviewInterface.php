<?php
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Api;

/**
 * Server-side image of a configured sneaker for cart, e-mail, gallery and Page Builder.
 *
 * @api
 */
interface PreviewInterface
{
    /**
     * Build the URL that renders the SVG of a configuration.
     *
     * The real implementation signs the configuration in the URL, so a tampered
     * URL never renders an image.
     *
     * @param string $serializedConfiguration configuration serialized by the ConfigurationSerializer
     * @return string URL of the preview
     */
    public function getUrl(string $serializedConfiguration): string;

    /**
     * Render the SVG of a configuration.
     *
     * On error no half-rendered image is returned: an unreadable configuration
     * fails loudly instead of silently painting a blank sneaker.
     *
     * @param string $serializedConfiguration configuration serialized by the ConfigurationSerializer
     * @return string SVG document (image/svg+xml)
     * @throws \InvalidArgumentException when the serialized configuration is not a JSON object
     */
    public function renderSvg(string $serializedConfiguration): string;
}
