<script setup>
defineProps({
    estadisticas: {
        type: Object,
        required: true
    }
})
</script>

<template>
    <section class="stats">
        <article class="stat">
            <div class="stat-icon neutral">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                </svg>
            </div>
            <div>
                <span>Alumnos</span>
                <strong>{{ estadisticas.total }}</strong>
                <small>Con solicitud</small>
            </div>
        </article>

        <article class="stat">
            <div class="stat-icon warning">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
            </div>
            <div>
                <span>Pendientes</span>
                <strong>{{ estadisticas.pendientes }}</strong>
                <small>Sin revisar</small>
            </div>
        </article>

        <article class="stat">
            <div class="stat-icon info">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19V5"/>
                    <path d="M4 19h16"/>
                    <path d="M8 15l3-4 3 2 4-6"/>
                </svg>
            </div>
            <div>
                <span>Seguimiento</span>
                <strong>{{ estadisticas.revision }}</strong>
                <small>En revisión</small>
            </div>
        </article>

        <article class="stat">
            <div class="stat-icon success">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12l4 4L19 6"/>
                </svg>
            </div>
            <div>
                <span>Aceptadas</span>
                <strong>{{ estadisticas.aceptadas }}</strong>
                <small>Dictaminadas</small>
            </div>
        </article>

        <article class="stat">
            <div class="stat-icon danger">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 6l12 12"/>
                    <path d="M18 6L6 18"/>
                </svg>
            </div>
            <div>
                <span>Rechazadas</span>
                <strong>{{ estadisticas.rechazadas }}</strong>
                <small>No aprobadas</small>
            </div>
        </article>
    </section>
</template>