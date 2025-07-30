# Green Mind Tools
Green Mind Agency Tools

## Prompt Generator
https://greenmindagency.com/tools/

### Local Development
- Open `prompt-generator/index.php` in your browser.
- Add new `.php` files to `prompt-generator/` to create tools.
- The `quotation-creator` folder now stores quotes in a MySQL database and provides an admin page to edit client tables. Use the **Refresh live pricing** button (or run `php quotation-creator/update-cache.php`) to download the latest data into `pricing-cache.json`. PDF export was removed in favour of a **Publish** button that generates a shareable link displaying the quote only.
- Cards on each index page are generated automatically and the top navigation is rendered via `nav.php`, which lists every generator in a dropdown.
- Common layout elements come from `header.php` and `footer.php`.

### SEO Platform Backups
Backups are created manually from the dashboard using the **Backup Now**
button. The seven most recent backup files are kept for each client.
