# Jak podłączyć się do MCP DevBrain TODO (jedyny wspierany sposób)

Ten plik jest dla **każdego narzędzia/agenta AI** (Claude Code, opencode, Cursor, Antigravity,
Claude.ai...), które ma dostać dostęp do tablicy zadań DevBrain przez MCP. Jeśli konfigurujesz
to pierwszy raz albo coś nie działa — czytaj od góry, nie zgaduj.

## Jedna zasada

**Jest tylko jeden wspierany sposób: zdalny endpoint HTTP.** Lokalny serwer
(`devbrain-todo-mcp-server/`, stdio, `docker exec`) jest **celowo zablokowany**
(`runConsoleCommand()` zawsze zwraca błąd) — powód: bił w kontener na maszynie, na której akurat
działał, co nie musiało być tym samym devbrainem (tą samą bazą danych) co widoczny w
przeglądarce. Jeśli gdzieś zobaczysz config wskazujący na `dist/index.js` przez `docker exec` —
to jest zły config, zamień go na poniższy.

## Czego potrzebujesz

1. **URL:** `https://devbrain.virral.tech/mcp`
2. **Token:** wartość zmiennej `MCP_BEARER_TOKEN` z `.env` na serwerze devbraina — **poproś o nią
   użytkownika, nigdy nie zgaduj i nie wymyślaj.** To jeden, stały, wspólny sekret (nie ma osobnych
   tokenów per klient/agent) — kto go ma, ma pełny dostęp do odczytu i zapisu tablicy.
   - Uważaj przy kopiowaniu z terminala: żaden dodatkowy znak na końcu (np. `>`) nie należy do
     tokena — prawdziwy token to czysty ciąg hex, nic więcej.

## Szybki test (zanim będziesz konfigurować klienta)

```bash
curl -s https://devbrain.virral.tech/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

Poprawna odpowiedź: JSON z listą 7 narzędzi (patrz niżej). `401` = zły/pusty token. Jeśli dostajesz
`401` mimo poprawnego tokena — zapytaj użytkownika, czy `MCP_BEARER_TOKEN` jest w ogóle ustawiony
na serwerze (puste = endpoint zawsze odrzuca, to celowe, fail-closed).

## Konfiguracja klienta

### Claude Code (CLI)

```bash
claude mcp add --transport http devbrain-todo https://devbrain.virral.tech/mcp \
  --header "Authorization: Bearer <TOKEN>"
