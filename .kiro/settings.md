# Kiro Settings

## MotherDuck (baza cache)

Na starcie połącz się z MotherDuck i zapoznaj się z danymi w `my_db`.

Dane połączenia w pliku: `/home/m.franciszczak@sellasist.pl/docker-fast-php-logger/.env`

Komenda:
```sh
duckdb "md:my_db?motherduck_token=<DUCK_TOKEN z .env>"
```

Tabele:
- `user_profile` — profil użytkownika (Mariusz)
- `projects` — lista projektów fast-php-*
- `glossary` — słowniczek angielskich terminów programistycznych
