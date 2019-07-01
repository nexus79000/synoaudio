#!/bin/bash
touch /tmp/remove_synoaudio_in_progress
echo 0 > /tmp/remove_synoaudio_in_progress
echo "###################################################################"
echo "##### Lancement de l'installation/mise à jour des dépendances #####"
echo "###################################################################"
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
ARCH=`uname -m`
function apt_remove {
  sudo apt-get -y autoremove "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $1 - abort"
    rm /tmp/remove_synoaudio_in_progress
    exit 1
  fi
}
echo 25 > /tmp/remove_synoaudio_in_progress
echo "########################################"
echo "#####     suppression du depot     #####"
echo "########################################"
sudo mv  /etc/apt/sources.list.bak /etc/apt/sources.list 
sudo apt-get clean
sudo apt-get update

echo 50 > /tmp/remove_synoaudio_in_progress
echo "########################################"
echo "##### Suppression des dependances  #####"
echo "########################################"
apt_remove libttspico-utils lame
echo 75 > /tmp/remove_synoaudio_in_progress

echo 100 > /tmp/remove_synoaudio_in_progress
echo "Tout est desinstalle"
rm /tmp/remove_synoaudio_in_progress