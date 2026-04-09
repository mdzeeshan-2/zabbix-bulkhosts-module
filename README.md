# Zabbix Bulk Host Manager (module)

A [Zabbix](https://www.zabbix.com/) frontend module to **create**, **delete**, and **modify** many hosts at once from a text list (one host per line or `hostname,ip`), with groups, templates, proxy, interfaces, and macros—using the same multiselect patterns as the built-in host form.

**Supported Zabbix versions:** **6.0.x** and **6.4+** (tested with the 6.0 and 6.4 frontend APIs; Super Admin / Zabbix Admin only).

---

## Features
<img width="1501" height="748" alt="Screenshot 2026-04-09 at 8 35 52 PM" src="https://github.com/user-attachments/assets/f286d187-4798-45a4-a224-3b9f797d340e" />
<img width="1408" height="746" alt="Screenshot 2026-04-09 at 8 37 19 PM" src="https://github.com/user-attachments/assets/4fc30a60-7f11-401f-9703-af7dc3eed143" />
<img width="1406" height="750" alt="Screenshot 2026-04-09 at 8 37 33 PM" src="https://github.com/user-attachments/assets/97d5b5b6-fedc-4735-b953-0908df23db84" />

| Tab | What it does |
|-----|----------------|
| **Create hosts** | Bulk-create hosts from a list; optional templates, groups (required), proxy, agent/SNMP/JMX/IPMI interface, IP vs DNS, optional `{$IPADDRESSES}` (or custom) macro and extra macros. |
| **Delete hosts** | Resolve hosts by technical name or agent interface IP, then delete. |
| **Modify hosts** | Add/remove host groups, link/unlink/clear templates, change proxy; same list format as above. |

- **Native UI:** Host groups and templates use Zabbix **CMultiSelect** (search + **Select** popup), aligned with **Configuration → Hosts → Create host**.
- **Feedback:** Short flash message at the top (no large JSON panel).
- **API:** Operations go through Zabbix’s PHP API (`Host.create`, `Host.delete`, `Host.massadd`, `Host.massremove`, `Host.update` as applicable).

---

## Requirements

- Zabbix **frontend** 6.0+ with **modules** support enabled (default).
- User must be **Super Admin** or **Zabbix Admin** (same as the menu registration in `Module.php`).
- On **Zabbix 6.4+**, CSRF protection applies to the JSON API action (`bulkhosts.api`).

---

## Installation

The module must live in a directory named **`bulkhosts`** under the Zabbix web **`modules`** folder, because `manifest.json` declares `"id": "bulkhosts"`.

### 1. Copy the module files

**From this repository**

```bash
# Example: install into a typical Zabbix web root
sudo cp -a bulkhosts /usr/share/zabbix/modules/
```

If you cloned this repo (folder might be named `zabbix-bulkhosts-module` or similar), copy **the contents** so the final path is:

```text
/usr/share/zabbix/modules/bulkhosts/
```

Expected layout:

```text
modules/bulkhosts/
├── manifest.json
├── Module.php
├── actions/
├── views/
├── public/
│   └── bulkhosts.js
└── helpers/
```

**Docker**

If the frontend container bind-mounts a local `modules` directory (for example `./modules:/usr/share/zabbix/modules`), place the `bulkhosts` folder next to your compose file:

```text
your-project/modules/bulkhosts/   # same files as above
```

Then restart the web container so PHP/opcache picks up new files:

```bash
docker compose restart zabbix-web
```

(Exact service name may differ, e.g. `zabbix-web-nginx`.)

### 2. Fix ownership (bare metal / package install)

Match the user your PHP/web server uses, for example:

```bash
sudo chown -R nginx:nginx /usr/share/zabbix/modules/bulkhosts
# or www-data:www-data, apache:apache, etc.
```

### 3. Enable the module in Zabbix

1. Log in as **Super Admin** (or **Zabbix Admin**).
2. Open **Administration → General → Modules**.
3. Click **Scan directory** (or refresh the list) so **Bulk Host Manager** appears.
4. Set the module to **Enabled** and **Update**.

### 4. Open the UI

- **Configuration → Bulk host manager**  
  If your menu theme does not show it under Configuration, check **Administration** (the module registers under whichever parent menu exists in your build).

Default URL pattern:

```text
zabbix.php?action=bulkhosts.form
```

---

## Usage (short)

### Host list format

- One entry per line; lines starting with `#` are ignored.
- **IP only:** `192.0.2.10` — a technical host name is generated automatically.
- **Host + IP:** `web-01,192.0.2.11` (CSV-style; supports quoted fields).

You can also load a `.csv` / `.txt` file into the text area from the **Load from file** control.

### Create hosts

1. Paste or load the list.
2. Select **Templates** and **Groups** (groups are required) via the multiselect controls.
3. Set proxy, interface type, port, IP vs DNS, enabled state, and macros as needed.
4. Use **Add** to submit; **Cancel** returns to **Configuration → Hosts**.

### Delete / Modify

Same list format; use **Delete** or **Update** in the corresponding tab. **Cancel** goes to the host list.

---

## Development / repository layout

| Path | Role |
|------|------|
| `manifest.json` | Module id, namespace, actions (`bulkhosts.form`, `bulkhosts.api`). |
| `Module.php` | Menu entry, assets URL, `CWidget` alias on 6.0. |
| `actions/BulkHostForm.php` | UI action; loads proxies for dropdowns. |
| `actions/BulkHostApi.php` | JSON API for create/delete/update. |
| `views/bulkhosts.form.view.php` | Main UI (tabs, multiselects, `makeFormFooter`). |
| `public/bulkhosts.js` | Browser `fetch` to `bulkhosts.api`. |

---

## Troubleshooting

| Issue | What to try |
|-------|-------------|
| Module not listed | Path must be `modules/bulkhosts/`, then **Scan directory** and enable. |
| Blank tab content | Clear browser cookies for the site (global Zabbix `tab` cookie) or use a private window; ensure you are on a supported Zabbix 6.x frontend. |
| Changes not visible | Restart `zabbix-web` / clear opcache after file edits. |
| Permission denied | User must be Zabbix Admin or Super Admin. |

---

## License

You may license your fork as you prefer. If you base this on Zabbix’s own code patterns, comply with [Zabbix’s GPL v2](https://www.zabbix.com/license) where it applies to combined works.

---

## Publishing to GitHub

For **[github.com/mdzeeshan-2](https://github.com/mdzeeshan-2)**, follow the step-by-step guide in **[GITHUB_SETUP.md](./GITHUB_SETUP.md)** (create the empty repo on GitHub, then push from your machine).

Short version: create a new repo under your account, then from this project root:

```bash
git init
git add .
git commit -m "Initial commit: Zabbix Bulk Host Manager module"
git branch -M main
git remote add origin https://github.com/mdzeeshan-2/YOUR_REPO_NAME.git
git push -u origin main
```

After cloning, **rename the project folder to `bulkhosts`** (or copy only that folder) when installing under Zabbix `modules/`.

---

## Contributing

Issues and pull requests are welcome (documentation, translations, compatibility fixes for newer Zabbix minors).
