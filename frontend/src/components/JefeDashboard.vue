<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'

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
| ESTADO GLOBAL
|--------------------------------------------------------------------------
*/
const tabActual = ref('resumen') // 'resumen' | 'revisadas'
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
| HELPERS DE FILTRADO Y ESTADO
|--------------------------------------------------------------------------
*/
const esEstadoRevisado = (estado) => {
    const e = String(estado || '').trim().toUpperCase()
    return ['ACEPTADA', 'RECHAZADA', 'CONFIRMADO', 'REVISADO_TUTOR'].includes(e)
}

const esEstadoPendiente = (estado) => {
    const e = String(estado || '').trim().toUpperCase()
    return ['PENDIENTE', 'EN_REVISION', 'DOCUMENTACION_INCOMPLETA'].includes(e)
}

/*
|--------------------------------------------------------------------------
| COMPUTADOS
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

// 1. Solicitudes según la pestaña actual
const solicitudesPorTab = computed(() => {
    const lista = Array.isArray(solicitudes.value) ? solicitudes.value : []
    return lista.filter(s => {
        const est = s.estado || s.estatus
        return tabActual.value === 'revisadas' ? esEstadoRevisado(est) : esEstadoPendiente(est)
    })
})

// 2. Solicitudes del grupo seleccionado
const solicitudesFiltradas = computed(() => {
    if (!grupoSeleccionado.value) return solicitudesPorTab.value

    return solicitudesPorTab.value.filter(s => {
        const idGrupoSol = Number(s.grupo_id || s.usuario?.grupo_id || s.grupo_relacion?.id || s.alumno?.grupo_id || 0)
        return idGrupoSol === Number(grupoSeleccionado.value.id)
    })
})

// 3. Métricas
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
    const totalGeneral = solicitudes.value.length
    if (totalGeneral === 0) return 0
    const atendidas = solicitudes.value.filter(s => esEstadoRevisado(s.estado || s.estatus)).length
    return Math.round((atendidas / totalGeneral) * 100)
})

/*
|--------------------------------------------------------------------------
| CONSULTAS Y METODOS
|--------------------------------------------------------------------------
*/
async function cargarDatosIniciales() {
    cargando.value = true
    error.value = ''
    try {
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
        console.error('Error al obtener datos:', err)
        error.value = 'No fue posible cargar la información completa.'
    } finally {
        cargando.value = false
    }
}

function contarSolicitudesPorGrupo(grupoId) {
    return solicitudesPorTab.value.filter(s => {
        const idGrupoSol = Number(s.grupo_id || s.usuario?.grupo_id || s.grupo_relacion?.id || s.alumno?.grupo_id || 0)
        return idGrupoSol === Number(grupoId)
    }).length
}

function cambiarTab(nuevoTab) {
    tabActual.value = nuevoTab
    // Mantener o resetear el grupo según el flujo deseado
}

