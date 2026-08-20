<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  solicitudActiva: Object,
  documentos: Array,
  progresoDocumentos: Number,
  subiendo: Boolean
})

const emit = defineEmits(['cambiar-seccion', 'subir-documento'])

const formDocumento = ref({
  archivo: null,
})

// Variable para controlar el modal del visor PDF
const modalPdf = ref(null)

/*
|--------------------------------------------------------------------------
| DICCIONARIO: MODALIDAD -> TIPO DE DOCUMENTO
|--------------------------------------------------------------------------
*/
const documentoRequerido = computed(() => {
  const mod = String(props.solicitudActiva?.modalidad || '').toUpperCase()
  
  const mapa = {
    'DISCAPACIDAD': { id: 'CERTIFICADO_MEDICO', nombre: 'Certificado médico' },
    'EXCELENCIA_ACADEMICA': { id: 'HISTORIAL_ACADEMICO', nombre: 'Historial académico / Excelencia' },
    'SITUACION_SOCIOECONOMICA': { id: 'COMPROBANTE_INGRESOS', nombre: 'Comprobante / Constancia de ingresos' }
  }

  return mapa[mod] || { id: 'OTRO', nombre: 'Documento requerido' }
})

/*
|--------------------------------------------------------------------------
| LÍMITE DE ARCHIVOS (Máximo 1)
|--------------------------------------------------------------------------
*/
const limiteAlcanzado = computed(() => {
  return props.documentos && props.documentos.length >= 1
})

function seleccionarArchivo(evento) {
  formDocumento.value.archivo = evento.target.files?.[0] || null
}

function manejarEnvio() {
  if (!formDocumento.value.archivo) return

  emit('subir-documento', { 
    tipo: documentoRequerido.value.id,
    archivo: formDocumento.value.archivo
  })
  
  // Limpiamos el formulario después de avisar al padre
  formDocumento.value.archivo = null
}

// Funciones para abrir y cerrar el PDF en el modal
function abrirPdf(doc) {
  modalPdf.value = urlArchivo(doc)
}

function cerrarPdf() {
  modalPdf.value = null
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
  const ruta = item?.archivo_url || item?.url || item?.ruta || item?.ruta_archivo || item?.archivo
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
        
        <!-- AVISO DE LÍMITE ALCANZADO (Oculta el formulario) -->
        <article v-if="limiteAlcanzado" class="panel upload-panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 30px;">
          <div class="empty-icon" style="background: #edf6f1; color: #147a4a; margin-bottom: 15px;">✓</div>
          <strong style="font-size: 14px; margin-bottom: 5px;">Expediente completo</strong>
          <span style="color: #68716c; font-size: 10px; max-width: 250px;">Ya has cargado el documento necesario para tu modalidad. Tu solicitud está lista para revisión.</span>
        </article>

        <!-- FORMULARIO DE CARGA -->
        <article v-else class="panel upload-panel">
          <div class="panel-heading">
            <div>
              <span class="eyebrow">NUEVO ARCHIVO</span>
              <h2>Cargar documento</h2>
            </div>
          </div>

          <form class="upload-form" @submit.prevent="manejarEnvio">
            <label class="field">
              <span>Documento requerido por la convocatoria *</span>
              <!-- INPUT BLOQUEADO QUE MUESTRA LO QUE EL ALUMNO DEBE SUBIR -->
              <input 
                type="text" 
                :value="documentoRequerido.nombre" 
                disabled 
                style="background-color: #f4f6f5; color: #147a4a; font-weight: 700; cursor: not-allowed;"
              />
            </label>

            <label class="file-drop">
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" required @change="seleccionarArchivo" />
              <span class="file-icon">↑</span>
              <strong>{{ formDocumento.archivo?.name || 'Seleccionar archivo' }}</strong>
              <small>PDF, JPG o PNG</small>
            </label>

            <button type="submit" class="primary-button" :disabled="subiendo || !formDocumento.archivo">
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
          <span class="count-badge">{{ documentos.length }} / 1</span>
        </div>

        <div v-if="documentos.length" class="document-list">
          <div v-for="doc in documentos" :key="doc.id" class="document-row">
            <div class="document-icon">PDF</div>
            <div class="document-main">
              <strong>{{ doc.nombre_original || doc.tipo_documento || doc.tipo || 'Documento' }}</strong>
              <span>Subido el: {{ fecha(doc.created_at) }}</span>
            </div>
            <span class="badge" :class="claseEstado(doc.estado || doc.estatus)">
              {{ nombreEstado(doc.estado || doc.estatus || 'CARGADO') }}
            </span>
            <!-- BOTÓN QUE ABRE EL VISOR EN LUGAR DE ENLACE -->
            <button v-if="urlArchivo(doc)" type="button" @click="abrirPdf(doc)" class="text-button" style="cursor: pointer;">
              Ver archivo
            </button>
          </div>
        </div>

        <div v-else class="empty-state compact">
          <div class="empty-icon">□</div>
          <strong>Aún no has cargado documentos</strong>
          <span>Usa el formulario de arriba para comenzar.</span>
        </div>
      </article>
    </template>

    <!-- VISOR DE PDF FLOTANTE (MODAL) -->
    <div v-if="modalPdf" class="overlay" style="display: flex; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999;" @click.self="cerrarPdf">
      <div class="modal" style="width: 80%; height: 90vh; max-width: 1000px; background: white; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <div style="padding: 15px 20px; background: #f4f6f5; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0;">
          <h3 style="margin: 0; color: #147a4a; font-size: 16px;">Visor de Documento</h3>
          <button @click="cerrarPdf" style="background: none; border: none; font-size: 28px; line-height: 1; cursor: pointer; color: #64748b; padding: 0;">&times;</button>
        </div>
        <div style="flex: 1; width: 100%; background: #334155;">
          <iframe :src="modalPdf" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
      </div>
    </div>
  </section>
</template>