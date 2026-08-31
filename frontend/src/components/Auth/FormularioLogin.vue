<script setup>
import { ref } from 'vue';
import api from '../../api/axios';

// Emits para avisar al componente padre (App.vue) sobre los cambios de vista o éxito
const emit = defineEmits([
  'login-exitoso',
  'ir-a-registro',
  'ir-a-recuperar',
  'ir-a-inicio'
]);

// Estados locales del formulario
const correoUsuario = ref('');
const passwordUsuario = ref('');
const mostrarPasswordLogin = ref(false);
const cargandoLogin = ref(false);
const mensajeLogin = ref('');
const errorLogin = ref(false);

const manejarLogin = async () => {
  errorLogin.value = false;
  mensajeLogin.value = '';
  cargandoLogin.value = true;

  try {
    const { data } = await api.post('/login', {
      email: correoUsuario.value.trim(),
      password: passwordUsuario.value
    });

    // Validar si requiere 2FA
    if (data?.two_factor_required === true) {
      if (!data?.challenge_token) {
        throw new Error('No se recibió el desafío 2FA.');
      }
      // Emitir evento para manejar 2FA en el padre
      emit('requiere-2fa', data.challenge_token);
      return;
    }

    if (!data?.token || !data?.user || !data?.user?.role) {
      throw new Error('El servidor no devolvió la sesión correctamente.');
    }

    // Guardar credenciales en localStorage
    localStorage.setItem('auth_token', data.token);
    localStorage.setItem('auth_user', JSON.stringify(data.user));

    // Emitir éxito al componente principal
    emit('login-exitoso', data);

  } catch (error) {
    console.error('Error login:', error);

    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');

    errorLogin.value = true;

    if (error.response) {
      mensajeLogin.value = error.response?.data?.message || 'Error al iniciar sesión.';
    } else if (error.request) {
      mensajeLogin.value = 'No se pudo conectar con el servidor.';
    } else {
      mensajeLogin.value = error.message || 'Error al iniciar sesión.';
    }
  } finally {
    cargandoLogin.value = false;
  }
};
</script>

<template>
    
<div class="w-full max-w-md relative z-20">
    <div class="bg-white rounded-3xl p-8 shadow-2xl space-y-5">
      
      <!-- LOGO Y ENCABEZADO INSTITUCIONAL -->
      <div class="text-center space-y-3">
       <img src="../../assets/logo-uptex.png" alt="UPTex" class="w-48 mx-auto" />
        
        <div class="flex justify-between items-center text-left pt-2 border-t border-slate-100">
          <div>
            <h2 class="font-black uppercase text-slate-800 text-sm">
              Iniciar Sesión
            </h2>
            <p class="text-xs text-slate-500">
              Credenciales institucionales
            </p>
          </div>
          <button @click="$emit('ir-a-inicio')" class="text-xs text-[#00723F] font-bold hover:underline">
            Regresar
          </button>
        </div>
      </div>

      <form @submit.prevent="manejarLogin" class="space-y-4">
        
        <!-- Correo -->
        <input
          v-model="correoUsuario"
          type="email"
          required
          placeholder="Correo institucional"
          class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#00723F]"
        />

        <!-- Contraseña con botón de mostrar/ocultar -->
        <div class="relative w-full">
          <input
            v-model="passwordUsuario"
            :type="mostrarPasswordLogin ? 'text' : 'password'"
            required
            placeholder="Contraseña"
            class="w-full border rounded-xl px-4 py-3 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-[#00723F]"
          />
          
          <button
            type="button"
            @click="mostrarPasswordLogin = !mostrarPasswordLogin"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
          >
            <!-- Ojo Abierto -->
            <svg
              v-if="!mostrarPasswordLogin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>

            <!-- Ojo Tachado -->
            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
          </button>
        </div>

        <!-- Enlace recuperar contraseña -->
        <div class="text-right">
          <button
            type="button"
            @click="$emit('ir-a-recuperar')"
            class="text-xs text-slate-500 hover:underline"
          >
            ¿Olvidaste tu contraseña?
          </button>
        </div>

        <!-- Mensajes de estado -->
        <p
          v-if="mensajeLogin"
          :class="errorLogin ? 'text-red-700' : 'text-green-700'"
          class="text-xs text-center font-bold"
        >
          {{ mensajeLogin }}
        </p>

        <!-- Botón Enviar -->
        <button
          type="submit"
          :disabled="cargandoLogin"
          class="w-full bg-[#00723F] text-white py-3 rounded-xl font-bold text-xs uppercase disabled:opacity-50 transition-colors hover:bg-[#005830]"
        >
          {{ cargandoLogin ? 'Validando...' : 'Validar Credenciales' }}
        </button>

        <!-- Enlace a registro -->
        <p class="text-center text-xs pt-2">
          ¿No tienes cuenta?
          <button
            type="button"
            @click="$emit('ir-a-registro')"
            class="text-[#7A1C33] font-bold hover:underline ml-1"
          >
            Regístrate
          </button>
        </p>

      </form>

    </div>
  </div>
</template>