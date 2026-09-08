<script setup>
import { ref, watch } from 'vue'

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
  },
  soloLectura: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['cerrar', 'guardar-dictamen'])

/* =========================================================================
   ESTADOS LOCALES
   ========================================================================= */
const porcentajeBeca = ref(50)
const mostrandoOpcionesAceptar = ref(false)

// Sincronizar porcentaje cuando cambia la solicitud
watch(() => props.solicitud, (nuevaSol) => {
  if (nuevaSol) {
    porcentajeBeca.value = Number(nuevaSol.porcentaje_beca || nuevaSol.porcentaje_descuento || 50)
    mostrandoOpcionesAceptar.value = false
  }
}, { immediate: true })

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
         '—'
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

const normalizarEstado = (estado) => String(estado || 'PENDIENTE').trim().toUpperCase()

const textoEstado = (estado) => {
  const valor = normalizarEstado(estado)
  const estados = {
    PENDIENTE: 'Pendiente',
    EN_REVISION: 'En revisión',
    ACEPTADA: 'Aceptada',
    RECHAZADA: 'Rechazada',
    DOCUMENTACION_INCOMPLETA: 'Documentación incompleta',
    REVISADO_TUTOR: 'Revisado por Tutor',
    CONFIRMADO: 'Confirmado',
  }
  return estados[valor] || valor
}

const claseEstado = (estado) => {
  const valor = normalizarEstado(estado)
  const clases = {
    PENDIENTE: 'warning',
    EN_REVISION: 'info',
    ACEPTADA: 'success',
    RECHAZADA: 'danger',
    DOCUMENTACION_INCOMPLETA: 'purple',
    REVISADO_TUTOR: 'success',
    CONFIRMADO: 'success',
  }
  return clases[valor] || 'neutral'
}

/* =========================================================================
   ENVÍO DE DICTAMEN
   ========================================================================= */
