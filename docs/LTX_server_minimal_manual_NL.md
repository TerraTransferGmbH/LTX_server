# LTX-server – minimale versie: installatie- en beheerhandleiding

Versie 1.0, oktober 2026. (C) TerraTransfer GmbH, www.terratransfer.de

## 1. Overzicht

De minimale versie van de LTX-server is een kleine PHP-webapplicatie die de gegevens van
TerraTransfer LTX-dataloggers via HTTP of HTTPS ontvangt en opslaat als platte tekstbestanden, met
één map per datalogger. Een met een wachtwoord beveiligde webinterface toont de status van de
dataloggers, exporteert gegevens als CSV, tekent grafieken en stuurt parameterbestanden of
commando's naar de dataloggers.

Er is geen database en geen verbinding met internet nodig. De server maakt nooit contact met een
andere host; het enige netwerkverkeer bestaat uit de verbindingen die de dataloggers met de server
opzetten. Alle gegevens blijven op uw server.

In vergelijking met de volledige LTX-server ontbreken: dashboard, gebruikersbeheer, e-mail- of
SMS-alarmen, doorsturen van gegevens (push) naar andere systemen, automatische gegevensretentie,
firmware-updates via de server, kaartweergave.

De dataloggers zelf worden ter plaatse via Bluetooth in bedrijf gesteld met de webapp Aquatos
Field (hoofdstuk 5); de server ontvangt alleen hun gegevens en geeft parameterbestanden en
commando's door.

De dataloggers zelf worden ter plaatse via Bluetooth in bedrijf gesteld met de webapp Aquatos
Field (hoofdstuk 5); de server ontvangt alleen hun gegevens en geeft parameterbestanden en
commando's door.

## 2. Vereisten

| Onderdeel | Vereiste |
|---|---|
| Hardware | Linux of Windows, kleine VM of single-board computer, bijv. 1 CPU-kern, 1 GB RAM |
| Webserver | Apache 2.4 of nginx (elke versie met PHP-FPM) |
| PHP | 7.4 of nieuwer, 8.x aanbevolen; extensies `session`, `ctype`, `iconv` (standaard) en `gd` (grafieken) |
| Schijfruimte | Tekstbestanden, doorgaans 2–3 MB per datalogger per jaar bij een interval van 15 minuten |
| Netwerk | De dataloggers bereiken de server via HTTP of HTTPS op poort 80 of 443 (LAN, VPN of privé-APN) |
| Tijd | Correcte systeemtijd (UTC); de server stuurt bij elk contact zijn tijd naar de dataloggers |

Niet nodig: database, mailserver, cronjobs, curl, internettoegang.

## 3. Installatie

De applicatie bestaat uit drie mappen: `sw/` (apparaateindpunt en configuratie), `legacy/`
(webinterface) en `docs/` (deze handleiding). De onderstaande voorbeelden installeren de applicatie
in de map `ltx` van de webroot, zodat de URL's `http://<server>/ltx/...` luiden. Elke andere mapnaam
werkt ook; pas de paden dan dienovereenkomstig aan.

### 3.1 Linux met Apache (Debian/Ubuntu)

```
sudo apt install apache2 php libapache2-mod-php php-gd
sudo mkdir -p /var/www/html/ltx
sudo unzip LTX_server_minimal_*.zip -d /var/www/html/        # creates /var/www/html/ltx
sudo mkdir -p /var/lib/ltx-data
sudo chown www-data:www-data /var/lib/ltx-data
sudo chmod 750 /var/lib/ltx-data
```

Bewerk `/var/www/html/ltx/sw/conf/api_key.inc.php` (zie hoofdstuk 4) en herstart daarna Apache
(`sudo systemctl restart apache2`). Op RHEL/Rocky/Alma heten de pakketten `httpd php php-gd` en is
de gebruiker van de webserver `apache`.

Als u de gegevensmap binnen de webroot laat staan (standaard `data_secret` naast `sw/`), moet Apache
`.htaccess`-bestanden respecteren: stel in de virtuele host `AllowOverride All` (of `AuthConfig`) in
voor de map. De server maakt `data_secret/.htaccess` met `Require all denied` automatisch aan.

