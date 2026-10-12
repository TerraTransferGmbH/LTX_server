# LTX server – minimal version: installation and operation manual

Version 1.0, October 2026. (C) TerraTransfer GmbH, www.terratransfer.de

## 1. Overview

The minimal version of the LTX server is a small PHP web application that receives the data of
TerraTransfer LTX data loggers via HTTP or HTTPS and stores it as plain text files, one directory
per logger. A password-protected web interface shows the status of the loggers, exports data as
CSV, draws charts and sends parameter files or commands to the loggers.

It needs no database and no connection to the Internet. The server never contacts any other
host; the only network traffic is the loggers calling the server. All data stays on your server.

Not included compared with the full LTX server: dashboard, user management, e-mail or SMS
alarms, data push to other systems, automatic data retention, firmware updates via the server,
map view.

## 2. Requirements

| Item | Requirement |
|---|---|
| Hardware | Linux or Windows, small VM or single-board computer, e.g. 1 CPU core, 1 GB RAM |
| Web server | Apache 2.4 or nginx (any version with PHP-FPM) |
| PHP | 7.4 or newer, 8.x recommended; extensions `session`, `ctype`, `iconv` (default) and `gd` (charts) |
| Disk | Text files, typically 2–3 MB per logger and year at 15-minute intervals |
| Network | Loggers reach the server via HTTP or HTTPS on port 80 or 443 (LAN, VPN or private APN) |
| Time | Correct system time (UTC); the server sends its time to the loggers at every contact |

Not needed: database, mail server, cron jobs, curl, Internet access.

## 3. Installation

The application consists of three directories: `sw/` (device endpoint and configuration),
`legacy/` (web interface) and `docs/` (this manual). The examples below install into the
directory `ltx` of the web root, so the URLs are `http://<server>/ltx/...`. Any other directory
name works; adjust the paths accordingly.

### 3.1 Linux with Apache (Debian/Ubuntu)

```
sudo apt install apache2 php libapache2-mod-php php-gd
sudo mkdir -p /var/www/html/ltx
sudo unzip LTX_server_minimal_*.zip -d /var/www/html/        # creates /var/www/html/ltx
sudo mkdir -p /var/lib/ltx-data
sudo chown www-data:www-data /var/lib/ltx-data
sudo chmod 750 /var/lib/ltx-data
```

Edit `/var/www/html/ltx/sw/conf/api_key.inc.php` (see section 4), then restart Apache
(`sudo systemctl restart apache2`). On RHEL/Rocky/Alma the packages are `httpd php php-gd`
and the web server user is `apache`.

If you keep the data directory inside the web root (default `data_secret` next to `sw/`), Apache
must honour `.htaccess` files: set `AllowOverride All` (or `AuthConfig`) for the directory in the
virtual host. The server creates `data_secret/.htaccess` with `Require all denied` automatically.

### 3.2 Linux with nginx

```
sudo apt install nginx php-fpm php-gd
```

Server block (replace `php8.2-fpm` by your PHP-FPM version):

