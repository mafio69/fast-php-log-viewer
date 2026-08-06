<!-- Language selector -->
<div style="margin-bottom:12px;">
  <a href="#en" style="display:inline-block;padding:6px 10px;margin-right:8px;background:#0a84ff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">EN</a>
  <a href="#pl" style="display:inline-block;padding:6px 10px;background:#2ea44f;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">PL</a>
</div>


<a id="en"></a>
## Our Engineering Manifesto
---

This is more than just a set of rules — it is my manifesto. It defines who I am as a programmer, what I value, and how I build software. It is a living document that evolves with me, but its core principles remain my guide.

I believe in craftsmanship, pragmatism, and continuous improvement. I strive to create software that is not only functional, but also elegant, maintainable, and genuinely pleasant to work with and read. That matters to me, and it should not be forgotten. I also believe that imperfection is part of being human — and, in my view, that is a strength, not a flaw. I write this for myself, and maybe it will be useful to someone else too.

## 1. Security First: A Non-Negotiable Principle

Before any line of code is written, we acknowledge that security is our highest priority. It is not a feature or an afterthought; it is the foundation upon which we build everything.

*   **No Hardcoded Secrets:** We have a zero-tolerance policy for sensitive data (API keys, passwords, tokens) in our codebase. Such credentials must never be committed to version control.
*   **Configuration via Environment:** All configuration, especially secrets, must be loaded from environment variables (e.g., via `.env` files). We ensure that our `.gitignore` files are always configured to exclude these files.
*   **Data Masking:** We are vigilant about protecting data in logs and error reports. All sensitive information must be masked or omitted to prevent accidental exposure.

---

## 2. Core Philosophy: Code Design & Architecture

Our philosophy is built on a foundation of proven principles that ensure the quality, longevity, and robustness of our code.

### 2.1 SOLID Principles & The "AND" Test

We strictly adhere to SOLID principles, especially the Single Responsibility Principle (SRP). A simple test for this: if you describe what a class or method does and need to use the word "AND," it's a sign it's doing too much and must be split.

*Example:* A service that "validates input AND sends an email" should be two separate services. A method that "hashes a password AND logs the attempt" should be two methods.

### 2.2 The Art of Simplicity

We believe the best code is the code that is easiest to delete. Simplicity and readability are paramount. To achieve this, we follow a few pragmatic heuristics:

*   **"One Glance" Rule:** Methods should be short and focused enough to be understood in a single glance, without needing to scroll.
*   **Readable Conditionals:** Complex `if` statements are a code smell. We refactor them by extracting the logic into well-named boolean variables or dedicated methods, making the condition read like a sentence.
*   **The "AND" Test (revisited):** This applies to all levels. If a class or method's purpose contains "and", it's a red flag.

*A Personal Note:* These are our current best practices for simplifying code. They are not dogma. If you can demonstrate a better, cleaner, or more efficient way to achieve the same result, we are not just open to it—we are eager to learn and adopt it. This is what continuous improvement means to us.

### 2.3 DRY (Don't Repeat Yourself)

We value knowledge and its clear, unambiguous representation. We eliminate repetition to improve clarity and reduce the chance of error.

### 2.4 Thin Controllers & Service Layer

Our controllers are lean and focused. They are the gatekeepers of our application, handling requests and responses, but delegating the heavy lifting of business logic to a dedicated service layer.

### 2.5 Clean Code as a Standard

We believe that code is read far more often than it is written. Therefore, we treat clarity as a primary feature.

*   **No Remnants of Debugging:** Production code must be free of any debugging artifacts like `var_dump`, `dd`, or `console.log`.
*   **No Commented-Out Code:** Dead code is noise. If it's not used, it's removed. Version control is our safety net, not commented-out blocks.
*   **Self-Documenting Code:** We favor clear naming of variables, functions, and classes over explanatory comments. A comment should explain *why* something is done, not *what* it does.

---

## 3. Code Correctness & Robustness

### 3.1 Functionality & Testing

Code is not "done" until it works. Every commit pushed to our repository represents a stable and functional state of the application.

*   **Unit & Integration Tests:** All critical paths have automated tests.
*   **Code Coverage Target:** 70%+ for services, 90%+ for security-critical code.
*   **Manual QA:** Critical features validated before production push.

### 3.2 Graceful Error Handling

We anticipate failure and design for it. Our users will never see an unhandled exception. We handle errors where they occur, with clarity and purpose.

