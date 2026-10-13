# Serveur LTX – version minimale : manuel d'installation et d'exploitation

Version 1.0, octobre 2026. (C) TerraTransfer GmbH, www.terratransfer.de

## 1. Vue d'ensemble

La version minimale du serveur LTX est une petite application web PHP qui reçoit par HTTP ou HTTPS les
données des enregistreurs de données TerraTransfer LTX et les stocke sous forme de fichiers texte brut,
avec un répertoire par enregistreur. Une interface web protégée par mot de passe affiche l'état des
enregistreurs, exporte les données au format CSV, trace des graphiques et envoie des fichiers de
paramètres ou des commandes aux enregistreurs.

Elle n'a besoin ni d'une base de données ni d'une connexion à Internet. Le serveur ne contacte jamais
d'autre hôte ; le seul trafic réseau est constitué des appels des enregistreurs vers le serveur. Toutes
les données restent sur votre serveur.

Par rapport au serveur LTX complet, les fonctions suivantes ne sont pas incluses : tableau de bord,
gestion des utilisateurs, alarmes par e-mail ou par SMS, envoi (push) de données vers d'autres systèmes,
rétention automatique des données, mises à jour du firmware via le serveur, affichage cartographique.

Les enregistreurs eux-mêmes sont mis en service sur site par Bluetooth avec l'application web
Aquatos Field (section 5) ; le serveur se contente de recevoir leurs données et de transmettre les
fichiers de paramètres et les commandes.

## 2. Prérequis

| Élément | Exigence |
|---|---|
| Matériel | Linux ou Windows, petite machine virtuelle ou ordinateur monocarte, par exemple 1 cœur de processeur, 1 Go de RAM |
| Serveur web | Apache 2.4 ou nginx (toute version avec PHP-FPM) |
| PHP | 7.4 ou plus récent, 8.x recommandé ; extensions `session`, `ctype`, `iconv` (activées par défaut) et `gd` (graphiques) |
| Disque | Fichiers texte, typiquement 2 à 3 Mo par enregistreur et par an avec un intervalle de 15 minutes |
| Réseau | Les enregistreurs joignent le serveur en HTTP ou HTTPS sur le port 80 ou 443 (LAN, VPN ou APN privé) |
| Heure | Heure système correcte (UTC) ; le serveur transmet son heure aux enregistreurs à chaque contact |

Non nécessaires : base de données, serveur de messagerie, tâches cron, curl, accès à Internet.

## 3. Installation

L'application se compose de trois répertoires : `sw/` (point d'entrée des appareils et configuration),
`legacy/` (interface web) et `docs/` (ce manuel). Les exemples ci-dessous l'installent dans le
répertoire `ltx` de la racine web ; les URL sont donc de la forme `http://<server>/ltx/...`. Tout autre
nom de répertoire convient, à condition d'adapter les chemins en conséquence.

### 3.1 Linux avec Apache (Debian/Ubuntu)

```
sudo apt install apache2 php libapache2-mod-php php-gd
sudo mkdir -p /var/www/html/ltx
sudo unzip LTX_server_minimal_*.zip -d /var/www/html/        # creates /var/www/html/ltx
sudo mkdir -p /var/lib/ltx-data
sudo chown www-data:www-data /var/lib/ltx-data
sudo chmod 750 /var/lib/ltx-data
```

Modifiez `/var/www/html/ltx/sw/conf/api_key.inc.php` (voir la section 4), puis redémarrez Apache
(`sudo systemctl restart apache2`). Sous RHEL/Rocky/Alma, les paquets s'appellent `httpd php php-gd`
et l'utilisateur du serveur web est `apache`.

Si vous conservez le répertoire de données dans la racine web (par défaut `data_secret`, à côté de
`sw/`), Apache doit prendre en compte les fichiers `.htaccess` : définissez `AllowOverride All` (ou
`AuthConfig`) pour ce répertoire dans l'hôte virtuel. Le serveur crée automatiquement
`data_secret/.htaccess` avec `Require all denied`.

### 3.2 Linux avec nginx

