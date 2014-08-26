<?php
/**
 * Le conteneur encapsule les classes servant aux scripts.
 * Il gère leur instanciation, ainsi que la récupération des paramètres depuis
 * le fichier de configuration, et de la ligne de commande.
 *
 * @category	eFlore
 * @package		Scripts
 * @subpackage	Bibliotheque
 * @author		Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @copyright	Copyright (c) 2014, Tela Botanica (accueil@tela-botanica.org)
 * @license		CeCILL v2 http://www.cecill.info/licences/Licence_CeCILL_V2-fr.txt
 * @license		GNU-GPL http://www.gnu.org/licenses/gpl.html
 */
class Conteneur {
	protected $parametres = array();
	protected $partages = array();

	public function __construct(array $parametres = null) {
		$this->parametres = is_null($parametres) ? array() : $parametres;
	}

	public function getParametre($cle) {
		$valeur = isset($this->parametres[$cle]) ? $this->parametres[$cle] : Config::get($cle);
		return $valeur;
	}

	/**
	 * Obtenir un paramètre depuis le tableau de paramètres ou depuis le fichier de config
	 * et le transformer en tableau s'il est de la forme : "cle=valeur,cle=valeur,..."
	 * @param String $cle le nom du paramètre
	 * @return la valeur du paramètre
	 */
	public function getParametreTableau($cle) {
		$tableau = array();
		$parametre = $this->getParametre($cle);
		if (empty($parametre) === false) {
			$tableauPartiel = explode(',', $parametre);
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

	public function setParametre($cle, $valeur) {
		$this->parametres[$cle] = $valeur;
	}

	public function getOutils() {
		if (!isset($this->partages['Outils'])){
			$this->partages['Outils'] = new Outils();
		}
		return $this->partages['Outils'];
	}

	public function getMessages() {
		if (!isset($this->partages['Messages'])){
			$this->partages['Messages'] = new Messages($this->getParametre('v'));
		}
		return $this->partages['Messages'];
	}

	public function getRestClient() {
		if (!isset($this->partages['RestClient'])){
			$this->partages['RestClient'] = new RestClient();
		}
		return $this->partages['RestClient'];
	}

	public function getBdd() {
		if (!isset($this->partages['Bdd'])){
			$this->partages['Bdd'] = new Bdd();
		}
		return $this->partages['Bdd'];
	}
}