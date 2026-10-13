# Server LTX – versione minima: manuale di installazione e gestione operativa

Versione 1.0, ottobre 2026. (C) TerraTransfer GmbH, www.terratransfer.de

## 1. Panoramica

La versione minima del server LTX è una piccola applicazione web in PHP che riceve, tramite HTTP o
HTTPS, i dati dei datalogger LTX di TerraTransfer e li memorizza come semplici file di testo, con
una directory per ciascun datalogger. Un'interfaccia web protetta da password mostra lo stato dei
datalogger, esporta i dati in formato CSV, disegna grafici e invia ai datalogger file dei
parametri o comandi.

Non sono necessari né un database né un collegamento a Internet. Il server non contatta mai altri
host; l'unico traffico di rete è costituito dalle chiamate dei datalogger al server. Tutti i dati
restano sul server dell'utente.

Rispetto al server LTX completo non sono inclusi: dashboard, gestione degli utenti, allarmi via
e-mail o SMS, invio (push) dei dati ad altri sistemi, gestione automatica della conservazione dei
dati, aggiornamenti del firmware tramite il server, visualizzazione su mappa.

I datalogger stessi vengono messi in servizio sul posto via Bluetooth con l'applicazione web
Aquatos Field (sezione 5); il server si limita a ricevere i loro dati e a trasmettere file dei
parametri e comandi.

## 2. Requisiti

| Voce | Requisito |
|---|---|
| Hardware | Linux o Windows, piccola macchina virtuale (VM) o computer a scheda singola, ad es. 1 core CPU, 1 GB di RAM |
| Server web | Apache 2.4 o nginx (qualsiasi versione con PHP-FPM) |
| PHP | 7.4 o successivo, consigliato 8.x; estensioni `session`, `ctype`, `iconv` (abilitate per impostazione predefinita) e `gd` (grafici) |
| Disco | File di testo, tipicamente 2–3 MB per datalogger e per anno con intervalli di 15 minuti |
| Rete | I datalogger raggiungono il server tramite HTTP o HTTPS sulla porta 80 o 443 (LAN, VPN o APN privato) |
| Ora | Ora di sistema corretta (UTC); il server invia la propria ora ai datalogger a ogni contatto |

Non sono necessari: database, server di posta, job cron, curl, accesso a Internet.

## 3. Installazione

L'applicazione è composta da tre directory: `sw/` (endpoint dei dispositivi e configurazione),
`legacy/` (interfaccia web) e `docs/` (il presente manuale). Gli esempi riportati di seguito
eseguono l'installazione nella directory `ltx` della radice web (document root), per cui gli URL
hanno la forma `http://<server>/ltx/...`. È possibile usare qualsiasi altro nome di directory,
adeguando di conseguenza i percorsi.

### 3.1 Linux con Apache (Debian/Ubuntu)

```
sudo apt install apache2 php libapache2-mod-php php-gd
sudo mkdir -p /var/www/html/ltx
sudo unzip LTX_server_minimal_*.zip -d /var/www/html/        # creates /var/www/html/ltx
sudo mkdir -p /var/lib/ltx-data
sudo chown www-data:www-data /var/lib/ltx-data
sudo chmod 750 /var/lib/ltx-data
```

Modificare il file `/var/www/html/ltx/sw/conf/api_key.inc.php` (vedere la sezione 4), quindi
riavviare Apache (`sudo systemctl restart apache2`). Su RHEL/Rocky/Alma i pacchetti si chiamano
`httpd php php-gd` e l'utente del server web è `apache`.

Se la directory dei dati viene mantenuta all'interno della radice web (per impostazione
predefinita `data_secret`, accanto a `sw/`), Apache deve interpretare i file `.htaccess`:
impostare `AllowOverride All` (oppure `AuthConfig`) per la directory nel virtual host. Il server
crea automaticamente il file `data_secret/.htaccess` con la direttiva `Require all denied`.

