# Mobile app options

## Option A — Install from the website (no Android Studio)

Visitors on [gaithoproperties.co.ke](https://gaithoproperties.co.ke/) see a floating **Install app** button on the public site (home, properties, about, contact).

| Platform | How it works |
|----------|----------------|
| **Android (Chrome)** | Tap **Install app** → browser adds icon to home screen (PWA) |
| **iPhone (Safari)** | Tap button → follow Share → **Add to Home Screen** |
| **Desktop (Chrome / Edge)** | Click **Install app** when prompted, or use the install icon in the address bar |
| **Desktop (Safari on Mac)** | Click **Install app** → follow **File → Add to Dock** (or Share → Add to Dock) |

The property portal (agent, landlord, tenant) also supports desktop install via the same **Install app** button — it opens as a standalone app window.

Requirements: site on **HTTPS**, run `npm run build` on the server after deploy.

You do **not** need Android Studio for this.

---

## Option B — APK file (Capacitor, needs Android Studio)

The APK is a **native shell** that opens your **hosted Laravel site** in a WebView. You keep one codebase; the APK does not bundle PHP or the database. Use this for Play Store distribution or when you want a `.apk` file to sideload.

## Prerequisites

1. **Java JDK 17+** and **Android Studio** (with Android SDK).
2. Laravel deployed on **HTTPS** for production (or HTTP + cleartext for local testing only).
3. `APP_URL` in Laravel `.env` must match the URL users open (same host as `CAPACITOR_SERVER_URL`).
4. For local testing from a phone: run Laravel reachable on your LAN, e.g.  
   `php artisan serve --host=0.0.0.0 --port=8000`  
   and use `npm run build` (not Vite dev server) so CSS/JS load on the device.

## One-time setup

```bash
npm install
copy capacitor.env.example capacitor.env
```

Edit `capacitor.env` (Gaitho production is preconfigured in `capacitor.env.example`):

```env
CAPACITOR_SERVER_URL=https://gaithoproperties.co.ke/property/tenant/login
CAPACITOR_ALLOW_CLEARTEXT=false
```

Server `.env` must include `APP_URL=https://gaithoproperties.co.ke` (no trailing slash).

Sync native project:

```bash
npm run cap:sync
```

Open in Android Studio:

```bash
npm run cap:android
```

In Android Studio: **Build → Generate Signed Bundle / APK** → APK.

## npm scripts

| Script | Purpose |
|--------|---------|
| `npm run cap:sync` | Copy web assets + config into `android/` |
| `npm run cap:android` | Open Android Studio |
| `npm run cap:copy` | Copy assets only (faster than full sync) |

After changing `capacitor.env`, always run `npm run cap:sync` before building.

## Laravel settings (production)

In `.env` on the server:

```env
APP_URL=https://your-domain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

If login fails inside the app but works in Chrome, check `SESSION_DOMAIN` (usually leave empty so it defaults to the host).

## Testing on a physical phone (XAMPP / local)

1. Find your PC IP (e.g. `192.168.1.10`).
2. `capacitor.env`:

   ```env
   CAPACITOR_SERVER_URL=http://192.168.1.10:8000/property/tenant/login
   CAPACITOR_ALLOW_CLEARTEXT=true
   ```

3. `npm run cap:sync`, rebuild/run from Android Studio.
4. Phone and PC must be on the **same Wi‑Fi**; allow Windows firewall for port 8000.

## App ID / name

- Package: `ke.co.gaithoproperties.portal`
- Display name: **Gaitho Property Agency**
- Live site: [gaithoproperties.co.ke](https://gaithoproperties.co.ke/)

Alternate start URLs in `capacitor.env`:

| Role | URL |
|------|-----|
| Tenant | `https://gaithoproperties.co.ke/property/tenant/login` |
| Landlord | `https://gaithoproperties.co.ke/property/landlord/login` |
| Staff | `https://gaithoproperties.co.ke/login` |
| Public listings | `https://gaithoproperties.co.ke/` |

## Agent vs tenant

Start with **tenant** URL (`/property/tenant/login`). Agent workspace is usable in WebView but dense on small screens; point `CAPACITOR_SERVER_URL` to agent login when ready.

## Limitations

- Requires network (no offline mode).
- PDF/print flows depend on the device browser or download behavior.
- Push notifications need extra Capacitor plugins (not included yet).

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Blank white screen | Set `CAPACITOR_SERVER_URL`, run `cap:sync`, rebuild |
| CSS/JS missing on phone | Run `npm run build`, remove `public/hot` |
| Login loops / 419 | Confirm `APP_URL` host matches server URL; cookies enabled in WebView |
| Can't reach localhost from phone | Use LAN IP or `10.0.2.2` (emulator), not `127.0.0.1` |
