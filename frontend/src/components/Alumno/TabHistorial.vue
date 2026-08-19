<script setup>
const props = defineProps({
  solicitudes: Array
})

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
  const modalidades = [
    { value: 'DISCAPACIDAD', label: 'Discapacidad' },
    { value: 'EXCELENCIA_ACADEMICA', label: 'Excelencia académica' },
    { value: 'SITUACION_SOCIOECONOMICA', label: 'Situación socioeconómica' },
  ]
  const item = modalidades.find(m => m.value === String(valor || '').toUpperCase())
  return item?.label || valor || '—'
}
</script>

<template>
  <section class="space">
    <div class="section-heading">
      <div>
        <span class="eyebrow">SEGUIMIENTO</span>
        <h1>Historial de solicitudes</h1>
        <p>Consulta tus trámites anteriores y su resultado.</p>
      </div>
    </div>

    <article class="panel">
      <div v-if="solicitudes.length" class="history-list">
        <div v-for="s in solicitudes" :key="s.id" class="history-row">
          <div class="history-folio">
            <span>Folio</span>
            <strong>{{ folio(s) }}</strong>
          </div>
          <div>
            <span>Convocatoria</span>
            <strong>{{ s.convocatoria?.nombre || 'Convocatoria' }}</strong>
          </div>
          <div>
            <span>Modalidad</span>
            <strong>{{ modalidadLabel(s.modalidad) }}</strong>
          </div>
          <div>
            <span>Fecha</span>
            <strong>{{ fecha(s.created_at) }}</strong>
          </div>
          <span class="badge" :class="claseEstado(s.estado || s.estatus)">
            {{ nombreEstado(s.estado || s.estatus) }}
          </span>
        </div>
      </div>

      <div v-else class="empty-state">
        <div class="empty-icon">↺</div>
        <strong>Sin historial todavía</strong>
        <span>Tus solicitudes aparecerán aquí.</span>
      </div>
    </article>
  </section>
</template>