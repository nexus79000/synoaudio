PROGRESS_FILE=/tmp/install_synoaudio_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "*******************************************************"
echo "*             Installation des dépendances            *"
echo "*******************************************************"
sudo add-apt-repository non-free
sudo apt-get clean
sudo apt-get update
echo 20 > ${PROGRESS_FILE}


echo 40 > ${PROGRESS_FILE}
sudo apt-get -y install libttspico-utils lame
echo 50 > ${PROGRESS_FILE}
echo 60 > ${PROGRESS_FILE}
echo 90 > ${PROGRESS_FILE}
echo 100 > ${PROGRESS_FILE}
echo "*******************************************************"
echo "*             Installation terminée                   *"
echo "*******************************************************"
rm ${PROGRESS_FILE}