```
sudo apt install nginx php-fpm php-gd
```

Bloc server (remplacez `php8.2-fpm` par votre version de PHP-FPM) :

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

Créez le répertoire de données comme à la section 3.1 (utilisateur `www-data`) et rechargez nginx.

### 3.3 Windows avec XAMPP

1. Installez XAMPP (Apache + PHP 8.x) depuis apachefriends.org.
2. Décompressez l'archive ZIP dans `C:\xampp\htdocs\` de sorte que le fichier
   `C:\xampp\htdocs\ltx\sw\lxu_v1.php` existe.
3. Dans `C:\xampp\php\php.ini`, vérifiez que la ligne `extension=gd` est présente et n'est pas mise en
   commentaire (`;` supprimé). Redémarrez Apache depuis le panneau de contrôle XAMPP.
4. Créez un répertoire de données, par exemple `C:\ltx-data`, et indiquez-le dans la configuration sous
   la forme `C:/ltx-data` (avec des barres obliques « / »).
5. Configurez Apache comme service Windows (panneau de contrôle XAMPP) afin qu'il démarre
   automatiquement.
6. Vérifiez que l'heure de Windows est synchronisée (les enregistreurs reprennent l'heure du serveur).

### 3.4 HTTPS (facultatif)

Configurez HTTPS dans le serveur web de la manière habituelle (Apache `mod_ssl`, nginx
`ssl_certificate` ou un proxy inverse). Les enregistreurs doivent accepter le certificat du serveur :
avant de basculer les enregistreurs en HTTPS, convenez avec TerraTransfer du certificat (autorité de
certification publique ou votre propre autorité de certification) auquel les enregistreurs font
confiance. L'interface web peut être utilisée en HTTPS sans aucune modification.

## 4. Configuration

Tous les paramètres se trouvent dans `sw/conf/api_key.inc.php` :

| Paramètre | Signification |
|---|---|
| `S_DATA` | Répertoire de données. Valeur par défaut : `../data_secret` (relatif à `sw/` et `legacy/`). Recommandé : un chemin absolu en dehors de la racine web, par exemple `/var/lib/ltx-data` ou `C:/ltx-data`. L'utilisateur du serveur web doit disposer d'un accès en écriture. |
| `L_KEY` | Mot de passe de l'interface web. Choisissez un mot de passe long ; c'est la seule protection de l'interface. |
| `D_API_KEY` | Clé d'appareil. Chaque enregistreur envoie cette clé à chaque contact. La valeur est convenue avec TerraTransfer et enregistrée dans les enregistreurs. |
| `DB_QUOTA` | Contenu initial de `quota_days.dat` des nouveaux enregistreurs (jours, lignes). Purement informatif dans cette version. |
| `MAXM_2GM`, `MAXM_NB`, `MXGET_MEM` | Taille maximale d'envoi par contact pour 2G/LTE-M et pour LTE-NB, ainsi que la limite multibloc. Conservez les valeurs par défaut, sauf indication contraire de TerraTransfer. |

Le serveur refuse toute requête tant que `L_KEY` ou `D_API_KEY` contiennent encore la valeur fictive
`CHANGE_ME`.

Paramètres PHP (`php.ini`) : les valeurs par défaut suffisent. Si vous souhaitez téléverser vers un
enregistreur, via l'interface web, des fichiers de plus de 2 Mo, augmentez `upload_max_filesize` et
`post_max_size`. Définissez `date.timezone = UTC` pour éviter les avertissements.

## 5. Connexion des enregistreurs

Chaque enregistreur a besoin des paramètres suivants ; ils sont enregistrés dans son fichier de
paramètres système (`sys_param.lxp`) et sont normalement définis par TerraTransfer avant la livraison :

- nom d'hôte ou adresse IP du serveur, par exemple `ltx.example.local` ou `192.168.1.10` ;
- chemin du point d'entrée des appareils : `ltx/sw/lxu_v1.php` (modifiez `ltx` si vous avez installé
  l'application ailleurs) ;
