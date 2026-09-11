<script setup>
const props = defineProps({
  solicitudes: { type: Array, default: () => [] },
  cargando: { type: Boolean, default: false },
  tabActual: { type: String, default: 'resumen' }
})

// Emitimos tanto la selección como la acción de confirmar
const emit = defineEmits(['seleccionar', 'confirmar'])

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS PARA RESOLVER CAMPOS Y ESTILOS
|--------------------------------------------------------------------------
*/
function obtenerAlumno(solicitud) {
  return {
    nombre: solicitud.usuario?.name || 
            solicitud.usuario?.nombre || 
            solicitud.alumno?.nombre || 
            solicitud.alumno?.name || 
            'Alumno',
    matricula: solicitud.usuario?.matricula || 
                solicitud.alumno?.matricula || 
                solicitud.matricula || 
                '—'
  }
}

function obtenerFolio(solicitud) {
  if (!solicitud) return 'Sin folio'
  return solicitud.folio || solicitud.id_solicitud || `#${solicitud.id}`
}

function obtenerGrupo(solicitud) {
  return solicitud.grupo_relacion?.nombre || 
         solicitud.grupo?.nombre || 
         solicitud.usuario?.grupo?.nombre || 
         solicitud.usuario?.grupo || 
         '—'
}

function obtenerPeriodo(solicitud) {
  return solicitud.convocatoria?.periodo?.nombre || 
         solicitud.convocatoria?.periodo || 
         solicitud.periodo || 
         '—'
}

function obtenerDocumentos(solicitud) {
  const docs = solicitud.documentos || solicitud.archivos || []
  return Array.isArray(docs) ? docs : []
}

function obtenerDescuento(solicitud) {
  const estatus = (solicitud.estado || solicitud.estatus || '').toUpperCase()

  const valor = solicitud.porcentaje_beca ?? solicitud.descuento ?? null

  if (valor === null || valor === undefined || valor === '' || valor === 'N/A') {
    return 'N/A'
  }

  const strValor = valor.toString().trim()
  return strValor.includes('%') ? strValor : `${strValor}%`
}

function normalizarEstado(estado) {
  return String(estado || 'PENDIENTE').trim().toUpperCase()
}

function textoEstado(estado) {
  const valor = normalizarEstado(estado)
  const estados = {
    PENDIENTE: 'Pendiente',
    EN_REVISION: 'En revisión',
    ACEPTADA: 'Aceptada',
    RECHAZADA: 'Rechazada',
    DOCUMENTACION_INCOMPLETA: 'Documentación incompleta',
    REVISADO_TUTOR: 'Revisado por Tutor',
    CONFIRMADO: 'Confirmado',
  }
  return estados[valor] || valor
}

function claseEstado(estado) {
  const valor = normalizarEstado(estado)
  const clases = {
    PENDIENTE: 'warning',
    EN_REVISION: 'info',
    ACEPTADA: 'success',
    RECHAZADA: 'danger',
    DOCUMENTACION_INCOMPLETA: 'purple',
    REVISADO_TUTOR: 'success',
    CONFIRMADO: 'success',
  }
  return clases[valor] || 'neutral'
}
</script>

