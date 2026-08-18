<script setup>
import { ref } from 'vue'
import api from '../../api/axios'

const props = defineProps({
  staff: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast', 'abrir-reset'])

const modal = ref(null)
const staffForm = ref({})

function iniciales(nombre) {
  return String(nombre || 'SA')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map(v => v[0]?.toUpperCase())
    .join('')
}

function nuevoPersonal() {
  staffForm.value = { name: '', email: '', password: '', role: 'profesor', carrera_id: '' }
  modal.value = 'personal'
}

async function crearPersonal() {
  const f = staffForm.value
  try {
    await api.post('/master/staff', {
      name: f.name, email: f.email, password: f.password, role: f.role,
      carreras: f.carrera_id ? [f.carrera_id] : []
    })
    modal.value = null
    emit('actualizar')
    emit('toast', 'Usuario institucional creado.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo crear el usuario.', 'error')
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
          <button @click="emit('abrir-reset', u)">Cambiar contraseña</button>
          <button class="danger-text" @click="eliminarPersonal(u)">Eliminar</button>
        </div>
      </article>
    </div>

    <!-- MODAL PERSONAL -->
    <div v-if="modal === 'personal'" class="overlay">
      <form class="modal" @submit.prevent="crearPersonal">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>Nuevo usuario institucional</h2>
        <label>Nombre <input v-model="staffForm.name" required /></label>
        <label>Correo <input v-model="staffForm.email" type="email" required /></label>
        <label>Contraseña temporal <input v-model="staffForm.password" type="password" minlength="8" required /></label>
        <label>Rol 
          <select v-model="staffForm.role">
            <option value="admin">Jefe / Administrador</option>
            <option value="profesor">Profesor / Tutor</option>
          </select>
        </label>
        <label>Carrera 
          <select v-model="staffForm.carrera_id">
            <option value="">Sin asignar</option>
            <option v-for="c in props.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </label>
        <button class="primary submit">Crear usuario</button>
      </form>
    </div>
  </div>
</template>