### 3.2 Linux met nginx

```
sudo apt install nginx php-fpm php-gd
```

Serverblok (vervang `php8.2-fpm` door uw PHP-FPM-versie):

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

Maak de gegevensmap aan zoals in paragraaf 3.1 (gebruiker `www-data`) en laad nginx opnieuw.

### 3.3 Windows met XAMPP

1. Installeer XAMPP (Apache + PHP 8.x) van apachefriends.org.
2. Pak het ZIP-bestand uit in `C:\xampp\htdocs\`, zodat `C:\xampp\htdocs\ltx\sw\lxu_v1.php`
   bestaat.
3. Controleer in `C:\xampp\php\php.ini` of de regel `extension=gd` aanwezig is en niet is
   uitgecommentarieerd (`;` verwijderd). Herstart Apache via het XAMPP Control Panel.
4. Maak een gegevensmap aan, bijv. `C:\ltx-data`, en voer deze in de configuratie in als
   `C:/ltx-data` (voorwaartse schuine strepen).
5. Configureer Apache als Windows-service (XAMPP Control Panel), zodat deze automatisch start.
6. Zorg dat de Windows-tijd wordt gesynchroniseerd (de dataloggers nemen hun tijd over van de
   server).

### 3.4 HTTPS (optioneel)

Configureer HTTPS in de webserver zoals gebruikelijk (Apache `mod_ssl`, nginx `ssl_certificate` of
een reverse proxy). De dataloggers moeten het servercertificaat accepteren: voordat u de dataloggers
op HTTPS overzet, moet u met TerraTransfer afstemmen welk certificaat (openbare CA of uw eigen CA)
de dataloggers vertrouwen. De webinterface kan zonder enige wijziging via HTTPS worden gebruikt.

## 4. Configuratie

Alle instellingen staan in `sw/conf/api_key.inc.php`:

| Instelling | Betekenis |
|---|---|
| `S_DATA` | Gegevensmap. Standaard `../data_secret` (relatief ten opzichte van `sw/` en `legacy/`). Aanbevolen: een absoluut pad buiten de webroot, bijv. `/var/lib/ltx-data` of `C:/ltx-data`. De gebruiker van de webserver heeft schrijfrechten nodig. |
| `L_KEY` | Wachtwoord van de webinterface. Kies een lang wachtwoord; het is de enige beveiliging van de interface. |
| `D_API_KEY` | Apparaatsleutel. Elke datalogger stuurt deze sleutel mee bij elk contact. De waarde wordt met TerraTransfer overeengekomen en in de dataloggers opgeslagen. |
| `DB_QUOTA` | Begininhoud van `quota_days.dat` van nieuwe dataloggers (dagen, regels). In deze versie alleen ter informatie. |
| `MAXM_2GM`, `MAXM_NB`, `MXGET_MEM` | Maximale uploadgrootte per contact voor 2G/LTE-M, voor LTE-NB en de multiblock-limiet. Houd de standaardwaarden aan, tenzij TerraTransfer anders adviseert. |

De server weigert elk verzoek zolang `L_KEY` of `D_API_KEY` nog de tijdelijke aanduiding
`CHANGE_ME` bevat.

PHP-instellingen (`php.ini`): de standaardwaarden volstaan. Als u via de webinterface bestanden
groter dan 2 MB naar een datalogger wilt uploaden, verhoog dan `upload_max_filesize` en
`post_max_size`. Stel `date.timezone = UTC` in om waarschuwingen te voorkomen.

## 5. Dataloggers verbinden

Elke datalogger heeft de volgende instellingen nodig; deze zijn opgeslagen in het
systeemparameterbestand (`sys_param.lxp`) van de datalogger en worden normaal gesproken door
TerraTransfer vóór levering ingesteld:

- hostnaam of IP-adres van de server, bijv. `ltx.example.local` of `192.168.1.10`;
- pad van het apparaateindpunt: `ltx/sw/lxu_v1.php` (wijzig `ltx` als u de applicatie elders hebt
  geïnstalleerd);
- de apparaatsleutel (`D_API_KEY`);
- HTTP of HTTPS, poort 80 of 443.

Geef deze waarden bij het bestellen van dataloggers door aan TerraTransfer, of wijzig ze zelf via de
webinterface (paragraaf 7.4, bestand `sys_param.lxp`). Een onjuiste waarde in `sys_param.lxp`
verbreekt de verbinding tussen de datalogger en de server; wijzig dit bestand daarom met zorg.

**Configuratie ter plaatse met de webapp Aquatos Field.** De server kan de eerste configuratie van
een datalogger niet uitvoeren. Inbedrijfstelling en onderhoud ter plaatse gebeuren via Bluetooth
met de webapp Aquatos Field van TerraTransfer
([aquatos-field.netlify.app](https://aquatos-field.netlify.app)): eerste configuratie
(serveradres, apparaatsleutel, APN van de simkaart, meet- en verzendintervallen), testmetingen,
tarreren (nulstelling) van niveausensoren, uitlezen van het geheugen van de datalogger, zelftest
en PDF-rapporten. De app draait in de browser via Web Bluetooth (Chrome of Edge op desktop en
Android, iOS via de browser Bluefy) en hoeft niet te worden geïnstalleerd. Latere wijzigingen van
de parameterbestanden kunnen ook in de webinterface van de server worden gedaan (paragraaf 7.4);
de datalogger past ze toe bij het volgende contact.

Verloop van een contact: de datalogger roept `http(s)://<server>/ltx/sw/lxu_v1.php?k=<key>&s=<MAC>`
aan en uploadt zijn nieuwe gegevens (HTTP POST). Het antwoord bevat de servertijd en openstaande
commando's. Met de standaardinstellingen meet een datalogger elke 15 minuten en neemt hij eens per
uur contact op met de server; beide intervallen zijn parameters van de datalogger.

