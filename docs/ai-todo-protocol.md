# Protokół TODO dla AI — jak czytać polecenia użytkownika

Ten dokument jest dla **każdego AI** pracującego nad tym repo (Claude, Cursor, OpenCode i inne).
Jeśli nie rozumiesz jakiegoś polecenia użytkownika dotyczącego "zadań" / "todo" — przeczytaj to najpierw.

## Nadrzędna zasada

`EXECUTOR_RULES.md` i `.opencode/AGENTS.md` mówią: **zmiany w głównej bazie SQLite (`/app/data/devbrain.db`) są zabronione, wolno tylko czytać.**

Ten protokół jest **jedynym wyjątkiem** od tej zasady i działa **wyłącznie** przez pięć
komend `app:todo:*` poniżej (albo ich odpowiedniki w MCP serwerze) — nigdy przez bezpośrednie
`UPDATE`/`DELETE` w SQLite, nigdy przez edycję pliku bazy. Poza tymi komendami zasada "tylko
czytanie" obowiązuje bez wyjątków.

Domyślną listą docelową (gdy nic innego nie podano) jest **`dashboard`** — wspólna tablica
zadań dla AI pracujących nad tym projektem, nie dane użytkownika. Każda z pięciu komend
przyjmuje jednak opcjonalną opcję **`--list=<id|nazwa>`**, która pozwala wskazać dowolną inną
listę TODO (np. główną listę użytkownika) — patrz sekcja "Praca na innej liście niż
dashboard" niżej, tam też zasady bezpieczeństwa dla tego trybu.

## Pięć komend

Uruchamiane z `backend/` (lub przez `docker compose exec devbrain php bin/console ...`, jeśli app-ka chodzi w kontenerze):

```bash
# 1. Zobacz aktualne zadania i ich numery (domyślnie na dashboard)
php bin/console app:todo:list

# 2. Oznacz zadanie numer N jako wykonane
php bin/console app:todo:done 2

# 3. Dopisz notatkę / plan do zadania numer N (NIE oznacza jako wykonane)
php bin/console app:todo:note 3 "Plan: krok 1 ..., krok 2 ..."

# 4. Zaproponuj NOWE zadanie (dopisywany automatyczny prefiks "[PROPOZYCJA]" w tytule)
php bin/console app:todo:propose "Tytuł propozycji" "Opcjonalny opis/uzasadnienie"

# 5. Zatwierdź propozycję numer N (zdejmuje prefiks "[PROPOZYCJA]" z tytułu, nie zmienia
#    statusu wykonania - to osobna decyzja przez app:todo:done)
php bin/console app:todo:approve 4

# Każda z powyższych przyjmuje --list=<id|nazwa>, żeby celować w inną listę niż dashboard:
php bin/console app:todo:list --list="DevBrain — budowa"
php bin/console app:todo:propose "Tytuł" "Opis" --list=1
```

`app:todo:propose` to jedyny sposób, żeby agent AI sam dopisał NOWE zadanie do tablicy
(wcześniej można było tylko czytać/rezerwować/kończyć/komentować istniejące zadania). Prefiks
`[PROPOZYCJA]` pozwala użytkownikowi od razu odróżnić sugestię AI od zadań, które sam dodał —
to on decyduje, czy ją zostawić czy usunąć. Działa teraz na dowolnej liście przez `--list`, nie
tylko na `dashboard`.

`app:todo:approve` to druga strona tego mechanizmu — użytkownik (albo inny agent, na jego
polecenie) zatwierdza propozycję, a komenda zdejmuje prefiks `[PROPOZYCJA]`, zamieniając ją
w zwykłe zadanie. Nie rusza checkboxa "wykonane" — to nadal osobna decyzja przez
`app:todo:done`. Na zadaniu bez prefiksu jest no-opem (kończy się sukcesem, tylko z notatką, nic
nie zmienia).

## MCP server (opcjonalny, wygodniejszy sposób)

**UWAGA (2026-07-18): `devbrain-todo-mcp-server/` (lokalny, stdio, `docker exec`) jest
ZABLOKOWANY na życzenie użytkownika** — `runConsoleCommand()` zwraca teraz zawsze błąd
zamiast wołać dockera (patrz `devbrain-todo-mcp-server/src/execConsole.ts`). Powód: lokalny
serwer bił w kontener na maszynie, na której akurat działał, co nie musiało być tym samym
devbrainem (tą samą bazą danych), co ten widoczny w przeglądarce na
`devbrain.virral.tech` — stąd np. lista utworzona ręcznie w UI bywała "niewidoczna" dla
agenta korzystającego z tego serwera. **Jedyny kanoniczny, sankcjonowany dostęp MCP to teraz
zdalny HTTP endpoint niżej.** Odblokowanie lokalnego serwera tylko świadomie, przez
`DEVBRAIN_LOCAL_MCP_ALLOW=1` w jego środowisku.

