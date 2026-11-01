<?php

namespace App\Support\Import;

use Generator;
use SplFileObject;

/**
 * Reads a CSV file exported from a spreadsheet: the first line holds the
 * column headers, a byte order mark is ignored, and both comma and
 * semicolon separated files are understood.
 */
class CsvFile
{
    private SplFileObject $file;

    private string $delimiter;

    public function __construct(string $path)
    {
        $this->file = new SplFileObject($path, 'r');
        $this->delimiter = $this->detectDelimiter();
    }

    /**
     * Get the column headers from the first line.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        $this->file->rewind();

        return array_map(
            fn (?string $header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
            $this->read() ?? [],
        );
    }

    /**
     * Read the data rows, keyed by their line number in the file.
     *
     * Blank lines are skipped.
     *
     * @return Generator<int, list<string>>
     */
    public function rows(): Generator
    {
        $this->file->rewind();
        $this->read();
        $line = 1;

        while (! $this->file->eof()) {
            $line++;
            $row = $this->read();

            if ($row === null || implode('', $row) === '') {
                continue;
            }

            yield $line => $row;
        }
    }

    /**
     * Read the next line as a list of trimmed cells.
     *
     * @return list<string>|null
     */
    private function read(): ?array
    {
        $row = $this->file->fgetcsv($this->delimiter, '"', '');

        if (! is_array($row) || $row === [null]) {
            return null;
        }

        return array_map(fn (?string $cell): string => trim((string) $cell), $row);
    }

    /**
     * Guess the delimiter from the header line.
     */
    private function detectDelimiter(): string
    {
        $this->file->rewind();
        $line = (string) $this->file->fgets();

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
