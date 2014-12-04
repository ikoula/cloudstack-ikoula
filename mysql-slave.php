<?php


/* Clé API */
print "Saisissez votre clé API :";
$apikey = trim (fgets(STDIN));

/* Clé privée */
print "Saisissez votre clé privée :";
$privatekey = trim (fgets(STDIN));

/* ID du réseau */
print "Saisissez l'ID de votre réseau :";
$networkid = trim (fgets(STDIN));

/* UUID de l'offre de service (configuration matérielle) voulue */
print "UUID de l'offre de service (configuration matérielle) voulue (voir FAQ https://support.ikoula.com/subindex-1-2-236.html): ";
$serviceofferinguuid = trim (fgets(STDIN));

/* UUID du modèle de systeme d'exploitation voulu */
print "UUID du modèle de systeme d'exploitation voulu (voir FAQ https://support.ikoula.com/subindex-1-2-236.html):";
$templateuuid = trim(fgets(STDIN));

/* Nom de votre choix pour l'instance (Nom de la machine et d'affichage dans l'interface)
Caractères alphanumériques uniquement. Ce nom doit être unique au sein de votre réseau. */
print "Nom a donner a votre instance :";
$hostname = trim (fgets(STDIN));

/* Mot de passe de votre choix pour le compte root de Mysql*/
print "Mot de passe pour le compte root de MySQL :";
$pwmysqlroot = trim(fgets(STDIN));

/* Mot de passe de votre choix pour le compte de réplication replic_user de Mysql*/
print "Mot de passe pour le compte de replication replic_user : ";
$pwreplic = trim (fgets(STDIN));

/* Adresse ip privée guest de l'instance MySQL Master*/
print "Adresse ip guest (10.1.1.x) de votre serveur MySQL Maitre : ";
$ipmaster = trim(fgets(STDIN));

print "Port SSH publique pour cette instance :";
$pubssh = trim(fgets(STDIN));

        // Liste complète des appels possibles : https://cloudstack.apache.org/docs/api/apidocs-4.2/TOC_User.html

        ################################
        # Paramètres généraux de l'API #
        ################################

        define("APIKEY","$apikey");
        define("SECRETKEY","$privatekey");
        //On définit l'URL d'appel de l'API (ou 'EndPoint')
        define("ENDPOINT","https://cloudstack.ikoula.com/client/api");


        #############################################################
        # Paramètres utilisateur de configuration d(es) instance(s) #
        #############################################################

        //Première instance :

                /* UUID(s) du réseau auquel sera connectée votre instance.
                Utilisez la requête API 'listNetworks' ou l'interface pour lister les réseaux existants et déterminer le réseau voulu
                */
                $vm01['conf']['networkid'] = "$networkid";

                /* UUID de l'offre de service (configuration matérielle) voulue.
                Utilisez la requête API 'listServiceOfferings' pour lister les offres existantes.
                */
                $vm01['conf']['serviceofferingid'] = "$serviceofferinguuid";

                /* UUID du modèle de systeme d'exploitation.
                Vous pouvez aussi utiliser la requête API 'listTemplates' pour lister les modèles existants.
                */
                $vm01['conf']['templateid'] = "$templateuuid";

                /* Nom de votre choix pour l'instance (Nom de la machine et d'affichage dans l'interface)
                Caractères alphanumériques uniquement. Ce nom doit être unique au sein de votre réseau. */
                $vm01['conf']['hostname'] = "$hostname";

                /* Données utilisateur a passer au processus de déploiement de l'instance.
                Ce paramètre nous sert à passer les paramètres Puppet au travers des userdata */

                $vm01['conf']['userdata'] = "echo $pwmysqlroot $pwreplic $ipmaster > /tmp/mysql && puppet module install ikoula/mysqlslave && puppet apply -e \"include mysqlslave\"";


