<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
    solicitud: {
        type: Object,
        required: true
    },
    guardando: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['cerrar', 'guardar-dictamen', 'marcar-revision'])

// Variables locales
const observaciones = ref('')
const porcentajeBeca = ref('')
const modalPdf = ref(null)

// Al montar el componente, cargamos los datos previos si ya existían
onMounted(() => {
    observaciones.value = props.solicitud?.observaciones || props.solicitud?.comentario_revision || ''
    porcentajeBeca.value = props.solicitud?.porcentaje_beca || ''
})

/*
|--------------------------------------------------------------------------
| FUNCIONES DE FORMATO Y LECTURA
|--------------------------------------------------------------------------
*/
function alumnoDe(sol) { 
    return sol?.usuario || sol?.user || sol?.alumno || {} 
}

function grupoDe(sol) {
    return sol?.grupo_relacion?.nombre || 
           sol?.grupoRelacion?.nombre || 
           sol?.grupo?.nombre || 
           sol?.grupo || 
           alumnoDe(sol).grupo || 
           'Sin grupo'
}

function folioDe(sol) { return sol?.folio || `BEC-${String(sol?.id).padStart(5, '0')}` }
function periodoDe(sol) { return sol?.convocatoria?.periodo?.nombre || sol?.convocatoria?.periodo || 'Sin periodo' }
function nombreConvocatoria(sol) { return sol?.convocatoria?.nombre || 'Convocatoria' }

function textoEstado(estado) {
    const val = String(estado || 'PENDIENTE').trim().toUpperCase()
    const map = {
        PENDIENTE: 'Pendiente',
        EN_REVISION: 'En revisión',
        ACEPTADA: 'Aceptada',
        RECHAZADA: 'Rechazada',
        DOCUMENTACION_INCOMPLETA: 'Documentación incompleta',
    }
    return map[val] || val
}

function obtenerDocumentos(sol) {
    return Array.isArray(sol?.documentos) ? sol.documentos : []
}

function obtenerUrlDocumento(doc) {
    const ruta = doc?.archivo_url || doc?.url || doc?.ruta_archivo || doc?.archivo
    if (!ruta) return null
    if (String(ruta).startsWith('http')) return ruta
    if (String(ruta).startsWith('/')) return `http://127.0.0.1:8000${ruta}`
    return `http://127.0.0.1:8000/storage/${ruta}`
}

/*
|--------------------------------------------------------------------------
| CONTROL DEL VISOR PDF
|--------------------------------------------------------------------------
*/
function abrirPdf(doc) {
    modalPdf.value = obtenerUrlDocumento(doc)
}

function cerrarPdf() {
    modalPdf.value = null
}

/*
|--------------------------------------------------------------------------
| ENVÍO DE DICTAMEN AL PADRE
|--------------------------------------------------------------------------
*/
function emitirDictamen(nuevoEstado) {
    if (nuevoEstado === 'RECHAZADA' && !observaciones.value.trim()) {
        alert('Escribe una observación para indicar por qué se rechaza la solicitud.')
        return
    }

    if (nuevoEstado === 'ACEPTADA' && !porcentajeBeca.value) {
        alert('Debes seleccionar el porcentaje de beca a otorgar antes de aceptar la solicitud.')
        return
    }

    emit('guardar-dictamen', { 
        estado: nuevoEstado, 
        observaciones: observaciones.value,
        porcentaje_beca: nuevoEstado === 'ACEPTADA' ? porcentajeBeca.value : null
    })
}
</script>