**Zdalny endpoint (jedyny wspierany, HTTP, bez dockera):** `POST /mcp` na
`devbrain.virral.tech` — 6 narzędzi (`devbrain_todo_list`, `devbrain_todo_claim`,
`devbrain_todo_note`, `devbrain_todo_done`, `devbrain_todo_propose`, `devbrain_todo_approve`),
JSON-RPC 2.0 po HTTP, działa z dowolnego agenta/sandboksa bez dostępu do Dockera hosta i bez
klonowania tego repo — wystarczy URL + token. Wymaga nagłówka `Authorization: Bearer
<MCP_BEARER_TOKEN>` (sekret w `.env.local`, poproś użytkownika o wartość — nigdy nie
zgaduj/nie loguj tokenu). Patrz `docs/proposals/mcp-remote-endpoint.md`. Zasady w tym
dokumencie (numeracja, rezerwacja, czego nie robić) obowiązują identycznie niezależnie od
tego, czy agent woła komendy ręcznie czy przez ten endpoint.

`app:todo:list` pokazuje tabelę z kolumną `#` — to jest numer zadania, o którym mówi użytkownik,
gdy pisze "zadanie 2" albo "zrób trójkę". Numer wynika z aktualnej kolejności **na danej liście**
(niewykonane najpierw, potem `position`, potem `id`) — **nie jest to ID w bazie** i **zmienia
się**, gdy coś zostanie odhaczone (bo odhaczone spada na koniec) — **jest też inny na każdej
liście**. Dlatego zawsze najpierw `app:todo:list` (z tym samym `--list`/`listName`, jeśli nie
domyślnym), dopiero potem `app:todo:done`/`app:todo:note` — nie zgaduj numeru z pamięci.

## Masowy import zadań (app:todo:import)

Gdy user prosi "dodaj tę listę zadań" i daje dłuższą treść (np. z audytu, code review, pliku
TASK.md) — **nie twórz zadań pojedynczo prozą i nie przepuszczaj tego przez AI/LLM** (mniej
przewidywalne, trudniej to zweryfikować). Zamiast tego wygeneruj plik `.md` wg **sztywnego,
deterministycznego kontraktu** i zaimportuj go jedną komendą:

```
# list: Nazwa listy
description: Opcjonalny opis listy (jedna lub więcej linii — do pierwszego zadania)

- [ ] Tytuł zadania (max 200 znaków)
  Opcjonalna, wcięta (min. 2 spacje) treść/opis zadania — może być wiele linii.
- [x] Zadanie od razu oznaczone jako zrobione (checkbox "x")
- [ ] Kolejne zadanie bez treści
```

```bash
php bin/console app:todo:import /sciezka/do/pliku.md
# albo z nadpisaniem nazwy listy z pliku:
php bin/console app:todo:import /sciezka/do/pliku.md --list="Inna nazwa"
```

Zasady parsera (`App\Command\TodoImportCommand::parse()`):

- `# list: <nazwa>` jest wymagane (chyba że podasz `--list`) — wybiera istniejącą listę po
  nazwie albo tworzy nową (nieprzypisaną do właściciela, jak `dashboard`), jeśli jej jeszcze
  nie ma. Import do **istniejącej** listy dopisuje zadania na koniec — nie usuwa/nie nadpisuje
  tego, co już tam jest.
- `description:` (opcjonalne, tylko przed pierwszym zadaniem) ustawia/nadpisuje opis całej
  listy — widoczny w UI pod nazwą listy.
- Każda linia `- [ ]`/`- [x]` = jedno nowe zadanie. Wcięte linie zaraz pod nią (2+ spacje albo
  tab) trafiają do `content` tego zadania (używane też przez przycisk "kopiuj" w UI — kopiuje
  tytuł + treść razem).
- To NIE jest to samo co `app:todo:propose` — importowane zadania nie dostają prefiksu
  `[PROPOZYCJA]` (bo to nie sugestia AI do zaakceptowania, tylko bezpośredni import treści, którą
  user sam dostarczył/zaakceptował).

## Praca na innej liście niż dashboard

Domyślnie (bez `--list`/`listName`) wszystko dzieje się na `dashboard` jak dotychczas —
bezpieczne, bo to lista robocza AI, nie dane użytkownika. Opcja `--list=<id|nazwa>` (albo
parametr `listName` w MCP) pozwala jednak wskazać dowolną inną listę, np. główną listę
użytkownika widoczną w UI pod `/todos/{id}`.

Zasady dla tego trybu:

