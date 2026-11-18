<?php

namespace App\Actions\Imports;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class WorkbookRows
{
    /** @param list<string> $headers
     * @return array<int, list<string>>
     */
    public function read(string $path, string $sheetName, array $headers, int $maxRows): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak valid.']);
        }
        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $bytes += ($zip->statIndex($i)['size'] ?? 0);
        }
        $zip->close();
        if ($bytes > 30 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Isi file Excel terlalu besar.']);
        }

        $book = null;
        try {
            $reader = IOFactory::createReader('Xlsx');
            $info = collect($reader->listWorksheetInfo($path))->firstWhere('worksheetName', $sheetName);
            if ($info === null || $info['totalRows'] > $maxRows + 1 || $info['totalColumns'] > count($headers)) {
                throw ValidationException::withMessages(['file' => 'Gunakan sheet '.$sheetName.' dengan kolom sesuai template dan maksimal '.$maxRows.' baris.']);
            }
            $reader->setLoadSheetsOnly($sheetName);
            $book = $reader->load($path);
            $sheet = $book->getSheetByName($sheetName);
            $rows = [];
            foreach (range(1, max(1, $info['totalRows'])) as $row) {
                $values = [];
                for ($col = 1; $col <= count($headers); $col++) {
                    $cell = $sheet->getCell([$col, $row]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        throw ValidationException::withMessages(['file' => 'Baris '.$row.': gunakan nilai biasa, bukan formula Excel.']);
                    }
                    $values[] = trim((string) $cell->getValue());
                }
                if ($row === 1 && $values !== $headers) {
                    throw ValidationException::withMessages(['file' => 'Header harus '.implode(', ', $headers).'.']);
                }
                if ($row > 1 && array_filter($values, fn ($value) => $value !== '') !== []) {
                    $rows[$row] = $values;
                }
            }
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca. Gunakan template Excel yang tersedia.']);
        } finally {
            $book?->disconnectWorksheets();
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File belum berisi data.']);
        }

        return $rows;
    }
}
