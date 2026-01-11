# Live Visitors & Real-Time Analytics

NetSendo includes a powerful real-time analytics system that allows you to see visitors on your site as they browse. This feature is powered by [Laravel Reverb](https://reverb.laravel.com), a first-party WebSocket server for Laravel applications.

## 🚀 Features

- **Real-Time Dashboard**: View active visitors instantly without refreshing the page.
- **Device Tracking**: Identify if visitors are on Desktop, Mobile, or Tablet.
- **Page Tracking**: See exactly which URL active visitors are currently viewing.
- **User Privacy**: All data is anonymized and linked only to your specific pixel ID.

## 🛠️ Architecture

The system uses a WebSocket connection to push updates from the server to your dashboard immediately when they happen.

1. **Visitor** browses your site with the NetSendo Pixel installed.
2. **Pixel** sends a `page_view` event to the NetSendo API.
3. **NetSendo API** processes the event and broadcasts a `PixelVisitorActive` event.
4. **Laravel Reverb** receives the broadcast and pushes it via WebSocket to subscribed clients.
5. **Dashboard** receives the event and updates the interface instantly.

## ⚙️ Configuration

### Environment Variables

To enable real-time features, you need to configure the Reverb settings in your `.env` file.

**Backend (Server) Configuration:**

```env
# Laravel Reverb Configuration
REVERB_APP_ID=netsendo
REVERB_APP_KEY=netsendo-reverb-key
REVERB_APP_SECRET=netsendo-reverb-secret
REVERB_HOST=reverb               # Internal Docker host
REVERB_PORT=8085                 # External port mapping
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8085          # Internal port
```

**Frontend (Browser) Configuration:**
These variables tell the browser where to connect for WebSocket updates.

```env
# Frontend Client Connection
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost       # Hostname accessible from the browser
VITE_REVERB_PORT=8085            # Port accessible from the browser
VITE_REVERB_SCHEME=http
```

> [!IMPORTANT]
> If you are deploying to production behind a reverse proxy (like Nginx) with SSL, you should set `VITE_REVERB_SCHEME=https`, `VITE_REVERB_HOST=your-domain.com`, and `VITE_REVERB_PORT=443` (or whatever port your WebSocket traffic is routed through).

### Docker Compose

The `docker-compose.yml` file includes a dedicated service for Reverb:

```yaml
reverb:
  image: ghcr.io/netsendo/netsendo:${NETSENDO_VERSION:-latest}
  command: php artisan reverb:start --host=0.0.0.0 --port=8085
  ports:
    - "8085:8085"
  environment:
    - REVERB_SERVER_HOST=0.0.0.0
    - REVERB_SERVER_PORT=8085
  # ... other config
```

## 🔧 Troubleshooting

### "WebSocket connection failed"

If you see connection errors in the browser console:

1. Ensure the `netsendo-reverb` container is running: `docker compose ps`
2. specific port `8085` is open and not blocked by a firewall.
3. Check `VITE_REVERB_HOST` in `.env` matches the domain you are accessing the dashboard from (or `localhost` for local dev).
4. After changing `.env` variables, you **MUST** rebuild the frontend assets:
   ```bash
   docker compose exec app npm run build
   ```

### "Connection refused"

If the backend cannot talk to Reverb:

1. Ensure `REVERB_HOST` is set to the correct internal hostname (usually `reverb` in Docker).
2. Ensure `BROADCAST_CONNECTION=reverb` is set in `.env`.

### Performance Tuning

For high-traffic sites, you may want to adjust the Reverb limits or scale the service horizontally. See the [Laravel Reverb Documentation](https://laravel.com/docs/reverb) for scaling strategies.
