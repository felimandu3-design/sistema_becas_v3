<script setup>
import { ref } from 'vue'
import api from '../../api/axios'

const props = defineProps({
  convocatorias: { type: Array, default: () => [] },
  periodos: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast'])

const modal = ref(null)
const convocatoriaForm = ref({})

// Helpers locales
function estado(valor) { return String(valor || '').trim().toUpperCase() }
function nombreEstado(valor) {
  const mapa = { BORRADOR: 'Borrador', PUBLICADA: 'Publicada', CERRADA: 'Cerrada' }
  return mapa[estado(valor)] || valor || 'Sin estado'
}
function claseEstado(valor) {
  const v = estado(valor)
  if (v === 'PUBLICADA') return 'success'
  if (v === 'CERRADA') return 'danger'
  return 'warning'
}
function fecha(valor) {
  if (!valor) return '—'
  const d = new Date(valor)
  return Number.isNaN(d.getTime()) ? valor : d.toLocaleDateString('es-MX')
}
function urlArchivo(c) {
  const ruta = c?.archivo_url || c?.pdf_url || c?.archivo
  if (!ruta) return null
  if (String(ruta).startsWith('http')) return ruta
  return `http://127.0.0.1:8000/storage/${ruta}`
}

// Lógica de Convocatorias
function nuevaConvocatoria() {
  convocatoriaForm.value = { id: null, periodo_id: '', nombre: '', descripcion: '', requisitos: '', promedio_minimo: 8, fecha_inicio: '', fecha_cierre: '', estado: 'BORRADOR', archivo: null }
  modal.value = 'convocatoria'
}

function editarConvocatoria(c) {
  convocatoriaForm.value = {
    id: c.id, periodo_id: c.periodo_id || c.periodo?.id || '', nombre: c.nombre || c.titulo || '', descripcion: c.descripcion || '',
    requisitos: c.requisitos || '', promedio_minimo: c.promedio_minimo ?? 8, fecha_inicio: String(c.fecha_inicio || '').slice(0, 10),
    fecha_cierre: String(c.fecha_cierre || '').slice(0, 10), estado: c.estado || 'BORRADOR', archivo: null
  }
  modal.value = 'convocatoria'
}

function seleccionarPdf(evento) {
  convocatoriaForm.value.archivo = evento.target.files?.[0] || null
}

async function guardarConvocatoria() {
  const f = convocatoriaForm.value
  const payload = { periodo_id: f.periodo_id, nombre: f.nombre, descripcion: f.descripcion, requisitos: f.requisitos, promedio_minimo: Number(f.promedio_minimo), fecha_inicio: f.fecha_inicio, fecha_cierre: f.fecha_cierre, estado: f.estado }
  try {
    let id = f.id
    if (id) {
      await api.patch(`/master/convocatorias/${id}`, payload)
    } else {
      const { data } = await api.post('/master/convocatorias', payload)
      id = data?.data?.id || data?.convocatoria?.id
    }
    if (f.archivo && id) {
      const form = new FormData()
      form.append('archivo', f.archivo)
      await api.post(`/master/convocatorias/${id}/archivo`, form)
    }
    modal.value = null
    emit('actualizar')
    emit('toast', 'Convocatoria guardada correctamente.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo guardar la convocatoria.', 'error')
  }
}

async function accionConvocatoria(c, accion) {
  try {
    if (accion === 'eliminar') {
      if (!confirm(`¿Eliminar "${c.nombre}"?`)) return
      await api.delete(`/master/convocatorias/${c.id}`)
    } else {
      await api.patch(`/master/convocatorias/${c.id}/${accion}`)
    }
    emit('actualizar')
    emit('toast', 'Convocatoria actualizada.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No fue posible realizar la operación.', 'error')
  }
}
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">PUBLICACIÓN</span>
        <h1>Convocatorias</h1>
        <p>Crea, edita, publica y administra documentos.</p>
      </div>
      <button class="primary" @click="nuevaConvocatoria">+ Nueva convocatoria</button>
    </div>

    <div class="records">
      <article v-for="c in props.convocatorias" :key="c.id" class="record">
        <div>
          <span class="badge" :class="claseEstado(c.estado)">{{ nombreEstado(c.estado) }}</span>
          <h3>{{ c.nombre }}</h3>
          <p>{{ c.periodo?.nombre || 'Sin periodo' }} · {{ fecha(c.fecha_inicio) }} — {{ fecha(c.fecha_cierre) }}</p>
        </div>
        <div class="actions">
          <a v-if="urlArchivo(c)" :href="urlArchivo(c)" target="_blank" class="action-link">Ver PDF</a>
          <button @click="editarConvocatoria(c)">Editar</button>
          <button v-if="estado(c.estado) !== 'PUBLICADA'" class="green-text" @click="accionConvocatoria(c, 'publicar')">Publicar</button>
          <button v-else @click="accionConvocatoria(c, 'cerrar')">Cerrar</button>
          <button class="danger-text" @click="accionConvocatoria(c, 'eliminar')">Eliminar</button>
        </div>
      </article>
    </div>

    <!-- MODAL CONVOCATORIA -->
    <div v-if="modal === 'convocatoria'" class="overlay">
      <form class="modal large" @submit.prevent="guardarConvocatoria">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>{{ convocatoriaForm.id ? 'Editar convocatoria' : 'Nueva convocatoria' }}</h2>
        <div class="form-grid">
          <label>Nombre <input v-model="convocatoriaForm.nombre" required /></label>
          <label>Periodo 
            <select v-model="convocatoriaForm.periodo_id" required>
              <option value="">Selecciona</option>
              <option v-for="p in props.periodos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>
          </label>
          <label class="full">Descripción <textarea v-model="convocatoriaForm.descripcion" required></textarea></label>
          <label class="full">Requisitos <textarea v-model="convocatoriaForm.requisitos" required></textarea></label>
          <label>Promedio mínimo <input v-model="convocatoriaForm.promedio_minimo" type="number" min="0" max="10" step=".1" /></label>
          <label>Estado 
            <select v-model="convocatoriaForm.estado">
              <option value="BORRADOR">Borrador</option>
              <option value="PUBLICADA">Publicada</option>
              <option value="CERRADA">Cerrada</option>
            </select>
          </label>
          <label>Inicio <input v-model="convocatoriaForm.fecha_inicio" type="date" required /></label>
          <label>Cierre <input v-model="convocatoriaForm.fecha_cierre" type="date" required /></label>
          <label class="full">PDF oficial <input type="file" accept="application/pdf" @change="seleccionarPdf" /></label>
        </div>
        <button class="primary submit">Guardar convocatoria</button>
      </form>
    </div>
  </div>
</template>