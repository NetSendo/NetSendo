# Webinary — Vimeo + Evergreen (plan wdrożenia i aktualizacji)

> **Data:** 2026-07-10
> **Status:** ✅ Zaimplementowane (Vimeo live/auto/hybrid, evergreen sync, wykrywanie końca nagrania, działający replay)
> **Wymogi wdrożeniowe:** migracja bazy (`vimeo_id`) + rebuild frontendu (`vite build`). Bez zmian w pliku `VERSION`.

Cel: rozszerzyć moduł Webinarów o **Vimeo** jako źródło wideo (obok YouTube i bezpośredniego URL), a auto-webinary uczynić prawdziwie **evergreen** — spóźniony widz wchodzi w odpowiednim momencie nagrania, koniec nagrania kończy sesję, a nagranie jest dostępne jako replay.

---

## 1. Zakres zmian

| Obszar | Co się zmieniło |
|---|---|
| **Źródło wideo** | Każdy typ webinaru może używać Vimeo. Live: YouTube / Vimeo. Auto & hybrid: bezpośredni URL / Vimeo. Wybór dostawcy w formularzu; zapisywane jest tylko ID wybranego dostawcy (pozostałe pola czyszczone transformem). Render oparty na obecności pola. |
| **Evergreen sync** | Auto/hybrid odtwarzają nagranie zsynchronizowane z zegarem sesji. Offset liczony po stronie klienta z `sessionStartTime`; seek przy wejściu; korekta „do przodu" (nigdy wstecz) przy driftcie — cyklicznie i przy powrocie do karty. |
| **Koniec nagrania** | Wejście po końcu nagrania (offset ≥ długość) lub dojście do końca → ekran „sesja zakończona" z linkiem do replayu. Długość czytana klient-side (native `loadedmetadata` / Vimeo `getDuration()`), fallback 8 s. |
| **Replay** | Trasa `webinar.replay` (wcześniej 500 — brak widoku) ma realny widok: nagranie od początku z kontrolkami (native + Vimeo) + lista produktów. `hasReplay` obejmuje Vimeo i respektuje ustawienie `allow_replay`. |
| **Śledzenie obecności** | Ujednolicony `videoTracker` (native `<video>` / Vimeo Player SDK) — beacony postępu/wyjścia działają tak samo dla obu (`max_video_position_seconds`, listy/tagi obecności, analityka). |

---

## 2. Zmienione i nowe pliki

**Backend**
- `app/Http/Controllers/WebinarController.php` — walidacja `vimeo_id`, `video_provider` (`youtube|vimeo|upload`).
- `app/Http/Controllers/Public/PublicWebinarController.php` — `$hasVideo`/`$hasReplay` obejmują Vimeo; flaga `videoSyncEnabled`; widok `replay`.
- `app/Models/Webinar.php` — `vimeo_id` w `$fillable`; helper `vimeoEmbedUrl()` (obsługa formatu niepublicznego `id/hash`).
- `database/migrations/2026_07_10_150000_add_vimeo_to_webinars.php` — kolumna `vimeo_id` (nullable) + backfill `video_provider='youtube'` dla istniejących webinarów YouTube.

**Frontend (Vue)**
- `resources/js/Pages/Webinars/Create.vue`, `Edit.vue` — selektor dostawcy zależny od typu, watcher/derivacja, transform czyszczący nieużywane pola.
- `resources/js/Pages/Webinars/Studio.vue` — podgląd Vimeo.
- `resources/js/locales/{en,pl,de,es}.json` — klucze dostawcy/Vimeo, `provider_upload`, poprawiony `video_url_help`.

**Widoki (Blade)**
- `resources/views/webinar/watch.blade.php` — Vimeo SDK (warunkowo), `videoTracker` (play/seek/getDuration/onEnded), evergreen sync, wykrywanie końca, nakładka „ended".
- `resources/views/webinar/partials/ended.blade.php` — **nowy**, współdzielona treść ekranu „zakończone".
- `resources/views/webinar/replay.blade.php` — **nowy**, strona replayu.

---

## 3. Kroki wdrożenia

1. **Migracja bazy** — dodaje kolumnę `webinars.vimeo_id` i robi backfill `video_provider`.
   - W środowisku Docker migracje uruchamiają się automatycznie przy starcie kontenera.
   - Ręcznie (jeśli potrzeba): `php artisan migrate --force`.
   - Migracja jest idempotentna (`Schema::hasColumn`) i ma `down()` (drop `vimeo_id`).
2. **Rebuild frontendu** — potrzebny dla zmian Vue/locale: `npm run build` (`vite build`).
3. **Czyszczenie widoków/tras** (zalecane): `php artisan view:clear && php artisan route:clear`.
4. **Weryfikacja** — patrz sekcja 5.

Kolejność bezpieczna: migracja → build → clear cache. Brak zmian w kolejce/cronie/schedulerze.

---

## 4. Wymogi konfiguracji i hostingu

