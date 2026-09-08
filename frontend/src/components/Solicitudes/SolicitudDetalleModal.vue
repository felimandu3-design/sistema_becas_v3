<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

/* =========================================================================
   PROPS Y EMITS
   ========================================================================= */
const props = defineProps({
  solicitud: {
    type: Object,
    default: null
  },
  soloLectura: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['cerrar', 'actualizar-estado'])

/* =========================================================================
   ESTADOS Y NOTIFICACIONES TOAST
   ========================================================================= */
const mensajeToast = ref('')
const typeToast = ref('')
const cargandoEstatus = ref(false)

// Variables temporales para la selección local (sin afectar el backend todavía)
const estadoSeleccionado = ref('')
const porcentajeDescuentoSeleccionado = ref(50)
const mostrandoOpcionesAceptar = ref(false)

// Sincronizar valores al abrir o cambiar de solicitud
watch(() => props.solicitud, (nuevaVal) => {
  if (nuevaVal) {
    estadoSeleccionado.value = nuevaVal.estatus || nuevaVal.estado || 'PENDIENTE'
    porcentajeDescuentoSeleccionado.value = nuevaVal.porcentaje_descuento || 50
    mostrandoOpcionesAceptar.value = false
  }
}, { immediate: true })

const mostrarNotificacion = (mensaje, esError = false) => {
  mensajeToast.value = mensaje
  typeToast.value = esError ? 'error' : ''
  
  setTimeout(() => {
    mensajeToast.value = ''
  }, 3500)
}

/* =========================================================================
   SELECCIÓN LOCAL (NO ENVÍA NADA AL BACKEND AÚN)
   ========================================================================= */
const seleccionarEstadoTemporal = (nuevoEstado) => {
  if (props.soloLectura) return
  estadoSeleccionado.value = nuevoEstado.toUpperCase()
  if (nuevoEstado.toUpperCase() !== 'ACEPTADA') {
    porcentajeDescuentoSeleccionado.value = null
  }
}

/* =========================================================================
   ENVÍO REAL AL BACKEND (ÚNICAMENTE AL PRESIONAR "CONFIRMAR")
   ========================================================================= */
const confirmarYGuardarCambios = async () => {
  if (props.soloLectura || !props.solicitud) return

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

    const estadoFinal = estadoSeleccionado.value.toUpperCase()
    const porcentajeFinal = (estadoFinal === 'ACEPTADA') ? porcentajeDescuentoSeleccionado.value : null

    const payload = {
      estado: estadoFinal,
      estatus: estadoFinal,
      porcentaje_descuento: porcentajeFinal
    }

    await axios.patch(`http://127.0.0.1:8000/api/profesor/solicitudes/${props.solicitud.id}/estatus`, payload, config)

    props.solicitud.estado = estadoFinal
    props.solicitud.estatus = estadoFinal
    props.solicitud.porcentaje_descuento = porcentajeFinal

    emit('actualizar-estado', { 
      id: props.solicitud.id, 
      estado: estadoFinal, 
      porcentaje_descuento: porcentajeFinal 
    })

    mostrarNotificacion('Información actualizada correctamente.')
    
    setTimeout(() => {
      emit('cerrar')
    }, 1000)

  } catch (error) {
    console.error('Error al actualizar:', error.response?.data || error)
    mostrarNotificacion(error.response?.data?.message || 'Error al actualizar la información', true)
  } finally {
    cargandoEstatus.value = false
  }
}

const obtenerUrlDocumento = (doc) => {
  if (!doc) return '#'
  if (doc.archivo_url || doc.url) return doc.archivo_url || doc.url
  const rutaRelativa = doc.ruta_archivo || doc.ruta || doc.archivo_path || doc.path
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

          <!-- ESTATUS ACTUAL Y VISTA PREVIA DE SELECCIÓN -->
          <div class="full" style="margin-top: 10px;">
            <span class="eyebrow">Estatus Actual de la Beca</span>
            <div style="margin-top: 6px; display: flex; align-items: center; gap: 10px;">
              <span :class="['badge', obtenerClaseBadge(estadoSeleccionado)]">
                {{ estadoSeleccionado.replace('_', ' ') }}
              </span>
              <span v-if="estadoSeleccionado === 'ACEPTADA' && porcentajeDescuentoSeleccionado" class="badge info">
                Descuento: {{ porcentajeDescuentoSeleccionado }}%
              </span>
            </div>
          </div>

          <!-- AVISO MODO SOLO LECTURA -->
          <div v-if="props.soloLectura" class="full" style="margin-top: 15px; border-top: 1px solid #e0e6e2; padding-top: 15px;">
            <div style="background: #f0f4f1; border: 1px solid #d1ded5; color: #2c4a35; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
              <span>🔒</span>
              <span>Esta solicitud ya fue dictaminada y se encuentra en modo de consulta.</span>
            </div>
          </div>

          <!-- SECCIÓN DICTAMINAR (SOLO ACEPTAR O RECHAZAR) -->
          <div v-else class="full" style="margin-top: 15px; border-top: 1px solid #e0e6e2; padding-top: 15px;">
            <span class="eyebrow" style="margin-bottom: 10px;">Dictaminar Solicitud:</span>
            
            <!-- PANEL DINÁMICO DE SELECCIÓN DE DESCUENTO SI ELIGE ACEPTAR -->
            <div v-if="mostrandoOpcionesAceptar" style="background: #f4fbf6; padding: 12px; border-radius: 8px; border: 1px solid #c2e8ce; margin-bottom: 12px;">
              <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">
                Seleccione el Porcentaje de Descuento:
              </label>
              <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                <label v-for="porcentaje in [25, 50, 75]" :key="porcentaje" style="cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 4px;">
                  <input type="radio" :value="porcentaje" v-model="porcentajeDescuentoSeleccionado" />
                  {{ porcentaje }}%
                </label>
              </div>
              <div style="display: flex; gap: 8px;">
                <button type="button" class="secondary" @click="mostrandoOpcionesAceptar = false">
                  Volver
                </button>
              </div>
            </div>

            <!-- BOTONES PRINCIPALES -->
            <div v-else class="actions" style="display: flex; gap: 10px;">
              <button 
                type="button" 
                class="logout" 
                style="flex: 1;"
                :class="{ 'active-option': estadoSeleccionado === 'RECHAZADA' }" 
                @click="seleccionarEstadoTemporal('RECHAZADA')"
              >
                Rechazar
              </button>

              <button 
                type="button" 
                class="logout" 
                style="flex: 1;"
                :class="{ 'active-option': estadoSeleccionado === 'ACEPTADA' }" 
                @click="() => { seleccionarEstadoTemporal('ACEPTADA'); mostrandoOpcionesAceptar = true; }"
              >
                Aceptar Beca
              </button>
            </div>
          </div>
        </div>

        <!-- BOTONES FINALES DE CIERRE O CONFIRMACIÓN -->
        <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="secondary" @click="cerrarModal">Cerrar</button>
          <button 
            v-if="!props.soloLectura" 
            type="button" 
            class="primary" 
            :disabled="cargandoEstatus"
            @click="confirmarYGuardarCambios"
          >
            {{ cargandoEstatus ? 'Guardando...' : 'Confirmar' }}
          </button>
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

.actions button.active-option {
  outline: 3px solid #10b981;
  font-weight: bold;
}
</style>