### 3.2 Linux con nginx

```
sudo apt install nginx php-fpm php-gd
```

Blocco server (sostituire `php8.2-fpm` con la versione di PHP-FPM in uso):

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

Creare la directory dei dati come nella sezione 3.1 (utente `www-data`) e ricaricare nginx.

### 3.3 Windows con XAMPP

1. Installare XAMPP (Apache + PHP 8.x) dal sito apachefriends.org.
2. Decomprimere il file ZIP in `C:\xampp\htdocs\` in modo che esista il file
   `C:\xampp\htdocs\ltx\sw\lxu_v1.php`.
3. In `C:\xampp\php\php.ini` verificare che la riga `extension=gd` sia presente e non commentata
   (carattere `;` rimosso). Riavviare Apache dal pannello di controllo di XAMPP.
4. Creare una directory dei dati, ad es. `C:\ltx-data`, e inserirla nella configurazione come
   `C:/ltx-data` (barre oblique in avanti).
5. Configurare Apache come servizio di Windows (pannello di controllo di XAMPP) in modo che si
   avvii automaticamente.
6. Verificare che l'ora di Windows sia sincronizzata (i datalogger acquisiscono l'ora dal server).

### 3.4 HTTPS (opzionale)

Configurare HTTPS nel server web nel modo consueto (Apache `mod_ssl`, nginx `ssl_certificate`
oppure un reverse proxy). I datalogger devono accettare il certificato del server: prima di
passare i datalogger a HTTPS, concordare con TerraTransfer quale certificato (CA pubblica o CA
propria) venga considerato attendibile dai datalogger. L'interfaccia web può essere utilizzata
tramite HTTPS senza alcuna modifica.

## 4. Configurazione

Tutte le impostazioni si trovano in `sw/conf/api_key.inc.php`:

| Impostazione | Significato |
|---|---|
| `S_DATA` | Directory dei dati. Valore predefinito: `../data_secret` (relativo a `sw/` e `legacy/`). Consigliato: un percorso assoluto al di fuori della radice web, ad es. `/var/lib/ltx-data` oppure `C:/ltx-data`. L'utente del server web necessita dei permessi di scrittura. |
| `L_KEY` | Password dell'interfaccia web. Scegliere una password lunga: è l'unica protezione dell'interfaccia. |
| `D_API_KEY` | Chiave del dispositivo. Ogni datalogger invia questa chiave a ogni contatto. Il valore viene concordato con TerraTransfer e memorizzato nei datalogger. |
| `DB_QUOTA` | Contenuto iniziale del file `quota_days.dat` dei nuovi datalogger (giorni, righe). In questa versione ha solo scopo informativo. |
| `MAXM_2GM`, `MAXM_NB`, `MXGET_MEM` | Dimensione massima del caricamento a ogni contatto per 2G/LTE-M e per LTE-NB, nonché limite multiblocco. Mantenere i valori predefiniti, salvo diversa indicazione di TerraTransfer. |

Il server rifiuta qualsiasi richiesta finché `L_KEY` o `D_API_KEY` contengono ancora il segnaposto
`CHANGE_ME`.

Impostazioni di PHP (`php.ini`): i valori predefiniti sono sufficienti. Per caricare su un
datalogger, tramite l'interfaccia web, file di dimensioni superiori a 2 MB, aumentare
`upload_max_filesize` e `post_max_size`. Impostare `date.timezone = UTC` per evitare avvisi.

## 5. Collegamento dei datalogger

Ogni datalogger richiede le seguenti impostazioni, memorizzate nel relativo file dei parametri di
sistema (`sys_param.lxp`) e normalmente configurate da TerraTransfer prima della consegna:

- nome host o indirizzo IP del server, ad es. `ltx.example.local` oppure `192.168.1.10`;
- percorso dell'endpoint dei dispositivi: `ltx/sw/lxu_v1.php` (modificare `ltx` se l'installazione è avvenuta in un'altra directory);
- chiave del dispositivo (`D_API_KEY`);
- HTTP o HTTPS, porta 80 o 443.

Comunicare questi valori a TerraTransfer al momento dell'ordine dei datalogger oppure modificarli
autonomamente tramite l'interfaccia web (sezione 7.4, file `sys_param.lxp`). Un valore errato in
`sys_param.lxp` scollega il datalogger dal server: modificare quindi questo file con cautela.

**Configurazione sul posto con l'applicazione web Aquatos Field.** Il server non può eseguire la
configurazione iniziale di un datalogger. La messa in servizio e la manutenzione sul posto si
effettuano via Bluetooth con l'applicazione web Aquatos Field di TerraTransfer
([aquatos-field.netlify.app](https://aquatos-field.netlify.app)): configurazione iniziale
(indirizzo del server, chiave del dispositivo, APN della scheda SIM, intervalli di misura e di
trasmissione), misure di prova, taratura (azzeramento) dei sensori di livello, lettura della
memoria del datalogger, autotest e rapporti PDF. L'applicazione funziona nel browser tramite Web
Bluetooth (Chrome o Edge su desktop e Android, iOS tramite il browser Bluefy) e non richiede
alcuna installazione. Le modifiche successive ai file dei parametri possono essere effettuate
anche nell'interfaccia web del server (sezione 7.4); il datalogger le applica al contatto
successivo.

Sequenza di contatto: il datalogger chiama `http(s)://<server>/ltx/sw/lxu_v1.php?k=<key>&s=<MAC>`
e carica i propri nuovi dati (HTTP POST). La risposta contiene l'ora del server e i comandi in
attesa. Con le impostazioni predefinite un datalogger esegue una misurazione ogni 15 minuti e
contatta il server una volta all'ora; entrambi gli intervalli sono parametri del datalogger.

