<?php
// declare(encoding='UTF-8');
/**
 * Navigation gère les url de navigation en fonction d'un départ et d'une limite
 *
 * @category  DEL
 * @package   Services
 * @package   Bibliotheque
 * @version   0.1
 * @author    Mathias CHOUET <mathias@tela-botanica.org>
 * @author    Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @author    Aurelien PERONNET <aurelien@tela-botanica.org>
 * @license   GPL v3 <http://www.gnu.org/licenses/gpl.txt>
 * @license   CECILL v2 <http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt>
 * @copyright 1999-2014 Tela Botanica <accueil@tela-botanica.org>
*/
class Navigation {

	private $conteneur;
	private $parametresUrl;
	private $serviceNom;
	private $actionNom;
	private $filtresPossibles;
	private $filtresActifs;

	private $total;
	private $sansLimite;

	/**
	 * Constructeur de la classe Navigation
	 * @param Array $parametresUrl (optionnel) la liste des paramètre issus du Conteneur
	 */
	public function __construct($conteneur) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;

		$contexte = $this->conteneur->getContexte();
		$this->parametresUrl = $contexte->getQS();
		$this->serviceNom = $contexte->getRessource(0); // 0 car non paginé

		$this->filtresPossibles = $this->conteneur->getparametreTableau($this->serviceNom.'.masques_possibles');
		$this->chargerFiltresActifs();
	}

	private function chargerFiltresActifs() {
		if ($this->parametresUrl != null) {
			foreach ($this->parametresUrl as $paramNom => $valeur) {
				if (in_array($paramNom, $this->filtresPossibles)) {
					$this->filtresActifs[$paramNom] = $valeur;
				}
			}
		}
	}

	/**
	 * Obtenir la valeur courante de départ
	 */
	public function getDepart() {
		$navDepart = $this->getParamUrl('navigation.depart');
		return ($navDepart == null) ? 0 : $navDepart ;
	}

	/**
	 * Obtenir la limite courante
	 */
	public function getLimite() {
		$limiteParam = $this->getParamUrl('navigation.limite');
		$limite = 10;
		if ($limiteParam != null && is_numeric($limiteParam)) {
			$limite = ($limiteParam < 1000) ? $limiteParam : 1000;// Pour éviter les abus !
		}
		return $limite;
	}

	private function getParamUrl($nom) {
		$valeur = isset($this->parametresUrl[$nom]) ? $this->parametresUrl[$nom] : null;
		return $valeur;
	}

	/**
	 * Récupérer l'url de navigation en concaténant d'éventuels paramètres
	 * @param $depart l'entier de départ de la recherche
	 * @param $limite le nombre de résultats à retourner
	 * @param $parametresAdditionnels le tableau contenant les parametres => valeurs additionnels
	 */
	private function obtenirUrlNavigation($depart, $limite) {
		$parametres = $this->parametresUrl;
		$parametres['navigation.depart'] = $depart;
		$parametres['navigation.limite'] = $limite;

		$urlServiceBase = $this->conteneur->getParametre('url_service_base').$this->serviceNom;
		if ($this->actionNom != '') {
			$urlServiceBase .= "/" . $this->actionNom;
		}
		$urlNavigation = $this->conteneur->getUrl($urlServiceBase);
		$urlNavigation->setOption(Url::OPTION_ENCODER_VALEURS, true);
		$urlNavigation->setRequete($parametres);
		$url = $urlNavigation->getURL();

		return $url;
	}

	/**
	 * Récupérer le lien pour afficher les résultats précédents en fonction des paramètres
	 */
	public function recupererHrefPrecedent() {
		$departActuel = $this->getDepart();
		$limite = $this->getLimite();
		$departPrecedent = max(0, $departActuel - $limite);
		$url = null;
		if ($departActuel > 0) {
			$url = $this->obtenirUrlNavigation($departPrecedent, $limite);
		}
		return $url;
	}

	/**
	 * Récupérer le lien pour afficher les résultats suivants en fonction des paramètres
	 */
	public function recupererHrefSuivant() {
		$departActuel = $this->getDepart();
		$limite = $this->getLimite();
		$departSuivant = $departActuel + $limite;
		$url = null;
		if ($departSuivant < $this->total) {
			$url = $this->obtenirUrlNavigation($departSuivant, $limite);
		}
		return $url;
	}

	/**
	 * Retourner le nombre total d'éléments
	 */
	public function getTotal() {
		return $this->total;
	}

	/**
	 * Enregistrer le nombre total d'éléments
	 * @param int $total le nombre d'éléments
	 */
	public function setTotal($total) {
		$this->total = $total;
	}

	/**
	 * Changer la valeur de sans limite pour ne pas l'afficher dans l'entete
	 * */
	public function setSansLimite() {
		$this->sansLimite = true;
	}

	/**
	 * Définit le nom de l'action en cours du service en cours pour générer les
	 * URLs précédentes et suivantes
	 * @param string $nom
	 */
	public function setActionNom($nom) {
		$this->actionNom = $nom;
	}

	/**
	 * Génère un tableau contenant les informations pour l'entête des services renvoyant une liste de résultats.
	 *
	 * @return array Le tableau d'entête prés à être encodé en JSON.
	 */
	public function getEntete() {
		$entete = array();
		$entete['masque'] = $this->getChaineFiltresActifs();

		$entete['total'] = $this->getTotal();
		if ($this->sansLimite == false) {
			$entete['depart'] = (int) $this->getDepart();
			$entete['limite'] = (int) $this->getLimite();

			$lienPrecedent = $this->recupererHrefPrecedent();
			if ($lienPrecedent != null) {
				$entete['href.precedent'] = $lienPrecedent;
			}

			$lienSuivant = $this->recupererHrefSuivant();
			if ($lienSuivant != null) {
				$entete['href.suivant'] = $lienSuivant;
			}
		}

		return $entete;
	}

	/**
	 * Retourne les filtres au format chaine sous la forme filtre1=valeur1&filtre2=valeur2.
	 *
	 * @return String la chaine de caractères ou une chaine vide si pas de filtre.
	 */
	private function getChaineFiltresActifs() {
		return (!empty($this->filtresActifs)) ? http_build_query($this->filtresActifs) : '';
	}

	/**
	 * Récupérer tout ou partie des filtres présent dans l'url.
	 *
	 * @param String $filtreNom (optionnel) le nom du filtre tel que présent dans l'url.
	 * @return mixed si un filtre est passé en paramètre retourn la valeur correspondante, si pas de paramétre
	 * retourne le tableau complet des filtres. False en cas d'erreur.
	 * */
	public function getFiltre($filtreNom = null) {
		$retour = false;
		if ($filtreNom == null) {
			$retour = $this->filtresActifs;
		} else if ($filtreNom != null && isset($this->filtresActifs[$filtreNom])) {
			$retour = $this->filtresActifs[$filtreNom];
		}
		return $retour;
	}
}