```
server {
    listen 80;
    server_name ltx.example.local;
    root /var/www/html;
    index index.php;

    location ~ ^/ltx/data_secret/ { deny all; }       # only if the data directory is inside the web root
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

Create the data directory as in 3.1 (user `www-data`) and reload nginx.

### 3.3 Windows with XAMPP

1. Install XAMPP (Apache + PHP 8.x) from apachefriends.org.
2. Unpack the ZIP into `C:\xampp\htdocs\` so that `C:\xampp\htdocs\ltx\sw\lxu_v1.php` exists.
3. In `C:\xampp\php\php.ini` make sure the line `extension=gd` is present and not commented out
   (`;` removed). Restart Apache from the XAMPP control panel.
4. Create a data directory, e.g. `C:\ltx-data`, and enter it in the configuration as
   `C:/ltx-data` (forward slashes).
5. Configure Apache as a Windows service (XAMPP control panel) so that it starts automatically.
6. Make sure the Windows time is synchronised (the loggers take their time from the server).

### 3.4 HTTPS (optional)

Configure HTTPS in the web server as usual (Apache `mod_ssl`, nginx `ssl_certificate`, or a
reverse proxy). The loggers must accept the server certificate: before switching the loggers to
HTTPS, agree with TerraTransfer which certificate (public CA or your own CA) the loggers trust.
The web interface can be used via HTTPS without any change.

## 4. Configuration

All settings are in `sw/conf/api_key.inc.php`:

| Setting | Meaning |
|---|---|
| `S_DATA` | Data directory. Default `../data_secret` (relative to `sw/` and `legacy/`). Recommended: an absolute path outside the web root, e.g. `/var/lib/ltx-data` or `C:/ltx-data`. The web server user needs write access. |
| `L_KEY` | Password of the web interface. Choose a long password; it is the only protection of the interface. |
| `D_API_KEY` | Device key. Every logger sends this key with each contact. The value is agreed with TerraTransfer and stored in the loggers. |
| `DB_QUOTA` | Initial content of `quota_days.dat` of new loggers (days, lines). Informational only in this version. |
| `MAXM_2GM`, `MAXM_NB`, `MXGET_MEM` | Maximum upload size per contact for 2G/LTE-M, for LTE-NB, and the multiblock limit. Keep the defaults unless TerraTransfer advises otherwise. |

The server refuses every request while `L_KEY` or `D_API_KEY` still contain the placeholder
`CHANGE_ME`.

PHP settings (`php.ini`): the defaults are sufficient. If you want to upload files larger than
2 MB to a logger via the web interface, raise `upload_max_filesize` and `post_max_size`.
Set `date.timezone = UTC` to avoid warnings.

## 5. Connecting the loggers

Each logger needs the following settings; they are stored in its system parameter file
(`sys_param.lxp`) and are normally set by TerraTransfer before delivery:

- host name or IP address of the server, e.g. `ltx.example.local` or `192.168.1.10`;
- path of the device endpoint: `ltx/sw/lxu_v1.php` (change `ltx` if you installed elsewhere);
- the device key (`D_API_KEY`);
- HTTP or HTTPS, port 80 or 443.

Please send these values to TerraTransfer when ordering loggers, or change them yourself via the
web interface (section 7.4, file `sys_param.lxp`). A wrong value in `sys_param.lxp` disconnects
the logger from the server, so change this file with care.

Contact sequence: the logger calls `http(s)://<server>/ltx/sw/lxu_v1.php?k=<key>&s=<MAC>` and
uploads its new data (HTTP POST). The reply contains the server time and pending commands. With
the default settings a logger measures every 15 minutes and calls the server once per hour; both
intervals are logger parameters.

## 6. First test

1. Open `http://<server>/ltx/` in a browser. You are redirected to the web interface. Log in
   with `L_KEY`.
2. The device list is empty until the first logger has called. The main log
   (`<S_DATA>/log/log.txt`, link "Main Logfile" on the start page) shows every contact, including
   rejected ones, e.g. `(ERROR:'API Key')` for a wrong device key.
3. After the first contact the logger appears in the list with its MAC address (16 hex digits)
   and its name. Open the device page and check "Last Contact" and "Files on Device". The first
   contacts transfer the directory listing and the parameter files; data follows with the next
   regular upload.

## 7. Web interface

### 7.1 Login, guest mode and demo mode

`legacy/index.php` is the start page. Without login the page is in guest mode (read-only) and
shows only loggers that were marked as demo devices ("Enable Demo-Mode" on the start page after
login). Logging in with `L_KEY` gives full access; "Logout" ends the session. The pages refresh
automatically every 15–30 seconds.

### 7.2 Device list

For each logger the list shows the MAC address (link to the device page), the device name from
`iparam.lxp`, the "legacy name" (a free text you can set on the server), the country of the mobile
network and the time of the last contact. The colour of the last contact shows how long ago it
was: green = less than 2 minutes, light yellow = more than 2 hours, yellow = more than 4 hours,
pink = more than 12 hours, dark pink = more than 24 hours, red = more than 3 days, magenta = more
than 14 days.

Below the list: links to the main log file, to the file browser (data directory only) and to the
PHP information page.

### 7.3 Device page

**Device Info**: name, legacy name, last contact with the reason (AUTO = regular, MANUAL =
triggered at the logger, RESET, ALARM), clock deviation of the logger, transmission counters,
firmware version and date, device type, SIM (ICCID), free memory on the logger, mobile signal
(dBm, network type, country), position if the logger reports one, last user command and its
state. "Last Transmission OK" shows the last confirmation of a command; `[OK]` clears it.

**Files on Device**: the directory of the logger as seen at the last directory scan.
`[GET DIR]` requests a new directory listing with the next contact. For each file: length, date
and flags on the device, and the state of the local copy ("mirror") on the server: `UP-TO-DATE`
means the server has the complete file.

