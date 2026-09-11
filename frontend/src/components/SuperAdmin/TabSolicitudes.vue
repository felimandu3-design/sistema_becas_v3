<script setup>
import { ref, computed, watch } from 'vue'

// 1. Recibimos los datos y estados del Padre (Dashboard)
const props = defineProps({
  solicitudes: { type: Array, default: () => [] },
  periodos: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] },
  convocatoriaVigente: { type: Object, default: null },
  publicandoResultados: { type: Boolean, default: false },
  descargandoExcel: { type: Boolean, default: false }
})

// 2. Le avisamos al Padre cuando interactuamos con botones
const emit = defineEmits(['abrir-solicitud', 'publicar-resultados', 'descargar-excel'])

// 3. Variables exclusivas de esta tabla
const busqueda = ref('')
const filtroPeriodo = ref('todos')
const filtroCarrera = ref('todos')
const filtroEstado = ref('todos')

// --- VARIABLES DE PAGINACIÓN ---
const paginaActual = ref(1)
const porPagina = ref(50)

const estados = [
  'PENDIENTE', 'ACEPTADA', 'RECHAZADA'
]

// 4. Funciones de formato locales
function estado(valor) { return String(valor || '').trim().toUpperCase() }
function nombreEstado(valor) {
  const mapa = { PENDIENTE: 'Pendiente', EN_REVISION: 'En revisión', DOCUMENTACION_INCOMPLETA: 'Docs. incompletos', ACEPTADA: 'Aceptada', RECHAZADA: 'Rechazada' }
  return mapa[estado(valor)] || valor || 'Sin estado'
}
function claseEstado(valor) {
  const v = estado(valor)
  if (v === 'ACEPTADA') return 'success'
  if (v === 'RECHAZADA') return 'danger'
  if (v === 'EN_REVISION') return 'info'
  if (v === 'DOCUMENTACION_INCOMPLETA') return 'purple'
  return 'warning'
}
function alumnoDe(s) { return s?.usuario || s?.user || s?.alumno || {} }
function carreraPorId(id) { return props.carreras.find(c => String(c.id) === String(id)) }
function carreraSolicitud(s) {
  const alumno = alumnoDe(s)
  return alumno?.carrera?.nombre || carreraPorId(alumno?.carrera_id || s?.carrera_id)?.nombre || 'Sin carrera'
}
function periodoSolicitud(s) { return s?.convocatoria?.periodo?.nombre || s?.periodo?.nombre || 'Sin periodo' }
function folio(s) { return s?.folio || `BEC-${String(s?.id || 0).padStart(5, '0')}` }

// --- FUNCIONES AUXILIARES ---
function grupoSolicitud(s) {
  const alumno = alumnoDe(s)
  return s?.grupo_relacion?.nombre || s?.grupoRelacion?.nombre || alumno?.grupo_relacion?.nombre || alumno?.grupoRelacion?.nombre || '—'
}
function totalDocumentos(s) {
  return Array.isArray(s?.documentos) ? s.documentos.length : 0
}
function descuentoSolicitud(s) {
  const porcentaje = s?.porcentaje_beca || s?.porcentajeBeca || s?.porcentaje
  return porcentaje ? `${Math.round(porcentaje)}%` : 'N/A'
}

// 5. Motor de búsqueda y filtros
const solicitudesFiltradas = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return props.solicitudes.filter(s => {
    const alumno = alumnoDe(s)
    const idPeriodo = s?.convocatoria?.periodo_id || s?.convocatoria?.periodo?.id
    const idCarrera = alumno?.carrera_id || s?.carrera_id
    const estadoSolicitud = estado(s.estado || s.estatus)
    
    const universo = [alumno.name, alumno.matricula, alumno.email, folio(s), carreraSolicitud(s), grupoSolicitud(s)].filter(Boolean).join(' ').toLowerCase()

    return (
      (filtroPeriodo.value === 'todos' || String(idPeriodo) === String(filtroPeriodo.value)) &&
      (filtroCarrera.value === 'todos' || String(idCarrera) === String(filtroCarrera.value)) &&
      (filtroEstado.value === 'todos' || estadoSolicitud === filtroEstado.value) &&
      (!q || universo.includes(q))
    )
  })
})

