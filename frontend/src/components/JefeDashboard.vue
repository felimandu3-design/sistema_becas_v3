<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'

// Importamos a nuestros 4 hijos
import JefeHeader from './Jefe/JefeHeader.vue'
import JefeEstadisticas from './Jefe/JefeEstadisticas.vue'
import JefeTabla from './Jefe/JefeTabla.vue'
import JefeModalDictamen from './Jefe/JefeModalDictamen.vue'

/*
|--------------------------------------------------------------------------
| PROPS & EMITS (Conexión con App.vue)
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
const solicitudes = ref([])
const cargando = ref(true)
const error = ref('')

const solicitudSeleccionada = ref(null)
const guardandoDictamen = ref(false)

const modalLogout = ref(false)
const mensajeExito = ref('')

/*
|--------------------------------------------------------------------------
| DATOS COMPUTADOS (Para pasarlos a los hijos)
|--------------------------------------------------------------------------
*/
const carreraJefe = computed(() => {
    return props.usuario?.carrera?.nombre || props.usuario?.carrera_nombre || 'Carrera asignada'
})

const estadisticas = computed(() => {
    const estados = solicitudes.value.map(s => String(s.estado || s.estatus || 'PENDIENTE').trim().toUpperCase())
    return {
        total: solicitudes.value.length,
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
async function cargarSolicitudes() {
    cargando.value = true
    error.value = ''
    try {
        const { data } = await api.get('/admin/solicitudes')
        solicitudes.value = data?.data || data?.solicitudes || (Array.isArray(data) ? data : [])
    } catch (err) {
        console.error('Error cargando solicitudes:', err)
        error.value = err?.response?.data?.message || 'No fue posible cargar las solicitudes.'
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
            porcentaje_beca: payload.porcentaje_beca || null,
        })

        const actualizado = data?.data
        if (actualizado) {
            const indice = solicitudes.value.findIndex(s => s.id === actualizado.id)
            if (indice !== -1) solicitudes.value[indice] = actualizado
        } else {
            await cargarSolicitudes()
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
        await cargarSolicitudes()
        solicitudSeleccionada.value = null
    } catch (err) {
        alert(err?.response?.data?.message || 'No fue posible cambiar el estado.')
    }
}

/*
|--------------------------------------------------------------------------
| ACCIONES DE LA INTERFAZ
|--------------------------------------------------------------------------
*/
function scrollASolicitudes() {
    document.getElementById('solicitudes')?.scrollIntoView({ behavior: 'smooth' })
}

function confirmarCerrarSesion() { modalLogout.value = true }
function cerrarSesion() {
    modalLogout.value = false
    emit('cerrar-sesion')
}

// Arrancamos el motor al cargar
onMounted(() => {
    cargarSolicitudes()
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

            <!-- 3. TABLA Y FILTROS (Hijo 3) -->
            <JefeTabla 
                :solicitudes="solicitudes"
                :cargando="cargando"
                :error="error"
                @recargar="cargarSolicitudes"
                @revisar="(sol) => solicitudSeleccionada = sol"
            />

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

        <!-- MODAL LOGOUT (Se queda en el padre) -->
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
/* ================================================================
   ESTILOS BASE DEL PADRE
================================================================ */
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
    padding: 44px 0 80px;
}

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

/* LOGOUT MODAL */
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

.logout-modal h2 { margin: 0 0 6px; font-size: 18px; }
.logout-modal p { margin: 0; color: #8a918d; font-size: 10px; }

.logout-actions {
    margin-top: 22px;
    display: flex;
    gap: 9px;
}

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

@media (max-width: 520px) {
    .main-content { width: calc(100% - 24px); padding-top: 30px; }
}
</style>