## 6. Primo test

1. Aprire `http://<server>/ltx/` in un browser. Il browser viene reindirizzato all'interfaccia web.
   Effettuare il login con `L_KEY`.
2. L'elenco dei dispositivi resta vuoto finché il primo datalogger non ha effettuato un contatto.
   Il log principale (`<S_DATA>/log/log.txt`, link "Main Logfile" nella pagina iniziale) mostra
   ogni contatto, compresi quelli rifiutati, ad es. `(ERROR:'API Key')` in caso di chiave del
   dispositivo errata.
3. Dopo il primo contatto il datalogger compare nell'elenco con il proprio indirizzo MAC (16 cifre
   esadecimali) e il proprio nome. Aprire la pagina del dispositivo e controllare "Last Contact" e
   "Files on Device". I primi contatti trasferiscono l'elenco della directory e i file dei
   parametri; i dati seguono con il successivo caricamento regolare.

## 7. Interfaccia web

### 7.1 Login, modalità ospite e modalità demo

`legacy/index.php` è la pagina iniziale. Senza login la pagina è in modalità ospite (sola lettura)
e mostra soltanto i datalogger contrassegnati come dispositivi demo ("Enable Demo-Mode" nella
pagina iniziale dopo il login). Il login con `L_KEY` consente l'accesso completo; "Logout"
termina la sessione. Le pagine si aggiornano automaticamente ogni 15–30 secondi.

### 7.2 Elenco dei dispositivi

Per ogni datalogger l'elenco mostra l'indirizzo MAC (link alla pagina del dispositivo), il nome del
dispositivo ricavato da `iparam.lxp`, il "legacy name" (un testo libero impostabile sul server),
il paese della rete mobile e l'ora dell'ultimo contatto. Il colore dell'ultimo contatto indica da
quanto tempo esso è avvenuto: verde = meno di 2 minuti, giallo chiaro = più di 2 ore, giallo = più
di 4 ore, rosa = più di 12 ore, rosa scuro = più di 24 ore, rosso = più di 3 giorni, magenta =
più di 14 giorni.

