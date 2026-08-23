<script setup>
import api from '../../api/axios';
import { ref, watch } from 'vue'

const props = defineProps({
  staff: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] },
  grupos: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast', 'abrir-reset'])

const modal = ref(null) // Controla qué modal está abierto: 'personal', 'editar', etc.

// Estado del formulario de creación
const staffForm = ref({
  name: '',
  email: '',
  password: '',
  role: 'profesor',
  carrera_id: '',
  grupo_id: ''
})

// Estado del formulario de edición
const editForm = ref({
  id: null,
  name: '',
  email: '',
  role: 'admin',
  carrera_id: '',
  grupo_id: ''
})

// Limpiar selecciones no correspondientes al cambiar de rol en la edición
watch(() => editForm.value.role, (nuevoRol) => {
  if (nuevoRol === 'admin') editForm.value.grupo_id = ''
  if (nuevoRol === 'profesor') editForm.value.carrera_id = ''
})

// Limpiar selecciones no correspondientes al cambiar de rol en la creación
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

    //  Petición POST a la ruta de creación (sin userId)
    await api.post('/master/staff', payload)
    
    // Limpiar formulario y cerrar modal
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

    const token = localStorage.getItem('token') || sessionStorage.getItem('token') || localStorage.getItem('access_token')

    const config = {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {})
      },
      withCredentials: true
    }

   await api.put(`http://localhost:8000/api/master/staff/${userId}`, payload, {
      withCredentials: true
    })

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
        <label>Contraseña temporal <input v-model="staffForm.password" type="password" minlength="8" required /></label>
        
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