<?php
/**
 * Exemple de lancement du script : :
 * php cli.php infloris -a chargerTous
 * 
 * Intégration et traitement des données Infloris (gentiana.org)
 *
 * @package		gentiana-services/Scripts
 * @author		Tela Botanica <equipe-dev@tela-botanica.org>
 * @copyright	Copyright (c) 2014, Tela Botanica (accueil@tela-botanica.org)
 * @license		http://www.cecill.info/licences/Licence_CeCILL_V2-fr.txt Licence CECILL
 * @license		http://www.gnu.org/licenses/gpl.html Licence GNU-GPL
 */
class Infloris extends GentianaScript {

	private $tableChorologie;
	private $tableNomsVernaculaires;
	//protected $parametres_autorises = array();

	public function init() {
		$this->projetNom = 'infloris';
		$this->tableChorologie = $this->conteneur->getParametre('tables.chorologie');
		$this->tableNomsVernaculaires = $this->conteneur->getParametre('tables.noms_vernaculaires');
	}

	// ce merdier devrait être générique, nom d'un petit bonhomme !
	public function executer() {
		try {
			// Lancement de l'action demandée
			$cmd = $this->getParametre('a');
			switch ($cmd) {
				case 'tout' :
					// 
					break;
				case 'nettoyage' : // faire place nette
					$this->nettoyage();
					break;
				case 'chargerStructure' : // créer les tables
					$this->chargerStructure();
					break;
				case 'importerCsv' : // intégrer le fichier CSV
					$this->importerCsv();
					break;
				case 'nomsVernaculaires' : // rabouter les noms vernaculaires
					$this->rabouterNomsVernaculaires();
					break;
				case 'statutsProtection' : // rabouter les statuts de protection
					$this->rabouterStatutsProtection();
					break;
				default :
					throw new Exception("Actions disponibles : tout, nettoyage, chargerStructure, importerCsv, nomsVernaculaires, statutsProtection");
			}
		} catch (Exception $e) {
			$this->traiterErreur($e->getMessage());
		}
	}

	// Dézingue tout le bousin
	protected function nettoyage() {
		echo "---- suppression des tables\n";
		$req = "DROP TABLE IF EXISTS `" . $this->tableChorologie . "`";
		$this->getBdd()->requeter($req);
		$req = "DROP TABLE IF EXISTS `" . $this->tableNomsVernaculaires . "`;";
		$this->getBdd()->requeter($req);
	}

	/**
	 * Crée les tables vides
	 */
	protected function chargerStructure() {
		
	}

	/**
	 * Importe le fichier CSV Infloris
	 */
	protected function importerCsv() {
		$cheminCsv = $this->conteneur->getParametre('chemins.csvInfloris');
		echo "---- chargement du fichier CSV Infloris [$cheminCsv]\n";
		$req = "LOAD DATA INFILE '" . $cheminCsv . "' INTO TABLE " . $this->tableChorologie
			. " FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' IGNORE 1 LINES";
		//$retour = $this->getBdd()->requeter($req);
		return $retour;
	}

	/**
	 * Va chercher les noms vernaculaires pour chaque espèce, et les rajoute
	 * dans la table dédiée
	 */
	protected function rabouterNomsVernaculaires() {
		
	}

	/**
	 * Va chercher les statuts de protection pour chaque espèce et les rajoute
	 * à la table
	 */
	protected function rabouterStatutsProtection() {
		
	}

	// Copie num_nom dans num_nom_retenu lorsque ce dernier est vide
	/*protected function completerNumNomRetenu() {
		echo "---- complétion des num_nom_retenu\n";
		$req = "UPDATE " . $this->table . " SET num_nom_retenu = num_nom WHERE num_nom_retenu='';";
		$this->getBdd()->requeter($req);
	}*/

	/*private function preparerTablePrChpHierarchie() {
		$requete = "SHOW COLUMNS FROM {$this->table} LIKE 'hierarchie' ";
		$resultat = $this->getBdd()->recuperer($requete);
		if ($resultat === false) {
			$requete = 	"ALTER TABLE {$this->table} ".
						'ADD hierarchie VARCHAR(1000) '.
						'CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ';
			$this->getBdd()->requeter($requete);
		}
	}*/
}
?>