*   **User-Facing Errors:** Never raw stack traces. Users see friendly messages + error ID for support.
*   **Logging Context:** Full details logged + searchable (error ID maps to logs).
*   **Observability:** Errors aggregated in monitoring systems (Sentry, DataDog, etc.).

### 3.3 Performance & Scalability

*   **Caching Strategy:** Implemented for data requested 2+ times.
*   **Database Queries:** N+1 problems caught in code review.
*   **Async Operations:** Used for I/O-bound tasks (emails, webhooks, heavy processing).

---

## 4. Observability, Monitoring & Debugging

*   **Structured Logging:** JSON format, searchable by ID/context.
*   **Error Tracking:** Integration with Sentry, DataDog, or similar.
*   **Performance Monitoring:** Detection of slow queries and API latency issues.
*   **Health Checks:** Services expose `/health` endpoint for monitoring.

---

## 5. Documentation

*   **Self-Documenting Code:** Clear names over comments explaining WHAT.
*   **README Files:** Every service/package has one (setup, API, examples).
*   **API Documentation:** OpenAPI/Swagger for all endpoints.
*   **Decision Records (ADR):** Document WHY architectural choices were made.

---

## 6. Development Process & Workflow

### 6.1 Coding Standards

*   **PHP:** We adhere to [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html). Code is automatically formatted using `php-cs-fixer`.
*   **Shell Scripts:** We follow the [Google Shell Style Guide](https://google.github.io/styleguide/shell.xml).
*   **JavaScript:** We use modern ES6+ with pragmatic linting.

### 6.2 Commit Message Format

We use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/). This ensures a readable and machine-parsable history.

