<script setup>
import { ref, computed } from 'vue'

// 1. Recibimos los datos del Padre
const props = defineProps({
  solicitudes: { type: Array, default: () => [] },
  periodos: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] }
})

// 2. Le avisamos al Padre cuando queramos abrir un expediente
const emit = defineEmits(['abrir-solicitud'])

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
  <div class="heading">
    <div>
      <span class="eyebrow">EXPEDIENTES</span>
      <h1>Solicitudes</h1>
      <p>Supervisa todas las carreras.</p>
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
          
          <!-- AQUÍ SE IMPRIME LA MATRÍCULA -->
          <td>{{ alumnoDe(s).matricula || '—' }}</td>
          
          <td>{{ carreraSolicitud(s) }}</td>
          <td>{{ periodoSolicitud(s) }}</td>
          <td>
            <span class="badge" :class="claseEstado(s.estado || s.estatus)">
              {{ nombreEstado(s.estado || s.estatus) }}
            </span>
          </td>
          <td>
            <!-- Emitimos el evento al Padre para que abra el modal -->
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
/* Aquí pegaremos los estilos propios de las tablas en el futuro si lo necesitas */
</style>