<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controladores Auth
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;

// Controladores Sistema
use App\Http\Controllers\AlumnoGestionController;
use App\Http\Controllers\CarreraController;
use App\Http\Controllers\ConvocatoriaController;
use App\Http\Controllers\DictamenController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\PeriodoController;
use App\Http\Controllers\ResultadosController;
use App\Http\Controllers\RolAsignacionController;
use App\Http\Controllers\SolicitudBecaController;
use App\Http\Controllers\SuperAdminController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [RegisterController::class, 'register']);
Route::get('/verify-email/{code}', [RegisterController::class, 'verifyEmail']);
Route::post('/resend-token', [RegisterController::class, 'resendToken']);
Route::post('/forgot-password', [PasswordResetController::class, 'enviarCodigo']);
Route::post('/reset-password', [PasswordResetController::class, 'restablecer']);
Route::post('/two-factor/challenge', [TwoFactorController::class, 'challenge']);

// Convocatorias y Carreras (Públicas)
Route::get('/convocatorias-publicas', [ConvocatoriaController::class, 'publica']);
Route::get('/convocatorias/vigente', [ConvocatoriaController::class, 'obtenerVigente']);
Route::get('/convocatoria/activa', [ConvocatoriaController::class, 'getActiva']);
Route::get('/carreras', [CarreraController::class, 'index']);

// ¡AQUÍ ESTÁ LA SOLUCIÓN! Ruta de grupos completamente pública:
Route::get('/carreras/{id}/grupos', function ($id) {
    return response()->json(\Illuminate\Support\Facades\DB::table('grupos')
        ->where('carrera_id', $id)
        ->where('estado', 'ACTIVO')
        ->get(), 200);
});

