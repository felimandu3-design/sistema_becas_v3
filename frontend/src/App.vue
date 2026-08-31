<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api from './api/axios';

// Recursos visuales (Imágenes y Logos)
import logoUptex from './assets/logo-uptex.png';
import campus1 from './assets/universidad1.jpg';
import campus2 from './assets/universidad2.jpg';
import campus3 from './assets/universidad3.jpg';

// Componentes del Dashboard (Roles internos)
import SuperAdminDashboard from './components/SuperAdminDashboard.vue';
import JefeDashboard from './components/JefeDashboard.vue';
import TutorDashboard from './components/TutorDashboard.vue';
import AlumnoDashboard from './components/AlumnoDashboard.vue';

// Vistas / Componentes Públicos y 2FA
import Login from './components/Auth/FormularioLogin.vue';
import Registro from './components/Auth/FormularioRegistro.vue';
import VerificacionCorreo from './components/Auth/VerificacionCorreo.vue';
import RecuperarPassword from './components/Auth/RecuperarPassword.vue';
import TwoFactorSetup from './views/TwoFactorSetup.vue';
import TwoFactorChallenge from './views/TwoFactorChallenge.vue';

/* =========================================================
   FONDOS ROTATIVOS
========================================================= */
const fondos = ref([campus1, campus2, campus3]);
const fondoActivo = ref(0);
let intervaloFondo = null;

const cambiarFondoAutomatico = () => {
  intervaloFondo = setInterval(() => {
    fondoActivo.value = (fondoActivo.value + 1) % fondos.value.length;
  }, 4000);
};

/* =========================================================
   ESTADOS GLOBALES DE LA APP
========================================================= */
const vistaActiva = ref('inicio');
const usuarioActivo = ref(null);
const twoFactorChallengeToken = ref('');
const correoTemporalVerificacion = ref('');

// Estados para Convocatorias Públicas
const convocatoriasPublicas = ref([]);
const cargandoConvocatoriasPublicas = ref(false);

/* =========================================================
   NAVEGACIÓN Y CAMBIO DE VISTAS
========================================================= */
const cambiarAInicio = () => { vistaActiva.value = 'inicio'; };
const cambiarALogin = () => { vistaActiva.value = 'login'; };
const cambiarARegistro = () => { vistaActiva.value = 'registro'; };
const irARecuperar = () => { vistaActiva.value = 'recuperar'; };

const irAVerificacion = (email) => {
  correoTemporalVerificacion.value = email;
  vistaActiva.value = 'verificacion';
};

/* =========================================================
   REDIRECCIÓN SEGÚN ROL
========================================================= */
const redirigirSegunRol = (role) => {
  console.log("Rol recibido de la API:", role);
  const rol = String(role || '').trim().toLowerCase();
  console.log("Rol procesado:", rol);

  const rutas = {
    superadmin: 'panel-master',
    admin: 'panel-admin',
    profesor: 'panel-profesor',
    alumno: 'panel-alumno'
  };

  if (!rutas[rol]) {
    console.error('Rol no reconocido:', rol);
    usuarioActivo.value = null;
    vistaActiva.value = 'inicio';
    return;
  }

  vistaActiva.value = rutas[rol];
};

/* =========================================================
   GESTIÓN DE SESIÓN Y 2FA
========================================================= */
const manejarLoginExitoso = (data) => {
  if (data?.two_factor_required) {
    twoFactorChallengeToken.value = data.challenge_token;
    vistaActiva.value = 'two-factor-challenge';
    return;
  }

  if (data?.token && data?.user) {
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('auth_user', JSON.stringify(data.user));
    usuarioActivo.value = data.user;
    redirigirSegunRol(data.user.role);
  }
};

const completarTwoFactor = (data) => {
  if (!data?.token || !data?.user) return;
  localStorage.setItem('auth_token', data.token);
  localStorage.setItem('auth_user', JSON.stringify(data.user));
  usuarioActivo.value = data.user;
  twoFactorChallengeToken.value = '';
  redirigirSegunRol(data.user.role);
};

const cancelarTwoFactorChallenge = () => {
  twoFactorChallengeToken.value = '';
  vistaActiva.value = 'login';
};

const abrirTwoFactorSetup = () => {
  if (!usuarioActivo.value) return;
  vistaActiva.value = 'two-factor-setup';
};

