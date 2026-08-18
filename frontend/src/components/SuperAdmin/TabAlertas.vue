<script setup>
const props = defineProps({
  alertas: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'navegar'])
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">SUPERVISIÓN</span>
        <h1>Alertas del sistema</h1>
        <p>Situaciones que requieren atención administrativa.</p>
      </div>
      <button class="secondary" @click="emit('actualizar')">Actualizar</button>
    </div>

    <div v-if="props.alertas.length" class="alerts">
      <article v-for="(a, i) in props.alertas" :key="i" :class="a.tipo">
        <div>
          <strong>{{ a.titulo }}</strong>
          <p>{{ a.detalle }}</p>
        </div>
        <button @click="emit('navegar', a.destino)">Revisar →</button>
      </article>
    </div>

    <div v-else class="all-good">
      <span>✓</span>
      <h2>Todo está en orden</h2>
      <p>No existen alertas administrativas.</p>
    </div>
  </div>
</template>