/* ------------------------------ NE PLUS RIEN MODIFIER APRES CETTE LIGNE ----------------------------------- */
        $json_response = null;

        ################################################
        # Récupération de l'ID de zone pour ce network #
        ################################################
        // Requête API
        $args['command'] = "listNetworks";

        // On reprend l'ID de réseau renseigné plus tôt
        $args['id'] = $vm01['conf']['networkid'];

        // Execution de la requête
        sendRequest($args, $json_response);

        // Stockage de l'ID de zone pour ce réseau
        $vm01['conf']['zoneid'] = $json_response['listnetworksresponse']["network"][0]['zoneid'];

        // On réinitialise les variables utilisées pour l'envoi de requêtes API
        unset($args);

        ######################################################
        # Récupération de l'id de l'adresse IP de ce network #
        ######################################################
        // Requête API
        $args['command'] = "listPublicIpAddresses";
        $args['associatednetworkid'] = $vm01['conf']['networkid'];

        // Execution de la requête
        sendRequest($args, $json_response);

        // Stockage de l'ID de l'adresse IP publique du réseau
        $vm01['conf']['publicIPid'] = $json_response['listpublicipaddressesresponse']["publicipaddress"][0]['id'];

        // On réinitialise les variables utilisées pour l'envoi de requêtes API
        unset($args);

        ####################################
        # Création de la première instance #
        ####################################

        // Requête API
        $args['command'] = "deployVirtualMachine";
        $args['zoneid'] = $vm01['conf']['zoneid'];
        $args['serviceofferingid'] = $vm01['conf']['serviceofferingid'];
        $args['templateid'] = $vm01['conf']['templateid'];
        $args['networkids'] = $vm01['conf']['networkid'];
        $args['name'] = $vm01['conf']['hostname']; //Hostname
        $args['displayname'] = $args['name']; //Nom d'affichage
        $args['userdata'] = $vm01['conf']['userdata']; //Userdata
        $args['userdata'] = base64_encode($vm01['conf']['userdata']); // Données utilisateur

        //Type de retour : JSON ou XML (par défaut : XML)
        $args['response'] = "json";

        // Initialisation du client API
        sendRequest($args, $json_response);

        //On vérifie la presence d'un Job
        if(preg_match("/^[0-9a-f\-]+$/", $json_response['deployvirtualmachineresponse']['jobid']) > 0)
        {
                $jobs[] = $json_response['deployvirtualmachineresponse']['jobid'];
        }
        else{
                echo "ID de job non trouvé.\n";
        }

        //On mémorise la correspondance name/id de l'instance au sein d'un tableau
        $vm01['conf']['id'] = $json_response['deployvirtualmachineresponse']['id'];

        //On utilise la fonction de vérification des jobs asynchrones
        if(!checkJobs($jobs))
                exit;

        //On cherche à récupérer le mot de passe de l'instance via 'queryAsyncJobResult'
        $args['command'] = "queryAsyncJobResult";
        $args['jobid'] = $json_response['deployvirtualmachineresponse']['jobid'];

        //Type de retour : JSON ou XML (par défaut : XML)
        $args['response'] = "json";

        // Initialisation du client API
        sendRequest($args, $json_response);

        // Indication du mot de passe de l'instance
        print($vm01['conf']['hostname']." - Mot de passe : ".$json_response['queryasyncjobresultresponse']['jobresult']['virtualmachine']['password']."\n");

         // On réinitialise les variables utilisées pour l'envoi de requêtes API
        unset($args);

        ########################################
        # Ouverture du port public pour le SSH #
        ########################################
        // Requête API
        $args['command'] = "createFirewallRule";
        $args['ipaddressid'] = $vm01['conf']['publicIPid'];

        // Définition du protocole a filtrer ici TCP (utilisé par le protocole SSH), les protocoles sont ICMP/UDP/TCP
        $args['protocol'] = "TCP";

        // Définition du masque réseau qui nous permet d'autoriser certaines IP,
        // ici nous acceptons la connection vers toutes les IP
        $args['cidrlist'] = "0.0.0.0/0";

        // Le port de début (port ssh public qui sera plus tard redirigé vers le port 22(ssh) de votre VM)
        $args['startport'] = $pubssh;

        // Le port de fin (port ssh public qui sera plus tard redirigé vers le port 22(ssh) de votre VM)
        $args['endport'] = $pubssh;

        // Execution de la requête
        sendRequest($args, $json_response);

        // Traitement de la requête
        if(preg_match("/^[0-9a-f\-]+$/", $json_response['createfirewallruleresponse']['jobid']) > 0)
        {
                $jobs[] = $json_response['createfirewallruleresponse']['jobid'];
        }
        else{
                echo "ID de job non trouvé.\n";
        }

        // On réinitialise les variables utilisées pour l'envoi de requêtes API
        unset($args);

        ###################################
        # Redirection de port pour le SSH #
        ###################################
        // Requête API
        $args['command'] = "createPortForwardingRule";
        $args['ipaddressid'] = $vm01['conf']['publicIPid'];
        $args['virtualmachineid'] = $vm01['conf']['id'];

        // Définition du protocole a filtrer ici TCP (utilisé par le protocole SSH), les protocoles sont ICMP/UDP/TCP
        $args['protocol'] = "TCP";

        // Le Port public sur lequels vous allez initialiser la connection et définie plus tôt comme ouvert
        $args['publicport'] = $pubssh;

        // Le SSH de votre VM
        $args['privateport'] = 22;

        // Execution de la requête
        sendRequest($args, $json_response);

        // Traitement de la requête
        if(preg_match("/^[0-9a-f\-]+$/", $json_response['createportforwardingruleresponse']['jobid']) > 0)
        {
                $jobs[] = $json_response['createportforwardingruleresponse']['jobid'];
        }
        else{
                echo "ID de job non trouvé.\n";
        }

        // On réinitialise les variables utilisées pour l'envoi de requêtes API
        unset($args);

        if(!checkJobs($jobs))
                        exit;

