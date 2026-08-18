<script setup>
import { ref } from 'vue'
import api from '../../api/axios'

const props = defineProps({
  periodos: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast'])

const modal = ref(null)
const periodoForm = ref({})

function estado(valor) { return String(valor || '').trim().toUpperCase() }
function nombreEstado(valor) { return estado(valor) === 'ACTIVO' ? 'Activo' : 'Cerrado' }
function claseEstado(valor) { return estado(valor) === 'ACTIVO' ? 'success' : 'danger' }
function fecha(valor) {
  if (!valor) return '—'
  const d = new Date(valor)
  return Number.isNaN(d.getTime()) ? valor : d.toLocaleDateString('es-MX')
}

function nuevoPeriodo() {
  periodoForm.value = { id: null, nombre: '', fecha_inicio: '', fecha_fin: '', estado: 'ACTIVO' }
  modal.value = 'periodo'
}

function editarPeriodo(p) {
  periodoForm.value = {
    id: p.id, nombre: p.nombre, fecha_inicio: String(p.fecha_inicio || '').slice(0, 10),
    fecha_fin: String(p.fecha_fin || '').slice(0, 10), estado: p.estado || 'ACTIVO'
  }
  modal.value = 'periodo'
}

async function guardarPeriodo() {
  const f = periodoForm.value
  try {
    if (f.id) { await api.patch(`/master/periodos/${f.id}`, f) } 
    else { await api.post('/master/periodos', f) }
    modal.value = null
    emit('actualizar')
    emit('toast', 'Periodo guardado.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo guardar el periodo.', 'error')
  }
}
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">CICLOS ESCOLARES</span>
        <h1>Periodos</h1>
        <p>Administra los ciclos académicos.</p>
      </div>
      <button class="primary" @click="nuevoPeriodo">+ Nuevo periodo</button>
    </div>

    <div class="records">
      <article v-for="p in props.periodos" :key="p.id" class="record">
        <div>
          <span class="badge" :class="claseEstado(p.estado)">{{ nombreEstado(p.estado) }}</span>
          <h3>{{ p.nombre }}</h3>
          <p>{{ fecha(p.fecha_inicio) }} — {{ fecha(p.fecha_fin) }}</p>
        </div>
        <div class="actions">
          <button @click="editarPeriodo(p)">Editar</button>
        </div>
      </article>
    </div>

    <!-- MODAL PERIODO -->
    <div v-if="modal === 'periodo'" class="overlay">
      <form class="modal" @submit.prevent="guardarPeriodo">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>{{ periodoForm.id ? 'Editar periodo' : 'Nuevo periodo' }}</h2>
        <label>Nombre <input v-model="periodoForm.nombre" required /></label>
        <label>Inicio <input v-model="periodoForm.fecha_inicio" type="date" required /></label>
        <label>Fin <input v-model="periodoForm.fecha_fin" type="date" required /></label>
        <label>Estado 
          <select v-model="periodoForm.estado">
            <option value="ACTIVO">Activo</option>
            <option value="CERRADO">Cerrado</option>
          </select>
        </label>
        <button class="primary submit">Guardar</button>
      </form>
    </div>
  </div>
</template>