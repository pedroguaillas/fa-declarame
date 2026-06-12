<?php

namespace App\Exports\Concerns;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

trait HasReportHeader
{
    private int $headerRowCount = 1;

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, $this->headerRowCount);

                $sheet->getRowDimension(1)->setRowHeight(62);

                $logoPath = $this->logoPath ?? null;
                if ($logoPath && Storage::disk('central')->exists($logoPath)) {
                    $drawing = new Drawing;
                    $drawing->setPath(Storage::disk('central')->path($logoPath));
                    $drawing->setHeight(56);
                    $drawing->setCoordinates('A1');
                    $drawing->setWorksheet($sheet);
                }
            },
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();

                // Detect actual heading row: first row where column A has a value.
                // Maatwebsite places headings at insertedRows+1 or insertedRows+2 depending on version.
                $headingRow = 1;
                while ($headingRow <= 10 && $sheet->getCell("A{$headingRow}")->getValue() === null) {
                    $headingRow++;
                }

                // Normalize: ensure heading row is at headerRowCount+2 (row 3).
                // If it landed one row too early (row 2), insert a blank row before it.
                $expectedHeadingRow = $this->headerRowCount + 2;
                while ($headingRow < $expectedHeadingRow) {
                    $sheet->insertNewRowBefore($headingRow, 1);
                    $headingRow++;
                }

                $lastRow = $sheet->getHighestRow();

                // Company name in row 1 next to logo
                $companyName = $this->companyName ?? null;
                if ($companyName) {
                    $sheet->mergeCells("B1:{$lastCol}1");
                    $sheet->setCellValue('B1', $companyName);
                    $sheet->getStyle('B1')->applyFromArray([
                        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A5F']],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                // Report title in row 2 (the implicit extra row before headings)
                $sheet->getRowDimension(2)->setRowHeight(22);
                $sheet->mergeCells("B2:{$lastCol}2");
                $sheet->setCellValue('B2', $this->title());
                $sheet->getStyle('B2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '4A6FA5']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Heading row
                $sheet->getRowDimension($headingRow)->setRowHeight(32);
                $sheet->getStyle("A{$headingRow}:{$lastCol}{$headingRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->freezePane('A'.($headingRow + 1));

                // Alternating row colors
                for ($row = $headingRow + 1; $row <= $lastRow; $row++) {
                    if (($row - $headingRow) % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('EEF2F8');
                    }
                }

                // Borders on full data table
                if ($lastRow >= $headingRow) {
                    $sheet->getStyle("A{$headingRow}:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D7E3']],
                            'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1E3A5F']],
                        ],
                    ]);
                }
            },
        ];
    }
}
