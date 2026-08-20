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
        $solicitudes = Solicitud::with(['usuario', 'convocatoria', 'carrera', 'grupoRelacion', 'documentos'])
            ->orderByDesc('id')->get();

        return response()->json(['data' => $solicitudes]);
    }

    // SOLICITUDES POR CARRERA ASIGNADA (Admin / Profesor)
    public function porCarreraAsignada(Request $request) {
        $usuario = $request->user();

        if (!$usuario) return response()->json(['message' => 'Usuario no autenticado.'], 401);

        $carreras = DB::table('asignaciones_carrera')->where('user_id', $usuario->id)->pluck('carrera_id');

        if ($carreras->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'No tienes carreras asignadas.']);
        }

        $solicitudes = Solicitud::with(['usuario', 'convocatoria', 'carrera', 'grupoRelacion', 'documentos'])
            ->whereIn('carrera_id', $carreras)
            ->orderByDesc('id')->get();

        return response()->json(['data' => $solicitudes]);
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
    public function actualizarEstatus(Request $request, Solicitud $solicitud) {
        if (!$this->puedeRevisar($request, $solicitud)) {
            return response()->json(['message' => 'No tienes permiso para modificar esta solicitud.'], 403);
        }

        $validated = $request->validate([
            'estado' => 'required|string|in:PENDIENTE,EN_REVISION,DOCUMENTACION_INCOMPLETA,ACEPTADA,RECHAZADA',
        ]);

        $solicitud->update([
            'estado' => $validated['estado'],
            'revisado_por' => $request->user()->id,
            'fecha_revision' => now(),
        ]);

        return response()->json(['message' => 'Estado actualizado correctamente.', 'data' => $solicitud->fresh()]);
    }

    // DICTAMINAR
    public function dictaminar(Request $request, Solicitud $solicitud) {
        if (!$this->puedeRevisar($request, $solicitud)) {
            return response()->json(['message' => 'No tienes permiso para dictaminar esta solicitud.'], 403);
        }

        $validated = $request->validate([
            'estado' => 'required|string|in:ACEPTADA,RECHAZADA',
            'porcentaje_beca' => 'nullable|numeric|between:0,100',
            'comentario_revision' => 'nullable|string|max:2000',
        ]);

        // Si es Aceptada, exigimos el porcentaje
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
            'message' => $validated['estado'] === 'ACEPTADA' ? 'Solicitud aceptada correctamente.' : 'Solicitud rechazada.',
            'data' => $solicitud,
        ]);
    }

    // VERIFICAR PERMISO PRIVADO
    private function puedeRevisar(Request $request, Solicitud $solicitud): bool {
        $usuario = $request->user();
        
        if (!$usuario) return false;
        if ($usuario->role === 'superadmin') return true;
        if (!in_array($usuario->role, ['admin', 'profesor'], true)) return false;

        $carreras = DB::table('asignaciones_carrera')
            ->where('user_id', $usuario->id)
            ->pluck('carrera_id')
            ->map(fn ($id) => (int) $id);

        return $carreras->contains((int) $solicitud->carrera_id);
    }

    // ALUMNO: MI SOLICITUD ACTIVA
    public function miSolicitudActiva(Request $request) {
        $solicitud = Solicitud::with(['convocatoria', 'documentos'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->first();

        if (!$solicitud) return response()->json(['message' => 'No hay solicitud activa'], 404);
        
        return response()->json($solicitud, 200);
    }

    // ALUMNO: HISTORIAL DE SOLICITUDES
    public function misSolicitudes(Request $request) {
        $solicitudes = Solicitud::with(['convocatoria'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->get();

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