- `data.edt`: `[Open local]` raw text, `[View CSV]` converted to CSV in the browser, `[CSV(D)]`
  download with German number format (comma, semicolon), `[CSV(Int)]` download with decimal
  point, `[CSVIEW]` chart.
- `iparam.lxp`, `sys_param.lxp`: `[Open local]` and `[Edit]` (section 7.4).
- `[GET(remote)]` asks the logger to upload the complete file with the next contact (useful if
  the mirror is incomplete). `[DEL(remote)]` deletes the file on the logger. Both are queued and
  executed at the next contact; the queue entries can be removed again until then.

**Cumulative data on server**: `out_total/total.edt` contains everything the server ever received
from this logger, in the order of arrival. The same view, download and chart links are available.
Use this file for archiving and long-term evaluation; `data.edt` is only a mirror of the file on
the logger and is replaced when the logger starts a new file.

**Files to 'put'**: files waiting to be transferred to the logger (section 7.4), with the number
of attempts. `[Remove]` cancels the transfer.

**Manage**: links to the log files of the logger (contacts, connections, user commands), "Upload Files to Device's Filesystem" (any file, e.g. a prepared parameter file),
"Send Server Command (Byte)" and "Send User Command (Text)" (commands defined by the logger
firmware; use only as instructed by TerraTransfer), `[Enable DEBUG]` (stores every upload in raw
form in `dbg/`; switch it off again after the diagnosis).

### 7.4 Editing parameter files

`[Edit]` next to `iparam.lxp` or `sys_param.lxp` opens a form with one input field per line and
a short description of the known fields. "Submit" stores the file in the `put/` queue; the logger
receives it with the next contact and applies it. `iparam.lxp` contains the measurement and
transmission intervals, the channel definitions (unit, decimals, scaling, alarm limits) and the
device name. `sys_param.lxp` contains the network and server settings (APN, server, path, key).
Only change fields whose meaning you know; ask TerraTransfer in case of doubt.

### 7.5 Charts (CSVIEW)

`[CSVIEW]` converts the data file to CSV and draws a chart (PHP extension `gd` required). The
checkboxes switch channels on and off, the arrows scroll, the magnifier buttons zoom, "Export CSV"
downloads the data shown. The temporary CSV files are stored in `<S_DATA>/stemp` and deleted
automatically after two hours.

## 8. Data storage

### 8.1 Directory layout

```
<S_DATA>/
  .htaccess                       denies web access (Apache)
  log/log.txt, _log_old.txt       main log of all contacts (rotated at 100 kB)
  stemp/                          temporary CSV files of the chart viewer
  <MAC>/                          one directory per logger, 16 hex digits
    files/data.edt                mirror of the data file on the logger (EDT format)
    files/data.edt.bak            previous version of the mirror
    files/iparam.lxp              parameters of the logger
    files/sys_param.lxp           system parameters (network, server, key)
    out_total/total.edt           cumulative data: every received fragment is appended here
    in_new/                       fragments of the current upload (moved to total.edt at once)
    cmd/                          metadata of the device files (*.vmeta, *.fmeta), pending commands
    get/ put/ del/                queues: files to fetch from / send to / delete on the logger
    dbg/                          raw uploads while debug is enabled
    device_info.dat               status written at every contact (key/value)
    log.txt, _log_old.txt         log of this logger (rotated at 50 kB)
    conn_log.txt                  mobile network information per contact
    userio.txt                    user commands and replies
    dapikey.dat                   device key as accepted for this logger
    quota_days.dat                retention settings (informational)
    user_info.dat                 legacy name set in the web interface
    date0.dat                     time of the first contact
```

All files are plain text except the raw uploads in `dbg/`.

### 8.2 EDT data format

EDT ("Easy Data") is a plain text format, UTF-8, one record per line:

```
!U 0:cm 1:°C 2:us/cm 90:V(Bat) 91:°C(int) 92:%rH(int)
!1791806400 0:-43.917045 1:13.50 2:1.23 90:3.638 91:16.3 92:9.4
!+900 0:-43.917045 1:13.50 2:1.26
<NT AUTO OK(637,51,0)>
```

- `!U` – units of the channels; appears at the start and whenever the parameters change.
- `!<seconds>` – measurement with an absolute UTC timestamp (Unix seconds) followed by
  `channel:value` pairs.
- `!+<seconds>` – measurement relative to the previous line.
- Channels 0–89 are measurements, 90–99 housekeeping values of the logger (battery voltage,
  internal temperature and humidity). A line contains only the channels measured at that time.
