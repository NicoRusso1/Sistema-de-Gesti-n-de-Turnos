FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build -- --configuration production

FROM nginx:1.27-alpine

ENV PORT=80 \
    API_URL=http://localhost:8000/api

COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/40-config.sh /docker-entrypoint.d/40-config.sh
RUN sed -i 's/\r$//' /docker-entrypoint.d/40-config.sh && chmod +x /docker-entrypoint.d/40-config.sh

COPY --from=build /app/dist/frontend/browser /usr/share/nginx/html

EXPOSE 80