- **Bezpośredni URL wideo (native `<video>`) musi obsługiwać HTTP Range requests** (`Accept-Ranges: bytes`). Bez tego seek evergreen dla plików MP4 nie zadziała (przeglądarka nie przewinie). Standardowy serwing plików statycznych (Nginx/Apache/S3/CloudFront) to zapewnia — zweryfikować dla własnego hostingu i ewentualnego CDN.
- **Vimeo — ustawienia prywatności embedu:** film musi mieć włączone osadzanie. Dla filmów „unlisted"/prywatnych używać formatu `ID/HASH` w polu Vimeo ID (helper zbuduje `?h=HASH`). Jeśli w prywatności Vimeo ograniczono domeny osadzania — dodać domenę aplikacji do whitelisty.
- **CSP (jeśli używane):** zezwolić na `player.vimeo.com` (skrypt SDK i `frame-src`) oraz `*.vimeocdn.com` (media).
- **Autoplay:** przeglądarki mogą blokować autoplay z dźwiękiem bez interakcji użytkownika — zachowanie identyczne jak dla dotychczasowego YouTube; odtwarzanie łapie odrzucenie (`.catch`).

---

## 5. Weryfikacja (QA) — do wykonania w środowisku Docker

> Runtime wymaga działającej aplikacji (lokalnie brak PHP/`node_modules`). Poniższe scenariusze pokrywają ścieżki krytyczne.

**Konfiguracja / formularz**
- [ ] Create/Edit: dla `live` selektor pokazuje YouTube/Vimeo; dla `auto`/`hybrid` — Bezpośredni URL/Vimeo.
- [ ] Zapis webinaru Vimeo: w bazie ustawione `vimeo_id` i `video_provider='vimeo'`, `youtube_live_id`/`video_url` puste.
- [ ] Studio: podgląd renderuje odtwarzacz Vimeo.

**Odtwarzanie na żywo (watch)**
- [ ] Live Vimeo: iframe Vimeo, kontrolki ukryte, overlay blokuje interakcję.
- [ ] Auto Vimeo/native, wejście na czas → gra od 0.
- [ ] Auto Vimeo/native, **wejście po X min** → seek do ~X:00, gra dalej.
- [ ] Karta w tle → powrót → doskok do aktualnego momentu.
- [ ] **Wejście po końcu nagrania** (offset > długość) → ekran „sesja zakończona" (nie czarna klatka / nie od 0).
- [ ] Obejrzenie do końca nagrania → ekran „sesja zakończona".
- [ ] Live YouTube/Vimeo: brak jakiegokolwiek seeka.

**Śledzenie / listy**
- [ ] Beacon postępu (co 30 s) i wyjścia zapisują `max_video_position_seconds` dla Vimeo auto-webinaru.
- [ ] Po przekroczeniu progu — subskrybent trafia na listę/tag „obecny".

**Replay**
- [ ] Przycisk „Obejrzyj nagranie" na ekranie „zakończone" prowadzi do działającej strony (nie 500).
- [ ] Replay gra nagranie **od początku z kontrolkami** (native i Vimeo).
- [ ] Przy `allow_replay = false` trasa replay zwraca 404, a przycisk się nie pokazuje.

**Regresja**
- [ ] Istniejące webinary YouTube działają bez zmian (po backfillu `video_provider='youtube'`).
- [ ] Istniejące auto-webinary z bezpośrednim URL działają (teraz z evergreen sync).

---

## 6. Rollback

- **Frontend/widoki:** revert commitów i ponowny `vite build`. Nakładka „ended", replay i sync to warstwa prezentacji — usunięcie nie narusza danych.
- **Baza:** `php artisan migrate:rollback` cofa `vimeo_id` (`down()` = drop kolumny). Backfill `video_provider` jest nieszkodliwy i może zostać (kolumna istniała wcześniej).
- **Zależność zewnętrzna:** SDK Vimeo ładowane tylko gdy renderowany jest film Vimeo — brak wpływu na webinary YouTube/native.

---

## 7. Znane ograniczenia i możliwe rozszerzenia

- **Długość nagrania czytana klient-side.** Wystarczające dla wykrywania końca, ale ekran „zakończone" pojawia się po krótkiej inicjalizacji odtwarzacza. Opcjonalne ulepszenie: kolumna `recording_duration_seconds` (uzupełniana z Vimeo oEmbed / przy zapisie) → serwerowe `sessionEnded` bez migotania odtwarzacza.
- **Range requests dla native MP4** — twarde wykrycie braku wsparcia po stronie klienta jest zawodne; udokumentowane jako wymóg hostingu (sekcja 4).
- **Replay nie śledzi obecności** (świadomie — to ponowne obejrzenie, nie sesja).
- **Hybrid live-mode:** gdy hybrydowy webinar ma aktywną sesję live (`isLive`), sync jest wyłączony (traktowany jak transmisja) — zgodnie z projektem.
