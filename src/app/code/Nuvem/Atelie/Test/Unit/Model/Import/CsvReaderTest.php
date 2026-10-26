<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Import;

use InvalidArgumentException;
use Nuvem\Atelie\Model\Import\CsvReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvReaderTest extends TestCase
{
    private CsvReader $csvReader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->csvReader = new CsvReader();
        $this->tempDir = sys_get_temp_dir() . '/atelie_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testReadValidCsvWithCommaDelimiter(): void
    {
        $filePath = $this->tempDir . '/valid.csv';
        $content = "codigo,grupo,nome,cor_hex,acabamento,preco_adicional,estoque,padrao\n"
            . "CAB-TELA-MENTA,cabedal,Tela Menta,#C6F0DC,tela,0.00,200,1\n"
            . "CAB-TRICO-PESSEGO,cabedal,Tricô Pêssego,#FFD8C2,trico,39.90,80,0\n";
        file_put_contents($filePath, $content);

        $rows = $this->csvReader->read($filePath);

        $this->assertCount(2, $rows);
        $this->assertSame('CAB-TELA-MENTA', $rows[0]['codigo']);
        $this->assertSame('cabedal', $rows[0]['grupo']);
        $this->assertSame('#C6F0DC', $rows[0]['cor_hex']);
        $this->assertSame(2, $rows[0]['_line_number']);
        $this->assertSame(3, $rows[1]['_line_number']);
    }

    public function testReadValidCsvWithSemicolonDelimiter(): void
    {
        $filePath = $this->tempDir . '/semicolon.csv';
        $content = "codigo;grupo;nome;cor_hex;acabamento;preco_adicional;estoque;padrao\n"
            . "BIQ-LISA-CEU;biqueira;Lisa Céu;#BEE3F8;liso;0,00;200;1\n";
        file_put_contents($filePath, $content);

        $rows = $this->csvReader->read($filePath);

        $this->assertCount(1, $rows);
        $this->assertSame('BIQ-LISA-CEU', $rows[0]['codigo']);
        $this->assertSame('biqueira', $rows[0]['grupo']);
    }

    public function testReadCsvWithBom(): void
    {
        $filePath = $this->tempDir . '/bom.csv';
        $content = "\xEF\xBB\xBFcodigo,grupo,nome,cor_hex,acabamento,preco_adicional\n"
            . "CAL-LISO-ROSA,calcanhar,Liso Rosa,#F7C6E4,liso,0.00\n";
        file_put_contents($filePath, $content);

        $rows = $this->csvReader->read($filePath);

        $this->assertCount(1, $rows);
        $this->assertSame('CAL-LISO-ROSA', $rows[0]['codigo']);
    }

    public function testReadNonExistentFileThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não existe ou não pode ser lido');

        $this->csvReader->read($this->tempDir . '/non_existent.csv');
    }

    public function testReadEmptyFileThrowsException(): void
    {
        $filePath = $this->tempDir . '/empty.csv';
        file_put_contents($filePath, '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('está vazio');

        $this->csvReader->read($filePath);
    }

    public function testReadMissingRequiredHeadersThrowsException(): void
    {
        $filePath = $this->tempDir . '/missing_headers.csv';
        $content = "codigo,nome\nCAB-01,Teste\n";
        file_put_contents($filePath, $content);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não possui as colunas obrigatórias');

        $this->csvReader->read($filePath);
    }

    public function testReadMismatchedColumnCountThrowsException(): void
    {
        $filePath = $this->tempDir . '/mismatch.csv';
        $content = "codigo,grupo,nome,cor_hex,acabamento,preco_adicional\n"
            . "CAB-01,cabedal,Teste,#FFFFFF,tela\n"; // missing preco_adicional column
        file_put_contents($filePath, $content);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('possui 5 colunas, mas o cabeçalho define 6 colunas');

        $this->csvReader->read($filePath);
    }
}
