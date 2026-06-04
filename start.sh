#!/bin/bash

# Skrypt do budowania i uruchamiania aplikacji log-viewer w Dockerze

# --- Konfiguracja ---
APP_NAME="log-viewer"
IMAGE_NAME="log-viewer-img"
HOST_PORT="8123"
# --------------------

# Zatrzymaj i usuń istniejący kontener, jeśli działa
echo "==> Stopping and removing old container..."
docker stop ${APP_NAME} > /dev/null 2>&1
docker rm ${APP_NAME} > /dev/null 2>&1

# Zbuduj obraz Docker z Dockerfile
echo "==> Building Docker image..."
docker build -t ${IMAGE_NAME} .

# Uruchom nowy kontener
echo "==> Starting new container..."
docker run -d \
  --name ${APP_NAME} \
  -p ${HOST_PORT}:80 \
  -v "$(pwd)/logs:/var/www/html/logs" \
  -v "$(pwd)/data:/var/www/html/data" \
  -e LOG_DIR="/var/www/html/logs" \
  --rm \
  ${IMAGE_NAME} \
  sh -c "php-fpm -D && nginx -g 'daemon off;'"

# Sprawdź, czy kontener został uruchomiony poprawnie
if [ $(docker ps -q -f name=^/${APP_NAME}$) ]; then
    echo ""
    echo "================================================="
    echo "  Aplikacja została pomyślnie uruchomiona!"
    echo "  Kliknij link, aby otworzyć: http://localhost:${HOST_PORT}"
    echo "================================================="
    echo ""
else
    echo ""
    echo "!!! BŁĄD: Nie udało się uruchomić kontenera. Sprawdź logi powyżej. !!!"
    echo ""
fi
