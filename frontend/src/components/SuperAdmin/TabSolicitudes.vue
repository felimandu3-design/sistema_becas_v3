<script setup>
import { ref, computed } from 'vue'

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

const estados = [
  'PENDIENTE', 'EN_REVISION', 'DOCUMENTACION_INCOMPLETA', 'ACEPTADA', 'RECHAZADA'
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

// 5. El motor de búsqueda y filtros
const solicitudesFiltradas = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return props.solicitudes.filter(s => {
    const alumno = alumnoDe(s)
    const idPeriodo = s?.convocatoria?.periodo_id || s?.convocatoria?.periodo?.id
    const idCarrera = alumno?.carrera_id || s?.carrera_id
    const estadoSolicitud = estado(s.estado || s.estatus)
    
    const universo = [alumno.name, alumno.matricula, alumno.email, folio(s), carreraSolicitud(s)].filter(Boolean).join(' ').toLowerCase()

    return (
      (filtroPeriodo.value === 'todos' || String(idPeriodo) === String(filtroPeriodo.value)) &&
      (filtroCarrera.value === 'todos' || String(idCarrera) === String(filtroCarrera.value)) &&
      (filtroEstado.value === 'todos' || estadoSolicitud === filtroEstado.value) &&
      (!q || universo.includes(q))
    )
  })
})
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
          @click="emit('descargar-excel', props.convocatoriaVigente.id)"
        >
          <svg v-if="!props.descargandoExcel" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          <svg v-else class="spin-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
          <span>{{ props.descargandoExcel ? 'Generando...' : 'Padrón (Excel)' }}</span>
        </button>

        <!-- BOTÓN DE PUBLICAR CON ICONO DE ALTAVOZ / CHECK -->
        <button 
          class="btn-solid"
          :class="{ 'published': props.convocatoriaVigente.resultados_publicados }"
          :disabled="Boolean(props.convocatoriaVigente.resultados_publicados) || props.publicandoResultados"
          @click="emit('publicar-resultados', props.convocatoriaVigente.id)"
        >
          <svg v-if="props.convocatoriaVigente.resultados_publicados" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
          <span>{{ props.publicandoResultados ? 'Publicando...' : (props.convocatoriaVigente.resultados_publicados ? 'Publicados' : 'Publicar') }}</span>
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
          <th>Carrera</th>
          <th>Periodo</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in solicitudesFiltradas" :key="s.id">
          <td>{{ folio(s) }}</td>
          <td><strong>{{ alumnoDe(s).name || 'Alumno' }}</strong></td>
          <td>{{ alumnoDe(s).matricula || '—' }}</td>
          <td>{{ carreraSolicitud(s) }}</td>
          <td>{{ periodoSolicitud(s) }}</td>
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
      </tbody>
    </table>
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

/* Animación de carga para el icono */
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
</style>