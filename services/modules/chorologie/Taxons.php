<?php
/**
 * Web service fournissant une liste de taxons présents en Isère
 * Paramètres :
 * - navigation.depart : élément auquel commencer la page (liste) servie
 * - navigation.limite : taille de page
 * - masque.(nom|zone-geo) : un LIKE sera effectué entre le champ et le masque
 * - masque.proteges : si '0' retourne les protections NULL, si '1' les NOT NULL
 * 
 * @TODO ça devrait s'appeler "noms" et pas "taxons"
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
	protected $tableNomsVernaculaires;
	protected $nom;
	protected $tri = "nom_sci";
	protected $tris_possibles = array('code_insee', 'nom', 'nom_sci', 'num_tax', 'num_nom', 'presence', 'noms_vernaculaires', 'protection');
	protected $tri_dir = "ASC";

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'taxons';
		$this->navigation = $conteneur->getNavigation();
		$this->table = $this->conteneur->getParametre('chorologie.table');
		$this->tableNomsVernaculaires = $this->conteneur->getParametre('chorologie.table_nv');
		$this->masque = array();
	}

	public function consulter($ressources, $parametres) {
		if ($this->navigation->getFiltre('masque.nom') != null) {
			$this->masque['nom'] = $this->navigation->getFiltre('masque.nom');
		}
		if ($this->navigation->getFiltre('masque.zone-geo') != null) {
			$this->masque['zone-geo'] = $this->navigation->getFiltre('masque.zone-geo');
		}
		if ($this->navigation->getFiltre('masque.proteges') === '0') {
			$this->masque['proteges'] = false;
		} elseif($this->navigation->getFiltre('masque.proteges') === '1') {
			$this->masque['proteges'] = true;
		}

		// TODO: renvoyer une erreur si le tri ou la direction n'existent pas ?
		// ou bien renvoyer le tri par défaut ?
		if($this->navigation->getFiltre('retour.tri') != null && in_array($this->navigation->getFiltre('retour.tri'), $this->tris_possibles)) {
			$this->tri = $this->navigation->getFiltre('retour.tri');
		}

		if($this->navigation->getFiltre('retour.ordre') != null) {
			$dir = $this->navigation->getFiltre('retour.ordre');
			$this->tri_dir = ($dir == "ASC" || $dir == "DESC") ? $dir : $this->tri_dir;
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
		$req = "SELECT DISTINCT num_nom, nom_sci, group_concat(DISTINCT nom_vernaculaire) as noms_vernaculaires, max(presence) as presence, protection";
		$req .= " FROM " . $this->table . " c";
		$req .= " LEFT JOIN " . $this->tableNomsVernaculaires . " nv ON c.num_tax=nv.num_tax";
		$req .= $this->construireWhere();
		// on groupe par num_nom, charge au responsable de la BD de ne mettre au
		// possible que des noms retenus afin de n'avoir qu'une entrée par espèce
		$req .= " GROUP BY c.num_nom";
		$req .= " ORDER BY ".$this->tri." ".$this->tri_dir." ";
		$req .= " LIMIT " . $this->navigation->getDepart() . ", " . $this->navigation->getLimite();

		$resultat = $this->conteneur->getBdd()->recupererTous($req);
		// décodage des statuts de protection
		foreach ($resultat as $k => $r) {
			$sp = null;
			if ($r['protection'] != '') {
				// Décodage statuts avec texte venant d'eFlore
				$sp = json_decode($r['protection']);
			}
			if ($sp == '') {
				// Statut brut d'Infloris
				$sp = $r['protection'];
			}
			$resultat[$k]['statuts_protection'] = $sp;
			unset($resultat[$k]['protection']);
		}

		return $resultat;
	}

	protected function compterTaxons() {
		$req = "SELECT count(DISTINCT num_nom, nom_sci) AS compte FROM " . $this->table;
		$req .= $this->construireWhere();
		$resultat = $this->conteneur->getBdd()->recuperer($req);

		return $resultat['compte'];
	}

	protected function construireWhere() {
		$where = "";
		$conditions = array();
		if(!empty($this->masque)) {
			if(isset($this->masque['nom'])) {
				$masqueNom = $this->conteneur->getBdd()->proteger($this->masque['nom']);
				$conditions[] = "nom_sci LIKE $masqueNom";
			}
			if(isset($this->masque['proteges'])) {
				// teste '' plutôt que NULL car le fichier infloris contenant la
				// colonne "protection", l'import conduit à des '' partout et aucun NULL
				$conditions[] = "protection " . ($this->masque['proteges'] === true ? " != " : " = ") . "''";
			}
			if(isset($this->masque['zone-geo'])) {
				$masqueZg = $this->conteneur->getBdd()->proteger($this->masque['zone-geo']);
				$conditions[] = "code_insee = $masqueZg";
			}
			$where = " WHERE ".implode(' AND ', $conditions);
		}
		return $where;
	}
}