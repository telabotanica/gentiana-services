<?php
 /**
 * Singleton donnant accès à des services d'utilité générale
 * Le conteneur encapsule l'instanciation des classes ainsi que la récupération des paramètres depuis l'url ou
 * les fichiers de configuration
 *
 * @version   0.1
 * @author    Mathias CHOUET <mathias@tela-botanica.org>
 * @author    Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @author    Aurelien PERONNET <aurelien@tela-botanica.org>
 * @license   GPL v3 <http://www.gnu.org/licenses/gpl.txt>
 * @license   CECILL v2 <http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt>
 * @copyright 1999-2014 Tela Botanica <accueil@tela-botanica.org>
 */
class Conteneur {

	private static $instance = null;

	protected $parametres = array();
	protected $partages = array();

	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new Conteneur();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function setParametre($cle, $valeur) {
		$this->parametres[$cle] = $valeur;
	}

	public function getParametre($cle) {
		$valeur = isset($this->parametres[$cle]) ? $this->parametres[$cle] : Config::get($cle);
		return $valeur;
	}

	public function getParametreTableau($cle) {
		$tableau = array();
		$parametre = $this->getParametre($cle);
		if (empty($parametre) === false) {
			$tableauPartiel = explode(',', $parametre);
			$tableauPartiel = array_map('trim', $tableauPartiel);
			foreach ($tableauPartiel as $champ) {
				if (strpos($champ, '=') === false) {
					$tableau[] = trim($champ);
				} else {
					list($cle, $val) = explode('=', $champ);
					$tableau[trim($cle)] = trim($val);
				}
			}
		}
		return $tableau;
	}

	public function getBdd() {
		if (!isset($this->partages['Bdd'])){
			$this->partages['Bdd'] = new Bdd();
		}
		return $this->partages['Bdd'];
	}
	
	public function getCache($dossierStockage = null) {
		if (!isset($this->partages['Cache'])){
			$params = array(
				'mise_en_cache' => $this->getParametre('cache'), 
				'stockage_chemin' => is_null($dossierStockage) ? $this->getParametre('chemincache') : $dossierStockage, 
				'duree_de_vie' => $this->getParametre('dureecache')
			);
			$this->partages['Cache'] = new CacheSimple($params);
		}
		return $this->partages['Cache'];
	}

	public function getRestClient() {
		if (!isset($this->partages['RestClient'])){
			$this->partages['RestClient'] = new RestClient();
		}
		return $this->partages['RestClient'];
	}

	public function getUrl($base) {
		return new Url($base);
	}

	public function getNavigation() {
		if (!isset($this->partages['Navigation'])){
			$this->partages['Navigation'] = new Navigation($this);
		}
		return $this->partages['Navigation'];
	}

	public function getContexte() {
		if (!isset($this->partages['contexte'])) {
			$this->partages['contexte'] = new Contexte($this, $_SERVER, $_GET, $_POST, $_SESSION, $_COOKIE);
		}
		return $this->partages['contexte'];
	}
}
?>