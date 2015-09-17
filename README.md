net.ouiedire.www
================

Ouïedire : j'en ai déjà entendu parler quelque part.

Installation
============

```bash
sudo apt-get update
sudo apt-get install gdebi-core resolvconf dnsmasq zlib1g-dev
wget http://download.virtualbox.org/virtualbox/5.0.4/virtualbox-5.0_5.0.4-102546~Ubuntu~trusty_amd64.deb
sudo gdebi -n virtualbox-5.0_5.0.4-102546~Ubuntu~trusty_amd64.deb
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

Déploiement
===========
```bash
ant configure -Dprofile=jeroboam -Dassets.version=`date +%s`
```
