<?php

namespace App\Http\Controllers;

use App\Models\Convocatoria;
use App\Models\User;
use App\Notifications\ConvocatoriaCerradaNotification;
use App\Notifications\ConvocatoriaPublicadaNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ConvocatoriaController extends Controller
{
    // LISTAR CONVOCATORIAS
    public function index() {
        $convocatorias = Convocatoria::with('periodo')->orderByDesc('id')->get();
        return response()->json(['status' => 'success', 'data' => $convocatorias, 'convocatorias' => $convocatorias], 200);
    }

    // VER UNA CONVOCATORIA
    public function show(Convocatoria $convocatoria) {
        $convocatoria->load('periodo');
        return response()->json(['status' => 'success', 'data' => $convocatoria, 'convocatoria' => $convocatoria], 200);
    }

    // CONVOCATORIA PÚBLICA / VIGENTE / ACTIVA
    public function publica() {
        $convocatoria = $this->buscarConvocatoriaVigente();
        return response()->json([
            'status' => 'success', 'data' => $convocatoria, 
            'convocatoria' => $convocatoria, 'convocatorias' => $convocatoria ? [$convocatoria] : []
        ], 200);
    }

    public function obtenerVigente() {
        $convocatoria = $this->buscarConvocatoriaVigente();
        return response()->json(['status' => 'success', 'data' => $convocatoria, 'convocatoria' => $convocatoria], 200);
    }

    public function getActiva() { return $this->obtenerVigente(); }
    public function actual() { return $this->obtenerVigente(); }

    // CREAR CONVOCATORIA
    public function store(Request $request) {
        $validated = $request->validate([
            'periodo_id' => 'required|integer|exists:periodos,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'requisitos' => 'required|string',
            'promedio_minimo' => 'required|numeric|min:0|max:10',
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'required|in:BORRADOR,PUBLICADA,CERRADA',
            'archivo' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $duplicada = Convocatoria::where('periodo_id', $validated['periodo_id'])
            ->where('nombre', $validated['nombre'])
            ->where('fecha_inicio', $validated['fecha_inicio'])
            ->where('fecha_cierre', $validated['fecha_cierre'])
            ->where('created_at', '>=', now()->subSeconds(15))
            ->orderByDesc('id')->first();

        if ($duplicada) {
            return response()->json([
                'status' => 'success', 'message' => 'La convocatoria ya había sido registrada.',
                'data' => $duplicada->load('periodo'), 'convocatoria' => $duplicada,
            ], 200);
        }

        if ($request->hasFile('archivo')) {
            $validated['archivo'] = $request->file('archivo')->store('convocatorias', 'public');
        }

        $rutaArchivo = $validated['archivo'] ?? null;

        try {
            $convocatoria = DB::transaction(function () use ($validated) {
                // Si se crea como publicada, cerramos las demás
                if ($validated['estado'] === 'PUBLICADA') {
                    Convocatoria::where('estado', 'PUBLICADA')->update(['estado' => 'CERRADA']);
                }
                return Convocatoria::create($validated);
            });
        } catch (\Throwable $e) {
            if ($rutaArchivo && Storage::disk('public')->exists($rutaArchivo)) {
                Storage::disk('public')->delete($rutaArchivo);
            }
            report($e);
            return response()->json([
                'status' => 'error', 'message' => 'No fue posible crear la convocatoria.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        if (strtoupper((string) $convocatoria->estado) === 'PUBLICADA') {
            try {
                $this->notificarPublicacion($convocatoria);
            } catch (\Throwable $e) {
                report($e);
                return response()->json([
                    'status' => 'warning', 'message' => 'La convocatoria fue creada y publicada, pero los correos no pudieron enviarse.',
                    'mail_error' => config('app.debug') ? $e->getMessage() : null,
                    'data' => $convocatoria->fresh('periodo'), 'convocatoria' => $convocatoria->fresh('periodo'),
                ], 201);
            }
        }

        return response()->json([
            'status' => 'success', 
            'message' => $convocatoria->estado === 'PUBLICADA' ? 'Convocatoria creada, publicada y alumnos notificados.' : 'Convocatoria creada correctamente.',
            'data' => $convocatoria->fresh('periodo'), 'convocatoria' => $convocatoria->fresh('periodo'),
        ], 201);
    }

    // ACTUALIZAR CONVOCATORIA
    public function update(Request $request, Convocatoria $convocatoria) {
        $estadoAnterior = strtoupper((string) $convocatoria->estado);
        $rutaAnterior = $convocatoria->archivo;

        $validated = $request->validate([
            'periodo_id' => 'sometimes|required|integer|exists:periodos,id',
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'requisitos' => 'sometimes|required|string',
            'promedio_minimo' => 'sometimes|required|numeric|min:0|max:10',
            'fecha_inicio' => 'sometimes|required|date',
            'fecha_cierre' => 'sometimes|required|date',
            'estado' => 'sometimes|required|in:BORRADOR,PUBLICADA,CERRADA',
            'archivo' => 'sometimes|nullable|file|mimes:pdf|max:10240',
        ]);

        $fechaInicio = $validated['fecha_inicio'] ?? $convocatoria->fecha_inicio;
        $fechaCierre = $validated['fecha_cierre'] ?? $convocatoria->fecha_cierre;

        if ($fechaInicio && $fechaCierre && strtotime((string) $fechaCierre) < strtotime((string) $fechaInicio)) {
            return response()->json(['status' => 'error', 'message' => 'La fecha de cierre no puede ser anterior a la de inicio.'], 422);
        }

        $rutaNueva = null;
        if ($request->hasFile('archivo')) {
            $rutaNueva = $request->file('archivo')->store('convocatorias', 'public');
            $validated['archivo'] = $rutaNueva;
        }

        try {
            DB::transaction(function () use ($validated, $convocatoria) {
                if (isset($validated['estado']) && $validated['estado'] === 'PUBLICADA') {
                    Convocatoria::where('id', '!=', $convocatoria->id)->where('estado', 'PUBLICADA')->update(['estado' => 'CERRADA']);
                }
                $convocatoria->update($validated);
            });
        } catch (\Throwable $e) {
            if ($rutaNueva && Storage::disk('public')->exists($rutaNueva)) Storage::disk('public')->delete($rutaNueva);
            report($e);
            return response()->json(['status' => 'error', 'message' => 'No fue posible actualizar.', 'error' => config('app.debug') ? $e->getMessage() : null], 500);
        }

        if ($rutaNueva && $rutaAnterior && $rutaAnterior !== $rutaNueva && Storage::disk('public')->exists($rutaAnterior)) {
            Storage::disk('public')->delete($rutaAnterior);
        }

        $convocatoria->refresh();
        $estadoNuevo = strtoupper((string) $convocatoria->estado);

        if ($estadoAnterior !== 'PUBLICADA' && $estadoNuevo === 'PUBLICADA') {
            try { $this->notificarPublicacion($convocatoria); } 
            catch (\Throwable $e) {
                report($e);
                return response()->json(['status' => 'warning', 'message' => 'Actualizada y publicada, pero fallaron los correos.', 'data' => $convocatoria->fresh('periodo')], 200);
            }
        }

        if ($estadoAnterior !== 'CERRADA' && $estadoNuevo === 'CERRADA') {
            try { $this->notificarCierre($convocatoria); } 
            catch (\Throwable $e) {
                report($e);
                return response()->json(['status' => 'warning', 'message' => 'Cerrada, pero fallaron los correos.', 'data' => $convocatoria->fresh('periodo')], 200);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Convocatoria actualizada.', 'data' => $convocatoria->fresh('periodo')], 200);
    }

    // REEMPLAZAR PDF
    public function reemplazarArchivo(Request $request, Convocatoria $convocatoria) {
        $request->validate(['archivo' => 'required|file|mimes:pdf|max:10240']);
        $rutaNueva = $request->file('archivo')->store('convocatorias', 'public');
        $rutaAnterior = $convocatoria->archivo;

        try {
            $convocatoria->update(['archivo' => $rutaNueva]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutaNueva);
            report($e);
            return response()->json(['status' => 'error', 'message' => 'No fue posible guardar el archivo.'], 500);
        }

        if ($rutaAnterior && Storage::disk('public')->exists($rutaAnterior)) Storage::disk('public')->delete($rutaAnterior);
        return response()->json(['status' => 'success', 'message' => 'PDF guardado.', 'data' => $convocatoria->fresh('periodo')], 200);
    }

    // ELIMINAR PDF
    public function eliminarArchivo(Convocatoria $convocatoria) {
        $ruta = $convocatoria->archivo;
        $convocatoria->update(['archivo' => null]);
        if ($ruta && Storage::disk('public')->exists($ruta)) Storage::disk('public')->delete($ruta);
        return response()->json(['status' => 'success', 'message' => 'PDF eliminado.', 'data' => $convocatoria->fresh('periodo')], 200);
    }

    // PUBLICAR
    public function publicar(Convocatoria $convocatoria) {
        if (strtoupper((string) $convocatoria->estado) === 'PUBLICADA') {
            return response()->json(['status' => 'success', 'message' => 'Ya se encuentra publicada.', 'data' => $convocatoria->fresh('periodo')], 200);
        }

        DB::transaction(function () use ($convocatoria) {
            Convocatoria::where('id', '!=', $convocatoria->id)->where('estado', 'PUBLICADA')->update(['estado' => 'CERRADA']);
            $convocatoria->update(['estado' => 'PUBLICADA']);
        });

        try { $this->notificarPublicacion($convocatoria); } 
        catch (\Throwable $e) {
            report($e);
            return response()->json(['status' => 'warning', 'message' => 'Publicada, pero fallaron los correos.', 'data' => $convocatoria->fresh('periodo')], 200);
        }

        return response()->json(['status' => 'success', 'message' => 'Convocatoria publicada y notificada.', 'data' => $convocatoria->fresh('periodo')], 200);
    }

    // CERRAR
    public function cerrar(Convocatoria $convocatoria) {
        if (strtoupper((string) $convocatoria->estado) === 'CERRADA') {
            return response()->json(['status' => 'success', 'message' => 'Ya se encuentra cerrada.', 'data' => $convocatoria->fresh('periodo')], 200);
        }

        $convocatoria->update(['estado' => 'CERRADA']);

        try { $this->notificarCierre($convocatoria); } 
        catch (\Throwable $e) {
            report($e);
            return response()->json(['status' => 'warning', 'message' => 'Cerrada, pero fallaron los correos.', 'data' => $convocatoria->fresh('periodo')], 200);
        }

        return response()->json(['status' => 'success', 'message' => 'Convocatoria cerrada y notificada.', 'data' => $convocatoria->fresh('periodo')], 200);
    }

    // ELIMINAR CONVOCATORIA
    public function destroy(Convocatoria $convocatoria) {
        $rutaArchivo = $convocatoria->archivo;
        try { $convocatoria->delete(); } 
        catch (\Throwable $e) {
            report($e);
            return response()->json(['status' => 'error', 'message' => 'No fue posible eliminar.'], 422);
        }
        if ($rutaArchivo && Storage::disk('public')->exists($rutaArchivo)) Storage::disk('public')->delete($rutaArchivo);
        return response()->json(['status' => 'success', 'message' => 'Convocatoria eliminada.'], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCAR CONVOCATORIA VIGENTE (CORREGIDO)
    |--------------------------------------------------------------------------
    | Devolvemos la más reciente que esté PUBLICADA o CERRADA.
    | El frontend se encarga de mostrar si las fechas siguen abiertas.
    */
    private function buscarConvocatoriaVigente() {
        return Convocatoria::query()
            ->with('periodo')
            ->whereIn('estado', ['PUBLICADA', 'CERRADA'])
            ->orderByDesc('id')
            ->first();
    }

    // OBTENER ALUMNOS PARA NOTIFICACIÓN
    private function alumnosParaNotificacion() {
        return User::query()->where('role', 'alumno')->whereNotNull('email')->where('email', '!=', '')->orderBy('id')->get();
    }

    // NOTIFICAR PUBLICACIÓN
    private function notificarPublicacion(Convocatoria $convocatoria): void {
        $convocatoria->refresh();
        if ($convocatoria->notificacion_publicada_en) return;

        $alumnos = $this->alumnosParaNotificacion();
        if ($alumnos->isEmpty()) throw new \RuntimeException('No existen alumnos con correo.');

        Notification::send($alumnos, new ConvocatoriaPublicadaNotification($convocatoria));
        $convocatoria->forceFill(['notificacion_publicada_en' => now()])->save();
    }

    // NOTIFICAR CIERRE
    private function notificarCierre(Convocatoria $convocatoria): void {
        $alumnos = $this->alumnosParaNotificacion();
        if ($alumnos->isEmpty()) throw new \RuntimeException('No existen alumnos con correo.');
        Notification::send($alumnos, new ConvocatoriaCerradaNotification($convocatoria));
    }
}