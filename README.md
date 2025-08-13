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
A simple prototype that uploads Word, PDF, PowerPoint, or text files and uses a Python script to generate full home page content with section titles and subtitles. The script reports clear errors when optional Python libraries for document parsing are missing, making it usable on shared hosting. Install dependencies with `pip install -r wordprseo-website-builder/requirements.txt` and start the tool by opening `wordprseo-website-builder/index.php` in your browser.