## 6. Eerste test

1. Open `http://<server>/ltx/` in een browser. U wordt doorgestuurd naar de webinterface. Log in
   met `L_KEY`.
2. De apparaatlijst is leeg totdat de eerste datalogger contact heeft opgenomen. Het hoofdlogboek
   (`<S_DATA>/log/log.txt`, link "Main Logfile" op de startpagina) toont elk contact, ook geweigerde
   contacten, bijv. `(ERROR:'API Key')` bij een onjuiste apparaatsleutel.
3. Na het eerste contact verschijnt de datalogger in de lijst met zijn MAC-adres (16 hexadecimale
   cijfers) en zijn naam. Open de apparaatpagina en controleer "Last Contact" en "Files on Device".
   Bij de eerste contacten worden de bestandslijst en de parameterbestanden overgedragen; de
   gegevens volgen bij de eerstvolgende reguliere upload.

## 7. Webinterface

### 7.1 Inloggen, gastmodus en demomodus

`legacy/index.php` is de startpagina. Zonder inloggen staat de pagina in de gastmodus (alleen-lezen)
en toont alleen dataloggers die als demoapparaten zijn gemarkeerd ("Enable Demo-Mode" op de
startpagina na het inloggen). Inloggen met `L_KEY` geeft volledige toegang; "Logout" beëindigt de
sessie. De pagina's worden elke 15–30 seconden automatisch vernieuwd.

### 7.2 Apparaatlijst

Voor elke datalogger toont de lijst het MAC-adres (link naar de apparaatpagina), de apparaatnaam uit
`iparam.lxp`, de "legacy name" (een vrije tekst die u op de server kunt instellen), het land van het
mobiele netwerk en het tijdstip van het laatste contact. De kleur van het laatste contact geeft aan
hoe lang geleden dat was: groen = minder dan 2 minuten, lichtgeel = meer dan 2 uur, geel = meer dan
4 uur, roze = meer dan 12 uur, donkerroze = meer dan 24 uur, rood = meer dan 3 dagen, magenta = meer
dan 14 dagen.

