<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolicitudBecaController extends Controller
{
    // TODAS LAS SOLICITUDES - SUPERADMIN
    public function todas(Request $request) {
        $solicitudes = Solicitud::with([
            'usuario.carrera',
            'usuario.grupoRelacion.carrera',
            'convocatoria.periodo',
            'carrera',
            'grupoRelacion.carrera',
            'documentos'
        ])
        ->orderByDesc('id')
        ->get();

        return response()->json(['data' => $solicitudes]);
    }

    // SOLICITUDES POR CARRERA ASIGNADA (Admin / Profesor / Jefe)
    public function porCarreraAsignada(Request $request)
    {
        try {
            $usuario = $request->user();

            if (!$usuario) {
                return response()->json(['message' => 'Usuario no autenticado.'], 401);
            }

            // 1. Recopilar todos los IDs de carrera asociados al Jefe/Admin
            $carrerasIds = [];

            if ($usuario->carrera_id) {
                $carrerasIds[] = $usuario->carrera_id;
            }

            // Si existen carreras asignadas en tabla pivote
            if (method_exists($usuario, 'carrerasAsignadas') && $usuario->carrerasAsignadas()->exists()) {
                $carrerasIds = array_merge($carrerasIds, $usuario->carrerasAsignadas()->pluck('carrera_id')->toArray());
            }

            $carrerasIds = array_unique(array_filter($carrerasIds));

            // 2. Consultar solicitudes cruzando la carrera por solicitud, por usuario y por grupo
            $query = \App\Models\Solicitud::with([
                'usuario.carrera',
                'usuario.grupoRelacion.carrera',
                'convocatoria.periodo',
                'carrera',
                'grupoRelacion.carrera',
                'documentos'
            ]);

            if (!empty($carrerasIds)) {
                $query->where(function ($q) use ($carrerasIds) {
                    // Carrera directa en la solicitud
                    $q->whereIn('carrera_id', $carrerasIds)
                      // O carrera del alumno que creó la solicitud
                      ->orWhereHas('usuario', function ($qUser) use ($carrerasIds) {
                          $qUser->whereIn('carrera_id', $carrerasIds);
                      })
                      // O carrera a través del grupo del alumno
                      ->orWhereHas('usuario.grupoRelacion', function ($qGrupo) use ($carrerasIds) {
                          $qGrupo->whereIn('carrera_id', $carrerasIds);
                      });
                });
            }

            $solicitudes = $query->orderByDesc('id')->get();

            return response()->json([
                'data' => $solicitudes,
                'carreras_detectadas' => $carrerasIds
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage()
            ], 500);
        }
    }

    // VER EXPEDIENTE
    public function show(Request $request, Solicitud $solicitud) {
        if (!$this->puedeRevisar($request, $solicitud)) {
            return response()->json(['message' => 'No tienes permiso para consultar este expediente.'], 403);
        }

        $solicitud->load(['usuario', 'convocatoria', 'carrera', 'grupoRelacion', 'documentos', 'documentos.revisor', 'revisor']);
        return response()->json(['data' => $solicitud]);
    }

    // ACTUALIZAR ESTADO
    public function actualizarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string'
        ]);

        $solicitud = Solicitud::findOrFail($id);
        $solicitud->estado = $request->estado;
        $solicitud->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'solicitud' => $solicitud
        ]);
    }

    public function confirmarSolicitud($id)
{
    $solicitud = Solicitud::findOrFail($id);
    $solicitud->estado = 'REVISADO_TUTOR'; 
    $solicitud->save();

    return response()->json([
        'message' => 'Solicitud confirmada y enviada a Solicitudes Revisadas correctamente.'
    ]);
}

    // DICTAMINAR
    public function dictaminar(Request $request, Solicitud $solicitud) 
    {
        if (!$this->puedeRevisar($request, $solicitud)) {
            return response()->json(['message' => 'No tienes permiso para dictaminar esta solicitud.'], 403);
        }

        $validated = $request->validate([
            'estado' => 'required|string|in:ACEPTADA,RECHAZADA,INCOMPLETA,DOCUMENTACION_INCOMPLETA,EN_REVISION',
            'porcentaje_beca' => 'nullable|numeric|between:0,100',
            'comentario_revision' => 'nullable|string|max:2000',
        ]);

        if ($validated['estado'] === 'ACEPTADA' && empty($validated['porcentaje_beca'])) {
            return response()->json(['message' => 'Debes indicar el porcentaje de beca autorizado.'], 422);
        }

        $solicitud->update([
            'estado' => $validated['estado'],
            'porcentaje_beca' => $validated['estado'] === 'ACEPTADA' ? $validated['porcentaje_beca'] : null,
            'comentario_revision' => $validated['comentario_revision'] ?? null,
            'revisado_por' => $request->user()->id,
            'fecha_revision' => now(),
        ]);

        $solicitud->load(['usuario', 'convocatoria', 'carrera', 'grupoRelacion', 'documentos']);

        return response()->json([
            'message' => 'Estatus de la solicitud actualizado correctamente.',
            'data' => $solicitud,
        ]);
    }

    // VERIFICAR PERMISO PRIVADO
    private function puedeRevisar(Request $request, Solicitud $solicitud): bool {
        $usuario = $request->user();
        
        if (!$usuario) return false;
        if ($usuario->role === 'superadmin') return true;
        if (!in_array($usuario->role, ['admin', 'profesor', 'jefe', 'jefe_carrera'], true)) return false; 

        // 1. Buscamos en la tabla pivot
        $carreras = DB::table('asignaciones_carrera')
            ->where('user_id', $usuario->id)
            ->pluck('carrera_id')
            ->map(fn ($id) => (int) $id);

        // 2. Agregamos su carrera principal
        if ($usuario->carrera_id) {
            $carreras->push((int) $usuario->carrera_id);
        }

        // 3. Verificamos si la carrera de la solicitud está en su lista
        return $carreras->contains((int) $solicitud->carrera_id);
    }

    // ALUMNO: MI SOLICITUD ACTIVA
    public function miSolicitudActiva(Request $request) {
        $solicitud = Solicitud::with(['convocatoria', 'documentos'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->first();

        if (!$solicitud) return response()->json(['message' => 'No hay solicitud activa'], 404);
        
        // EL ESCUDO: Ocultar dictamen si no se han publicado resultados
        if ($solicitud->convocatoria && !$solicitud->convocatoria->resultados_publicados) {
            if (in_array($solicitud->estado, ['ACEPTADA', 'RECHAZADA'])) {
                $solicitud->estado = 'EN_REVISION';
                $solicitud->porcentaje_beca = null;
                $solicitud->comentario_revision = null;
            }
        }
        
        return response()->json($solicitud, 200);
    }

    // ALUMNO: HISTORIAL DE SOLICITUDES
    public function misSolicitudes(Request $request) {
        $solicitudes = Solicitud::with(['convocatoria'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->get();

        // EL ESCUDO para todas las solicitudes del historial
        $solicitudes->transform(function ($solicitud) {
            if ($solicitud->convocatoria && !$solicitud->convocatoria->resultados_publicados) {
                if (in_array($solicitud->estado, ['ACEPTADA', 'RECHAZADA'])) {
                    $solicitud->estado = 'EN_REVISION';
                    $solicitud->porcentaje_beca = null;
                    $solicitud->comentario_revision = null;
                }
            }
            return $solicitud;
        });

        return response()->json($solicitudes, 200);
    }

    // ALUMNO: CREAR NUEVA SOLICITUD
    public function crear(Request $request) {
        $usuario = $request->user();

        $validated = $request->validate([
            'modalidad' => 'required|string',
            'carrera_id' => 'required|integer|exists:carreras,id',
            'grupo_id' => 'required|integer|exists:grupos,id',
        ]);

        $convocatoria = DB::table('convocatorias')->where('estado', 'PUBLICADA')->first();
        if (!$convocatoria) return response()->json(['message' => 'No hay una convocatoria activa.'], 400);

        $existe = Solicitud::where('user_id', $usuario->id)->where('convocatoria_id', $convocatoria->id)->exists();
        if ($existe) return response()->json(['message' => 'Ya tienes una solicitud para esta convocatoria.'], 400);

        $ultimoId = Solicitud::max('id') ?? 0;
        $folio = 'BEC-' . date('Y') . '-' . str_pad($ultimoId + 1, 4, '0', STR_PAD_LEFT);

        $solicitud = Solicitud::create([
            'user_id' => $usuario->id,
            'convocatoria_id' => $convocatoria->id,
            'estado' => 'PENDIENTE',
            'folio' => $folio,
            'modalidad' => $validated['modalidad'],
            'carrera_id' => $validated['carrera_id'],
            'grupo_id' => $validated['grupo_id'],
        ]);

        if (!$usuario->carrera_id) {
            $usuario->update(['carrera_id' => $validated['carrera_id'], 'grupo_id' => $validated['grupo_id']]);
        }

        return response()->json([
            'message' => 'Solicitud registrada correctamente.',
            'data' => $solicitud->load('convocatoria', 'documentos')
        ], 201);
    }

    // ALUMNO: SUBIR DOCUMENTO
    public function subirDocumento(Request $request, $solicitudId) {
        $usuario = $request->user();

        $solicitud = Solicitud::where('id', $solicitudId)->where('user_id', $usuario->id)->firstOrFail();

        $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tipo' => 'required|string',
        ]);

        $archivo = $request->file('archivo');
        $tipo = $request->input('tipo');

        $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
        $ruta = $archivo->storeAs('documentos', $nombreArchivo, 'public');

        // Registro exacto con los campos de tu modelo
        $documento = Documento::updateOrCreate(
            [
                'solicitud_id' => $solicitud->id,
                'tipo_documento' => $tipo, 
            ],
            [
                'nombre_original' => $archivo->getClientOriginalName(), 
                'ruta_archivo' => $ruta, 
                'estado' => 'PENDIENTE',
            ]
        );

        return response()->json(['message' => 'Documento cargado correctamente.', 'data' => $documento], 201);
    }
}