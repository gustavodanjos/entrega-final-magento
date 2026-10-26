<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Nuvem\Atelie\Api\CatalogImporterInterface;
use Nuvem\Atelie\Api\Data\CatalogImportResultInterface;
use Nuvem\Atelie\Api\Data\CatalogImportRowErrorInterface;
use Nuvem\Atelie\Console\Command\ImportCatalogCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportCatalogCommandTest extends TestCase
{
    private CatalogImporterInterface|MockObject $catalogImporterMock;
    private DirectoryList|MockObject $directoryListMock;
    private ImportCatalogCommand $command;
    private CommandTester $commandTester;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->catalogImporterMock = $this->createMock(CatalogImporterInterface::class);
        $this->directoryListMock = $this->createMock(DirectoryList::class);

        $this->tempFile = tempnam(sys_get_temp_dir(), 'atelie_test_') ?: '/tmp/test.csv';
        file_put_contents($this->tempFile, "codigo,grupo,nome,cor_hex,acabamento,preco_adicional\n");

        $this->command = new ImportCatalogCommand(
            $this->catalogImporterMock,
            $this->directoryListMock
        );
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('atelie:catalogo:importar', $this->command->getName());
        $this->assertTrue($this->command->getDefinition()->hasArgument('arquivo'));
        $this->assertTrue($this->command->getDefinition()->getArgument('arquivo')->isRequired());
        $this->assertTrue($this->command->getDefinition()->hasOption('simular'));
    }

    public function testExecuteNonExistentFileReturnsFailure(): void
    {
        $this->directoryListMock->method('getRoot')->willReturn('/var/www/html');

        $exitCode = $this->commandTester->execute([
            'arquivo' => '/non/existent/file.csv',
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('não encontrado ou sem permissão de leitura', $this->commandTester->getDisplay());
    }

    public function testExecuteSimulationModeReturnsSuccess(): void
    {
        $resultMock = $this->createMock(CatalogImportResultInterface::class);
        $resultMock->method('isSuccessful')->willReturn(true);
        $resultMock->method('getCreatedCount')->willReturn(3);
        $resultMock->method('getUpdatedCount')->willReturn(10);
        $resultMock->method('getRefusedCount')->willReturn(0);
        $resultMock->method('getErrors')->willReturn([]);

        $this->catalogImporterMock->expects($this->once())
            ->method('import')
            ->with($this->tempFile, true)
            ->willReturn($resultMock);

        $exitCode = $this->commandTester->execute([
            'arquivo' => $this->tempFile,
            '--simular' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('[SIMULAÇÃO]', $display);
        $this->assertStringContainsString('Criados', $display);
        $this->assertStringContainsString('3', $display);
        $this->assertStringContainsString('Atualizados', $display);
        $this->assertStringContainsString('10', $display);
        $this->assertStringContainsString('Simulação concluída com sucesso', $display);
    }

    public function testExecuteWithValidationErrorsReturnsFailureAndPrintsReasons(): void
    {
        $errorMock = $this->createMock(CatalogImportRowErrorInterface::class);
        $errorMock->method('getRowNumber')->willReturn(4);
        $errorMock->method('getComponentCode')->willReturn('CAB-INVALID');
        $errorMock->method('getReason')->willReturn('A cor "#XYZ" é inválida.');

        $resultMock = $this->createMock(CatalogImportResultInterface::class);
        $resultMock->method('isSuccessful')->willReturn(false);
        $resultMock->method('getCreatedCount')->willReturn(0);
        $resultMock->method('getUpdatedCount')->willReturn(0);
        $resultMock->method('getRefusedCount')->willReturn(1);
        $resultMock->method('getErrors')->willReturn([$errorMock]);

        $this->catalogImporterMock->expects($this->once())
            ->method('import')
            ->with($this->tempFile, false)
            ->willReturn($resultMock);

        $exitCode = $this->commandTester->execute([
            'arquivo' => $this->tempFile,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('O arquivo contém erros e foi recusado por completo', $display);
        $this->assertStringContainsString('CAB-INVALID', $display);
        $this->assertStringContainsString('A cor "#XYZ" é inválida.', $display);
    }

    public function testExecuteSuccessfulImportReturnsSuccess(): void
    {
        $resultMock = $this->createMock(CatalogImportResultInterface::class);
        $resultMock->method('isSuccessful')->willReturn(true);
        $resultMock->method('getCreatedCount')->willReturn(5);
        $resultMock->method('getUpdatedCount')->willReturn(26);
        $resultMock->method('getRefusedCount')->willReturn(0);
        $resultMock->method('getErrors')->willReturn([]);

        $this->catalogImporterMock->expects($this->once())
            ->method('import')
            ->with($this->tempFile, false)
            ->willReturn($resultMock);

        $exitCode = $this->commandTester->execute([
            'arquivo' => $this->tempFile,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Catálogo importado e gravado com sucesso!', $display);
    }
}
