<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class CreateHowItsMadePage implements DataPatchInterface
{
    private const PAGE_IDENTIFIER = 'como-e-feito';

    /**
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     * @param PageInterfaceFactory $pageFactory
     * @param PageRepositoryInterface $pageRepository
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->createPageIfMissing();
        $this->activateNavigationLink();

        return $this;
    }

    /**
     * Preserve all editorial changes when the page already exists.
     *
     * @return void
     */
    private function createPageIfMissing(): void
    {
        try {
            $this->getPageByIdentifier->execute(self::PAGE_IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            $page = $this->pageFactory->create();
        }

        $page->setTitle('Como é feito');
        $page->setContentHeading('Como é feito');
        $page->setIdentifier(self::PAGE_IDENTIFIER);
        $page->setPageLayout('1column');
        $page->setIsActive(true);
        $page->setStores([Store::DEFAULT_STORE_ID]);
        $page->setContent($this->getPageContent());
        $this->pageRepository->save($page);
    }

    /**
     * Replace only the shipped placeholder so customized navigation remains intact.
     *
     * @return void
     */
    private function activateNavigationLink(): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute('nuvem-menu', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $content = (string) $block->getContent();
        $updatedContent = str_replace(
            '<span class="level-top nuvem-navigation__pending" aria-disabled="true" title="Em breve">'
                . 'Como é feito</span>',
            '<a class="level-top" href="{{store direct_url=\'como-e-feito\'}}">'
                . '<span>Como é feito</span></a>',
            $content
        );
        if ($content !== $updatedContent) {
            $block->setContent($updatedContent);
            $this->blockRepository->save($block);
        }
    }

    /**
     * Initial editorial content remains editable through the CMS page.
     *
     * @return string
     */
    private function getPageContent(): string
    {
        return <<<'HTML'
<article class="nuvem-how-its-made">
    <section class="nuvem-how-its-made__chapter nuvem-how-its-made__chapter--tinted"
             aria-labelledby="nuvem-story-title">
        <div class="nuvem-how-its-made__copy">
            <p class="nuvem-how-its-made__eyebrow">Nossa história</p>
            <h2 id="nuvem-story-title">De uma oficina em Franca, para o seu dia a dia</h2>
            <p>A Nuvem nasceu numa oficina de Franca, no interior de São Paulo. Entre materiais, ferramentas
            e ideias, encontramos nosso jeito de fazer tênis: à mão, com cores que parecem algodão-doce.</p>
            <p>Hoje, somos catorze artesãos. O trabalho das mãos continua no centro da nossa história.
            E cada par leva um pouco desse começo.</p>
        </div>
        <img class="nuvem-how-its-made__photo"
             src="{{view url='images/workshop/artisan-at-workbench.jpg'}}"
             alt="Artesão de avental segurando um tênis pastel na bancada da oficina"
             width="448" height="600">
    </section>
    <section class="nuvem-how-its-made__chapter nuvem-how-its-made__chapter--reversed"
             aria-labelledby="nuvem-choices-title">
        <div class="nuvem-how-its-made__copy">
            <p class="nuvem-how-its-made__eyebrow">Seu jeito, nossas mãos</p>
            <h2 id="nuvem-choices-title">Cada escolha conta um pouco de você</h2>
            <p>Tela, tricô, camurça. Tons suaves e detalhes para combinar do seu jeito. No estúdio, você
            escolhe as sete partes do tênis: cabedal, biqueira, calcanhar, cadarço, emblema, entressola e solado.</p>
            <p>Cada material tem suas particularidades. Por isso, o estúdio explica quais escolhas funcionam
            juntas, para que a sua ideia possa ganhar forma na nossa oficina.</p>
        </div>
        <img class="nuvem-how-its-made__photo"
             src="{{view url='images/workshop/artisan-at-sewing-machine.jpg'}}"
             alt="Artesão trabalhando em um tênis azul junto à máquina de costura"
             width="399" height="600" loading="lazy">
    </section>
    <section class="nuvem-how-its-made__chapter nuvem-how-its-made__chapter--tinted"
             aria-labelledby="nuvem-workshop-title">
        <div class="nuvem-how-its-made__copy">
            <p class="nuvem-how-its-made__eyebrow">Feito à mão em Franca</p>
            <h2 id="nuvem-workshop-title">Da sua criação à bancada dos artesãos</h2>
            <p>Depois da compra, suas escolhas chegam à equipe numa ficha de produção. Materiais, cores,
            tamanho e detalhes acompanham o pedido e orientam a fabricação do seu tênis.</p>
            <p>É aqui que a sua criação encontra as mãos de quem faz. Um par pensado por você,
            feito pela Nuvem e pronto para fazer parte da sua história.</p>
        </div>
        <img class="nuvem-how-its-made__photo"
             src="{{view url='images/workshop/artisan-assembling-shoe.jpg'}}"
             alt="Artesão usando uma ferramenta manual na montagem de um tênis na oficina"
             width="450" height="600" loading="lazy">
    </section>
    <section class="nuvem-how-its-made__closing" aria-labelledby="nuvem-your-pair-title">
        <h2 id="nuvem-your-pair-title">Agora, a sua história</h2>
        <p>Você conheceu um pouco da nossa oficina. Que tal trazer as suas ideias e criar um par
        com a sua identidade?</p>
        <a class="action primary nuvem-how-its-made__cta"
           href="{{store direct_url='nuvem-runner.html'}}">Criar o meu tênis</a>
    </section>
</article>
HTML;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [CompleteHomeLayout::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
