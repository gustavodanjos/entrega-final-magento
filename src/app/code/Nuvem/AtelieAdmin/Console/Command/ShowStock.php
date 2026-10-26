<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowStock extends Command
{
    public function __construct(
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('atelie:estoque:ver')
            ->setDescription('Consulta o estoque dos componentes, com filtro opcional por código do grupo.')
            ->addOption('grupo', null, InputOption::VALUE_REQUIRED, 'Código do grupo, por exemplo solado');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $groupCode = $input->getOption('grupo');
            $criteria = clone $this->searchCriteriaBuilder;
            $groups = [];
            if ($groupCode !== null) {
                $groupCode = trim((string) $groupCode);
                if ($groupCode === '') {
                    $output->writeln('<error>Informe o código do grupo em --grupo.</error>');
                    return Command::INVALID;
                }
                $group = $this->groupRepository->getByCodigo($groupCode);
                $groupId = (int) $group->getGrupoId();
                $groups[$groupId] = (string) $group->getNome();
                $criteria->addFilter('grupo_id', $groupId);
            }
            $sort = $this->sortOrderBuilder->setField('codigo')->setAscendingDirection()->create();
            $items = $this->componentRepository->getList($criteria->addSortOrder($sort)->create())->getItems();
            $rows = [];
            foreach ($items as $component) {
                $groupId = (int) $component->getGrupoId();
                if (!isset($groups[$groupId])) {
                    $groups[$groupId] = (string) $this->groupRepository->getById($groupId)->getNome();
                }
                $rows[] = [
                    OutputFormatter::escape((string) $component->getCodigo()),
                    OutputFormatter::escape((string) $component->getNome()),
                    OutputFormatter::escape($groups[$groupId]),
                    $component->getEstoque(),
                    $component->isAtivo() ? 'Sim' : 'Não'
                ];
            }
            if (!$rows) {
                $output->writeln('<info>Nenhum componente encontrado.</info>');
                return Command::SUCCESS;
            }
            (new Table($output))
                ->setHeaders(['Código', 'Nome', 'Grupo', 'Estoque', 'Ativo'])
                ->setRows($rows)
                ->render();
            return Command::SUCCESS;
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');
        } catch (\Throwable $exception) {
            $this->logger->error('Atelie: CLI stock query failed.', ['exception' => $exception]);
            $output->writeln('<error>Não foi possível consultar o estoque. Consulte os logs do Magento.</error>');
        }
        return Command::FAILURE;
    }
}
