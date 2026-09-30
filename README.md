# Exactplay Spin – WordPress plugin

Embeds demo slots from the [Exactplay Spin API](https://exactplay.com/spin) in WordPress posts and pages. Live example of the API: [Plentyspins](https://plentyspins.com).

`readme.txt` is the WordPress.org listing (features, shortcode reference, FAQ, external services disclosure). This file is for developers.

## What's in it

| Path | Purpose |
|---|---|
| `exactplay-spin.php` | Plugin header and bootstrap |
| `includes/class-api-client.php` | Calls `gameserver.exactplay.com`, normalizes responses, caches them in transients, fetches in parallel |
| `includes/class-renderer.php` | Front-end markup shared by blocks and shortcodes |
| `includes/class-blocks.php` + `blocks/*/block.json` | "Demo Game" and "Demo Game Grid" blocks (server-rendered) |
| `includes/class-shortcodes.php` | `[exactplay_game]` and `[exactplay_games]` |
| `includes/class-rest-controller.php` | `exactplay-spin/v1/providers` and `/games` for the block editor (the API has no CORS headers, so the editor goes through the site) |
| `includes/class-admin.php` | Game Library, Settings, plugin-list links, privacy policy text |
| `assets/js/editor.js` | Block editor UI. Plain JS on the `wp.*` globals, no build step |
| `assets/js/frontend.js` | Click-to-play, fullscreen, pop-up player (`<dialog>`) |
| `.wordpress-org/` | WordPress.org listing icon, banners and screenshots (not part of the plugin zip) |

## Search fallback

The live API currently ignores `search` on `/api/v1/games`. When results don't match the search term, the plugin builds a per-studio index of the catalog (all pages, fetched in parallel), caches it, and searches that. It records that the API ignored search for the cache lifetime, then checks again. Once the API filters correctly, the fallback stops being used on its own. See `Api_Client::get_games()`.

## Run locally

```sh
docker compose up -d
docker compose run --rm cli wp core install --url=http://localhost:8089 --title=Spin --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
docker compose run --rm cli wp plugin activate exactplay-spin
```

Open http://localhost:8089/wp-admin (admin / admin). The plugin folder is mounted read-only, so edits show up immediately.

Run WordPress.org's Plugin Check:

```sh
docker compose run --rm cli wp plugin install plugin-check --activate
docker compose run --rm cli wp plugin check exactplay-spin
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
