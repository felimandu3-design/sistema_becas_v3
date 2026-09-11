<script setup>
import { ref } from 'vue';
import api from '../../api/axios';

const emit = defineEmits([
  'password-restablecida',
  'ir-a-login'
]);

// Estados locales
const pasoRecuperacion = ref(1);
const correoRecuperacion = ref('');
const codigoRecuperacion = ref('');
const passwordNueva = ref('');
const passwordNuevaConfirmar = ref('');

// Control de visibilidad de contraseñas nuevas
const mostrarPasswordNueva = ref(false);
const mostrarPasswordConfirmar = ref(false);

const cargandoRecuperacion = ref(false);
const mensajeRecuperacion = ref('');
const errorRecuperacion = ref(false);

const enviarCodigoRecuperacion = async () => {
  errorRecuperacion.value = false;
  mensajeRecuperacion.value = '';

  if (!correoRecuperacion.value.trim()) {
    errorRecuperacion.value = true;
    mensajeRecuperacion.value = 'Ingresa tu correo institucional.';
    return;
  }

  cargandoRecuperacion.value = true;

  try {
    const { data } = await api.post('/forgot-password', {
      email: correoRecuperacion.value.trim()
    });

    mensajeRecuperacion.value = data?.message || 'Código enviado correctamente.';
    pasoRecuperacion.value = 2;
  } catch (error) {
    errorRecuperacion.value = true;
    mensajeRecuperacion.value =
      error.response?.data?.message || 'Error al enviar el código.';
  } finally {
    cargandoRecuperacion.value = false;
  }
};

const restablecerContrasena = async () => {
  errorRecuperacion.value = false;
  mensajeRecuperacion.value = '';

  if (passwordNueva.value !== passwordNuevaConfirmar.value) {
    errorRecuperacion.value = true;
    mensajeRecuperacion.value = 'Las contraseñas no coinciden.';
    return;
  }

  cargandoRecuperacion.value = true;

  try {
    // ✅ CAMBIA 'codigo' POR 'code' AQUÍ:
    const { data } = await api.post('/reset-password', {
      email: correoRecuperacion.value.trim(),
      code: codigoRecuperacion.value.trim().toUpperCase(), 
      password: passwordNueva.value,
      password_confirmation: passwordNuevaConfirmar.value
    });

    mensajeRecuperacion.value = data?.message || 'Contraseña actualizada correctamente.';

    setTimeout(() => {
      emit('password-restablecida');
    }, 1500);

  } catch (error) {
    errorRecuperacion.value = true;
    mensajeRecuperacion.value =
      error.response?.data?.message || 'No se pudo restablecer la contraseña.';
  } finally {
    cargandoRecuperacion.value = false;
  }
};
</script>

<template>
  <div class="w-full max-w-md relative z-20">
    <div class="bg-white rounded-3xl p-8 shadow-2xl space-y-4">
      
      <h2 class="font-black uppercase text-center text-slate-800">
        Recuperar Contraseña
      </h2>

      <!-- Paso 1: Solicitar Correo -->
      <div v-if="pasoRecuperacion === 1" class="space-y-4">
        <p class="text-xs text-slate-500 text-center">
          Ingresa tu correo institucional para recibir un código de recuperación.
        </p>

        <input
          v-model="correoRecuperacion"
          type="email"
          required
          placeholder="Correo institucional"
          class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#00723F]"
        />

        <p
          v-if="mensajeRecuperacion"
          :class="errorRecuperacion ? 'text-red-700' : 'text-green-700'"
          class="text-xs text-center font-bold"
        >
          {{ mensajeRecuperacion }}
        </p>

        <button
          @click="enviarCodigoRecuperacion"
          :disabled="cargandoRecuperacion"
          class="w-full bg-[#00723F] text-white py-3 rounded-xl font-bold text-xs uppercase disabled:opacity-50 transition-colors hover:bg-[#005830]"
        >
          {{ cargandoRecuperacion ? 'Enviando...' : 'Enviar Código' }}
        </button>

        <button
          type="button"
          @click="$emit('ir-a-login')"
          class="w-full text-xs text-slate-500 text-center pt-2 hover:underline"
        >
          Regresar al inicio de sesión
        </button>
      </div>

      <!-- Paso 2: Ingresar Código y Nuevas Contraseñas -->
      <div v-else class="space-y-4">
        <p class="text-xs text-slate-500 text-center">
          Introduce el código de 6 dígitos recibido y tu nueva contraseña.
        </p>

        <input
          v-model="codigoRecuperacion"
          maxlength="6"
          placeholder="Código de recuperación"
          class="w-full text-center border rounded-xl py-3 uppercase tracking-widest text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#00723F]"
        />

        <!-- Nueva Contraseña con Ojo -->
        <div class="relative w-full">
          <input
            v-model="passwordNueva"
            :type="mostrarPasswordNueva ? 'text' : 'password'"
            placeholder="Nueva contraseña"
            class="w-full border rounded-xl px-4 py-3 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-[#00723F]"
          />
          <button
            type="button"
            @click="mostrarPasswordNueva = !mostrarPasswordNueva"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
          >
            <svg v-if="!mostrarPasswordNueva" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
          </button>
        </div>

        <!-- Confirmar Nueva Contraseña con Ojo -->
        <div class="relative w-full">
          <input
            v-model="passwordNuevaConfirmar"
            :type="mostrarPasswordConfirmar ? 'text' : 'password'"
            placeholder="Confirmar nueva contraseña"
            class="w-full border rounded-xl px-4 py-3 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-[#00723F]"
          />
          <button
            type="button"
            @click="mostrarPasswordConfirmar = !mostrarPasswordConfirmar"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
          >
            <svg v-if="!mostrarPasswordConfirmar" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
          </button>
        </div>

        <p
          v-if="mensajeRecuperacion"
          :class="errorRecuperacion ? 'text-red-700' : 'text-green-700'"
          class="text-xs text-center font-bold"
        >
          {{ mensajeRecuperacion }}
        </p>

        <button
          @click="restablecerContrasena"
          :disabled="cargandoRecuperacion"
          class="w-full bg-[#00723F] text-white py-3 rounded-xl font-bold text-xs uppercase disabled:opacity-50 transition-colors hover:bg-[#005830]"
        >
          {{ cargandoRecuperacion ? 'Actualizando...' : 'Restablecer Contraseña' }}
        </button>

        <button
          type="button"
          @click="pasoRecuperacion = 1; mensajeRecuperacion = '';"
          class="w-full text-xs text-slate-500 text-center pt-2 hover:underline"
        >
          Volver a enviar código / Cambiar correo
        </button>
      </div>

    </div>
  </div>
</template>