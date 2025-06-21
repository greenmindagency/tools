# Green Mind Tools
Green Mind Agency Tools

## Prompt Generator
https://greenmindagency.com/tools/

### Local Development
- Open `prompt-generator/index.php` in your browser.
- Add new `.php` files to `prompt-generator/` to create tools.
- Navigation links and cards are generated automatically.
- Common layout elements come from `header.php` and `footer.php`.

### SEO Platform Backups
Run `seo-platform/cron_backup.php` daily to create CSV backups of each client's keywords. Backups are timestamped using the Africa/Cairo time zone and only the seven most recent files are kept per client. Example cron entry:

```
0 0 * * * php /path/to/tools/seo-platform/cron_backup.php
```

You can also create a backup on demand from the dashboard using the **Backup Now** button next to the restore dropdown.