Sotto l'elenco: link al file di log principale, al browser dei file (solo la directory dei dati)
e alla pagina di informazioni su PHP.

### 7.3 Pagina del dispositivo

**Device Info**: nome, legacy name, ultimo contatto con il relativo motivo (AUTO = regolare,
MANUAL = avviato sul datalogger, RESET, ALARM), scostamento dell'orologio del datalogger, contatori
delle trasmissioni, versione e data del firmware, tipo di dispositivo, SIM (ICCID), memoria libera
sul datalogger, segnale della rete mobile (dBm, tipo di rete, paese), posizione, se il datalogger
ne comunica una, ultimo comando utente e relativo stato. "Last Transmission OK" mostra l'ultima
conferma di un comando; `[OK]` la cancella.

**Files on Device**: la directory del datalogger quale risulta dall'ultima scansione della
directory. `[GET DIR]` richiede un nuovo elenco della directory al contatto successivo. Per ogni
file sono indicati lunghezza, data e flag sul dispositivo, nonché lo stato della copia locale
("mirror") sul server: `UP-TO-DATE` significa che il server dispone del file completo.

- `data.edt`: `[Open local]` testo grezzo, `[View CSV]` conversione in CSV nel browser, `[CSV(D)]`
  download con formato numerico tedesco (virgola, punto e virgola), `[CSV(Int)]` download con
  punto decimale, `[CSVIEW]` grafico.
- `iparam.lxp`, `sys_param.lxp`: `[Open local]` e `[Edit]` (sezione 7.4).
- `[GET(remote)]` chiede al datalogger di caricare il file completo al contatto successivo (utile
  se il mirror è incompleto). `[DEL(remote)]` elimina il file sul datalogger. Entrambe le richieste
  vengono messe in coda ed eseguite al contatto successivo; fino ad allora è possibile rimuovere
  nuovamente le voci dalla coda.

**Cumulative data on server**: `out_total/total.edt` contiene tutto ciò che il server ha mai
ricevuto da questo datalogger, in ordine di arrivo. Sono disponibili gli stessi link per
visualizzazione, download e grafico. Utilizzare questo file per l'archiviazione e per le analisi a
lungo termine; `data.edt` è soltanto un mirror del file presente sul datalogger e viene sostituito
quando il datalogger inizia un nuovo file.

**Files to 'put'**: file in attesa di essere trasferiti al datalogger (sezione 7.4), con il numero
di tentativi. `[Remove]` annulla il trasferimento.

**Manage**: link ai file di log del datalogger (contatti, connessioni, comandi utente),
"Upload Files to Device's Filesystem" (un file qualsiasi, ad es. un file dei parametri già
preparato), "Send Server Command (Byte)" e "Send User Command (Text)" (comandi definiti dal
firmware del datalogger; da utilizzare solo su istruzione di TerraTransfer), `[Enable DEBUG]`
(memorizza ogni caricamento in forma grezza in `dbg/`; da disattivare di nuovo dopo la diagnosi).

### 7.4 Modifica dei file dei parametri

`[Edit]` accanto a `iparam.lxp` o `sys_param.lxp` apre un modulo con un campo di immissione per
ogni riga e una breve descrizione dei campi noti. "Submit" salva il file nella coda `put/`; il
datalogger lo riceve al contatto successivo e lo applica. `iparam.lxp` contiene gli intervalli di
misurazione e di trasmissione, le definizioni dei canali (unità, cifre decimali, scalatura, soglie
di allarme) e il nome del dispositivo. `sys_param.lxp` contiene le impostazioni di rete e del
server (APN, server, percorso, chiave). Modificare solo i campi di cui si conosce il significato;
in caso di dubbio rivolgersi a TerraTransfer.

### 7.5 Grafici (CSVIEW)