- la clé d'appareil (`D_API_KEY`) ;
- HTTP ou HTTPS, port 80 ou 443.

Veuillez transmettre ces valeurs à TerraTransfer lors de la commande des enregistreurs, ou modifiez-les
vous-même via l'interface web (section 7.4, fichier `sys_param.lxp`). Une valeur erronée dans
`sys_param.lxp` déconnecte l'enregistreur du serveur ; modifiez donc ce fichier avec précaution.

**Configuration sur site avec l'application web Aquatos Field.** Le serveur ne peut pas effectuer la
configuration initiale d'un enregistreur. La mise en service et la maintenance sur site s'effectuent
par Bluetooth avec l'application web Aquatos Field de TerraTransfer
([aquatos-field.netlify.app](https://aquatos-field.netlify.app)) : configuration initiale (adresse du
serveur, clé d'appareil, APN de la carte SIM, intervalles de mesure et de transmission), mesures de
test, tarage (mise à zéro) des capteurs de niveau, lecture de la mémoire de l'enregistreur, autotest et
rapports PDF. L'application fonctionne dans le navigateur via Web Bluetooth (Chrome ou Edge sur
ordinateur et sous Android, iOS via le navigateur Bluefy) et ne nécessite aucune installation. Les
modifications ultérieures des fichiers de paramètres peuvent également être effectuées dans l'interface
web du serveur (section 7.4) ; l'enregistreur les applique lors de son prochain contact.

Déroulement d'un contact : l'enregistreur appelle
`http(s)://<server>/ltx/sw/lxu_v1.php?k=<key>&s=<MAC>` et envoie ses nouvelles données (HTTP POST). La
réponse contient l'heure du serveur et les commandes en attente. Avec les réglages par défaut, un
enregistreur effectue une mesure toutes les 15 minutes et appelle le serveur une fois par heure ; ces
deux intervalles sont des paramètres de l'enregistreur.

## 6. Premier test

1. Ouvrez `http://<server>/ltx/` dans un navigateur. Vous êtes redirigé vers l'interface web.
   Connectez-vous avec `L_KEY`.
2. La liste des appareils est vide tant que le premier enregistreur n'a pas appelé. Le journal principal
   (`<S_DATA>/log/log.txt`, lien « Main Logfile » sur la page d'accueil) affiche chaque contact, y
   compris les contacts rejetés, par exemple `(ERROR:'API Key')` pour une clé d'appareil erronée.
3. Après le premier contact, l'enregistreur apparaît dans la liste avec son adresse MAC (16 chiffres
   hexadécimaux) et son nom. Ouvrez la page de l'appareil et vérifiez « Last Contact » et
   « Files on Device ». Les premiers contacts transmettent la liste des fichiers et les fichiers de
   paramètres ; les données suivent lors du prochain envoi régulier.

## 7. Interface web

### 7.1 Connexion, mode invité et mode démo

`legacy/index.php` est la page d'accueil. Sans connexion, la page est en mode invité (lecture seule) et
n'affiche que les enregistreurs marqués comme appareils de démonstration (« Enable Demo-Mode » sur la
page d'accueil après connexion). La connexion avec `L_KEY` donne un accès complet ; « Logout » met fin à
la session. Les pages s'actualisent automatiquement toutes les 15 à 30 secondes.

### 7.2 Liste des appareils

Pour chaque enregistreur, la liste affiche l'adresse MAC (lien vers la page de l'appareil), le nom de
l'appareil tiré de `iparam.lxp`, le « legacy name » (un texte libre que vous pouvez définir sur le
serveur), le pays du réseau mobile et l'heure du dernier contact. La couleur du dernier contact indique
son ancienneté : vert = moins de 2 minutes, jaune clair = plus de 2 heures, jaune = plus de 4 heures,
rose = plus de 12 heures, rose foncé = plus de 24 heures, rouge = plus de 3 jours, magenta = plus de
14 jours.

Sous la liste : liens vers le fichier journal principal, vers l'explorateur de fichiers (répertoire de
données uniquement) et vers la page d'informations PHP.

