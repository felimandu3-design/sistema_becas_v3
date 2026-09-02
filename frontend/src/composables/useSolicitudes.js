import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'

export function useSolicitudes() {
  // 1. Inicializar SIEMPRE como arreglo vacío
  const solicitudes = ref([])
  const cargando = ref(false)
  const error = ref(null)
  const mensajeExito = ref('')

  const busqueda = ref('')
  const filtroEstado = ref('TODOS')

  const obtenerSolicitudes = async () => {
    cargando.value = true
    error.value = null
    try {
      const response = await api.get('/profesor/solicitudes')
      
      // 2. Manejar si Laravel responde paginado ({ data: [...] }) o un arreglo directo ([...])
      if (Array.isArray(response.data)) {
        solicitudes.value = response.data
      } else if (response.data && Array.isArray(response.data.data)) {
        solicitudes.value = response.data.data
      } else {
        solicitudes.value = []
      }
    } catch (err) {
      error.value = 'Error al cargar las solicitudes.'
      solicitudes.value = []
    } finally {
      cargando.value = false
    }
  }

  // 3. Computed tolerante a estructuras complejas, paginaciones y variaciones de nombres de atributos
  const solicitudesFiltradas = computed(() => {
    let lista = []
    if (Array.isArray(solicitudes.value)) {
      lista = solicitudes.value
    } else if (solicitudes.value && Array.isArray(solicitudes.value.data)) {
      lista = solicitudes.value.data
    }

    return lista.filter(item => {
      const term = (busqueda.value || '').toLowerCase().trim()
      
      const nombreAlumno = (
        item.usuario?.name || 
        item.usuario?.nombre || 
        item.alumno?.nombre || 
        item.alumno?.name || 
        ''
      ).toLowerCase()

      const matricula = (
        item.usuario?.matricula || 
        item.alumno?.matricula || 
        item.matricula || 
        ''
      ).toString().toLowerCase()

      const folio = (
        item.folio || 
        item.id || 
        ''
      ).toString().toLowerCase()

      const coincideBusqueda = !term || 
        nombreAlumno.includes(term) || 
        matricula.includes(term) || 
        folio.includes(term)

      // Normalización del estado seleccionado en el filtro
      const estadoItem = (
        item.estado || 
        item.estatus || 
        item.status || 
        'pendiente'
      ).toString().toLowerCase()

      const filtro = (filtroEstado.value || 'todos').toLowerCase()

      const coincideEstado = filtro === 'todos' || estadoItem === filtro

      return coincideBusqueda && coincideEstado
    })
  })

  const cambiarEstado = async (solicitudId, nuevoEstado) => {
    try {
      const { data } = await api.patch(`/profesor/solicitudes/${solicitudId}/estatus`, {
        estado: nuevoEstado
      })
      const index = solicitudes.value.findIndex(s => s.id === solicitudId)
      if (index !== -1) solicitudes.value[index] = data
      
      mensajeExito.value = 'Estado actualizado correctamente'
      setTimeout(() => mensajeExito.value = '', 3000)
    } catch (e) {
      error.value = 'No se pudo actualizar el estado.'
    }
  }

  onMounted(obtenerSolicitudes)

  return {
    solicitudes,
    solicitudesFiltradas,
    cargando,
    error,
    mensajeExito,
    busqueda,
    filtroEstado,
    cambiarEstado,
    obtenerSolicitudes
  }
}