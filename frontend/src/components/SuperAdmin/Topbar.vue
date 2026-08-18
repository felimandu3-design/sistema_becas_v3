<script setup>
const props = defineProps({
  seccion: {
    type: String,
    default: 'resumen'
  },
  alertas: {
    type: Array,
    default: () => []
  },
  usuario: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['cambiar-tab', 'cerrar-sesion'])

const tabs = [
  { id: 'resumen', label: 'Resumen' },
  { id: 'solicitudes', label: 'Solicitudes' },
  { id: 'convocatorias', label: 'Convocatorias' },
  { id: 'periodos', label: 'Periodos' },
  { id: 'carreras', label: 'Carreras' },
  { id: 'grupos', label: 'Grupos' },
  { id: 'alumnos', label: 'Alumnos' },
  { id: 'personal', label: 'Personal' },
  { id: 'alertas', label: 'Alertas' }
]

function iniciales(nombre) {
  return String(nombre || 'SA')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(v => v[0]?.toUpperCase())
    .join('')
}
</script>

<template>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <div class="logo-text">
          <span>UP</span>T<span>ex</span>
        </div>
        <div>
          <strong>Sistema de Becas</strong>
          <small>Super Administración · UPTex</small>
        </div>
      </div>

      <nav>
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="{ active: props.seccion === tab.id }"
          @click="emit('cambiar-tab', tab.id)"
        >
          {{ tab.label }}
          <b v-if="tab.id === 'alertas' && props.alertas.length" class="counter">
            {{ props.alertas.length }}
          </b>
        </button>
      </nav>

      <div class="profile">
        <div>
          <strong>{{ props.usuario?.name || 'Super Administrador' }}</strong>
          <small>SuperAdmin</small>
        </div>
        <span class="avatar">
          {{ iniciales(props.usuario?.name) }}
        </span>
        <button class="logout" @click="emit('cerrar-sesion')">
          Salir
        </button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar { position: sticky; top: 0; z-index: 40; background: rgba(255, 255, 255, 0.98); border-bottom: 1px solid #dfe6e1; box-shadow: 0 3px 12px rgba(20, 50, 35, 0.04); }
.topbar-inner { width: min(1420px, calc(100% - 32px)); min-height: 78px; margin: auto; display: flex; align-items: center; gap: 22px; }
.brand { min-width: 225px; display: flex; align-items: center; gap: 13px; }
.logo-text { font-size: 23px; font-weight: 900; color: #b32643; }
.logo-text span:first-child { color: #087846; }
.logo-text span:last-child { color: #707a74; }
.brand > div:last-child { display: flex; flex-direction: column; border-left: 1px solid #dfe5e1; padding-left: 12px; }
.brand strong { font-size: 14px; }
.brand small, .profile small { font-size: 11px; color: #8c9690; }
nav { flex: 1; display: flex; justify-content: center; gap: 3px; overflow-x: auto; }
nav button { position: relative; border: 0; background: transparent; border-radius: 9px; padding: 10px; color: #66716a; font-size: 13px; font-weight: 750; white-space: nowrap; cursor: pointer; }
nav button:hover, nav button.active { background: #e8f3ed; color: #087846; }
.counter { position: absolute; right: 1px; top: 0; min-width: 17px; height: 17px; display: grid; place-items: center; border-radius: 20px; background: #8e2843; color: #fff; font-size: 10px; }
.profile { display: flex; align-items: center; gap: 8px; }
.profile > div { display: flex; flex-direction: column; text-align: right; }
.profile strong { font-size: 12px; }
.avatar { width: 39px; height: 39px; display: grid; place-items: center; border-radius: 50%; background: #087846; color: #fff; font-size: 12px; font-weight: 900; }
.logout { border: 1px solid #dce3df; background: #fff; color: #8e2843; border-radius: 8px; padding: 8px 11px; font-size: 12px; font-weight: 750; cursor: pointer; }
@media(max-width: 1050px) {
  .topbar-inner { flex-wrap: wrap; padding: 9px 0; }
  nav { order: 3; flex-basis: 100%; justify-content: flex-start; }
  .profile > div { display: none; }
}
@media(max-width: 650px) {
  .topbar-inner { width: calc(100% - 20px); }
}
</style>