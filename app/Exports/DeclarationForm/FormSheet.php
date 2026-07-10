<?php

namespace App\Exports\DeclarationForm;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class FormSheet implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithStrictNullComparison, WithTitle
{
    private const HEADER_COLOR = '1E3A5F';

    private const SECTION_COLOR = '4A6FA5';

    private const FORMULA_COLOR = 'EEF2F8';

    /** @var array<int, bool> Filas de títulos de sección. */
    private array $sectionRows = [];

    /** @var array<int, bool> Filas de casilleros tipo fórmula. */
    private array $formulaRows = [];

    /** @var array<int, string> Valores string (Mes, Año, RUC) por fila: se reescriben como texto. */
    private array $textValueRows = [];

    private int $lastDataRow = 0;

    /**
     * @param  array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>  $sections
     * @param  array<int, array{code: string, base: float, value: float}>  $unmapped
     */
    public function __construct(
        private readonly string $formName,
        private readonly string $formTitle,
        private readonly array $sections,
        private readonly string $periodLabel,
        private readonly string $ruc,
        private readonly ?string $companyName,
        private readonly array $unmapped = [],
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $rows = [
            [$this->formTitle],
            ['RUC', $this->ruc],
            ['Razón social', $this->companyName],
            ['Período', $this->periodLabel],
            [''],
            ['Casillero', 'Descripción', 'Valor', 'Tipo'],
        ];

        foreach ($this->sections as $section) {
            $rows[] = [$section['section']];
            $this->sectionRows[count($rows)] = true;

            foreach ($section['rows'] as $row) {
                $rows[] = [$row['c'], $row['d'], $row['v'], ucfirst($row['t'] === 'formula' ? 'fórmula' : $row['t'])];

                if ($row['t'] === 'formula') {
                    $this->formulaRows[count($rows)] = true;
                }

                if (is_string($row['v']) && $row['v'] !== '') {
                    $this->textValueRows[count($rows)] = $row['v'];
                }
            }
        }

        $this->lastDataRow = count($rows);

        if ($this->unmapped !== []) {
            $rows[] = [''];
            $rows[] = ['CÓDIGOS DE RETENCIÓN SIN CASILLERO ASIGNADO (revisar manualmente)'];
            $this->sectionRows[count($rows)] = true;

            foreach ($this->unmapped as $item) {
                $rows[] = [$item['code'], 'Código sin mapeo a casillero', $item['value'], 'Revisar'];
            }
        }

        return $rows;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 80, 'C' => 16, 'D' => 11];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }

    /** @return array<string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Título del formulario
                $sheet->mergeCells('A1:D1');
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_COLOR]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // Bloque RUC / razón social / período
                $sheet->getStyle('A2:A4')->getFont()->setBold(true);

                // RUC como texto: con formato General un número de 13 dígitos sale en notación científica
                $sheet->getCell('B2')->setValueExplicit($this->ruc, DataType::TYPE_STRING);
                $sheet->getStyle('B2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                // RUC como texto: con formato General un número de 13 dígitos sale en notación científica
                $sheet->getCell('B2')->setValueExplicit($this->ruc, DataType::TYPE_STRING);
                $sheet->getStyle('B2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                // Encabezado de la tabla
                $sheet->getRowDimension(6)->setRowHeight(22);
                $sheet->getStyle('A6:D6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_COLOR]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->freezePane('A7');

                // Títulos de sección
                foreach (array_keys($this->sectionRows) as $row) {
                    $sheet->mergeCells("A{$row}:D{$row}");
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_COLOR]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Filas de fórmulas (totales) resaltadas
                foreach (array_keys($this->formulaRows) as $row) {
                    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::FORMULA_COLOR]],
                    ]);
                }

                // Alineaciones de columnas
                $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D7:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Valores string (Mes, Año, RUC, razón social) como texto explícito,
                // si no el binder los convierte a número y toman el formato decimal
                foreach ($this->textValueRows as $row => $value) {
                    $sheet->getCell("C{$row}")->setValueExplicit($value, DataType::TYPE_STRING);
                    $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Bordes de la tabla de casilleros
                $sheet->getStyle("A6:D{$this->lastDataRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D7E3']],
                        'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::HEADER_COLOR]],
                    ],
                ]);
            },
        ];
    }

    public function title(): string
    {
        return $this->formName;
    }
}