/*-----------------------------------------------------------------------------------------------------------*/

        #############
        # Fonctions #
        #############

        //Fonction de gestion d'erreur(s) API
        function apiErrorCheck($json_response)
        {
                if(is_array($json_response))
                {
                        $key = array_keys($json_response);
                        if(isset($json_response['errorcode']))
                        {
                                echo "ERREUR : ".$json_response['errorcode']." - ".$json_response['errortext']."\n";
                                exit;
                        }
                        if(isset($json_response['errorcode']) || (isset($key[0]) && isset($json_response[$key[0]]['errorcode'])))
                        {
                                echo "ERREUR : ".$json_response[$key[0]]['errorcode']." - ".$json_response[$key[0]]['errortext']."\n";
                                exit;
                        }
                }
                else
                {
                        echo "ERREUR : PARAMETRE INVALIDE";
                                exit;
                }
        }

        //Fonction d'envoi de requête à l'API
        function sendRequest($args, &$json_response)
        {
                $json_response = null;
                // Clef API
                $args['apikey'] = APIKEY;
                $args['response'] = "json";
                //On classe les paramètres
                ksort($args);
                // On construit la requête HTTP basée sur les paramètres contenus dans $args
                $query = http_build_query($args);
                // On s'assure de bien remplacer toutes les occurences de '+' par des '%20'
                $query = str_replace("+", "%20", $query);
                //On utilise la clef secrète et un algorithme HMAC SHA-1 sur la requête pour encoder la signature
                $hash = hash_hmac("SHA1",  strtolower($query), SECRETKEY, true);
                $base64encoded = base64_encode($hash);
                $signature = urlencode($base64encoded);

                // Construction de la requête finale sous la forme 'URL API + Requête API et paramètres + Signature'
                $query .= "&signature=" . $signature;

                // $jobs = null;

                // Initialisation du client API
                try
                {
                        //Construction de la requête
                        $httpRequest = new HttpRequest();
                        $httpRequest->setMethod(HTTP_METH_POST);
                        $httpRequest->setUrl(ENDPOINT . "?" . $query);

                        // Envoi de la requête au serveur :
                        $httpRequest->send();
                        // Récupération du retour de l'API
                        $response = $httpRequest->getResponseData();
                        // retour de la réponse
                        $json_response = json_decode($response['body'], true);

                        apiErrorCheck($json_response);
                }
                catch (Exception $e)
                {
                        echo "Probleme lors de l'envoi de la requête. ERREUR=".$e->getMessage();
                        exit;
                }
        }

        //Fonction de vérification des jobs asynchrones
        function checkJobs($jobs)
        {
                $json_response = null;
                $error_msg = "";
                if(is_array($jobs) && count($jobs) > 0)
                {
                        // La tâche est asynchrone, on doit donc régulièrement vérifier les tâches avec une sécurité
                        $secu = 0;
                        // On indexe les tâches
                        $ij = 0;
                        // Tant qu'il y a des tâches asynchrones non-terminées dans la pile de vérification, on boucle et on vérifie le statut
                        while(count($jobs) > 0 && $secu < 100)
                        {
                                try
                                {
                                        //On interroge le statut de la tâche asynchrone
                                        // http://download.cloud.com/releases/3.0.6/api_3.0.6/root_admin/queryAsyncJobResult.html
                                        $args['apikey'] = APIKEY;
                                        $args['command'] = "queryAsyncJobResult";
                                        $args['jobid'] = $jobs[$ij];
                                        $args['response'] = "json";

                                        $json_response = null;
                                        sendRequest($args, $json_response);

                                        if(is_array($json_response['queryasyncjobresultresponse']))
                                        {
                                                // Si OK...
                                                if($json_response['queryasyncjobresultresponse']['jobstatus'] == 1)
                                                {
                                                        // ...On retire simplement la tâche du tableau a surveiller
                                                        //return("JOB OK\n");
                                                        array_splice($jobs, $ij, 1);
                                                }
                                                // Sinon...
                                                elseif($json_response['queryasyncjobresultresponse']['jobstatus'] == 2)
                                                {
                                                        //...On mémorise l'erreur et on retire la tâche du tableau à surveiller
                                                        //return("JOB ERREUR\n");
                                                        array_splice($jobs, $ij, 1);
                                                        $error_msg .= "ERREUR ! RESULT_CODE=".$json_response['queryasyncjobresultresponse']['jobresultcode'];
                                                }
                                                // Cette tâche est encore en cours, on passe à la suivante et on temporise
                                                elseif($json_response['queryasyncjobresultresponse']['jobstatus'] == 0)
                                                {
                                                        // Tâche suivante
                                                        $ij++;
                                                        // Temporisation entre chaque interrogation pour ne pas charger inutilement l'API
                                                        sleep(5);
                                                }
                                        }
                                }
                                catch(Exception $e)
                                {
                                        $error_msg .= "EXCEPTION Lors de la verification la tache asynchrone. JOB_UUID:".$jobs[$ij]." ERREUR=".$e->getMessage()." \n";
                                }

                                // Si l'index arrive en bout de tableau, on le réinitialise
                                if($ij == count($jobs))
                                {
                                        $ij = 0;
                                        $secu++;
                                }
                        }

                        if($error_msg)
                        {
                                echo "ERRORS:".$error_msg."\n";
                                return false;
                        }
                        return true;
                }
                echo "No job\n";
                return false;
        }
?>
