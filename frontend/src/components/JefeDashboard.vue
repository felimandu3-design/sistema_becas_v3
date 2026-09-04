<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'

// Importamos a los 4 hijos
import JefeHeader from './Jefe/JefeHeader.vue'
import JefeEstadisticas from './Jefe/JefeEstadisticas.vue'
import JefeTabla from './Jefe/JefeTabla.vue'
import JefeModalDictamen from './Jefe/JefeModalDictamen.vue'

/*
|--------------------------------------------------------------------------
| PROPS & EMITS
|--------------------------------------------------------------------------
*/
const props = defineProps({
    usuario: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['cerrar-sesion'])

/*
|--------------------------------------------------------------------------
| ESTADO GLOBAL DEL PADRE
|--------------------------------------------------------------------------
*/
const grupos = ref([])
const grupoSeleccionado = ref(null)

const solicitudes = ref([])
const cargando = ref(true)
const error = ref('')

const solicitudSeleccionada = ref(null)
const guardandoDictamen = ref(false)

const modalLogout = ref(false)
const mensajeExito = ref('')

/*
|--------------------------------------------------------------------------
| DATOS COMPUTADOS
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| DATOS COMPUTADOS
|--------------------------------------------------------------------------
*/
const carreraJefe = computed(() => {
    const u = props.usuario?.user || props.usuario

    if (u?.carrera?.nombre) return u.carrera.nombre
    if (u?.carrera_nombre) return u.carrera_nombre

    if (Array.isArray(u?.carreras_asignadas) && u.carreras_asignadas.length > 0) {
        return u.carreras_asignadas[0].nombre
    }

    if (Array.isArray(grupos.value) && grupos.value.length > 0) {
        if (grupos.value[0].carrera?.nombre) return grupos.value[0].carrera.nombre
    }

    return 'Carrera asignada'
})

// 1. Declarar PRIMERO la propiedad de solicitudes filtradas
const solicitudesFiltradas = computed(() => {
    const lista = Array.isArray(solicitudes.value) ? solicitudes.value : []
    
    if (!grupoSeleccionado.value) return lista

    return lista.filter(s => {
        const idGrupoSol = Number(s.grupo_id || s.usuario?.grupo_id || s.grupo_relacion?.id || 0)
        return idGrupoSol === Number(grupoSeleccionado.value.id)
    })
})

// 2. Declarar DESPUÉS las estadísticas para que puedan usar solicitudesFiltradas.value
const estadisticas = computed(() => {
    const fuente = grupoSeleccionado.value ? solicitudesFiltradas.value : (Array.isArray(solicitudes.value) ? solicitudes.value : [])
    const estados = fuente.map(s => String(s.estado || s.estatus || 'PENDIENTE').trim().toUpperCase())
    
    return {
        total: fuente.length,
        pendientes: estados.filter(e => e === 'PENDIENTE').length,
        revision: estados.filter(e => e === 'EN_REVISION').length,
        aceptadas: estados.filter(e => e === 'ACEPTADA').length,
        rechazadas: estados.filter(e => e === 'RECHAZADA').length,
        incompletas: estados.filter(e => e === 'DOCUMENTACION_INCOMPLETA').length,
    }
})

const porcentajeAtendidas = computed(() => {
    if (estadisticas.value.total === 0) return 0
    const atendidas = estadisticas.value.aceptadas + estadisticas.value.rechazadas
    return Math.round((atendidas / estadisticas.value.total) * 100)
})

/*
|--------------------------------------------------------------------------
| LLAMADAS A LA API
|--------------------------------------------------------------------------
*/
async function cargarDatosIniciales() {
    cargando.value = true
    error.value = ''
    try {
        // Cargar solicitudes y grupos en paralelo
        const [resSolicitudes, resGrupos] = await Promise.allSettled([
            api.get('/admin/solicitudes'),
            api.get('/admin/grupos')
        ])

        if (resSolicitudes.status === 'fulfilled') {
            const data = resSolicitudes.value.data
            solicitudes.value = data?.data || data?.solicitudes || (Array.isArray(data) ? data : [])
        }

        if (resGrupos.status === 'fulfilled') {
            const dataG = resGrupos.value.data
            grupos.value = dataG?.data || (Array.isArray(dataG) ? dataG : [])
        }
    } catch (err) {
        console.error('Error al obtener datos del Jefe de Carrera:', err)
        error.value = 'No fue posible cargar la información completa.'
    } finally {
        cargando.value = false
    }
}

async function guardarDictamen(payload) {
    if (!solicitudSeleccionada.value) return
    guardandoDictamen.value = true

    try {
        const { data } = await api.patch(`/admin/solicitudes/${solicitudSeleccionada.value.id}/dictamen`, {
            estado: payload.estado,
            observaciones: payload.observaciones || null,
            porcentaje_beca: payload.porcentaje_beca || payload.porcentaje_descuento || null,
        })

        const actualizado = data?.data
        if (actualizado) {
            const indice = solicitudes.value.findIndex(s => s.id === actualizado.id)
            if (indice !== -1) solicitudes.value[indice] = actualizado
        } else {
            await cargarDatosIniciales()
        }

        mensajeExito.value = data?.message || 'Solicitud actualizada correctamente.'
        solicitudSeleccionada.value = null

        setTimeout(() => { mensajeExito.value = '' }, 3500)
    } catch (err) {
        console.error('Error guardando dictamen:', err)
        alert(err?.response?.data?.message || 'No fue posible guardar el dictamen.')
    } finally {
        guardandoDictamen.value = false
    }
}

async function marcarEnRevision() {
    if (!solicitudSeleccionada.value) return
    try {
        await api.patch(`/admin/solicitudes/${solicitudSeleccionada.value.id}/estatus`, { estado: 'EN_REVISION' })
        await cargarDatosIniciales()
        solicitudSeleccionada.value = null
    } catch (err) {
        alert(err?.response?.data?.message || 'No fue posible cambiar el estado.')
    }
}

/*
|--------------------------------------------------------------------------
| NAVEGACIÓN Y ACCIONES
|--------------------------------------------------------------------------
*/
function contarSolicitudesPorGrupo(grupoId) {
    return solicitudes.value.filter(s => {
        const idGrupoSol = s.grupo_id || s.usuario?.grupo_id || s.alumno?.grupo_id
        return idGrupoSol === grupoId
    }).length
}

function seleccionarGrupo(grupo) {
    grupoSeleccionado.value = grupo
}

function volverAGrupos() {
    grupoSeleccionado.value = null
}

function scrollASolicitudes() {
    document.getElementById('solicitudes')?.scrollIntoView({ behavior: 'smooth' })
}

function confirmarCerrarSesion() { modalLogout.value = true }
function cerrarSesion() {
    modalLogout.value = false
    emit('cerrar-sesion')
}

onMounted(() => {
    cargarDatosIniciales()
})
</script>

<template>
    <div class="jefe-dashboard">

        <!-- 1. HEADER (Hijo 1) -->
        <JefeHeader 
            :usuario="usuario" 
            @scroll-solicitudes="scrollASolicitudes"
            @cerrar-sesion="confirmarCerrarSesion"
        />

        <main class="main-content">
            
            <!-- ALERTA DE ÉXITO -->
            <div v-if="mensajeExito" class="success-alert">
                <div class="check-mini">✓</div>
                {{ mensajeExito }}
            </div>

            <!-- 2. ESTADÍSTICAS (Hijo 2) -->
            <JefeEstadisticas 
                :carrera-jefe="carreraJefe"
                :estadisticas="estadisticas"
                :porcentaje-atendidas="porcentajeAtendidas"
            />

            <!-- VISTA DE TARJETAS DE GRUPOS (Se oculta al seleccionar un grupo) -->
            <section v-if="!grupoSeleccionado" class="grupos-section">
                <div class="section-title">
                    <h3>Grupos de la Carrera</h3>
                    <p>Selecciona un grupo para revisar las solicitudes de beca de sus alumnos</p>
                </div>

                <div v-if="cargando" class="loading-state">
                    Cargando grupos...
                </div>

                <div v-else-if="grupos.length" class="grupos-grid">
                    <div v-for="grupo in grupos" :key="grupo.id" class="grupo-card">
                        <div class="card-top">
                            <span class="status-badge">{{ grupo.estado || grupo.estatus || 'ACTIVO' }}</span>
                            <span class="turno-badge">{{ grupo.turno || 'VESPERTINO' }}</span>
                        </div>

                        <div class="card-body">
                            <h4 class="grupo-name">{{ grupo.nombre || grupo.clave }}</h4>
                            <p class="grupo-sub">{{ grupo.carrera?.nombre || grupo.carrera?.clave || 'Carrera asignada' }}</p>

                            <div class="info-box">
                                <p>Cuatrimestre: <strong>{{ grupo.cuatrimestre || 1 }}°</strong></p>
                                <p>Profesor Tutor: <strong>{{ grupo.tutor?.name || grupo.profesor || 'Sin asignar' }}</strong></p>
                                <p>Solicitudes registradas: <strong class="text-solicitudes">{{ contarSolicitudesPorGrupo(grupo.id) }}</strong></p>
                            </div>
                        </div>

                        <div class="card-actions">
                            <button 
                                type="button" 
                                class="btn-card-primary" 
                                @click="seleccionarGrupo(grupo)"
                            >
                                📋 Ver solicitudes del grupo
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state">
                    No hay grupos registrados en esta carrera.
                </div>
            </section>

            <!-- 3. TABLA Y FILTROS (ÚNICA INSTANCIA - SE MUESTRA AL SELECCIONAR UN GRUPO) -->
            <div v-if="grupoSeleccionado" id="solicitudes" class="solicitudes-container">
                <JefeTabla 
                    :solicitudes="solicitudesFiltradas || solicitudes"
                    :grupo-seleccionado="grupoSeleccionado" 
                    :cargando="cargando"
                    :error="error"
                    @recargar="cargarDatosIniciales || cargarSolicitudes"
                    @revisar="(sol) => solicitudSeleccionada = sol"
                    @volver="grupoSeleccionado = null"
                />
            </div>

        </main>

        <!-- 4. MODAL DE REVISIÓN Y PDF (Hijo 4) -->
        <JefeModalDictamen 
            v-if="solicitudSeleccionada"
            :solicitud="solicitudSeleccionada"
            :guardando="guardandoDictamen"
            @cerrar="solicitudSeleccionada = null"
            @guardar-dictamen="guardarDictamen"
            @marcar-revision="marcarEnRevision"
        />

        <!-- MODAL LOGOUT -->
        <div v-if="modalLogout" class="modal-overlay" @click.self="modalLogout = false">
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
                    <button type="button" class="secondary-button" @click="modalLogout = false">Cancelar</button>
                    <button type="button" class="primary-button logout-confirm" @click="cerrarSesion">Cerrar sesión</button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
* { 
    box-sizing: border-box; 
}

.jefe-dashboard {
    min-height: 100vh;
    background: linear-gradient(180deg, #f5f7f6 0%, #fbfcfb 50%, #f4f6f5 100%);
    color: #242a27;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
}

.main-content {
    width: min(1220px, calc(100% - 40px));
    margin: auto;
    padding: 30px 0 80px;
}

/* ================================================================
   ALERTA DE ÉXITO
================================================================ */
.success-alert {
    margin-bottom: 18px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #d9eadd;
    border-radius: 13px;
    background: #edf7f0;
    color: #25623d;
    font-size: 11px;
    font-weight: 700;
}

.check-mini {
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #247548;
    color: #fff;
    font-size: 11px;
}

/* ================================================================
   SECCIÓN DE GRUPOS Y TARJETAS
================================================================ */
.grupos-section {
    margin: 28px 0;
}

.section-title h3 { 
    margin: 0; 
    font-size: 16px; 
    font-weight: 800; 
    color: #1e293b; 
}

.section-title p { 
    margin: 4px 0 16px; 
    font-size: 12px; 
    color: #64748b; 
}

.grupos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.grupo-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.grupo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-badge {
    background: #e6f4ea;
    color: #137333;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.turno-badge {
    color: #94a3b8;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.05em;
}

.grupo-name {
    margin: 12px 0 2px;
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
}

.grupo-sub {
    margin: 0 0 12px;
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
}

.info-box {
    background: #f8fafc;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 12px;
    color: #475569;
}

.info-box p { 
    margin: 4px 0; 
}

.info-box strong { 
    color: #1e293b; 
}

.text-solicitudes {
    color: #247548;
    font-weight: 800;
}

.card-actions {
    display: flex;
    margin-top: 16px;
}

.btn-card-primary {
    width: 100%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 750;
    cursor: pointer;
    border: 1px solid #10b981;
    background: #ecfdf5;
    color: #047857;
    transition: all 0.2s ease;
}

.btn-card-primary:hover {
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.loading-state, 
.empty-state {
    text-align: center;
    padding: 30px;
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 13px;
}

/* ================================================================
   CONTENEDOR DE LA TABLA
================================================================ */
.solicitudes-container {
    margin-top: 24px;
}

/* ================================================================
   MODAL LOGOUT
================================================================ */
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    padding: 20px;
    display: grid;
    place-items: center;
    background: rgba(19, 27, 22, .45);
    backdrop-filter: blur(5px);
}

.modal {
    position: relative;
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 30px 85px rgba(18, 27, 22, .23);
}

.logout-modal {
    width: min(405px, 100%);
    text-align: center;
    padding: 29px;
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
    font-size: 10px; 
}

.logout-actions {
    margin-top: 22px;
    display: flex;
    gap: 9px;
}

.primary-button, 
.secondary-button {
    min-height: 40px;
    padding: 0 15px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 850;
    cursor: pointer;
}

.primary-button { 
    border: 0; 
    background: #247548; 
    color: #fff; 
}

.secondary-button { 
    flex: 1; 
    border: 1px solid #e0e4e1; 
    background: #fff; 
    color: #666e69; 
}

.logout-confirm { 
    flex: 1; 
    background: #7a1c33; 
}

/* RESPONSIVE */
@media (max-width: 520px) {
    .main-content { 
        width: calc(100% - 24px); 
        padding-top: 20px; 
    }
    .grupos-grid { 
        grid-template-columns: 1fr; 
    }
}
</style>