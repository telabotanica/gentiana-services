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
		
		$this->cheminBaseCartes = $this->conteneur->getParametre('cartes.chemin');
		$this->cheminBaseCache = $this->conteneur->getParametre('cartes.chemin_cache');
		
		$this->couleurs_legende_taxons = explode(',', $this->conteneur->getParametre('cartes.couleurs_legende_taxons'));
		$this->couleurs_legende_globale = explode(',', $this->conteneur->getParametre('cartes.couleurs_legende_globale'));
		
		$this->options_cache = array('mise_en_cache' => $this->conteneur->getParametre('cartes.cache_miseEnCache'),
									'stockage_chemin' => $this->conteneur->getParametre('cartes.cache_stockageChemin'),
									'duree_de_vie' => $this->conteneur->getParametre('cartes.cache_dureeDeVie'));
	}

	public function consulter($ressources, $parametres) {
		
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
	
	public function getLegendeCarteTaxonsParZones($nb_max) {
		$couleurs = $this->couleurs_legende_globale;
		$legende = array(
				"seuil0" => array(
					"code" => "",
					"couleur" => $couleurs[0],
					"css" => "",	
					"nom" => "Non renseignée",
					"description" => "Zone géographique non renseignée."
				)
		);
		array_shift($couleurs);
		$borne_min = 0;
		$borne_max = 1;
		
		for($i = 1; $i <= 5; $i++) {
			$borne_max = ($i == 5) ? $nb_max : $borne_max;
			$legende["seuil".$i] = array(
					"code" => "",
					"couleur" => $couleurs[$i-1],
					"css" => "",	
					"nom" => "de ".$borne_min." à ".$borne_max." taxons",
					"description" => "de ".$borne_min." à ".$borne_max." taxons."
				);
			$borne_min = $borne_max + 1;
			$borne_max = ($i == 5) ? $nb_max : ($i * 150);
		}
		
		return $legende;
	}
	
	public function getLegendeCarteParTaxon() {
		$couleurs = $this->couleurs_legende_taxons;
		$legende = array(
				"0" => array(
					"code" => "0",
					"couleur" => $couleurs[0],
					"css" => "",
					"nom" => "Absent",
					"description" => "Absent de la zone."
				),
				"n/a" => array(
					"code" => "",
					"couleur" => $couleurs[1],
					"css"	=> "",
					"nom" => "Non renseignée",
					"description" => "Zone géographique non renseignée."	
				),
				"1" => array(
					"code" => "1",
					"couleur" => $couleurs[2],
					"css"	=> "",	
					"nom" => "Présent",
					"description" => "Présent dans la zone."	
				),
				"1?" => array(
					"code" => "1?",
					"couleur" => $couleurs[3],
					"css"	=> 	"",
					"nom" => "A confimer",
					"description" => "Présence dans la zone à confirmer."
				)
		);
		
		return $legende;
	}
	
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
		
		return $css;
	}
	
	public function getCarteTaxonsParZones() {
		$this->envoyerCacheSiExiste('global');
		
		$nbMaxTaxonsParZone = $this->getNbMaxTaxonsParZones();
		$legende = $this->getLegendeCarteTaxonsParZones($nbMaxTaxonsParZone);
				
		$taxonsParZones = $this->compterTaxonsParZones();
		$infos_zones = array();
		
		foreach($taxonsParZones as $zone) {
			$infos_zones[$zone['code_insee']] = "- ".$zone['nb']." taxons présents";
			$legende[$this->getSeuil($zone['nb'])]['css'] .= $legende[$this->getSeuil($zone['nb'])]['css'] != "" ? ', ' : '' ;
			$legende[$this->getSeuil($zone['nb'])]['css'] .= "#".$this->prefixe_id_zone.$zone['code_insee'];
		}
		
		$this->style = $this->convertirLegendeVersCss($legende);
		$svg = $this->assemblerSvg(
				$this->calculerHauteur($this->largeurDemandee),
				$this->largeurDemandee,
				$this->style,
				$infos_zones);
		
		$this->sauverCache(array('style' => $this->style, 'infos_zones' => $infos_zones), 'global');
		$this->envoyerSvg($svg);
	}
	
	public function getCarteParTaxon($champ_nt_ou_nn, $nt_ou_nn) {	
		$this->envoyerCacheSiExiste($champ_nt_ou_nn.$nt_ou_nn);

		$legende = $this->getLegendeCarteParTaxon();
		$zonesTaxon = $this->obtenirPresenceTaxon($champ_nt_ou_nn, $nt_ou_nn);
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
				$infos_zones);
				
		$this->sauverCache(array('style' => $this->style, 'infos_zones' => $infos_zones), $champ_nt_ou_nn.$nt_ou_nn);
		$this->envoyerSvg($svg);
		exit;
	}
	
	public function getNbMaxTaxonsParZones() {
		$req = "SELECT MAX(nb) as nb_max FROM (SELECT COUNT(num_nom) as nb, code_insee FROM ".$this->table." ch ".
				"WHERE presence = 1 ".
				"GROUP BY code_insee) c";

		$resultat = $this->conteneur->getBdd()->recuperer($req);
		return $resultat['nb_max'];
	}
	
	public function compterTaxonsParZones() {
		//TODO : ceci est il efficace à long terme ?
		// Si jamais le service a besoin d'être accéléré, la dernière borne
		// pourrait prendre la forme de "XXX taxons et plus" (où XXX est l'avant dernière borne)
		$req = "SELECT COUNT(num_nom) as nb, code_insee FROM ".$this->table." ".
				"WHERE presence = 1 ".
				"GROUP BY code_insee ".
				"ORDER BY nb DESC ";

		$resultat = $this->conteneur->getBdd()->recupererTous($req);
		return $resultat;
	}
	
	public function obtenirPresenceTaxon($champ_nt_ou_nn, $nt_ou_nn) {
		$req = "SELECT code_insee, presence FROM ".$this->table." ".
				"WHERE ".$champ_nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($nt_ou_nn);
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
		echo json_encode($legende);
		exit;
	}
	
	private function envoyerSvg($svg) {
		header("Content-type: image/svg+xml");
		echo $svg;
		exit;
	}
}