<template>
    <!-- MODAL PRINCIPAL DE DICTAMEN -->
    <div class="modal-overlay" @click.self="emit('cerrar')">
        <div class="modal review-modal">
            
            <button type="button" class="modal-close" @click="emit('cerrar')">×</button>

            <!-- ENCABEZADO -->
            <div class="modal-heading">
                <span class="eyebrow">REVISIÓN DE SOLICITUD</span>
                <h2>{{ alumnoDe(solicitud).name || 'Alumno' }}</h2>
                <p>{{ folioDe(solicitud) }}</p>
            </div>

            <!-- DATOS DEL SOLICITANTE -->
            <div class="applicant-grid">
                <div>
                    <span>Matrícula</span>
                    <strong>{{ alumnoDe(solicitud).matricula || 'Sin matrícula' }}</strong>
                </div>
                <div>
                    <span>Grupo</span>
                    <strong>{{ grupoDe(solicitud) }}</strong>
                </div>
                <div>
                    <span>Periodo</span>
                    <strong>{{ periodoDe(solicitud) }}</strong>
                </div>
                <div>
                    <span>Estado actual</span>
                    <strong>{{ textoEstado(solicitud.estado || solicitud.estatus) }}</strong>
                </div>
            </div>

            <!-- CONVOCATORIA -->
            <div class="review-section">
                <span class="section-title">CONVOCATORIA</span>
                <strong>{{ nombreConvocatoria(solicitud) }}</strong>
            </div>

            <!-- DOCUMENTOS -->
            <div class="review-section">
                <div class="section-header">
                    <span class="section-title">DOCUMENTOS</span>
                    <span class="document-total">{{ obtenerDocumentos(solicitud).length }}</span>
                </div>

                <div v-if="obtenerDocumentos(solicitud).length === 0" class="no-documents">
                    No hay documentos registrados.
                </div>

                <div v-else class="documents-list">
                    <div v-for="doc in obtenerDocumentos(solicitud)" :key="doc.id || doc.ruta_archivo" class="document-row">
                        <div class="document-icon">PDF</div>
                        <div class="document-copy">
                            <strong>{{ doc.nombre_original || doc.tipo_documento || 'Documento' }}</strong>
                            <span>{{ doc.tipo_documento || 'Archivo adjunto' }}</span>
                        </div>
                        
                        <!-- BOTÓN QUE ABRE EL VISOR EN LUGAR DE UNA PESTAÑA NUEVA -->
                        <button 
                            v-if="obtenerUrlDocumento(doc)" 
                            type="button" 
                            class="document-open" 
                            style="border: none; cursor: pointer;"
                            @click="abrirPdf(doc)"
                        >
                            Ver
                        </button>
                    </div>
                </div>
            </div>

            <!-- PORCENTAJE DE BECA (Opciones: 25%, 50%, 75%) -->
            <div class="review-section">
                <label class="section-title">PORCENTAJE DE BECA (Requerido para aceptar)</label>
                <select v-model="porcentajeBeca" class="percentage-select">
                    <option value="" disabled>Selecciona el porcentaje a otorgar...</option>
                    <option value="25">25% de beca</option>
                    <option value="50">50% de beca</option>
                    <option value="75">75% de beca</option>
                </select>
            </div>

            <!-- OBSERVACIONES -->
            <div class="review-section">
                <label class="section-title">OBSERVACIONES</label>
                <textarea 
                    v-model="observaciones" 
                    rows="4" 
                    placeholder="Escribe una observación para el alumno (obligatoria si se rechaza)..."
                ></textarea>
            </div>

            <!-- ACCIONES -->
            <div class="decision-actions">
                <button 
                    type="button" 
                    class="decision-button review" 
                    :disabled="guardando"
                    @click="emit('marcar-revision')"
                >
                    En revisión
                </button>

                <button 
                    type="button" 
                    class="decision-button incomplete" 
                    :disabled="guardando"
                    @click="emitirDictamen('DOCUMENTACION_INCOMPLETA')"
                >
                    Doc. incompleta
                </button>

                <button 
                    type="button" 
                    class="decision-button reject" 
                    :disabled="guardando"
                    @click="emitirDictamen('RECHAZADA')"
                >
                    Rechazar
                </button>

                <button 
                    type="button" 
                    class="decision-button approve" 
                    :disabled="guardando"
                    @click="emitirDictamen('ACEPTADA')"
                >
                    {{ guardando ? 'Guardando...' : 'Aceptar' }}
                </button>
            </div>
        </div>
    </div>

    <!-- VISOR DE PDF FLOTANTE (MODAL SECUNDARIO) -->
    <div v-if="modalPdf" class="modal-overlay" style="z-index: 99999;" @click.self="cerrarPdf">
        <div class="modal" style="width: 80%; height: 90vh; max-width: 1000px; padding: 0; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
            <div style="padding: 15px 20px; background: #f4f6f5; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; color: #147a4a; font-size: 16px;">Visor de Documento</h3>
                <button type="button" @click="cerrarPdf" style="background: none; border: none; font-size: 28px; line-height: 1; cursor: pointer; color: #64748b; padding: 0;">&times;</button>
            </div>
            <div style="flex: 1; width: 100%; background: #334155;">
                <iframe :src="modalPdf" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ================================================================
   MODALS Y ESTRUCTURA
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
    width: min(650px, 100%);
    max-height: 90vh;
    overflow-y: auto;
    padding: 29px;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 30px 85px rgba(18, 27, 22, .23);
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 16px;
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: #f4f6f5;
    color: #818884;
    cursor: pointer;
    font-size: 19px;
}

