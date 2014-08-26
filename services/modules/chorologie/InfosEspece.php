<?php
/**
 * Web service fournissant des infos sommaires sur une espece
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
class InfosEspece {

	protected $masque;
	protected $conteneur;
	protected $navigation;
	protected $table;
	protected $nom;

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		//echo '<pre>'.print_r($this->conteneur, true).'</pre>';exit;
		$this->nom = 'infos-especes';
		$this->navigation = $conteneur->getNavigation();
		$this->table = $this->conteneur->getParametre('chorologie.table');
	}
	
	public function consulter($ressources, $parametres) {
		
		$retour = null;
		
		if(preg_match("/^(nt|nn):([0-9]+)$/", $ressources[0], $matches)) {
				$champ_nt_ou_nn = ($matches[1] == "nn") ? "num_nom" : "num_tax";
							
				$total_communes = $this->getTotalCommunes();
				$infos_especes = $this->getInfosEspece($champ_nt_ou_nn, $matches[2]);
				
				$retour = array(
					'nb_zones_totales' => 	$total_communes,
					'noms_vernaculaires' => array(),
					'statuts_protection' => array()	
				);
				$retour = array_merge($retour, $infos_especes);
		} else {
			// TODO : envoyer message erreur;
		}
		return $retour;
	}
		
	private function getTotalCommunes() {
		$req = "SELECT COUNT(DISTINCT code_insee) as nb_communes_total FROM chorologie";
		
		$resultat = $this->conteneur->getBdd()->recuperer($req);
		return $resultat['nb_communes_total'];
	}
	
	private function getInfosEspece($champ_nt_ou_nn, $nt_ou_nn) {
		
		$req = "SELECT COUNT(presence) as nb_presence_zones, num_nom, num_tax, nom_sci ".
				"FROM chorologie ".
				"WHERE ".$champ_nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($nt_ou_nn)." AND presence = 1";
		
		$resultat = $this->conteneur->getBdd()->recuperer($req);
		return $resultat;
	}
}
?>