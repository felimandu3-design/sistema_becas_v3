<script setup>
import { ref } from 'vue';
import api from '../../api/axios';

const props = defineProps({
  correo: {
    type: String,
    required: true
  }
});

const emit = defineEmits([
  'verificacion-exitosa',
  'ir-a-login'
]);

const codigoVerificacion = ref('');
const cargandoVerificacion = ref(false);
const mensajeVerificacion = ref('');
const errorVerificacion = ref(false);

const verificarCodigo = async () => {
  errorVerificacion.value = false;
  mensajeVerificacion.value = '';

  const codigoLimpio = codigoVerificacion.value.trim().toUpperCase();

  if (!codigoLimpio) {
    errorVerificacion.value = true;
    mensajeVerificacion.value = 'Ingresa el código de verificación.';
    return;
  }

  cargandoVerificacion.value = true;

  try {
    const { data } = await api.get(`/verify-email/${codigoLimpio}`);

    mensajeVerificacion.value = data?.message || 'Correo verificado correctamente.';

    setTimeout(() => {
      codigoVerificacion.value = '';
      emit('verificacion-exitosa');
    }, 1500);

  } catch (error) {
    errorVerificacion.value = true;
    mensajeVerificacion.value =
      error.response?.data?.message || 'No se pudo verificar el código.';
  } finally {
    cargandoVerificacion.value = false;
  }
};

const reenviarCodigo = async () => {
  if (!props.correo) {
    errorVerificacion.value = true;
    mensajeVerificacion.value = 'No se encontró el correo a verificar.';
    return;
  }

  cargandoVerificacion.value = true;

  try {
    const { data } = await api.post('/resend-token', {
      email: props.correo
    });

    errorVerificacion.value = false;
    mensajeVerificacion.value = data?.message || 'Código reenviado correctamente.';
  } catch (error) {
    errorVerificacion.value = true;
    mensajeVerificacion.value =
      error.response?.data?.message || 'No se pudo reenviar el código.';
  } finally {
    cargandoVerificacion.value = false;
  }
};
</script>

<template>
  <div class="w-full max-w-md relative z-20">
    <div class="bg-white rounded-3xl p-8 shadow-2xl space-y-4 text-center">
      
      <h2 class="font-black text-slate-800 uppercase">
        Verifica tu correo
      </h2>

      <p class="text-xs text-slate-500">
        Introduce el código de 6 dígitos enviado a:
        <br />
        <strong class="text-slate-700">{{ correo }}</strong>
      </p>

      <!-- Input de Código -->
      <input
        v-model="codigoVerificacion"
        maxlength="6"
        placeholder="ABC123"
        class="w-full text-center border rounded-xl py-3 text-sm font-mono uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-[#00723F]"
      />

      <!-- Mensajes de estado -->
      <p
        v-if="mensajeVerificacion"
        :class="errorVerificacion ? 'text-red-700' : 'text-green-700'"
        class="text-xs font-bold"
      >
        {{ mensajeVerificacion }}
      </p>

      <!-- Botón Verificar -->
      <button
        @click="verificarCodigo"
        :disabled="cargandoVerificacion"
        class="w-full bg-[#00723F] text-white py-3 rounded-xl font-bold text-xs uppercase disabled:opacity-50 transition-colors hover:bg-[#005830]"
      >
        {{ cargandoVerificacion ? 'Verificando...' : 'Verificar Código' }}
      </button>

      <!-- Botón Reenviar -->
      <button
        @click="reenviarCodigo"
        :disabled="cargandoVerificacion"
        class="w-full border border-[#00723F] text-[#00723F] py-2 rounded-xl font-bold text-xs uppercase transition-colors hover:bg-slate-50"
      >
        Reenviar Código
      </button>

      <!-- Regresar al Login -->
      <button
        type="button"
        @click="$emit('ir-a-login')"
        class="w-full text-xs text-slate-500 hover:underline pt-2"
      >
        Regresar al inicio de sesión
      </button>

    </div>
  </div>
</template>