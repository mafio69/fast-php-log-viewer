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
## UPDATE IN docker-fast-php-logger  
  
```shell
/var/www/html/vendor/mafio69/log-viewer/composer update
```
❭ Ta aplikacja ma wywietlać logi jak np php error itp, w momencie uruchamiania powinna wywietlic zapamietane jak baza pusta szukac w /var/logs  chociaż z swojego docker lub z hosta. Zobacz na co ten kod jest przygotowany . Tez ta aplikacja łączyła się przez ssh i czytała logi z zdalnych maszyn ,  ssh na dwa
sposoby mapowanie katalogu ~/.ssh lub hasła oba warianty logowania , używała też  datatable . Teraz się uruchamia i jest statyczna strona bez ssh wyszukiwania plików. Pliki do wywietlenia apka powinna pamietać oprócz domyslnych też , do tego ma słuzyć DuckDb ale w wersji jak sqllite . Estetyka teraz jest
czarna z buałymi napisami. Była czarna z zielonymi napisami jak w dawnych monitorach CRT 