`[CSVIEW]` converte il file dei dati in CSV e disegna un grafico (è richiesta l'estensione PHP
`gd`). Le caselle di controllo attivano e disattivano i canali, le frecce consentono di scorrere,
i pulsanti con la lente di ingrandimento eseguono lo zoom, "Export CSV" scarica i dati
visualizzati. I file CSV temporanei vengono salvati in `<S_DATA>/stemp` e cancellati
automaticamente dopo due ore.

## 8. Memorizzazione dei dati

### 8.1 Struttura delle directory

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

Tutti i file sono file di testo semplice, ad eccezione dei caricamenti grezzi in `dbg/`.

### 8.2 Formato dei dati EDT

EDT ("Easy Data") è un formato di testo semplice, UTF-8, con un record per riga:

```
!U 0:cm 1:°C 2:us/cm 90:V(Bat) 91:°C(int) 92:%rH(int)
!1791806400 0:-43.917045 1:13.50 2:1.23 90:3.638 91:16.3 92:9.4
!+900 0:-43.917045 1:13.50 2:1.26
<NT AUTO OK(637,51,0)>
```

- `!U` – unità di misura dei canali; compare all'inizio e ogni volta che i parametri cambiano.
- `!<seconds>` – misurazione con timestamp UTC assoluto (secondi Unix), seguito da coppie
  `channel:value`.
- `!+<seconds>` – misurazione relativa alla riga precedente.
- I canali 0–89 sono misurazioni, i canali 90–99 valori di servizio del datalogger (tensione della
  batteria, temperatura e umidità interne). Una riga contiene soltanto i canali misurati in quel
  momento.
- `NoReply` al posto di un valore: il sensore non ha risposto. Un valore che inizia con `*` ha
  superato una soglia di allarme.
- Le righe che iniziano con `<` sono eventi: `<NT AUTO OK(…)>` caricamento, `<NT AUTO ERROR …>`
  caricamento non riuscito, `<RESET …>` riavvio, `<COOKIE …>` sincronizzazione dell'ora, `<NEW>`
  inizio di un file. Dopo un riavvio l'orologio del datalogger riparte da zero fino alla successiva
  sincronizzazione; tali righe compaiono con date del 1970.
- Le righe che iniziano con `$` sono record compressi; l'interfaccia web li decodifica.

### 8.3 Esportazione CSV

Le visualizzazioni e i download CSV contengono le colonne `NO` (numero di riga nel file EDT),
`TIME(UTC)` (`dd.mm.yy hh:mm:ss`) e una colonna per ciascun canale, denominata `unit(channel)`. I
canali assenti in una riga restano vuoti. `[CSV(D)]` usa il punto e virgola e la virgola decimale,
`[CSV(Int)]` la virgola e il punto decimale. Il parametro URL `utc=<seconds>` sposta la colonna
dell'ora, ad es. `utc=3600` per UTC+1.

## 9. Gestione operativa

**Backup**: eseguire regolarmente il backup della directory dei dati con un qualsiasi strumento di
backup dei file. I file vengono scritti a ogni contatto del datalogger; un backup eseguito durante
un contatto può terminare con un'ultima riga incompleta in `total.edt`, che il visualizzatore
mostra come testo; gli altri dati non ne risentono.

**Spazio su disco**: `out_total/total.edt` cresce senza limiti (circa 2–3 MB per anno e per
datalogger con intervalli di 15 minuti). Per archiviare il file, spostarlo o troncarlo tra due
contatti del datalogger; il caricamento successivo prosegue in un nuovo file. Le directory
`stemp/` e `dbg/` possono essere svuotate in qualsiasi momento.

**Monitoraggio**: l'elenco dei dispositivi mostra con un colore da quanto tempo è avvenuto
l'ultimo contatto. Un datalogger che non contatta il server per più del doppio del proprio
intervallo di trasmissione va controllato (alimentazione, SIM, segnale). Il log principale mostra i
contatti rifiutati, ad es. quelli di un datalogger con chiave errata.

