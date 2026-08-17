# Etapa 1: Construir Vue 3
FROM node:20-alpine as build-stage
WORKDIR /app

# Copiar archivos de dependencias del frontend
COPY frontend/package*.json ./
RUN npm install

# Copiar el resto del código Vue y compilar
COPY frontend/ .

# Inyectamos la URL de la API para que Axios apunte al mismo dominio
ENV VITE_API_URL=/api
RUN npm run build

# Etapa 2: Configurar Nginx
FROM nginx:alpine

# Copiar los estáticos compilados desde la etapa anterior al directorio que Nginx servirá
COPY --from=build-stage /app/dist /var/www/frontend

# (El archivo de configuración default.conf se mapea desde el docker-compose)