# ouiedire.net

Ouïedire : j'en ai déjà entendu parler quelque part.

## Déploiement

### Conversion MP3 en CBR

### Tagger un morceau

```bash
ssh ouiedire_net@ftp.pastis-hosting.net /var/www/vhosts/ouiedire.net/httpdocs/bin/tag <IDENTIFIANT EMISSION (eg. ailleurs-xxx / ouiedire-xxx>
ssh ouiedire_net@ftp.pastis-hosting.net /var/www/vhosts/ouiedire.net/httpdocs/bin/tag ailleurs-139
```

### Simulation

```bash
ant deploy -Dprofile=pastishosting -Dassets.version=`date +%s` -Drsync.option="--dry-run --delete-after"
```

### Pour de vrai

```bash
ant deploy cloudflare.purgeAll -Dprofile=pastishosting -Dassets.version=`date +%s` -Drsync.options="--progress --delete-after"
```

### Invalidation du cache après la mise à jour d'un fichier MP3 sur le serveur

```bash
ant cloudflare.purgeAll -Dprofile=pastishosting
```

### Uploader une émission sur Mixcloud

```sh
./bin/mixcloud-upload ailleurs-139
```