<template>
  <div class="tabla-wrapper">
    <!-- Estado de Carga -->
    <div v-if="cargando" class="estado-vacio">
      <div class="spinner"></div>
      <p>Cargando solicitudes...</p>
    </div>

    <!-- Sin datos -->
    <div v-else-if="!props.solicitudes || props.solicitudes.length === 0" class="estado-vacio">
      <div class="vacio-icon">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
        </svg>
      </div>
      <strong>No hay solicitudes registradas</strong>
      <p>Aún no existen registros en esta sección.</p>
    </div>

    <!-- Tabla de datos -->
    <div v-else class="tabla-container">
      <table class="tabla">
        <thead>
          <tr>
            <th>ALUMNO</th>
            <th>MATRÍCULA</th>
            <th>GRUPO</th>
            <th>PERIODO</th>
            <th>DOCUMENTOS</th>
            <th>DESCUENTO</th>
            <th>ESTADO</th>
            <th class="text-right">ACCIÓN</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="solicitud in props.solicitudes" :key="solicitud.id">
            <!-- ALUMNO -->
            <td>
              <div class="col-alumno">
                <div class="avatar-sm">
                  {{ obtenerAlumno(solicitud).nombre.charAt(0).toUpperCase() }}
                </div>
                <div class="info-alumno">
                  <strong>{{ obtenerAlumno(solicitud).nombre }}</strong>
                  <small>{{ obtenerFolio(solicitud) }}</small>
                </div>
              </div>
            </td>

            <!-- MATRÍCULA -->
            <td class="text-muted">
              {{ obtenerAlumno(solicitud).matricula }}
            </td>

            <!-- GRUPO -->
            <td class="text-muted">
              {{ obtenerGrupo(solicitud) }}
            </td>

            <!-- PERIODO -->
            <td class="text-muted">
              {{ obtenerPeriodo(solicitud) }}
            </td>

            <!-- DOCUMENTOS -->
            <td>
              <span class="badge-docs">
                <strong>{{ obtenerDocumentos(solicitud).length }}</strong>
                <small>archivo(s)</small>
              </span>
            </td>

            <!-- DESCUENTO -->
            <td class="text-muted">
                {{ obtenerDescuento(solicitud) }}
            </td>

            <!-- ESTADO -->
            <td>
              <span :class="['badge-estado', claseEstado(solicitud.estado || solicitud.estatus)]">
                {{ textoEstado(solicitud.estado || solicitud.estatus) }}
              </span>
            </td>

            <!-- ACCIÓN CONDICIONADA -->
            <td class="text-right">
              <div class="acciones">
                <button 
                  type="button" 
                  class="btn-detalle" 
                  @click="emit('seleccionar', solicitud)"
                >
                  Ver detalle
                </button>

                <!-- Botón Confirmar solo visible en la pestaña 'resumen' -->
                <button 
                  v-if="props.tabActual === 'resumen'"
                  type="button" 
                  class="btn-confirmar" 
                  @click="emit('confirmar', solicitud)"
                >
                  Confirmar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.tabla-wrapper {
  width: 100%;
}

.tabla-container {
  width: 100%;
  overflow-x: auto;
}

.tabla {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 11px;
}

.tabla th {
  padding: 14px 18px;
  border-bottom: 1px solid #edf0ee;
  color: #8c938f;
  font-size: 9px;
  font-weight: 850;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.tabla td {
  padding: 14px 18px;
  border-bottom: 1px solid #f2f5f3;
  vertical-align: middle;
}

.tabla tbody tr:hover {
  background: #fbfcfb;
}

.text-right { text-align: right; }
.text-muted { color: #5a625d; font-weight: 600; }

/* ALUMNO */
.col-alumno {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar-sm {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #eaf5ee;
  color: #247548;
  font-weight: 800;
  font-size: 11px;
}

.info-alumno {
  display: flex;
  flex-direction: column;
}

.info-alumno strong {
  color: #272e2a;
  font-size: 11px;
}

.info-alumno small {
  color: #939a96;
  font-size: 9px;
}

/* BADGES */
.badge-docs {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  color: #4a524d;
}

.badge-estado {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 9px;
  font-weight: 800;
  text-transform: capitalize;
}

.badge-estado.warning { background: #fff5da; color: #956516; }
.badge-estado.info { background: #eaf3fa; color: #356b91; }
.badge-estado.success { background: #eaf5ee; color: #247548; }
.badge-estado.danger { background: #faedf1; color: #85243d; }
.badge-estado.purple { background: #f3ebfc; color: #6b21a8; }
.badge-estado.neutral { background: #f0f2f1; color: #505753; }

/* ACCIONES */
.acciones {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.btn-detalle {
  padding: 6px 12px;
  border: 1px solid #e1e5e2;
  border-radius: 18px;
  background: #fff;
  color: #247548;
  font-size: 10px;
  font-weight: 750;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-detalle:hover {
  background: #edf5f0;
  border-color: #247548;
}

.btn-confirmar {
  padding: 6px 14px;
  border: 0;
  border-radius: 18px;
  background: #00875a;
  color: #fff;
  font-size: 10px;
  font-weight: 750;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn-confirmar:hover {
  background: #006644;
}

/* ESTADOS VACÍOS */
.estado-vacio {
  padding: 50px 20px;
  text-align: center;
  color: #8c938f;
}

.vacio-icon {
  width: 52px;
  height: 52px;
  margin: 0 auto 12px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: #f4f6f5;
  color: #9ca29f;
}

.estado-vacio strong {
  display: block;
  color: #39413d;
  font-size: 13px;
}

.estado-vacio p {
  margin: 4px 0 0;
  font-size: 10px;
}

.spinner {
  width: 28px;
  height: 28px;
  margin: 0 auto 12px;
  border: 3px solid #e2e6e3;
  border-top-color: #247548;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>