<script setup>
import { computed } from 'vue'

const props = defineProps({
  usuario: Object,
  convocatoria: Object,
  solicitudActiva: Object,
  solicitudes: Array,
  documentos: Array,
  progresoDocumentos: Number,
  estadoActual: String
})

const emit = defineEmits(['cambiar-seccion', 'actualizar-datos'])

// Funciones de formato locales para este componente
function fecha(valor) {
  if (!valor) return '—'
  const d = new Date(valor)
  if (Number.isNaN(d.getTime())) return valor
  return new Intl.DateTimeFormat('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric',
  }).format(d)
}

function iniciales(nombre) {
  return String(nombre || 'AL').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(x => x.charAt(0).toUpperCase()).join('')
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

function folio(s) {
  return s?.folio || `BEC-${String(s?.id || 0).padStart(5, '0')}`
}

function modalidadLabel(valor) {
  const modalidades = [
    { value: 'DISCAPACIDAD', label: 'Discapacidad' },
    { value: 'EXCELENCIA_ACADEMICA', label: 'Excelencia académica' },
    { value: 'SITUACION_SOCIOECONOMICA', label: 'Situación socioeconómica' },
  ]
  const item = modalidades.find(m => m.value === String(valor || '').toUpperCase())
  return item?.label || valor || '—'
}

// Lógica de vistas
const puedeCrear = computed(() => !!props.convocatoria && !props.solicitudActiva)

const convocatoriaAbierta = computed(() => {
  if (!props.convocatoria) return false
  const estado = String(props.convocatoria.estado || '').toUpperCase()
  if (estado && !['PUBLICADA', 'ACTIVA', 'ABIERTO', 'ABIERTA'].includes(estado)) return false

  const hoy = new Date()
  const inicio = props.convocatoria.fecha_inicio ? new Date(props.convocatoria.fecha_inicio) : null
  const cierre = props.convocatoria.fecha_cierre ? new Date(props.convocatoria.fecha_cierre) : null

  if (inicio && !Number.isNaN(inicio.getTime()) && hoy < inicio) return false
  if (cierre && !Number.isNaN(cierre.getTime())) {
    cierre.setHours(23, 59, 59, 999)
    if (hoy > cierre) return false
  }
  return true
})

const resumenCards = computed(() => [
  { titulo: 'Estado', valor: props.solicitudActiva ? nombreEstado(props.estadoActual) : 'Sin solicitud', detalle: props.solicitudActiva ? folio(props.solicitudActiva) : 'Puedes iniciar cuando haya convocatoria', clase: claseEstado(props.estadoActual) },
  { titulo: 'Documentos', valor: props.documentos.length, detalle: 'archivos cargados', clase: 'info' },
  { titulo: 'Modalidad', valor: props.solicitudActiva ? modalidadLabel(props.solicitudActiva.modalidad) : '—', detalle: 'modalidad registrada', clase: 'purple' },
  { titulo: 'Historial', valor: props.solicitudes.length, detalle: 'solicitudes registradas', clase: 'neutral' },
])
</script>

<template>
  <section class="space">
    <div class="hero">
      <div class="hero-copy">
        <span class="eyebrow">PORTAL DEL ESTUDIANTE</span>
        <h1>Hola, {{ usuario?.name?.split(' ')[0] || 'estudiante' }}</h1>
        <p>Consulta tu convocatoria, completa tu solicitud y da seguimiento a tu proceso de beca desde un solo lugar.</p>
        <div class="hero-actions">
          <button v-if="puedeCrear && convocatoriaAbierta" class="primary-button" @click="emit('cambiar-seccion', 'solicitud')">Iniciar solicitud</button>
          <button v-else-if="solicitudActiva" class="primary-button" @click="emit('cambiar-seccion', 'solicitud')">Ver mi solicitud</button>
          <button class="soft-button" @click="emit('actualizar-datos')">Actualizar</button>
        </div>
      </div>
      <div class="hero-status">
        <span class="hero-status-label">PROCESO ACTUAL</span>
        <strong v-if="solicitudActiva">{{ nombreEstado(estadoActual) }}</strong>
        <strong v-else>Sin solicitud activa</strong>
        <div class="progress-track">
          <div class="progress-fill" :style="{ width: `${progresoDocumentos}%` }"></div>
        </div>
        <small>{{ solicitudActiva ? `${progresoDocumentos}% de avance estimado` : 'Revisa la convocatoria vigente para comenzar' }}</small>
      </div>
    </div>

    <div class="kpis">
      <article v-for="card in resumenCards" :key="card.titulo" class="kpi" :class="card.clase">
        <span>{{ card.titulo }}</span>
        <strong>{{ card.valor }}</strong>
        <small>{{ card.detalle }}</small>
      </article>
    </div>

    <div class="dashboard-grid">
      <article class="panel convocatoria-card">
        <div class="panel-heading">
          <div>
            <span class="eyebrow">CONVOCATORIA</span>
            <h2>Convocatoria vigente</h2>
          </div>
          <span class="badge" :class="convocatoriaAbierta ? 'success' : 'neutral'">{{ convocatoriaAbierta ? 'Disponible' : 'No disponible' }}</span>
        </div>
        <div v-if="convocatoria" class="convocatoria-content">
          <h3>{{ convocatoria.nombre || convocatoria.titulo || 'Convocatoria de becas' }}</h3>
          <p>{{ convocatoria.descripcion || 'Consulta las fechas y requisitos antes de registrar tu solicitud.' }}</p>
          <div class="details-grid">
            <div><span>Inicio</span><strong>{{ fecha(convocatoria.fecha_inicio) }}</strong></div>
            <div><span>Cierre</span><strong>{{ fecha(convocatoria.fecha_cierre) }}</strong></div>
            <div><span>Periodo</span><strong>{{ convocatoria.periodo?.nombre || '—' }}</strong></div>
            <div><span>Promedio mínimo</span><strong>{{ convocatoria.promedio_minimo ?? 'Según modalidad' }}</strong></div>
          </div>
        </div>
        <div v-else class="empty-state compact">
          <div class="empty-icon">◎</div>
          <strong>No hay convocatoria vigente</strong>
          <span>Cuando se publique una convocatoria aparecerá aquí.</span>
        </div>
      </article>

      <article class="panel">
        <div class="panel-heading">
          <div>
            <span class="eyebrow">DATOS ACADÉMICOS</span>
            <h2>Mi información</h2>
          </div>
        </div>
        <div class="student-info">
          <div class="student-avatar">{{ iniciales(usuario?.name) }}</div>
          <div class="student-name">
            <strong>{{ usuario?.name || 'Alumno' }}</strong>
            <span>{{ usuario?.email || '—' }}</span>
          </div>
          <div class="info-row"><span>Matrícula</span><strong>{{ usuario?.matricula || '—' }}</strong></div>
          <div class="info-row"><span>Carrera</span><strong>{{ usuario?.carrera?.nombre || 'Asignada en tu perfil' }}</strong></div>
          <div class="info-row"><span>Grupo</span><strong>{{ usuario?.grupo?.nombre || usuario?.grupo || '—' }}</strong></div>
        </div>
      </article>
    </div>
  </section>
</template>