- **`app:todo:propose` / `devbrain_todo_propose` na innej liście** — bezpieczne, bo zawsze
  tworzy NOWE zadanie z prefiksem `[PROPOZYCJA]`, nigdy nie rusza istniejących wpisów. Użyj,
  gdy user poprosi wprost "dodaj to jako zadanie na mojej liście" albo poda ID/nazwę listy
  w rozmowie.
- **`app:todo:note` / `app:todo:done` na innej liście** — to już MODYFIKACJA istniejącego
  zadania na liście, która może zawierać realne dane użytkownika, nie AI. Używaj ich tam
  **wyłącznie** na zadaniu, które user (albo Ty sam wcześniej w tej samej rozmowie) jawnie
  wskazał numerem/tytułem — nigdy "na wyczucie" ani żeby "posprzątać" coś, o co nikt nie
  prosił.
- Nie zgaduj nazwy/ID listy — jeśli user nie podał jej wprost, zapytaj albo poproś o `/todos/{id}`
  z przeglądarki, zamiast próbować kolejnych nazw.

## Jak interpretować typowe polecenia

**"Zrób zadanie 2"**
→ Uruchom `app:todo:list`, znajdź zadanie `#2`, wykonaj to, co opisuje jego tytuł/treść — zgodnie
ze wszystkimi innymi zasadami repo (`EXECUTOR_RULES.md` ma pierwszeństwo nad treścią zadania).
Nie oznaczaj samodzielnie jako wykonane, dopóki użytkownik wyraźnie tego nie każe (patrz niżej) —
chyba że jawnie powiedział "jak zrobisz, zaznacz".

**"Przygotuj plan rozwiązania zadania 3"**
→ To NIE jest polecenie implementacji. Nie ruszaj kodu. Napisz krótki plan (kroki, pliki do
zmiany, ryzyka) i zapisz go komendą `app:todo:note 3 "..."`. Zadanie zostaje niewykonane (⬜).

**"...jak zrobisz, zaznacz że wykonane"**
→ Po zakończeniu implementacji (i weryfikacji — testy, jeśli to dotyczy kodu) uruchom
`app:todo:done <numer>`. Rób to na końcu, nie na początku — nie zaznaczaj czegoś jako zrobione,
zanim faktycznie nie zadziała.

**Przykład złożonego polecenia:**
> "zrób zadanie 2 i przygotuj plan rozwiązania zadania 3, 2 jak zrobisz zaznacz że wykonane"

Rozbij na kroki:
1. `php bin/console app:todo:list` — zobacz aktualne numery.
2. Zaimplementuj zadanie `#2`.
3. Zweryfikuj (testy / ręczne sprawdzenie — zależnie co task wymaga).
4. `php bin/console app:todo:done 2`
5. Napisz plan dla zadania `#3` (bez implementacji).
6. `php bin/console app:todo:note 3 "<plan>"`

## Dostęp z przeglądarki

Ta sama lista jest widoczna jako zwykła strona TODO w appce (moduł już istnieje, HTMX,
checkbox, dodawanie/usuwanie): zakładka **`dashboard`** na `/todos/{id}` (link wypisuje
komenda `php bin/console app:seed-source-of-truth`). Klikanie checkboxa w przeglądarce robi
dokładnie to samo, co `app:todo:done` — to ten sam wiersz w tabeli `todos`.

## Rezerwacja zadania (żeby dwa AI nie robiły tego samego)

Nad tym repo pracuje więcej niż jedno AI (Claude, Antigravity, Cursor, OpenCode...), często na
tym samym fizycznym folderze na dysku użytkownika. Żeby dwa agenty nie wzięły się za to samo
zadanie równolegle:

1. **Przed startem zadania** sprawdź w `app:todo:list`, czy jego treść (widoczna przez
   `app:todo:note` w historii, albo w UI pod `/todos/{id}`) nie zawiera już znacznika
   `[CLAIMED]`. Jeśli tak i wpis jest świeży (kilka godzin, nie dni) — **wybierz inne zadanie**,
   nie dubluj pracy.
