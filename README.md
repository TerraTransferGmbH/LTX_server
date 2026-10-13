# LTX server – minimal version (file mode)

(C) TerraTransfer GmbH – www.terratransfer.de

Receives the data of TerraTransfer LTX data loggers via HTTP/HTTPS and stores it as plain text
files, one directory per logger. Password-protected web interface with device status, CSV export,
charts and parameter/command transfer to the loggers. No database, no Internet connection, no
external services.

**Documentation**: `docs/LTX_server_minimal_manual.md` (English) and the translations
`docs/LTX_server_minimal_manual_RU.md` (Russian), `_NL.md` (Dutch), `_FR.md` (French) and
`_IT.md` (Italian) – installation, configuration, logger setup, web interface, data format,
operation, troubleshooting. HTML and PDF versions are in the same directory.

**Quick start**

1. Copy the directory tree to the web server (PHP 7.4 or newer with `gd`), e.g. `.../htdocs/ltx`.
2. Edit `sw/conf/api_key.inc.php`: data directory `S_DATA` (writable, preferably outside the web
   root), web password `L_KEY`, device key `D_API_KEY`.
3. Open `http://<server>/ltx/` and log in. Loggers call `http(s)://<server>/ltx/sw/lxu_v1.php`.
4. Loggers are configured on site via Bluetooth with the Aquatos Field web app
   (server address, device key, APN, intervals, taring); see the manual, section 5.

**Directories**: `sw/` device endpoint and configuration, `legacy/` web interface, `docs/` manual.
