<script setup>
import { computed } from 'vue'

const props = defineProps({
    usuario: {
        type: Object,
        default: () => ({})
    },
    tabActual: {
        type: String,
        default: 'resumen'
    }
})

const emit = defineEmits(['cambiar-tab', 'scroll-solicitudes', 'cerrar-sesion'])

/*
|--------------------------------------------------------------------------
| DATOS DEL JEFE COMPUTADOS
|--------------------------------------------------------------------------
*/
const nombreJefe = computed(() => {
    return props.usuario?.name || 'Jefe de Carrera'
})

const primerNombre = computed(() => {
    return String(nombreJefe.value)
        .trim()
        .split(' ')
        .filter(Boolean)[0] || 'Jefe'
})

const iniciales = computed(() => {
    return String(nombreJefe.value)
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(nombre => nombre.charAt(0).toUpperCase())
        .join('') || 'JC'
})
</script>

<template>
    <header class="topbar">
        <div class="topbar-inner">
            <!-- LOGO INSTITUCIONAL -->
            <div class="brand">
                <div class="brand-mark">
                    <span class="green">UP</span>
                    <span class="red">T</span>
                    <span class="gray">ex</span>
                </div>
                <div class="brand-copy">
                    <strong>Sistema de Becas</strong>
                    <span>Gestión académica</span>
                </div>
            </div>

            <!-- NAVEGACIÓN (PESTAÑAS DEL DASHBOARD) -->
            <nav class="nav">
                <button 
                    type="button" 
                    :class="['nav-item', { active: tabActual === 'resumen' }]"
                    @click="emit('cambiar-tab', 'resumen')"
                >
                    Resumen
                </button>
                <button 
                    type="button" 
                    :class="['nav-item', { active: tabActual === 'revisadas' }]"
                    @click="emit('cambiar-tab', 'revisadas')"
                >
                    Solicitudes Revisadas
                </button>
            </nav>

            <!-- PERFIL Y CERRAR SESIÓN -->
            <div class="profile">
                <div class="profile-copy">
                    <strong>{{ primerNombre }}</strong>
                    <span>Jefe de Carrera</span>
                </div>
                <div class="avatar">
                    {{ iniciales }}
                </div>
                <button 
                    type="button" 
                    class="logout-button" 
                    title="Cerrar sesión" 
                    @click="emit('cerrar-sesion')"
                >
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 17l5-5-5-5" />
                        <path d="M15 12H3" />
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>

<style scoped>
.topbar {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(255, 255, 255, .95);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid #e5e8e6;
}

.topbar-inner {
    width: min(1220px, calc(100% - 40px));
    height: 76px;
    margin: auto;
    display: flex;
    align-items: center;
    gap: 35px;
}

.brand {
    min-width: 260px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-mark {
    font-size: 21px;
    font-weight: 900;
    letter-spacing: -2px;
}

.brand-mark .green { color: #247548; }
.brand-mark .red { color: #b91f37; }
.brand-mark .gray { color: #737a76; }

.brand-copy {
    padding-left: 12px;
    border-left: 1px solid #e1e4e2;
    display: flex;
    flex-direction: column;
}

.brand-copy strong {
    color: #29302c;
    font-size: 12px;
}

.brand-copy span {
    color: #9aa09d;
    font-size: 9px;
    margin-top: 3px;
}

.nav {
    flex: 1;
    display: flex;
    justify-content: center;
    gap: 6px;
}

.nav-item {
    border: 0;
    background: transparent;
    color: #737a76;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nav-item:hover,
.nav-item.active {
    background: #edf5f0;
    color: #247548;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-copy {
    display: flex;
    flex-direction: column;
    text-align: right;
}

.profile-copy strong { font-size: 11px; }
.profile-copy span { color: #929894; font-size: 9px; }

.avatar {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: linear-gradient(145deg, #247548, #358c5a);
    color: #fff;
    font-size: 11px;
    font-weight: 850;
}

.logout-button {
    width: 38px;
    height: 38px;
    border: 1px solid #e2e5e3;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #fff;
    color: #727975;
    cursor: pointer;
}

.logout-button:hover {
    color: #7a1c33;
    border-color: #ebd5db;
    background: #fff5f7;
}

@media (max-width: 800px) {
    .nav { display: none; }
    .brand { flex: 1; }
    .profile-copy { display: none; }
}

@media (max-width: 520px) {
    .topbar-inner {
        width: calc(100% - 24px);
        height: 68px;
    }
    .brand-copy span { display: none; }
}
</style>