// --- LÓGICA DE PAGINACIÓN ---
const totalPaginas = computed(() => Math.ceil(solicitudesFiltradas.value.length / porPagina.value) || 1)

const solicitudesPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * porPagina.value
  const fin = inicio + porPagina.value
  return solicitudesFiltradas.value.slice(inicio, fin)
})

// Reiniciar a la página 1 si cambian los filtros o la búsqueda
watch([busqueda, filtroPeriodo, filtroCarrera, filtroEstado], () => {
  paginaActual.value = 1
})

function irAPagina(p) {
  if (p >= 1 && p <= totalPaginas.value) {
    paginaActual.value = p
  }
}
</script>

<template>
  <div class="tab-header">
    <div class="titles">
      <span class="eyebrow">EXPEDIENTES</span>
      <h1>Solicitudes</h1>
      <p>Supervisa los expedientes de todas las carreras y publica los resultados.</p>
    </div>

    <!-- WIDGET DE ACCIONES ELEGANTE -->
    <div v-if="props.convocatoriaVigente" class="action-widget">
      <div class="widget-top">
        <span class="widget-label">Convocatoria Vigente</span>
        <span class="widget-title">{{ props.convocatoriaVigente.nombre || props.convocatoriaVigente.titulo || 'Activa' }}</span>
      </div>
      
      <div class="widget-buttons">
        <!-- BOTÓN DE EXCEL CON ICONO DE DESCARGA -->
<button 
  class="btn-outline"
  :disabled="props.descargandoExcel"
  @click="emit('descargar-excel', { 
    convocatoria_id: props.convocatoriaVigente.id,
    periodo_id: filtroPeriodo,
    carrera_id: filtroCarrera,
    estado: filtroEstado,
    buscar: busqueda
  })"
>
  <svg v-if="!props.descargandoExcel" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
  <svg v-else class="spin-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
  <span>{{ props.descargandoExcel ? 'Generando...' : 'Padrón (Excel)' }}</span>
</button>

        <!-- BOTÓN DE PUBLICAR / CHECK -->
        <button 
        class="btn-solid"
        :class="{ 'published': props.convocatoriaVigente.resultados_publicados }"
        :disabled="Boolean(props.convocatoriaVigente.resultados_publicados) || props.publicandoResultados"
        @click="emit('publicar-resultados', props.convocatoriaVigente.id)"
        >   
       <span>{{ props.publicandoResultados ? 'Publicando' : (props.convocatoriaVigente.resultados_publicados ? 'Publicados' : '📢Publicar') }}</span>
        </button>
      </div>
    </div>
  </div>

  <div class="filters four">
    <input v-model="busqueda" placeholder="Buscar alumno, matrícula o folio..." />

    <select v-model="filtroPeriodo">
      <option value="todos">Todos los periodos</option>
      <option v-for="p in props.periodos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
    </select>

    <select v-model="filtroCarrera">
      <option value="todos">Todas las carreras</option>
      <option v-for="c in props.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
    </select>

    <select v-model="filtroEstado">
      <option value="todos">Todos los estados</option>
      <option v-for="e in estados" :key="e" :value="e">{{ nombreEstado(e) }}</option>
    </select>
  </div>

  <div class="panel table-wrap">
    <table>
      <thead>
        <tr>
          <th>Folio</th>
          <th>Alumno</th>
          <th>Matrícula</th>
          <th>Grupo</th>
          <th>Periodo</th>
          <th>Documentos</th>
          <th>Descuento</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in solicitudesPaginadas" :key="s.id">
          <td>{{ folio(s) }}</td>
          <td><strong>{{ alumnoDe(s).name || 'Alumno' }}</strong></td>
          <td>{{ alumnoDe(s).matricula || '—' }}</td>
          <td>{{ grupoSolicitud(s) }}</td>
          <td>{{ periodoSolicitud(s) }}</td>
          <td>
            <div>{{ totalDocumentos(s) }}</div>
            <small style="color: #8a948e; font-size: 11px;">archivo(s)</small>
          </td>
          <td>
            <span :class="['discount-text', { 'active': descuentoSolicitud(s) !== 'N/A' }]">
              {{ descuentoSolicitud(s) }}
            </span>
          </td>
          <td>
            <span class="badge" :class="claseEstado(s.estado || s.estatus)">
              {{ nombreEstado(s.estado || s.estatus) }}
            </span>
          </td>
          <td>
            <button class="table-button" @click="emit('abrir-solicitud', s)">
              Abrir expediente
            </button>
          </td>
        </tr>
        <tr v-if="solicitudesPaginadas.length === 0">
          <td colspan="9" style="text-align: center; padding: 25px; color: #8a948e;">
            No se encontraron solicitudes.
          </td>
        </tr>
      </tbody>
    </table>

    <!-- BARRA DE PAGINACIÓN -->
    <div class="pagination-bar">
      <div class="pagination-info">
        Mostrando {{ solicitudesPaginadas.length }} de {{ solicitudesFiltradas.length }} solicitudes
      </div>

      <div class="pagination-controls" v-if="totalPaginas > 1">
        <button 
          class="page-btn" 
          :disabled="paginaActual === 1" 
          @click="irAPagina(paginaActual - 1)"
        >
          &laquo; Anterior
        </button>

        <button 
          v-for="p in totalPaginas" 
          :key="p" 
          class="page-number" 
          :class="{ active: p === paginaActual }"
          @click="irAPagina(p)"
        >
          {{ p }}
        </button>

        <button 
          class="page-btn" 
          :disabled="paginaActual === totalPaginas" 
          @click="irAPagina(paginaActual + 1)"
        >
          Siguiente &raquo;
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 22px;
  gap: 20px;
}