*Allowed Types:* `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `revert`.

### 6.3 Code Review Process

*   **Every change reviewed + approved** before merge.
*   **Focus:** Learning and improvement, not blame.
*   **Review turnaround:** Feedback within 24h.

### 6.4 Atomic Commits

*   Each commit solves ONE problem (helps with bisect & revert).
*   **No Merge Debt:** PRs merged within 24h or closed (avoid stale contexts).

---

## 7. Architecture & System Design

### 7.1 Architectural Style

We choose our architecture pragmatically based on project scale:

*   **Single Service:** For 1-2 person projects, straightforward MVP.
*   **Modular Monolith:** For growing apps with clear domain boundaries.
*   **Microservices:** For complex, horizontally-scaled systems with independent deployment needs.

### 7.2 API-Driven Communication

Services communicate through well-defined, versioned APIs:

*   **REST (primary):** For most use cases.
*   **gRPC (optional):** For high-performance, low-latency inter-service communication.

### 7.3 Configuration Management

We maintain a strict separation of code and configuration, following [twelve-factor app](https://12factor.net/) principles:

*   Environment variables for all configuration.
*   Externalized secrets management.
*   No hardcoded URLs, database credentials, or API keys.

---

## 8. Technology Stack & Tools

We choose our tools pragmatically, selecting the best technology for the job at hand. We value both cutting-edge innovation and the stability of proven solutions.

### 8.1 Backend (PHP)

**Framework Selection:**

*   **Symfony:** For large, complex, and feature-rich services that require a robust foundation.
*   **Laravel:** For rapid application development where speed and convention are key.
*   **Slim:** For lightweight microservices and APIs where performance and a minimal footprint are paramount.

**Asynchronous Operations:**

We embrace asynchronicity for high-performance, I/O-bound operations using the **`react-php`** ecosystem to build scalable and responsive services.

### 8.2 Frontend (JavaScript)

**Framework Selection:**

*   **Vue.js:** Our default choice for building modern, interactive user interfaces.
*   **React:** Leveraged in projects where its component model and ecosystem provide a distinct advantage.

**Vanilla JavaScript:**

We are not afraid to use the raw power of the web platform for performance-critical code or when a framework is overkill.

**Legacy Support:**

*   **jQuery:** For legacy projects requiring backward compatibility.

---

## 9. Coding in the Age of AI: A Paradigm Shift

We are at the forefront of a new era in software development, actively integrating AI assistants into our daily workflow. We see AI not as a replacement for human ingenuity, but as a powerful force multiplier—a partner that elevates our craft and accelerates our velocity.

### 9.1 The Advantages We've Embraced

*   **Accelerated Architecture & Design:** AI serves as an invaluable sparring partner in the conceptual phase. It helps us rapidly prototype architectural ideas, explore different design patterns, and make more informed decisions from the outset.
*   **Enhanced Cognitive Offloading:** By handling routine syntax lookups, boilerplate generation, and remembering API details, AI frees up our mental bandwidth. This allows us to stay in the flow and concentrate on higher-level problem-solving.
*   **Rapid Code Generation & Iteration:** AI empowers us to translate ideas into functional code at an unprecedented speed. It's a catalyst for writing new features, building tests, and refactoring existing code with greater efficiency.
*   **Streamlined Debugging:** When faced with a bug, AI can quickly suggest potential causes, analyze stack traces, and propose solutions, significantly reducing the time spent on diagnostics.

### 9.2 Our Guiding Principles for AI Collaboration

*   **The Engineer as the Pilot:** We are the pilots, and AI is our advanced co-pilot. We set the destination, make the critical decisions, and maintain ultimate control. The engineer is always the final authority.
*   **Critical Oversight is Non-Negotiable:** We rigorously review, test, and understand every piece of code suggested by AI. We are accountable for the quality and security of our work, regardless of its origin.
*   **Mastering the Art of the Prompt:** We recognize that "prompt engineering" is a crucial new skill. The clarity and context of our questions directly shape the quality of the answers. We are dedicated to mastering this dialogue.

By embracing AI, we are not just coding faster; we are coding smarter. We are augmenting our creativity and focusing our energy on what truly matters: building exceptional software.

---

## 10. Team Culture & Maintenance

### 10.1 Continuous Improvement

*   **Learning Culture:** New technologies and approaches are studied quarterly.
*   **Knowledge Sharing:** Pair programming, code reviews, and team discussions are encouraged.
*   **Post-Mortems:** Document incidents + lessons learned (constructive, not punitive).

### 10.2 Technical Debt Management

*   **Tracked & Prioritized:** Technical debt is visible and managed.
*   **Quarterly Review:** Address high-impact debt items each quarter.
*   **Dependency Updates:** Security patches immediately, minor versions monthly.
*   **Deprecation Warnings:** 2-3 major versions before removal to allow migration time.

### 10.3 Runbooks & Documentation

*   **Incident Runbooks:** Standard procedures for common issues.
*   **Onboarding Docs:** New team members can get productive in 1-2 days.
*   **Architecture Diagrams:** Key systems documented with decision records.

## 11. Principles for Working with AI: Think, Simplify, Execute with Precision

We work with AI assistants daily. These principles ensure our collaboration is efficient, safe, and focused.
_**It's worth adding them to AI guidelines.**_

### 11.1 Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

When working with AI, force explicit reasoning:

*   **State assumptions explicitly:** If uncertain, ask rather than guess. Ambiguity creates bugs.
*   **Present multiple interpretations:** Don't let AI pick silently when options exist. Discuss tradeoffs.
*   **Push back when warranted:** If a simpler approach exists, say so. Don't accept overengineered solutions.
*   **Stop when confused:** Name what's unclear and ask for clarification. Confusion now saves rework later.

### 11.2 Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

Combat overengineering:

*   No features beyond what was asked
*   No abstractions for single-use code
*   No "flexibility" or "configurability" that wasn't requested
*   No error handling for impossible scenarios
*   If 200 lines could be 50, rewrite it

**The test:** Would a senior engineer say this is overcomplicated? If yes, simplify.

### 11.3 Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:

*   Don't "improve" adjacent code, comments, or formatting
*   Don't refactor things that aren't broken
*   Match existing style, even if you'd do it differently
*   If you notice unrelated dead code, mention it — don't delete it

When changes create orphans:

*   Remove imports/variables/functions that YOUR changes made unused
*   Don't remove pre-existing dead code unless asked

**The test:** Every changed line should trace directly to the requirement.

### 11.4 Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform vague requirements into verifiable goals:

| Instead of... | Transform to... |
|--------------|-----------------|
| "Add validation" | "Write tests for invalid inputs, then make them pass" |
| "Fix the bug" | "Write a test that reproduces it, then make it pass" |
| "Refactor X" | "Ensure tests pass before and after" |

For multi-step tasks, state a brief plan with verification:

```
[Step] → verify: [check]
[Step] → verify: [check]
[Step] → verify: [check]
```

Strong success criteria let AI work independently. Weak criteria ("make it work") require constant clarification.

**This document is a living manifesto, created and refined with the assistance of AI tools. It evolves with our team's growth and learning.**

*Last updated: 2026-05-24*

<hr>

<!-- Language selector -->
<div style="margin-bottom:12px;">
  <a href="#en" style="display:inline-block;padding:6px 10px;margin-right:8px;background:#0a84ff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">EN</a>
  <a href="#pl" style="display:inline-block;padding:6px 10px;background:#2ea44f;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">PL</a>
</div>

<a id="pl"></a>
# Po polsku
# Manifest inżynieryjny

To nie jest tylko zbiór zasad — to mój sposób myślenia o programowaniu. Zapisuję to dla siebie, ale jeśli komuś też pomoże, tym lepiej.

Cenię rzemiosło, pragmatyzm i ciągłe doskonalenie. Chcę tworzyć oprogramowanie, które działa dobrze, jest łatwe w utrzymaniu i po prostu przyjemne w codziennej pracy. Ważne jest dla mnie też, żeby kod był czytelny i miał sens dla innych ludzi, nie tylko dla kompilatora czy AI.
Ten dokument ma żyć razem ze mną. Będzie się zmieniał, ale jego rdzeń zostaje ten sam: robić rzeczy porządnie, bez zbędnej komplikacji i z szacunkiem do czasu własnego oraz innych.

## 1. Bezpieczeństwo przede wszystkim (zasada niepodlegająca negocjacjom)

Zanim zostanie napisana jakakolwiek linia kodu, uznajemy, że bezpieczeństwo jest naszym najwyższym priorytetem. To nie funkcja ani dodatek — to fundament, na którym budujemy wszystko.

*   **Brak zaszytych sekretów:** Zero tolerancji dla wrażliwych danych (klucze API, hasła, tokeny) w repozytorium. Takie dane nigdy nie powinny trafiać do kontroli wersji.
*   **Konfiguracja przez środowisko:** Cała konfiguracja, zwłaszcza sekretna, powinna być ładowana ze zmiennych środowiskowych (np. `.env`). Dbamy, aby `.gitignore` wykluczał pliki z sekretami.
*   **Maskowanie danych:** Chronimy dane w logach i raportach błędów — wrażliwe informacje maskujemy lub pomijamy, aby uniknąć wycieku.

---

## 2. Filozofia rdzeniowa: projektowanie kodu i architektura

Nasza filozofia opiera się na sprawdzonych zasadach, które zapewniają jakość, trwałość i odporność kodu.

### 2.1 Zasady SOLID i test "AND"

Stosujemy zasady SOLID, ze szczególnym naciskiem na Zasadę Pojedynczej Odpowiedzialności (SRP). Prosty test: jeśli opis czynności klasy/metody zawiera słowo "I" / "AND", prawdopodobnie robi za dużo i trzeba to rozdzielić.

*Przykład:* Serwis, który "waliduje dane AND wysyła e-mail", powinien być rozdzielony na dwa serwisy. Metoda, która "haszuje hasło AND loguje próbę", powinna być rozdzielona.

### 2.2 Sztuka prostoty

Najlepszy kod to taki, który najłatwiej usunąć. Prostota i czytelność są kluczowe. Stosujemy praktyczne heurystyki:

*   **Reguła "jednego spojrzenia":** Metody powinny być krótkie i na tyle czytelne, by zrozumieć je jednorazowym spojrzeniem, bez przewijania.
*   **Czytelne warunki:** Złożone if-y to zapach kodu. Wyciągamy logikę do dobrze nazwanych zmiennych boolowskich lub dedykowanych metod, tak aby warunek czytał się jak zdanie.
*   **Test "AND" (ponownie):** Jeśli cel klasy/metody zawiera "i/and", to czerwony znak ostrzegawczy.

*Uwaga osobista:* To nasze najlepsze praktyki — nie dogmat. Jeżeli ktoś zaproponuje lepsze, czystsze lub bardziej efektywne rozwiązanie, jesteśmy otwarci na zmianę. Ciągłe doskonalenie to nasza postawa.

### 2.3 DRY (Don't Repeat Yourself)

Cenimy jasne i jednoznaczne reprezentowanie wiedzy. Eliminujemy powtórzenia, by poprawić czytelność i zmniejszyć ryzyko błędów.

### 2.4 Lekkie kontrolery i warstwa serwisów

Kontrolery są szczupłe i skupione. Obsługują żądania i odpowiedzi, delegując logikę biznesową do dedykowanej warstwy serwisów.

### 2.5 Czysty kod jako standard

Kod jest czytany częściej niż pisany — traktujemy czytelność jak kluczową cechę.

*   **Brak artefaktów debugowania:** Kod produkcyjny nie zawiera `var_dump`, `dd`, `console.log` itp.
*   **Brak zakomentowanego kodu:** Martwy kod to hałas. Jeżeli coś nie jest używane — usuń. Kontrola wersji przechowuje historię.
*   **Samodokumentujący się kod:** Preferujemy czytelne nazwy zmiennych, funkcji i klas zamiast komentarzy tłumaczących CO robi kod. Komentarz powinien wyjaśniać DLACZEGO coś jest zrobione, a nie CO robi.

---

## 3. Poprawność kodu i odporność

### 3.1 Funkcjonalność i testowanie

Kod nie jest "gotowy", dopóki nie działa poprawnie. Każdy commit reprezentuje stabilny stan aplikacji.

*   **Testy jednostkowe i integracyjne:** Krytyczne ścieżki są objęte automatycznymi testami.
*   **Cel pokrycia kodu:** 70%+ dla usług, 90%+ dla kodu krytycznego z punktu widzenia bezpieczeństwa.
*   **Ręczne testy jakości:** Kluczowe funkcje weryfikowane przed wdrożeniem.

### 3.2 Łagodna obsługa błędów

Projektujemy pod kątem błędów — użytkownik nigdy nie zobaczy nieobsłużonego wyjątku.

*   **Błędy widoczne dla użytkownika:** Nigdy surowe ślady stosu (stack trace). Użytkownik widzi przyjazny komunikat + identyfikator błędu do wsparcia.
*   **Kontekst w logach:** Pełne informacje logowane i przeszukiwalne (ID błędu mapuje się na logi).
*   **Obserwowalność:** Błędy agregowane w systemach monitoringu (Sentry, DataDog itp.).

### 3.3 Wydajność i skalowalność

*   **Strategia cache'owania:** Wdrożone tam, gdzie dane są pobierane 2+ razy.
*   **Zapytania do DB:** Problemy N+1 wykrywane na przeglądzie kodu.
*   **Operacje asynchroniczne:** Używane dla zadań I/O (wysyłka e-maili, webhooki, ciężkie przetwarzanie).

---

## 4. Obserwowalność, monitoring i debugowanie

*   **Strukturalne logowanie:** Format JSON, przeszukiwalny po ID/kontekście.
*   **Śledzenie błędów:** Integracja z Sentry, DataDog lub podobnymi.
*   **Monitorowanie wydajności:** Wykrywanie wolnych zapytań i opóźnień API.
*   **Health check:** Usługi udostępniają endpoint `/health` do monitoringu.

---

## 5. Dokumentacja

*   **Samodokumentujący się kod:** Czytelne nazwy zamiast komentarzy tłumaczących CO.
*   **Pliki README:** Każdy serwis/pakiet ma README (uruchomienie, API, przykłady).
*   **Dokumentacja API:** OpenAPI/Swagger dla wszystkich endpointów.
*   **Decyzje architektoniczne (ADR):** Dokumentujemy DLACZEGO dane rozwiązanie zostało wybrane.

---

## 6. Proces rozwoju i workflow

### 6.1 Standardy kodowania

*   **PHP:** Trzymamy się [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html). Kod formatowany automatycznie przy pomocy `php-cs-fixer`.
*   **Skrypty shell:** Stosujemy [Google Shell Style Guide](https://google.github.io/styleguide/shell.xml).
*   **JavaScript:** Używamy nowoczesnego ES6+ z pragmatycznym lintowaniem.

### 6.2 Format wiadomości commitów

Stosujemy [Conventional Commits](https://www.conventionalcommits.org/pl/v1.0.0/). Zapewnia to czytelną i maszynowo przetwarzalną historię.

*Dozwolone typy:* `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `revert`.

