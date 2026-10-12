# LTX server – minimal version (file mode)

(C) TerraTransfer GmbH – www.terratransfer.de

A small PHP web application that receives the data of TerraTransfer LTX data loggers via
HTTP or HTTPS and stores it as text files, one directory per logger. A password-protected
web page shows the status of the loggers, exports the data as CSV, draws simple charts and
sends parameter files or commands to the loggers.

No database, no Internet connection and no external services are needed. The server
never contacts another host; no data leaves your network.

**Not included** (compared with the full LTX server): dashboard, user management, e-mail
alarms, data push/export to other systems, automatic data retention, firmware updates via
the server, map view.

## Requirements

- Linux or Windows, a small VM or single-board computer is sufficient (e.g. 1 CPU core, 1 GB RAM).
- Apache or nginx with PHP 7.4 or newer (8.x recommended), PHP extension `gd` (charts),
  `session`, `ctype`, `iconv` (usually enabled by default). No `curl`, no mail, no cron.
- A writable data directory, preferably outside the web root. Text storage, typically a few MB
  per logger and year.
- The loggers must reach the server via HTTP or HTTPS (port 80 or 443) inside your network
  (LAN, VPN or private APN).
- Correct system time (UTC); the server sends its time to the loggers.

## Installation

1. Copy the directory tree to the web server, e.g. to `/var/www/html/ltx` (Linux) or
   `C:\xampp\htdocs\ltx` (Windows/XAMPP). The web server user needs read access to the files.
2. Edit `sw/conf/api_key.inc.php`:
   - `S_DATA`: data directory. Default `../data_secret` (next to `sw/` and `legacy/`, i.e. inside
     the web root; an `.htaccess` file that denies web access is created automatically for Apache).
     Recommended: an absolute path outside the web root, e.g. `/var/lib/ltx-data` or `C:/ltx-data`.
   - `L_KEY`: password of the web interface.
   - `D_API_KEY`: device key, the value agreed with TerraTransfer and stored in the loggers.
   The server refuses to work while the placeholders `CHANGE_ME` are still present.
3. Create the data directory and make it writable for the web server user, e.g. on Linux:
   `mkdir -p /var/lib/ltx-data && chown www-data:www-data /var/lib/ltx-data` (Debian/Ubuntu;
   on RHEL the user is `apache`). On Windows/XAMPP no special rights are needed.
4. Web server:
   - Apache: PHP enabled, `AllowOverride All` (or at least `AuthConfig`) for the data directory
     if it is inside the web root. Nothing else.
   - nginx: standard PHP-FPM setup. If the data directory is inside the web root, deny access to it:
     `location ^~ /ltx/data_secret/ { deny all; }`.
   - PHP: `upload_max_filesize` and `post_max_size` of at least 2M (default); `session` enabled.
5. Open `http://<server>/ltx/` in a browser. You are redirected to the web interface
   (`legacy/index.php`). Log in with the password `L_KEY`. The list is empty until the first
   logger has called the server.

## Logger configuration

Each logger needs (set by TerraTransfer or via the parameter file `sys_param.lxp`):

- server host name or IP address (e.g. `ltx.example.local` or `192.168.1.10`),
- path `ltx/sw/lxu_v1.php` (adjust the first part if you installed into another directory),
- the device key (`D_API_KEY`),
- HTTP or HTTPS. For HTTPS the certificate must be accepted by the loggers.

The logger calls `http(s)://<server>/ltx/sw/lxu_v1.php?k=<key>&s=<MAC>` and uploads its new
data. The reply contains the server time and pending commands.

## Directory layout of the data directory

```
<S_DATA>/log/log.txt               main log (all contacts), rotated at 100 kB
<S_DATA>/<MAC>/                    one directory per logger (16 hex digits)
    files/data.edt                 mirror of the data file on the logger (EDT format)
    files/iparam.lxp, sys_param.lxp  current parameter files of the logger
    out_total/total.edt            cumulative data: every uploaded fragment is appended here
    in_new/                        uploaded fragments, moved to out_total/total.edt immediately
    cmd/, get/, put/, del/         commands and file transfer queues (used by the web interface)
    device_info.dat                status (counters, signal, memory, last contact)
    log.txt, conn_log.txt          log of this logger / network info per contact
    quota_days.dat, user_info.dat  settings of the web interface
```

`files/data.edt` is replaced when the logger starts a new data file. `out_total/total.edt`
keeps everything the server ever received; it is plain text and can be archived or trimmed
with normal tools (stop the web server or do it between contacts).

## Data format (EDT)

Plain text, UTF-8. `!U 0:cm 1:°C ...` lists the units of the channels; `!<seconds> 0:1.23 1:14.5`
is a measurement with an absolute UTC time (Unix seconds); `!+900 ...` is relative to the
previous line; channels 0–89 are measurements, 90–99 housekeeping values (battery, internal
temperature and humidity); `NoReply` means the sensor did not answer; a value starting with
`*` exceeded an alarm limit; lines starting with `<` are events (upload, reset, time sync).

The web interface converts EDT to CSV (`[View CSV]`, `[CSV(D)]` German decimal comma,
`[CSV(Int)]` decimal point) and shows charts (`[CSVIEW]`), both for the device mirror and
for the cumulative file.

## Operation and maintenance

- Back up the data directory regularly (plain files, any backup tool).
- Disk space: watch `out_total/total.edt`; a logger with 15-minute intervals produces
  roughly 2–3 MB per year.
- Logs: `<S_DATA>/log/log.txt` shows every contact with the result; errors are marked
  `(ERROR:...)`. Each logger has its own `log.txt`.
- The `[Enable DEBUG]` link of a logger stores the raw uploads in `dbg/` for diagnosis;
  disable it again afterwards.
- Updating the software: replace the directories `sw/` and `legacy/` but keep
  `sw/conf/api_key.inc.php` and the data directory.

## Security notes

- Use HTTPS or a private network for the loggers; the device key is sent with every contact.
- Keep the data directory outside the web root or protected by the web server.
- The web interface has one password (`L_KEY`) with full rights; restrict access to it
  (firewall, VPN or an additional web server authentication) if the server is reachable
  from untrusted networks.
