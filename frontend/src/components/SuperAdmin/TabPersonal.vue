<script setup>
import api from '../../api/axios';
import { ref, watch } from 'vue'

const props = defineProps({
  staff: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] },
  grupos: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast', 'abrir-reset'])

const modal = ref(null)
const mostrarPassword = ref(false)

const staffForm = ref({
  name: '',
  email: '',
  password: '',
  role: 'profesor',
  carrera_id: '',
  grupo_id: ''
})

const editForm = ref({
  id: null,
  name: '',
  email: '',
  role: 'admin',
  carrera_id: '',
  grupo_id: ''
})

watch(() => editForm.value.role, (nuevoRol) => {
  if (nuevoRol === 'admin') editForm.value.grupo_id = ''
  if (nuevoRol === 'profesor') editForm.value.carrera_id = ''
})

watch(() => staffForm.value.role, (nuevoRol) => {
  if (nuevoRol === 'admin') staffForm.value.grupo_id = ''
  if (nuevoRol === 'profesor') staffForm.value.carrera_id = ''
})

function iniciales(nombre) {
  return String(nombre || 'SA')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(v => v[0]?.toUpperCase())
    .join('')
}

function nuevoPersonal() {
  staffForm.value = {
    name: '',
    email: '',
    password: '',
    role: 'profesor',
    carrera_id: '',
    grupo_id: ''
  }
  mostrarPassword.value = false
  modal.value = 'personal'
}

function abrirEditar(usuario) {
  editForm.value = {
    id: usuario.id,
    name: usuario.name,
    email: usuario.email,
    role: usuario.role || 'admin',
    carrera_id: usuario.carreras?.[0]?.id || usuario.carrera_id || '',
    grupo_id: usuario.grupos?.[0]?.id || usuario.grupo_id || ''
  }
  modal.value = 'editar'
}

async function crearPersonal() {
  const f = staffForm.value
  try {
    const carreraId = f.role === 'admin' && f.carrera_id ? Number(f.carrera_id) : null
    const grupoId = f.role === 'profesor' && f.grupo_id ? Number(f.grupo_id) : null

    const payload = {
      name: f.name,
      email: f.email,
      password: f.password,
      role: f.role,
      carrera_id: carreraId,
      grupo_id: grupoId,
    }

    await api.post('/master/staff', payload)
    
    modal.value = null
    staffForm.value = {
      name: '',
      email: '',
      password: '',
      role: 'profesor',
      carrera_id: '',
      grupo_id: ''
    }

    emit('actualizar')
    emit('toast', 'Usuario institucional creado exitosamente.', 'ok')
  } catch (e) {
    console.error('Error al crear personal:', e)
    const msj = e.response?.data?.message || e.message || 'No se pudo crear el usuario.'
    emit('toast', msj, 'error')
  }
}

async function guardarEdicion() {
  const userId = editForm.value.id || editForm.value.user_id
  if (!userId) {
    emit('toast', 'Error: No se encontró el ID del usuario a editar.', 'error')
    return
  }

  try {
    const carreraId = editForm.value.role === 'admin' && editForm.value.carrera_id ? Number(editForm.value.carrera_id) : null
    const grupoId = editForm.value.role === 'profesor' && editForm.value.grupo_id ? Number(editForm.value.grupo_id) : null

    const payload = {
      name: editForm.value.name,
      email: editForm.value.email,
      role: editForm.value.role,
      carrera_id: carreraId,
      carreras: carreraId ? [carreraId] : [],
      grupo_id: grupoId,
      grupos: grupoId ? [grupoId] : []
    }

    await api.put(`/master/staff/${userId}`, payload)

    modal.value = null
    emit('actualizar')
    emit('toast', 'Usuario actualizado con éxito', 'ok')
  } catch (error) {
    console.error('Error al actualizar personal:', error)
    const msj = error.response?.data?.message || error.message || 'Error al actualizar el usuario'
    emit('toast', msj, 'error')
  }
}