.eyebrow {
    display: block;
    color: #8a918d;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .16em;
}

.modal-heading h2 {
    margin: 7px 0 3px;
    color: #29302c;
    font-size: 21px;
}

.modal-heading p {
    margin: 0;
    color: #9ba19e;
    font-size: 9px;
}

/* APPLICANT DATA */
.applicant-grid {
    margin-top: 21px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 9px;
}

.applicant-grid > div {
    padding: 12px;
    border-radius: 11px;
    background: #f6f8f7;
}

.applicant-grid span {
    display: block;
    color: #9da39f;
    font-size: 7px;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .07em;
}

.applicant-grid strong {
    display: block;
    margin-top: 4px;
    color: #454d48;
    font-size: 9px;
}

/* REVIEW SECTIONS */
.review-section {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #ecefed;
}

.section-title {
    display: block;
    margin-bottom: 10px;
    color: #8b928e;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .12em;
}

.review-section > strong {
    color: #444c47;
    font-size: 11px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.document-total {
    min-width: 23px;
    height: 23px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: #edf5f0;
    color: #267348;
    font-size: 8px;
    font-weight: 850;
}

.no-documents {
    padding: 16px;
    border-radius: 11px;
    background: #f7f8f8;
    color: #999f9c;
    font-size: 9px;
    text-align: center;
}

.documents-list {
    display: grid;
    gap: 7px;
}

.document-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 11px;
    border: 1px solid #e8ebe9;
    border-radius: 11px;
}

.document-icon {
    width: 35px;
    height: 35px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #faedf1;
    color: #81263d;
    font-size: 8px;
    font-weight: 850;
}

.document-copy {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.document-copy strong { color: #414944; font-size: 9px; }
.document-copy span { margin-top: 3px; color: #9ba19e; font-size: 8px; }

.document-open {
    padding: 7px 10px;
    border-radius: 8px;
    background: #edf5f0;
    color: #267348;
    font-size: 8px;
    font-weight: 850;
}

/* SELECTOR DE PORCENTAJE */
.percentage-select {
    width: 100%;
    height: 42px;
    border: 1px solid #e0e4e1;
    border-radius: 12px;
    padding: 0 12px;
    outline: 0;
    color: #454c48;
    font-family: inherit;
    font-size: 10px;
    font-weight: 600;
    background: #fff;
}
.percentage-select:focus {
    border-color: #6ca583;
    box-shadow: 0 0 0 3px #edf6f1;
}

.review-section textarea {
    width: 100%;
    resize: vertical;
    min-height: 90px;
    border: 1px solid #e0e4e1;
    border-radius: 12px;
    padding: 12px;
    outline: 0;
    color: #454c48;
    font-family: inherit;
    font-size: 10px;
    line-height: 1.5;
}

.review-section textarea:focus {
    border-color: #6ca583;
    box-shadow: 0 0 0 3px #edf6f1;
}

/* DECISION BUTTONS */
.decision-actions {
    margin-top: 22px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.decision-button {
    min-height: 41px;
    border: 0;
    border-radius: 10px;
    font-size: 8px;
    font-weight: 850;
    cursor: pointer;
}

.decision-button.review { background: #eaf3fa; color: #32688f; }
.decision-button.incomplete { background: #f1eaf8; color: #724896; }
.decision-button.reject { background: #faeaf0; color: #86253d; }
.decision-button.approve { background: #247548; color: #fff; }
.decision-button:disabled { opacity: .5; cursor: not-allowed; }

/* RESPONSIVE */
@media (max-width: 800px) {
    .applicant-grid { grid-template-columns: repeat(2, 1fr); }
    .decision-actions { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 520px) {
    .modal { padding: 25px 18px; }
    .applicant-grid { grid-template-columns: 1fr 1fr; }
    .decision-actions { grid-template-columns: 1fr; }
}
</style>