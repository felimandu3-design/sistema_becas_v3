<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'

// 1. IMPORTAMOS A TODOS LOS COMPONENTES HIJOS
import Topbar from './SuperAdmin/Topbar.vue'
import TabResumen from './SuperAdmin/TabResumen.vue'
import TabSolicitudes from './SuperAdmin/TabSolicitudes.vue'
import TabConvocatorias from './SuperAdmin/TabConvocatorias.vue'
import TabPeriodos from './SuperAdmin/TabPeriodos.vue'
import TabCarreras from './SuperAdmin/TabCarreras.vue'
import TabGrupos from './SuperAdmin/TabGrupos.vue'
import TabAlumnos from './SuperAdmin/TabAlumnos.vue'
import TabPersonal from './SuperAdmin/TabPersonal.vue'
import TabAlertas from './SuperAdmin/TabAlertas.vue'

/* =========================================================
   PROPS Y EMITS
========================================================= */
const props = defineProps({
  usuario: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['cerrar-sesion'])

/* =========================================================
   ESTADO PRINCIPAL (EL MOTOR DE DATOS)
========================================================= */
const seccion = ref('resumen')
const cargando = ref(true)
const errorGeneral = ref('')
const toast = ref(null)
const modal = ref(null)

const solicitudes = ref([])
const convocatorias = ref([])
const periodos = ref([])
const carreras = ref([])
const grupos = ref([])
const alumnos = ref([])
const staff = ref([])
const statsApi = ref({})

/* =========================================================
   HELPERS GLOBALES
========================================================= */
function unwrap(data) {
  if (Array.isArray(data)) return data
  const claves = ['data', 'solicitudes', 'usuarios', 'convocatorias', 'periodos', 'carreras', 'grupos', 'alumnos', 'staff']
  for (const clave of claves) {
    if (Array.isArray(data?.[clave])) return data[clave]
  }
  return []
}

function mostrarToast(mensaje, tipo = 'ok') {
  toast.value = { mensaje, tipo }
  setTimeout(() => { toast.value = null }, 3200)
}

function estado(valor) {
  return String(valor || '').trim().toUpperCase()
}

// Helpers para el Modal de Solicitud
function alumnoDe(s) { return s?.usuario || s?.user || s?.alumno || {} }
function carreraPorId(id) { return carreras.value.find(c => String(c.id) === String(id)) }
function carreraSolicitud(s) {
  const alumno = alumnoDe(s)
  return alumno?.carrera?.nombre || carreraPorId(alumno?.carrera_id || s?.carrera_id)?.nombre || 'Sin carrera'
}
function folio(s) { return s?.folio || `BEC-${String(s?.id || 0).padStart(5, '0')}` }

/* =========================================================
   CARGA DE DATOS DESDE EL BACKEND
========================================================= */
async function cargarTodo() {
  cargando.value = true
  errorGeneral.value = ''

  const respuestas = await Promise.allSettled([
    api.get('/master/stats'),
    api.get('/master/solicitudes'),
    api.get('/master/convocatorias'),
    api.get('/master/periodos'),
    api.get('/master/carreras'),
    api.get('/master/grupos'),
    api.get('/master/alumnos'),
    api.get('/master/staff')
  ])

  const [rStats, rSolicitudes, rConvocatorias, rPeriodos, rCarreras, rGrupos, rAlumnos, rStaff] = respuestas

  if (rStats.status === 'fulfilled') statsApi.value = rStats.value.data?.stats || rStats.value.data || {}
  if (rSolicitudes.status === 'fulfilled') solicitudes.value = unwrap(rSolicitudes.value.data)
  if (rConvocatorias.status === 'fulfilled') convocatorias.value = unwrap(rConvocatorias.value.data)
  if (rPeriodos.status === 'fulfilled') periodos.value = unwrap(rPeriodos.value.data)
  if (rCarreras.status === 'fulfilled') carreras.value = unwrap(rCarreras.value.data)
  if (rGrupos.status === 'fulfilled') grupos.value = unwrap(rGrupos.value.data)
  if (rAlumnos.status === 'fulfilled') alumnos.value = unwrap(rAlumnos.value.data)
  if (rStaff.status === 'fulfilled') staff.value = unwrap(rStaff.value.data)

  const fallidos = respuestas.filter(r => r.status === 'rejected')
  if (fallidos.length) {
    errorGeneral.value = `${fallidos.length} módulo(s) no respondieron. El resto sigue disponible.`
  }
  cargando.value = false
}

/* =========================================================
   SISTEMA DE ALERTAS (Para Topbar y Pestaña Alertas)
========================================================= */
const periodoActivo = computed(() => periodos.value.find(p => estado(p.estado) === 'ACTIVO') || null)
const convocatoriaVigente = computed(() => convocatorias.value.find(c => estado(c.estado) === 'PUBLICADA') || null)

const alertas = computed(() => {
  const lista = []
  
  // Contamos directamente para no depender de la pestaña de resumen
  const pendientes = solicitudes.value.filter(s => estado(s.estado || s.estatus) === 'PENDIENTE').length
  const incompletas = solicitudes.value.filter(s => estado(s.estado || s.estatus) === 'DOCUMENTACION_INCOMPLETA').length

  if (pendientes) lista.push({ tipo: 'warning', titulo: 'Solicitudes pendientes', detalle: `${pendientes} solicitudes necesitan atención.`, destino: 'solicitudes' })
  if (incompletas) lista.push({ tipo: 'purple', titulo: 'Documentación incompleta', detalle: `${incompletas} expedientes requieren corrección.`, destino: 'solicitudes' })

  const sinCarrera = alumnos.value.filter(a => !a.carrera_id).length
  if (sinCarrera) lista.push({ tipo: 'info', titulo: 'Alumnos sin carrera', detalle: `${sinCarrera} alumnos no tienen carrera asignada.`, destino: 'alumnos' })

  const sinGrupo = alumnos.value.filter(a => !a.grupo_id && !a.grupo).length
  if (sinGrupo) lista.push({ tipo: 'warning', titulo: 'Alumnos sin grupo', detalle: `${sinGrupo} alumnos necesitan grupo.`, destino: 'alumnos' })

  if (!periodoActivo.value) lista.push({ tipo: 'danger', titulo: 'Sin periodo activo', detalle: 'El sistema no tiene un periodo académico activo.', destino: 'periodos' })
  if (!convocatoriaVigente.value) lista.push({ tipo: 'info', titulo: 'Sin convocatoria vigente', detalle: 'No existe una convocatoria publicada actualmente.', destino: 'convocatorias' })

  return lista
})

/* =========================================================
   LÓGICA COMPARTIDA DE MODALES GLOBALES
========================================================= */
// Modal Solicitud
const solicitudSeleccionada = ref(null)
function abrirSolicitud(s) {
  solicitudSeleccionada.value = s
  modal.value = 'solicitud'
}
async function actualizarSolicitud(nuevoEstado) {
  try {
    await api.patch(`/master/solicitudes/${solicitudSeleccionada.value.id}/estatus`, { estado: nuevoEstado })
    modal.value = null
    await cargarTodo()
    mostrarToast('Solicitud actualizada correctamente.')
  } catch (e) {
    mostrarToast(e.response?.data?.message || 'No se pudo actualizar.', 'error')
  }
}

// Modal Reset Password
const resetForm = ref({})
function abrirReset(u) {
  resetForm.value = { user: u, password: '', password_confirmation: '' }
  modal.value = 'reset'
}
async function restablecerPassword() {
  const f = resetForm.value
  if (f.password !== f.password_confirmation) return mostrarToast('Las contraseñas no coinciden.', 'error')
  
  try {
    await api.post('/superadmin/reset-password', { user_id: f.user.id, password: f.password, password_confirmation: f.password_confirmation })
    modal.value = null
    mostrarToast('Contraseña restablecida.')
  } catch (e) {
    mostrarToast(e.response?.data?.message || 'No se pudo cambiar la contraseña.', 'error')
  }
}

function cerrarSesion() { emit('cerrar-sesion') }

onMounted(cargarTodo)
</script>


<template>
<div class="dashboard">

  <!-- TOAST -->
  <transition name="fade">
    <div v-if="toast" class="toast" :class="toast.tipo">
      {{ toast.mensaje }}
    </div>
  </transition>

  <!-- NAVEGACIÓN SUPERIOR (Componente Hijo) -->
  <Topbar 
    :seccion="seccion" 
    :alertas="alertas" 
    :usuario="props.usuario"
    @cambiar-tab="id => seccion = id"
    @cerrar-sesion="cerrarSesion"
  />

  <main>
    <div v-if="errorGeneral" class="warning-banner">{{ errorGeneral }}</div>
    
    <div v-if="cargando" class="loading">
      <div class="spinner"></div>
      Cargando administración...
    </div>

    <template v-else>

      <!-- RESUMEN -->
      <TabResumen 
        v-if="seccion === 'resumen'"
        :solicitudes="solicitudes" :alumnos="alumnos" :staff="staff" 
        :carreras="carreras" :grupos="grupos" :convocatorias="convocatorias" 
        :periodos="periodos" :statsApi="statsApi" :alertas="alertas"
        @actualizar="cargarTodo"
      />

      <!-- SOLICITUDES -->
      <TabSolicitudes 
        v-if="seccion === 'solicitudes'"
        :solicitudes="solicitudes" :periodos="periodos" :carreras="carreras"
        @abrir-solicitud="abrirSolicitud"
      />

      <!-- CONVOCATORIAS -->
      <TabConvocatorias 
        v-if="seccion === 'convocatorias'"
        :convocatorias="convocatorias" :periodos="periodos"
        @actualizar="cargarTodo" @toast="mostrarToast"
      />

      <!-- PERIODOS -->
      <TabPeriodos 
        v-if="seccion === 'periodos'"
        :periodos="periodos"
        @actualizar="cargarTodo" @toast="mostrarToast"
      />

      <!-- CARRERAS -->
      <TabCarreras 
        v-if="seccion === 'carreras'"
        :carreras="carreras" :alumnos="alumnos" :grupos="grupos"
        @actualizar="cargarTodo" @toast="mostrarToast"
        @ver-grupos="id => { seccion = 'grupos' }"
      />

      <!-- GRUPOS -->
      <TabGrupos 
        v-if="seccion === 'grupos'"
        :grupos="grupos" :carreras="carreras" :periodos="periodos" 
        :staff="staff" :alumnos="alumnos" :periodoActivoId="periodoActivo?.id"
        @actualizar="cargarTodo" @toast="mostrarToast"
        @ver-alumnos="id => { seccion = 'alumnos' }"
      />

      <!-- ALUMNOS -->
      <TabAlumnos 
        v-if="seccion === 'alumnos'"
        :alumnos="alumnos" :carreras="carreras" :grupos="grupos"
        @actualizar="cargarTodo" @toast="mostrarToast" @abrir-reset="abrirReset"
      />

      <!-- PERSONAL -->
      <TabPersonal 
        v-if="seccion === 'personal'"
        :staff="staff" :carreras="carreras"
        @actualizar="cargarTodo" @toast="mostrarToast" @abrir-reset="abrirReset"
      />

      <!-- ALERTAS -->
      <TabAlertas 
        v-if="seccion === 'alertas'"
        :alertas="alertas"
        @actualizar="cargarTodo" @navegar="destino => { seccion = destino }"
      />

    </template>
  </main>

  <!-- =====================================================
       MODALES GLOBALES (Compartidos entre componentes)
  ====================================================== -->

  <!-- MODAL CAMBIAR ESTATUS SOLICITUD -->
  <div v-if="modal === 'solicitud'" class="overlay" @click.self="modal = null">
    <div class="modal">
      <button class="close" @click="modal = null">×</button>
      <span class="eyebrow">EXPEDIENTE</span>
      <h2>{{ alumnoDe(solicitudSeleccionada).name }}</h2>
      <p>{{ folio(solicitudSeleccionada) }} · {{ carreraSolicitud(solicitudSeleccionada) }}</p>

      <label>Estado
        <select :value="estado(solicitudSeleccionada.estado || solicitudSeleccionada.estatus)" @change="actualizarSolicitud($event.target.value)">
          <option value="PENDIENTE">Pendiente</option>
          <option value="EN_REVISION">En revisión</option>
          <option value="DOCUMENTACION_INCOMPLETA">Documentación incompleta</option>
          <option value="ACEPTADA">Aceptada</option>
          <option value="RECHAZADA">Rechazada</option>
        </select>
      </label>
    </div>
  </div>

  <!-- MODAL RESTABLECER CONTRASEÑA -->
  <div v-if="modal === 'reset'" class="overlay" @click.self="modal = null">
    <form class="modal" @submit.prevent="restablecerPassword">
      <button type="button" class="close" @click="modal = null">×</button>
      <h2>Restablecer contraseña</h2>
      <p>{{ resetForm.user?.name }}</p>
      
      <label>Nueva contraseña <input v-model="resetForm.password" type="password" minlength="8" required /></label>
      <label>Confirmar <input v-model="resetForm.password_confirmation" type="password" minlength="8" required /></label>
      <button class="primary submit">Cambiar contraseña</button>
    </form>
  </div>

</div>
</template>


<style>
/* Estilos globales para todo el dashboard y sus componentes hijos */
*{box-sizing:border-box}
.dashboard{min-height:100vh;background:#f4f7f5;color:#27312b;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;font-size:15px}
.topbar{position:sticky;top:0;z-index:40;background:rgba(255,255,255,.98);border-bottom:1px solid #dfe6e1;box-shadow:0 3px 12px rgba(20,50,35,.04)}
.topbar-inner{width:min(1420px,calc(100% - 32px));min-height:78px;margin:auto;display:flex;align-items:center;gap:22px}
.brand{min-width:225px;display:flex;align-items:center;gap:13px}.logo-text{font-size:23px;font-weight:900;color:#b32643}.logo-text span:first-child{color:#087846}.logo-text span:last-child{color:#707a74}
.brand>div:last-child{display:flex;flex-direction:column;border-left:1px solid #dfe5e1;padding-left:12px}.brand strong{font-size:14px}.brand small,.profile small{font-size:11px;color:#8c9690}
nav{flex:1;display:flex;justify-content:center;gap:3px;overflow-x:auto}nav button{position:relative;border:0;background:transparent;border-radius:9px;padding:10px;color:#66716a;font-size:13px;font-weight:750;white-space:nowrap;cursor:pointer}nav button:hover,nav button.active{background:#e8f3ed;color:#087846}.counter{position:absolute;right:1px;top:0;min-width:17px;height:17px;display:grid;place-items:center;border-radius:20px;background:#8e2843;color:#fff;font-size:10px}
.profile{display:flex;align-items:center;gap:8px}.profile>div{display:flex;flex-direction:column;text-align:right}.profile strong{font-size:12px}.avatar{width:39px;height:39px;display:grid;place-items:center;border-radius:50%;background:#087846;color:#fff;font-size:12px;font-weight:900}.logout{border:1px solid #dce3df;background:#fff;color:#8e2843;border-radius:8px;padding:8px 11px;font-size:12px;font-weight:750;cursor:pointer}
main{width:min(1280px,calc(100% - 32px));margin:auto;padding:38px 0 70px}.heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:20px}.eyebrow{display:block;color:#8a948e;font-size:11px;font-weight:900;letter-spacing:.14em}.heading h1{margin:5px 0;font-size:34px;letter-spacing:-.035em}.heading p{margin:0;color:#748078;font-size:14px}
.context{display:flex;gap:10px}.context>div{min-width:160px;padding:11px 14px;background:#fff;border:1px solid #e0e6e2;border-radius:11px}.context span{display:block;font-size:10px;color:#8d9791;text-transform:uppercase}.context strong{display:block;margin-top:3px;font-size:12px}
.primary,.secondary{border-radius:9px;padding:10px 14px;font-size:13px;font-weight:800;cursor:pointer}.primary{border:0;background:#087846;color:#fff}.primary:hover{background:#05683c}.secondary{border:1px solid #dce3df;background:#fff;color:#087846}
.filters{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:11px;margin-bottom:15px;background:#fff;border:1px solid #e0e6e2;border-radius:13px}.filters.four{grid-template-columns:repeat(4,1fr)}
input,select,textarea{width:100%;padding:11px 12px;border:1px solid #d9e1dc;border-radius:8px;background:#fff;color:#344039;font:inherit;font-size:13px;outline:none}input:focus,select:focus,textarea:focus{border-color:#6da486;box-shadow:0 0 0 3px #edf6f1}textarea{min-height:85px;resize:vertical}
.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:15px}.kpis article{padding:17px;border:1px solid #e0e6e2;border-top:3px solid #65716a;border-radius:13px;background:#fff}.kpis .amber{border-top-color:#d99a25}.kpis .blue{border-top-color:#3b82b6}.kpis .green{border-top-color:#147a4a}.kpis .burgundy{border-top-color:#8e2843}.kpis span,.mini-stats span{display:block;color:#838e88;font-size:11px;font-weight:800;text-transform:uppercase}.kpis strong{display:block;margin:5px 0;font-size:28px}.kpis small{font-size:11px;color:#919a95}
.charts{display:grid;grid-template-columns:1.25fr .85fr;gap:14px;margin-bottom:15px}.panel{background:#fff;border:1px solid #e0e6e2;border-radius:14px;overflow:hidden}.panel-title{padding:17px 18px 0}.panel-title h2{margin:4px 0;font-size:17px}.chart{height:285px;padding:14px}
.mini-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}.mini-stats article{padding:14px 16px;background:#fff;border:1px solid #e0e6e2;border-radius:11px}.mini-stats strong{display:block;margin-top:4px;font-size:21px}
.table-wrap{overflow:auto}table{width:100%;min-width:850px;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #edf1ee;text-align:left;font-size:13px}th{background:#fafbfa;color:#7d8881;font-size:11px;text-transform:uppercase}td strong{font-size:13px}.table-button,.row-actions button{border:0;background:#e8f3ed;color:#087846;border-radius:7px;padding:8px 10px;font-size:12px;font-weight:800;cursor:pointer}.row-actions{display:flex;gap:5px}
.badge{display:inline-flex;padding:5px 8px;border-radius:99px;font-size:11px;font-weight:800}.badge.success{background:#e6f3eb;color:#087846}.badge.danger{background:#fae9ee;color:#8e2843}.badge.info{background:#e8f2f8;color:#39749a}.badge.purple{background:#f1eaf8;color:#704994}.badge.warning{background:#fff2d3;color:#8d611c}
.records{display:grid;gap:10px}.record{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:17px;background:#fff;border:1px solid #e0e6e2;border-radius:13px}.record h3,.academic-card h3{margin:8px 0 4px;font-size:17px}.record p,.academic-card p{margin:0;color:#77827b;font-size:13px}.actions{display:flex;gap:6px;flex-wrap:wrap}.actions button,.action-link{border:1px solid #d9e1dc;background:#fff;border-radius:7px;padding:8px 10px;color:#536058;font-size:12px;font-weight:750;text-decoration:none;cursor:pointer}.green-text{color:#087846!important}.danger-text{color:#8e2843!important}
.card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:11px}.academic-card,.person-card{padding:17px;background:#fff;border:1px solid #e0e6e2;border-radius:13px}.academic-top{display:flex;justify-content:space-between;align-items:center}.code{font-size:11px;color:#8c9690;font-weight:800}.academic-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:15px 0}.academic-stats>div{padding:10px;background:#f5f7f6;border-radius:8px}.academic-stats span{display:block;color:#87918b;font-size:11px}.academic-stats strong{font-size:19px}.group-info{display:grid;gap:6px;margin:13px 0;padding:11px;background:#f6f8f7;border-radius:8px}.group-info span{font-size:12px;color:#65716a}.full-actions{margin-top:14px}
.person-head{display:flex;align-items:center;gap:10px}.person-avatar{width:42px;height:42px;display:grid;place-items:center;border-radius:10px;background:#e8f3ed;color:#087846;font-weight:900}.person-head>div{display:flex;flex-direction:column}.person-head strong{font-size:14px}.person-head small{font-size:11px;color:#86918a}.role{margin:13px 0;padding-top:11px;border-top:1px solid #edf1ee;font-size:12px;color:#65716a}
.alerts{display:grid;gap:10px}.alerts article{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;background:#fff;border:1px solid #e0e6e2;border-left:4px solid #d99a25;border-radius:11px}.alerts article.danger{border-left-color:#8e2843}.alerts article.info{border-left-color:#3b82b6}.alerts article.purple{border-left-color:#7754a4}.alerts strong{font-size:14px}.alerts p{margin:4px 0 0;color:#77827b;font-size:13px}.alerts button{border:0;background:#edf5f1;color:#087846;border-radius:7px;padding:8px 10px;font-size:12px;font-weight:800;cursor:pointer}.all-good{text-align:center;padding:55px;background:#fff;border:1px solid #e0e6e2;border-radius:14px}.all-good>span{width:55px;height:55px;margin:auto;display:grid;place-items:center;border-radius:50%;background:#e6f3eb;color:#087846;font-size:25px}.all-good h2{margin:12px 0 4px}.all-good p{color:#7b867f}
.overlay{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:18px;background:rgba(20,30,24,.5);backdrop-filter:blur(4px)}.modal{position:relative;width:min(500px,100%);max-height:90vh;overflow:auto;padding:25px;background:#fff;border-radius:16px;box-shadow:0 30px 80px rgba(20,30,24,.25)}.modal.large{width:min(700px,100%)}.modal h2{margin:0 0 12px;font-size:22px}.modal p{font-size:13px;color:#758078}.modal label{display:grid;gap:6px;margin-top:10px;font-size:13px;font-weight:750}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}.form-grid .full{grid-column:1/-1}.submit{width:100%;margin-top:14px}.close{position:absolute;right:12px;top:12px;width:32px;height:32px;border:0;border-radius:7px;background:#f2f5f3;font-size:18px;cursor:pointer}
.toast{position:fixed;right:20px;top:90px;z-index:150;padding:12px 16px;border-radius:9px;background:#087846;color:#fff;font-size:13px;font-weight:800}.toast.error{background:#8e2843}.warning-banner{margin-bottom:14px;padding:12px;background:#fff5d9;color:#7c591b;border:1px solid #ead7a0;border-radius:9px;font-size:13px}.loading{min-height:420px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#6f7973}.spinner{width:36px;height:36px;border:3px solid #e2e8e4;border-top-color:#087846;border-radius:50%;animation:spin .8s linear infinite}.empty{display:grid;place-items:center;height:100%;color:#8b958f;font-size:13px}@keyframes spin{to{transform:rotate(360deg)}}.fade-enter-active,.fade-leave-active{transition:.2s}.fade-enter-from,.fade-leave-to{opacity:0}
@media(max-width:1050px){.topbar-inner{flex-wrap:wrap;padding:9px 0}nav{order:3;flex-basis:100%;justify-content:flex-start}.profile>div{display:none}.kpis{grid-template-columns:repeat(2,1fr)}.charts{grid-template-columns:1fr}.card-grid{grid-template-columns:repeat(2,1fr)}.mini-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){main,.topbar-inner{width:calc(100% - 20px)}.heading,.record,.alerts article{align-items:flex-start;flex-direction:column}.context{flex-direction:column;width:100%}.context>div{width:100%}.filters,.filters.four,.kpis,.mini-stats,.card-grid,.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}.heading h1{font-size:28px}}
</style>