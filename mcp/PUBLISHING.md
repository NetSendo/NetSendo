# Publikacja @netsendo/mcp-client na npm

## TL;DR

Publikujesz to **TY JEDEN RAZ**. Potem każdy użytkownik NetSendo może użyć `npx @netsendo/mcp-client` bez żadnych dodatkowych kroków.

---

## Wymagania

1. **Konto npm** - [npmjs.com/signup](https://www.npmjs.com/signup)
2. **Organizacja npm** (opcjonalne) - dla `@netsendo/` namespace
3. **Node.js 18+** zainstalowany

---

## Krok po kroku

### 1. Zaloguj się do npm

```bash
npm login
# Podaj: username, password, email, OTP (jeśli masz 2FA)
```

### 2. Utwórz organizację (jednorazowo)

Jeśli chcesz używać `@netsendo/mcp-client` (zalecane):

1. Idź na [npmjs.com/org/create](https://www.npmjs.com/org/create)
2. Utwórz organizację `netsendo`
3. Wybierz plan (darmowy wystarczy dla publicznych pakietów)

### 3. Zaktualizuj package.json

```bash
cd mcp
```

Edytuj `package.json`:

```json
{
  "name": "@netsendo/mcp-client",
  "version": "1.0.0",
  "private": false,
  "publishConfig": {
    "access": "public"
  }
}
```

### 4. Zbuduj pakiet

```bash
npm run build
```

### 5. Przetestuj lokalnie (opcjonalne)

```bash
# Symuluj instalację
npm pack

# Powinno utworzyć: netsendo-mcp-client-1.0.0.tgz
# Przetestuj:
npx ./netsendo-mcp-client-1.0.0.tgz --help
```

### 6. Opublikuj

```bash
npm publish --access public
```

Gotowe! Pakiet jest teraz dostępny dla wszystkich.

---

## Aktualizacje

Przy każdej nowej wersji NetSendo z zmianami MCP:

```bash
# 1. Zaktualizuj wersję
npm version patch  # lub minor/major

# 2. Zbuduj
npm run build

# 3. Opublikuj
npm publish
```

---

## Weryfikacja

Po publikacji sprawdź:

1. **Na npmjs.com:** https://www.npmjs.com/package/@netsendo/mcp-client
2. **Instalacja:**
   ```bash
   npx @netsendo/mcp-client --help
   ```

---

## Alternatywa: Bez publikacji na npm

Jeśli nie chcesz (jeszcze) publikować, użytkownicy mogą:

### A) Użyć Dockera (obecna metoda)

```json
{
  "mcpServers": {
    "netsendo": {
      "command": "docker",
      "args": [
        "compose",
        "-f",
        "/path/to/docker-compose.yml",
        "run",
        "--rm",
        "-i",
        "mcp"
      ]
    }
  }
}
```

### B) Zainstalować z GitHub

```json
{
  "mcpServers": {
    "netsendo": {
      "command": "npx",
      "args": [
        "-y",
        "github:netsendo/netsendo#mcp",
        "--url",
        "https://...",
        "--api-key",
        "..."
      ]
    }
  }
}
```

---

## FAQ

### Czy każdy użytkownik musi coś publikować?

**NIE.** Publikujesz TY raz, użytkownicy tylko wpisują `npx @netsendo/mcp-client`.

### Czy mogę używać innej nazwy?

Tak, np. `netsendo-mcp-client` zamiast `@netsendo/mcp-client` (nie wymaga organizacji).

### Ile kosztuje?

Publikacja publicznych pakietów na npm jest **darmowa**.

### Jak zaktualizować pakiet?

```bash
npm version patch && npm publish
```
