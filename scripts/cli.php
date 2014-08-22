<?php
/**
* Initialise le chargement et l'exécution des scripts
*
* Lancer ce fichier en ligne de commande avec :
* <code>php cli.php mon_script -a test</code>
*
* @author       Tela Botanica <equipe-dev@tela-botanica.org>
* @copyright    Tela-Botanica 1999-2014
* @licence      GPL v3 & CeCILL v2
* @version      $Id$
*/

// Le fichier Framework.php du Framework de Tela Botanica doit être appelée avant tout autre chose dans l'application.
// Sinon, rien ne sera chargé.
// Chemin du fichier chargeant le framework requis
$framework = dirname(__FILE__).DIRECTORY_SEPARATOR.'framework.php';
if (!file_exists($framework)) {
    $e = "Veuillez paramétrer l'emplacement et la version du Framework dans le fichier $framework";
    trigger_error($e, E_USER_ERROR);
} else {
    // Inclusion du Framework
    require_once $framework;

    // Ajout d'information concernant cette application
    Framework::setCheminAppli(__FILE__);// Obligatoire
    Framework::setInfoAppli(Config::get('info'));

    // Initialisation et lancement du script appelé en ligne de commande
    Cli::executer();
}
?>