```

Albo ręcznie w `.mcp.json` / `~/.claude.json`:

```json
{
  "mcpServers": {
    "devbrain-todo": {
      "type": "http",
      "url": "https://devbrain.virral.tech/mcp",
      "headers": {
        "Authorization": "Bearer <TOKEN>"
      }
    }
  }
}
```

### opencode

```json
"devbrain-todo": {
  "type": "remote",
  "enabled": true,
  "url": "https://devbrain.virral.tech/mcp",
  "headers": {
    "Authorization": "Bearer <TOKEN>"
  }
}
```

**Nie** `"type": "local"` z `command`/`docker exec` — to stary, zablokowany config.

### Dowolny inny klient wspierający MCP-over-HTTP

Transport: JSON-RPC 2.0 przez HTTP POST, `Content-Type: application/json`, bez SSE/sesji
(każde zapytanie jest samodzielne). Nagłówek `Authorization: Bearer <TOKEN>` wymagany na
każdym żądaniu.

## Dostępne narzędzia (8)

| Narzędzie | Co robi | Wymagane argumenty |
|---|---|---|
| `devbrain_todo_lists` | Wypisuje WSZYSTKIE listy TODO (ID, nazwa, otwarte/wszystkie zadania) | — |
| `devbrain_todo_list` | Wypisuje zadania z JEDNEJ listy (domyślnie `DevBrain — budowa`) | — |
| `devbrain_todo_claim` | Rezerwuje zadanie o numerze N (dopisuje `[CLAIMED] agent data`) | `number`, `agentName` |
| `devbrain_todo_note` | Dopisuje notatkę/plan do zadania N | `number`, `note` |
| `devbrain_todo_done` | Oznacza zadanie N jako wykonane | `number` |
| `devbrain_todo_propose` | Dodaje NOWE zadanie z prefiksem `[PROPOZYCJA]` | `title` |
| `devbrain_todo_approve` | Zdejmuje prefiks `[PROPOZYCJA]` z zadania N (zatwierdza) | `number` |
| `devbrain_todo_set_priority` | Ustawia priorytet zadania N: `fire`/`high`/`mid`/`low`/`none` | `number`, `priority` |

Każde narzędzie przyjmuje opcjonalny `listName` (ID albo dokładna nazwa listy) — bez niego
działa na domyślnej liście `DevBrain — budowa`.

**Co to jest `number`.** To REALNE, stałe ID zadania w bazie (`Todo::getId()`) — nie pozycja w
wyliczeniu, więc się nie przesuwa, kiedy ktoś (Ty albo inny agent) oznaczy inne zadanie jako
zrobione. `devbrain_todo_list` wypisuje je w formacie `listId-taskId` (np. `21-89`) — możesz
przekazać do `number` **albo cały ten zapis, albo samo ID zadania** (`89`), oba działają
identycznie. Zawsze najpierw wywołaj `devbrain_todo_list` z tym samym `listName`, żeby dostać
aktualny numer, zanim użyjesz go w innym narzędziu.

### Przykłady wywołań (`tools/call`)

Każde wywołanie to POST na `/mcp` z ciałem `{"jsonrpc":"2.0","id":<dowolne>,"method":"tools/call","params":{"name":"<narzędzie>","arguments":{...}}}`.
Poniżej tylko `params` dla czytelności.

**`devbrain_todo_lists`** — wszystkie listy (bez argumentów) — użyj tego, żeby znaleźć ID/nazwę
listy, zanim podasz ją jako `listName` gdzie indziej:
```json
{"name": "devbrain_todo_lists", "arguments": {}}
```

**`devbrain_todo_list`** — lista zadań (domyślna albo wskazana):
```json
{"name": "devbrain_todo_list", "arguments": {"listName": "DevBrain — budowa"}}
```

**`devbrain_todo_claim`** — rezerwacja zadania `21-89` przez agenta „Claude”:
```json
{"name": "devbrain_todo_claim", "arguments": {"number": "21-89", "agentName": "Claude"}}
```

**`devbrain_todo_note`** — dopisanie notatki/planu do zadania `89` (samo ID też działa):
```json
{"name": "devbrain_todo_note", "arguments": {"number": 89, "note": "Plan: najpierw backend, potem UI"}}
```

**`devbrain_todo_done`** — oznaczenie zadania jako wykonane:
```json
{"name": "devbrain_todo_done", "arguments": {"number": "21-89"}}
```

**`devbrain_todo_propose`** — nowa propozycja zadania (dostaje prefiks `[PROPOZYCJA]` automatycznie):
```json
{"name": "devbrain_todo_propose", "arguments": {"title": "Dodać cache do widoku listy", "description": "Wolne przy >200 zadaniach"}}
```

**`devbrain_todo_approve`** — zatwierdzenie propozycji (zdejmuje prefiks `[PROPOZYCJA]`):
```json
{"name": "devbrain_todo_approve", "arguments": {"number": 90}}
```

**`devbrain_todo_set_priority`** — ustawienie/usunięcie priorytetu istniejącego zadania:
```json
{"name": "devbrain_todo_set_priority", "arguments": {"number": 90, "priority": "fire"}}
```
`priority` przyjmuje `fire`/`high`/`mid`/`low`/`none` (`none` usuwa priorytet). Błędna wartość
zwraca `isError: true` z komunikatem w `content[0].text`, nie JSON-RPC error.

Odpowiedź na każde `tools/call` ma kształt `{"result":{"content":[{"type":"text","text":"..."}],"isError":false}}`
— `isError: true` znaczy, że komenda konsolowa zwróciła niezerowy exit code (np. zły numer,
zła lista, zadanie już `cancelled`); treść błędu jest w `content[0].text`.

Pełne zasady zachowania (rezerwacja zadań, kiedy tworzyć `[PROPOZYCJA]`, czego nie robić na
cudzych listach) są w [`docs/ai-todo-protocol.md`](ai-todo-protocol.md) — przeczytaj go, zanim
zaczniesz cokolwiek zmieniać na tablicy, nie tylko podłączać klienta.

## Typowe błędy

- **401 Unauthorized** — zły token, pusty `MCP_BEARER_TOKEN` na serwerze, albo dodatkowy znak
  wklejony razem z tokenem (patrz sekcja "Czego potrzebujesz").
- **Narzędzie widoczne w `tools/list`, ale `tools/call` mówi "Nieznane narzedzie"** — literówka
  w nazwie narzędzia albo używasz starej wersji configu z inną listą narzędzi.
- **`devbrain_todo_list` pokazuje pustą/inną listę niż w przeglądarce** — sprawdź `listName`;
  bez niego trafiasz na domyślną listę `DevBrain — budowa`, a użytkownik mógł akurat patrzeć
  na inną zakładkę w UI. Zawsze podawaj `listName` jawnie, jeśli user wskazał konkretną listę.
- **Config wskazuje na `docker exec`/`dist/index.js`** — to zablokowany lokalny serwer, zamień
  na zdalny endpoint z tego pliku.
