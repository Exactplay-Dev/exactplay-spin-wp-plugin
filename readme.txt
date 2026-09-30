=== Exactplay Spin – Demo Slot Games ===
Contributors: exactplay
Tags: slots, casino, demo games, embed, affiliate
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed free-play demo slots from 25+ studios in posts and pages with a block or shortcode. Powered by the Exactplay Spin API.

== Description ==

**Exactplay Spin** puts thousands of real demo slots on your WordPress site with no coding and no per-studio integrations. Search the catalog right inside the block editor, click a game, and it's embedded, with artwork, a play button and fullscreen mode.

The games come from the [Exactplay Spin API](https://exactplay.com/spin), which aggregates demo games from Pragmatic Play, NetEnt, Play'n GO, Hacksaw Gaming, Nolimit City, Relax Gaming, Red Tiger, Quickspin, ELK Studios, Yggdrasil, Games Global, Playtech and more, with new studios added regularly.

Want to see what the API can do? [Plentyspins](https://plentyspins.com) is a swipeable slot demo site built entirely on Exactplay Spin.

= Features =

* **Demo Game block**: search thousands of games by name or studio and embed one in a click.
* **Demo Game Grid block**: show random games, a studio's catalog, or a hand-picked list. Visitors play in a pop-up player without leaving the page.
* **Shortcodes** for the classic editor, widgets and page builders such as Elementor, Divi and WPBakery.
* **Game Library** admin screen to browse the whole catalog, preview games and copy shortcodes.
* **Click to play** by default, so pages stay fast and nothing loads from the game studio until a visitor presses play.
* **Fullscreen button**, responsive layouts and portrait, square or landscape artwork for every game.
* **Language and currency** for each game, defaulting to your site's language.
* **Schema.org VideoGame markup** for embedded games (optional).
* **Built-in caching**: catalog data is stored on your site, so embeds don't slow your pages down.
* No API key or account needed.

= Quick start =

1. Edit a post and add the **Demo Game** block.
2. Type a game name (e.g. "Sweet Bonanza") or pick a studio.
3. Click the game. Done.

Prefer shortcodes? Go to **Exactplay Spin → Game Library**, find a game and click **Copy shortcode**:

`[exactplay_game game="netent/twin-spin"]`

`[exactplay_games provider="hacksaw" count="8" columns="4"]`

= Shortcode reference =

`[exactplay_game]` embeds one game.

* `game`: the game to embed as `studio/game`, e.g. `pragmatic-play/sweet-bonanza` (required)
* `display`: `click` (click to play) or `iframe` (load immediately)
* `ratio`: `16:9`, `3:2`, `4:3`, `21:9` or `1:1`
* `lang`: game language, e.g. `en`, `de`, `es`
* `currency`: demo currency, e.g. `EUR`, `USD`, `GBP`
* `channel`: `auto`, `web` (desktop) or `mobile`
* `caption`: `yes` or `no` to show the title and fullscreen button

`[exactplay_games]` shows a grid of games.

* `games`: comma-separated list of games to show, e.g. `netent/twin-spin, relax-gaming/money-train-2`
* `provider`: show games from one studio, e.g. `hacksaw`
* `order`: `random` (refreshed every 15 minutes) or `catalog`
* `count`: number of games (default 12)
* `columns`: maximum columns, 1 to 8 (default 4)
* `image`: `portrait`, `square` or `landscape`
* `click`: `modal` (pop-up player) or `tab` (new tab)
* `show_provider`: `yes` or `no`
* `exclude`: a game slug to leave out
* `lang`, `currency`, `channel`: as above

= Demo play only =

All games run in demo mode with play money. The plugin does not offer real-money gambling. Check that game content suits your audience and follow the advertising rules that apply where you operate.

== Installation ==

1. In your dashboard, go to **Plugins → Add New**, search for "Exactplay Spin" and click **Install Now**, then **Activate**.
2. Optionally visit **Exactplay Spin → Settings** to choose defaults such as language, currency and aspect ratio.
3. Add the **Demo Game** or **Demo Game Grid** block to any post or page, or use the shortcodes.

== Frequently Asked Questions ==

= Do I need an API key or account? =

No. The Exactplay Spin catalog and demo launcher are public. Learn more about the API at [exactplay.com/spin](https://exactplay.com/spin).

= Which studios and games are available? =

The catalog currently has more than 4,000 demo games from 25+ studios, and new games appear automatically. Browse them under **Exactplay Spin → Game Library**, or on [Plentyspins](https://plentyspins.com).

= Can I see a site that uses Exactplay Spin? =

Yes: [Plentyspins](https://plentyspins.com) is built entirely on the Exactplay Spin API.

= Does it work on mobile? =

Yes. Embeds are responsive and each studio serves its mobile layout on phones and tablets. The fullscreen button uses the browser's fullscreen mode where available and otherwise opens the game in a new tab.

= Will embedded games slow down my pages? =

Not by default. In "click to play" mode only the game's artwork loads with the page; the game itself loads when the visitor presses play. Catalog data is cached on your site (12 hours by default).

= A game I embedded shows "no longer available". =

Studios occasionally retire demo games. Replace it with another game using the block toolbar's **Replace game** button. This notice is only shown to logged-in editors; visitors see nothing.

= Does the plugin add links to my site? =

Not unless you ask it to. There is an optional "Powered by Exactplay Spin" credit line under **Exactplay Spin → Settings**, which is off by default.

= New games aren't showing up yet. =

Catalog data is cached. Click **Clear cache** under **Exactplay Spin → Settings** to fetch the latest games immediately.

== External services ==

This plugin relies on the **Exactplay Spin API**, a third-party service operated by Exactplay, to list and launch demo games. Without it the plugin cannot show any games.

* **Catalog requests (from your server).** When an editor searches or browses games, and when a page with a game or game grid is displayed and the cached data has expired, your site requests game lists, studio lists and game details from `https://gameserver.exactplay.com`. These requests contain only search terms, studio and game identifiers, and page numbers. No information about your visitors is sent.
* **Game launches (from the visitor's browser).** When a visitor presses play, or when the page loads if "Load the game immediately" is enabled, the visitor's browser loads the game from `https://gameserver.exactplay.com`, which forwards it to the game studio's servers. The visitor's IP address, browser details, the page address (as the referrer) and the chosen language and currency are sent. The studio may set cookies to run the game.
* **Game artwork (from the visitor's browser).** Thumbnails and backgrounds are loaded from `https://s3.exactplay.com` when a page with an embed is displayed.

Exactplay [Terms of Service](https://exactplay.com/terms-of-service) and [Privacy Policy](https://exactplay.com/privacy-policy).

Links to [Plentyspins](https://plentyspins.com) appear in the admin screens and, only if you enable the credit line, on your site. No data is sent to Plentyspins unless someone clicks one of those links.

== Screenshots ==

1. Search the catalog and embed a game from the Demo Game block.
2. An embedded game with click-to-play artwork and a fullscreen button.
3. The Demo Game Grid block with portrait artwork.
4. Games from a grid open in a pop-up player.
5. The Game Library: browse games, preview them and copy shortcodes.
6. Settings for language, currency, layout and caching.

== Changelog ==

= 1.0.0 =
* First release: Demo Game and Demo Game Grid blocks, shortcodes, Game Library, settings and caching.

== Upgrade Notice ==

= 1.0.0 =
First release.
