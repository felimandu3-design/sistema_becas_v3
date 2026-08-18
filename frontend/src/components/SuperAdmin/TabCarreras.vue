<script setup>
import { ref } from 'vue'
import api from '../../api/axios'

const props = defineProps({
  carreras: { type: Array, default: () => [] },
  alumnos: { type: Array, default: () => [] },
  grupos: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast', 'ver-grupos'])

const modal = ref(null)
const carreraForm = ref({})

function estado(valor) { return String(valor || '').trim().toUpperCase() }
function nombreEstado(valor) { return estado(valor) === 'ACTIVA' ? 'Activa' : 'Inactiva' }
function claseEstado(valor) { return estado(valor) === 'ACTIVA' ? 'success' : 'danger' }

function nuevaCarrera() {
  carreraForm.value = { id: null, nombre: '', clave: '', descripcion: '', estado: 'ACTIVA' }
  modal.value = 'carrera'
}

function editarCarrera(c) {
  carreraForm.value = { id: c.id, nombre: c.nombre || '', clave: c.clave || '', descripcion: c.descripcion || '', estado: c.estado || 'ACTIVA' }
  modal.value = 'carrera'
}

async function guardarCarrera() {
  const f = carreraForm.value
  const payload = { nombre: f.nombre, clave: f.clave || null, descripcion: f.descripcion || null, estado: f.estado }
  try {
    if (f.id) { await api.patch(`/master/carreras/${f.id}`, payload) } 
    else { await api.post('/master/carreras', payload) }
    modal.value = null
    emit('actualizar')
    emit('toast', 'Carrera guardada correctamente.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo guardar la carrera.', 'error')
  }
}

async function eliminarCarrera(c) {
  if (!confirm(`¿Eliminar la carrera "${c.nombre}"?`)) return
  try {
    await api.delete(`/master/carreras/${c.id}`)
    emit('actualizar')
    emit('toast', 'Carrera eliminada.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No puede eliminarse. Prueba marcarla como inactiva.', 'error')
  }
}
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">ESTRUCTURA ACADÉMICA</span>
        <h1>Carreras</h1>
        <p>Administra los programas académicos de la universidad.</p>
      </div>
      <button class="primary" @click="nuevaCarrera">+ Nueva carrera</button>
    </div>

    <div class="card-grid">
      <article v-for="c in props.carreras" :key="c.id" class="academic-card">
        <div class="academic-top">
          <span class="badge" :class="claseEstado(c.estado)">{{ nombreEstado(c.estado || 'ACTIVA') }}</span>
          <span class="code">{{ c.clave || 'SIN CLAVE' }}</span>
        </div>
        <h3>{{ c.nombre }}</h3>
        <p>{{ c.descripcion || 'Programa académico UPTex.' }}</p>

        <div class="academic-stats">
          <div>
            <span>Alumnos</span>
            <strong>{{ c.alumnos_count ?? props.alumnos.filter(a => String(a.carrera_id) === String(c.id)).length }}</strong>
          </div>
          <div>
            <span>Grupos</span>
            <strong>{{ c.grupos_count ?? props.grupos.filter(g => String(g.carrera_id) === String(c.id)).length }}</strong>
          </div>
        </div>

        <div class="actions full-actions">
          <button @click="editarCarrera(c)">Editar</button>
          <button @click="emit('ver-grupos', c.id)">Ver grupos</button>
          <button class="danger-text" @click="eliminarCarrera(c)">Eliminar</button>
        </div>
      </article>
    </div>

    <!-- MODAL CARRERA -->
    <div v-if="modal === 'carrera'" class="overlay">
      <form class="modal" @submit.prevent="guardarCarrera">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>{{ carreraForm.id ? 'Editar carrera' : 'Nueva carrera' }}</h2>
        <label>Nombre <input v-model="carreraForm.nombre" required /></label>
        <label>Clave <input v-model="carreraForm.clave" placeholder="ITI" /></label>
        <label>Descripción <textarea v-model="carreraForm.descripcion"></textarea></label>
        <label>Estado 
          <select v-model="carreraForm.estado">
            <option value="ACTIVA">Activa</option>
            <option value="INACTIVA">Inactiva</option>
          </select>
        </label>
        <button class="primary submit">Guardar carrera</button>
      </form>
    </div>
  </div>
</template>