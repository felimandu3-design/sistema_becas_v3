<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DictamenController extends Controller
{
    public function guardar(
    Request $request,
    Solicitud $solicitud
) {
    $validated =
        $request->validate([
            'estado' => [
            'required',
           'string',
            'in:PENDIENTE,ACEPTADA,RECHAZADA,EN_REVISION,DOCUMENTACION_INCOMPLETA',  
            ],
            'porcentaje_beca' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'comentario_revision' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);


    /*
    |--------------------------------------------------------------------------
    | ACEPTADA REQUIERE PORCENTAJE
    |--------------------------------------------------------------------------
    */

    if (
        $validated['estado'] === 'ACEPTADA'
        &&
        (
            !isset($validated['porcentaje_beca'])
        )
    ) {
        return response()->json([
            'message' => 'Debes indicar el porcentaje autorizado.',
        ], 422);
    }


    $solicitud = DB::transaction(
        function () use (
            $request,
            $solicitud,
            $validated
        ) {

            $solicitud->update([
                'estado' =>
                    $validated['estado'],

                'porcentaje_beca' =>
                    $validated['estado'] === 'ACEPTADA'
                        ? $validated['porcentaje_beca']
                        : null,

                'comentario_revision' =>
                    $validated['comentario_revision']
                    ?? null,

                'revisado_por' =>
                    $request->user()->id,

                'fecha_revision' =>
                    now(),
            ]);

            return $solicitud;
        }
    );


    $solicitud->load([
        'usuario',
        'convocatoria',
        'carrera',
        'grupoRelacion',
        'documentos',
    ]);


    return response()->json([
        'message' => 'Estado de la solicitud actualizado correctamente.',
        'data' => $solicitud,
    ]);
}
}