const emitirDictamen = (nuevoEstado) => {
  if (props.soloLectura || props.guardando) return

  const payload = { 
    estado: nuevoEstado, 
    estatus: nuevoEstado,
    porcentaje_beca: nuevoEstado === 'ACEPTADA' ? porcentajeBeca.value : null,
    porcentaje_descuento: nuevoEstado === 'ACEPTADA' ? porcentajeBeca.value : null
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
    <!-- MODAL DE DETALLE DE DICTAMEN -->
    <div v-if="props.solicitud" class="overlay" @click.self="cerrarModal">
      <div class="modal large">
        <button type="button" class="close" @click="cerrarModal">×</button>
        
        <span class="eyebrow">DICTAMEN DE BECA</span>
        <h2 class="modal-titulo">Detalle de Solicitud</h2>
        <p class="subtitulo-folio">
          Folio: {{ props.solicitud.folio || props.solicitud.id_solicitud || `#${props.solicitud.id}` }}
        </p>

        <div class="form-grid">
          <!-- 1. NOMBRE ALUMNO -->
          <div>
            <span class="eyebrow">Nombre del Alumno</span>
            <p class="valor-campo">
              {{ alumnoDe(props.solicitud).name || alumnoDe(props.solicitud).nombre || 'Alumno' }}
            </p>
          </div>

          <!-- 2. MATRÍCULA -->
          <div>
            <span class="eyebrow">Matrícula</span>
            <p class="valor-campo">
              {{ alumnoDe(props.solicitud).matricula || '—' }}
            </p>
          </div>

          <!-- 3. GRUPO ACTUAL -->
          <div>
            <span class="eyebrow">Grupo Actual</span>
            <p class="valor-campo">
              {{ grupoDe(props.solicitud) }}
            </p>
          </div>

          <!-- 4. CORREO INSTITUCIONAL (EN LUGAR DE PERIODO) -->
          <div>
            <span class="eyebrow">Correo Institucional</span>
            <p class="valor-campo">
              {{ alumnoDe(props.solicitud).email || '—' }}
            </p>
          </div>

          <!-- DOCUMENTACIÓN PRESENTADA -->
          <div class="full margin-top-sm">
            <span class="eyebrow margin-bottom-xs">Documentación Presentada</span>
            <div v-if="props.solicitud.documentos && props.solicitud.documentos.length" class="records">
              <div 
                v-for="doc in props.solicitud.documentos" 
                :key="doc.id || doc.ruta_archivo" 
                class="record"
              >
                <div>
                  <strong class="nombre-doc">{{ doc.nombre || doc.nombre_original || doc.tipo_documento || 'Documento' }}</strong>
                </div>
                <a 
                  :href="obtenerUrlDocumento(doc)" 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  class="action-link"
                >
                  Ver Documento ↗
                </a>
              </div>
            </div>
            <p v-else class="empty-docs">
              No hay documentos adjuntos.
            </p>
          </div>

          <!-- ESTATUS Y DESCUENTO ASIGNADO -->
          <div class="full margin-top-sm">
            <span class="eyebrow">Estatus Actual de la Beca</span>
            <div class="estatus-contenedor">
              <span :class="['badge-estado', claseEstado(props.solicitud.estatus || props.solicitud.estado)]">
                {{ textoEstado(props.solicitud.estatus || props.solicitud.estado) }}
              </span>
              <span 
                v-if="props.solicitud.porcentaje_descuento || props.solicitud.porcentaje_beca" 
                class="badge-estado info"
              >
                Descuento Asignado: {{ props.solicitud.porcentaje_descuento || props.solicitud.porcentaje_beca }}%
              </span>
            </div>
          </div>

          <!-- SECCIÓN DICTAMINAR -->
          <div v-if="!props.soloLectura" class="full seccion-dictaminar">
            <span class="eyebrow margin-bottom-xs">Dictaminar Solicitud:</span>
            
            <!-- PANEL DINÁMICO DE SELECCIÓN DE DESCUENTO SI ELIGE ACEPTAR -->
            <div v-if="mostrandoOpcionesAceptar" class="panel-descuento">
              <label class="label-descuento">
                Seleccione el Porcentaje de Descuento:
              </label>
              <div class="radios-descuento">
                <label v-for="porcentaje in [25, 50, 75]" :key="porcentaje" class="radio-label">
                  <input type="radio" :value="porcentaje" v-model="porcentajeBeca" />
                  {{ porcentaje }}%
                </label>
              </div>
              <div class="acciones-panel">
                <button 
                  type="button" 
                  class="btn-confirmar-beca" 
                  :disabled="props.guardando"
                  @click="emitirDictamen('ACEPTADA')"
                >
                  {{ props.guardando ? 'Guardando...' : 'Confirmar y Aceptar Beca' }}
                </button>
                <button type="button" class="btn-volver" @click="mostrandoOpcionesAceptar = false">
                  Volver
                </button>
              </div>
            </div>

            <!-- BOTONES PRINCIPALES (RECHAZAR / ACEPTAR BECA) EN PÍLDORA -->
            <div v-else class="acciones-dictamen">
              <button 
                type="button" 
                class="btn-rechazar" 
                :disabled="props.guardando"
                @click="emitirDictamen('RECHAZADA')"
              >
                Rechazar
              </button>

              <button 
                type="button" 
                class="btn-aceptar" 
                :disabled="props.guardando"
                @click="mostrandoOpcionesAceptar = true"
              >
                Aceptar Beca
              </button>
            </div>
          </div>
        </div>

        <!-- FOOTER: MENSAJE SOLO LECTURA Y BOTÓN CERRAR -->
        <div class="modal-footer">
          <div v-if="props.soloLectura" class="mensaje-lectura">
            🔒 Esta solicitud ya fue evaluada. Vista en modo solo lectura.
          </div>
          <div v-else></div>

          <button type="button" class="btn-cerrar" @click="cerrarModal">Cerrar</button>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* =========================================================================
   OVERLAY Y MODAL
   ========================================================================= */
.overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(3px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal.large {
  background: #ffffff;
  border-radius: 16px;
  width: 90%;
  max-width: 620px;
  padding: 28px;
  position: relative;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  max-height: 90vh;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
}

.close {
  position: absolute;
  top: 18px;
  right: 20px;
  border: none;
  background: transparent;
  font-size: 22px;
  cursor: pointer;
  color: #8c938f;
  line-height: 1;
  transition: color 0.2s ease;
}
.close:hover {
  color: #272e2a;
}

.eyebrow {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #8c938f;
  font-weight: 850;
  display: block;
}

.modal-titulo {
  margin: 2px 0 0;
  font-size: 18px;
  font-weight: 800;
  color: #272e2a;
}

.subtitulo-folio {
  font-size: 11px;
  color: #5a625d;
  font-weight: 600;
  margin-top: 2px;
  margin-bottom: 20px;
}

/* =========================================================================
   GRID Y CAMPOS
   ========================================================================= */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 18px;
}

.form-grid .full {
  grid-column: 1 / -1;
}

.valor-campo {
  margin: 4px 0 0;
  font-weight: 700;
  font-size: 12px;
  color: #272e2a;
  word-break: break-all;
}

.margin-top-sm { margin-top: 8px; }
.margin-bottom-xs { margin-bottom: 6px; }

/* =========================================================================
   DOCUMENTOS
   ========================================================================= */
.records {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.record {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nombre-doc {
  font-size: 11px;
  color: #272e2a;
  font-weight: 700;
}

.action-link {
  color: #247548;
  text-decoration: none;
  font-weight: 750;
  font-size: 11px;
  transition: opacity 0.2s ease;
}
.action-link:hover {
  opacity: 0.8;
  text-decoration: underline;
}

.empty-docs {
  color: #8c938f;
  font-size: 11px;
  margin: 0;
  padding: 8px 0;
}

/* =========================================================================
   BADGES DE ESTADO (ESTILO TUTOR)
   ========================================================================= */
.estatus-contenedor {
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.badge-estado {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 9px;
  font-weight: 800;
  text-transform: capitalize;
}

.badge-estado.warning { background: #fff5da; color: #956516; }
.badge-estado.info { background: #eaf3fa; color: #356b91; }
.badge-estado.success { background: #eaf5ee; color: #247548; }
.badge-estado.danger { background: #faedf1; color: #85243d; }
.badge-estado.purple { background: #f3ebfc; color: #6b21a8; }
.badge-estado.neutral { background: #f0f2f1; color: #505753; }

/* =========================================================================
   SECCIÓN DICTAMINAR Y BOTONES
   ========================================================================= */
.seccion-dictaminar {
  margin-top: 14px;
  border-top: 1px solid #f2f5f3;
  padding-top: 16px;
}

.acciones-dictamen {
  display: flex;
  gap: 10px;
}

.btn-rechazar {
  flex: 1;
  padding: 8px 16px;
  border: 1px solid #faedf1;
  border-radius: 18px;
  background: #faedf1;
  color: #85243d;
  font-size: 11px;
  font-weight: 750;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-rechazar:hover {
  background: #f7d8e1;
}

.btn-aceptar {
  flex: 1;
  padding: 8px 16px;
  border: 0;
  border-radius: 18px;
  background: #00875a;
  color: #fff;
  font-size: 11px;
  font-weight: 750;
  cursor: pointer;
  transition: background 0.2s ease;
}
.btn-aceptar:hover {
  background: #006644;
}

/* PANEL DESCUENTO */
.panel-descuento {
  background: #f4fbf6;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid #c2e8ce;
  margin-bottom: 8px;
}

.label-descuento {
  font-weight: 750;
  font-size: 11px;
  color: #272e2a;
  display: block;
  margin-bottom: 8px;
}

.radios-descuento {
  display: flex;
  gap: 16px;
  align-items: center;
  margin-bottom: 14px;
}

.radio-label {
  cursor: pointer;
  font-size: 11px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
  color: #39413d;
}

.acciones-panel {
  display: flex;
  gap: 8px;
}

.btn-confirmar-beca {
  flex: 1;
  padding: 8px 14px;
  border: 0;
  border-radius: 18px;
  background: #00875a;
  color: #fff;
  font-size: 11px;
  font-weight: 750;
  cursor: pointer;
  transition: background 0.2s ease;
}
.btn-confirmar-beca:hover {
  background: #006644;
}

.btn-volver {
  padding: 8px 14px;
  border: 1px solid #e1e5e2;
  border-radius: 18px;
  background: #fff;
  color: #39413d;
  font-size: 11px;
  font-weight: 750;
  cursor: pointer;
  transition: background 0.2s ease;
}
.btn-volver:hover {
  background: #f4f6f5;
}

/* =========================================================================
   FOOTER
   ========================================================================= */
.modal-footer {
  margin-top: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #f2f5f3;
  padding-top: 16px;
}

.mensaje-lectura {
  font-size: 11px;
  color: #8c938f;
  font-weight: 650;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-cerrar {
  padding: 7px 16px;
  border: 1px solid #e1e5e2;
  border-radius: 18px;
  background: #f4f6f5;
  color: #39413d;
  font-size: 11px;
  font-weight: 750;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-cerrar:hover {
  background: #e2e6e3;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>