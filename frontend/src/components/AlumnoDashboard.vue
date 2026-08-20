<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api/axios'

// 1. IMPORTAMOS LAS PIEZAS DEL ROMPECABEZAS (Componentes Hijos)
import TabResumen from './Alumno/TabResumen.vue'
import TabSolicitud from './Alumno/TabSolicitud.vue'
import TabDocumentos from './Alumno/TabDocumentos.vue'
import TabHistorial from './Alumno/TabHistorial.vue'

const props = defineProps({
  usuario: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['cerrar-sesion'])

// ESTADO GLOBAL
const seccion = ref('resumen')
const cargando = ref(true)
const guardando = ref(false)
const subiendo = ref(false)
const errorGeneral = ref('')
const toast = ref(null)

const convocatoria = ref(null)
const solicitudActiva = ref(null)
const solicitudes = ref([])
const carreras = ref([])

const tabs = [
  { id: 'resumen', label: 'Inicio' },
  { id: 'solicitud', label: 'Mi solicitud' },
  { id: 'documentos', label: 'Documentos' },
  { id: 'historial', label: 'Historial' },
]

// UTILIDADES
function unwrapArray(data) {
  if (Array.isArray(data)) return data
  if (Array.isArray(data?.data)) return data.data
  if (Array.isArray(data?.solicitudes)) return data.solicitudes
  if (Array.isArray(data?.carreras)) return data.carreras
  return []
}

function unwrapObject(data, keys = []) {
  if (!data) return null
  for (const key of keys) {
    if (data?.[key] && typeof data[key] === 'object') return data[key]
  }
  if (data?.data && !Array.isArray(data.data) && typeof data.data === 'object') return data.data
  if (typeof data === 'object' && !Array.isArray(data)) return data
  return null
}

function mostrarToast(mensaje, tipo = 'ok') {
  toast.value = { mensaje, tipo }
  window.setTimeout(() => {
    toast.value = null
  }, 3200)
}

function iniciales(nombre) {
  return String(nombre || 'AL').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(x => x.charAt(0).toUpperCase()).join('')
}

// PROPIEDADES COMPUTADAS GLOBALES
const documentos = computed(() => {
  const s = solicitudActiva.value
  if (!s) return []
  if (Array.isArray(s.documentos)) return s.documentos
  if (Array.isArray(s.documents)) return s.documents
  return []
})

const progresoDocumentos = computed(() => {
  if (!solicitudActiva.value) return 0
  const total = documentos.value.length
  
  if (!total) return 15 // Solo tiene la solicitud creada, sin archivos

  // Verificamos si el documento que subió no está rechazado
  const validos = documentos.value.filter(d => {
    const e = String(d.estado || d.estatus || '').toUpperCase()
    return !['RECHAZADO', 'OBSERVADO', 'CORRECCION'].includes(e)
  }).length

  // Como ahora solo es 1 documento obligatorio, si es válido, tiene el 100%
  if (validos >= 1) return 100 
  
  // Si subió algo pero se lo rechazaron, lo bajamos a 50% para que corrija
  return 50 
})

const estadoActual = computed(() =>
  solicitudActiva.value?.estado || solicitudActiva.value?.estatus || 'SIN_SOLICITUD'
)

const convocatoriaAbierta = computed(() => {
  if (!convocatoria.value) return false
  const estado = String(convocatoria.value.estado || '').toUpperCase()
  if (estado && !['PUBLICADA', 'ACTIVA', 'ABIERTO', 'ABIERTA'].includes(estado)) return false

  const hoy = new Date()
  const inicio = convocatoria.value.fecha_inicio ? new Date(convocatoria.value.fecha_inicio) : null
  const cierre = convocatoria.value.fecha_cierre ? new Date(convocatoria.value.fecha_cierre) : null

  if (inicio && !Number.isNaN(inicio.getTime()) && hoy < inicio) return false
  if (cierre && !Number.isNaN(cierre.getTime())) {
    cierre.setHours(23, 59, 59, 999)
    if (hoy > cierre) return false
  }
  return true
})

// FUNCIONES DE API
async function cargarDatos() {
  cargando.value = true
  errorGeneral.value = ''

  const resultados = await Promise.allSettled([
    api.get('/alumno/convocatoria-actual'),
    api.get('/alumno/mi-solicitud-activa'),
    api.get('/alumno/mis-solicitudes'),
    api.get('/carreras'),
  ])

  const [rConv, rActiva, rHistorial, rCarreras] = resultados

  if (rConv.status === 'fulfilled') {
    convocatoria.value = unwrapObject(rConv.value.data, ['convocatoria'])
  }
  if (rActiva.status === 'fulfilled') {
    solicitudActiva.value = unwrapObject(rActiva.value.data, ['solicitud'])
  } else if (rActiva.reason?.response?.status === 404) {
    solicitudActiva.value = null
  }
  if (rHistorial.status === 'fulfilled') {
    solicitudes.value = unwrapArray(rHistorial.value.data)
  }
  if (rCarreras.status === 'fulfilled') {
    carreras.value = unwrapArray(rCarreras.value.data)
  }

  const fallidosReales = resultados.filter(r => r.status === 'rejected' && r.reason?.response?.status !== 404)
  if (fallidosReales.length === resultados.length) {
    errorGeneral.value = 'No fue posible conectar con el backend.'
  } else if (fallidosReales.length) {
    errorGeneral.value = 'Algunos datos no pudieron cargarse. Puedes actualizar el panel.'
  }
  cargando.value = false
}

async function crearSolicitud(datosFormulario) {
  if (!convocatoria.value) {
    mostrarToast('No hay una convocatoria disponible.', 'error')
    return
  }
  guardando.value = true
  try {
    const payload = {
      convocatoria_id: convocatoria.value.id,
      modalidad: datosFormulario.modalidad,
      carrera_id: datosFormulario.carrera_id || props.usuario?.carrera_id || null,
      grupo_id: datosFormulario.grupo_id || props.usuario?.grupo_id || null,
    }

    Object.keys(payload).forEach(k => {
      if (payload[k] === null || payload[k] === '') delete payload[k]
    })

    const { data } = await api.post('/alumno/solicitudes', payload)
    solicitudActiva.value = unwrapObject(data, ['solicitud']) || solicitudActiva.value

    mostrarToast('Solicitud creada correctamente.')
    await cargarDatos()
    seccion.value = 'documentos'
  } catch (e) {
    const errores = e.response?.data?.errors
    const primerError = errores ? Object.values(errores).flat().filter(Boolean)[0] : null
    mostrarToast(primerError || e.response?.data?.message || 'No se pudo crear la solicitud.', 'error')
  } finally {
    guardando.value = false
  }
}

async function subirDocumento(datosDoc) {
  if (!solicitudActiva.value) {
    mostrarToast('Primero debes crear una solicitud.', 'error')
    return
  }
  subiendo.value = true
  try {
    const fd = new FormData()
    fd.append('archivo', datosDoc.archivo)
    fd.append('tipo', datosDoc.tipo)
    fd.append('tipo_documento', datosDoc.tipo)
    fd.append('nombre', datosDoc.tipo)

    await api.post(`/alumno/solicitudes/${solicitudActiva.value.id}/documentos`, fd, { 
      headers: { 'Content-Type': 'multipart/form-data' } 
    })

    mostrarToast('Documento cargado correctamente.')
    await cargarDatos()
  } catch (e) {
    const errores = e.response?.data?.errors
    const primerError = errores ? Object.values(errores).flat().filter(Boolean)[0] : null
    mostrarToast(primerError || e.response?.data?.message || 'No se pudo cargar el documento.', 'error')
  } finally {
    subiendo.value = false
  }
}

function cerrarSesion() {
  emit('cerrar-sesion')
}

onMounted(cargarDatos)
</script>

<template>
  <div class="alumno-dashboard">
    <transition name="toast">
      <div v-if="toast" class="toast" :class="toast.tipo">
        {{ toast.mensaje }}
      </div>
    </transition>

    <header class="topbar">
      <div class="topbar-inner">
        <div class="brand">
          <div class="brand-logo">
            <span class="up">UP</span><span class="t">T</span><span class="ex">ex</span>
          </div>
          <div class="brand-copy">
            <strong>Sistema de Becas</strong>
            <span>Portal del estudiante · UPTex</span>
          </div>
        </div>

        <nav class="nav">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :class="{ active: seccion === tab.id }"
            @click="seccion = tab.id"
          >
            {{ tab.label }}
          </button>
        </nav>

        <div class="profile">
          <div class="profile-copy">
            <strong>{{ usuario?.name || 'Alumno' }}</strong>
            <span>{{ usuario?.matricula || 'Estudiante' }}</span>
          </div>
          <div class="avatar">{{ iniciales(usuario?.name) }}</div>
          <button class="logout" type="button" @click="cerrarSesion">
            Salir
          </button>
        </div>
      </div>
    </header>

    <main class="main">
      <div v-if="errorGeneral" class="warning-banner">
        <div>
          <strong>Atención</strong>
          <span>{{ errorGeneral }}</span>
        </div>
        <button @click="cargarDatos">Reintentar</button>
      </div>

      <div v-if="cargando" class="loading-card">
        <div class="spinner"></div>
        <strong>Cargando tu panel</strong>
        <span>Consultando convocatoria, solicitud y documentos...</span>
      </div>

      <template v-else>
        <!-- AQUI SE INSERTAN LOS COMPONENTES HIJOS DEPENDIENDO LA PESTAÑA -->
        
        <TabResumen 
          v-if="seccion === 'resumen'" 
          :usuario="usuario"
          :convocatoria="convocatoria"
          :solicitudActiva="solicitudActiva"
          :solicitudes="solicitudes"
          :documentos="documentos"
          :progresoDocumentos="progresoDocumentos"
          :estadoActual="estadoActual"
          @cambiar-seccion="seccion = $event"
          @actualizar-datos="cargarDatos"
        />

        <TabSolicitud
          v-if="seccion === 'solicitud'"
          :usuario="usuario"
          :convocatoria="convocatoria"
          :convocatoriaAbierta="convocatoriaAbierta"
          :solicitudActiva="solicitudActiva"
          :estadoActual="estadoActual"
          :carreras="carreras"
          :guardando="guardando"
          @cambiar-seccion="seccion = $event"
          @submit-solicitud="crearSolicitud"
        />

        <TabDocumentos
          v-if="seccion === 'documentos'"
          :solicitudActiva="solicitudActiva"
          :documentos="documentos"
          :progresoDocumentos="progresoDocumentos"
          :subiendo="subiendo"
          @cambiar-seccion="seccion = $event"
          @subir-documento="subirDocumento"
        />

        <TabHistorial
          v-if="seccion === 'historial'"
          :solicitudes="solicitudes"
        />
        
      </template>
    </main>
  </div>
</template>

<style>
/* 
  NOTA IMPORTANTE: Le quitamos la palabra "scoped" a esta etiqueta style 
  para que las clases CSS afecten también a los componentes hijos que acabamos de crear.
*/
* {
  box-sizing: border-box;
}

.alumno-dashboard {
  min-height: 100vh;
  background: #f4f6f5;
  color: #29312d;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 30;
  border-bottom: 1px solid #e2e6e3;
  background: rgba(255,255,255,.96);
  backdrop-filter: blur(16px);
}

.topbar-inner {
  width: min(1440px, calc(100% - 40px));
  min-height: 74px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 22px;
}

.brand,
.profile,
.nav {
  display: flex;
  align-items: center;
}

.brand {
  gap: 12px;
}

.brand-logo {
  display: flex;
  align-items: baseline;
  font-size: 22px;
  font-weight: 950;
  letter-spacing: -.07em;
}

.brand-logo .up { color: #111827; }
.brand-logo .t { color: #7a1c33; margin-left: 1px; }
.brand-logo .ex { color: #147a4a; }

.brand-copy {
  display: flex;
  flex-direction: column;
}

.brand-copy strong {
  font-size: 12px;
}

.brand-copy span,
.profile-copy span {
  margin-top: 2px;
  color: #9aa19d;
  font-size: 8px;
}

.nav {
  padding: 4px;
  gap: 3px;
  border: 1px solid #e6eae7;
  border-radius: 12px;
  background: #f7f9f8;
}

.nav button {
  border: 0;
  border-radius: 9px;
  background: transparent;
  padding: 9px 12px;
  color: #767e79;
  font: inherit;
  font-size: 9px;
  font-weight: 800;
  cursor: pointer;
}

.nav button:hover {
  color: #147a4a;
}

.nav button.active {
  background: #fff;
  color: #147a4a;
  box-shadow: 0 3px 10px rgba(26,42,33,.08);
}

.profile {
  justify-content: flex-end;
  gap: 10px;
}

.profile-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.profile-copy strong {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 10px;
}

.avatar,
.student-avatar {
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #e9f4ee;
  color: #147a4a;
  font-weight: 900;
}

.avatar {
  width: 34px;
  height: 34px;
  font-size: 10px;
}

.logout {
  border: 1px solid #eadde1;
  border-radius: 9px;
  background: #fff;
  color: #8e2843;
  padding: 8px 10px;
  font: inherit;
  font-size: 8px;
  font-weight: 800;
  cursor: pointer;
}

.main {
  width: min(1240px, calc(100% - 40px));
  margin: 0 auto;
  padding: 30px 0 60px;
}

.space {
  display: grid;
  gap: 18px;
}

.hero {
  min-height: 225px;
  display: grid;
  grid-template-columns: 1.45fr .75fr;
  align-items: stretch;
  overflow: hidden;
  border-radius: 22px;
  background: radial-gradient(circle at 90% 10%, rgba(255,255,255,.11), transparent 28%), linear-gradient(135deg, #0f6f45 0%, #0a5d39 55%, #084c31 100%);
  color: white;
  box-shadow: 0 18px 40px rgba(12,83,51,.13);
}

.hero-copy {
  padding: 34px 38px;
}

.eyebrow {
  color: #8f9893;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.hero .eyebrow {
  color: #a9d7bd;
}

.hero h1 {
  margin: 7px 0 8px;
  font-size: clamp(28px, 4vw, 42px);
  line-height: 1;
  letter-spacing: -.045em;
}

.hero p {
  max-width: 620px;
  margin: 0;
  color: rgba(255,255,255,.78);
  font-size: 11px;
  line-height: 1.65;
}

.hero-actions {
  display: flex;
  gap: 9px;
  margin-top: 24px;
}

.hero .soft-button {
  border-color: rgba(255,255,255,.18);
  background: rgba(255,255,255,.08);
  color: white;
}

.hero-status {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 32px;
  background: rgba(0,0,0,.09);
  border-left: 1px solid rgba(255,255,255,.10);
}

.hero-status-label {
  color: #a9d7bd;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: .12em;
}

.hero-status > strong {
  margin-top: 9px;
  font-size: 20px;
}

.hero-status small {
  margin-top: 9px;
  color: rgba(255,255,255,.68);
  font-size: 8px;
}

.progress-track {
  width: 100%;
  height: 7px;
  margin-top: 18px;
  overflow: hidden;
  border-radius: 99px;
  background: rgba(255,255,255,.17);
}

.progress-track.big {
  height: 10px;
  margin-top: 0;
  background: #e8ece9;
}

.progress-fill {
  height: 100%;
  border-radius: inherit;
  background: #dfb34b;
  transition: width .35s ease;
}

.kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.kpi {
  min-height: 112px;
  padding: 18px;
  border: 1px solid #e2e6e3;
  border-top: 3px solid #7d8580;
  border-radius: 16px;
  background: white;
  box-shadow: 0 8px 22px rgba(28,40,33,.035);
}

.kpi.success { border-top-color: #147a4a; }
.kpi.info { border-top-color: #3b82b6; }
.kpi.warning { border-top-color: #d99a25; }
.kpi.purple { border-top-color: #7656a5; }
.kpi.danger { border-top-color: #a63a51; }

.kpi span {
  color: #8f9692;
  font-size: 8px;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.kpi strong {
  display: block;
  margin-top: 7px;
  color: #29312d;
  font-size: 22px;
  line-height: 1.15;
}

.kpi small {
  display: block;
  margin-top: 8px;
  color: #a0a6a3;
  font-size: 8px;
}

.dashboard-grid,
.documents-grid {
  display: grid;
  grid-template-columns: 1.45fr .75fr;
  gap: 16px;
}

.panel {
  overflow: hidden;
  border: 1px solid #e2e6e3;
  border-radius: 18px;
  background: white;
  box-shadow: 0 10px 28px rgba(27,39,32,.04);
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 20px 12px;
}

.panel-heading h2 {
  margin: 5px 0 0;
  font-size: 16px;
}

.convocatoria-content {
  padding: 5px 20px 22px;
}

.convocatoria-content h3 {
  margin: 0 0 7px;
  font-size: 15px;
}

.convocatoria-content > p {
  margin: 0;
  color: #808883;
  font-size: 9px;
  line-height: 1.6;
}

.details-grid,
.request-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 9px;
  margin-top: 20px;
}

.details-grid > div,
.request-summary > div {
  padding: 13px;
  border: 1px solid #e8ebe9;
  border-radius: 12px;
  background: #fafbfa;
}

.details-grid span,
.request-summary span,
.history-row > div > span,
.info-row span {
  display: block;
  color: #9aa09d;
  font-size: 7px;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .07em;
}

.details-grid strong,
.request-summary strong,
.history-row > div > strong {
  display: block;
  margin-top: 5px;
  font-size: 9px;
}

.student-info {
  padding: 4px 20px 22px;
}

.student-avatar {
  width: 50px;
  height: 50px;
  font-size: 14px;
}

.student-name {
  margin: 10px 0 16px;
}

.student-name strong {
  display: block;
  font-size: 13px;
}

.student-name span {
  color: #959c98;
  font-size: 8px;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  padding: 10px 0;
  border-top: 1px solid #edf0ee;
}

.info-row strong {
  font-size: 9px;
  text-align: right;
}

.section-heading {
  display: flex;
  justify-content: space-between;
  align-items: end;
  padding: 2px 2px 4px;
}

.section-heading h1 {
  margin: 5px 0 3px;
  font-size: 28px;
  letter-spacing: -.04em;
}

.section-heading p {
  margin: 0;
  color: #8d9590;
  font-size: 9px;
}

.request-detail,
.form-panel {
  min-height: 360px;
}

.request-summary.large {
  padding: 0 20px;
}

.observation {
  margin: 18px 20px 0;
  padding: 15px;
  border: 1px solid #f0dfad;
  border-radius: 12px;
  background: #fffaf0;
}

.observation strong {
  color: #8b6a19;
  font-size: 9px;
}

.observation p {
  margin: 6px 0 0;
  color: #746b56;
  font-size: 9px;
  line-height: 1.55;
}

.action-strip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin: 20px;
  padding: 16px 18px;
  border-radius: 14px;
  background: #f1f7f4;
}

.action-strip strong {
  display: block;
  font-size: 10px;
}

.action-strip span {
  display: block;
  margin-top: 3px;
  color: #7f8983;
  font-size: 8px;
}

.application-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  padding: 6px 20px 24px;
}

.full {
  grid-column: 1 / -1;
}

.conv-mini {
  grid-column: 1 / -1;
  padding: 15px;
  border-radius: 13px;
  background: #f3f8f5;
}

.conv-mini span,
.conv-mini small {
  display: block;
  color: #89918c;
  font-size: 8px;
}

.conv-mini strong {
  display: block;
  margin: 4px 0;
  font-size: 12px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field > span {
  color: #68716c;
  font-size: 8px;
  font-weight: 850;
}

.field input,
.field select {
  width: 100%;
  border: 1px solid #dfe4e1;
  border-radius: 10px;
  background: #fff;
  color: #414944;
  padding: 11px 12px;
  outline: none;
  font: inherit;
  font-size: 9px;
}

.field input:focus,
.field select:focus {
  border-color: #70a88a;
  box-shadow: 0 0 0 3px #edf6f1;
}

.form-note {
  padding: 12px 14px;
  border-radius: 10px;
  background: #fafbfa;
  color: #858d88;
  font-size: 8px;
  line-height: 1.5;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

.upload-panel {
  min-height: 320px;
}

.upload-form {
  display: grid;
  gap: 14px;
  padding: 7px 20px 22px;
}

.file-drop {
  min-height: 130px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 3px;
  border: 1px dashed #b9c6be;
  border-radius: 14px;
  background: #fafcfb;
  cursor: pointer;
}

.file-drop input {
  display: none;
}

.file-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  margin-bottom: 4px;
  border-radius: 50%;
  background: #e9f4ee;
  color: #147a4a;
  font-size: 18px;
  font-weight: 900;
}

.file-drop strong {
  font-size: 9px;
}

.file-drop small {
  color: #9aa19d;
  font-size: 7px;
}

.progress-number {
  color: #147a4a;
  font-size: 20px;
}

.progress-card {
  padding: 10px 20px 22px;
}

.progress-card p {
  margin: 13px 0 0;
  color: #87908b;
  font-size: 9px;
  line-height: 1.55;
}

.document-list {
  padding: 0 20px 12px;
}

.document-row {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-top: 1px solid #edf0ee;
}

.document-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 38px;
  border-radius: 8px;
  background: #f8ecef;
  color: #8e2843;
  font-size: 7px;
  font-weight: 950;
}

.document-main strong,
.document-main span {
  display: block;
}

.document-main strong {
  font-size: 9px;
}

.document-main span {
  margin-top: 3px;
  color: #9da39f;
  font-size: 7px;
}

.history-list {
  padding: 0 20px 12px;
}

.history-row {
  display: grid;
  grid-template-columns: .8fr 1.5fr 1.1fr .8fr auto;
  align-items: center;
  gap: 18px;
  padding: 16px 0;
  border-top: 1px solid #edf0ee;
}

.history-row:first-child {
  border-top: 0;
}

.history-folio strong {
  color: #147a4a;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: fit-content;
  padding: 6px 9px;
  border-radius: 99px;
  background: #f0f2f1;
  color: #6e7772;
  font-size: 7px;
  font-weight: 900;
  white-space: nowrap;
}

.badge.success { background: #e8f5ed; color: #147a4a; }
.badge.warning { background: #fff4da; color: #956910; }
.badge.info { background: #e9f3fa; color: #2e6f98; }
.badge.purple { background: #f1ebf8; color: #71519a; }
.badge.danger { background: #f9e9ed; color: #9a3149; }

.count-badge {
  display: grid;
  place-items: center;
  min-width: 29px;
  height: 29px;
  padding: 0 8px;
  border-radius: 99px;
  background: #edf6f1;
  color: #147a4a;
  font-size: 9px;
  font-weight: 900;
}

.primary-button,
.soft-button,
.text-button,
.warning-banner button {
  border: 0;
  border-radius: 10px;
  padding: 10px 14px;
  font: inherit;
  font-size: 8px;
  font-weight: 850;
  cursor: pointer;
}

.primary-button {
  background: #147a4a;
  color: white;
  box-shadow: 0 6px 14px rgba(20,122,74,.17);
}

.primary-button:hover { background: #106d41; }
.primary-button:disabled { cursor: not-allowed; opacity: .55; }

.soft-button {
  border: 1px solid #dfe4e1;
  background: white;
  color: #59625d;
}

.text-button {
  padding: 7px 9px;
  background: #f4f8f6;
  color: #147a4a;
  text-decoration: none;
}

.empty-state {
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px;
  text-align: center;
}

.empty-state.compact { min-height: 190px; }
.empty-state .primary-button { margin-top: 16px; }

.empty-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin-bottom: 11px;
  border-radius: 50%;
  background: #f0f5f2;
  color: #147a4a;
  font-size: 23px;
}

.empty-state strong { font-size: 12px; }
.empty-state span {
  max-width: 390px;
  margin-top: 5px;
  color: #939a96;
  font-size: 8px;
  line-height: 1.5;
}

.warning-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 16px;
  padding: 13px 15px;
  border: 1px solid #f0d6a0;
  border-radius: 12px;
  background: #fff9ed;
}

.warning-banner strong,
.warning-banner span { display: block; }
.warning-banner strong { color: #8f6615; font-size: 9px; }
.warning-banner span { margin-top: 2px; color: #8b8069; font-size: 8px; }
.warning-banner button { background: #f5e8ca; color: #7f5d17; }

.loading-card {
  min-height: 360px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e6e3;
  border-radius: 18px;
  background: white;
}

.spinner {
  width: 34px;
  height: 34px;
  margin-bottom: 14px;
  border: 3px solid #e5ebe7;
  border-top-color: #147a4a;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

.loading-card strong { font-size: 11px; }
.loading-card span { margin-top: 4px; color: #9aa19d; font-size: 8px; }

.toast {
  position: fixed;
  top: 90px;
  right: 24px;
  z-index: 100;
  max-width: 360px;
  padding: 12px 15px;
  border-radius: 11px;
  background: #174f35;
  color: white;
  box-shadow: 0 14px 36px rgba(0,0,0,.16);
  font-size: 9px;
  font-weight: 750;
}

.toast.error { background: #8e2843; }

.toast-enter-active,
.toast-leave-active { transition: .2s ease; }
.toast-enter-from,
.toast-leave-to { opacity: 0; transform: translateY(-8px); }

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 1050px) {
  .topbar-inner { grid-template-columns: 1fr auto; }
  .nav {
    order: 3;
    grid-column: 1 / -1;
    justify-content: center;
    margin-bottom: 10px;
  }
  .hero, .dashboard-grid, .documents-grid { grid-template-columns: 1fr; }
  .hero-status { border-left: 0; border-top: 1px solid rgba(255,255,255,.1); }
  .kpis { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 720px) {
  .topbar-inner, .main { width: min(100% - 22px, 1240px); }
  .brand-copy, .profile-copy { display: none; }
  .nav { overflow-x: auto; justify-content: flex-start; }
  .nav button { white-space: nowrap; }
  .hero-copy, .hero-status { padding: 25px 22px; }
  .kpis { grid-template-columns: 1fr 1fr; }
  .details-grid, .request-summary, .application-form { grid-template-columns: 1fr; }
  .application-form .full, .conv-mini { grid-column: auto; }
  .history-row { grid-template-columns: 1fr 1fr; }
  .history-row .badge { grid-column: 1 / -1; }
  .document-row { grid-template-columns: auto 1fr; }
  .document-row .badge, .document-row .text-button { grid-column: 2; }
}

@media (max-width: 460px) {
  .kpis { grid-template-columns: 1fr; }
  .profile .logout { padding: 7px 8px; }
  .hero-actions, .action-strip { align-items: stretch; flex-direction: column; }
  .history-row { grid-template-columns: 1fr; }
  .history-row .badge { grid-column: auto; }
}
</style>