**Log**: `<S_DATA>/log/log.txt` (tutti i contatti, una riga ciascuno, con l'esito e il tempo di
esecuzione), `<MAC>/log.txt` (contatti di un singolo datalogger), `<MAC>/conn_log.txt` (dettagli di
rete), `<MAC>/userio.txt` (comandi utente e risposte). I log vengono ruotati
automaticamente; la versione precedente viene conservata come `_…_old.txt`.

**Ora**: mantenere sincronizzato l'orologio del server (NTP). I datalogger acquisiscono l'ora del
server a ogni contatto; un'ora errata del server produce timestamp errati nei dati.

**Aggiornamenti**: per installare una nuova versione, sostituire le directory `sw/`, `legacy/` e
`docs/`, conservando `sw/conf/api_key.inc.php` e la directory dei dati. Consultare le note di
versione per verificare eventuali modifiche alle impostazioni.

**Trasferimento su un altro server**: installare il software sul nuovo server, copiare la
directory dei dati e la configurazione, quindi modificare l'indirizzo del server nei datalogger
(sezione 5) oppure reindirizzare il vecchio nome host. I datalogger conservano i propri dati fino
al successivo caricamento riuscito.

## 10. Risoluzione dei problemi

| Sintomo | Causa / rimedio |
|---|---|
| Il browser mostra `ERROR: 'Server not configured (sw/conf/api_key.inc.php)'` | Nella configurazione sono ancora presenti i segnaposto `CHANGE_ME`. |
| Il datalogger non compare, il log principale mostra `(ERROR:'API Key')` | La chiave del dispositivo nel datalogger è diversa da `D_API_KEY`. |
| Il datalogger non compare, nel log principale non c'è nulla | Il datalogger non riesce a raggiungere il server: verificare nome host/IP, percorso, porta, firewall, APN/VPN; verificare le impostazioni del datalogger sul posto con l'applicazione web Aquatos Field (sezione 5); attivare il debug nel datalogger insieme a TerraTransfer. |
| Il log principale resta vuoto benché i datalogger contattino il server; nel log degli errori del server web compaiono avvisi PHP `mkdir`/`fopen` … `Permission denied` | La directory dei dati non è scrivibile per l'utente del server web, oppure il percorso `S_DATA` è errato. |
| La pagina del dispositivo mostra `(no Directory Scan)` | Non è ancora avvenuto alcun contatto completo, oppure i primi contatti non sono andati a buon fine. Usare `[GET DIR]`. |
| Mirror `data.edt` incompleto (`ERROR: Real-Local`) | Usare `[GET(remote)]` per ottenere l'intero file al contatto successivo. |
| `[CSVIEW]` mostra `the PHP extension 'gd' is required` | Attivare `gd` in `php.ini` e riavviare il server web. |
| Il login non funziona benché la password sia corretta | Le sessioni PHP non possono essere memorizzate (`session.save_path` non scrivibile) oppure i cookie sono bloccati. |
| Timestamp del 1970 | Il datalogger è stato riavviato e non ha ancora ricevuto l'ora del server; il problema si risolve al contatto successivo. |
| La directory dei dati è raggiungibile dal browser | Apache: `AllowOverride` mancante; nginx: aggiungere la location con `deny all`. Meglio ancora: spostare la directory al di fuori della radice web. |

## 11. Note sulla sicurezza

- L'interfaccia web dispone di un'unica password con pieni diritti. Limitarne l'accesso (firewall,
  VPN o un'ulteriore autenticazione nel server web) quando il server è raggiungibile da reti non
  attendibili.
- I datalogger inviano la chiave del dispositivo a ogni contatto. Utilizzare HTTPS oppure una rete
  privata (VPN, APN privato).
- Mantenere la directory dei dati al di fuori della radice web oppure protetta dal server web;
  essa contiene la chiave del dispositivo (`dapikey.dat`).
- Mantenere aggiornati PHP e il server web.
