<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Import;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class CsvReader
 *
 * Handles reading and normalizing CSV files for catalog import.
 */
class CsvReader
{
    private const REQUIRED_HEADERS = [
        'codigo',
        'grupo',
        'nome',
        'cor_hex',
        'acabamento',
        'preco_adicional',
    ];

    private const HEADER_SYNONYMS = [
        'código' => 'codigo',
        'code' => 'codigo',
        'group' => 'grupo',
        'grupo_id' => 'grupo',
        'name' => 'nome',
        'cor' => 'cor_hex',
        'color' => 'cor_hex',
        'hex' => 'cor_hex',
        'cor_hexadecimal' => 'cor_hex',
        'finish' => 'acabamento',
        'preço_adicional' => 'preco_adicional',
        'preco' => 'preco_adicional',
        'preço' => 'preco_adicional',
        'price' => 'preco_adicional',
        'stock' => 'estoque',
        'qty' => 'estoque',
        'quantidade' => 'estoque',
        'padrão' => 'padrao',
        'default' => 'padrao',
        'active' => 'ativo',
        'status' => 'ativo',
        'order' => 'ordem',
        'sort_order' => 'ordem',
    ];

    /**
     * Read and parse CSV file
     *
     * @param string $filePath
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function read(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(
                sprintf('O arquivo "%s" não existe ou não pode ser lido.', $filePath)
            );
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException(
                sprintf('Não foi possível abrir o arquivo "%s".', $filePath)
            );
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false || trim($firstLine) === '') {
                throw new RuntimeException('O arquivo informado está vazio.');
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $rawHeaders = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($rawHeaders === false || empty($rawHeaders)) {
                throw new RuntimeException('Não foi possível identificar o cabeçalho do arquivo CSV.');
            }

            $headers = $this->normalizeHeaders($rawHeaders);
            $this->validateHeaders($headers);

            $rows = [];
            $lineNumber = 1;

            while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $lineNumber++;

                // Skip blank lines
                if ($data === [null] || empty(array_filter($data, static fn ($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                if (count($data) !== count($headers)) {
                    throw new RuntimeException(
                        sprintf(
                            'Linha %d possui %d colunas, mas o cabeçalho define %d colunas.',
                            $lineNumber,
                            count($data),
                            count($headers)
                        )
                    );
                }

                $combined = array_combine($headers, $data);
                if ($combined === false) {
                    continue;
                }

                $trimmed = array_map(static fn ($val): string => trim((string) $val), $combined);
                $trimmed['_line_number'] = $lineNumber;
                $rows[] = $trimmed;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Detect CSV delimiter (comma or semicolon)
     *
     * @param string $line
     * @return string
     */
    private function detectDelimiter(string $line): string
    {
        $semicolons = substr_count($line, ';');
        $commas = substr_count($line, ',');

        return $semicolons > $commas ? ';' : ',';
    }

    /**
     * Normalize header names
     *
     * @param array<int, string|null> $rawHeaders
     * @return string[]
     */
    private function normalizeHeaders(array $rawHeaders): array
    {
        $headers = [];

        foreach ($rawHeaders as $index => $header) {
            $header = (string) $header;
            if ($index === 0) {
                // Strip UTF-8 BOM
                $header = (string) preg_replace('/^\xEF\xBB\xBF/', '', $header);
            }

            $cleaned = mb_strtolower(trim($header), 'UTF-8');
            $normalized = self::HEADER_SYNONYMS[$cleaned] ?? $cleaned;
            $headers[] = $normalized;
        }

        return $headers;
    }

    /**
     * Validate presence of required headers
     *
     * @param string[] $headers
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateHeaders(array $headers): void
    {
        $missing = [];

        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array($required, $headers, true)) {
                $missing[] = $required;
            }
        }

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                sprintf('O arquivo CSV não possui as colunas obrigatórias: %s.', implode(', ', $missing))
            );
        }
    }
}
