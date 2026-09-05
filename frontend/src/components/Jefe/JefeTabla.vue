<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    solicitudes: { type: Array, default: () => [] },
    grupoSeleccionado: { type: Object, default: null },
    cargando: { type: Boolean, default: false },
    error: { type: String, default: null }
});

const emit = defineEmits(['recargar', 'revisar', 'volver']);

// Variables locales para filtros
const filtroEstatus = ref('TODOS')
const terminoBusqueda = ref('')

/*
|--------------------------------------------------------------------------
| FUNCIONES DE UTILIDAD (Formato y Clases)
|--------------------------------------------------------------------------
*/
function normalizarEstado(estado) {
    return String(estado || 'PENDIENTE').trim().toUpperCase()
}

function textoEstado(estado) {
    const valor = normalizarEstado(estado)
    const estados = {
        PENDIENTE: 'Pendiente',
        EN_REVISION: 'En revisión',
        ACEPTADA: 'Aceptada',
        RECHAZADA: 'Rechazada',
        DOCUMENTACION_INCOMPLETA: 'Documentación incompleta',
    }
    return estados[valor] || valor
}

function claseEstado(estado) {
    const valor = normalizarEstado(estado)
    const clases = {
        PENDIENTE: 'warning',
        EN_REVISION: 'info',
        ACEPTADA: 'success',
        RECHAZADA: 'danger',
        DOCUMENTACION_INCOMPLETA: 'purple',
    }
    return clases[valor] || 'neutral'
}

function alumnoDe(solicitud) {
    return solicitud?.usuario || solicitud?.user || solicitud?.alumno || {}
}

function grupoDe(solicitud) {
    const g = solicitud?.grupo_relacion || 
              solicitud?.grupoRelacion || 
              solicitud?.grupo || 
              alumnoDe(solicitud)?.grupo

    if (typeof g === 'object' && g !== null) {
        return g.clave || g.nombre || '—'
    }
    
    return g || '—'
}

function periodoDe(solicitud) {
    return solicitud?.convocatoria?.periodo?.nombre || solicitud?.convocatoria?.periodo || 'Sin periodo'
}

function folioDe(solicitud) {
    if (!solicitud) return 'Sin folio'
    return solicitud.folio || `BEC-${String(solicitud.id).padStart(5, '0')}`
}

function obtenerDocumentos(solicitud) {
    const documentos = solicitud?.documentos || []
    return Array.isArray(documentos) ? documentos : []
}

/*
|--------------------------------------------------------------------------
| LÓGICA DE FILTRADO
|--------------------------------------------------------------------------
*/
const solicitudesFiltradas = computed(() => {
    const termino = terminoBusqueda.value.trim().toLowerCase()

    return props.solicitudes.filter(solicitud => {
        // 1. FILTRAR POR GRUPO SELECCIONADO (Si hay uno activo)
        if (props.grupoSeleccionado) {
            const targetId = props.grupoSeleccionado.id
            const targetClave = String(props.grupoSeleccionado.clave || props.grupoSeleccionado.nombre || '').trim().toLowerCase()

            const alumno = alumnoDe(solicitud)
            
            // Comprobación por ID
            const matchId = 
                solicitud.grupo_id == targetId || 
                solicitud.grupo?.id == targetId ||
                alumno.grupo_id == targetId ||
                alumno.grupo?.id == targetId

            // Comprobación por Clave/Nombre (ej: "7VSC1")
            const grupoNombreStr = String(grupoDe(solicitud)).trim().toLowerCase()
            const matchClave = targetClave !== '' && grupoNombreStr === targetClave

            if (!matchId && !matchClave) {
                return false
            }
        }

        // 2. FILTRAR POR ESTATUS
        const estado = normalizarEstado(solicitud.estado || solicitud.estatus)
        const coincideEstado = filtroEstatus.value === 'TODOS' || estado === filtroEstatus.value

        // 3. BÚSQUEDA POR TEXTO (Nombre, Matrícula, Folio, etc.)
        const alumno = alumnoDe(solicitud)
        const textoBusqueda = [
            alumno.name,
            alumno.matricula,
            grupoDe(solicitud),
            folioDe(solicitud),
            solicitud.convocatoria?.nombre,
            solicitud.convocatoria?.periodo?.nombre,
        ].filter(Boolean).join(' ').toLowerCase()

        const coincideBusqueda = !termino || textoBusqueda.includes(termino)

        return coincideEstado && coincideBusqueda
    })
})
</script>

