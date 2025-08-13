# Green Mind Tools
Green Mind Agency Tools

## Prompt Generator
https://greenmindagency.com/tools/

### Local Development
- Open `prompt-generator/index.php` in your browser.
- Add new `.php` files to `prompt-generator/` to create tools.
- The `quotation-creator` folder now stores quotes in a MySQL database and provides an admin page to edit client tables. Use the **Refresh live pricing** button (or run `php quotation-creator/update-cache.php`) to download the latest data into `pricing-cache.json`. PDF export was removed in favour of a **Publish** button that generates a shareable link displaying the quote only.
- Access to the quotation creator requires logging in with the admin credentials defined in `quotation-creator/login.php`.
- Cards on each index page are generated automatically and the top navigation is rendered via `nav.php`, which lists every generator in a dropdown.
- Common layout elements come from `header.php` and `footer.php`.

### SEO Platform Backups
Backups are created manually from the dashboard using the **Backup Now**
button. The seven most recent backup files are kept for each client.

## Wordprseo Website Builder
Log in with the admin credentials to access a two-step builder. Start by entering a client name and arranging the site map via drag-and-drop, then switch to the Content tab to upload Word, PDF, PowerPoint, or text files. The PHP backend extracts their text and calls Google Gemini to produce a full page. The API key is defined in `wordprseo-website-builder/index.php`; see `wordprseo-website-builder/INSTRUCTIONS.txt` for usage details.
