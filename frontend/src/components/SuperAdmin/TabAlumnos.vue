<script setup>
import { ref, computed, watch } from 'vue'
import api from '../../api/axios'

const props = defineProps({
  alumnos: { type: Array, default: () => [] },
  carreras: { type: Array, default: () => [] },
  grupos: { type: Array, default: () => [] }
})

const emit = defineEmits(['actualizar', 'toast', 'abrir-reset'])

const busqueda = ref('')
const filtroCarrera = ref('todos')
const filtroGrupo = ref('todos')

const modal = ref(null)
const alumnoForm = ref({})

function carreraPorId(id) { return props.carreras.find(c => String(c.id) === String(id)) }
function grupoPorId(id) { return props.grupos.find(g => String(g.id) === String(id)) }

// Grupos filtrados según la carrera seleccionada en los filtros
const gruposFiltrados = computed(() => {
  if (filtroCarrera.value === 'todos') {
    return props.grupos
  }
  return props.grupos.filter(g => String(g.carrera_id) === String(filtroCarrera.value))
})

// Reiniciar el filtro de grupo si la carrera cambia y el grupo ya no pertenece a ella
watch(filtroCarrera, (nuevaCarrera) => {
  if (nuevaCarrera === 'todos') return
  
  const grupoValido = gruposFiltrados.value.some(g => String(g.id) === String(filtroGrupo.value))
  if (!grupoValido) {
    filtroGrupo.value = 'todos'
  }
})

// Grupos disponibles dentro del modal de edición
const gruposEdicion = computed(() => {
  if (!alumnoForm.value.carrera_id) return props.grupos
  return props.grupos.filter(g => String(g.carrera_id) === String(alumnoForm.value.carrera_id))
})

// Si el usuario cambia la carrera en el modal, resetea el grupo si ya no coincide
watch(() => alumnoForm.value.carrera_id, (nuevaCarreraId) => {
  if (!nuevaCarreraId) return
  const esGrupoValido = gruposEdicion.value.some(g => String(g.id) === String(alumnoForm.value.grupo_id))
  if (!esGrupoValido) {
    alumnoForm.value.grupo_id = ''
  }
})

const alumnosFiltrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return props.alumnos.filter(a => {
    const okCarrera = filtroCarrera.value === 'todos' || String(a.carrera_id) === String(filtroCarrera.value)
    const okGrupo = filtroGrupo.value === 'todos' || String(a.grupo_id) === String(filtroGrupo.value)
    const universo = [a.name, a.email, a.matricula, a.grupo, a.carrera?.nombre, a.grupo_relacion?.nombre].filter(Boolean).join(' ').toLowerCase()
    return okCarrera && okGrupo && (!q || universo.includes(q))
  })
})

function editarAlumno(a) {
  alumnoForm.value = { id: a.id, name: a.name || '', matricula: a.matricula || '', carrera_id: a.carrera_id || '', grupo_id: a.grupo_id || '', grupo: a.grupo || '' }
  modal.value = 'alumno'
}

async function guardarAlumno() {
  const f = alumnoForm.value
  try {
    await api.patch(`/master/alumnos/${f.id}`, {
      name: f.name, matricula: f.matricula, carrera_id: f.carrera_id || null, grupo_id: f.grupo_id || null,
      grupo: grupoPorId(f.grupo_id)?.nombre || f.grupo || null
    })
    
    // Compatibilidad para asignar grupo formalmente si es necesario
    if (f.grupo_id) {
      try { await api.post(`/master/grupos/${f.grupo_id}/alumnos`, { alumno_id: f.id }) } catch (_) {}
    }

    modal.value = null
    emit('actualizar')
    emit('toast', 'Alumno actualizado.', 'ok')
  } catch (e) {
    emit('toast', e.response?.data?.message || 'No se pudo actualizar el alumno.', 'error')
  }
}
</script>

<template>
  <div>
    <div class="heading">
      <div>
        <span class="eyebrow">PADRÓN</span>
        <h1>Alumnos</h1>
        <p>Carrera, grupo y acceso al sistema.</p>
      </div>
    </div>

    <div class="filters">
      <input v-model="busqueda" placeholder="Buscar alumno..." />
      <select v-model="filtroCarrera">
        <option value="todos">Todas las carreras</option>
        <option v-for="c in props.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
      </select>
      <select v-model="filtroGrupo">
        <option value="todos">Todos los grupos</option>
        <option v-for="g in gruposFiltrados" :key="g.id" :value="g.id">{{ g.nombre }}</option>
      </select>
    </div>

    <div class="panel table-wrap">
      <table>
        <thead>
          <tr>
            <th>Alumno</th>
            <th>Matrícula</th>
            <th>Carrera</th>
            <th>Grupo</th>
            <th>Correo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in alumnosFiltrados" :key="a.id">
            <td><strong>{{ a.name }}</strong></td>
            <td>{{ a.matricula || '—' }}</td>
            <td>{{ a.carrera?.nombre || carreraPorId(a.carrera_id)?.nombre || 'Sin asignar' }}</td>
            <td>{{ a.grupo_relacion?.nombre || grupoPorId(a.grupo_id)?.nombre || a.grupo || 'Sin grupo' }}</td>
            <td>{{ a.email }}</td>
            <td class="row-actions">
              <button @click="editarAlumno(a)">Editar</button>
              <button @click="emit('abrir-reset', a)">Contraseña</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL ALUMNO -->
    <div v-if="modal === 'alumno'" class="overlay">
      <form class="modal" @submit.prevent="guardarAlumno">
        <button type="button" class="close" @click="modal = null">×</button>
        <h2>Editar alumno</h2>
        <label>Nombre <input v-model="alumnoForm.name" required /></label>
        <label>Matrícula <input v-model="alumnoForm.matricula" /></label>
        <label>Carrera 
          <select v-model="alumnoForm.carrera_id">
            <option value="">Sin carrera</option>
            <option v-for="c in props.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </label>
        <label>Grupo 
          <select v-model="alumnoForm.grupo_id">
            <option value="">Sin grupo</option>
            <option v-for="g in gruposEdicion" :key="g.id" :value="g.id">{{ g.nombre }}</option>
          </select>
        </label>
        <button class="primary submit">Guardar alumno</button>
      </form>
    </div>
  </div>
</template>