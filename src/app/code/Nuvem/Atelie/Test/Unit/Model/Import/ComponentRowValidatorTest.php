<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Import;

use Nuvem\Atelie\Model\Import\ComponentRowValidator;
use PHPUnit\Framework\TestCase;

class ComponentRowValidatorTest extends TestCase
{
    private ComponentRowValidator $validator;
    private array $availableGroups;

    protected function setUp(): void
    {
        $this->validator = new ComponentRowValidator();
        $this->availableGroups = [
            'cabedal' => 1,
            'biqueira' => 2,
            'solado' => 3,
        ];
    }

    public function testValidateValidRow(): void
    {
        $row = [
            '_line_number' => 2,
            'codigo' => 'CAB-TELA-MENTA',
            'grupo' => 'cabedal',
            'nome' => 'Tela Menta',
            'cor_hex' => '#c6f0dc',
            'acabamento' => 'tela',
            'preco_adicional' => '39,90',
            'estoque' => '200',
            'padrao' => '1',
            'ativo' => '1',
            'ordem' => '10',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['data']);
        $this->assertSame('CAB-TELA-MENTA', $result['data']['codigo']);
        $this->assertSame(1, $result['data']['grupo_id']);
        $this->assertSame('Tela Menta', $result['data']['nome']);
        $this->assertSame('#C6F0DC', $result['data']['cor_hex']); // normalized to uppercase
        $this->assertSame(39.9, $result['data']['preco_adicional']);
        $this->assertSame(200, $result['data']['estoque']);
        $this->assertSame(1, $result['data']['padrao']);
        $this->assertSame(1, $result['data']['ativo']);
        $this->assertSame(10, $result['data']['ordem']);
    }

    public function testValidateRowWithOmittedOptionalFieldsReturnsNullForThoseFields(): void
    {
        $row = [
            '_line_number' => 2,
            'codigo' => 'CAB-TELA-MENTA',
            'grupo' => 'cabedal',
            'nome' => 'Tela Menta',
            'cor_hex' => '#C6F0DC',
            'acabamento' => 'tela',
            'preco_adicional' => '39.90',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['data']);
        $this->assertNull($result['data']['estoque']);
        $this->assertNull($result['data']['padrao']);
        $this->assertNull($result['data']['ativo']);
        $this->assertNull($result['data']['ordem']);
    }

    public function testValidateRowWithExplicitZeroValuesReturnsZeros(): void
    {
        $row = [
            '_line_number' => 2,
            'codigo' => 'CAB-TELA-MENTA',
            'grupo' => 'cabedal',
            'nome' => 'Tela Menta',
            'cor_hex' => '#C6F0DC',
            'acabamento' => 'tela',
            'preco_adicional' => '0.00',
            'estoque' => '0',
            'padrao' => '0',
            'ativo' => '0',
            'ordem' => '0',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['data']);
        $this->assertSame(0, $result['data']['estoque']);
        $this->assertSame(0, $result['data']['padrao']);
        $this->assertSame(0, $result['data']['ativo']);
        $this->assertSame(0, $result['data']['ordem']);
    }

    public function testValidateInvalidColor(): void
    {
        $row = [
            '_line_number' => 5,
            'codigo' => 'CAB-01',
            'grupo' => 'cabedal',
            'nome' => 'Teste',
            'cor_hex' => 'AZUL_MENTA',
            'acabamento' => 'tela',
            'preco_adicional' => '0.00',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertNotEmpty($result['errors']);
        $this->assertNull($result['data']);
        $this->assertSame(5, $result['errors'][0]->getRowNumber());
        $this->assertSame('CAB-01', $result['errors'][0]->getComponentCode());
        $this->assertStringContainsString('A cor "AZUL_MENTA" é inválida', $result['errors'][0]->getReason());
    }

    public function testValidateUnknownGroup(): void
    {
        $row = [
            '_line_number' => 4,
            'codigo' => 'CAB-01',
            'grupo' => 'inexistente',
            'nome' => 'Teste',
            'cor_hex' => '#FFFFFF',
            'acabamento' => 'tela',
            'preco_adicional' => '0.00',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('O grupo "inexistente" não existe no catálogo', $result['errors'][0]->getReason());
    }

    public function testValidateInvalidPrice(): void
    {
        $row = [
            '_line_number' => 6,
            'codigo' => 'CAB-01',
            'grupo' => 'cabedal',
            'nome' => 'Teste',
            'cor_hex' => '#FFFFFF',
            'acabamento' => 'tela',
            'preco_adicional' => '-10.50',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('preço adicional "-10.50" é inválido', $result['errors'][0]->getReason());
    }

    public function testValidateInvalidStock(): void
    {
        $row = [
            '_line_number' => 7,
            'codigo' => 'CAB-01',
            'grupo' => 'cabedal',
            'nome' => 'Teste',
            'cor_hex' => '#FFFFFF',
            'acabamento' => 'tela',
            'preco_adicional' => '0.00',
            'estoque' => '-5',
        ];

        $result = $this->validator->validate($row, $this->availableGroups, []);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('estoque "-5" é inválido', $result['errors'][0]->getReason());
    }

    public function testValidateDuplicateCodeInFile(): void
    {
        $row = [
            '_line_number' => 8,
            'codigo' => 'CAB-01',
            'grupo' => 'cabedal',
            'nome' => 'Teste 2',
            'cor_hex' => '#FFFFFF',
            'acabamento' => 'tela',
            'preco_adicional' => '0.00',
        ];

        $seenCodes = ['CAB-01' => 2];
        $result = $this->validator->validate($row, $this->availableGroups, $seenCodes);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Código duplicado no arquivo: "CAB-01" já foi informado na linha 2', $result['errors'][0]->getReason());
    }
}
