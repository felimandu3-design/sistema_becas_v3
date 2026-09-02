<script setup>
defineProps({
  busqueda: String,
  filtroEstado: String
})

defineEmits(['update:busqueda', 'update:filtroEstado'])
</script>

<template>
  <div class="filtros-container">
    <input 
      type="text" 
      :value="busqueda"
      @input="$emit('update:busqueda', $event.target.value)"
      placeholder="Buscar por nombre, matrícula o folio..."
    />

    <select 
      :value="filtroEstado" 
      @change="$emit('update:filtroEstado', $event.target.value)"
    >
      <option value="TODOS">Todos los estados</option>
      <option value="PENDIENTE">Pendientes</option>
      <option value="EN_REVISION">En Revisión</option>
      <option value="ACEPTADA">Aceptadas</option>
      <option value="RECHAZADA">Rechazadas</option>
    </select>
  </div>
</template>