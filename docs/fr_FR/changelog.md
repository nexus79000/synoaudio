Change Log
==========

version 31
----------
* Correction bug d'affichage suite passage v4
* Prise en compte de la double authentification
* Suppression du bouton de 'découverte' dans la configucration du plugin.

version 30
----------
* Passage v4
* Mise à jour du widget

version 26
----------
* Correction lié à des messages d'erreur dans la cron_execution

version 25
----------
* Suppression du mode TTS Online car il n'est plus disponible chez le fournisseur
* Mise à jour de compatibilité avec la version 3 de Jeedom.

version 24
----------
* Ajout action 'Tache' avec Start/Stop, pour arreter la tache cron et deconnecter le user du Syno.

version 23
----------
* Ajout action 'Ordre' pour ajouter automatiquement un artiste ou un album (bien mettre vos tags de musique à jour)
* Ajout action pour purger la liste de lecture d'un player
* Mise à jour panel de playlist avec les musiques récemments ajoutés

version 22
----------
* Correction pour prise en charge des noms de player non ASCII
* Correction repertoire voice_tts

version 21
----------
* Mise à jour du widget (Opacité - Transparence - Bordure - ...)
* Fix du problème avec les images d'album
* Mise à jour de la recherche
* Correction temporisation lors du TTS

version 20
----------
* Suppression de la limitation sur le moteur TTS local
* Suppression bootstrapswich (Jeedom 3.x.x)
* Compatibilité avec l'opacité (plan, design,...)
* Modification de la recherche (Artistes/Albums/Chansons)

version 19
----------
* Correction de la recherche
* Préparation des cmd pour les types génériques (En attendant les type génériques multimédia)
* Prise en compte du port HTTP pour Docker
* Correction pour l'affichage avancé dans la configuration de l'équipement
* Ajout de raccourci dans le plugin


version 18
----------
* Mise à jour du code pour Jeedom 2.3
* Ajout de la recherche d'une chanson


version 17
----------
* Correction de l'appel des dépendances
* Correction de la remise en état après un appel du TTS
* Conversion wav to mp3
* Correction Widget
* Ajout dans les playlists d'une fonction aléatoire

version 16
----------
* Arrêt de la désactivation du plugin (Tuile inactive)
* Modification du widget ( ajout de la class 'widget-name')
* Correction TTS :
  ** Inversement des champs Volume et Message pour coller au standard 
  ** Possibilité d'ajouter une temporisation (Voir documentation)

version 15
----------
* Correction TTS

version 14
----------
* Optimisation de la génération et de la lecture du TTS
* Correction affichage Cover.

version 13
----------
* Gestion des erreurs de session (Relance la création du sid)
* Optimisation de la fonction pull (moins de charge système)
* Mise à jour du code pour Jeedom 2.1 et suivant TTS
  
version 12
----------
* Ajout de la pièce au player lors de la découverte, si son nom est présent dans le nom du player.
* Prise en charge du mode sécurisé (https) 
* Ajout option simplification de l'affichage
* Ajout de plusieurs player airplay via scénario (En précisant leur nom et leur volume séparé par une virgule)
* Amélioration du panel de gestion des lecteurs 'airplay'
* Mise à jour de la documentation

version 11
----------
* Prise en charge des caractères spéciaux dans le mot de passe et les users du NAS
* Prise en charge complète des players 'airplay' (Gestion du volume globale et unitaire)

beta 0.0.10
----------
* Correction ajout playlist et boucle lecture

beta 0.0.9
----------
* Correction lancement playlist via un scénario

beta 0.0.8
----------
* Correction de la partie mobile
* Mise à jour de la documentation et des screenshots

beta 0.0.6
----------
* Correction bug (play/pause)

beta 0.0.5
----------
* Correction deamon pour la v2

beta 0.0.4
----------
* Correction du cron pour conformité version 2.0

beta 0.0.3
----------
* Correction du cron pour la fonction de pulling
* Ajout de la version mobile
* Corrections mineurs

beta 0.0.2
----------
* Correction de la découverte des players

beta 0.0.1
----------
* Première version bêta