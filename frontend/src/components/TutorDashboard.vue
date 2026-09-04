<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { useSolicitudes } from '../composables/useSolicitudes.js'

// Importaciones relativas de subcomponentes
import SolicitudesFiltros from './Solicitudes/SolicitudesFiltros.vue'
import SolicitudesTabla from './Solicitudes/SolicitudesTabla.vue'
import SolicitudDetalleModal from './Solicitudes/SolicitudDetalleModal.vue'

// 1. Recibir los datos del usuario autenticado que envía App.vue
const props = defineProps({
  usuario: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['cerrar-sesion'])

// Extraemos lógica del composable
const { 
  solicitudes,
  solicitudesFiltradas, 
  cargando, 
  error, 
  mensajeExito, 
  busqueda, 
  filtroEstado, 
  cambiarEstado,
  obtenerSolicitudes 
} = useSolicitudes()

const solicitudSeleccionada = ref(null)
const vistaActual = ref('resumen')
const modalLogout = ref(false)

const abrirModal = (solicitud) => { solicitudSeleccionada.value = solicitud }
const cerrarModal = () => { solicitudSeleccionada.value = null }

// Contadores defensivos para las tarjetas de estado
const contarPorEstado = (estadoBuscado) => {
  if (!Array.isArray(solicitudes.value)) return 0
  
  const target = estadoBuscado.toLowerCase().trim()
  
  return solicitudes.value.filter(s => {
    // Revisa 'estado' o 'estatus' o 'status' y convierte a minúsculas
    const val = (s.estado || s.estatus || s.status || '').toString().toLowerCase().trim()
    
    // Mapeo flexible para PENDIENTE
    if (target === 'pendiente') {
      return val === 'pendiente' || val === 'sin_revisar' || val === '0'
    }
    // Mapeo flexible para EN_REVISION / SEGUIMIENTO
    if (target === 'en_revision' || target === 'seguimiento') {
      return val === 'en_revision' || val === 'revision' || val === 'seguimiento'
    }
    // Mapeo flexible para ACEPTADA
    if (target === 'aceptada') {
      return val === 'aceptada' || val === 'aprobada' || val === 'dictaminada'
    }
    // Mapeo flexible para RECHAZADA
    if (target === 'rechazada') {
      return val === 'rechazada' || val === 'no_aprobada'
    }
    
    return val === target
  }).length
}

const confirmarCerrarSesion = () => {
  modalLogout.value = true
}

const cerrarSesion = () => {
  modalLogout.value = false
  emit('cerrar-sesion')
}

// Total de solicitudes del grupo
const totalAlumnosConSolicitud = computed(() => {
  return Array.isArray(solicitudes.value) ? solicitudes.value.length : 0
})

// 2. Obtener el grupo directamente del objeto usuario prop
const grupoAsignado = computed(() => {
  // 1. Intentar obtenerlo desde el objeto de usuario
  if (props.usuario?.grupo?.nombre) return props.usuario.grupo.nombre
  if (props.usuario?.grupo?.clave) return props.usuario.grupo.clave
  if (typeof props.usuario?.grupo === 'string' && props.usuario.grupo.trim() !== '') {
    return props.usuario.grupo
  }

  // 2. Fallback al recargar (F5): Si el usuario no trae el grupo cargado,
  if (solicitudes.value && solicitudes.value.length > 0) {
    const primeraSol = solicitudes.value[0]
    const grupoEncontrado = primeraSol.usuario?.grupo || primeraSol.grupo || primeraSol.alumno?.grupo
    if (grupoEncontrado) return grupoEncontrado
  }

  // 3. Fallback final
  return 'Sin asignación'
})

// Carrera del grupo (Informativa)
// Carrera del grupo asignado (Vista Tutor)
const carreraAsignada = computed(() => {
  const u = props.usuario?.user || props.usuario

  // 1. Si el profesor/tutor trae la carrera directa o a través de su grupo asignado
  if (u?.carrera?.nombre) return u.carrera.nombre
  if (u?.grupo?.carrera?.nombre) return u.grupo.carrera.nombre
  if (u?.grupo_relacion?.carrera?.nombre) return u.grupo_relacion.carrera.nombre

  // 2. Extraer arreglo de solicitudes (manejando si viene array directo o paginado/en .data)
  const lista = Array.isArray(solicitudes.value) 
    ? solicitudes.value 
    : (solicitudes.value?.data || [])

  // 3. Buscar la carrera en la primera solicitud del grupo de alumnos
  if (lista.length > 0) {
    const primeraSol = lista[0]
    return (
      primeraSol.carrera?.nombre ||
      primeraSol.usuario?.carrera?.nombre ||
      primeraSol.usuario?.grupo_relacion?.carrera?.nombre ||
      primeraSol.grupo_relacion?.carrera?.nombre ||
      primeraSol.alumno?.carrera?.nombre ||
      'Sin asignación'
    )
  }

  return 'Sin asignación'
})


const cambiarEstadoSolicitud = async (id, nuevoEstado) => {
  try {
    // 1. Obtener el token guardado tras el login (ajústalo al nombre con el que guardas tu token)
    const token = localStorage.getItem('token') || localStorage.getItem('auth_token')

    // 2. Definir los headers de autenticación
    const config = {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }

    // 3. Petición apuntando al puerto de Laravel
    await axios.patch(`http://127.0.0.1:8000/api/profesor/solicitudes/${id}/estatus`, {
      estatus: nuevoEstado,
      estado: nuevoEstado
    }, config)

    // 4. Actualización reactiva de la vista
    if (solicitudSeleccionada.value) {
      solicitudSeleccionada.value.estado = nuevoEstado
      solicitudSeleccionada.value.estatus = nuevoEstado
    }

    if (typeof solicitudes !== 'undefined' && solicitudes.value) {
      const index = solicitudes.value.findIndex(s => s.id === id)
      if (index !== -1) {
        solicitudes.value[index].estado = nuevoEstado
        solicitudes.value[index].estatus = nuevoEstado
      }
    }

    alert(`Estado actualizado a: ${nuevoEstado}`)
  } catch (error) {
    console.error('Error al actualizar:', error.response?.data || error)
    alert(`No se pudo actualizar: ${error.response?.data?.message || 'Error de permisos o ruta'}`)
  }
}

</script>

<template>
  <div class="tutor-dashboard">
    <!-- TOPBAR / HEADER ORIGINAL -->
    <header class="topbar">
      <div class="topbar-inner">
        <!-- Marca UPTex -->
        <div class="brand">
          <div class="brand-mark">
            <span class="up">UP</span>
            <span class="t">T</span>
            <span class="ex">ex</span>
          </div>
          <div class="brand-info">
            <strong>Sistema de Becas</strong>
            <span>Seguimiento académico</span>
          </div>
        </div>

        <!-- Navegación central -->
        <nav class="navigation">
          <button 
            type="button" 
            :class="['navigation-button', { active: vistaActual === 'resumen' }]"
            @click="vistaActual = 'resumen'"
          >
            Resumen
          </button>
          <button 
            type="button" 
            :class="['navigation-button', { active: vistaActual === 'alumnos' }]"
            @click="
              vistaActual = 'alumnos';
              document.getElementById('lista-solicitudes')?.scrollIntoView({ behavior: 'smooth' });
            "
          >
            Alumnos
          </button>
        </nav>

        <!-- Perfil del Tutor -->
        <div class="profile">
          <div class="profile-data">
            <strong>{{ props.usuario?.name || 'Profesor Tutor' }}</strong>
            <span>Tutor académico</span>
          </div>
          <div class="avatar">
            {{ props.usuario?.name ? props.usuario.name.charAt(0).toUpperCase() : 'PT' }}
          </div>
          <button 
            type="button" 
            class="logout" 
            title="Cerrar sesión" 
            @click="confirmarCerrarSesion"
          >
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 17l5-5-5-5" />
              <path d="M15 12H3" />
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- MAIN CONTAINER -->
    <main class="main">
      <!-- Alertas -->
      <transition name="fade">
        <div v-if="mensajeExito" class="success-message">
          <div class="success-icon">✓</div>
          {{ mensajeExito }}
        </div>
      </transition>

      <transition name="fade">
        <div v-if="error" class="alerta-error">
          {{ error }}
        </div>
      </transition>

      <!-- SECCIÓN BIENVENIDA -->
      <section class="welcome">
        <div>
          <span class="eyebrow">PORTAL DEL TUTOR</span>
          <h1>Hola, {{ props.usuario?.name ? props.usuario.name.split(' ')[0] : 'Profesor' }}</h1>
          <p>Consulta el seguimiento académico de los alumnos que solicitaron una beca.</p>
        </div>

        <div class="career-cards-container">
          <div class="career-box">
            <span>GRUPO ASIGNADO</span>
            <strong class="group-highlight">{{ grupoAsignado }}</strong>
          </div>
          <div class="career-box">
            <span>CARRERA</span>
            <strong>{{ carreraAsignada }}</strong>
          </div>
        </div>
      </section>

<!-- TARJETAS DE MÉTRICAS -->
<section class="stats">
  <article class="stat">
    <div class="stat-icon neutral">
      <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
      </svg>
    </div>
    <div>
      <span>Alumnos</span>
      <strong>{{ Array.isArray(solicitudes) ? solicitudes.length : (solicitudes?.data?.length || 0) }}</strong>
      <small>Con solicitud</small>
    </div>
  </article>

  <article class="stat">
    <div class="stat-icon warning">
      <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </svg>
    </div>
    <div>
      <span>Pendientes</span>
      <strong>{{ contarPorEstado('PENDIENTE') }}</strong>
      <small>Sin revisar</small>
    </div>
  </article>

  <article class="stat">
    <div class="stat-icon info">
      <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 19V5" />
        <path d="M4 19h16" />
        <path d="M8 15l3-4 3 2 4-6" />
      </svg>
    </div>
    <div>
      <span>Seguimiento</span>
      <strong>{{ contarPorEstado('EN_REVISION') }}</strong>
      <small>En revisión</small>
    </div>
  </article>

  <article class="stat">
    <div class="stat-icon success">
      <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M5 12l4 4L19 6" />
      </svg>
    </div>
    <div>
      <span>Aceptadas</span>
      <strong>{{ contarPorEstado('ACEPTADA') }}</strong>
      <small>Dictaminadas</small>
    </div>
  </article>

  <article class="stat">
    <div class="stat-icon danger">
      <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 6l12 12" />
        <path d="M18 6L6 18" />
      </svg>
    </div>
    <div>
      <span>Rechazadas</span>
      <strong>{{ contarPorEstado('RECHAZADA') }}</strong>
      <small>No aprobadas</small>
    </div>
  </article>
</section>


      <!-- BANNER INFORMATIVO -->
      <section class="notice">
        <div class="notice-icon">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 16v-4" />
            <path d="M12 8h.01" />
          </svg>
        </div>
        <div>
          <strong>Seguimiento del tutor</strong>
          <p>
            Desde este panel puedes consultar alumnos, documentación y marcar solicitudes para seguimiento.
            El dictamen final corresponde al área administrativa.
          </p>
        </div>
      </section>

      <!-- SECCIÓN DE CONTENIDO PRINCIPAL -->
      <section id="lista-solicitudes" class="requests">
        <div class="requests-header">
          <div>
            <span class="eyebrow">SEGUIMIENTO</span>
            <h2>Solicitudes de alumnos</h2>
            <p>{{ Array.isArray(solicitudesFiltradas) ? solicitudesFiltradas.length : 0 }} resultado(s)</p>
          </div>

          <button 
            type="button" 
            class="refresh" 
            @click="obtenerSolicitudes" 
            :disabled="cargando"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" :class="{'spin': cargando}">
              <path d="M20 11a8 8 0 1 0-2 5.3" />
              <path d="M20 4v7h-7" />
            </svg>
            Actualizar
          </button>
        </div>

        <!-- Componente Filtros -->
        <div class="filters-container">
          <SolicitudesFiltros 
            v-model:busqueda="busqueda"
            v-model:filtroEstado="filtroEstado"
          />
        </div>

        <!-- Componente Tabla -->
        <SolicitudesTabla 
          :solicitudes="solicitudesFiltradas"
          :cargando="cargando"
          @seleccionar="abrirModal"
        />
      </section>
    </main>

    <!-- Modal Detalle Subcomponente -->
    <SolicitudDetalleModal
  v-if="solicitudSeleccionada"
  :solicitud="solicitudSeleccionada"
  @cerrar="solicitudSeleccionada = null"
  @actualizado="cargarSolicitudes"
/>

    <!-- MODAL CERRAR SESIÓN -->
    <div 
      v-if="modalLogout" 
      class="modal-overlay" 
      @click.self="modalLogout = false"
    >
      <div class="modal logout-modal">
        <div class="logout-modal-icon">
          <svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10 17l5-5-5-5" />
            <path d="M15 12H3" />
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
          </svg>
        </div>
        <h2>¿Cerrar sesión?</h2>
        <p>Volverás a la pantalla principal del sistema.</p>

        <div class="logout-actions">
          <button type="button" class="secondary" @click="modalLogout = false">
            Cancelar
          </button>
          <button type="button" class="primary close-session" @click="cerrarSesion">
            Cerrar sesión
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ================================================================
   BASE & LAYOUT
================================================================ */
* {
  box-sizing: border-box;
}

.tutor-dashboard {
  min-height: 100vh;
  background: linear-gradient(180deg, #f5f7f6 0%, #fcfdfc 55%, #f4f6f5 100%);
  color: #272e2a;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* ================================================================
   TOPBAR
================================================================ */
.topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  border-bottom: 1px solid #e4e8e5;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(18px);
}

.topbar-inner {
  width: min(1200px, calc(100% - 40px));
  height: 76px;
  margin: auto;
  display: flex;
  align-items: center;
  gap: 34px;
}

/* BRAND */
.brand {
  min-width: 260px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-mark {
  display: flex;
  align-items: baseline;
  font-size: 22px;
  font-weight: 900;
  letter-spacing: normal;
  line-height: 1;
}

.brand-mark .up { 
  color: #247548; 
  margin-right: 2px;
}

.brand-mark .t { 
  color: #bb1f38; 
  margin-right: 1px;
}

.brand-mark .ex { 
  color: #727975; 
}

.brand-info {
  display: flex;
  flex-direction: column;
}

.brand-info strong {
  font-size: 13px;
  line-height: 1.2;
}

.brand-info span {
  font-size: 10px;
  color: #727975;
}

/* NAVIGATION */
.navigation {
  flex: 1;
  display: flex;
  justify-content: center;
  gap: 6px;
}

.navigation-button {
  padding: 10px 14px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #747b77;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.navigation-button:hover,
.navigation-button.active {
  background: #edf5f0;
  color: #247548;
}

/* PROFILE */
.profile {
  display: flex;
  align-items: center;
  gap: 10px;
}

.profile-data {
  display: flex;
  flex-direction: column;
  text-align: right;
}

.profile-data strong { font-size: 11px; }
.profile-data span {
  color: #999f9b;
  font-size: 9px;
}

.avatar {
  width: 38px;
  height: 38px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: linear-gradient(145deg, #247548, #409063);
  color: #fff;
  font-size: 11px;
  font-weight: 850;
}

.logout {
  width: 38px;
  height: 38px;
  display: grid;
  place-items: center;
  border: 1px solid #e2e5e3;
  border-radius: 10px;
  background: #fff;
  color: #737a76;
  cursor: pointer;
}

.logout:hover {
  background: #fff5f7;
  border-color: #ecd4db;
  color: #7a1c33;
}

/* ================================================================
   MAIN CONTAINER
================================================================ */
.main {
  width: min(1200px, calc(100% - 40px));
  margin: auto;
  padding: 44px 0 80px;
}

.success-message {
  margin-bottom: 18px;
  padding: 13px 16px;
  display: flex;
  align-items: center;
  gap: 9px;
  border: 1px solid #d9eadf;
  border-radius: 13px;
  background: #edf7f1;
  color: #256541;
  font-size: 11px;
  font-weight: 750;
}

.success-icon {
  width: 22px;
  height: 22px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #247548;
  color: #fff;
}

.alerta-error {
  margin-bottom: 18px;
  padding: 13px 16px;
  border: 1px solid #fecaca;
  border-radius: 13px;
  background: #fef2f2;
  color: #991b1b;
  font-size: 11px;
  font-weight: 750;
}

/* ================================================================
   WELCOME
================================================================ */
.welcome {
  margin-bottom: 25px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 25px;
}

.eyebrow {
  display: block;
  color: #8d9490;
  font-size: 9px;
  font-weight: 850;
  letter-spacing: 0.16em;
}

.welcome h1 {
  margin: 7px 0 5px;
  font-size: clamp(28px, 4vw, 40px);
  line-height: 1.05;
  letter-spacing: -0.04em;
}

.welcome p {
  margin: 0;
  color: #7c847f;
  font-size: 13px;
}

.career-cards-container {
  display: flex;
  gap: 12px;
}

.career-box {
  min-width: 160px;
  padding: 14px 17px;
  border: 1px solid #e2e6e3;
  border-radius: 15px;
  background: #fff;
  box-shadow: 0 6px 18px rgba(30, 40, 34, 0.04);
}

.career-box span {
  display: block;
  color: #9ca29f;
  font-size: 8px;
  font-weight: 850;
  letter-spacing: 0.11em;
}

.career-box strong {
  display: block;
  margin-top: 5px;
  color: #39413d;
  font-size: 11px;
}

.group-highlight {
  color: #247548 !important;
  font-size: 13px !important;
  font-weight: 800;
}

/* ================================================================
   STATS CARDS
================================================================ */
.stats {
  margin-bottom: 18px;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 13px;
}

.stat {
  min-height: 110px;
  padding: 17px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  border: 1px solid #e4e7e5;
  border-radius: 17px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(27, 39, 32, 0.04);
}

.stat-icon {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 12px;
}

.stat-icon.neutral { color: #4d5551; background: #f0f2f1; }
.stat-icon.warning { color: #956516; background: #fff5da; }
.stat-icon.info { color: #356b91; background: #eaf3fa; }
.stat-icon.success { color: #247548; background: #eaf5ee; }
.stat-icon.danger { color: #85243d; background: #faedf1; }

.stat > div:last-child {
  display: flex;
  flex-direction: column;
}

.stat span {
  color: #969c98;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
}

.stat strong {
  margin-top: 2px;
  font-size: 24px;
  line-height: 1;
}

.stat small {
  margin-top: 5px;
  color: #a3a8a5;
  font-size: 9px;
}

/* ================================================================
   NOTICE / BANNER
================================================================ */
.notice {
  margin-bottom: 22px;
  padding: 17px 19px;
  display: flex;
  gap: 13px;
  align-items: center;
  border: 1px solid #dde8e1;
  border-radius: 15px;
  background: #f5faf7;
}

.notice-icon {
  width: 39px;
  height: 39px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: 11px;
  background: #e6f2ea;
  color: #247548;
}

.notice strong {
  color: #3e4742;
  font-size: 11px;
}

.notice p {
  margin: 4px 0 0;
  max-width: 760px;
  color: #838b86;
  font-size: 10px;
  line-height: 1.5;
}

/* ================================================================
   REQUESTS PANEL
================================================================ */
.requests {
  overflow: hidden;
  border: 1px solid #e3e7e4;
  border-radius: 21px;
  background: #fff;
  box-shadow: 0 13px 38px rgba(28, 40, 33, 0.05);
}

.requests-header {
  padding: 24px 25px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #edf0ee;
}

.requests-header h2 {
  margin: 6px 0 3px;
  font-size: 19px;
}

.requests-header p {
  margin: 0;
  color: #989e9b;
  font-size: 10px;
}

.refresh {
  height: 37px;
  padding: 0 13px;
  display: flex;
  gap: 7px;
  align-items: center;
  border: 1px solid #e1e5e2;
  border-radius: 10px;
  background: #fff;
  color: #626a66;
  font-size: 10px;
  font-weight: 800;
  cursor: pointer;
}

.filters-container {
  padding: 15px 24px;
  background: #fbfcfb;
  border-bottom: 1px solid #edf0ee;
}

/* ================================================================
   MODAL OVERLAY & LOGOUT
================================================================ */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  padding: 20px;
  display: grid;
  place-items: center;
  background: rgba(19, 27, 22, 0.45);
  backdrop-filter: blur(5px);
}

.logout-modal {
  width: min(400px, 100%);
  padding: 29px;
  border-radius: 22px;
  background: #fff;
  text-align: center;
  box-shadow: 0 30px 85px rgba(18, 27, 22, 0.23);
}

.logout-modal-icon {
  width: 50px;
  height: 50px;
  margin: 0 auto 13px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  color: #84253d;
  background: #faedf1;
}

.logout-modal h2 {
  margin: 0 0 6px;
  font-size: 18px;
}

.logout-modal p {
  margin: 0;
  color: #8a918d;
  font-size: 11px;
}

.logout-actions {
  margin-top: 22px;
  display: flex;
  gap: 9px;
}

.logout-actions button {
  flex: 1;
}

.primary,
.secondary {
  min-height: 40px;
  padding: 0 15px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 850;
  cursor: pointer;
}

.primary {
  border: 0;
  background: #247548;
  color: #fff;
}

.secondary {
  border: 1px solid #e0e4e1;
  background: #fff;
  color: #666e69;
}

.close-session {
  background: #7a1c33;
}

/* Animación de rotación para refrescar */
.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Transiciones */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ================================================================
   RESPONSIVE
================================================================ */
@media (max-width: 1000px) {
  .stats {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 800px) {
  .navigation { display: none; }
  .brand { flex: 1; }
  .profile-data { display: none; }
  .welcome {
    flex-direction: column;
    align-items: flex-start;
  }
  .career-cards-container { width: 100%; }
  .stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 520px) {
  .topbar-inner {
    width: calc(100% - 24px);
    height: 68px;
  }
  .brand-info span { display: none; }
  .main {
    width: calc(100% - 24px);
    padding-top: 30px;
  }
  .career-cards-container {
    flex-direction: column;
  }
  .stats { gap: 9px; }
  .stat {
    min-height: 100px;
    padding: 13px;
    gap: 9px;
  }
  .stat-icon {
    width: 35px;
    height: 35px;
  }
  .stat strong { font-size: 20px; }
  .requests-header { padding: 19px 17px 15px; }
}
</style>