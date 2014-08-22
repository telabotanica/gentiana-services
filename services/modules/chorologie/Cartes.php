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

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'cartes';
		$this->navigation = $conteneur->getNavigation();
		$this->table = $this->conteneur->getParametre('chorologie.table');
		$this->cheminBaseCartes = $this->conteneur->getParametre('cartes.chemin');
		$this->cheminBaseCache = $this->conteneur->getParametre('cartes.chemin_cache');
		$this->couleurs_legende_taxons = explode(',', $this->conteneur->getParametre('cartes.couleurs_legende_taxons'));
		$this->couleurs_legende_globale = explode(',', $this->conteneur->getParametre('cartes.couleurs_legende_globale'));
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
				$nt_ou_nn = ($matches[1] == "nn") ? "num_nom" : "num_tax";
				$this->getCarteParTaxon($nt_ou_nn, $matches[2]);
			}

		}
		return $resultat;
	}
	
	public function getLegendeCarteTaxonsParZones($nb_max) {
		$couleurs = $this->couleurs_legende_globale;
		$legende = array(
				array(
					"code" => "",
					"couleur" => $couleurs[0],
					"css" => ".seuil0",	
					"nom" => "Non renseignée",
					"description" => "Zone géographique non renseignée."
				)
		);
		array_shift($couleurs);
		$borne_min = 0;
		$borne_max = 1;
		
		for($i = 1; $i <= 5; $i++) {
			$borne_max = ($i == 5) ? $nb_max : $borne_max;
			$legende[] = array(
					"code" => "",
					"couleur" => $couleurs[$i-1],
					"css" => ".seuil".$i,	
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
				array(
					"code" => "0",
					"couleur" => $couleurs[0],
					"css" => ".presenceAucune",
					"nom" => "Absent",
					"description" => "Absent de la zone."
				),
				array(
					"code" => "",
					"couleur" => $couleurs[1],
					"css"	=> ".presenceNonRenseignee",
					"nom" => "Non renseignée",
					"description" => "Zone géographique non renseignée."	
				),
				array(
					"code" => "1",
					"couleur" => $couleurs[2],
					"css"	=> ".presence",	
					"nom" => "Présent",
					"description" => "Présent dans la zone."	
				),
				array(
					"code" => "1?",
					"couleur" => $couleurs[3],
					"css"	=> 	".presenceDouteuse",
					"nom" => "A confimer",
					"description" => "Présence dans la zone à confirmer."
				)
		);
		
		return $legende;
	}
	
	private function convertirLegendeVersCss($legende) {
		$css = "";
		foreach($legende as $item) {
			$css .= 
				$item['css']." {"."\n".
				"	fill:".$item['couleur'].";"."\n".		
				"}"."\n\n";
		}
		
		return $css;
	}
	
	public function getCarteTaxonsParZones() {
		
		$nbMaxTaxonsParZone = $this->getNbMaxTaxonsParZones();
		$this->style = $this->convertirLegendeVersCss($this->getLegendeCarteTaxonsParZones($nbMaxTaxonsParZone));
		$this->envoyerCacheSiExiste('global');
		
		$doc = new DOMDocument();
		$doc->validateOnParse = true;
		$doc->loadXML($this->assemblerSvg(file_get_contents($this->cheminBaseCartes.'isere_communes.svg'), 
						$this->calculerHauteur($this->largeurDemandee), 
						$this->largeurDemandee,
						$this->style)
					);
		
		$taxonsParZones = $this->compterTaxonsParZones();
		foreach($taxonsParZones as $zone) {
			$zone_svg = $doc->getElementById($zone['code_insee']);
			$zone_svg->setAttribute("class", $this->getSeuil($zone['nb']));
			$titre = $zone_svg->getAttribute("title")." (".$zone['code_insee'].") - ".$zone['nb']." taxons présents";
			$zone_svg->setAttribute("title", $titre);
		}
		
		$this->sauverCache($doc, 'global');
		$this->envoyerSvg($doc);
	}
	
	public function getCarteParTaxon($nt_ou_nn, $num_nom) {
		
		$this->style = $this->convertirLegendeVersCss($this->getLegendeCarteParTaxon());
		$this->envoyerCacheSiExiste($nt_ou_nn.$num_nom);
		
		$doc = new DOMDocument();
		$doc->validateOnParse = true;
		$doc->loadXML($this->assemblerSvg(file_get_contents($this->cheminBaseCartes.'isere_communes.svg'), 
						$this->calculerHauteur($this->largeurDemandee), 
						$this->largeurDemandee,
						$this->style)
				);
		
		$zonesTaxon = $this->obtenirPresenceTaxon($nt_ou_nn, $num_nom);
		foreach($zonesTaxon as $zone) {
			$zone_svg = $doc->getElementById($zone['code_insee']);
			$doc->getElementById($zone['code_insee'])->setAttribute("class", $this->getPresenceTaxon($zone['presence']));
		}
		
		$this->sauverCache($doc, $nt_ou_nn.$num_nom);
		$this->envoyerSvg($doc);
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
	
	public function obtenirPresenceTaxon($nt_ou_nn, $num_nom) {
		$req = "SELECT code_insee, presence FROM ".$this->table." ".
				"WHERE ".$nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($num_nom);
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
	
	public function sauverCache($doc, $cache_id) {
		$xpath = new DomXPath($doc);
		$nodes = $xpath->query("//*[contains(@class, 'communes')]");
		$cache = $doc->saveXML($nodes->item(0));
		return file_put_contents($this->cheminBaseCache.$cache_id.'.svg', $cache);
	}
	
	public function getCache($id) {
		$chemin = $this->cheminBaseCache.$id.'.svg';
		if(file_exists($chemin)) {
			return file_get_contents($chemin);
		} else {
			return null;
		}
	}
	
	private function calculerHauteur($largeur) {
		$rapport = $this->longeurOrig/$this->largeurOrig;
		$hauteur = $rapport * $largeur;
		return intval($hauteur);
	}
	
	private function assemblerSvg($contenu_svg_string, $hauteur, $largeur, $style) {	
		$tpl_svg = file_get_contents($this->cheminBaseCartes.'/entete_pied_svg.tpl');
		$svg = str_replace(array('{hauteur}','{largeur}','{contenu_svg}', '{css}'), array($hauteur, $largeur, $contenu_svg_string, $style), $tpl_svg);
		
		return $svg;
	}
	
	private function envoyerCacheSiExiste($id) {
		if(($cache = $this->getCache($id)) != null) {
			$cache = $this->assemblerSvg($cache, $this->calculerHauteur($this->largeurDemandee), $this->largeurDemandee, $this->style);
			header("Content-type: image/svg+xml");
			echo $cache;
			exit;
		}
	}
	
	private function envoyerLegende($legende) {
		header("Content-type: application/json");
		echo json_encode($legende);
		exit;
	}
	
	private function envoyerSvg($doc) {
		header("Content-type: image/svg+xml");
		$doc->save('php://output');
		exit;
	}
}