<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  usuario: Object,
  convocatoria: Object,
  convocatoriaAbierta: Boolean,
  solicitudActiva: Object,
  estadoActual: String,
  carreras: Array,
  guardando: Boolean
})

const emit = defineEmits(['cambiar-seccion', 'submit-solicitud'])

const formSolicitud = ref({
  modalidad: '',
  carrera_id: '',
  grupo: ''
})

const modalidades = [
  { value: 'DISCAPACIDAD', label: 'Discapacidad' },
  { value: 'EXCELENCIA_ACADEMICA', label: 'Excelencia académica' },
  { value: 'SITUACION_SOCIOECONOMICA', label: 'Situación socioeconómica' },
]

// Pre-llenar datos si el usuario ya los tiene en su perfil
watch(() => props.usuario, (u) => {
  if (u && !props.solicitudActiva) {
    formSolicitud.value.carrera_id = u.carrera_id || ''
    formSolicitud.value.grupo = u.grupo?.nombre || u.grupo || ''
  }
}, { immediate: true })

function enviarFormulario() {
  emit('submit-solicitud', formSolicitud.value)
}

// Utilidades locales
function fecha(valor) {
  if (!valor) return '—'
  const d = new Date(valor)
  return Number.isNaN(d.getTime()) ? valor : new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(d)
}

function folio(s) {
  return s?.folio || `BEC-${String(s?.id || 0).padStart(5, '0')}`
}

function nombreEstado(valor) {
  const v = String(valor || '').toUpperCase()
  const mapa = { BORRADOR: 'Borrador', PENDIENTE: 'Pendiente', EN_REVISION: 'En revisión', DOCUMENTACION_INCOMPLETA: 'Documentación incompleta', ACEPTADA: 'Aceptada', RECHAZADA: 'Rechazada' }
  return mapa[v] || valor || 'Sin estado'
}

function claseEstado(valor) {
  const v = String(valor || '').toUpperCase()
  if (['ACEPTADA', 'APROBADA'].includes(v)) return 'success'
  if (['RECHAZADA', 'CANCELADA'].includes(v)) return 'danger'
  if (v === 'EN_REVISION') return 'info'
  if (v === 'DOCUMENTACION_INCOMPLETA') return 'purple'
  if (['PENDIENTE', 'BORRADOR'].includes(v)) return 'warning'
  return 'neutral'
}

function modalidadLabel(valor) {
  const item = modalidades.find(m => m.value === String(valor || '').toUpperCase())
  return item?.label || valor || '—'
}
</script>

<template>
  <section class="space">
    <div class="section-heading">
      <div>
        <span class="eyebrow">TRÁMITE</span>
        <h1>Mi solicitud</h1>
        <p>Registra o consulta tu solicitud de apoyo.</p>
      </div>
    </div>

    <!-- VISTA CUANDO YA HAY SOLICITUD -->
    <article v-if="solicitudActiva" class="panel request-detail">
      <div class="panel-heading">
        <div>
          <span class="eyebrow">{{ folio(solicitudActiva) }}</span>
          <h2>Solicitud registrada</h2>
        </div>
        <span class="badge" :class="claseEstado(estadoActual)">
          {{ nombreEstado(estadoActual) }}
        </span>
      </div>

      <div class="request-summary large">
        <div><span>Modalidad</span><strong>{{ modalidadLabel(solicitudActiva.modalidad) }}</strong></div>
        <div><span>Carrera</span><strong>{{ solicitudActiva.carrera?.nombre || usuario?.carrera?.nombre || '—' }}</strong></div>
        <div><span>Grupo</span><strong>{{ solicitudActiva.grupo?.nombre || solicitudActiva.grupo || usuario?.grupo?.nombre || usuario?.grupo || '—' }}</strong></div>
        <div><span>Registrada</span><strong>{{ fecha(solicitudActiva.created_at) }}</strong></div>
      </div>

      <div v-if="solicitudActiva.observaciones || solicitudActiva.comentarios" class="observation">
        <strong>Observaciones</strong>
        <p>{{ solicitudActiva.observaciones || solicitudActiva.comentarios }}</p>
      </div>

      <div class="action-strip">
        <div>
          <strong>¿Te falta documentación?</strong>
          <span>Puedes cargar archivos desde la sección Documentos.</span>
        </div>
        <button class="primary-button" @click="emit('cambiar-seccion', 'documentos')">Ir a documentos</button>
      </div>
    </article>

    <!-- FORMULARIO NUEVA SOLICITUD -->
    <article v-else class="panel form-panel">
      <div class="panel-heading">
        <div>
          <span class="eyebrow">NUEVA SOLICITUD</span>
          <h2>Selecciona tu modalidad</h2>
        </div>
      </div>

      <div v-if="!convocatoria" class="empty-state">
        <div class="empty-icon">⌛</div>
        <strong>No hay convocatoria disponible</strong>
        <span>No puedes crear una solicitud hasta que exista una convocatoria vigente.</span>
      </div>

      <div v-else-if="!convocatoriaAbierta" class="empty-state">
        <div class="empty-icon">⊘</div>
        <strong>La convocatoria no está abierta</strong>
        <span>Revisa las fechas de apertura y cierre.</span>
      </div>

      <form v-else class="application-form" @submit.prevent="enviarFormulario">
        <div class="conv-mini">
          <span>Convocatoria</span>
          <strong>{{ convocatoria.nombre || convocatoria.titulo }}</strong>
          <small>{{ fecha(convocatoria.fecha_inicio) }} — {{ fecha(convocatoria.fecha_cierre) }}</small>
        </div>

        <label class="field full">
          <span>Modalidad *</span>
          <select v-model="formSolicitud.modalidad" required>
            <option value="">Selecciona una modalidad</option>
            <option v-for="m in modalidades" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>

        <label class="field">
          <span>Carrera</span>
          <select v-model="formSolicitud.carrera_id">
            <option value="">Usar carrera de mi perfil</option>
            <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </label>

        <label class="field">
          <span>Grupo</span>
          <input v-model="formSolicitud.grupo" type="text" placeholder="Ej. 8A" />
        </label>

        <div class="form-note full">Al enviar la solicitud confirmas que la información registrada es correcta.</div>

        <div class="form-actions full">
          <button type="submit" class="primary-button" :disabled="guardando">
            {{ guardando ? 'Registrando...' : 'Registrar solicitud' }}
          </button>
        </div>
      </form>
    </article>
  </section>
</template>