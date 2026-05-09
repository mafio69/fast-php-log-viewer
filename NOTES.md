# Notatki

## Composer update w kontenerze Docker

```sh
docker compose exec php bash
cd /var/www/html/vendor/mafio69/fast-php-logger
composer update --no-interaction
chmod -R 755 /var/www/html/vendor
```

## Usuwanie plików przez Docker (gdy brak uprawnień lokalnie)

```sh
docker compose exec php rm /var/www/html/<ścieżka_do_pliku>
```
