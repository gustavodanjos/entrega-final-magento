<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetStock extends Command
{
    public function __construct(
        private readonly ComponentStockInterface $stock,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('atelie:estoque:definir')
            ->setDescription('Define o estoque de um componente e registra o ajuste no razão.')
            ->addArgument('codigo', InputArgument::REQUIRED, 'Código do componente, por exemplo SOL-LUA-HOLO')
            ->addArgument('qtd', InputArgument::REQUIRED, 'Quantidade inteira não negativa');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = trim((string) $input->getArgument('codigo'));
        $rawQty = (string) $input->getArgument('qtd');
        $qty = filter_var($rawQty, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 2147483647]
        ]);
        if ($code === '' || $qty === false) {
            $output->writeln('<error>Informe um código e uma quantidade inteira entre 0 e 2147483647.</error>');
            return Command::INVALID;
        }

        try {
            $this->stock->adjust($code, (int) $qty, 'Ajuste pelo comando atelie:estoque:definir');
            $output->writeln(sprintf(
                '<info>Estoque de %s definido para %d.</info>',
                OutputFormatter::escape($code),
                $qty
            ));
            return Command::SUCCESS;
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');
        } catch (\Throwable $exception) {
            $this->logger->error('Atelie: CLI stock adjustment failed.', [
                'component_code' => $code,
                'exception' => $exception
            ]);
            $output->writeln('<error>Não foi possível ajustar o estoque. Consulte os logs do Magento.</error>');
        }
        return Command::FAILURE;
    }
}