.titles .eyebrow {
  display: block;
  color: #8a948e;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: .14em;
}

.titles h1 {
  margin: 5px 0;
  font-size: 32px;
  color: #27312b;
  letter-spacing: -.03em;
}

.titles p {
  margin: 0;
  color: #748078;
  font-size: 14px;
}

/* TARJETA WIDGET */
.action-widget {
  background: #ffffff;
  border: 1px solid #dce4e0;
  border-radius: 14px;
  padding: 14px 18px;
  min-width: 380px;
  box-shadow: 0 4px 15px rgba(20, 40, 30, 0.03);
}

.widget-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid #edf1ef;
}

.widget-label {
  font-size: 10px;
  color: #8a948e;
  font-weight: 850;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.widget-title {
  font-size: 11px;
  font-weight: 800;
  color: #143625;
  background: #eaf1ed;
  padding: 4px 9px;
  border-radius: 6px;
}

.widget-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.btn-outline, .btn-solid {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 7px;
  padding: 10px 14px;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 850;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-outline {
  background: #ffffff;
  border: 1px solid #087846;
  color: #087846;
}

.btn-outline:hover:not(:disabled) {
  background: #f2f8f5;
}

.btn-solid {
  background: #087846;
  border: 1px solid #087846;
  color: #ffffff;
}

.btn-solid:hover:not(:disabled) {
  background: #065c36;
  border-color: #065c36;
}

.btn-solid.published {
  background: #f4f6f5;
  border-color: #dce4e0;
  color: #087846;
  cursor: default;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .tab-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .action-widget {
    width: 100%;
  }
}

.discount-text {
  color: #8a948e;
  font-size: 13px;
}

.discount-text.active {
  color: #087846;
  font-weight: 800;
}

/* PAGINACIÓN */
.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-top: 1px solid #edf1ef;
  background: #fafbfc;
}

.pagination-info {
  font-size: 12px;
  color: #748078;
  font-weight: 600;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 6px;
}

.page-btn, .page-number {
  border: 1px solid #dce4e0;
  background: #ffffff;
  color: #27312b;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-number.active {
  background: #087846;
  color: #ffffff;
  border-color: #087846;
}

.page-btn:hover:not(:disabled), .page-number:hover:not(.active) {
  background: #eaf1ed;
  border-color: #087846;
}
</style>