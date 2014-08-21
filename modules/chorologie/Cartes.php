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

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'cartes';
		$this->navigation = $conteneur->getNavigation();
		$this->table = $this->conteneur->getParametre('chorologie.table');
		$this->cheminBaseCartes = $this->conteneur->getParametre('cartes.chemin');
		$this->cheminBaseCache = $this->conteneur->getParametre('cartes.chemin_cache');
	}

	public function consulter($ressources, $parametres) {
		
		if($this->navigation->getFiltre('retour.format') != null && is_numeric($this->navigation->getFiltre('retour.format'))) {
			$this->largeurDemandee = intval($this->navigation->getFiltre('retour.format'));
		}
				
		if(empty($ressources)) {
			$this->getCarteTaxonsParZones();
		} elseif(preg_match("/^(nt|nn):([0-9]+)$/", $ressources[0], $matches)) {
			$nt_ou_nn = ($matches[1] == "nn") ? "num_nom" : "num_tax";
			$this->getCarteParTaxon($nt_ou_nn, $matches[2]);
		}
		return $resultat;
	}
	
	public function getCarteTaxonsParZones() {
		
		$this->envoyerCacheSiExiste('global');
		
		$taxonsParZones = $this->compterTaxonsParZones();
		$doc = new DOMDocument();
		$doc->validateOnParse = true;
		$doc->loadXML($this->assemblerSvg(file_get_contents($this->cheminBaseCartes.'isere_communes.svg'), 
						$this->calculerHauteur($this->largeurDemandee), 
						$this->largeurDemandee)
					);

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
		
		$this->envoyerCacheSiExiste($nt_ou_nn.$num_nom);
		
		$zonesTaxon = $this->obtenirPresenceTaxon($nt_ou_nn, $num_nom);
		$doc = new DOMDocument();
		$doc->validateOnParse = true;
		$doc->loadXML($this->assemblerSvg(file_get_contents($this->cheminBaseCartes.'isere_communes.svg'), 
						$this->calculerHauteur($this->largeurDemandee), 
						$this->largeurDemandee)
				);
		
		foreach($zonesTaxon as $zone) {
			$zone_svg = $doc->getElementById($zone['code_insee']);
			$doc->getElementById($zone['code_insee'])->setAttribute("class", $this->getPresenceTaxon($zone['presence']));
		}
		
		$this->sauverCache($doc, $nt_ou_nn.$num_nom);
		$this->envoyerSvg($doc);
		exit;
	}
	
	public function compterTaxonsParZones() {
		$req = "SELECT COUNT(num_nom) as nb, code_insee FROM ".$this->table." ".
				"WHERE presence = 1 ".
				"GROUP BY code_insee";

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
		$seuil = "";
		if($nb_taxons <= 1) {
			$seuil = "1";
		} elseif (2 <= $nb_taxons && $nb_taxons <= 156) {	
			$seuil = "2";
		} elseif (153 <= $nb_taxons && $nb_taxons <= 283) {
			$seuil = "3";
		} elseif (284 <= $nb_taxons && $nb_taxons <= 511) {
			$seuil = "4";
		} elseif (512 <= $nb_taxons) {
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
	
	private function assemblerSvg($contenu_svg_string, $hauteur, $largeur) {		
		$tpl_svg = file_get_contents($this->cheminBaseCartes.'/entete_pied_svg.tpl');
		$svg = str_replace(array('{hauteur}','{largeur}','{contenu_svg}'), array($hauteur, $largeur, $contenu_svg_string), $tpl_svg);
		return $svg;
	}
	
	private function envoyerCacheSiExiste($id) {
		if(($cache = $this->getCache($id)) != null) {
			$cache = $this->assemblerSvg($cache, $this->calculerHauteur($this->largeurDemandee), $this->largeurDemandee);
			header("Content-type: image/svg+xml");
			echo $cache;
			exit;
		}
	}
	
	private function envoyerSvg($doc) {
		header("Content-type: image/svg+xml");
		$doc->save('php://output');
		exit;
	}
}