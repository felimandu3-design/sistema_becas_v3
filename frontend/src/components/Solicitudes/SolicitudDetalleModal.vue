<script setup>
import { ref } from 'vue'
import axios from 'axios'

/* =========================================================================
   PROPS Y EMITS
   ========================================================================= */
const props = defineProps({
  solicitud: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['cerrar', 'actualizar-estado'])

/* =========================================================================
   ESTADOS Y NOTIFICACIONES TOAST
   ========================================================================= */
const mensajeToast = ref('')
const typeToast = ref('')
const cargandoEstatus = ref(false)

// Variables para el control de beca / descuento
const mostrandoOpcionesAceptar = ref(false)
const porcentajeDescuento = ref(50) // Valor por defecto

const mostrarNotificacion = (mensaje, esError = false) => {
  mensajeToast.value = mensaje
  typeToast.value = esError ? 'error' : ''
  
  setTimeout(() => {
    mensajeToast.value = ''
  }, 3500)
}

/* =========================================================================
   CAMBIO DE ESTADO Y DESCUENTO
   ========================================================================= */
  const cambiarEstadoSolicitud = async (id, nuevoEstado, descuento = null) => {
  cargandoEstatus.value = true
  try {
    const token = localStorage.getItem('token') || localStorage.getItem('auth_token')

    const config = {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }

    const porcentajeFinal = (nuevoEstado.toUpperCase() === 'ACEPTADA') ? descuento : null

    const payload = {
      estado: nuevoEstado.toUpperCase(),
      estatus: nuevoEstado.toUpperCase(),
      porcentaje_descuento: porcentajeFinal
    }

    if (descuento !== null) {
      payload.porcentaje_descuento = Number(descuento)
    }

    await axios.patch(`http://127.0.0.1:8000/api/profesor/solicitudes/${id}/estatus`, payload, config)

    if (props.solicitud) {
      props.solicitud.estado = nuevoEstado.toUpperCase()
      props.solicitud.estatus = nuevoEstado.toUpperCase()
      props.solicitud.porcentaje_descuento = porcentajeFinal
    }

    emit('actualizar-estado', { 
      id, 
      estado: nuevoEstado.toUpperCase(), 
      porcentaje_descuento: porcentajeFinal 
    })

    mostrarNotificacion(
      porcentajeFinal === null && props.solicitud?.porcentaje_descuento
        ? 'Estatus actualizado y porcentaje de descuento removido.'
        : 'Información actualizada correctamente.'
    )
    
    mostrandoOpcionesAceptar.value = false
  } catch (error) {
    console.error('Error al actualizar:', error.response?.data || error)
    mostrarNotificacion(error.response?.data?.message || 'Error al actualizar la información', true)
  } finally {
    cargandoEstatus.value = false
  }
}

const obtenerUrlDocumento = (doc) => {
  if (!doc) return '#'

  if (doc.archivo_url || doc.url) {
    return doc.archivo_url || doc.url
  }

  const rutaRelativa = doc.ruta_archivo || doc.ruta || doc.archivo_path || doc.path

  if (!rutaRelativa) return '#'

  if (String(rutaRelativa).startsWith('http')) {
    return rutaRelativa
  }

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

const cerrarModal = () => {
  emit('cerrar')
}
</script>

<template>
  <div>
    <!-- NOTIFICACIÓN TOAST -->
    <Transition name="fade">
      <div v-if="mensajeToast" :class="['toast', typeToast]">
        {{ mensajeToast }}
      </div>
    </Transition>

    <!-- MODAL DE DETALLE -->
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
              {{ props.solicitud.usuario?.name || props.solicitud.alumno?.nombre || props.solicitud.alumno?.name || props.solicitud.user?.name || 'Alumno' }}
            </p>
          </div>

          <div>
            <span class="eyebrow">Matrícula</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ props.solicitud.usuario?.matricula || props.solicitud.alumno?.matricula || props.solicitud.matricula || 'N/A' }}
            </p>
          </div>

          <div>
            <span class="eyebrow">Grupo Actual</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ props.solicitud.usuario?.grupo || props.solicitud.alumno?.grupo || props.solicitud.grupo || 'N/A' }}
            </p>
          </div>

          <div>
            <span class="eyebrow">Correo Institucional</span>
            <p style="margin: 4px 0 12px; font-weight: 700;">
              {{ props.solicitud.usuario?.email || props.solicitud.alumno?.email || props.solicitud.user?.email || 'N/A' }}
            </p>
          </div>

          <!-- DOCUMENTACIÓN PRESENTADA -->
          <div class="full" style="margin-top: 10px;">
            <span class="eyebrow" style="margin-bottom: 8px;">Documentación Presentada</span>
            <div v-if="props.solicitud.documentos && props.solicitud.documentos.length" class="records">
              <div v-for="doc in props.solicitud.documentos" :key="doc.id" class="record" style="padding: 10px 14px;">
                <div>
                  <strong style="font-size: 13px;">{{ doc.nombre || doc.tipo_documento || doc.tipo || 'Documento' }}</strong>
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
              <span v-if="props.solicitud.porcentaje_descuento" class="badge info">
                Descuento: {{ props.solicitud.porcentaje_descuento }}%
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
                  <input type="radio" :value="porcentaje" v-model="porcentajeDescuento" />
                  {{ porcentaje }}%
                </label>
              </div>
              <div style="display: flex; gap: 8px;">
                <button 
                  type="button" 
                  class="primary" 
                  :disabled="cargandoEstatus"
                  @click="cambiarEstadoSolicitud(props.solicitud.id, 'ACEPTADA', porcentajeDescuento)"
                >
                  Confirmar y Aceptar Beca
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
                :disabled="cargandoEstatus"
                @click="cambiarEstadoSolicitud(props.solicitud.id, 'RECHAZADA')"
              >
                Rechazar
              </button>

              <button 
                type="button" 
                class="secondary" 
                :disabled="cargandoEstatus"
                @click="cambiarEstadoSolicitud(props.solicitud.id, 'DOCUMENTACION_INCOMPLETA')"
              >
                Doc. Incompleta
              </button>

              <button 
                type="button" 
                class="secondary" 
                :disabled="cargandoEstatus"
                @click="cambiarEstadoSolicitud(props.solicitud.id, 'EN_REVISION')"
              >
                En Revisión
              </button>

              <button 
                type="button" 
                class="secondary" 
                :disabled="cargandoEstatus"
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
/* Transición Fade / Modal */
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