### 7.3 Page de l'appareil

**Device Info** : nom, legacy name, dernier contact avec son motif (AUTO = régulier, MANUAL = déclenché
sur l'enregistreur, RESET, ALARM), écart de l'horloge de l'enregistreur, compteurs de transmission,
version et date du firmware, type d'appareil, SIM (ICCID), mémoire libre de l'enregistreur, signal
mobile (dBm, type de réseau, pays), position si l'enregistreur en indique une, dernière commande
utilisateur et son état. « Last Transmission OK » indique la dernière confirmation d'une commande ;
`[OK]` l'efface.

**Files on Device** : le répertoire de l'enregistreur tel qu'il a été relevé lors de la dernière lecture
du répertoire. `[GET DIR]` demande une nouvelle liste des fichiers lors du prochain contact. Pour chaque
fichier : taille, date et indicateurs (flags) sur l'appareil, ainsi que l'état de la copie locale
(« miroir ») sur le serveur : `UP-TO-DATE` signifie que le serveur possède le fichier complet.

- `data.edt` : `[Open local]` texte brut, `[View CSV]` conversion en CSV dans le navigateur, `[CSV(D)]`
  téléchargement au format numérique allemand (virgule décimale, point-virgule), `[CSV(Int)]`
  téléchargement avec point décimal, `[CSVIEW]` graphique.
- `iparam.lxp`, `sys_param.lxp` : `[Open local]` et `[Edit]` (section 7.4).
- `[GET(remote)]` demande à l'enregistreur d'envoyer le fichier complet lors du prochain contact (utile
  si le miroir est incomplet). `[DEL(remote)]` supprime le fichier sur l'enregistreur. Ces deux
  commandes sont placées en file d'attente et exécutées lors du prochain contact ; d'ici là, les entrées
  de la file d'attente peuvent être de nouveau supprimées.

**Cumulative data on server** : `out_total/total.edt` contient tout ce que le serveur a reçu de cet
enregistreur depuis le début, dans l'ordre d'arrivée. Les mêmes liens d'affichage, de téléchargement et
de graphique sont disponibles. Utilisez ce fichier pour l'archivage et l'analyse à long terme ;
`data.edt` n'est qu'un miroir du fichier présent sur l'enregistreur et est remplacé lorsque
l'enregistreur commence un nouveau fichier.

**Files to 'put'** : fichiers en attente de transfert vers l'enregistreur (section 7.4), avec le nombre
de tentatives. `[Remove]` annule le transfert.

**Manage** : liens vers les fichiers journaux de l'enregistreur (contacts, connexions, commandes
utilisateur), « Upload Files to Device's Filesystem » (n'importe quel fichier, par exemple un fichier
de paramètres préparé), « Send Server Command (Byte) » et « Send User Command (Text) » (commandes
définies par le firmware de l'enregistreur ; à n'utiliser que sur instruction de TerraTransfer),
`[Enable DEBUG]` (enregistre chaque envoi sous forme brute dans `dbg/` ; à désactiver de nouveau après
le diagnostic).

### 7.4 Modification des fichiers de paramètres

`[Edit]`, à côté de `iparam.lxp` ou de `sys_param.lxp`, ouvre un formulaire avec un champ de saisie par
ligne et une brève description des champs connus. « Submit » enregistre le fichier dans la file
d'attente `put/` ; l'enregistreur le reçoit lors du prochain contact et l'applique. `iparam.lxp` contient
les intervalles de mesure et de transmission, les définitions des canaux (unité, décimales, mise à
l'échelle, seuils d'alarme) et le nom de l'appareil. `sys_param.lxp` contient les paramètres réseau et
serveur (APN, serveur, chemin, clé). Ne modifiez que les champs dont vous connaissez la signification ;
en cas de doute, adressez-vous à TerraTransfer.

### 7.5 Graphiques (CSVIEW)

