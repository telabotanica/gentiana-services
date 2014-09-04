<?php
/**
 * Web service fournissant une cartes de taxons présents en Isère
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
class Cartes {

	protected $masque;
	protected $conteneur;
	protected $navigation;
	protected $table;
	protected $nom;
	protected $cheminBaseCartes;
	protected $cheminBaseCache;
	
	protected $largeurOrig = 1000;
	protected $longeurOrig = 1043;
	
	protected $largeurDefaut = 720;
	protected $largeurDemandee = 1024;

	/** Nombre de taxons pour changer de tranche */
	protected $pasParDefaut = 150;
	
	protected $style = null;
	protected $couleurs_legende_taxons = null;
	protected $couleurs_legende_globale = null;
	
	protected $prefixe_id_zone = 'INSEE-C-';
	
	protected $options_cache = 	array();

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'cartes';
		$this->navigation = $conteneur->getNavigation();
		$this->table = $this->conteneur->getParametre('chorologie.table');
		
		$this->cheminBaseCartes = $this->conteneur->getParametre('chorologie_cartes.chemin');
		$this->cheminBaseCache = $this->conteneur->getParametre('chorologie_cartes.chemin_cache');
		
		$this->couleurs_legende_taxons = explode(',', $this->conteneur->getParametre('chorologie_cartes.couleurs_legende_taxons'));
		$this->couleurs_legende_globale = explode(',', $this->conteneur->getParametre('chorologie_cartes.couleurs_legende_globale'));
		
		$this->options_cache = array(
			'mise_en_cache' => $this->conteneur->getParametre('chorologie_cartes.cache_miseEnCache'),
			'stockage_chemin' => $this->conteneur->getParametre('chorologie_cartes.cache_stockageChemin'),
			'duree_de_vie' => $this->conteneur->getParametre('chorologie_cartes.cache_dureeDeVie')
		);
	}

	public function consulter($ressources, $parametres) {

		if($this->navigation->getFiltre('masque.proteges') != null && is_numeric($this->navigation->getFiltre('masque.proteges'))) {
			$this->masque['proteges'] = ($this->navigation->getFiltre('masque.proteges') === '1');
		}
		if($this->navigation->getFiltre('retour.format') != null && is_numeric($this->navigation->getFiltre('retour.format'))) {
			$this->largeurDemandee = intval($this->navigation->getFiltre('retour.format'));
		}
		
		if(empty($ressources)) {
			$this->getCarteTaxonsParZones();
		} elseif($ressources[0] == "legende") {
			$nbMaxTaxonsParZone = $this->getNbMaxTaxonsParZones();
			$this->envoyerLegende($this->getLegendeCarteTaxonsParZones($nbMaxTaxonsParZone));
		} elseif(preg_match("/^(nt|nn):([0-9]+)$/", $ressources[0], $matches)) {
			if(count($ressources) > 1 && $ressources[1] == "legende") {
				$this->envoyerLegende($this->getLegendeCarteParTaxon());
			} else {
				$champ_nt_ou_nn = ($matches[1] == "nn") ? "num_nom" : "num_tax";
				$this->getCarteParTaxon($champ_nt_ou_nn, $matches[2]);
			}

		}
		return $resultat;
	}

	/**
	 * Retourne une légende à niveaux (maximum 6) pour tous les taxons sur toute
	 * la dition, en fonction du nombre max de taxons par zone (adaptative)
	 */
	public function getLegendeCarteTaxonsParZones($nb_max) {
		$couleurs = $this->couleurs_legende_globale;
		$legende = array(
			"seuil0" => array(
				"code" => "",
				"couleur" => $couleurs[0],
				"css" => "",	
				"nom" => "Aucun taxon signalé",
				"description" => "Aucun taxon signalé sur cette zone"
			)
		);

		// réglage de l'échelle
		$pas = min($this->pasParDefaut, max(1, intval($nb_max / 5)));
		$borne_min = 1;
		$borne_max = $pas;
		$tranches = min($nb_max, 5);
		
		for($i = 1; $i <= $tranches; $i++) {
			$borne_max = ($i == $tranches) ? $nb_max : $borne_max;
			if ($borne_min == $borne_max) {
				$desc = "$borne_min taxon" . ($borne_min == 1 ? '' : 's');
			} else {
				$desc = "de $borne_min à $borne_max taxons";
			}
			$legende["seuil".$i] = array(
				"code" => "",
				"couleur" => $couleurs[$i],
				"css" => "",	
				"nom" => $desc,
				"description" => $desc
			);
			// tour suivant
			$borne_min = $borne_max + 1;
			$borne_max = ($i == 5) ? $nb_max : ($borne_max + $pas);
		}
		
		return $legende;
	}

	/**
	 * Retourne une légende pour un taxon donné, sur toute la dition
	 */
	public function getLegendeCarteParTaxon() {
		$couleurs = $this->couleurs_legende_taxons;
		$legende = array(
			"n/a" => array(
				"code" => "n/a",
				"couleur" => $couleurs[0],
				"css"	=> "",
				"nom" => "Non renseignée",
				"description" => "Zone géographique non renseignée."	
			),
			"0" => array(
				"code" => "0",
				"couleur" => $couleurs[1],
				"css" => "",
				"nom" => "Absent",
				"description" => "Absent de la zone."
			),
			"1?" => array(
				"code" => "1?",
				"couleur" => $couleurs[2],
				"css"	=> 	"",
				"nom" => "A confimer",
				"description" => "Présence dans la zone à confirmer."
			),
			"1" => array(
				"code" => "1",
				"couleur" => $couleurs[3],
				"css"	=> "",	
				"nom" => "Présent",
				"description" => "Présent dans la zone."	
			)
		);
		
		return $legende;
	}

	/**
	 * Crée un morceau de CSS pour colorer la carte en fonction de la légende
	 */
	private function convertirLegendeVersCss($legende) {
		$css = "";
		foreach($legende as $cle => $item) {
			if($item['css'] != '') {
				$css .= 
					$item['css']." {"."\n".
					"	fill:".$item['couleur'].";"."\n".		
					"}"."\n\n";
			}
		}

		$premiereTranche = reset($legende);
		$premiereCouleur = $premiereTranche['couleur'];
		$css .= "
			.communes { 
				fill           : " . $premiereCouleur . ";
				fill-opacity   : 1;
				stroke         : #f0f0f0;
				stroke-opacity : 1;
				stroke-width   : 0.002;
			}\n\n
		";

		return $css;
	}

	/**
	 * Retourne une carte globale de la dition, colorée en fonction du nombre de
	 * taxons dans chaque zone
	 */
	public function getCarteTaxonsParZones() {
		$nomCache = 'global';
		if ($this->masque['proteges']) {
			$nomCache .= '_proteges';
		}
		$this->envoyerCacheSiExiste($nomCache);
		
		$nbMaxTaxonsParZone = $this->getNbMaxTaxonsParZones();
		$legende = $this->getLegendeCarteTaxonsParZones($nbMaxTaxonsParZone);
				
		$taxonsParZones = $this->compterTaxonsParZones();
		$infos_zones = array();
		
		foreach($taxonsParZones as $zone) {
			$s = ($zone['nb'] > 1) ? 's' : '';
			$infos_zones[$zone['code_insee']] = sprintf("- ".$zone['nb']." taxon%s présent%s", $s, $s);
			$legende[$this->getSeuil($zone['nb'])]['css'] .= $legende[$this->getSeuil($zone['nb'])]['css'] != "" ? ', ' : '' ;
			$legende[$this->getSeuil($zone['nb'])]['css'] .= "#".$this->prefixe_id_zone.$zone['code_insee'];
		}
		
		$this->style = $this->convertirLegendeVersCss($legende);
		$svg = $this->assemblerSvg(
				$this->calculerHauteur($this->largeurDemandee),
				$this->largeurDemandee,
				$this->style,
				$infos_zones);
		
		$this->sauverCache(array('style' => $this->style, 'infos_zones' => $infos_zones), $nomCache);
		$this->envoyerSvg($svg);
	}

	/**
	 * Retourne une carte de présence pour un taxon donné, sur la dition
	 */
	public function getCarteParTaxon($champ_nt_ou_nn, $nt_ou_nn) {	
		$this->envoyerCacheSiExiste($champ_nt_ou_nn.$nt_ou_nn);

		$legende = $this->getLegendeCarteParTaxon();
		$zonesTaxon = $this->obtenirPresenceTaxon($champ_nt_ou_nn, $nt_ou_nn);
		//echo "<pre>" . print_r($zonesTaxon, true) . "</pre>";
		//exit;
		$cssCodesInsee = "";

		$infos_zones = array();		
		foreach($zonesTaxon as $zone) {
			$infos_zones[$zone['code_insee']] = "- ".$legende[$zone['presence']]['nom'];
			$legende[$zone['presence']]['css'] .= $legende[$zone['presence']]['css'] != "" ? ', ' : '' ;
			$legende[$zone['presence']]['css'] .= "#".$this->prefixe_id_zone.$zone['code_insee'];
		}

		$this->style = $this->convertirLegendeVersCss($legende);
		
		$svg = $this->assemblerSvg(
			$this->calculerHauteur($this->largeurDemandee),
			$this->largeurDemandee,
			$this->style,
			$infos_zones
		);

		$this->sauverCache(array('style' => $this->style, 'infos_zones' => $infos_zones), $champ_nt_ou_nn.$nt_ou_nn);
		$this->envoyerSvg($svg);
		exit;
	}

	protected function construireWhere() {
		$where = 'WHERE presence = 1';
		if ($this->masque['proteges']) {
			$where .= ' AND protection IS NOT NULL';
		}
		return $where;
	}
	
	public function getNbMaxTaxonsParZones() {
		$req = "SELECT MAX(nb) as nb_max FROM (SELECT COUNT(num_nom) as nb, code_insee FROM ".$this->table." ch"
			. " " . $this->construireWhere()
			. " GROUP BY code_insee) c";

		$resultat = $this->conteneur->getBdd()->recuperer($req);
		return $resultat['nb_max'];
	}
	
	public function compterTaxonsParZones() {
		//TODO : ceci est il efficace à long terme ?
		// Si jamais le service a besoin d'être accéléré, la dernière borne
		// pourrait prendre la forme de "XXX taxons et plus" (où XXX est l'avant dernière borne)
		$req = "SELECT COUNT(num_nom) as nb, code_insee FROM ".$this->table
			. " " . $this->construireWhere()
			. " GROUP BY code_insee"
			. " ORDER BY nb DESC";

		$resultat = $this->conteneur->getBdd()->recupererTous($req);
		return $resultat;
	}
	
	public function obtenirPresenceTaxon($champ_nt_ou_nn, $nt_ou_nn) {
		$req = "SELECT code_insee, presence FROM ".$this->table." ".
			"WHERE ".$champ_nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($nt_ou_nn);
		if ($this->masque['proteges']) {
			$req .= ' AND protection IS NOT NULL';
		}
		$resultat = $this->conteneur->getBdd()->recupererTous($req);
		return $resultat;
	}
	
	public function getSeuil($nb_taxons) {
		// TODO: factoriser les bornes avec la fonction qui gère la légende
		$seuil = "";
		if($nb_taxons <= 1) {
			$seuil = "1";
		} elseif (2 <= $nb_taxons && $nb_taxons <= 150) {	
			$seuil = "2";
		} elseif (151 <= $nb_taxons && $nb_taxons <= 300) {
			$seuil = "3";
		} elseif (301 <= $nb_taxons && $nb_taxons <= 450) {
			$seuil = "4";
		} elseif (451 <= $nb_taxons) {
			$seuil = "5";
		}			
		return "seuil".$seuil;
	}
	
	public function getPresenceTaxon($presence) {
		$presence = "";
		if($presence == "1") {
			$presence = "presence";
		} elseif($presence == "1?") {
			$presence = "presenceDouteuse";
		} elseif($presence == "0") {
			$presence = "presenceAucune";
		}
		return presence;
	}
	
	public function sauverCache($a_cacher, $cache_id) {
		$cache = new CacheSimple($this->options_cache);
		return $cache->sauver(serialize($a_cacher), $cache_id);
	}
	
	public function getCache($id) {
		$cache = new CacheSimple($this->options_cache);
		if(($contenu_cache = $cache->charger($id)) !== false) {
			$contenu_cache = unserialize($contenu_cache);
		}
		return $contenu_cache;
	}
	
	private function calculerHauteur($largeur) {
		$rapport = $this->longeurOrig/$this->largeurOrig;
		$hauteur = $rapport * $largeur;
		return intval($hauteur);
	}
	
	private function envoyerCacheSiExiste($id) {
		if(($cache = $this->getCache($id))) {
			$style = $cache['style'];
			$infos_zones = $cache['infos_zones'];
			$cache = $this->assemblerSvg($this->calculerHauteur($this->largeurDemandee), $this->largeurDemandee, $style, $infos_zones);
			$this->envoyerSvg($cache);
		}
	}
	
	private function assemblerSvg($hauteur, $largeur, $style, $infos_zones) {	
		$tpl_svg = $this->cheminBaseCartes.'/communes_isere.tpl.svg';
		$donnees = array(
						'hauteur'	=> $hauteur,
						'largeur'	=> $largeur,
						'css'		=> $style,
						'infos_zones'	=> $infos_zones
					);
		$svg = SquelettePhp::analyser($tpl_svg, $donnees);
		return $svg;
	}
	
	private function envoyerLegende($legende) {
		header("Content-type: application/json");
		echo json_encode(array_values($legende));
		exit;
	}
	
	private function envoyerSvg($svg) {
		header("Content-type: image/svg+xml");
		echo $svg;
		exit;
	}
}