Onder de lijst: links naar het hoofdlogbestand, naar de bestandsbrowser (alleen de gegevensmap) en
naar de PHP-informatiepagina.

### 7.3 Apparaatpagina

**Device Info**: naam, legacy name, laatste contact met de reden (AUTO = regulier, MANUAL = op de
datalogger zelf gestart, RESET, ALARM), klokafwijking van de datalogger, transmissietellers,
firmwareversie en -datum, apparaattype, SIM (ICCID), vrij geheugen op de datalogger, mobiel signaal
(dBm, netwerktype, land), positie als de datalogger er een meldt, laatste gebruikerscommando en de
status ervan. "Last Transmission OK" toont de laatste bevestiging van een commando; `[OK]` wist
deze.

**Files on Device**: de map van de datalogger zoals vastgesteld bij de laatste scan. `[GET DIR]`
vraagt bij het volgende contact een nieuwe bestandslijst op. Per bestand: lengte, datum en vlaggen
op het apparaat, en de status van de lokale kopie ("mirror") op de server: `UP-TO-DATE` betekent
dat de server het volledige bestand heeft.

- `data.edt`: `[Open local]` ruwe tekst, `[View CSV]` in de browser naar CSV omgezet, `[CSV(D)]`
  download met Duitse getalnotatie (komma, puntkomma), `[CSV(Int)]` download met decimale punt,
  `[CSVIEW]` grafiek.
- `iparam.lxp`, `sys_param.lxp`: `[Open local]` en `[Edit]` (paragraaf 7.4).
- `[GET(remote)]` vraagt de datalogger het volledige bestand bij het volgende contact te uploaden
  (nuttig als de mirror onvolledig is). `[DEL(remote)]` verwijdert het bestand op de datalogger.
  Beide worden in de wachtrij geplaatst en bij het volgende contact uitgevoerd; tot dat moment
  kunnen de items in de wachtrij weer worden verwijderd.

**Cumulative data on server**: `out_total/total.edt` bevat alles wat de server ooit van deze
datalogger heeft ontvangen, in volgorde van binnenkomst. Dezelfde links voor weergave, download en
grafiek zijn beschikbaar. Gebruik dit bestand voor archivering en langetermijnanalyse; `data.edt` is
slechts een mirror van het bestand op de datalogger en wordt vervangen wanneer de datalogger een
nieuw bestand begint.

**Files to 'put'**: bestanden die wachten op overdracht naar de datalogger (paragraaf 7.4), met het
aantal pogingen. `[Remove]` annuleert de overdracht.

**Manage**: links naar de logbestanden van de datalogger (contacten, verbindingen,
gebruikerscommando's), "Upload Files to Device's Filesystem" (een willekeurig bestand, bijv. een
voorbereid parameterbestand), "Send Server Command (Byte)" en "Send User Command (Text)" (commando's
die door de firmware van de datalogger zijn gedefinieerd; gebruik deze alleen in opdracht van
TerraTransfer), `[Enable DEBUG]` (slaat elke upload in ruwe vorm op in `dbg/`; schakel dit na de
diagnose weer uit).

### 7.4 Parameterbestanden bewerken

`[Edit]` naast `iparam.lxp` of `sys_param.lxp` opent een formulier met één invoerveld per regel en
een korte beschrijving van de bekende velden. "Submit" slaat het bestand op in de wachtrij `put/`;
de datalogger ontvangt het bij het volgende contact en past het toe. `iparam.lxp` bevat de meet- en
verzendintervallen, de kanaaldefinities (eenheid, decimalen, schaling, alarmgrenzen) en de
apparaatnaam. `sys_param.lxp` bevat de netwerk- en serverinstellingen (APN, server, pad, sleutel).
Wijzig alleen velden waarvan u de betekenis kent; neem bij twijfel contact op met TerraTransfer.

### 7.5 Grafieken (CSVIEW)