`[CSVIEW]` convertit le fichier de données en CSV et trace un graphique (extension PHP `gd` nécessaire).
Les cases à cocher activent et désactivent les canaux, les flèches font défiler l'affichage, les boutons
en forme de loupe permettent de zoomer, « Export CSV » télécharge les données affichées. Les fichiers
CSV temporaires sont stockés dans `<S_DATA>/stemp` et supprimés automatiquement au bout de deux heures.

## 8. Stockage des données

### 8.1 Structure des répertoires

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

Tous les fichiers sont des fichiers texte, à l'exception des envois bruts dans `dbg/`.

### 8.2 Format de données EDT

EDT (« Easy Data ») est un format texte brut, en UTF-8, avec un enregistrement par ligne :

```
!U 0:cm 1:°C 2:us/cm 90:V(Bat) 91:°C(int) 92:%rH(int)
!1791806400 0:-43.917045 1:13.50 2:1.23 90:3.638 91:16.3 92:9.4
!+900 0:-43.917045 1:13.50 2:1.26
<NT AUTO OK(637,51,0)>
```

- `!U` – unités des canaux ; apparaît au début et à chaque modification des paramètres.
- `!<seconds>` – mesure avec un horodatage UTC absolu (secondes Unix), suivie de paires
  `channel:value`.
- `!+<seconds>` – mesure relative à la ligne précédente.
- Les canaux 0–89 sont des mesures, les canaux 90–99 des valeurs d'état de l'enregistreur (tension de la
  batterie, température et humidité internes). Une ligne ne contient que les canaux mesurés à ce
  moment-là.
- `NoReply` à la place d'une valeur : le capteur n'a pas répondu. Une valeur commençant par `*` a
  dépassé un seuil d'alarme.
- Les lignes commençant par `<` sont des événements : `<NT AUTO OK(…)>` envoi, `<NT AUTO ERROR …>`
  envoi échoué, `<RESET …>` redémarrage, `<COOKIE …>` synchronisation de l'heure, `<NEW>` début d'un
  fichier. Après un redémarrage, l'horloge de l'enregistreur repart de zéro jusqu'à la prochaine
  synchronisation ; ces lignes apparaissent avec des dates en 1970.
- Les lignes commençant par `$` sont des enregistrements compressés ; l'interface web les décode.

### 8.3 Export CSV

Les vues et téléchargements CSV comportent les colonnes `NO` (numéro de ligne dans le fichier EDT),
`TIME(UTC)` (`dd.mm.yy hh:mm:ss`) et une colonne par canal, nommée `unit(channel)`. Les canaux absents
d'une ligne restent vides. `[CSV(D)]` utilise des points-virgules et une virgule décimale, `[CSV(Int)]`
des virgules et un point décimal. Le paramètre d'URL `utc=<seconds>` décale la colonne de l'heure, par
exemple `utc=3600` pour UTC+1.

## 9. Exploitation

**Sauvegarde** : sauvegardez régulièrement le répertoire de données avec n'importe quel outil de
sauvegarde de fichiers. Les fichiers sont écrits à chaque contact d'un enregistreur ; une sauvegarde
effectuée pendant un contact peut se terminer par une dernière ligne incomplète dans `total.edt`, que la
visionneuse affiche comme du texte ; aucune autre donnée n'est affectée.

**Espace disque** : `out_total/total.edt` croît sans limite (environ 2 à 3 Mo par an et par enregistreur
avec un intervalle de 15 minutes). Pour archiver, déplacez ou tronquez le fichier entre deux contacts de
l'enregistreur ; l'envoi suivant se poursuit dans un nouveau fichier. `stemp/` et `dbg/` peuvent être
vidés à tout moment.

**Supervision** : la liste des appareils indique par une couleur l'ancienneté du dernier contact. Un
enregistreur qui n'a pas appelé depuis plus de deux fois son intervalle de transmission doit être contrôlé
(alimentation, SIM, signal). Le journal principal affiche les contacts rejetés, par exemple ceux d'un
enregistreur dont la clé est incorrecte.

