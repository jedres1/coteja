# Despliegue a producción

Guía para publicar los cambios de este proyecto en el servidor usado el 22 de octubre de 2026.

## Destino

- Repositorio: `origin` (`https://github.com/jedres1/coteja.git`)
- Rama: `main`
- Servidor: `129.80.200.19`
- Usuario SSH: `ubuntu`
- Directorio de la aplicación: `/var/www/coteja`
- URL verificada: `http://129.80.200.19`
- Llave local: `~/Downloads/ssh-key-2026-10-21 (2).key`

No guardes la llave privada dentro del repositorio ni la imprimas en la terminal. La copia temporal debe tener permisos `600` y eliminarse al terminar.

## Publicar cambios

1. Revisa los cambios y crea un commit en `main`.
2. Compila los assets localmente y sube el commit:

   ```sh
   npm run build
   git push origin main
   ```

   El servidor no tenía Node instalado durante el último despliegue; por eso se compilaron los assets localmente y se copiaron a producción.

3. Prepara una copia temporal protegida de la llave y actualiza el código y los assets:

   ```sh
   DEPLOY_KEY="$HOME/Downloads/ssh-key-2026-10-21 (2).key"
   DEPLOY_KEY_COPY="$(mktemp /tmp/coteja-deploy-key.XXXXXX)"
   install -m 600 "$DEPLOY_KEY" "$DEPLOY_KEY_COPY"
   trap 'rm -P "$DEPLOY_KEY_COPY"' EXIT

   ssh -o BatchMode=yes -i "$DEPLOY_KEY_COPY" ubuntu@129.80.200.19 \
     'cd /var/www/coteja && git -c core.filemode=false pull --ff-only origin main'

   rsync -avz -e "ssh -o BatchMode=yes -i $DEPLOY_KEY_COPY" \
     public/build/ ubuntu@129.80.200.19:/var/www/coteja/public/build/
   ```

   `core.filemode=false` evita que los cambios de permisos en los `.gitignore` de `storage` y `bootstrap/cache` bloqueen el fast-forward. No reviertas esos cambios a la fuerza.

4. Aplica migraciones y reconstruye las cachés de Laravel:

   ```sh
   ssh -o BatchMode=yes -i "$DEPLOY_KEY_COPY" ubuntu@129.80.200.19 \
     'cd /var/www/coteja && php artisan migrate --force && php artisan optimize:clear && sudo -n -u www-data php artisan optimize'
   ```

   Ejecuta `optimize` como `www-data`: ese usuario es dueño de `storage` y `bootstrap/cache`. No cambies esos directorios a `ubuntu` ni ejecutes `optimize` como root.

5. Verifica commit, migración y respuesta HTTP:

   ```sh
   ssh -o BatchMode=yes -i "$DEPLOY_KEY_COPY" ubuntu@129.80.200.19 \
     'cd /var/www/coteja && git rev-parse --short HEAD && sudo -n -u www-data php artisan migrate:status'
   ssh -o BatchMode=yes -i "$DEPLOY_KEY_COPY" ubuntu@129.80.200.19 \
     'curl -sS -L -o /dev/null -w "HTTP %{http_code}\\n" --max-time 20 http://127.0.0.1/login'
   ```

   `/login` debe responder `HTTP 200`. Confirma que las migraciones nuevas aparezcan como `Ran`.

## Notas

- El conector SSH y la llave se probaron correctamente desde el equipo local.
- Si cambia la llave, el host, el usuario, el directorio o la URL, actualiza esta guía después de confirmar los nuevos datos.
- La extracción de facturas usa la conexión Laravel `background`. Si ese mecanismo o la infraestructura cambia, verifica también la ejecución de un trabajo antes de cerrar el despliegue.