<template>
    <section id="solicitudes" class="requests-card">
        <!-- ENCABEZADO, INDICADOR DE GRUPO Y BOTONES DE ACCIÓN -->
        <div class="requests-heading">
            <div>
                <!-- Muestra la etiqueta y clave del grupo si existe -->
                <template v-if="grupoSeleccionado">
                    <span class="eyebrow-grupo">FILTRANDO POR GRUPO</span>
                    <h2 class="grupo-nombre">{{ grupoSeleccionado.nombre || grupoSeleccionado.clave }}</h2>
                </template>
                <template v-else>
                    <span class="eyebrow">SOLICITUDES</span>
                    <h2>Alumnos de mi carrera</h2>
                </template>

                <p>{{ solicitudesFiltradas.length }} resultado(s)</p>
            </div>
            
            <div class="header-buttons">
                <button 
                    type="button" 
                    class="refresh-button" 
                    @click="$emit('recargar')"
                >
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 11a8 8 0 1 0-2 5.3" />
                        <path d="M20 4v7h-7" />
                    </svg>
                    Actualizar
                </button>

                <!-- BOTÓN REGRESAR A GRUPOS -->
                <button 
                    v-if="grupoSeleccionado"
                    type="button" 
                    class="back-button" 
                    @click="$emit('volver')"
                >
                    ← Ver todos los grupos
                </button>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="filters">
            <div class="search-field">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M20 20l-4-4" />
                </svg>
                <input 
                    v-model="terminoBusqueda" 
                    type="text" 
                    placeholder="Buscar por alumno, matrícula, folio..." 
                />
            </div>
            <select v-model="filtroEstatus" class="filter-select">
                <option value="TODOS">Todos los estados</option>
                <option value="PENDIENTE">Pendiente</option>
                <option value="EN_REVISION">En revisión</option>
                <option value="ACEPTADA">Aceptada</option>
                <option value="RECHAZADA">Rechazada</option>
                <option value="DOCUMENTACION_INCOMPLETA">Documentación incompleta</option>
            </select>
        </div>

        <!-- ESTADO: CARGANDO -->
        <div v-if="cargando" class="loading-box">
            <div class="spinner"></div>
            <strong>Consultando solicitudes</strong>
            <span>Espera un momento...</span>
        </div>

        <!-- ESTADO: ERROR -->
        <div v-else-if="error" class="empty-box">
            <div class="empty-icon error">!</div>
            <strong>No se pudieron cargar las solicitudes</strong>
            <span>{{ error }}</span>
            <button type="button" class="primary-button" style="margin-top: 15px;" @click="$emit('recargar')">
                Intentar nuevamente
            </button>
        </div>

        <!-- ESTADO: VACÍO (SIN RESULTADOS) -->
        <div v-else-if="solicitudesFiltradas.length === 0" class="empty-box">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2h9l5 5v15H6z" />
                    <path d="M14 2v6h6" />
                </svg>
            </div>
            <strong>No encontramos solicitudes</strong>
            <span>Cambia los filtros o vuelve a intentarlo.</span>
        </div>

        <!-- TABLA DE DATOS -->
        <div v-else class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Matrícula</th>
                        <th>Grupo</th>
                        <th>Periodo</th>
                        <th>Documentos</th>
                        <th>Descuento</th>
                        <th>Estado</th>
                        <th class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="solicitud in solicitudesFiltradas" :key="solicitud.id">
                        <td>
                            <div class="student-cell">
                                <div class="student-avatar">
                                    {{ (alumnoDe(solicitud).name || alumnoDe(solicitud).nombre || 'A').charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <strong>{{ alumnoDe(solicitud).name || alumnoDe(solicitud).nombre || 'Alumno' }}</strong>
                                    <span>{{ folioDe(solicitud) }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ alumnoDe(solicitud).matricula || '—' }}</td>
                        <td>{{ grupoDe(solicitud) }}</td>
                        <td>{{ periodoDe(solicitud) }}</td>
                        <td>
                            <div class="docs-count">
                                {{ obtenerDocumentos(solicitud).length }}
                                <span>archivo(s)</span>
                            </div>
                        </td>

                        <!-- CELDA DE DESCUENTO ESTILO TUTOR -->
                        <td>
                            <div v-if="solicitud.porcentaje_beca || solicitud.porcentaje_descuento" class="discount-badge">
                                {{ parseFloat(solicitud.porcentaje_beca || solicitud.porcentaje_descuento) }}%
                            </div>
                            <span v-else class="no-discount">N/A</span>
                        </td>

                        <td>
                            <span class="status-badge" :class="claseEstado(solicitud.estado || solicitud.estatus)">
                                {{ textoEstado(solicitud.estado || solicitud.estatus) }}
                            </span>
                        </td>
                        <td class="text-right">
                            <button 
                                type="button" 
                                class="review-button" 
                                @click="$emit('revisar', solicitud)"
                            >
                                Revisar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<style scoped>
/* ================================================================
   CONTENEDOR GENERAL DE LA SECCIÓN
================================================================ */
.tabla-seccion-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ================================================================
   TARJETA FILTRO POR GRUPO (NUEVO BLOQUE SUPERIOR)
================================================================ */
.grupo-filtro-card {
    border: 1px solid #e3e7e4;
    border-radius: 21px;
    background: #fff;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 13px 38px rgba(28, 40, 33, .05);
}

.eyebrow-grupo {
    display: block;
    color: #8a918d;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .16em;
    text-transform: uppercase;
}

.grupo-nombre {
    margin: 4px 0 0;
    font-size: 20px;
    font-weight: 850;
    color: #2e3531;
}

.back-button {
    height: 37px;
    padding: 0 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e1e5e2;
    border-radius: 10px;
    background: #f6f8f7;
    color: #4a524e;
    font-size: 9px;
    font-weight: 850;
    cursor: pointer;
    transition: all 0.2s ease;
}

.back-button:hover {
    background: #edf2ee;
    color: #247548;
    border-color: #d2ded5;
}

/* ================================================================
   REQUESTS CARD
================================================================ */
.requests-card {
    border: 1px solid #e3e7e4;
    border-radius: 21px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 13px 38px rgba(28, 40, 33, .05);
}

.requests-heading {
    padding: 24px 25px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #edf0ee;
}

.eyebrow {
    display: block;
    color: #8a918d;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .16em;
}

.requests-heading h2 {
    margin: 6px 0 3px;
    font-size: 19px;
}

.requests-heading p {
    margin: 0;
    color: #989e9b;
    font-size: 9px;
}

.refresh-button {
    height: 37px;
    padding: 0 13px;
    display: flex;
    align-items: center;
    gap: 7px;
    border: 1px solid #e1e5e2;
    border-radius: 10px;
    background: #fff;
    color: #626a66;
    font-size: 9px;
    font-weight: 800;
    cursor: pointer;
}

.refresh-button:hover {
    background: #f6f8f7;
}

/* FILTERS */
.filters {
    padding: 15px 24px;
    display: grid;
    grid-template-columns: 1fr 235px;
    gap: 11px;
    background: #fbfcfb;
    border-bottom: 1px solid #edf0ee;
}

.search-field {
    position: relative;
    display: flex;
    align-items: center;
}

.search-field svg {
    position: absolute;
    left: 13px;
    color: #9da39f;
}

.search-field input {
    width: 100%;
    height: 40px;
    border: 1px solid #e1e5e2;
    border-radius: 11px;
    padding: 0 13px 0 40px;
    background: #fff;
    color: #404743;
    outline: 0;
    font-size: 10px;
}

.search-field input:focus {
    border-color: #6ca583;
    box-shadow: 0 0 0 3px #edf6f1;
}

.filter-select {
    height: 40px;
    border: 1px solid #e1e5e2;
    border-radius: 11px;
    padding: 0 12px;
    background: #fff;
    color: #505753;
    outline: 0;
    font-size: 10px;
    font-weight: 700;
}

/* TABLE */
.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

thead th {
    padding: 12px 17px;
    background: #fafbfa;
    color: #969c98;
    border-bottom: 1px solid #ecefec;
    text-align: left;
    font-size: 8px;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .08em;
}

tbody td {
    padding: 14px 17px;
    border-bottom: 1px solid #f0f2f1;
    color: #59605c;
    font-size: 10px;
}

tbody tr:last-child td {
    border-bottom: 0;
}

tbody tr:hover {
    background: #fcfdfc;
}

.text-right {
    text-align: right;
}

.student-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-avatar {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #edf5f0;
    color: #247548;
    font-size: 11px;
    font-weight: 850;
}

.student-cell div:last-child {
    display: flex;
    flex-direction: column;
}

.student-cell strong {
    color: #333a36;
    font-size: 10px;
}

.student-cell span {
    margin-top: 3px;
    color: #a0a5a2;
    font-size: 8px;
}

.docs-count {
    display: flex;
    flex-direction: column;
    font-weight: 750;
    color: #434b47;
}

.docs-count span {
    margin-top: 2px;
    color: #9fa5a1;
    font-size: 8px;
    font-weight: 500;
}

/* DESCUENTO - ESTILO TUTOR EXACTO */
.discount-badge {
    display: inline-block;
    font-weight: 850;
    color: #216841;
}

.no-discount {
    color: #a0a5a2;
    font-size: 9px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 9px;
    border-radius: 99px;
    font-size: 8px;
    font-weight: 800;
    white-space: nowrap;
}

.status-badge.warning { color: #90611b; background: #fff4d7; }
.status-badge.info { color: #32688f; background: #e9f3fa; }
.status-badge.success { color: #216841; background: #e8f5ed; }
.status-badge.danger { color: #86253d; background: #faeaf0; }
.status-badge.purple { color: #6e4992; background: #f2ebf8; }
.status-badge.neutral { color: #707773; background: #f0f2f1; }

.review-button {
    height: 32px;
    padding: 0 12px;
    border: 1px solid #dbe5de;
    border-radius: 99px;
    background: #f4f8f5;
    color: #267348;
    font-size: 8px;
    font-weight: 850;
    cursor: pointer;
}

.review-button:hover {
    background: #eaf4ed;
}

/* EMPTY / LOADING */
.loading-box,
.empty-box {
    min-height: 320px;
    padding: 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.loading-box strong,
.empty-box strong {
    margin-top: 13px;
    color: #454d49;
    font-size: 12px;
}

.loading-box span,
.empty-box span {
    margin-top: 5px;
    color: #969d99;
    font-size: 9px;
}

.spinner {
    width: 35px;
    height: 35px;
    border: 3px solid #e8edea;
    border-top-color: #247548;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.empty-icon {
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    color: #76807a;
    background: #f0f3f1;
}

.empty-icon.error {
    color: #84253c;
    background: #faeaf0;
    font-size: 17px;
    font-weight: 850;
}

.primary-button {
    min-height: 40px;
    padding: 0 15px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 850;
    cursor: pointer;
    border: 0;
    background: #247548;
    color: #fff;
}

.header-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* RESPONSIVE */
@media (max-width: 800px) {
    .filters { grid-template-columns: 1fr; }
}

@media (max-width: 520px) {
    .requests-heading,
    .grupo-filtro-card { padding: 19px 17px 15px; }
    .filters { padding: 12px 16px; }
}
</style>