**Journaux** : `<S_DATA>/log/log.txt` (tous les contacts, une ligne par contact, avec le résultat et la
durée d'exécution), `<MAC>/log.txt` (contacts d'un enregistreur), `<MAC>/conn_log.txt` (détails du
réseau), `<MAC>/userio.txt` (commandes utilisateur et réponses). Les journaux font l'objet d'une rotation
automatique ; la version précédente est conservée sous la forme `_…_old.txt`.

**Heure** : maintenez l'horloge du serveur synchronisée (NTP). Les enregistreurs reprennent l'heure du
serveur à chaque contact ; une heure de serveur erronée produit des horodatages erronés dans les données.

**Mises à jour** : pour installer une nouvelle version, remplacez les répertoires `sw/`, `legacy/` et
`docs/` en conservant `sw/conf/api_key.inc.php` et le répertoire de données. Consultez les notes de
version pour connaître les éventuelles modifications de paramètres.

**Migration vers un autre serveur** : installez le logiciel sur le nouveau serveur, copiez le répertoire
de données et la configuration, puis modifiez l'adresse du serveur dans les enregistreurs (section 5) ou
redirigez l'ancien nom d'hôte. Les enregistreurs conservent leurs données jusqu'au prochain envoi réussi.

## 10. Dépannage

| Symptôme | Cause / que faire |
|---|---|
| Le navigateur affiche `ERROR: 'Server not configured (sw/conf/api_key.inc.php)'` | Les valeurs fictives `CHANGE_ME` figurent encore dans la configuration. |
| L'enregistreur n'apparaît pas, le journal principal affiche `(ERROR:'API Key')` | La clé d'appareil enregistrée dans l'enregistreur diffère de `D_API_KEY`. |
| L'enregistreur n'apparaît pas, rien dans le journal principal | L'enregistreur ne peut pas joindre le serveur : vérifiez le nom d'hôte/l'IP, le chemin, le port, le pare-feu, l'APN/le VPN ; contrôlez sur site les paramètres de l'enregistreur avec l'application web Aquatos Field (section 5) ; activez le débogage dans l'enregistreur avec l'aide de TerraTransfer. |
| Le journal principal reste vide alors que des enregistreurs appellent ; avertissements PHP `mkdir`/`fopen` … `Permission denied` dans le journal d'erreurs du serveur web | Le répertoire de données n'est pas accessible en écriture pour l'utilisateur du serveur web, ou le chemin `S_DATA` est erroné. |
| La page de l'appareil affiche `(no Directory Scan)` | Pas encore de contact complet, ou les premiers contacts ont échoué. Utilisez `[GET DIR]`. |
| Miroir `data.edt` incomplet (`ERROR: Real-Local`) | Utilisez `[GET(remote)]` pour récupérer le fichier complet lors du prochain contact. |
| `[CSVIEW]` affiche `the PHP extension 'gd' is required` | Activez `gd` dans `php.ini` et redémarrez le serveur web. |
| La connexion échoue alors que le mot de passe est correct | Les sessions PHP ne peuvent pas être enregistrées (`session.save_path` non accessible en écriture) ou les cookies sont bloqués. |
| Horodatages en 1970 | L'enregistreur a été redémarré et n'a pas encore reçu l'heure du serveur ; le problème disparaît au prochain contact. |
| Le répertoire de données est accessible depuis le navigateur | Apache : `AllowOverride` manquant ; nginx : ajoutez le bloc location `deny all`. Mieux encore : déplacez le répertoire en dehors de la racine web. |

## 11. Remarques sur la sécurité

- L'interface web n'est protégée que par un mot de passe unique, qui donne tous les droits. Restreignez
  l'accès à l'interface (pare-feu, VPN ou authentification supplémentaire dans le serveur web) lorsque
  le serveur est joignable depuis des réseaux non fiables.
- Les enregistreurs envoient la clé d'appareil à chaque contact. Utilisez HTTPS ou un réseau privé (VPN,
  APN privé).
- Placez le répertoire de données en dehors de la racine web ou protégez-le par le serveur web ; il
  contient la clé d'appareil (`dapikey.dat`).
- Maintenez PHP et le serveur web à jour.
