<script setup>
const props = defineProps({
  solicitudes: { type: Array, default: () => [] },
  cargando: { type: Boolean, default: false }
})

const emit = defineEmits(['seleccionar'])

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
  }
  return clases[valor] || 'neutral'
}
</script>

<template>
  <div class="requests-card">
    <!-- ESTADO: CARGANDO -->
    <div v-if="cargando" class="loading-box">
      <div class="spinner"></div>
      <strong>Consultando solicitudes</strong>
      <span>Espera un momento...</span>
    </div>

    <!-- ESTADO: VACÍO -->
    <div v-else-if="!solicitudes || solicitudes.length === 0" class="empty-box">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 2h9l5 5v15H6z" />
          <path d="M14 2v6h6" />
        </svg>
      </div>
      <strong>No hay solicitudes registradas</strong>
      <span>Aún no existen registros en esta sección.</span>
    </div>

    <!-- TABLA DE DATOS -->
    <div v-else class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Alumno</th>
            <th>Matrícula</th>
            <th>Grupo</th>
            <th>Periodo</th>
            <th>Documentos</th>
            <th>Descuento</th>
            <th>Estado</th>
            <th class="text-right">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="solicitud in solicitudes" :key="solicitud.id">
            <!-- 1. ALUMNO (Avatar, Nombre y Folio) -->
            <td>
              <div class="student-cell">
                <div class="student-avatar">
                  {{ obtenerAlumno(solicitud).nombre.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <strong>{{ obtenerAlumno(solicitud).nombre }}</strong>
                  <span>{{ obtenerFolio(solicitud) }}</span>
                </div>
              </div>
            </td>

            <!-- 2. MATRÍCULA -->
            <td>{{ obtenerAlumno(solicitud).matricula }}</td>

            <!-- 3. GRUPO -->
            <td>{{ obtenerGrupo(solicitud) }}</td>

            <!-- 4. PERIODO -->
            <td>{{ obtenerPeriodo(solicitud) }}</td>

            <!-- 5. DOCUMENTOS -->
            <td>
              <div class="docs-count">
                {{ obtenerDocumentos(solicitud).length }}
                <span>archivo(s)</span>
              </div>
            </td>

            <!-- 6. PORCENTAJE DESCUENTO -->
            <td>
              <div class="discount-badge" v-if="solicitud.porcentaje_descuento">
                {{ solicitud.porcentaje_descuento }}%
              </div>
              <span v-else class="no-discount">N/A</span>
            </td>

            <!-- 7. ESTADO -->
            <td>
              <span class="status-badge" :class="claseEstado(solicitud.estado || solicitud.status)">
                {{ textoEstado(solicitud.estado || solicitud.status) }}
              </span>
            </td>

            <!-- 8. ACCIONES -->
            <td class="text-right">
              <button 
                type="button" 
                class="review-button" 
                @click="$emit('seleccionar', solicitud)"
              >
                Ver detalle
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.requests-card {
  border: 1px solid #e3e7e4;
  border-radius: 21px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 13px 38px rgba(28, 40, 33, .05);
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 950px;
  border-collapse: collapse;
}

thead th {
  padding: 14px 17px;
  background: #fafbfa;
  color: #969c98;
  border-bottom: 1px solid #ecefec;
  text-align: left;
  font-size: 8px;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .08em;
}

tbody td {
  padding: 14px 17px;
  border-bottom: 1px solid #f0f2f1;
  color: #59605c;
  font-size: 10px;
  vertical-align: middle;
}

tbody tr:last-child td {
  border-bottom: 0;
}

tbody tr:hover {
  background: #fcfdfc;
}

.text-right {
  text-align: right;
}

/* Celda de Alumno con Avatar */
.student-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.student-avatar {
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: #edf5f0;
  color: #247548;
  font-size: 11px;
  font-weight: 850;
}

.student-cell div:last-child {
  display: flex;
  flex-direction: column;
}

.student-cell strong {
  color: #333a36;
  font-size: 10px;
}

.student-cell span {
  margin-top: 3px;
  color: #a0a5a2;
  font-size: 8px;
}

/* Documentos contador */
.docs-count {
  display: flex;
  flex-direction: column;
  font-weight: 750;
  color: #434b47;
}

.docs-count span {
  margin-top: 2px;
  color: #9fa5a1;
  font-size: 8px;
  font-weight: 500;
}

/* Badge Descuento */
.discount-badge {
  display: inline-block;
  font-weight: 850;
  color: #216841;
}

.no-discount {
  color: #a0a5a2;
  font-size: 9px;
}

/* Badges de Estado */
.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 11px;
  border-radius: 99px;
  font-size: 8px;
  font-weight: 800;
  white-space: nowrap;
}

.status-badge.warning { color: #90611b; background: #fff4d7; }
.status-badge.info { color: #32688f; background: #e9f3fa; }
.status-badge.success { color: #216841; background: #e8f5ed; }
.status-badge.danger { color: #86253d; background: #faeaf0; }
.status-badge.purple { color: #6e4992; background: #f2ebf8; }
.status-badge.neutral { color: #707773; background: #f0f2f1; }

/* Botón de Acción */
.review-button {
  height: 32px;
  padding: 0 14px;
  border: 1px solid #dbe5de;
  border-radius: 99px;
  background: #f4f8f5;
  color: #267348;
  font-size: 8px;
  font-weight: 850;
  cursor: pointer;
  transition: background 0.2s ease;
}

.review-button:hover {
  background: #eaf4ed;
}

/* Estados de Carga y Vacío */
.loading-box,
.empty-box {
  min-height: 250px;
  padding: 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.loading-box strong,
.empty-box strong {
  margin-top: 13px;
  color: #454d49;
  font-size: 12px;
}

.loading-box span,
.empty-box span {
  margin-top: 5px;
  color: #969d99;
  font-size: 9px;
}

.spinner {
  width: 35px;
  height: 35px;
  border: 3px solid #e8edea;
  border-top-color: #247548;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  width: 45px;
  height: 45px;
  display: grid;
  place-items: center;
  border-radius: 13px;
  color: #76807a;
  background: #f0f3f1;
}
</style>