# net.ouiedire.www

Ouïedire : j'en ai déjà entendu parler quelque part.

## Installation

```bash
sudo add-apt-repository ppa:openjdk-r/ppa
sudo apt-get update
sudo apt-get install gdebi-core resolvconf dnsmasq virtualbox zlib1g-dev openjdk-7-jre-headless
wget https://releases.hashicorp.com/vagrant/1.9.2/vagrant_1.9.2_x86_64.deb
sudo gdebi -n vagrant_1.9.2_x86_64.deb
sudo sh -c 'echo "server=/vagrant.dev/127.0.0.1#10053" > /etc/dnsmasq.d/vagrant-landrush'
sudo service dnsmasq restart
vagrant plugin install vagrant-vbguest
vagrant plugin install vagrant-share
vagrant plugin install landrush

git clone https://github.com/constructions-incongrues/net.ouiedire.www.git
cd net.ouiedire.www
vagrant up
```

## Déploiement

### Conversion MP3 en CBR


### Tagger un morceau

```bash
ssh -p 2222 ouiedire_net@ftp.pastis-hosting.net /var/www/vhosts/ouiedire.net/httpdocs/bin/tag <IDENTIFIANT EMISSION (eg. ailleurs-xxx / ouiedire-xxx>
ssh -p 2222 ouiedire_net@ftp.pastis-hosting.net /var/www/vhosts/ouiedire.net/httpdocs/bin/tag ailleurs-139
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