### 6.3 Proces przeglądu kodu

*   **Każda zmiana podlega review i zatwierdzeniu** przed merge.
*   **Cel przeglądu:** nauka i poprawa jakości, nie obwinianie.
*   **Czas zwrotu:** feedback w ciągu 24h.

### 6.4 Atomiczne commity

*   Każdy commit rozwiązuje JEDEN problem (ułatwia bisect i revert).
*   **Brak długiego backlogu PR:** PRy scalane w ciągu 24h lub zamykane (unikamy przestarzałych kontekstów).

---

## 7. Architektura i projekt systemu

### 7.1 Styl architektoniczny

Wybieramy architekturę pragmatycznie, w zależności od skali projektu:

*   **Pojedynczy serwis:** Dla projektów 1–2 osobowych, prosty MVP.
*   **Modularny monolit:** Dla rosnących aplikacji z wyraźnymi granicami domen.
*   **Mikroserwisy:** Dla złożonych systemów, które wymagają niezależnego skalowania i wdrożeń.

### 7.2 Komunikacja API

Usługi komunikują się przez dobrze zdefiniowane i wersjonowane API:

*   **REST (domyślnie):** Dla większości przypadków użycia.
*   **gRPC (opcjonalnie):** Dla wysokowydajnej, niskolatencyjnej komunikacji międzyserwisowej.

