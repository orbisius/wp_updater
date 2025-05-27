# WordPress Site Updater

**Simple updater script for one or more WordPress sites.**

This script automates WordPress core, plugin, and theme updates. It's designed for **self-hosted staging/demo environments only**, not production sites.

---

## 📂 Installation

Add this script to the **root directory of your WordPress demo installation**, where `wp-config.php` is located.

Always use the latest version:  
**[https://github.com/orbisius/wp_updater](https://github.com/orbisius/wp_updater)**

---

## 🚀 Usage

### From a Web Browser

Single site update:
```bash
https://yourdemo.com/000_wp_updater.php?go=SomeSmartCode
```

Mass update all WordPress installations in the directory:
```bash
https://yourdemo.com/000_wp_updater.php?go=SomeSmartCode&all=1
```

### From the Command Line (CLI)

Single site update:
```bash
php 000_wp_updater.php
```

Or with a specific WordPress path:
```bash
php 000_wp_updater.php /path/to/your/wordpress/installation
```

Mass update all WordPress installations in a directory:
```bash
php 000_wp_updater.php /path/to/parent/dir 1
```

---

## ⚠️ WARNING

This script performs updates to:
- WordPress core
- All installed plugins
- All installed themes

It does **not** create any backups.

This is intended for **demo/staging site environments only**.  
**Do NOT run this on your main production site. It doesn't perform any backups. **  

---

## 🛑 Disclaimer

This script is provided **"AS-IS"** with no warranties.

Use it at your own risk.  
The author (Svetoslav Marinov | [https://orbisius.com](https://orbisius.com))  
is **not responsible** for any damage, data loss, or downtime caused by its use.

---

## 🤝 Need Help?

Need help setting up or customizing this script?

We're available for hire.  
Reach out here: [https://orbisius.com/contact](https://orbisius.com/contact)
