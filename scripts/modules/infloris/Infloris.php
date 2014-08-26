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

	protected $tableChorologie;
	protected $tableNomsVernaculaires;
	protected $tailleTranche;
	//protected $parametres_autorises = array();

	public function init() {
		$this->projetNom = 'infloris';
		$this->tableChorologie = $this->conteneur->getParametre('tables.chorologie');
		$this->tableNomsVernaculaires = $this->conteneur->getParametre('tables.noms_vernaculaires');
		$this->tailleTranche = 1000;
	}

	// ce merdier devrait être générique, nom d'un petit bonhomme !
	public function executer() {
		try {
			// Lancement de l'action demandée
			$cmd = $this->getParametre('a');
			switch ($cmd) {
				case 'tout' :
					$this->nettoyage();
					$this->chargerStructure();
					$this->importerCsv();
					$this->rabouterNomsVernaculaires();
					$this->rabouterStatutsProtection();
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
		$this->conteneur->getBdd()->requeter($req);
		$req = "DROP TABLE IF EXISTS `" . $this->tableNomsVernaculaires . "`;";
		$this->conteneur->getBdd()->requeter($req);
	}

	/**
	 * Crée les tables vides
	 */
	protected function chargerStructure() {
		echo "---- création des tables\n";
		$this->chargerStructureSql();
	}

	/**
	 * Importe le fichier CSV Infloris
	 */
	protected function importerCsv() {
		$cheminCsv = $this->conteneur->getParametre('chemins.csvInfloris');
		echo "---- chargement du fichier CSV Infloris [$cheminCsv]\n";
		$req = "LOAD DATA INFILE '" . $cheminCsv . "' INTO TABLE " . $this->tableChorologie
			. " IGNORE 1 LINES;";
		$retour = $this->conteneur->getBdd()->requeter($req);
		return $retour;
	}

	/**
	 * Va chercher les noms vernaculaires pour chaque espèce, et les rajoute
	 * dans la table dédiée
	 */
	protected function rabouterNomsVernaculaires() {
		$squeletteUrlNvjfl = $this->conteneur->getParametre("url_nvjfl");
		echo "---- récupération des noms vernaculaires depuis eFlore\n";
		$depart = 0;
		$yenaencore = true;
		while ($yenaencore) {
			$url = sprintf($squeletteUrlNvjfl, $depart, $this->tailleTranche);
			echo "URL: $url\n";
			$noms = $this->chargerDonnees($url);
			//echo "NOMS: " . print_r($noms, true) . "\n";
			$req = "INSERT INTO " . $this->tableNomsVernaculaires . " VALUES ";
			$valeurs = array();
			echo "Préparation de " . count($noms['resultat']) . " valeurs\n";
			// insertion des données
			foreach ($noms['resultat'] as $res) {
				$nvP = $this->conteneur->getBdd()->proteger($res['nom']);
				$valeurs[] = "(" . $res['num_taxon'] . ", " . $nvP  . ")";
			}
			$req .= implode(",", $valeurs);
			//echo "ReQ : $req\n";
			echo "Insertion de " . count($valeurs) . " valeurs\n";
			$this->conteneur->getBdd()->executer($req);
			// prochain tour
			$depart += $this->tailleTranche;
			$total = $noms['entete']['total'];
			$yenaencore = $depart <= $total;
			echo "insérés: " . min($depart, $total) . "\n";
		}
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