- `NoReply` instead of a value: the sensor did not answer. A value starting with `*` exceeded an
  alarm limit.
- Lines starting with `<` are events: `<NT AUTO OK(…)>` upload, `<NT AUTO ERROR …>` failed
  upload, `<RESET …>` restart, `<COOKIE …>` time synchronisation, `<NEW>` start of a file.
  After a restart the logger clock starts at zero until the next synchronisation; such lines
  appear with dates in 1970.
- Lines starting with `$` are compressed records; the web interface decodes them.

### 8.3 CSV export

The CSV views and downloads have the columns `NO` (line number in the EDT file), `TIME(UTC)`
(`dd.mm.yy hh:mm:ss`) and one column per channel, named `unit(channel)`. Missing channels in a
line are empty. `[CSV(D)]` uses semicolons and a decimal comma, `[CSV(Int)]` commas and a
decimal point. The URL parameter `utc=<seconds>` shifts the time column, e.g. `utc=3600` for
UTC+1.

## 9. Operation

**Backup**: back up the data directory regularly with any file backup tool. The files are
written at every logger contact; a backup taken during a contact may end with an incomplete
last line in `total.edt`, which the viewer shows as text; no other data is affected.

**Disk space**: `out_total/total.edt` grows without limit (about 2–3 MB per year and logger at
15-minute intervals). To archive, move or truncate the file between two contacts of the logger;
the next upload continues in a new file. `stemp/` and `dbg/` can be emptied at any time.

**Monitoring**: the device list shows the age of the last contact in colour. A logger that does
not call for longer than twice its transmission interval should be checked (power, SIM, signal).
The main log shows rejected contacts, e.g. a logger with a wrong key.

**Logs**: `<S_DATA>/log/log.txt` (all contacts, one line each, with the result and the run
time), `<MAC>/log.txt` (contacts of one logger), `<MAC>/conn_log.txt` (network details),
`<MAC>/userio.txt` (user commands and replies). The logs rotate
automatically; the previous version is kept as `_…_old.txt`.

**Time**: keep the server clock synchronised (NTP). The loggers take the server time at every
contact; a wrong server time produces wrong timestamps in the data.

**Updates**: to install a new version, replace the directories `sw/`, `legacy/` and `docs/`,
keep `sw/conf/api_key.inc.php` and the data directory. Check the version notes for changed
settings.

**Moving to another server**: install the software on the new server, copy the data directory
and the configuration, then change the server address in the loggers (section 5) or redirect
the old host name. Loggers keep their data until the next successful upload.

## 10. Troubleshooting

| Symptom | Cause / what to do |
|---|---|
| Browser shows `ERROR: 'Server not configured (sw/conf/api_key.inc.php)'` | Placeholders `CHANGE_ME` still in the configuration. |
| Logger does not appear, main log shows `(ERROR:'API Key')` | Device key in the logger differs from `D_API_KEY`. |
| Logger does not appear, nothing in the main log | Logger cannot reach the server: check host name/IP, path, port, firewall, APN/VPN; enable debug in the logger with TerraTransfer. |
| Main log stays empty although loggers call, PHP warnings `mkdir`/`fopen` … `Permission denied` in the web server error log | Data directory not writable for the web server user, or wrong `S_DATA` path. |
| Device page shows `(no Directory Scan)` | No complete contact yet, or the first contacts failed. Use `[GET DIR]`. |
| Mirror `data.edt` incomplete (`ERROR: Real-Local`) | Use `[GET(remote)]` to fetch the whole file at the next contact. |
| `[CSVIEW]` shows `the PHP extension 'gd' is required` | Enable `gd` in `php.ini` and restart the web server. |
| Login does not work although the password is right | PHP sessions cannot be stored (`session.save_path` not writable) or cookies blocked. |
| Timestamps in 1970 | Logger was restarted and has not received the server time yet; resolved at the next contact. |
| Data directory reachable in the browser | Apache: `AllowOverride` missing; nginx: add the `deny all` location. Better: move the directory outside the web root. |

## 11. Security notes

- The web interface has a single password with full rights. Restrict access to it (firewall,
  VPN, or an additional authentication in the web server) when the server is reachable from
  untrusted networks.
- Loggers send the device key with every contact. Use HTTPS or a private network (VPN, private
  APN).
- Keep the data directory outside the web root or protected by the web server; it contains the
  device key (`dapikey.dat`).
- Keep PHP and the web server updated.
