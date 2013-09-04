#!/bin/sh
PUPPET_FILES_REMOTE_URL="<URL DE VOTRE REPOSITORY PUPPET>"
PUPPET_FILES_LOCAL_DIR="/etc/puppet/modules"
NOM_MODULE="<LE NOM DE VOTRE MODULE PUPPET>"
OPTION_WGET="-r --mirror --execute robots=off --no-host-directories --cut-dirs=1 --reject="index.html*,sh""

#On récupére les modules Puppet de notre Puppet repository en local
wget $OPTION_WGET -P $PUPPET_FILES_LOCAL_DIR $PUPPET_FILES_REMOTE_URL

#Si le téléchargement s'est terminé sans erreur nous appliquons notre module
if [ $? -eq 0 ] ; then
        logger -t "puppet" "Puppet files are successfully downloaded"
        puppet apply -e "include $NOM_MODULE" 
        exit 0
#Sinon on reporte l'erreur dans nos logs système
else
        logger -t "puppet" "Failed to download Puppet files"
        exit 1
fi