function seleccionarGrupoPorId(grupoId) {
    if (!grupoId) {
        grupoSeleccionado.value = null
        return
    }
    const encontrado = grupos.value.find(g => Number(g.id) === Number(grupoId))
    grupoSeleccionado.value = encontrado || null
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
        alert(err?.response?.data?.message || 'Error al guardar el dictamen.')
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

        <!-- HEADER -->
        <JefeHeader 
            :usuario="usuario" 
            :tab-actual="tabActual"
            @cambiar-tab="cambiarTab"
            @scroll-solicitudes="scrollASolicitudes"
            @cerrar-sesion="confirmarCerrarSesion"
        />

        <main class="main-content">
            
            <div v-if="mensajeExito" class="success-alert">
                <div class="check-mini">✓</div>
                {{ mensajeExito }}
            </div>

            <!-- ESTADÍSTICAS -->
            <JefeEstadisticas 
                :carrera-jefe="carreraJefe"
                :estadisticas="estadisticas"
                :porcentaje-atendidas="porcentajeAtendidas"
            />

            <!-- CONTROL BAR: SELECTOR DE GRUPOS NATIVO -->
            <section class="group-selector-bar">
                <div class="selector-info">
                    <label for="select-grupo">Filtrar por grupo:</label>
                    <div class="select-wrapper">
                        <select 
                            id="select-grupo"
                            :value="grupoSeleccionado?.id || ''"
                            @change="e => seleccionarGrupoPorId(e.target.value)"
                        >
                            <option value="">-- Todos los grupos --</option>
                            <option v-for="grupo in grupos" :key="grupo.id" :value="grupo.id">
                                {{ grupo.nombre || grupo.clave }} ({{ contarSolicitudesPorGrupo(grupo.id) }} solicitudes)
                            </option>
                        </select>
                    </div>
                </div>

                <button 
                    v-if="grupoSeleccionado" 
                    type="button" 
                    class="btn-reset-grupo"
                    @click="grupoSeleccionado = null"
                >
                    ✕ Ver todos los grupos
                </button>
            </section>

            <!-- VISTA 1: GRILLA DE GRUPOS (Si no hay un grupo seleccionado) -->
            <section v-if="!grupoSeleccionado" class="grupos-section">
                <div class="section-title">
                    <h3>
                        {{ tabActual === 'resumen' ? 'Grupos con Solicitudes Pendientes' : 'Grupos con Solicitudes Revisadas' }}
                    </h3>
                    <p>
                        {{ tabActual === 'resumen' 
                            ? 'Selecciona un grupo para evaluar sus solicitudes' 
                            : 'Selecciona un grupo para consultar su historial de dictámenes' 
                        }}
                    </p>
                </div>

                <div v-if="cargando" class="loading-state">Cargando grupos...</div>

                <div v-else-if="grupos.length" class="grupos-grid">
                    <div v-for="grupo in grupos" :key="grupo.id" class="grupo-card">
                        <div class="card-top">
                            <span class="status-badge">{{ grupo.estado || grupo.estatus || 'ACTIVO' }}</span>
                            <span class="turno-badge">{{ grupo.turno || 'VESPERTINO' }}</span>
                        </div>

                        <div class="card-body">
                            <h4 class="grupo-name">{{ grupo.nombre || grupo.clave }}</h4>
                            <p class="grupo-sub">{{ grupo.carrera?.nombre || 'Carrera asignada' }}</p>

                            <div class="info-box">
                                <p>Cuatrimestre: <strong>{{ grupo.cuatrimestre || 1 }}°</strong></p>
                                <p>Tutor: <strong>{{ grupo.tutor?.name || grupo.profesor || 'Sin asignar' }}</strong></p>
                                <p>
                                    {{ tabActual === 'resumen' ? 'Pendientes:' : 'Revisadas:' }} 
                                    <strong class="text-solicitudes">{{ contarSolicitudesPorGrupo(grupo.id) }}</strong>
                                </p>
                            </div>
                        </div>

                        <div class="card-actions">
                            <button 
                                type="button" 
                                :class="['btn-card-primary', { 'btn-card-revisadas': tabActual === 'revisadas' }]" 
                                @click="grupoSeleccionado = grupo"
                            >
                                {{ tabActual === 'resumen' ? '📋 Ver solicitudes del grupo' : '👁️ Ver solicitudes revisadas' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state">No hay grupos asignados.</div>
            </section>

            <!-- VISTA 2: TABLA DE SOLICITUDES DEL GRUPO SELECCIONADO -->
            <div v-else id="solicitudes" class="solicitudes-container">
                <JefeTabla 
                    :solicitudes="solicitudesFiltradas"
                    :grupo-seleccionado="grupoSeleccionado" 
                    :modo-revisadas="tabActual === 'revisadas'"
                    :cargando="cargando"
                    :error="error"
                    @recargar="cargarDatosIniciales"
                    @revisar="(sol) => solicitudSeleccionada = sol"
                    @volver="grupoSeleccionado = null"
                />
            </div>

        </main>

        <!-- MODAL DICTAMEN -->
        <JefeModalDictamen 
            v-if="solicitudSeleccionada"
            :solicitud="solicitudSeleccionada"
            :guardando="guardandoDictamen"
            :solo-lectura="tabActual === 'revisadas'"
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
* { box-sizing: border-box; }

.jefe-dashboard {
    min-height: 100vh;
    background: linear-gradient(180deg, #f5f7f6 0%, #fbfcfb 50%, #f4f6f5 100%);
    color: #242a27;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif;
}

.main-content {
    width: min(1220px, calc(100% - 40px));
    margin: auto;
    padding: 24px 0 80px;
}

/* BARRA DE SELECTOR DE GRUPO */
.group-selector-bar {
    margin: 20px 0 24px;
    padding: 14px 20px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

.selector-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.selector-info label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
}

.select-wrapper {
    position: relative;
    max-width: 320px;
    width: 100%;
}

.select-wrapper select {
    width: 100%;
    padding: 9px 14px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.select-wrapper select:focus {
    border-color: #247548;
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(36, 117, 72, 0.12);
}

.btn-reset-grupo {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-reset-grupo:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* ALERTA Y TARJETAS */
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

.grupos-section { margin: 28px 0; }
.section-title h3 { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
.section-title p { margin: 4px 0 16px; font-size: 12px; color: #64748b; }

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

.card-top { display: flex; justify-content: space-between; align-items: center; }
.status-badge { background: #e6f4ea; color: #137333; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.turno-badge { color: #94a3b8; font-size: 11px; font-weight: 800; letter-spacing: 0.05em; }

.grupo-name { margin: 12px 0 2px; font-size: 22px; font-weight: 800; color: #0f172a; }
.grupo-sub { margin: 0 0 12px; font-size: 13px; color: #64748b; font-weight: 600; }

.info-box { background: #f8fafc; padding: 12px 14px; border-radius: 12px; font-size: 12px; color: #475569; }
.info-box p { margin: 4px 0; }
.info-box strong { color: #1e293b; }
.text-solicitudes { color: #247548; font-weight: 800; }

.card-actions { display: flex; margin-top: 16px; }

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

.btn-card-revisadas {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
}

.btn-card-revisadas:hover {
    background: #2563eb;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}

.loading-state, .empty-state {
    text-align: center;
    padding: 30px;
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 13px;
}

.solicitudes-container { margin-top: 10px; }

/* MODAL LOGOUT */
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

.logout-modal { width: min(405px, 100%); text-align: center; padding: 29px; }
.logout-modal-icon { width: 50px; height: 50px; margin: 0 auto 13px; display: grid; place-items: center; border-radius: 14px; color: #84253d; background: #faedf1; }
.logout-modal h2 { margin: 0 0 6px; font-size: 18px; }
.logout-modal p { margin: 0; color: #8a918d; font-size: 10px; }
.logout-actions { margin-top: 22px; display: flex; gap: 9px; }

.primary-button, .secondary-button {
    min-height: 40px;
    padding: 0 15px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 850;
    cursor: pointer;
}

.primary-button { border: 0; background: #247548; color: #fff; }
.secondary-button { flex: 1; border: 1px solid #e0e4e1; background: #fff; color: #666e69; }
.logout-confirm { flex: 1; background: #7a1c33; }

@media (max-width: 640px) {
    .group-selector-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .selector-info {
        flex-direction: column;
        align-items: stretch;
    }
    .select-wrapper {
        max-width: 100%;
    }
}
</style>