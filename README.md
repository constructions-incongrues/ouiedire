# net.ouiedire.www

Ouïedire : j'en ai déjà entendu parler quelque part.

## Installation

```bash
sudo add-apt-repository ppa:openjdk-r/ppa
sudo apt-get update
sudo apt-get install gdebi-core resolvconf dnsmasq virtualbox zlib1g-dev openjdk-7-jre-headless
wget https://dl.bintray.com/mitchellh/vagrant/vagrant_1.7.4_x86_64.deb
sudo gdebi -n vagrant_1.7.4_x86_64.deb
sudo sh -c 'echo "server=/vagrant.dev/127.0.0.1#10053" > /etc/dnsmasq.d/vagrant-landrush'
sudo service dnsmasq restart

git clone https://github.com/constructions-incongrues/net.ouiedire.www.git
cd net.ouiedire.www
vagrant plugin install vagrant-vbguest
vagrant plugin install vagrant-share
vagrant plugin install landrush
vagrant up
```

## Déploiement

### Simulation

```bash
ant deploy -Dprofile=pastis-hosting -Dassets.version=`date +%s` -Drsync.option="--dry-run --delete-after"
```

### Pour de vrai

```bash
ant deploy -Dprofile=pastis-hosting -Dassets.version=`date +%s` -Drsync.options="--progress --delete-after"
```
