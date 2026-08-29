<script setup>
defineProps({
    carreraJefe: {
        type: String,
        default: 'Carrera asignada'
    },
    estadisticas: {
        type: Object,
        default: () => ({
            total: 0,
            pendientes: 0,
            revision: 0,
            aceptadas: 0,
            rechazadas: 0,
            incompletas: 0
        })
    },
    porcentajeAtendidas: {
        type: Number,
        default: 0
    }
})
</script>

<template>
    <div>
        <!-- ========================================================
             BIENVENIDA
        ========================================================= -->
        <section class="welcome">
            <div>
                <span class="eyebrow">PANEL ACADÉMICO</span>
                <h1>Gestión de solicitudes</h1>
                <p>Revisa y dictamina las solicitudes de beca correspondientes a tu carrera.</p>
            </div>
            <div class="career-card">
                <span>CARRERA ASIGNADA</span>
                <strong>{{ carreraJefe }}</strong>
            </div>
        </section>

        <!-- ========================================================
             ESTADÍSTICAS
        ========================================================= -->
        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon dark">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2h9l5 5v15H6z" />
                        <path d="M14 2v6h6" />
                    </svg>
                </div>
                <div>
                    <span>Total</span>
                    <strong>{{ estadisticas.total }}</strong>
                    <small>Solicitudes</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon amber">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </div>
                <div>
                    <span>Pendientes</span>
                    <strong>{{ estadisticas.pendientes }}</strong>
                    <small>Por atender</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19V5" />
                        <path d="M4 19h16" />
                        <path d="M8 15l3-4 3 2 4-6" />
                    </svg>
                </div>
                <div>
                    <span>En revisión</span>
                    <strong>{{ estadisticas.revision }}</strong>
                    <small>En proceso</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12l4 4L19 6" />
                    </svg>
                </div>
                <div>
                    <span>Aceptadas</span>
                    <strong>{{ estadisticas.aceptadas }}</strong>
                    <small>Dictamen positivo</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon burgundy">
                    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6l12 12" />
                        <path d="M18 6L6 18" />
                    </svg>
                </div>
                <div>
                    <span>Rechazadas</span>
                    <strong>{{ estadisticas.rechazadas }}</strong>
                    <small>Dictamen negativo</small>
                </div>
            </article>
        </section>

        <!-- ========================================================
             RESUMEN ATENCIÓN
        ========================================================= -->
        <section class="summary-strip">
            <div>
                <span class="eyebrow">AVANCE DE REVISIÓN</span>
                <strong>{{ porcentajeAtendidas }}%</strong>
                <p>de las solicitudes ya tienen dictamen final.</p>
            </div>
            <div class="summary-progress">
                <div 
                    class="summary-progress-fill" 
                    :style="{ width: porcentajeAtendidas + '%' }"
                ></div>
            </div>
            <div class="summary-numbers">
                <span>{{ estadisticas.aceptadas + estadisticas.rechazadas }} atendidas</span>
                <span>{{ estadisticas.total }} totales</span>
            </div>
        </section>
    </div>
</template>

<style scoped>
.eyebrow {
    display: block;
    color: #8a918d;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .16em;
}

/* WELCOME */
.welcome {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 25px;
    margin-bottom: 26px;
}

.welcome h1 {
    margin: 7px 0 5px;
    font-size: clamp(28px, 4vw, 40px);
    line-height: 1.05;
    letter-spacing: -.04em;
}

.welcome p {
    margin: 0;
    color: #7e8581;
    font-size: 13px;
}

.career-card {
    min-width: 270px;
    padding: 14px 17px;
    border: 1px solid #e2e6e3;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 6px 18px rgba(30, 40, 34, .04);
}

.career-card span {
    display: block;
    color: #9ca29f;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .11em;
}

.career-card strong {
    display: block;
    margin-top: 5px;
    color: #39413d;
    font-size: 11px;
}

/* STATS */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 13px;
    margin-bottom: 18px;
}

.stat-card {
    min-height: 110px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 17px 16px;
    border: 1px solid #e4e7e5;
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(27, 39, 32, .04);
}

.stat-icon {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 12px;
}

.stat-icon.dark { color: #424b46; background: #f0f2f1; }
.stat-icon.amber { color: #9a6817; background: #fff6dd; }
.stat-icon.blue { color: #32688f; background: #eaf3fa; }
.stat-icon.green { color: #247548; background: #eaf5ee; }
.stat-icon.burgundy { color: #85243d; background: #faedf1; }

.stat-card div:last-child {
    display: flex;
    flex-direction: column;
}

.stat-card span {
    color: #969c98;
    font-size: 8px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
}

.stat-card strong {
    margin-top: 2px;
    color: #2d3430;
    font-size: 24px;
    line-height: 1;
}

.stat-card small {
    margin-top: 5px;
    color: #a3a8a5;
    font-size: 8px;
}

/* SUMMARY */
.summary-strip {
    margin-bottom: 23px;
    padding: 18px 22px;
    display: grid;
    grid-template-columns: 210px 1fr auto;
    align-items: center;
    gap: 25px;
    border: 1px solid #e3e7e4;
    border-radius: 17px;
    background: #fff;
}

.summary-strip strong {
    display: inline-block;
    margin-top: 4px;
    color: #247548;
    font-size: 21px;
}

.summary-strip p {
    display: inline;
    margin-left: 7px;
    color: #7d8580;
    font-size: 10px;
}

.summary-progress {
    height: 6px;
    overflow: hidden;
    border-radius: 99px;
    background: #edf0ee;
}

.summary-progress-fill {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #247548, #45a16c);
    transition: width .3s ease;
}

.summary-numbers {
    display: flex;
    gap: 14px;
}

.summary-numbers span {
    color: #8c938f;
    font-size: 9px;
    font-weight: 700;
}

/* RESPONSIVE */
@media (max-width: 1050px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
    .summary-strip { grid-template-columns: 180px 1fr; }
    .summary-numbers { grid-column: 1 / -1; justify-content: flex-end; }
}

@media (max-width: 800px) {
    .welcome { flex-direction: column; align-items: flex-start; }
    .career-card { width: 100%; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .summary-strip { grid-template-columns: 1fr; }
    .summary-numbers { justify-content: flex-start; }
}

@media (max-width: 520px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 9px; }
    .stat-card { min-height: 100px; gap: 10px; padding: 13px; }
    .stat-icon { width: 35px; height: 35px; }
    .stat-card strong { font-size: 20px; }
}
</style>