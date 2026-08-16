# Przepływ Git — zasady dla agentów AI (uniwersalne, do kopiowania między repo)

Ten plik jest napisany tak, żeby dało się go skopiować 1:1 do innego repo i tylko
podmienić nazwy branchy/komend na lokalne konwencje, jeśli są inne niż `develop`/`master`.

## 1. Tożsamość commitów — AI podpisuje się sobą, nie właścicielem repo

Każdy commit, który robi agent AI, musi używać **własnej** tożsamości gita, nie
dziedziczyć tożsamości człowieka z lokalnego configu repo.

```bash
git commit --author="<Twoja nazwa/model> <adres>" -m "..."
# np.
git commit --author="Claude Sonnet 5 <noreply@anthropic.com>" -m "..."
```

- Bez `--author`, `git commit` domyślnie bierze lokalny `user.name`/`user.email` z configu
  repo — więc `git blame` pokazuje zmiany AI jako napisane przez człowieka. To
  nieuczciwe wobec niego i myli każdego, kto później czyta historię.
- Dodatkowo zostaw trailer na końcu treści commit message:
  ```
  Co-Authored-By: <Twoja tożsamość, np. Claude Sonnet 5 <noreply@anthropic.com>>
  ```
  GitHub pokazuje to jako drugiego współautora z osobnym avatarem na stronie
  commita/PR-a. Trailer **nie zastępuje** `--author` — używać obu naraz.
- **NIGDY nie zmieniaj `git config user.name`/`user.email`** (globalnie ani per-repo),
  żeby to osiągnąć. `--author` to jednorazowy override na pojedynczej komendzie, nie
  trwała zmiana configu — zmiana configu wpłynęłaby na WSZYSTKIE przyszłe commity
  w tym repo, także te robione ręcznie przez człowieka.
- Gdy commit wprowadza **zasadę/politykę ustaloną wspólnie z człowiekiem** (nie tylko
  kod, który AI samo zaprojektowało) — np. commit zmieniający ten właśnie plik —
  dopisz dodatkowy trailer z jego tożsamością, obok własnego:
  ```
  Co-Authored-By: Mariusz <mf1969@gmail.com>
  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
  ```
  To oddaje, że treść reguły pochodzi od niego (dyktowana w rozmowie), a AI tylko ją
  spisało i wdrożyło w plikach.

## 2. Git flow — obowiązkowy dla każdego zadania

1. **Nowy branch, lokalnie, z `develop`** (nigdy z `master`, nigdy ze starego brancha
   innego, już zamkniętego zadania).
2. Praca na tym branchu.
3. **Merge brancha do `develop` — lokalnie** (`git merge`, nie przez PR na GitHubie —
   `develop` nie wymaga PR-a, tylko `master` go wymaga, patrz punkt 4).
4. `git push origin develop`.
5. CI (GitHub Actions albo odpowiednik) odpala testy na `develop`.
6. **Jeśli zielono → PR `develop` → `master`.** `master` przyjmuje kod **wyłącznie
   przez PR** (branch protection / ruleset to wymusza — brak bezpośrednich pushy,
   nawet fast-forward). Merge PR-a dopiero po przejściu wymaganych statusów NA TYM
   PR-cie (nie wystarczy, że przeszły na `develop` — PR triggeruje je od nowa).
7. **Jeśli PR do mastera zielony → deploy** z `master` (ręczny trigger albo
   automatyczny po zielonym merge, zależnie od repo).
8. **Po udanym deployu branch zadania jest usuwany** (lokalnie i na `origin`, jeśli
   był pushowany).

### Zasada nadrzędna

**Bezpośrednia praca na `develop` jest niedozwolona.** Zawsze nowy branch, zawsze
z aktualnego `develop` (`git fetch`, potem branch z `origin/develop`, nie ze
starej lokalnej kopii).

### Jedyny wyjątek

Zadanie oznaczone przez człowieka jako pilne/priorytetowe (w DevBrain: priorytet
`fire` na tablicy TODO) — wtedy wolno pracować bezpośrednio na `develop`, bez
osobnego brancha, żeby nie tracić czasu na ceremonię przy prawdziwie pilnej sprawie.

- **Tę flagę nadaje wyłącznie człowiek**, nigdy AI samo sobie.
- AI, które widzi zadanie bez tej flagi, nie ustawia jej samo, żeby obejść zasadę
  "zawsze branch z develop" — to obejście całego sensu tej reguły.
- W repo, które nie ma koncepcji "priorytetu zadania" (np. brak systemu TODO), ten
  wyjątek można zastąpić dowolnym innym jawnym, jednoznacznym sygnałem od człowieka
  w tej samej rozmowie ("rób to bezpośrednio na develop, wiem co robię") — ale
  domyślne zachowanie bez takiego sygnału to zawsze branch.

## 3. Ochrona brancha `master` — co musi być skonfigurowane w repo

Żeby punkt 6 powyżej faktycznie coś znaczył (a nie był tylko dobrą wolą), branch
`master` musi mieć w ustawieniach repo (GitHub: Settings → Rules → Rulesets, albo
klasyczne Branch protection):

- zakaz bezpośredniego push/force-push/usunięcia brancha,
- wymóg PR-a przed mergem,
- wymagane status checki (testy) — **uwaga**: nie dodawaj do wymaganych checków
  jobów z filtrami `paths`, które nie zawsze się odpalają (np. workflow, który
  triggeruje się tylko przy zmianie plików Dockera) — required-status-check, który
  nigdy nie zaraportuje statusu, blokuje merge NA ZAWSZE. To samo dotyczy wymogów
  w rodzaju "Code Scanning" (CodeQL), jeśli faktyczny skan nie jest w repo włączony —
  PR wisi w nieskończoność czekając na wynik, którego nigdy nie będzie.
- jeśli reguła celuje w "domyślny branch repo" (np. `~DEFAULT_BRANCH` na GitHubie)
  zamiast w jawną nazwę (`refs/heads/master`) — **sprawdź, który branch jest faktycznie
  domyślny** (`gh api repos/<owner>/<repo> -q .default_branch`). Zmiana domyślnego
  brancha po fakcie po cichu przesuwa cel reguły na inny branch, co wygląda jak
  reguła przestała działać, a naprawdę zaczęła chronić coś innego.