`[CSVIEW]` zet het gegevensbestand om naar CSV en tekent een grafiek (PHP-extensie `gd` vereist).
Met de selectievakjes schakelt u kanalen in en uit, de pijlen scrollen, de vergrootglasknoppen
zoomen en "Export CSV" downloadt de weergegeven gegevens. De tijdelijke CSV-bestanden worden
opgeslagen in `<S_DATA>/stemp` en na twee uur automatisch verwijderd.

## 8. Gegevensopslag

### 8.1 Mapstructuur

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

Alle bestanden zijn platte tekst, behalve de ruwe uploads in `dbg/`.

### 8.2 EDT-gegevensformaat

EDT ("Easy Data") is een formaat van platte tekst (UTF-8) met één record per regel:

```
!U 0:cm 1:°C 2:us/cm 90:V(Bat) 91:°C(int) 92:%rH(int)
!1791806400 0:-43.917045 1:13.50 2:1.23 90:3.638 91:16.3 92:9.4
!+900 0:-43.917045 1:13.50 2:1.26
<NT AUTO OK(637,51,0)>
```

- `!U` – eenheden van de kanalen; verschijnt aan het begin en telkens wanneer de parameters
  veranderen.
- `!<seconds>` – meting met een absolute UTC-tijdstempel (Unix-seconden), gevolgd door
  `channel:value`-paren.
- `!+<seconds>` – meting ten opzichte van de vorige regel.
- Kanalen 0–89 zijn metingen, 90–99 systeemwaarden (housekeeping) van de datalogger
  (batterijspanning, interne temperatuur en luchtvochtigheid). Een regel bevat alleen de kanalen die
  op dat moment zijn gemeten.
- `NoReply` in plaats van een waarde: de sensor heeft niet geantwoord. Een waarde die met `*`
  begint, heeft een alarmgrens overschreden.
- Regels die met `<` beginnen zijn gebeurtenissen: `<NT AUTO OK(…)>` upload, `<NT AUTO ERROR …>`
  mislukte upload, `<RESET …>` herstart, `<COOKIE …>` tijdsynchronisatie, `<NEW>` begin van een
  bestand. Na een herstart begint de klok van de datalogger tot de volgende synchronisatie bij nul
  te tellen; zulke regels verschijnen met datums in 1970.
- Regels die met `$` beginnen zijn gecomprimeerde records; de webinterface decodeert ze.

### 8.3 CSV-export

De CSV-weergaven en -downloads hebben de kolommen `NO` (regelnummer in het EDT-bestand), `TIME(UTC)`
(`dd.mm.yy hh:mm:ss`) en één kolom per kanaal, met de naam `unit(channel)`. Ontbrekende kanalen in
een regel blijven leeg. `[CSV(D)]` gebruikt puntkomma's en een decimale komma, `[CSV(Int)]` komma's
en een decimale punt. De URL-parameter `utc=<seconds>` verschuift de tijdkolom, bijv. `utc=3600`
voor UTC+1.

## 9. Beheer

**Back-up**: maak regelmatig een back-up van de gegevensmap met een willekeurig back-upprogramma
voor bestanden. De bestanden worden bij elk contact van een datalogger geschreven; een back-up die
tijdens een contact wordt gemaakt, kan in `total.edt` eindigen met een onvolledige laatste regel,
die de viewer als tekst toont; andere gegevens worden niet beïnvloed.

**Schijfruimte**: `out_total/total.edt` groeit onbeperkt (ongeveer 2–3 MB per jaar en datalogger bij
een interval van 15 minuten). Om te archiveren verplaatst u het bestand of kapt u het af tussen twee
contacten van de datalogger; de volgende upload gaat verder in een nieuw bestand. `stemp/` en `dbg/`
kunnen op elk moment worden geleegd.

**Monitoring**: de apparaatlijst toont de ouderdom van het laatste contact in kleur. Een datalogger
die langer dan tweemaal zijn verzendinterval geen contact opneemt, moet worden gecontroleerd
(voeding, SIM, signaal). Het hoofdlogboek toont geweigerde contacten, bijv. een datalogger met een
onjuiste sleutel.

