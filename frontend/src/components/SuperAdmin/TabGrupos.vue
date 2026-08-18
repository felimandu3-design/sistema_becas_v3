<script setup>
import { ref } from 'vue'
import api from '../../api/axios'

const props = defineProps({
  grupos: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] },
  periodos: { type: Array, default: () => [] },
  staff: { type: Array, default: () => [] },
  alumnos: { type: Array, default: () => [] },
  periodoActivoId: { type: [String, Number], default: '' }
})

const emit = defineEmits(['actualizar', 'toast', 'ver-alumnos'])

const filtroCarrera = ref('todos')
const filtroPeriodo = ref('todos')

const modal = ref(null)
const grupoForm = ref({})

function estado(valor) { return String(valor || '').trim().toUpperCase() }
function nombreEstado(valor) { return estado(valor) === 'ACTIVO' ? 'Activo' : 'Inactivo' }
function claseEstado(valor) { return estado(valor) === 'ACTIVO' ? 'success' : 'danger' }
function carreraPorId(id) { return props.carreras.find(c => String(c.id) === String(id)) }

function nuevoGrupo() {
  grupoForm.value = { id: null, nombre: '', carrera_id: '', periodo_id: props.periodoActivoId || '', tutor_id: '', cuatrimestre: '', turno: 'MATUTINO', estado: 'ACTIVO' }
  modal.value = 'grupo'
}

function editarGrupo(g) {
  grupoForm.value = { id: g.id, nombre: g.nombre || '', carrera_id: g.carrera_id || '', periodo_id: g.periodo_id || '', tutor_id: g.tutor_id || '', cuatrimestre: g.cuatrimestre || '', turno: g.turno || 'MATUTINO', estado: g.estado || 'ACTIVO' }
  modal.value = 'grupo'
}

async function guardarGrupo() {
  const f = grupoForm.value
  const payload = { nombre: f.nombre, carrera_id: f.carrera_id, periodo_id: f.periodo_id || null, tutor_id: f.tutor_id || null, cuatrimestre: f.cuatrimestre ? Number(f.cuatrimestre) : null, turno: f.turno, estado: f.estado }
  try {
    if (f.id) { await api.patch(`/master/grupos/${f.id}`, payload) } 
    else { await api.post('/master/grupos', payload) }
    modal.value = null
    emit('actualizar')
    emit('toast', 'Grupo guardado correctamente.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo guardar el grupo.', 'error')
  }
}

async function eliminarGrupo(g) {
  if (!confirm(`¿Eliminar el grupo "${g.nombre}"?`)) return
  try {
    await api.delete(`/master/grupos/${g.id}`)
    emit('actualizar')
    emit('toast', 'Grupo eliminado.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'El grupo tiene alumnos asignados.', 'error')
  }
}
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">ORGANIZACIÓN ACADÉMICA</span>
        <h1>Grupos</h1>
        <p>Organiza alumnos por carrera, periodo y tutor.</p>
      </div>
      <button class="primary" @click="nuevoGrupo">+ Nuevo grupo</button>
    </div>

    <div class="filters">
      <select v-model="filtroCarrera">
        <option value="todos">Todas las carreras</option>
        <option v-for="c in props.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
      </select>
      <select v-model="filtroPeriodo">
        <option value="todos">Todos los periodos</option>
        <option v-for="p in props.periodos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
      </select>
    </div>

    <div class="card-grid">
      <article v-for="g in props.grupos.filter(g => (filtroCarrera === 'todos' || String(g.carrera_id) === String(filtroCarrera)) && (filtroPeriodo === 'todos' || String(g.periodo_id) === String(filtroPeriodo)))" :key="g.id" class="academic-card">
        <div class="academic-top">
          <span class="badge" :class="claseEstado(g.estado)">{{ nombreEstado(g.estado) }}</span>
          <span class="code">{{ g.turno || 'MATUTINO' }}</span>
        </div>
        <h3>{{ g.nombre }}</h3>
        <p>{{ g.carrera?.nombre || carreraPorId(g.carrera_id)?.nombre || 'Sin carrera' }}</p>

        <div class="group-info">
          <span>Cuatrimestre: <b>{{ g.cuatrimestre || '—' }}</b></span>
          <span>Tutor: <b>{{ g.tutor?.name || 'Sin asignar' }}</b></span>
          <span>Alumnos: <b>{{ g.alumnos_count ?? props.alumnos.filter(a => String(a.grupo_id) === String(g.id)).length }}</b></span>
        </div>

        <div class="actions full-actions">
          <button @click="editarGrupo(g)">Editar grupo</button>
          <button @click="emit('ver-alumnos', g.id)">Ver alumnos</button>
          <button class="danger-text" @click="eliminarGrupo(g)">Eliminar</button>
        </div>
      </article>
    </div>

    <!-- MODAL GRUPO -->
    <div v-if="modal === 'grupo'" class="overlay">
      <form class="modal large" @submit.prevent="guardarGrupo">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>{{ grupoForm.id ? 'Editar grupo' : 'Nuevo grupo' }}</h2>
        <div class="form-grid">
          <label>Nombre <input v-model="grupoForm.nombre" placeholder="8ITI1" required /></label>
          <label>Carrera 
            <select v-model="grupoForm.carrera_id" required>
              <option value="">Selecciona</option>
              <option v-for="c in props.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
            </select>
          </label>
          <label>Periodo 
            <select v-model="grupoForm.periodo_id">
              <option value="">Sin periodo</option>
              <option v-for="p in props.periodos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>
          </label>
          <label>Tutor 
            <select v-model="grupoForm.tutor_id">
              <option value="">Sin tutor</option>
              <option v-for="u in props.staff.filter(u => u.role === 'profesor')" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </label>
          <label>Cuatrimestre <input v-model="grupoForm.cuatrimestre" type="number" min="1" max="12" /></label>
          <label>Turno 
            <select v-model="grupoForm.turno">
              <option value="MATUTINO">Matutino</option>
              <option value="VESPERTINO">Vespertino</option>
              <option value="MIXTO">Mixto</option>
            </select>
          </label>
          <label>Estado 
            <select v-model="grupoForm.estado">
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
            </select>
          </label>
        </div>
        <button class="primary submit">Guardar grupo</button>
      </form>
    </div>
  </div>
</template>