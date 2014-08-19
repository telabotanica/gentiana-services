<?php
/**
 * Web service fournissant une liste de taxons présents en Isère
 * Paramètres :
 * - navigation.depart : élément auquel commencer la page (liste) servie
 * - navigation.limite : taille de page
 * - masque : un LIKE sera effectué entre le nom sxientifique et ce masque
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
class Taxons {

	protected $masque;
	protected $conteneur;
	protected $navigation;
	protected $table;
	protected $nom;

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'taxons';
		$this->navigation = $conteneur->getNavigation();
		$this->navigation->setActionNom($this->nom);
		$this->table = $this->conteneur->getParametre('chorologie.table');
		$this->masque = array();
	}

	public function consulter($ressources, $parametres) {
		if($this->navigation->getFiltre('masque.nom') != null) {
			$this->masque['nom'] = $this->navigation->getFiltre('masque.nom');
		}
		if($this->navigation->getFiltre('masque.zone-geo') != null) {
			$this->masque['zone-geo'] = $this->navigation->getFiltre('masque.zone-geo');
		}
		$zones = $this->listeTaxons();
		$total = count($zones);

		// encore du tabarouette de code générique qu'a rien à foutre là
		$resultat = new ResultatService();
		$this->navigation->setTotal($this->compterTaxons());
		$resultat->corps = array('entete' => $this->navigation->getEntete(), 'resultat' => $zones);
		return $resultat;
	}

	protected function listeTaxons() {
		$req = "SELECT DISTINCT num_nom, num_tax, nom_sci FROM " . $this->table;
		$req .= $this->construireMasque();
		$req .= " ORDER BY nom_sci ASC";
		$req .= " LIMIT " . $this->navigation->getDepart() . ", " . $this->navigation->getLimite();

		$resultat = $this->conteneur->getBdd()->recupererTous($req);

		return $resultat;
	}
	
	private function construireMasque() {
		$where = "";
		$conditions = array();
		if(!empty($this->masque)) {
			if(isset($this->masque['nom'])) {
				$masqueNom = $this->conteneur->getBdd()->proteger($this->masque['nom']);
				$conditions[] = "nom_sci LIKE $masqueNom";
			}
			
			if(isset($this->masque['zone-geo'])) {
				$masqueZg = $this->conteneur->getBdd()->proteger($this->masque['zone-geo']);
				$conditions[] = "code_insee = $masqueZg";
			}
			$where = " WHERE ".implode(' AND ', $conditions);
		}
		return $where;
	}

	protected function compterTaxons() {
		$req = "SELECT count(DISTINCT num_nom, num_tax, nom_sci) AS compte FROM " . $this->table;
		$req .= $this->construireMasque();
		$resultat = $this->conteneur->getBdd()->recuperer($req);

		return $resultat['compte'];
	}
}