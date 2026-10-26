<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Nuvem\Atelie\Api\CatalogImporterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportCatalogCommand
 *
 * Console command to import atelie catalog components from a CSV spreadsheet.
 * Syntax: bin/magento atelie:catalogo:importar <arquivo> [--simular]
 */
class ImportCatalogCommand extends Command
{
    private const ARG_FILE = 'arquivo';
    private const OPT_SIMULATE = 'simular';

    /**
     * @param CatalogImporterInterface $catalogImporter
     * @param DirectoryList $directoryList
     * @param string|null $name
     */
    public function __construct(
        private readonly CatalogImporterInterface $catalogImporter,
        private readonly DirectoryList $directoryList,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('atelie:catalogo:importar')
            ->setDescription('Importa componentes do catálogo do ateliê a partir de uma planilha CSV.')
            ->addArgument(
                self::ARG_FILE,
                InputArgument::REQUIRED,
                'Caminho para o arquivo CSV de componentes.'
            )
            ->addOption(
                self::OPT_SIMULATE,
                's',
                InputOption::VALUE_NONE,
                'Simula a importação, validando todas as linhas sem gravar nada no banco.'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawFilePath = trim((string) $input->getArgument(self::ARG_FILE));
        $simulate = (bool) $input->getOption(self::OPT_SIMULATE);

        $resolvedPath = $this->resolveFilePath($rawFilePath);

        if ($resolvedPath === null) {
            $output->writeln(sprintf(
                '<error>Arquivo "%s" não encontrado ou sem permissão de leitura.</error>',
                OutputFormatter::escape($rawFilePath)
            ));
            return Command::FAILURE;
        }

        if ($simulate) {
            $output->writeln('<comment>[SIMULAÇÃO] Modo de simulação ativado: nenhuma alteração será gravada.</comment>');
        }

        $result = $this->catalogImporter->import($resolvedPath, $simulate);

        $output->writeln('');
        $output->writeln('<comment>Resumo da importação:</comment>');

        $summaryTable = new Table($output);
        $summaryTable->setHeaders(['Status', 'Quantidade']);
        $summaryTable->addRow(['Criados', $result->getCreatedCount()]);
        $summaryTable->addRow(['Atualizados', $result->getUpdatedCount()]);
        $summaryTable->addRow(['Recusados', $result->getRefusedCount()]);
        $summaryTable->render();

        if (!$result->isSuccessful() || $result->getRefusedCount() > 0) {
            $output->writeln('');
            $output->writeln(
                '<error>O arquivo contém erros e foi recusado por completo. Nenhuma alteração foi gravada.</error>'
            );

            if (!empty($result->getErrors())) {
                $output->writeln('');
                $output->writeln('<comment>Motivos de recusa:</comment>');

                $errorTable = new Table($output);
                $errorTable->setHeaders(['Linha', 'Código', 'Motivo']);

                foreach ($result->getErrors() as $error) {
                    $errorTable->addRow([
                        $error->getRowNumber() > 0 ? (string) $error->getRowNumber() : '-',
                        $error->getComponentCode() ?: '-',
                        $error->getReason(),
                    ]);
                }

                $errorTable->render();
            }

            return Command::FAILURE;
        }

        $output->writeln('');
        if ($simulate) {
            $output->writeln('<info>Simulação concluída com sucesso! Nenhuma alteração foi realizada.</info>');
        } else {
            $output->writeln('<info>Catálogo importado e gravado com sucesso!</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * Resolve file path (supports relative to CWD, relative to Magento root, or absolute)
     *
     * @param string $filePath
     * @return string|null
     */
    private function resolveFilePath(string $filePath): ?string
    {
        if (file_exists($filePath) && is_readable($filePath)) {
            return realpath($filePath) ?: $filePath;
        }

        $cwdPath = getcwd() . DIRECTORY_SEPARATOR . $filePath;
        if (file_exists($cwdPath) && is_readable($cwdPath)) {
            return realpath($cwdPath) ?: $cwdPath;
        }

        $rootPath = $this->directoryList->getRoot() . DIRECTORY_SEPARATOR . $filePath;
        if (file_exists($rootPath) && is_readable($rootPath)) {
            return realpath($rootPath) ?: $rootPath;
        }

        return null;
    }
}
