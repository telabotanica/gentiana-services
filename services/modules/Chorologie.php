<?php
/**
* 
*
 * @category   Gentiana
 * @package    Services
 * @subpackage Chorologie
 * @version    0.1
 * @author     Mathias CHOUET <mathias@tela-botanica.org>
 * @author     Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @author     Aurelien PERONNET <aurelien@tela-botanica.org>
 * @license    GPL v3 <http://www.gnu.org/licenses/gpl.txt>
 * @license    CECILL v2 <http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt>
 * @copyright  1999-2014 Tela Botanica <accueil@tela-botanica.org>
*/
class Chorologie extends RestService {

	protected $conteneur;
	protected $ressources;
	protected $parametres;
	protected $cheminCourant;
	protected $service;
	protected $actionsPossibles;

	public function __construct() {
		$this->cheminCourant = dirname(__FILE__).DS;
		$this->conteneur = Conteneur::getInstance();
		$this->service = 'chorologie';
		$this->actionsPossibles = array('zones-geo', 'taxons', 'cartes', 'infos-espece');

		// pourquoi ces machins ne sont-ils pas dans RestService ?
		$chemin = Config::get('chemin_configurations') . "config_" . $this->service . ".ini";
		//$this->conteneur->getConfig ?
		Config::charger($chemin);
	}

	public function consulter($ressources, $parametres) {
		$this->ressources = $ressources;
		$this->parametres = $parametres;

		$resultat = '';
		$reponseHttp = new ReponseHttp();

		// quelle action ?
		$action = null;
		if (count($this->ressources) > 0) {
			$action = array_shift($this->ressources);
		}

		if (!in_array($action, $this->actionsPossibles)) {
			$message = "L'action demandée '{$action}' n'existe pas dans le service {$this->service}."
				. "\nActions possibles: " . implode(", ", $this->actionsPossibles) . ".";
			throw new Exception($message, RestServeur::HTTP_CODE_RESSOURCE_INTROUVABLE);
		}

		// exécute le bousin
		$resultat = $this->consulterAction($action);
		$reponseHttp->setResultatService($resultat);
		$reponseHttp->emettreLesEntetes();
		$corps = $reponseHttp->getCorps();

		return $corps;
	}

	protected function consulterAction($action) {
		// autoloader rustiquasse
		$classe = $this->obtenirNomClasseService($action);
		$chemin = $this->cheminCourant . $this->service . DS . $classe . '.php';
		if (file_exists($chemin)) {
			require_once $chemin;
			$service = new $classe($this->conteneur);
		}

		$retour = $service->consulter($this->ressources, $this->parametres);
		return $retour;
	}

	// pourquoi ce truc n'est pas factorisé ?
	protected function obtenirNomClasseService($mot) {
		$classeNom = str_replace(' ', '', ucwords(strtolower(str_replace('-', ' ', $mot))));
		return $classeNom;
	}
}
?>