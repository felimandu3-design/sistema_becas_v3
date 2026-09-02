<script setup>
defineProps({
  solicitudes: Array,
  cargando: Boolean
})

defineEmits(['seleccionar'])
</script>

<template>
  <div class="tabla-container">
    <table class="tabla">
      <thead>
        <tr>
          <th>FOLIO</th>
          <th>ALUMNO</th>
          <th>GRUPO</th>
          <th>ESTADO</th>
          <th>ACCIONES</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="solicitud in solicitudes" :key="solicitud.id">
          <!-- 1. FOLIO -->
          <td>
            {{ solicitud.folio || solicitud.id_solicitud || `#${solicitud.id}` }}
          </td>

          <!-- 2. ALUMNO (Nombre y Matrícula) -->
          <td>
            <div class="info-alumno">
              <strong>
                {{ 
                  solicitud.usuario?.name || 
                  solicitud.usuario?.nombre || 
                  solicitud.alumno?.nombre || 
                  solicitud.alumno?.name || 
                  'Alumno' 
                }}
              </strong>
              <small class="block text-gray-500">
                {{ solicitud.usuario?.matricula || solicitud.alumno?.matricula || solicitud.matricula || '' }}
              </small>
            </div>
          </td>

          <!-- 3. GRUPO -->
          <td>
            {{ 
              solicitud.grupo_relacion?.nombre || 
              solicitud.grupo?.nombre || 
              solicitud.usuario?.grupo?.nombre || 
              solicitud.usuario?.grupo || 
              '7VSC1' 
            }}
          </td>

          <!-- 4. ESTADO -->
          <td>
            <span :class="['badge-estado', (solicitud.estado || solicitud.status || 'pendiente').toLowerCase()]">
              {{ (solicitud.estado || solicitud.status || 'PENDIENTE').toUpperCase() }}
            </span>
          </td>

          <!-- 5. ACCIONES -->
          <button 
            type="button" 
                class="px-4 py-2 bg-[#eaf5ee] text-[#1b6339] text-xs font-bold rounded-xl hover:bg-[#d8ebd9] hover:text-[#124728] transition-colors duration-200"
                @click="$emit('seleccionar', solicitud)"
            >
            Ver detalle
            </button>
        </tr>
      </tbody>
    </table>
  </div>
</template>