const restaurarSesion = async () => {
  const tokenGuardado = localStorage.getItem('auth_token');

  if (!tokenGuardado) {
    localStorage.removeItem('auth_user');
    usuarioActivo.value = null;
    vistaActiva.value = 'inicio';
    return;
  }

  try {
    const { data } = await api.get('/user');
    const usuario = data?.user || data?.data || data;

    if (!usuario || !usuario.id || !usuario.role) {
      throw new Error('La API no devolvió un usuario válido.');
    }

    usuarioActivo.value = usuario;
    localStorage.setItem('auth_user', JSON.stringify(usuario));
    redirigirSegunRol(usuario.role);
  } catch (error) {
    console.warn('No se pudo restaurar la sesión:', error);
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    usuarioActivo.value = null;
    vistaActiva.value = 'inicio';
  }
};

const cerrarSesion = async () => {
  try {
    if (localStorage.getItem('auth_token')) {
      await api.post('/logout');
    }
  } catch (error) {
    console.warn('Error cerrando sesión:', error);
  }

  localStorage.removeItem('auth_token');
  localStorage.removeItem('auth_user');
  usuarioActivo.value = null;
  twoFactorChallengeToken.value = '';
  vistaActiva.value = 'inicio';
};

/* =========================================================
   CONVOCATORIAS PÚBLICAS
========================================================= */
const verConvocatoriaPublica = async () => {
  vistaActiva.value = 'convocatoria-publica';
  cargandoConvocatoriasPublicas.value = true;

  try {
    const { data } = await api.get('/convocatorias-publicas');
    convocatoriasPublicas.value = data?.convocatorias || data?.data || (Array.isArray(data) ? data : []);
  } catch (error) {
    console.error('Error cargando convocatorias:', error);
    convocatoriasPublicas.value = [];
  } finally {
    cargandoConvocatoriasPublicas.value = false;
  }
};

const formatearFecha = (fecha) => {
  if (!fecha) return '';
  const valor = new Date(fecha);
  if (Number.isNaN(valor.getTime())) return fecha;
  return valor.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
};

/* =========================================================
   CICLO DE VIDA
========================================================= */
onMounted(() => {
  cambiarFondoAutomatico();
  restaurarSesion();
});

onUnmounted(() => {
  if (intervaloFondo) clearInterval(intervaloFondo);
});
</script>