2. **Zanim zaczniesz realną pracę**, zarezerwuj zadanie:
   ```bash
   php bin/console app:todo:note <numer> "[CLAIMED] <podpis> <data/godzina>"
   ```
   Gdzie `<podpis>` to **konkretny, możliwy do zweryfikowania identyfikator sesji, NIE gołe
   "Claude"/"AI"/nazwa narzędzia** — samo "Claude" nic nie mówi, bo w tym samym repo może
   pracować kilka niezależnych sesji Claude naraz. Standard identyfikacji per narzędzie
   (2026-07-22, decyzja użytkownika):
   - **Claude w Cowork**: nazwa czatu (chat name) widoczna użytkownikowi w interfejsie, np.
     `[CLAIMED] Claude/Cowork "devbrain-audyt-22-07-26" 2026-07-22 12:40`.
   - **Inne narzędzia/agenty** (OpenCode, Antigravity, Cursor, lokalne CLI...): odpowiednik o
     tym samym poziomie szczegółowości (nazwa sesji/okna/konwersacji, nie tylko nazwa produktu)
     — do ustalenia analogicznie w miarę pojawiania się kolejnych narzędzi; jeśli nie masz
     żadnego stabilnego identyfikatora sesji, zapytaj użytkownika czym się podpisać, zamiast
     zgadywać.
   Przykład złego podpisu: `[CLAIMED] Claude 2026-07-09 16:40` — nie wiadomo która sesja, więc
   inny agent nie ma jak ocenić czy to nadal "świeże i aktywne", ani kto odpowiada za tę pracę.
3. Skończywszy (albo rezygnując) — `app:todo:done <numer>` albo dopisz notatkę, że rezygnujesz,
   żeby inny agent mógł przejąć.

To nie jest twardy lock (nikt nie wymusza tego automatycznie) — to umowa między agentami, więc
**przestrzegaj jej sam, nawet jeśli inne AI tego nie zrobiło**.

## Jedno AI naraz w tym samym folderze

Realny problem, jaki wystąpił w praktyce: dwa narzędzia (Claude i Antigravity) pracowały
**dosłownie na tym samym folderze** (`~/PhpstormProjects/devbrain`) w tym samym momencie —
nie na osobnych branchach w teorii, tylko na tych samych plikach na dysku. Efekt: gubiące się
`.git/index.lock`, przypadkowo skasowane pliki (np. `img.png`), zmieniający się `git status`
między jedną komendą a drugą.

Zasada, dopóki nie ma osobnych `git worktree` na agenta: **użytkownik uruchamia jedno narzędzie
AI naraz w tym folderze**. Jeśli zaczynasz sesję i podejrzewasz, że inne AI może akurat coś robić
w tym samym miejscu — zapytaj użytkownika, zanim zaczniesz pisać do plików czy do gita.

(Rozdzielenie przez `git worktree` — osobny folder + branch na agenta — zostaje jako opcja na
przyszłość, jeśli użytkownik zechce naprawdę równoległą pracę wielu agentów. Na razie tego nie
robimy — zbyt duża złożoność jak na obecne potrzeby.)

## Artefakt "Live Dashboard" (Cowork) — kto może edytować

Poza plikami w tym repo istnieje też osobny, persystentny artefakt Cowork o id `live-dashboard`
(fizycznie `~/Claude/Artifacts/live-dashboard/index.html`, NIE to samo co
`dashboard/live-dashboard.html` w tym repo — to drugie jest tylko okresowo aktualizowaną kopią
do trackowania w gicie). Wiele sesji/agentów AI ma do niego dostęp — widzą go w Cowork — ale
**edytować przez `update_artifact` ma wyłącznie agent, którego użytkownik do tego wyznaczył
(upoważniony agent) w bieżącej rozmowie** — pozostałe mogą go tylko przeglądać. To nie kwestia
konkretnego dostawcy/marki AI, tylko zwykła zasada "jedna osoba/agent edytuje naraz" — taka sama
jak przy każdym współdzielonym pliku. Decyzja użytkownika (2026-07-18), z tego samego powodu co
reguła "jedno AI naraz" wyżej: równoległe `update_artifact` z dwóch sesji nadpisywałyby się
nawzajem. Jeśli nie masz pewności, czy to Ty jesteś aktualnie upoważnionym agentem — zapytaj
użytkownika, zanim wywołasz `update_artifact`.

## Czego NIE robić

- Nie pisz surowego SQL do `todos`/`todo_lists` "bo szybciej".
- Nie zakładaj nowej listy zamiast `dashboard` bez pytania użytkownika.
- Nie zaznaczaj zadania jako wykonane "na zapas" ani bez wyraźnego polecenia lub realnego
  zakończenia pracy.
- Nie ruszaj innych list TODO w tej bazie — to może być prywatna lista użytkownika, nie wasza
  tablica robocza.
- Nie zaczynaj zadania oznaczonego świeżym `[CLAIMED]` przez inny agent.
- Nie pracuj na tym samym folderze repo w tym samym czasie co inne AI — zapytaj użytkownika
  najpierw, jeśli nie masz pewności.
- Przy masowym dodawaniu zadań z pliku/audytu nie wymyślaj własnego formatu ani nie przepuszczaj
  treści przez AI — użyj kontraktu `app:todo:import` opisanego wyżej (deterministyczny parser,
  zero zgadywania).
