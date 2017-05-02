#!/bin/bash
touch /tmp/install_synoaudio_in_progress
echo 0 > /tmp/install_synoaudio_in_progress
echo "###################################################################"
echo "##### Lancement de l'installation/mise à jour des dépendances #####"
echo "###################################################################"
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
ARCH=`uname -m`
function apt_install {
  sudo apt-get -y install "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $1 - abort"
    rm /tmp/install_synoaudio_in_progress
    exit 1
  fi
}
echo 25 > /tmp/install_synoaudio_in_progress
echo "########################################"
echo "#####        ajout du depot        #####"
echo "########################################"
if [ ! -f /etc/apt/sources.list.bak ]
then
	sudo cp -f /etc/apt/sources.list /etc/apt/sources.list.bak
fi
sudo sh -c "cat /etc/apt/sources.list.bak|  sed 's/main$/main non-free/g' > /etc/apt/sources.list"
sudo apt-get clean
sudo apt-get update
echo 50 > /tmp/install_synoaudio_in_progress
echo "########################################"
echo "##### Installation des dependances #####"
echo "########################################"
apt_install libttspico-utils lame
echo 75 > /tmp/install_synoaudio_in_progress
echo 100 > /tmp/install_synoaudio_in_progress
echo "Tout est installe"
rm /tmp/install_synoaudio_in_progress