<script setup>
import { ref, computed } from 'vue'
import { Bar, Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend)

const props = defineProps({
  solicitudes: { type: Array, default: () => [] },
  alumnos: { type: Array, default: () => [] },
  staff: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] },
  grupos: { type: Array, default: () => [] },
  convocatorias: { type: Array, default: () => [] },
  periodos: { type: Array, default: () => [] },
  statsApi: { type: Object, default: () => ({}) },
  alertas: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar'])

const busqueda = ref('')
const filtroPeriodo = ref('todos')
const filtroCarrera = ref('todos')
const filtroEstado = ref('todos')

const estados = ['PENDIENTE', 'EN_REVISION', 'DOCUMENTACION_INCOMPLETA', 'ACEPTADA', 'RECHAZADA']

function estado(valor) { return String(valor || '').trim().toUpperCase() }
function nombreEstado(valor) {
  const mapa = { PENDIENTE: 'Pendiente', EN_REVISION: 'En revisión', DOCUMENTACION_INCOMPLETA: 'Docs. incompletos', ACEPTADA: 'Aceptada', RECHAZADA: 'Rechazada' }
  return mapa[estado(valor)] || valor || 'Sin estado'
}
function alumnoDe(s) { return s?.usuario || s?.user || s?.alumno || {} }
function carreraPorId(id) { return props.carreras.find(c => String(c.id) === String(id)) }
function carreraSolicitud(s) {
  const alumno = alumnoDe(s)
  return alumno?.carrera?.nombre || carreraPorId(alumno?.carrera_id || s?.carrera_id)?.nombre || 'Sin carrera'
}
function folio(s) { return s?.folio || `BEC-${String(s?.id || 0).padStart(5, '0')}` }

const solicitudesFiltradas = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return props.solicitudes.filter(s => {
    const alumno = alumnoDe(s)
    const idPeriodo = s?.convocatoria?.periodo_id || s?.convocatoria?.periodo?.id
    const idCarrera = alumno?.carrera_id || s?.carrera_id
    const estadoSolicitud = estado(s.estado || s.estatus)
    const universo = [alumno.name, alumno.matricula, alumno.email, folio(s), carreraSolicitud(s)].filter(Boolean).join(' ').toLowerCase()
    return (filtroPeriodo.value === 'todos' || String(idPeriodo) === String(filtroPeriodo.value)) &&
           (filtroCarrera.value === 'todos' || String(idCarrera) === String(filtroCarrera.value)) &&
           (filtroEstado.value === 'todos' || estadoSolicitud === filtroEstado.value) &&
           (!q || universo.includes(q))
  })
})

const resumen = computed(() => {
  const lista = solicitudesFiltradas.value
  const contar = e => lista.filter(s => estado(s.estado || s.estatus) === e).length
  return {
    solicitudes: lista.length,
    pendientes: contar('PENDIENTE'),
    revision: contar('EN_REVISION'),
    incompletas: contar('DOCUMENTACION_INCOMPLETA'),
    aceptadas: contar('ACEPTADA'),
    rechazadas: contar('RECHAZADA'),
    alumnos: props.alumnos.length || props.statsApi.alumnos || 0,
    personal: props.staff.length,
    carreras: props.carreras.length,
    grupos: props.grupos.length,
    convocatorias: props.convocatorias.length
  }
})

const periodoActivo = computed(() => props.periodos.find(p => estado(p.estado) === 'ACTIVO') || null)
const convocatoriaVigente = computed(() => props.convocatorias.find(c => estado(c.estado) === 'PUBLICADA') || null)

const chartCarreras = computed(() => {
  const datos = {}
  solicitudesFiltradas.value.forEach(s => {
    const nombre = carreraSolicitud(s)
    datos[nombre] = (datos[nombre] || 0) + 1
  })
  return { labels: Object.keys(datos), datasets: [{ label: 'Solicitudes', data: Object.values(datos), backgroundColor: '#147a4a', borderRadius: 8, maxBarThickness: 42 }] }
})

const chartEstados = computed(() => ({
  labels: ['Pendientes', 'En revisión', 'Docs. incompletos', 'Aceptadas', 'Rechazadas'],
  datasets: [{ data: [resumen.value.pendientes, resumen.value.revision, resumen.value.incompletas, resumen.value.aceptadas, resumen.value.rechazadas], backgroundColor: ['#d99a25', '#3b82b6', '#7754a4', '#147a4a', '#8e2843'], borderWidth: 0 }]
}))

const opcionesBarras = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
const opcionesDona = { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
</script>

<template>
  <div class="heading">
    <div>
      <span class="eyebrow">CENTRO DE CONTROL</span>
      <h1>Resumen institucional</h1>
      <p>Control completo del programa de becas.</p>
    </div>
    <div class="context">
      <div><span>Periodo activo</span><strong>{{ periodoActivo?.nombre || 'Sin periodo' }}</strong></div>
      <div><span>Convocatoria</span><strong>{{ convocatoriaVigente?.nombre || 'Sin publicación' }}</strong></div>
    </div>
  </div>

  <div class="filters">
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
    <button class="secondary" @click="emit('actualizar')">Actualizar</button>
  </div>

  <div class="kpis">
    <article><span>Solicitudes</span><strong>{{ resumen.solicitudes }}</strong><small>Total filtrado</small></article>
    <article class="amber"><span>Pendientes</span><strong>{{ resumen.pendientes }}</strong><small>Requieren atención</small></article>
    <article class="blue"><span>En revisión</span><strong>{{ resumen.revision }}</strong><small>En proceso</small></article>
    <article class="green"><span>Aceptadas</span><strong>{{ resumen.aceptadas }}</strong><small>Aprobadas</small></article>
    <article class="burgundy"><span>Rechazadas</span><strong>{{ resumen.rechazadas }}</strong><small>No aprobadas</small></article>
  </div>

  <div class="charts">
    <article class="panel">
      <div class="panel-title"><span class="eyebrow">DISTRIBUCIÓN</span><h2>Solicitudes por carrera</h2></div>
      <div class="chart">
        <Bar v-if="chartCarreras.labels.length" :data="chartCarreras" :options="opcionesBarras" />
        <div v-else class="empty">Sin datos.</div>
      </div>
    </article>
    <article class="panel">
      <div class="panel-title"><span class="eyebrow">ESTATUS</span><h2>Estado de solicitudes</h2></div>
      <div class="chart">
        <Doughnut v-if="resumen.solicitudes" :data="chartEstados" :options="opcionesDona" />
        <div v-else class="empty">Sin solicitudes.</div>
      </div>
    </article>
  </div>

  <div class="mini-stats">
    <article><span>Alumnos</span><strong>{{ resumen.alumnos }}</strong></article>
    <article><span>Carreras</span><strong>{{ resumen.carreras }}</strong></article>
    <article><span>Grupos</span><strong>{{ resumen.grupos }}</strong></article>
    <article><span>Personal</span><strong>{{ resumen.personal }}</strong></article>
    <article><span>Alertas</span><strong>{{ props.alertas.length }}</strong></article>
  </div>
</template>