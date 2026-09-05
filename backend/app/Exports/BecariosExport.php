<?php

namespace App\Exports;

use App\Models\Solicitud;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BecariosExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $convocatoriaId;

    public function __construct($convocatoriaId)
    {
        $this->convocatoriaId = $convocatoriaId;
    }

    public function collection()
    {
        // Solo exportar los ACEPTADOS de la convocatoria específica
        return Solicitud::with(['usuario.carrera', 'carrera', 'grupoRelacion'])
            ->where('convocatoria_id', $this->convocatoriaId)
            ->where('estado', 'ACEPTADA')
            ->get();
    }

    public function headings(): array
    {
        return ['Folio', 'Matrícula', 'Nombre del Alumno', 'Carrera', 'Grupo', 'Porcentaje Otorgado'];
    }

    public function map($solicitud): array
    {
        return [
            $solicitud->folio,
            $solicitud->usuario->matricula ?? '—',
            $solicitud->usuario->name ?? '—',
            $solicitud->carrera->nombre ?? ($solicitud->usuario->carrera->nombre ?? '—'),
            $solicitud->grupoRelacion->nombre ?? ($solicitud->usuario->grupoRelacion->nombre ?? '—'),
            $solicitud->porcentaje_beca ? $solicitud->porcentaje_beca . '%' : '—',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para la fila de encabezados (Fondo verde oscuro, texto blanco)
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 
                'fill' => ['fillType' => 'solid', 'color' => ['argb' => 'FF087846']]
            ],
        ];
    }
}