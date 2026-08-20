<template>
  <div class="min-h-screen relative flex items-center justify-center bg-slate-900 font-sans select-none overflow-hidden">
    
    <!-- FONDO INSTITUCIONAL CON DESENFOQUE -->
    <div class="absolute inset-0 z-0">
      <img src="../assets/universidad1.jpg" alt="Fondo UPTex" class="w-full h-full object-cover filter blur-xs brightness-50">
    </div>

    <!-- CONTENEDOR CENTRAL -->
    <div class="relative z-10 w-full max-w-lg px-4">

      <!-- VISTA INICIAL PÚBLICA -->
      <div v-if="vistaPublica === 'inicio'" class="bg-white/95 backdrop-blur-md rounded-3xl p-8 shadow-2xl border border-white/20 text-center space-y-6">
        <div class="flex justify-center">
          <img src="../assets/logo-uptex.png" alt="Logo UPTex" class="h-24 w-auto mx-auto" />
        </div>

        <div>
          <h1 class="text-xl font-black text-slate-800 tracking-tight">SISTEMA DE CONTROL <span class="text-emerald-700">DE BECAS</span></h1>
          <p class="text-xs text-slate-500 font-medium mt-1">Portal digital para el registro, validación y seguimiento de becas de descuento en colegiaturas.</p>
        </div>

        <div class="space-y-3 pt-2">
          <button @click="cargarConvocatoria" class="w-full py-3.5 px-4 bg-white hover:bg-slate-50 text-emerald-800 font-bold text-xs rounded-xl border-2 border-emerald-700 transition shadow-sm flex items-center justify-center gap-2 cursor-pointer">
            VER CONVOCATORIA VIGENTE
          </button>

          <button @click="vistaPublica = 'auth'" class="w-full py-3.5 px-4 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-xl transition shadow-md flex items-center justify-center gap-2 cursor-pointer">
            ACCESO AL PORTAL
          </button>
        </div>
      </div>

      <!-- VISTA DE CONVOCATORIA VIGENTE -->
      <div v-else-if="vistaPublica === 'convocatoria'" class="bg-white/95 backdrop-blur-md rounded-3xl p-6 shadow-2xl border border-white/20 text-center space-y-5">
        <div class="flex justify-between items-center border-b border-slate-200 pb-3">
          <h2 class="text-sm font-extrabold text-slate-800 tracking-wider uppercase">CONVOCATORIA VIGENTE</h2>
          <button @click="vistaPublica = 'inicio'" class="text-xs font-bold text-emerald-700 hover:underline cursor-pointer">
            Regresar
          </button>
        </div>

        <div class="min-h-[200px] bg-slate-50 rounded-2xl border border-slate-200 flex flex-col items-center justify-center p-6">
          <div v-if="cargandoConvocatoria" class="text-xs text-slate-500 font-bold animate-pulse">
            Cargando información de la convocatoria...
          </div>

          <div v-else-if="convocatoria" class="space-y-3 w-full">
            <div class="w-12 h-12 bg-red-100 text-red-700 rounded-full flex items-center justify-center mx-auto text-xl font-bold">
              📄
            </div>
            <h3 class="text-xs font-bold text-slate-800">{{ convocatoria.nombre || convocatoria.titulo }}</h3>
            
            <p v-if="convocatoria.fecha_cierre" class="text-[10px] text-slate-400">
              Cierre: {{ new Date(convocatoria.fecha_cierre).toLocaleDateString('es-MX') }}
            </p>

            <!-- BOTÓN QUE ABRE EL VISOR PDF -->
            <button 
              v-if="convocatoria.archivo || convocatoria.url" 
              @click="abrirPdf(convocatoria.archivo || convocatoria.url)" 
              class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-900 text-white font-bold text-xs rounded-lg transition shadow-sm mt-2 cursor-pointer"
            >
              Ver Documento Oficial
            </button>
          </div>

          <div v-else class="text-center text-slate-500 text-xs">
            No hay ninguna convocatoria activa publicada en este momento.
          </div>
        </div>

        <button @click="vistaPublica = 'auth'" class="w-full py-3.5 px-4 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs rounded-xl transition shadow-md cursor-pointer">
          INICIAR SESIÓN PARA SOLICITAR
        </button>
      </div>

      <!-- VISTA DE AUTENTICACIÓN -->
      <div v-else-if="vistaPublica === 'auth'">
        <button @click="vistaPublica = 'inicio'" class="mb-2 text-xs font-bold text-white/80 hover:text-white flex items-center gap-1 cursor-pointer">
          ← Regresar al menú principal
        </button>
        <AuthForm @login-success="onLoginSuccess" />
      </div>

    </div>

    <!-- FOOTER INSTITUCIONAL -->
    <footer class="absolute bottom-4 text-center w-full z-10">
      <p class="text-[11px] text-white/80 font-medium">© 2026 Universidad Politécnica de Texcoco. Todos los derechos reservados.</p>
    </footer>

    <!-- VISOR DE PDF FLOTANTE (MODAL) -->
    <div v-if="modalPdf" class="overlay" style="display: flex; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999;" @click.self="cerrarPdf">
      <div class="modal" style="width: 80%; height: 90vh; max-width: 1000px; background: white; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <div style="padding: 15px 20px; background: #f4f6f5; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0;">
          <h3 style="margin: 0; color: #147a4a; font-size: 16px;">Convocatoria Oficial</h3>
          <button @click="cerrarPdf" style="background: none; border: none; font-size: 28px; line-height: 1; cursor: pointer; color: #64748b; padding: 0;">&times;</button>
        </div>
        <div style="flex: 1; width: 100%; background: #334155;">
          <iframe :src="modalPdf" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import AuthForm from './AuthLogin.vue'; 

const emit = defineEmits(['login-success']);
const vistaPublica = ref('inicio');
const cargandoConvocatoria = ref(false);
const convocatoria = ref(null);
const modalPdf = ref(null);

// Formateador de ruta para el PDF
function urlArchivo(ruta) {
  if (!ruta) return null;
  if (String(ruta).startsWith('http')) return ruta;
  if (String(ruta).startsWith('/')) return `http://127.0.0.1:8000${ruta}`;
  return `http://127.0.0.1:8000/storage/${ruta}`;
}

function abrirPdf(ruta) {
  modalPdf.value = urlArchivo(ruta);
}

function cerrarPdf() {
  modalPdf.value = null;
}

// Carga la convocatoria desde la API
const cargarConvocatoria = async () => {
  vistaPublica.value = 'convocatoria';
  cargandoConvocatoria.value = true;
  try {
    // Usando la ruta de la API que ya tenías configurada
    const response = await fetch('http://127.0.0.1:8000/api/convocatoria-activa');
    const data = await response.json();
    
    // Validamos si la respuesta trae la data directamente o envuelta en un objeto 'data' o 'convocatoria'
    const infoConv = data.data || data.convocatoria || data;

    if (response.ok && infoConv && (infoConv.estado === 'PUBLICADA' || infoConv.activa || infoConv.id)) {
      convocatoria.value = infoConv;
    } else {
      convocatoria.value = null;
    }
  } catch (error) {
    console.error("Error al obtener la convocatoria:", error);
    convocatoria.value = null;
  } finally {
    cargandoConvocatoria.value = false;
  }
};

const onLoginSuccess = (role) => {
  emit('login-success', role);
};
</script>