/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS (Requieren inicio de sesión)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Usuario Actual
    Route::get('/user', function (Request $request) {
        $usuario = $request->user();
        if (!$usuario) return response()->json(['status' => 'error', 'message' => 'Usuario no autenticado.'], 401);
        try { $usuario->load(['carrera', 'grupoRelacion', 'carrerasAsignadas']); } catch (\Throwable $e) {}
        return response()->json([
            'status' => 'success',
            'user' => $usuario,
            'must_change_password' => (bool) ($usuario->must_change_password ?? false),
        ]);
    });

    Route::post('/logout', [LoginController::class, 'logout']);

    // 2FA
    Route::get('/two-factor/status', [TwoFactorController::class, 'status']);
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
    Route::delete('/two-factor', [TwoFactorController::class, 'disable']);

    /* --- ALUMNO --- */
    Route::prefix('alumno')->middleware('role:alumno')->group(function () {
        Route::get('/convocatoria-actual', [ConvocatoriaController::class, 'actual']);
        Route::get('/mi-solicitud-activa', [SolicitudBecaController::class, 'miSolicitudActiva']);
        Route::get('/mis-solicitudes', [SolicitudBecaController::class, 'misSolicitudes']);
        Route::post('/solicitudes', [SolicitudBecaController::class, 'crear']);
        Route::post('/solicitudes/{solicitud}/documentos', [SolicitudBecaController::class, 'subirDocumento']);
    });

    /* --- PROFESOR --- */
    Route::prefix('profesor')->middleware('role:profesor')->group(function () {
        Route::get('/solicitudes', [SolicitudBecaController::class, 'porCarreraAsignada']);
        Route::patch('/solicitudes/{solicitud}/estatus', [SolicitudBecaController::class, 'actualizarEstatus']);
        Route::patch('/solicitudes/{solicitud}/dictamen', [SolicitudBecaController::class, 'dictaminar']);
        Route::patch('/documentos/{documento}/observar', [DocumentoController::class, 'solicitarCorreccion']);
    });

    /* --- ADMIN / JEFE DE CARRERA --- */
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/solicitudes', [SolicitudBecaController::class, 'porCarreraAsignada']);
        Route::patch('/solicitudes/{solicitud}/estatus', [SolicitudBecaController::class, 'actualizarEstatus']);
        Route::patch('/solicitudes/{solicitud}/dictamen', [SolicitudBecaController::class, 'dictaminar']);
        Route::patch('/documentos/{documento}/observar', [DocumentoController::class, 'solicitarCorreccion']);
        Route::get('/alumnos', [AlumnoGestionController::class, 'index']);
        Route::patch('/alumnos/{alumno}', [AlumnoGestionController::class, 'actualizar']);
        Route::get('/staff', [RolAsignacionController::class, 'listarStaff']);
        Route::get('/periodos', [PeriodoController::class, 'index']);
        Route::get('/convocatorias', [ConvocatoriaController::class, 'index']);
    });

    /* --- SUPERADMIN --- */
    Route::prefix('master')->middleware('role:superadmin')->group(function () {
        Route::get('/stats', [SuperAdminController::class, 'getStats']);
        Route::get('/usuarios', [SuperAdminController::class, 'listarUsuarios']);
        Route::post('/reset-password', [SuperAdminController::class, 'resetPassword']);
        Route::post('/usuarios/{usuario}/forzar-reset', [AlumnoGestionController::class, 'forzarResetPassword']);
        
        Route::get('/solicitudes', [SolicitudBecaController::class, 'todas']);
        Route::patch('/solicitudes/{solicitud}/estatus', [SolicitudBecaController::class, 'actualizarEstatus']);
        Route::patch('/solicitudes/{solicitud}/dictamen', [SolicitudBecaController::class, 'dictaminar']);
        Route::patch('/solicitudes/{solicitud}/dictamen-final', [DictamenController::class, 'guardar']);
        
        Route::patch('/documentos/{documento}/observar', [DocumentoController::class, 'solicitarCorreccion']);
        
        Route::get('/alumnos', [AlumnoGestionController::class, 'index']);
        Route::patch('/alumnos/{alumno}', [AlumnoGestionController::class, 'actualizar']);
        
        Route::get('/staff', [RolAsignacionController::class, 'listarStaff']);
        Route::post('/staff', [RolAsignacionController::class, 'crearStaff']);
        Route::patch('/staff/{usuario}', [RolAsignacionController::class, 'actualizarStaff']);
        Route::put('/staff/{usuario}', [RolAsignacionController::class, 'actualizarStaff']);
        Route::delete('/staff/{usuario}', [RolAsignacionController::class, 'eliminarStaff']);
        
        Route::get('/convocatorias', [ConvocatoriaController::class, 'index']);
        Route::post('/convocatorias', [ConvocatoriaController::class, 'store']);
        Route::get('/convocatorias/{convocatoria}', [ConvocatoriaController::class, 'show']);
        Route::patch('/convocatorias/{convocatoria}', [ConvocatoriaController::class, 'update']);
        Route::put('/convocatorias/{convocatoria}', [ConvocatoriaController::class, 'update']);
        Route::delete('/convocatorias/{convocatoria}', [ConvocatoriaController::class, 'destroy']);
        Route::post('/convocatorias/{convocatoria}/archivo', [ConvocatoriaController::class, 'reemplazarArchivo']);
        Route::delete('/convocatorias/{convocatoria}/archivo', [ConvocatoriaController::class, 'eliminarArchivo']);
        Route::patch('/convocatorias/{convocatoria}/publicar', [ConvocatoriaController::class, 'publicar']);
        Route::patch('/convocatorias/{convocatoria}/cerrar', [ConvocatoriaController::class, 'cerrar']);
        Route::post('/convocatorias/{convocatoria}/enviar-resultados', [ResultadosController::class, 'enviar']);
        
        Route::get('/periodos', [PeriodoController::class, 'index']);
        Route::post('/periodos', [PeriodoController::class, 'store']);
        Route::get('/periodos/{periodo}', [PeriodoController::class, 'show']);
        Route::patch('/periodos/{periodo}', [PeriodoController::class, 'update']);
        Route::put('/periodos/{periodo}', [PeriodoController::class, 'update']);
        Route::patch('/periodos/{periodo}/cerrar', [PeriodoController::class, 'cerrar']);
        Route::delete('/periodos/{periodo}', [PeriodoController::class, 'destroy']);
        
        Route::get('/carreras', [CarreraController::class, 'index']);
        Route::post('/carreras', [CarreraController::class, 'store']);
        Route::get('/carreras/{carrera}', [CarreraController::class, 'show']);
        Route::patch('/carreras/{carrera}', [CarreraController::class, 'update']);
        Route::put('/carreras/{carrera}', [CarreraController::class, 'update']);
        Route::delete('/carreras/{carrera}', [CarreraController::class, 'destroy']);
        
        Route::get('/grupos', [GrupoController::class, 'index']);
        Route::post('/grupos', [GrupoController::class, 'store']);
        Route::get('/grupos/{grupo}', [GrupoController::class, 'show']);
        Route::patch('/grupos/{grupo}', [GrupoController::class, 'update']);
        Route::put('/grupos/{grupo}', [GrupoController::class, 'update']);
        Route::delete('/grupos/{grupo}', [GrupoController::class, 'destroy']);
        Route::post('/grupos/{grupo}/alumnos', [GrupoController::class, 'asignarAlumno']);
        Route::delete('/grupos/{grupo}/alumnos/{alumno}', [GrupoController::class, 'quitarAlumno']);
    });
});