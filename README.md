# Exactplay Spin – WordPress plugin

Embeds demo slots from the [Exactplay Spin API](https://exactplay.com/spin) in WordPress posts and pages. Live example of the API: [PlentySpins](https://plentyspins.com).

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
| `.wordpress-org/` | Listing icon, banners and screenshots (SVN `assets/`, not shipped in the zip) |

## Search fallback

The live API currently ignores `search` on `/api/v1/games`. When results don't match the search term, the plugin builds a per-studio index of the catalog (all pages, fetched in parallel), caches it, and searches that. It records that the API ignored search for the cache lifetime, then checks again. Once the API filters correctly, the fallback stops being used on its own. See `Api_Client::get_games()`.

## Run locally

```sh
docker compose up -d
docker compose run --rm cli wp core install --url=http://localhost:8089 --title=Spin --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
docker compose run --rm cli wp plugin activate exactplay-spin
```

Open http://localhost:8089/wp-admin (admin / admin). The plugin folder is mounted read-only, so edits show up immediately.

Run WordPress.org's Plugin Check before each release:

```sh
docker compose run --rm cli wp plugin install plugin-check --activate
docker compose run --rm cli wp plugin check exactplay-spin
```

## Release

1. Bump `Version` in `exactplay-spin.php`, `EXACTPLAY_SPIN_VERSION`, `version` in both `block.json` files, and `Stable tag` in `readme.txt`. Add a changelog entry.
2. `bin/build-zip.sh` builds `dist/exactplay-spin.zip`. It fails if the version and stable tag differ.
3. First release: submit the zip at https://wordpress.org/plugins/developers/add/ from an account using an @exactplay.com email, since the plugin name uses the Exactplay trademark. Set `Contributors:` in `readme.txt` to that account's username.
4. After approval, in the SVN repo: copy `dist/exactplay-spin/*` into `trunk/`, copy `.wordpress-org/*` into `assets/`, `svn cp trunk tags/<version>`, and commit.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
