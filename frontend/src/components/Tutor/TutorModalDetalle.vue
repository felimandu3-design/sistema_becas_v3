<script setup>
const props = defineProps({
    solicitud: { type: Object, required: true },
    actualizando: { type: Boolean, default: false },
    helpers: { type: Object, required: true }
})

const emit = defineEmits(['cerrar', 'cambiar-estado'])
</script>

<template>
    <div class="modal-overlay" @click.self="emit('cerrar')">
        <div class="modal">
            <button type="button" class="modal-close" @click="emit('cerrar')">×</button>

            <div class="modal-title">
                <span class="eyebrow">SEGUIMIENTO ACADÉMICO</span>
                <h2>{{ helpers.obtenerAlumno(solicitud).name || 'Alumno' }}</h2>
                <p>{{ helpers.folioSolicitud(solicitud) }}</p>
            </div>

            <div class="detail-grid">
                <div>
                    <span>Matrícula</span>
                    <strong>{{ helpers.obtenerAlumno(solicitud).matricula || 'Sin matrícula' }}</strong>
                </div>
                <div>
                    <span>Grupo</span>
                    <strong>{{ solicitud.grupo || helpers.obtenerAlumno(solicitud).grupo || 'Sin grupo' }}</strong>
                </div>
                <div>
                    <span>Periodo</span>
                    <strong>{{ helpers.periodoSolicitud(solicitud) }}</strong>
                </div>
                <div>
                    <span>Registro</span>
                    <strong>{{ helpers.formatearFecha(solicitud.created_at) }}</strong>
                </div>
            </div>

            <section class="modal-section">
                <div class="section-header">
                    <span>ESTADO DE SOLICITUD</span>
                    <span class="status" :class="helpers.claseEstado(solicitud.estado || solicitud.estatus)">
                        {{ helpers.textoEstado(solicitud.estado || solicitud.estatus) }}
                    </span>
                </div>

                <p class="section-description">
                    El tutor puede marcar la solicitud como "En revisión" para indicar que está realizando seguimiento académico.
                </p>

                <button
                    v-if="helpers.normalizarEstado(solicitud.estado || solicitud.estatus) === 'PENDIENTE'"
                    type="button"
                    class="review-status-button"
                    :disabled="actualizando"
                    @click="emit('cambiar-estado', solicitud, 'EN_REVISION')"
                >
                    {{ actualizando ? 'Actualizando...' : 'Marcar como En revisión' }}
                </button>
            </section>

            <section class="modal-section">
                <div class="section-header">
                    <span>DOCUMENTOS ADJUNTOS</span>
                    <small>{{ helpers.obtenerDocumentos(solicitud).length }}</small>
                </div>

                <div v-if="helpers.obtenerDocumentos(solicitud).length === 0" class="no-docs">
                    Este alumno todavía no tiene documentos registrados.
                </div>

                <div v-else class="documents">
                    <div
                        v-for="documento in helpers.obtenerDocumentos(solicitud)"
                        :key="documento.id || documento.ruta_archivo"
                        class="document"
                    >
                        <div class="pdf">PDF</div>
                        <div class="document-info">
                            <strong>{{ documento.nombre_original || documento.tipo_documento || 'Documento' }}</strong>
                            <span>{{ documento.tipo_documento || 'Archivo del alumno' }}</span>
                        </div>
                        <a
                            v-if="helpers.urlDocumento(documento)"
                            :href="helpers.urlDocumento(documento)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="open-document"
                        >
                            Abrir
                        </a>
                    </div>
                </div>
            </section>

            <section v-if="solicitud.observaciones || solicitud.comentario_revision" class="modal-section">
                <span class="section-label">OBSERVACIONES</span>
                <div class="observation">
                    {{ solicitud.observaciones || solicitud.comentario_revision }}
                </div>
            </section>

            <button type="button" class="primary full" @click="emit('cerrar')">
                Cerrar
            </button>
        </div>
    </div>
</template>