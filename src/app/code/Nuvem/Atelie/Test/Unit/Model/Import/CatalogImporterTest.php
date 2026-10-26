<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\ComponentFactory;
use Nuvem\Atelie\Model\Import\CatalogImporter;
use Nuvem\Atelie\Model\Import\ComponentRowValidator;
use Nuvem\Atelie\Model\Import\CsvReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CatalogImporterTest extends TestCase
{
    private CsvReader|MockObject $csvReaderMock;
    private ComponentRowValidator $rowValidator;
    private ComponentRepositoryInterface|MockObject $componentRepositoryMock;
    private ComponentFactory|MockObject $componentFactoryMock;
    private ResourceConnection|MockObject $resourceConnectionMock;
    private AdapterInterface|MockObject $connectionMock;
    private LoggerInterface|MockObject $loggerMock;
    private CatalogImporter $importer;

    protected function setUp(): void
    {
        $this->csvReaderMock = $this->createMock(CsvReader::class);
        $this->rowValidator = new ComponentRowValidator();
        $this->componentRepositoryMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->componentFactoryMock = $this->createMock(ComponentFactory::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceConnectionMock->method('getTableName')->willReturnArgument(0);

        $this->importer = new CatalogImporter(
            $this->csvReaderMock,
            $this->rowValidator,
            $this->componentRepositoryMock,
            $this->componentFactoryMock,
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    public function testImportWithValidationErrorsRefusesEntireFileWithoutTransaction(): void
    {
        $filePath = '/dummy/path.csv';
        $rows = [
            [
                '_line_number' => 2,
                'codigo' => 'CAB-OK',
                'grupo' => 'cabedal',
                'nome' => 'Cabedal Ok',
                'cor_hex' => '#112233',
                'acabamento' => 'tela',
                'preco_adicional' => '0.00',
            ],
            [
                '_line_number' => 3,
                'codigo' => 'CAB-INVALID-COLOR',
                'grupo' => 'cabedal',
                'nome' => 'Cabedal Invalido',
                'cor_hex' => 'INVALID_COLOR',
                'acabamento' => 'tela',
                'preco_adicional' => '0.00',
            ],
        ];

        $this->csvReaderMock->expects($this->once())->method('read')->with($filePath)->willReturn($rows);

        // Group lookup query
        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchAll')->willReturn([
            ['grupo_id' => 1, 'codigo' => 'cabedal', 'nome' => 'Cabedal'],
        ]);

        // Transaction must NEVER be opened
        $this->connectionMock->expects($this->never())->method('beginTransaction');
        $this->componentRepositoryMock->expects($this->never())->method('save');

        $result = $this->importer->import($filePath, false);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $result->getCreatedCount());
        $this->assertSame(0, $result->getUpdatedCount());
        $this->assertSame(1, $result->getRefusedCount());
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('CAB-INVALID-COLOR', $result->getErrors()[0]->getComponentCode());
        $this->assertStringContainsString('A cor "INVALID_COLOR" é inválida', $result->getErrors()[0]->getReason());
    }

    public function testImportInSimulationModeDoesNotPersist(): void
    {
        $filePath = '/dummy/path.csv';
        $rows = [
            [
                '_line_number' => 2,
                'codigo' => 'CAB-NOVO',
                'grupo' => 'cabedal',
                'nome' => 'Cabedal Novo',
                'cor_hex' => '#112233',
                'acabamento' => 'tela',
                'preco_adicional' => '10.00',
            ],
            [
                '_line_number' => 3,
                'codigo' => 'CAB-EXISTENTE',
                'grupo' => 'cabedal',
                'nome' => 'Cabedal Existente',
                'cor_hex' => '#445566',
                'acabamento' => 'tela',
                'preco_adicional' => '20.00',
            ],
        ];

        $this->csvReaderMock->expects($this->once())->method('read')->with($filePath)->willReturn($rows);

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchAll')->willReturn([
            ['grupo_id' => 1, 'codigo' => 'cabedal', 'nome' => 'Cabedal'],
        ]);
        // Simulate CAB-EXISTENTE exists in database
        $this->connectionMock->method('fetchPairs')->willReturn([
            'CAB-EXISTENTE' => 10,
        ]);

        // Transaction and save must NEVER be called in simulation
        $this->connectionMock->expects($this->never())->method('beginTransaction');
        $this->componentRepositoryMock->expects($this->never())->method('save');

        $result = $this->importer->import($filePath, true);

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->isSimulated());
        $this->assertSame(1, $result->getCreatedCount());
        $this->assertSame(1, $result->getUpdatedCount());
        $this->assertSame(0, $result->getRefusedCount());
    }

    public function testImportSavesInTransactionAndCommits(): void
    {
        $filePath = '/dummy/path.csv';
        $rows = [
            [
                '_line_number' => 2,
                'codigo' => 'CAB-NOVO',
                'grupo' => 'cabedal',
                'nome' => 'Cabedal Novo',
                'cor_hex' => '#112233',
                'acabamento' => 'tela',
                'preco_adicional' => '10.00',
                'estoque' => '50',
                'padrao' => '1',
            ],
        ];

        $this->csvReaderMock->expects($this->once())->method('read')->with($filePath)->willReturn($rows);

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchAll')->willReturn([
            ['grupo_id' => 1, 'codigo' => 'cabedal', 'nome' => 'Cabedal'],
        ]);
        $this->connectionMock->method('fetchPairs')->willReturn([]); // No existing components

        $componentMock = $this->createMock(Component::class);
        $this->componentFactoryMock->expects($this->once())->method('create')->willReturn($componentMock);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->componentRepositoryMock->expects($this->once())->method('save')->with($componentMock);
        $this->connectionMock->expects($this->once())->method('commit');
        $this->connectionMock->expects($this->never())->method('rollBack');

        $result = $this->importer->import($filePath, false);

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->isSimulated());
        $this->assertSame(1, $result->getCreatedCount());
        $this->assertSame(0, $result->getUpdatedCount());
        $this->assertSame(0, $result->getRefusedCount());
    }

    public function testImportRollsBackOnSaveException(): void
    {
        $filePath = '/dummy/path.csv';
        $rows = [
            [
                '_line_number' => 2,
                'codigo' => 'CAB-NOVO',
                'grupo' => 'cabedal',
                'nome' => 'Cabedal Novo',
                'cor_hex' => '#112233',
                'acabamento' => 'tela',
                'preco_adicional' => '10.00',
            ],
        ];

        $this->csvReaderMock->method('read')->willReturn($rows);

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchAll')->willReturn([
            ['grupo_id' => 1, 'codigo' => 'cabedal', 'nome' => 'Cabedal'],
        ]);
        $this->connectionMock->method('fetchPairs')->willReturn([]);

        $componentMock = $this->createMock(Component::class);
        $this->componentFactoryMock->method('create')->willReturn($componentMock);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->componentRepositoryMock->method('save')->willThrowException(new RuntimeException('DB Error'));
        $this->connectionMock->expects($this->once())->method('rollBack');
        $this->connectionMock->expects($this->never())->method('commit');

        $result = $this->importer->import($filePath, false);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(1, $result->getRefusedCount());
        $this->assertStringContainsString('Erro na transação', $result->getErrors()[0]->getReason());
    }
}
