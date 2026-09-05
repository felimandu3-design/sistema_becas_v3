<script setup>
import { ref, onMounted } from 'vue'

/* =========================================================================
   PROPS Y EMITS
   ========================================================================= */
const props = defineProps({
  solicitud: {
    type: Object,
    default: null
  },
  guardando: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['cerrar', 'guardar-dictamen', 'marcar-revision'])

/* =========================================================================
   ESTADOS LOCALES
   ========================================================================= */
const porcentajeBeca = ref(50)
const mostrandoOpcionesAceptar = ref(false)

onMounted(() => {
  if (props.solicitud?.porcentaje_beca || props.solicitud?.porcentaje_descuento) {
    porcentajeBeca.value = Number(props.solicitud.porcentaje_beca || props.solicitud.porcentaje_descuento)
  }
})

/* =========================================================================
   HELPERS Y RESOLUCIÓN DE DATOS
   ========================================================================= */
const alumnoDe = (sol) => sol?.usuario || sol?.alumno || sol?.user || {}

const grupoDe = (sol) => {
  return sol?.grupo_relacion?.nombre || 
         sol?.grupoRelacion?.nombre || 
         sol?.grupo?.nombre || 
         sol?.grupo || 
         alumnoDe(sol).grupo || 
         'N/A'
}

const obtenerUrlDocumento = (doc) => {
  if (!doc) return '#'
  if (doc.archivo_url || doc.url) return doc.archivo_url || doc.url

  const rutaRelativa = doc.ruta_archivo || doc.ruta || doc.archivo_path || doc.path || doc.archivo
  if (!rutaRelativa) return '#'
  if (String(rutaRelativa).startsWith('http')) return rutaRelativa

  const pathLimpio = String(rutaRelativa).replace(/^public\//, '').replace(/^\/?storage\//, '')
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000'
  
  return `${baseUrl}/storage/${pathLimpio}`
}

const obtenerClaseBadge = (estatus) => {
  if (!estatus) return 'warning'
  const e = estatus.toString().toLowerCase()
  if (e.includes('aceptad')) return 'success'
  if (e.includes('rechazad')) return 'danger'
  if (e.includes('revision')) return 'info'
  if (e.includes('incomplet')) return 'purple'
  return 'warning'
}

/* =========================================================================
   ENVÍO DE DICTAMEN
   ========================================================================= */
const emitirDictamen = (nuevoEstado) => {
  const payload = { 
    estado: nuevoEstado, 
    comentario_revision: null,
    observaciones: null,
    porcentaje_beca: nuevoEstado === 'ACEPTADA' ? porcentajeBeca.value : null
  }

  emit('guardar-dictamen', payload)
  mostrandoOpcionesAceptar.value = false
}
const cerrarModal = () => {
  emit('cerrar')
}
</script>

<template>
  <div>
    <!-- MODAL DE DETALLE CON ESTILO DE TUTOR -->
    <div v-if="props.solicitud" class="overlay" @click.self="cerrarModal">
      <div class="modal large">
        <button type="button" class="close" @click="cerrarModal">×</button>
        
        <h2>Detalle de Solicitud</h2>
        <p class="eyebrow" style="margin-top: -8px; margin-bottom: 16px;">
          Folio: {{ props.solicitud.folio || `SOL-${props.solicitud.id}` }}
        </p>

        <div class="form-grid">
          <div>
            <span class="eyebrow">Nombre del Alumno</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ alumnoDe(props.solicitud).name || alumnoDe(props.solicitud).nombre || 'Alumno' }}
            </p>
          </div>

          <div>
            <span class="eyebrow">Matrícula</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ alumnoDe(props.solicitud).matricula || 'N/A' }}
            </p>
          </div>

          <div>
            <span class="eyebrow">Grupo Actual</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ grupoDe(props.solicitud) }}
            </p>
          </div>

          <div>
            <span class="eyebrow">Correo Institucional</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ alumnoDe(props.solicitud).email || 'N/A' }}
            </p>
          </div>

          <!-- DOCUMENTACIÓN PRESENTADA (ABRE EN OTRA PESTAÑA) -->
          <div class="full" style="margin-top: 10px;">
            <span class="eyebrow" style="margin-bottom: 8px;">Documentación Presentada</span>
            <div v-if="props.solicitud.documentos && props.solicitud.documentos.length" class="records">
              <div v-for="doc in props.solicitud.documentos" :key="doc.id || doc.ruta_archivo" class="record" style="padding: 10px 14px;">
                <div>
                  <strong style="font-size: 13px;">{{ doc.nombre || doc.nombre_original || doc.tipo_documento || 'Documento' }}</strong>
                </div>
                <a 
                  :href="obtenerUrlDocumento(doc)" 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  class="action-link green-text"
                >
                  Ver Documento
                </a>
              </div>
            </div>
            <p v-else class="empty" style="height: auto; padding: 12px 0;">
              No hay documentos adjuntos.
            </p>
          </div>

          <!-- ESTATUS Y DESCUENTO ASIGNADO -->
          <div class="full" style="margin-top: 10px;">
            <span class="eyebrow">Estatus Actual de la Beca</span>
            <div style="margin-top: 6px; display: flex; align-items: center; gap: 10px;">
              <span :class="['badge', obtenerClaseBadge(props.solicitud.estatus || props.solicitud.estado)]">
                {{ (props.solicitud.estatus || props.solicitud.estado || 'PENDIENTE').toString().replace('_', ' ').toUpperCase() }}
              </span>
              <span v-if="props.solicitud.porcentaje_descuento || props.solicitud.porcentaje_beca" class="badge info">
                Descuento: {{ props.solicitud.porcentaje_descuento || props.solicitud.porcentaje_beca }}%
              </span>
            </div>
          </div>

          <!-- SECCIÓN CAMBIAR ESTATUS -->
          <div class="full" style="margin-top: 15px; border-top: 1px solid #e0e6e2; padding-top: 15px;">
            <span class="eyebrow" style="margin-bottom: 10px;">Cambiar Estatus a:</span>
            
            <!-- PANEL DINÁMICO DE SELECCIÓN DE DESCUENTO -->
            <div v-if="mostrandoOpcionesAceptar" style="background: #f4fbf6; padding: 12px; border-radius: 8px; border: 1px solid #c2e8ce; margin-bottom: 12px;">
              <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">
                Seleccione el Porcentaje de Descuento:
              </label>
              <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                <label v-for="porcentaje in [25, 50, 75]" :key="porcentaje" style="cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                  <input type="radio" :value="porcentaje" v-model="porcentajeBeca" />
                  {{ porcentaje }}%
                </label>
              </div>
              <div style="display: flex; gap: 8px;">
                <button 
                  type="button" 
                  class="primary" 
                  :disabled="props.guardando"
                  @click="emitirDictamen('ACEPTADA')"
                >
                  {{ props.guardando ? 'Guardando...' : 'Confirmar y Aceptar Beca' }}
                </button>
                <button type="button" class="secondary" @click="mostrandoOpcionesAceptar = false">
                  Cancelar
                </button>
              </div>
            </div>

            <!-- BOTONES ESTÁNDAR DE CAMBIO DE ESTADO -->
            <div v-else class="actions">
              <button 
                type="button" 
                class="logout" 
                :disabled="props.guardando"
                @click="emitirDictamen('RECHAZADA')"
              >
                Rechazar
              </button>

              <button 
                type="button" 
                class="secondary" 
                :disabled="props.guardando"
                @click="emitirDictamen('DOCUMENTACION_INCOMPLETA')"
              >
                Doc. Incompleta
              </button>

              <button 
                type="button" 
                class="secondary" 
                :disabled="props.guardando"
                @click="emit('marcar-revision')"
              >
                En Revisión
              </button>

              <button 
                type="button" 
                class="secondary" 
                :disabled="props.guardando"
                @click="mostrandoOpcionesAceptar = true"
              >
                Aceptar Beca
              </button>
            </div>
          </div>
        </div>

        <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
          <button type="button" class="secondary" @click="cerrarModal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: scale(0.96);
}

.badge.info {
  background-color: #e3f2fd;
  color: #0d47a1;
  border: 1px solid #bbdefb;
}
</style>