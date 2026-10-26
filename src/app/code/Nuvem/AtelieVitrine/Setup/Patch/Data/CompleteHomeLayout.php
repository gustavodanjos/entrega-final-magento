<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class CompleteHomeLayout implements DataPatchInterface
{
    private const ORIGINAL_HERO_HASH = '4d10490b2f71acdd773603b492d852fcfe4af3ed49c4875ec4e0b9be2729dddb';
    private const SHOWCASE_IDENTIFIER = 'nuvem-criacoes-home';

    /**
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     * @param BlockInterfaceFactory $blockFactory
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     * @param PageRepositoryInterface $pageRepository
     */
    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->saveBlock('nuvem-hero-home', 'Nuvem — Hero da home', $this->getHeroContent(), true);
        $this->saveBlock('nuvem-menu', 'Nuvem — Navegação principal', $this->getNavigationContent());
        $this->saveBlock(self::SHOWCASE_IDENTIFIER, 'Nuvem — Criações da home', $this->getShowcaseContent());
        $this->addShowcaseToHome();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [AddHomeHeroCms::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Preserve existing editorial blocks and only migrate the known default hero.
     *
     * @param string $identifier
     * @param string $title
     * @param string $content
     * @param bool $updateHero
     * @return void
     */
    private function saveBlock(string $identifier, string $title, string $content, bool $updateHero = false): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
            if (!$updateHero || hash('sha256', trim((string) $block->getContent())) !== self::ORIGINAL_HERO_HASH) {
                return;
            }
        } catch (NoSuchEntityException) {
            $block = $this->blockFactory->create();
            $block->setIdentifier($identifier);
            $block->setTitle($title);
            $block->setIsActive(true);
            $block->setStores([Store::DEFAULT_STORE_ID]);
        }

        $block->setContent($content);
        $this->blockRepository->save($block);
    }

    /**
     * Insert the showcase without replacing other content on the home page.
     *
     * @return void
     */
    private function addShowcaseToHome(): void
    {
        $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        $content = (string) $page->getContent();
        if (str_contains($content, self::SHOWCASE_IDENTIFIER)) {
            return;
        }

        $directive = '{{block class="Magento\\Cms\\Block\\BlockByIdentifier" identifier="'
            . self::SHOWCASE_IDENTIFIER . '"}}';
        $heroDirective = '{{block class="Magento\\Cms\\Block\\BlockByIdentifier" identifier="nuvem-hero-home"}}';
        $page->setContent(str_contains($content, $heroDirective)
            ? str_replace($heroDirective, $heroDirective . PHP_EOL . $directive, $content)
            : $content . PHP_EOL . $directive);
        $this->pageRepository->save($page);
    }

    /**
     * Keep future page destinations editable without exposing broken links.
     *
     * @return string
     */
    private function getNavigationContent(): string
    {
        return <<<'HTML'
<li class="level0 level-top">
    <a class="level-top" href="{{store direct_url='nuvem-runner.html'}}"><span>Ateliê</span></a>
</li>
<li class="level0 level-top">
    <a class="level-top" href="{{store direct_url=''}}#criacoes"><span>Galeria</span></a>
</li>
<li class="level0 level-top">
    <a class="level-top" href="{{store direct_url='catalogsearch/result/'}}?q=nuvem"><span>Modelos</span></a>
</li>
<li class="level0 level-top">
    <span class="level-top nuvem-navigation__pending" aria-disabled="true" title="Em breve">Como é feito</span>
</li>
HTML;
    }

    /**
     * Seed the brand composition as editable CMS markup.
     *
     * @return string
     */
    private function getHeroContent(): string
    {
        return <<<'HTML'
<section class="nuvem-hero" aria-labelledby="nuvem-hero-title">
    <div class="nuvem-hero__texto">
        <span class="nuvem-hero__cloud" aria-hidden="true"></span>
        <h1 class="nuvem-hero__titulo" id="nuvem-hero-title">Seu tênis.<br><span>Do seu jeito.</span></h1>
        <p class="nuvem-hero__resumo">Sete partes, trinta e uma escolhas, um par feito à mão.</p>
        <div class="nuvem-hero__actions">
            <a class="nuvem-hero__cta action primary" href="{{store direct_url='nuvem-runner.html'}}">Criar o meu</a>
            <a class="nuvem-hero__cta nuvem-hero__secondary" href="#criacoes">Ver a galeria</a>
        </div>
    </div>
    <img class="nuvem-hero__imagem" src="{{view url='images/home/criacao-algodao-doce.svg'}}"
         alt="Tênis Nuvem rosa com detalhes lavanda e azul" width="620" height="290">
</section>
HTML;
    }

    /**
     * Use official editorial examples until the F5.4 widget is available.
     *
     * @return string
     */
    private function getShowcaseContent(): string
    {
        return <<<'HTML'
<section class="nuvem-showcase" id="criacoes" aria-labelledby="nuvem-showcase-title">
    <div class="nuvem-showcase__heading">
        <h2 id="nuvem-showcase-title">Criações da semana</h2>
        <p>Cores para inspirar o seu próximo par.</p>
    </div>
    <ul class="nuvem-showcase__grid">
        <li class="nuvem-creation">
            <div class="nuvem-creation__image nuvem-creation__image--rose">
                <img src="{{view url='images/home/criacao-algodao-doce.svg'}}"
                     alt="Tênis rosa com detalhes lavanda e azul"
                     width="620" height="290" loading="lazy">
            </div>
            <h3>Algodão-doce</h3>
            <p>Inspiração Nuvem</p>
        </li>
        <li class="nuvem-creation">
            <div class="nuvem-creation__image nuvem-creation__image--mint">
                <img src="{{view url='images/home/criacao-jardim.svg'}}"
                     alt="Tênis da coleção Jardim"
                     width="620" height="290" loading="lazy">
            </div>
            <h3>Jardim</h3>
            <p>Inspiração Nuvem</p>
        </li>
        <li class="nuvem-creation">
            <div class="nuvem-creation__image nuvem-creation__image--lavender">
                <img src="{{view url='images/home/criacao-lua.svg'}}"
                     alt="Tênis da coleção Lua cheia"
                     width="620" height="290" loading="lazy">
            </div>
            <h3>Lua cheia</h3>
            <p>Inspiração Nuvem</p>
        </li>
        <li class="nuvem-creation">
            <div class="nuvem-creation__image nuvem-creation__image--vanilla">
                <img src="{{view url='images/home/criacao-valida.svg'}}"
                     alt="Tênis Nuvem com combinação pastel"
                     width="620" height="290" loading="lazy">
            </div>
            <h3>Tons de nuvem</h3>
            <p>Inspiração Nuvem</p>
        </li>
    </ul>
</section>
HTML;
    }
}
