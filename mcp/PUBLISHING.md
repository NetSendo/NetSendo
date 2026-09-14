# Wydanie @netsendo/mcp-client

## TL;DR

Paczkę publikuje **GitHub Actions** (`.github/workflows/publish-mcp.yml`) po wypchnięciu
taga `mcp-v<wersja>`. Nie publikuj `npm publish` z własnego komputera: paczka nie dostanie
atestacji provenance, a do tego ścigasz się z workflow o ten sam numer wersji, którego npm
nigdy nie pozwoli użyć ponownie.

Użytkownicy instalują klienta przez `npx @netsendo/mcp-client`; instalacje Dockerowe
dostają nową wersję przy przebudowie usługi `mcp` (budowanej z katalogu `mcp/`).

---

## Warunek wstępny (jednorazowo, w UI npm)

npmjs.com → paczka `@netsendo/mcp-client` → Settings → Publish access → Trusted Publishers →
GitHub Actions:

| Pole | Wartość |
|---|---|
| Organization or user | `NetSendo` |
| Repository | `NetSendo` |
| Workflow filename | `publish-mcp.yml` |
| Environment name | `NPM_TOKEN` |
| Allowed actions | zaznaczone „Allow npm publish" |

Bez tego publikacja kończy się błędem **404** — tak npm maskuje brak uprawnień. Token npm
nie jest nigdzie przechowywany; workflow wymienia tożsamość GitHub na krótkotrwałe
poświadczenie.

---

## Wydanie krok po kroku

Numer: nowe narzędzia lub parametry → `minor`, same poprawki → `patch`.

1. **Backend najpierw.** Jeśli narzędzie potrzebuje nowej trasy lub pola w API, zmień
   `src/` (z testami) — klient tylko przekazuje to, co API przyjmuje.
2. **Kod klienta** w `mcp/src/`, sprawdzenie typów:
   ```bash
   cd mcp && npx tsc --noEmit
   ```
3. **Wersja** — `npm version` w `mcp/` **nie tworzy commita ani taga** (katalog paczki nie
   jest korzeniem repozytorium), tylko podbija `package.json` i `package-lock.json`:
   ```bash
   npm version minor --no-git-tag-version   # albo patch
   ```
4. Ta sama wersja w `SERVER_VERSION` (`mcp/src/index.ts`) i w
   `src/config/netsendo.php → plugins.mcp.version` (z niej aplikacja liczy
   `update_available`).
5. **Build** — `dist/` jest śledzony w gicie i zawiera `SERVER_VERSION`, więc buduj po
   kroku 4:
   ```bash
   npm run build
   ```
6. **Dokumentacja**, jeśli zmieniły się narzędzia: `mcp/README.md`, `docs/mcp-server.md`,
   `docs/MCP_INTEGRATION.md`, lista na stronie `src/resources/js/Pages/Marketplace/MCP.vue`
   (opisy `mcp.tools.*`) i lista narzędzi w podpowiadanym prompcie (`MCP.vue` oraz
   `mcp.agent_prompt_content`) — we wszystkich czterech plikach
   `src/resources/js/locales/*.json`.
7. **Commit i tag:**
   ```bash
   git commit -am "chore(mcp): release 1.5.0"
   git tag -a mcp-v1.5.0 -m "chore(mcp): release 1.5.0"
   ```
8. **Push** — najpierw kod, potem tag (tag uruchamia publikację; wersja w
   `config/netsendo.php` powinna trafić na `main` razem z nim):
   ```bash
   git push origin HEAD:main
   git push origin mcp-v1.5.0
   ```

Workflow przerywa, jeśli wersja z taga nie zgadza się z `mcp/package.json`, i pomija
publikację, jeśli ta wersja jest już na npm.

---

## Weryfikacja

```bash
gh run list --workflow publish-mcp.yml --limit 3
npm view @netsendo/mcp-client version
npm view @netsendo/mcp-client@1.5.0 dist.attestations.provenance.predicateType
# oczekiwane: https://slsa.dev/provenance/v1
```

---

## Test end-to-end przed wydaniem

Gdy zmieniają się narzędzia, sprawdź je na prawdziwym API:

1. Lokalna instancja na SQLite: z `src/` `php artisan migrate --force` z
   `DB_CONNECTION=sqlite DB_DATABASE=<plik>`, potem skryptem użytkownik i
   `ApiKey::generate($userId, 'MCP', [...uprawnienia])`.
2. Serwer (katalog roboczy musi być `public/`):
   ```bash
   cd src/public && php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
   ```
3. Skrypt Node z `Client` i `StdioClientTransport` z `@modelcontextprotocol/sdk`, który
   uruchamia `node mcp/dist/index.js` ze zmiennymi `NETSENDO_API_URL` i `NETSENDO_API_KEY`,
   wywołuje `listTools()` i narzędzia przez `callTool()`.

---

## Bez npm

Instalacje Dockerowe mogą uruchamiać klienta z własnego obrazu:

```json
{
  "mcpServers": {
    "netsendo": {
      "command": "docker",
      "args": ["compose", "-f", "/path/to/docker-compose.yml", "run", "--rm", "-i", "mcp"]
    }
  }
}
```