<template>
  <div class="min-h-screen font-sans">

    <!-- =====================================================
         VISTAS PÚBLICAS Y AUTENTICACIÓN
    ===================================================== -->
    <div
      v-if="[
        'inicio',
        'login',
        'registro',
        'verificacion',
        'recuperar',
        'convocatoria-publica',
        'two-factor-setup',
        'two-factor-challenge'
      ].includes(vistaActiva)"
      class="min-h-screen flex flex-col items-center justify-between p-4 relative overflow-hidden"
    >
      <!-- FONDO ROTATIVO -->
      <div class="absolute inset-0 z-0 pointer-events-none">
        <div
          v-for="(img, index) in fondos"
          :key="index"
          :style="{ backgroundImage: `url(${img})` }"
          :class="[
            'absolute inset-0 bg-cover bg-center transition-opacity duration-1000',
            fondoActivo === index ? 'opacity-40' : 'opacity-0'
          ]"
        ></div>
        <div class="absolute inset-0 bg-[#1C1F26]/40"></div>
      </div>

      <div></div>

      <!-- 1. PANTALLA DE INICIO -->
      <div v-if="vistaActiva === 'inicio'" class="w-full max-w-md relative z-20">
        <div class="bg-white rounded-3xl p-8 shadow-2xl text-center space-y-6">
          <img :src="logoUptex" alt="UPTex" class="w-64 mx-auto" />
          <h1 class="text-xl font-black">
            Sistema de Control <span class="text-[#00723F]">de Becas</span>
          </h1>
          <p class="text-xs text-slate-500">Universidad Politécnica de Texcoco</p>

          <button
            @click="verConvocatoriaPublica"
            class="w-full border-2 border-[#00723F] text-[#00723F] py-3 rounded-xl font-bold text-xs uppercase transition-colors hover:bg-slate-50"
          >
            Ver Convocatoria Vigente
          </button>

          <button
            @click="cambiarALogin"
            class="w-full bg-[#00723F] text-white py-3 rounded-xl font-bold text-xs uppercase transition-colors hover:bg-[#005830]"
          >
            Acceso al Portal
          </button>
        </div>
      </div>

      <!-- 2. CONVOCATORIA PÚBLICA -->
      <div v-if="vistaActiva === 'convocatoria-publica'" class="w-full max-w-lg relative z-20">
        <div class="bg-white rounded-3xl p-8 shadow-2xl space-y-5">
          <div class="flex justify-between items-center">
            <h2 class="font-black uppercase text-slate-800">Convocatoria Vigente</h2>
            <button @click="cambiarAInicio" class="text-xs text-[#00723F] font-bold hover:underline">
              Regresar
            </button>
          </div>

          <p v-if="cargandoConvocatoriasPublicas" class="text-center text-sm text-slate-500">Cargando...</p>
          <p v-else-if="convocatoriasPublicas.length === 0" class="text-center text-sm text-slate-500">
            No hay convocatoria activa.
          </p>

          <div v-else class="space-y-3 max-h-96 overflow-y-auto pr-1">
            <article
              v-for="convocatoria in convocatoriasPublicas"
              :key="convocatoria.id"
              class="border rounded-xl p-4 space-y-2 bg-slate-50/50"
            >
              <h3 class="font-bold text-slate-800">{{ convocatoria.titulo || convocatoria.nombre }}</h3>
              <p class="text-xs text-slate-500">{{ convocatoria.descripcion }}</p>
              <p class="text-xs mt-2 text-slate-700 font-medium">
                Cierre: {{ formatearFecha(convocatoria.fecha_cierre) }}
              </p>
              <div v-if="convocatoria.archivo" class="pt-2">
                <a
                  :href="`http://localhost:8000/storage/${convocatoria.archivo}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1.5 text-xs bg-[#00723F] hover:bg-[#005830] text-white font-semibold py-2 px-3 rounded-lg transition-colors"
                >
                  📄 Ver PDF
                </a>
              </div>
            </article>
          </div>

          <button
            @click="cambiarALogin"
            class="w-full bg-[#00723F] text-white py-3 rounded-xl font-bold text-xs uppercase transition-colors hover:bg-[#005830]"
          >
            Iniciar sesión
          </button>
        </div>
      </div>

      <!-- 3. LOGIN MODULAR -->
      <Login
  v-if="vistaActiva === 'login'"
      :logo-uptex="logoUptex"
      @login-exitoso="manejarLoginExitoso"
      @ir-a-inicio="cambiarAInicio"
      @ir-a-registro="cambiarARegistro"
      @ir-a-recuperar="irARecuperar"
      />

      <!-- 4. REGISTRO MODULAR -->
      <Registro
        v-if="vistaActiva === 'registro'"
        @registro-exitoso="irAVerificacion"
        @ir-a-login="cambiarALogin"
      />

      <!-- 5. VERIFICACIÓN DE CORREO MODULAR -->
      <VerificacionCorreo
        v-if="vistaActiva === 'verificacion'"
        :correo="correoTemporalVerificacion"
        @verificacion-exitosa="cambiarALogin"
        @ir-a-login="cambiarALogin"
      />

      <!-- 6. RECUPERAR CONTRASEÑA MODULAR -->
      <RecuperarPassword
        v-if="vistaActiva === 'recuperar'"
        @password-restablecida="cambiarALogin"
        @ir-a-login="cambiarALogin"
      />

      <!-- 7. 2FA SETUP -->
      <TwoFactorSetup
        v-if="vistaActiva === 'two-factor-setup'"
        :usuario="usuarioActivo"
        @completado="redirigirSegunRol(usuarioActivo.role)"
        @cancelar="redirigirSegunRol(usuarioActivo.role)"
      />

      <!-- 8. 2FA CHALLENGE -->
      <TwoFactorChallenge
        v-if="vistaActiva === 'two-factor-challenge'"
        :challenge-token="twoFactorChallengeToken"
        @completado="completarTwoFactor"
        @cancelar="cancelarTwoFactorChallenge"
      />

      <div></div>
    </div>

    <!-- =====================================================
         DASHBOARDS / PANELES INTERNOS (SEGÚN ROL)
    ===================================================== -->
    <SuperAdminDashboard
      v-if="vistaActiva === 'panel-master'"
      :usuario="usuarioActivo"
      @cerrar-sesion="cerrarSesion"
    />

    <JefeDashboard
      v-if="vistaActiva === 'panel-admin'"
      :usuario="usuarioActivo"
      @cerrar-sesion="cerrarSesion"
      @configurar-2fa="abrirTwoFactorSetup"
    />

    <TutorDashboard
      v-if="vistaActiva === 'panel-profesor'"
      :usuario="usuarioActivo"
      @cerrar-sesion="cerrarSesion"
      @configurar-2fa="abrirTwoFactorSetup"
    />

    <AlumnoDashboard
      v-if="vistaActiva === 'panel-alumno'"
      :usuario="usuarioActivo"
      @cerrar-sesion="cerrarSesion"
      @configurar-2fa="abrirTwoFactorSetup"
    />

  </div>
</template>