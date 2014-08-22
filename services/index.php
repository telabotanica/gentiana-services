<?php
/**
 * Initialise le chargement et l'exécution des services web.
 *
 * @category DEL
 * @package Services
 * @subpackage Bibliotheque
 * @version 0.1
 * @author Mathias CHOUET <mathias@tela-botanica.org>
 * @author Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @author Aurelien PERONNET <aurelien@tela-botanica.org>
 * @license GPL v3 <http://www.gnu.org/licenses/gpl.txt>
 * @license CECILL v2 <http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt>
 * @copyright 1999-2014 Tela Botanica <accueil@tela-botanica.org>
 */

// Permet d'afficher le temps d'execution du service
$temps_debut = (isset($_GET['chrono']) && $_GET['chrono'] == 1) ? microtime(true) : '';

// Le fichier autoload.inc.php du Framework de Tela Botanica doit être appelée avant tout autre chose dans l'application.
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

	// Initialisation et lancement du serveur
	$Serveur = new RestServeur();
	$Serveur->executer();
}