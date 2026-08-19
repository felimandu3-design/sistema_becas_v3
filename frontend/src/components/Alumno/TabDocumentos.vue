<script setup>
import { ref } from 'vue'

const props = defineProps({
  solicitudActiva: Object,
  documentos: Array,
  progresoDocumentos: Number,
  subiendo: Boolean
})

const emit = defineEmits(['cambiar-seccion', 'subir-documento'])

const formDocumento = ref({
  tipo: '',
  archivo: null,
})

function seleccionarArchivo(evento) {
  formDocumento.value.archivo = evento.target.files?.[0] || null
}

function manejarEnvio() {
  emit('subir-documento', { ...formDocumento.value })
  // Limpiamos el formulario después de avisar al padre
  formDocumento.value.tipo = ''
  formDocumento.value.archivo = null
}

// Utilidades locales
function fecha(valor) {
  if (!valor) return '—'
  const d = new Date(valor)
  return Number.isNaN(d.getTime()) ? valor : new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(d)
}

function nombreEstado(valor) {
  const v = String(valor || '').toUpperCase()
  const mapa = { RECHAZADA: 'Rechazada', OBSERVADO: 'Observado', CARGADO: 'Cargado' }
  return mapa[v] || valor || 'Cargado'
}

function claseEstado(valor) {
  const v = String(valor || '').toUpperCase()
  if (['RECHAZADA', 'OBSERVADO'].includes(v)) return 'danger'
  return 'neutral'
}

function urlArchivo(item) {
  const ruta = item?.archivo_url || item?.url || item?.ruta || item?.archivo
  if (!ruta) return null
  if (String(ruta).startsWith('http')) return ruta
  if (String(ruta).startsWith('/')) return `http://127.0.0.1:8000${ruta}`
  return `http://127.0.0.1:8000/storage/${ruta}`
}
</script>

<template>
  <section class="space">
    <div class="section-heading">
      <div>
        <span class="eyebrow">EXPEDIENTE DIGITAL</span>
        <h1>Documentos</h1>
        <p>Adjunta los comprobantes correspondientes a tu modalidad.</p>
      </div>
    </div>

    <!-- MENSAJE SI NO HAY SOLICITUD -->
    <div v-if="!solicitudActiva" class="panel empty-state">
      <div class="empty-icon">▣</div>
      <strong>Primero crea una solicitud</strong>
      <span>Después podrás integrar tu expediente digital.</span>
      <button class="primary-button" @click="emit('cambiar-seccion', 'solicitud')">
        Ir a mi solicitud
      </button>
    </div>

    <!-- GESTOR DE DOCUMENTOS -->
    <template v-else>
      <div class="documents-grid">
        
        <!-- FORMULARIO DE CARGA -->
        <article class="panel upload-panel">
          <div class="panel-heading">
            <div>
              <span class="eyebrow">NUEVO ARCHIVO</span>
              <h2>Cargar documento</h2>
            </div>
          </div>

          <form class="upload-form" @submit.prevent="manejarEnvio">
            <label class="field">
              <span>Tipo de documento *</span>
              <select v-model="formDocumento.tipo" required>
                <option value="">Selecciona</option>
                <option value="HISTORIAL_ACADEMICO">Historial académico</option>
                <option value="CERTIFICADO_MEDICO">Certificado médico</option>
                <option value="COMPROBANTE_INGRESOS">Comprobante de ingresos</option>
                <option value="CONSTANCIA_INGRESOS">Constancia de ingresos</option>
                <option value="COMPROBANTE_DOMICILIO">Comprobante de domicilio</option>
                <option value="IDENTIFICACION">Identificación</option>
                <option value="OTRO">Otro documento</option>
              </select>
            </label>

            <label class="file-drop">
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="seleccionarArchivo" />
              <span class="file-icon">↑</span>
              <strong>{{ formDocumento.archivo?.name || 'Seleccionar archivo' }}</strong>
              <small>PDF, JPG o PNG</small>
            </label>

            <button type="submit" class="primary-button" :disabled="subiendo">
              {{ subiendo ? 'Subiendo...' : 'Subir documento' }}
            </button>
          </form>
        </article>

        <!-- TARJETA DE AVANCE -->
        <article class="panel">
          <div class="panel-heading">
            <div>
              <span class="eyebrow">AVANCE</span>
              <h2>Expediente</h2>
            </div>
            <strong class="progress-number">{{ progresoDocumentos }}%</strong>
          </div>
          <div class="progress-card">
            <div class="progress-track big">
              <div class="progress-fill" :style="{ width: `${progresoDocumentos}%` }"></div>
            </div>
            <p>Mantén tus documentos completos y legibles para evitar observaciones.</p>
          </div>
        </article>
      </div>

      <!-- LISTA DE DOCUMENTOS CARGADOS -->
      <article class="panel">
        <div class="panel-heading">
          <div>
            <span class="eyebrow">ARCHIVOS</span>
            <h2>Documentos cargados</h2>
          </div>
          <span class="count-badge">{{ documentos.length }}</span>
        </div>

        <div v-if="documentos.length" class="document-list">
          <div v-for="doc in documentos" :key="doc.id" class="document-row">
            <div class="document-icon">PDF</div>
            <div class="document-main">
              <strong>{{ doc.nombre || doc.tipo_documento || doc.tipo || 'Documento' }}</strong>
              <span>{{ fecha(doc.created_at) }}</span>
            </div>
            <span class="badge" :class="claseEstado(doc.estado || doc.estatus)">
              {{ nombreEstado(doc.estado || doc.estatus || 'CARGADO') }}
            </span>
            <a v-if="urlArchivo(doc)" :href="urlArchivo(doc)" target="_blank" rel="noopener" class="text-button">
              Ver
            </a>
          </div>
        </div>

        <div v-else class="empty-state compact">
          <div class="empty-icon">□</div>
          <strong>Aún no has cargado documentos</strong>
          <span>Usa el formulario de arriba para comenzar.</span>
        </div>
      </article>
    </template>
  </section>
</template>