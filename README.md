# Green Mind Tools
Green Mind Agency Tools

## Prompt Generator
https://greenmindagency.com/tools/

### Local Development
- Open `prompt-generator/index.php` in your browser.
- Add new `.php` files to `prompt-generator/` to create tools.
- The `quotation-creator` folder contains a tool for building PDF price quotes from the Green Mind Agency price list. It parses the page using CSS class names and stores the results in `pricing-cache.json` so the tool works offline. Run `php quotation-creator/update-cache.php` on a network-enabled host to refresh the cache with live data.
- Cards on each index page are generated automatically and the top navigation is rendered via `nav.php`, which lists every generator in a dropdown.
- Common layout elements come from `header.php` and `footer.php`.

### SEO Platform Backups
Backups are created manually from the dashboard using the **Backup Now**
button. The seven most recent backup files are kept for each client.