async function eliminarPersonal(u) {
  if (!confirm(`¿Eliminar a ${u.name}?`)) return
  try {
    await api.delete(`/master/staff/${u.id}`)
    emit('actualizar')
    emit('toast', 'Usuario eliminado.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo eliminar.', 'error')
  }
}
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">USUARIOS INSTITUCIONALES</span>
        <h1>Personal</h1>
        <p>Jefes, administradores y profesores/tutores.</p>
      </div>
      <button class="primary" @click="nuevoPersonal">+ Nuevo usuario</button>
    </div>

    <div class="card-grid">
      <article v-for="u in props.staff" :key="u.id" class="person-card">
        <div class="person-head">
          <span class="person-avatar">{{ iniciales(u.name) }}</span>
          <div>
            <strong>{{ u.name }}</strong>
            <small>{{ u.email }}</small>
          </div>
        </div>

        <div class="role">
          {{ u.role === 'admin' ? 'Jefe / Administrador' : u.role === 'profesor' ? 'Profesor / Tutor' : u.role }}
        </div>

        <div class="actions full-actions">
          <button type="button" class="btn-secondary" @click="abrirEditar(u)">
            Editar
          </button>
          <button type="button" class="btn-secondary" @click="emit('abrir-reset', u)">
            Cambiar contraseña
          </button>
          <button type="button" class="btn-danger-text" @click="eliminarPersonal(u)">
            Eliminar
          </button>
        </div>
      </article>
    </div>

    <!-- MODAL CREAR PERSONAL -->
    <div v-if="modal === 'personal'" class="overlay">
      <form class="modal" @submit.prevent="crearPersonal">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>Nuevo usuario institucional</h2>
        
        <label>Nombre <input v-model="staffForm.name" required /></label>
        <label>Correo <input v-model="staffForm.email" type="email" required /></label>
        
        <!-- Contraseña con botón Ojo -->
        <label>Contraseña temporal
          <div class="password-wrapper">
            <input 
              v-model="staffForm.password" 
              :type="mostrarPassword ? 'text' : 'password'" 
              minlength="8" 
              required 
            />
            <button 
              type="button" 
              class="eye-btn" 
              @click="mostrarPassword = !mostrarPassword"
              title="Mostrar u ocultar contraseña"
            >
              <svg v-if="!mostrarPassword" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                <line x1="1" y1="1" x2="23" y2="23" />
              </svg>
            </button>
          </div>
        </label>
        
        <!-- Selector de Rol -->
        <label>Rol 
          <select v-model="staffForm.role">
            <option value="admin">Jefe / Administrador</option>
            <option value="profesor">Profesor / Tutor</option>
          </select>
        </label>

        <!-- Si es Jefe / Administrador -> Muestra Selector de Carrera -->
        <label v-if="staffForm.role === 'admin'">Carrera
          <select v-model="staffForm.carrera_id">
            <option value="">Sin asignar</option>
            <option v-for="c in props.carreras" :key="c.id" :value="c.id">
              {{ c.nombre }}
            </option>
          </select>
        </label>

        <!-- Si es Profesor / Tutor -> Muestra Selector de Grupo -->
        <label v-if="staffForm.role === 'profesor'">Grupo
          <select v-model="staffForm.grupo_id">
            <option value="">Sin asignar</option>
            <option v-for="g in props.grupos" :key="g.id" :value="g.id">
              {{ g.nombre }}
            </option>
          </select>
        </label>

        <button class="primary submit">Crear usuario</button>
      </form>
    </div>

    <!-- MODAL EDITAR USUARIO -->
    <div v-if="modal === 'editar'" class="overlay">
      <form class="modal" @submit.prevent="guardarEdicion">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>Editar usuario institucional</h2>

        <label>Nombre
          <input v-model="editForm.name" type="text" required />
        </label>

        <label>Correo
          <input v-model="editForm.email" type="email" required />
        </label>

        <label>Rol
          <select v-model="editForm.role">
            <option value="admin">Jefe / Administrador</option>
            <option value="profesor">Profesor / Tutor</option>
          </select>
        </label>

        <!-- Asignación dinámica según el Rol -->
        <label v-if="editForm.role === 'admin'">Carrera
          <select v-model="editForm.carrera_id">
            <option value="">Sin asignar</option>
            <option v-for="c in props.carreras" :key="c.id" :value="c.id">
              {{ c.nombre }}
            </option>
          </select>
        </label>

        <label v-if="editForm.role === 'profesor'">Grupo
          <select v-model="editForm.grupo_id">
            <option value="">Sin asignar</option>
            <option v-for="g in props.grupos" :key="g.id" :value="g.id">
              {{ g.nombre }}
            </option>
          </select>
        </label>

        <button type="submit" class="primary submit">Guardar cambios</button>
      </form>
    </div>

  </div>
</template>

<style scoped>
.password-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.password-wrapper input {
  width: 100%;
  padding-right: 42px;
}

.eye-btn {
  position: absolute;
  right: 10px;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  border-radius: 4px;
  transition: color 0.2s;
}

.eye-btn:hover {
  color: #111827;
}
</style>