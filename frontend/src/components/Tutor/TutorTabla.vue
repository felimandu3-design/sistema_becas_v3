<script setup>
const props = defineProps({
    solicitudes: { type: Array, required: true },
    cargando: { type: Boolean, default: false },
    error: { type: String, default: '' },
    terminoBusqueda: { type: String, default: '' },
    filtroEstado: { type: String, default: 'TODOS' },
    helpers: { type: Object, required: true }
})

const emit = defineEmits([
    'update:terminoBusqueda',
    'update:filtroEstado',
    'abrir-solicitud',
    'cargar-solicitudes'
])
</script>

<template>
    <section id="lista-solicitudes" class="requests">
        <div class="requests-header">
            <div>
                <span class="eyebrow">SEGUIMIENTO</span>
                <h2>Solicitudes de alumnos</h2>
                <p>{{ solicitudes.length }} resultado(s)</p>
            </div>
            <button type="button" class="refresh" @click="emit('cargar-solicitudes')">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 11a8 8 0 1 0-2 5.3"/>
                    <path d="M20 4v7h-7"/>
                </svg>
                Actualizar
            </button>
        </div>

        <div class="filters">
            <div class="search">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20l-4-4"/>
                </svg>
                <input
                    :value="terminoBusqueda"
                    @input="emit('update:terminoBusqueda', $event.target.value)"
                    type="text"
                    placeholder="Buscar alumno, matrícula o folio"
                />
            </div>

            <select
                :value="filtroEstado"
                @change="emit('update:filtroEstado', $event.target.value)"
            >
                <option value="TODOS">Todos los estados</option>
                <option value="PENDIENTE">Pendiente</option>
                <option value="EN_REVISION">En revisión</option>
                <option value="ACEPTADA">Aceptada</option>
                <option value="RECHAZADA">Rechazada</option>
                <option value="DOCUMENTACION_INCOMPLETA">Documentación incompleta</option>
            </select>
        </div>

        <div v-if="cargando" class="state">
            <div class="spinner"></div>
            <strong>Cargando alumnos</strong>
            <span>Consultando información...</span>
        </div>

        <div v-else-if="error" class="state">
            <div class="state-icon error">!</div>
            <strong>No pudimos cargar la información</strong>
            <span>{{ error }}</span>
            <button type="button" class="primary" @click="emit('cargar-solicitudes')">
                Intentar nuevamente
            </button>
        </div>

        <div v-else-if="solicitudes.length === 0" class="state">
            <div class="state-icon">
                <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
            <strong>No hay solicitudes</strong>
            <span>No encontramos alumnos con estos filtros.</span>
        </div>

        <div v-else class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Matrícula</th>
                        <th>Grupo</th>
                        <th>Periodo</th>
                        <th>Documentos</th>
                        <th>Estado</th>
                        <th class="right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="solicitud in solicitudes" :key="solicitud.id">
                        <td>
                            <div class="student">
                                <div class="student-avatar">
                                    {{ helpers.obtenerInicialAlumno(solicitud) }}
                                </div>
                                <div>
                                    <strong>{{ helpers.obtenerAlumno(solicitud).name || 'Alumno' }}</strong>
                                    <span>{{ helpers.folioSolicitud(solicitud) }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ helpers.obtenerAlumno(solicitud).matricula || '—' }}</td>
                        <td>{{ solicitud.grupo || helpers.obtenerAlumno(solicitud).grupo || '—' }}</td>
                        <td>{{ helpers.periodoSolicitud(solicitud) }}</td>
                        <td>
                            <div class="document-count">
                                <strong>{{ helpers.obtenerDocumentos(solicitud).length }}</strong>
                                <span>archivo(s)</span>
                            </div>
                        </td>
                        <td>
                            <span class="status" :class="helpers.claseEstado(solicitud.estado || solicitud.estatus)">
                                {{ helpers.textoEstado(solicitud.estado || solicitud.estatus) }}
                            </span>
                        </td>
                        <td class="right">
                            <button type="button" class="view-button" @click="emit('abrir-solicitud', solicitud)">
                                Ver seguimiento
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>