### 7.3 Zarządzanie konfiguracją

Stosujemy ścisłe rozdzielenie kodu i konfiguracji, zgodnie z zasadami [twelve-factor app](https://12factor.net/):

*   Zmienne środowiskowe dla całej konfiguracji.
*   Zewnętrzne zarządzanie sekretami.
*   Brak zaszytych adresów URL, poświadczeń DB lub kluczy API.

---

## 8. Stos technologiczny i narzędzia

Wybieramy narzędzia pragmatycznie — najlepsze do zadania. Cenimy zarówno nowoczesne technologie, jak i stabilne rozwiązania.

### 8.1 Backend (PHP)

**Wybór frameworka:**

*   **Symfony:** Dla dużych, złożonych serwisów wymagających solidnej bazy.
*   **Laravel:** Dla szybkiego developmentu tam, gdzie konwencja przyspiesza pracę.
*   **Slim:** Dla lekkich usług i API o minimalnym narzucie.

**Operacje asynchroniczne:**

Wykorzystujemy asynchroniczność dla zadań I/O (np. `react-php`) tam, gdzie przynosi to korzyść skalowalności.

### 8.2 Frontend (JavaScript)

**Wybór frameworka:**

*   **Vue.js:** Nasz domyślny wybór dla nowoczesnych, interaktywnych interfejsów.
*   **React:** Używamy, gdy korzyści ekosystemu i modelu komponentów przeważają.

**Vanilla JS:**

Nie boimy się użyć czystego JavaScriptu dla krytycznych wydajnościowo fragmentów.

**Wsparcie dla projektów legacy:**

*   **jQuery:** Tylko dla projektów wymagających kompatybilności wstecznej.

---

## 9. Kodowanie w erze AI: zmiana paradygmatu

Aktywnie integrujemy asystentów AI w nasz workflow. AI nie zastępuje inżyniera, jest zaawansowanym współpilotem, który zwiększa naszą produktywność.

### 9.1 Korzyści, które przyjęliśmy

*   **Przyspieszone projektowanie architektury:** AI pomaga w szybkim prototypowaniu i porównywaniu wzorców.
*   **Odciążenie poznawcze:** AI zajmuje się rutynowym boilerplate i pamięta szczegóły API.
*   **Szybsze generowanie i iteracja kodu:** AI wspiera tworzenie funkcji, testów i refaktoryzacji.
*   **Wsparcie w debugowaniu:** AI podpowiada możliwe przyczyny błędów i sugeruje rozwiązania.

### 9.2 Zasady współpracy z AI

*   **Inżynier jako pilot:** To człowiek podejmuje decyzje; AI sugeruje opcje.
*   **Krytyczna weryfikacja jest obowiązkowa:** Zawsze recenzujemy i testujemy kod sugerowany przez AI.
*   **Sztuka promptowania:** Umiejętność formułowania zapytań do AI jest kluczowa.

---

## 10. Kultura zespołu i utrzymanie

### 10.1 Ciągłe doskonalenie

*   **Kultura nauki:** Nowe technologie i podejścia badamy kwartalnie.
*   **Dzielenie wiedzy:** Pair programming, code review i dyskusje zespołowe są zachęcane.
*   **Post-mortem:** Dokumentujemy incydenty i wnioski (konstruktywnie).

### 10.2 Zarządzanie długiem technicznym

*   **Śledzony i priorytetyzowany:** Dług techniczny jest widoczny i zarządzany.
*   **Przegląd kwartalny:** Rozwiązujemy najwyższy priorytet co kwartał.
*   **Aktualizacje zależności:** Łatki bezpieczeństwa natychmiast, aktualizacje drobne co miesiąc.
*   **Wycofywanie funkcji:** Ostrzeżenia 2–3 wersje główne wcześniej, aby dać czas na migrację.

### 10.3 Runbooki i dokumentacja operacyjna

*   **Runbooki:** Standardowe procedury dla typowych problemów.
*   **Dokumentacja onboardingu:** Nowa osoba powinna być produktywna w 1–2 dni.
*   **Diagramy architektury:** Kluczowe systemy dokumentowane wraz z rejestrami decyzji.

---

## 11. Zasady pracy z AI: myśl, uprość, wykonaj precyzyjnie

Pracujemy z asystentami AI każdego dnia. Te zasady zapewniają, że współpraca jest efektywna, bezpieczna i skupiona.
_**Warto dodać je do wytycznych dla AI.**_

### 11.1 Myśl przed kodowaniem

**Nie zakładaj z góry. Nie ukrywaj wątpliwości. Nazywaj kompromisy (tradeoff-y).**

Pracując z AI, wymuszaj jawne rozumowanie:

*   **Mów założenia jawnie:** Jeśli nie wiesz, pytaj zamiast zgadywać. Niejasność tworzy błędy.
*   **Przedstawiaj wiele interpretacji:** Nie pozwalaj AI wybierać po cichu. Dyskutuj o kompromisach.
*   **Sprzeciwiaj się, gdy warto:** Jeśli istnieje prostsze podejście, powiedz to. Nie akceptuj przeinżynierowanych rozwiązań.
*   **Zatrzymaj się, gdy się gubisz:** Nazwij, co jest niejasne, i poproś o wyjaśnienie. Zamieszanie teraz oszczędza pracę potem.

### 11.2 Prostota przede wszystkim

**Minimum kodu rozwiązujące problem. Nic spekulacyjnego.**

Zwalczaj overengineering:

*   Brak funkcji poza tym, co zostało poproszone
*   Brak abstrakcji dla kodu jednorazowego użytku
*   Brak "elastyczności" lub "konfigurowalności", która nie była wymagana
*   Brak obsługi błędów dla niemożliwych scenariuszy
*   Jeśli 200 linii mogłoby być 50, przepisz to

**Test:** Czy senior engineer powiedziałby, że to skomplikowane? Jeśli tak — uprość.

### 11.3 Chirurgiczne zmiany

**Dotykaj tylko co musisz. Sprzątaj tylko swój własny bałagan.**

Edytując istniejący kod:

*   Nie "ulepszaj" sąsiedniego kodu, komentarzy czy formatowania
*   Nie refaktoruj rzeczy, które nie są zepsute
*   Pasuj do istniejącego stylu, nawet jeśli byś to robił inaczej
*   Jeśli zauważysz niezwiązany martwy kod, wspomnij o tym — nie usuwaj go

Gdy zmiany tworzą sieroty (orphans):

*   Usuń importy/zmienne/funkcje, które Twoje zmiany uczyniły nieużywanymi
*   Nie usuwaj istniejącego martwego kodu, chyba że zostaniesz poproszony

**Test:** Każda zmieniona linia powinna bezpośrednio odnosić się do wymagania.

### 11.4 Wykonanie sterowane celem

**Zdefiniuj kryteria sukcesu. Pętla aż do weryfikacji.**

Przekształć niejasne wymagania w weryfikowalne cele:

| Zamiast... | Przekształć na... |
|-----------|------------------|
| "Dodaj walidację" | "Napisz testy dla błędnych danych, potem spraw, by przeszły" |
| "Napraw błąd" | "Napisz test, który go reprodukuje, potem go napraw" |
| "Refaktoruj X" | "Upewnij się, że testy przechodzą przed i po" |

Dla wieloetapowych zadań podaj krótki plan z weryfikacją:

```
[Krok] → verify: [check]
[Krok] → verify: [check]
[Krok] → verify: [check]
```

Silne kryteria sukcesu pozwalają AI pracować samodzielnie. Słabe ("zrób, żeby działało") wymagają ciągłego dopytywania.

---

Ten dokument jest żywym manifestem, tworzonym i udoskonalanym przy wsparciu narzędzi AI. Ewoluuje wraz z rozwojem i nauką zespołu.

*Ostatnia aktualizacja: 2026-05-24*
