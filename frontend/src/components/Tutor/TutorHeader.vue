<script setup>
defineProps({
    primerNombre: { type: String, default: 'Tutor' },
    iniciales: { type: String, default: 'PT' }
})

const emit = defineEmits(['confirmar-logout'])
</script>

<template>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <div class="brand-mark">
                    <span class="up">UP</span>
                    <span class="t">T</span>
                    <span class="ex">ex</span>
                </div>
                <div class="brand-info">
                    <strong>Sistema de Becas</strong>
                    <span>Seguimiento académico</span>
                </div>
            </div>

            <nav class="navigation">
                <button type="button" class="navigation-button active">
                    Resumen
                </button>
                <button
                    type="button"
                    class="navigation-button"
                    @click="document.getElementById('lista-solicitudes')?.scrollIntoView({ behavior: 'smooth' })"
                >
                    Alumnos
                </button>
            </nav>

            <div class="profile">
                <div class="profile-data">
                    <strong>{{ primerNombre }}</strong>
                    <span>Tutor académico</span>
                </div>
                <div class="avatar">
                    {{ iniciales }}
                </div>
                <button
                    type="button"
                    class="logout"
                    title="Cerrar sesión"
                    @click="emit('confirmar-logout')"
                >
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 17l5-5-5-5"/>
                        <path d="M15 12H3"/>
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>
</template>