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

const mostrarNotificacion = (mensaje, esError = false) => {
  mensajeToast.value = mensaje
  typeToast.value = esError ? 'error' : ''
  
  setTimeout(() => {
    mensajeToast.value = ''
  }, 3500)
}

/* =========================================================================
   CAMBIO DE ESTADO
   ========================================================================= */
const cambiarEstadoSolicitud = async (id, nuevoEstado) => {
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

    // Se envía 'estado' en MAYÚSCULAS según la validación Enum de Laravel
    const payload = {
      estado: nuevoEstado.toUpperCase(),
      estatus: nuevoEstado.toUpperCase()
    }

    await axios.patch(`http://127.0.0.1:8000/api/profesor/solicitudes/${id}/estatus`, payload, config)

    if (props.solicitud) {
      props.solicitud.estado = nuevoEstado.toUpperCase()
      props.solicitud.estatus = nuevoEstado.toUpperCase()
    }

    // Notificamos al componente padre indicando id y estado
    emit('actualizar-estado', { id, estado: nuevoEstado.toUpperCase() })
    mostrarNotificacion('Información actualizada correctamente')
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

  const rutaRelativa = doc.ruta_archivo || doc.ruta || doc.path

  if (!rutaRelativa) return '#'

  if (String(rutaRelativa).startsWith('http')) {
    return rutaRelativa
  }

  const pathLimpio = String(rutaRelativa).replace(/^public\//, '').replace(/^\//, '')
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

          <div class="full" style="margin-top: 10px;">
            <span class="eyebrow">Estatus Actual de la Beca</span>
            <div style="margin-top: 6px;">
              <span :class="['badge', obtenerClaseBadge(props.solicitud.estatus || props.solicitud.estado)]">
                {{ (props.solicitud.estatus || props.solicitud.estado || 'PENDIENTE').toString().replace('_', ' ').toUpperCase() }}
              </span>
            </div>
          </div>

          <div class="full" style="margin-top: 15px; border-top: 1px solid #e0e6e2; padding-top: 15px;">
            <span class="eyebrow" style="margin-bottom: 10px;">Cambiar Estatus a:</span>
            <div class="actions">
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
                @click="cambiarEstadoSolicitud(props.solicitud.id, 'ACEPTADA')"
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

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-contenedor {
  background: #ffffff;
  border-radius: 8px;
  width: 90%;
  max-width: 650px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.modal-header {
  padding: 1rem 1.5rem;
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: #1b396a;
}

.folio {
  font-size: 0.85rem;
  color: #6c757d;
}

.btn-cerrar {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

h4 {
  margin-top: 0;
  margin-bottom: 0.75rem;
  color: #333;
  border-bottom: 2px solid #e9ecef;
  padding-bottom: 0.25rem;
}

.grid-datos {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.grid-datos p {
  margin: 0.2rem 0 0 0;
  color: #555;
}

.lista-documentos {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.item-documento {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background-color: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 4px;
}

.btn-ver-doc {
  color: #0d6efd;
  text-decoration: none;
  font-weight: 500;
}

.btn-ver-doc:hover {
  text-decoration: underline;
}

.estatus-actual {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.badge-estado {
  padding: 0.25rem 0.6rem;
  border-radius: 4px;
  font-weight: bold;
  font-size: 0.85rem;
}

.badge-estado.pendiente { background: #ffeeba; color: #856404; }
.badge-estado.en_revision { background: #b8daff; color: #004085; }
.badge-estado.aceptada { background: #c3e6cb; color: #155724; }
.badge-estado.rechazada { background: #f5c6cb; color: #721c24; }
.badge-estado.documentacion_incompleta { background: #e2e3e5; color: #383d41; }

.grupo-botones {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.btn-estatus {
  padding: 0.4rem 0.8rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.85rem;
  transition: opacity 0.2s;
}

.btn-estatus:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.btn-estatus.aceptada { background: #28a745; color: white; }
.btn-estatus.rechazada { background: #dc3545; color: white; }
.btn-estatus.revision { background: #0d6efd; color: white; }
.btn-estatus.incompleta { background: #6c757d; color: white; }

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: flex-end;
  background-color: #f8f9fa;
}

.btn-secundario {
  padding: 0.4rem 1rem;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.sin-datos {
  color: #6c757d;
  font-style: italic;
}
</style>