**Logbestanden**: `<S_DATA>/log/log.txt` (alle contacten, één regel per contact, met het resultaat
en de uitvoeringstijd), `<MAC>/log.txt` (contacten van één datalogger), `<MAC>/conn_log.txt`
(netwerkdetails), `<MAC>/userio.txt` (gebruikerscommando's en antwoorden). De logbestanden roteren
automatisch; de vorige versie blijft bewaard als `_…_old.txt`.

**Tijd**: houd de serverklok gesynchroniseerd (NTP). De dataloggers nemen bij elk contact de
servertijd over; een onjuiste servertijd leidt tot onjuiste tijdstempels in de gegevens.

**Updates**: vervang voor het installeren van een nieuwe versie de mappen `sw/`, `legacy/` en
`docs/`, maar behoud `sw/conf/api_key.inc.php` en de gegevensmap. Controleer de release notes op
gewijzigde instellingen.

**Verhuizen naar een andere server**: installeer de software op de nieuwe server, kopieer de
gegevensmap en de configuratie en wijzig vervolgens het serveradres in de dataloggers (hoofdstuk 5)
of laat de oude hostnaam naar de nieuwe server verwijzen. Dataloggers bewaren hun gegevens tot de
volgende geslaagde upload.

## 10. Probleemoplossing

| Symptoom | Oorzaak / wat te doen |
|---|---|
| Browser toont `ERROR: 'Server not configured (sw/conf/api_key.inc.php)'` | De tijdelijke aanduidingen `CHANGE_ME` staan nog in de configuratie. |
| Datalogger verschijnt niet, hoofdlogboek toont `(ERROR:'API Key')` | De apparaatsleutel in de datalogger wijkt af van `D_API_KEY`. |
| Datalogger verschijnt niet, niets in het hoofdlogboek | De datalogger kan de server niet bereiken: controleer hostnaam/IP, pad, poort, firewall, APN/VPN; controleer de instellingen van de datalogger ter plaatse met de webapp Aquatos Field (hoofdstuk 5); schakel in overleg met TerraTransfer de debugmodus van de datalogger in. |
| Hoofdlogboek blijft leeg hoewel dataloggers contact opnemen, PHP-waarschuwingen `mkdir`/`fopen` … `Permission denied` in het foutenlogboek van de webserver | Gegevensmap niet beschrijfbaar voor de gebruiker van de webserver, of onjuist pad in `S_DATA`. |
| Apparaatpagina toont `(no Directory Scan)` | Nog geen volledig contact geweest, of de eerste contacten zijn mislukt. Gebruik `[GET DIR]`. |
| Mirror `data.edt` onvolledig (`ERROR: Real-Local`) | Gebruik `[GET(remote)]` om het hele bestand bij het volgende contact op te halen. |
| `[CSVIEW]` toont `the PHP extension 'gd' is required` | Schakel `gd` in `php.ini` in en herstart de webserver. |
| Inloggen werkt niet hoewel het wachtwoord juist is | PHP-sessies kunnen niet worden opgeslagen (`session.save_path` niet beschrijfbaar) of cookies worden geblokkeerd. |
| Tijdstempels in 1970 | De datalogger is herstart en heeft de servertijd nog niet ontvangen; wordt bij het volgende contact opgelost. |
| Gegevensmap bereikbaar in de browser | Apache: `AllowOverride` ontbreekt; nginx: voeg de `deny all`-location toe. Beter: verplaats de map buiten de webroot. |

## 11. Beveiligingsaanwijzingen

- De webinterface heeft één wachtwoord met volledige rechten. Beperk de toegang ertoe (firewall,
  VPN of een extra authenticatie in de webserver) wanneer de server bereikbaar is vanaf
  niet-vertrouwde netwerken.
- Dataloggers sturen de apparaatsleutel mee bij elk contact. Gebruik HTTPS of een privénetwerk (VPN,
  privé-APN).
- Houd de gegevensmap buiten de webroot of beschermd door de webserver; de map bevat de
  apparaatsleutel (`dapikey.dat`).
- Houd PHP en de webserver bijgewerkt.
