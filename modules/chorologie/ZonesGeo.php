<?php
/**
 * Web service fournissant une liste de noms de communes de l'Isère
 * Paramètres :
 * - navigation.depart : élément auquel commencer la page (liste) servie
 * - navigation.limite : taille de page
 * - masque : un LIKE sera effectué entre le nom de la commune et ce masque
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
class ZonesGeo {

	protected $masque;
	protected $conteneur;
	protected $navigation;
	protected $table;
	protected $nom;

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'zones-geo';
		$this->navigation = $conteneur->getNavigation();
		$this->navigation->setActionNom($this->nom);
		$this->table = $this->conteneur->getParametre('chorologie.table');
	}

	public function consulter($ressources, $parametres) {
		$this->masque = $this->navigation->getFiltre('masque.nom');
		$zones = $this->listeZonesGeo();
		$total = count($zones);

		// encore du tabarouette de code générique qu'a rien à foutre là
		$resultat = new ResultatService();
		$this->navigation->setTotal($this->compterZonesGeo());
		$resultat->corps = array('entete' => $this->navigation->getEntete(), 'resultat' => $zones);
		return $resultat;
	}

	protected function listeZonesGeo() {
		$req = "SELECT DISTINCT (code_insee) AS code, nom FROM " . $this->table;
		if ($this->masque != null) {
			$masqueP = $this->conteneur->getBdd()->proteger($this->masque);
			$req .= " WHERE nom LIKE $masqueP";
		}
		$req .= " ORDER BY nom ASC";
		$req .= " LIMIT " . $this->navigation->getDepart() . ", " . $this->navigation->getLimite();

		$resultat = $this->conteneur->getBdd()->recupererTous($req);

		return $resultat;
	}

	protected function compterZonesGeo() {
		$req = "SELECT count(DISTINCT code_insee) AS compte FROM " . $this->table;
		if ($this->masque != null) {
			$masqueP = $this->conteneur->getBdd()->proteger($this->masque);
			$req .= " WHERE nom LIKE $masqueP";
		}
		$resultat = $this->conteneur->getBdd()->recuperer($req);

		return $resultat['compte'];
	}
}