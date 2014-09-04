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
	protected $tableNomsVernaculaires;
	protected $nom;

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'infos-especes';
		$this->navigation = $conteneur->getNavigation();
		$this->table = $this->conteneur->getParametre('chorologie.table');
		$this->tableNomsVernaculaires = $this->conteneur->getParametre('chorologie.table_nv');
	}
	
	public function consulter($ressources, $parametres) {

		$retour = null;
		if(preg_match("/^(nt|nn):([0-9]+)$/", $ressources[0], $matches)) {
			$champ_nt_ou_nn = ($matches[1] == "nn") ? "num_nom" : "num_tax";

			if(count($ressources) == 1) {
				// toutes les infos
				$infos_espece = $this->getInfosEspece($champ_nt_ou_nn, $matches[2]);
				$noms_vernaculaires = array(
					'noms_vernaculaires_fr' => $this->getNomsVernaculaires($champ_nt_ou_nn, $matches[2])
				);
				$retour = array_merge($infos_espece, $noms_vernaculaires);
			} else {
				// sous action du service
				$retour = array();
				switch($ressources[1]) {
					case "noms-vernaculaires":
						$retour = array('noms_vernaculaires_fr' => $this->getNomsVernaculaires($champ_nt_ou_nn, $matches[2]));
					break;
					case "statuts-protection":
						$retour = array('statuts_protection' => $this->getStatutsProtection($champ_nt_ou_nn, $matches[2]));
					break;
					case "presence":
						$retour = $this->getInfosPresence($champ_nt_ou_nn, $matches[2]);
					break;
					default:
						$retour = "Actions possibles: noms-vernaculaires, statuts-protection, presence";
				} 	
			}
		} else {
			// TODO : envoyer message erreur;
		}
		return $retour;
	}

	/**
	 * Toutes les infos sauf noms vernaculaires (requête plus efficace)
	 */
	protected function getInfosEspece($champ_nt_ou_nn, $nt_ou_nn) {
		$req = "SELECT num_nom, num_tax, nom_sci, COUNT(presence) as nb_presence_zones, protection".
				" FROM ".$this->table.
				" WHERE ".$champ_nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($nt_ou_nn)." AND presence = 1";
		
		$resultat = $this->conteneur->getBdd()->recuperer($req);
		$resultat['statuts_protection'] = json_decode($resultat['protection']);
		unset($resultat['protection']);

		return $resultat;
	}

	protected function getInfosPresence($champ_nt_ou_nn, $nt_ou_nn) {
		$req = "SELECT COUNT(presence) as nb_presence_zones".
				" FROM ".$this->table.
				" WHERE ".$champ_nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($nt_ou_nn)." AND presence = 1";

		$resultat = $this->conteneur->getBdd()->recuperer($req);
		return $resultat;
	}

	protected function getStatutsProtection($champ_nt_ou_nn, $nt_ou_nn) {
		$req = "SELECT protection".
				" FROM ".$this->table.
				" WHERE ".$champ_nt_ou_nn." = ".$this->conteneur->getBdd()->proteger($nt_ou_nn)." AND presence = 1";

		$resultat = $this->conteneur->getBdd()->recuperer($req);
		$resultat = json_decode($resultat['protection']);
		return $resultat;
	}

	protected function getNomsVernaculaires($champ_nt_ou_nn, $nt_ou_nn) {
		$noms_vernaculaires = array();
		$req = "SELECT nom_vernaculaire FROM ".$this->tableNomsVernaculaires
			. " WHERE num_tax = ";
		if($champ_nt_ou_nn == "num_nom") {
			$req .= "(SELECT DISTINCT num_tax FROM ".$this->table." WHERE num_nom = ".$this->conteneur->getBdd()->proteger($nt_ou_nn).") ";
		} else {
			$req .= $this->conteneur->getBdd()->proteger($nt_ou_nn);
		}

		$resultat = $this->conteneur->getBdd()->recupererTous($req);

		$resultat_fmt = array();
		foreach($resultat as $nv) {
			$resultat_fmt[] = $nv['nom_vernaculaire'];
		}

		return $resultat_fmt;
	}
}
?>