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
	protected static $zonesIsere = array('EU', 'FX', 'Reg-82', 'Dep-38');

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
					$this->rabouterNumTax();
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
				case 'numTax' : // rabouter les numéros taxonomiques
					$this->rabouterNumTax();
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

	/**
	 * Dézingue tout le bousin
	 * @TODO chaque méthode devrait s'autonettoyer au début afin d'être répétable
	 * sans avoir à tout reprendre depuis le début (principe du dump)
	 */
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
		$nbInsertions = 0;
		$yenaencore = true;
		while ($yenaencore) {
			$url = sprintf($squeletteUrlNvjfl, $depart, $this->tailleTranche);
			$noms = $this->chargerDonnees($url);
			// Si quelqu'un parvient à dédoublonner les $valeurs, on enlève le IGNORE
			$req = "INSERT IGNORE INTO " . $this->tableNomsVernaculaires . " VALUES ";
			$valeurs = array();
			// insertion des données
			foreach ($noms['resultat'] as $res) {
				$numTaxons = explode(',', $res['num_taxon']);
				$nvP = $this->conteneur->getBdd()->proteger($res['nom']);
				foreach ($numTaxons as $numTaxon) {
					$valeurs[] = "(" . $numTaxon . ", " . $nvP  . ")";
				}
			}
			$req .= implode(",", $valeurs);
			$this->conteneur->getBdd()->executer($req);
			// prochain tour
			$nbInsertions += count($valeurs); // Faux car INSERT IGNORE - dédoublonner ou compter les insertions réelles
			$depart += $this->tailleTranche;
			$total = $noms['entete']['total'];
			$yenaencore = $depart <= $total;
			echo "insérés: " . min($depart, $total) . " noms, " . $nbInsertions . " attributions\n";
		}
	}

	/**
	 * Va chercher les numéros taxonomiques pour chaque numéro nomenclatural et
	 * les rajoute à la table; on s'en passerait bien mais les noms vernaculaires
	 * de Jean-François Léger (le frère de Claude) sont basés dessus...
	 */
	protected function rabouterNumTax() {
		echo "---- récupération des statuts de protection depuis eFlore\n";
		$req = "SELECT distinct num_nom FROM " . $this->tableChorologie;
		$resultat = $this->conteneur->getBdd()->requeter($req);
		// pour chaque taxon mentionné (inefficace)
		$squeletteUrlNumTax = $this->conteneur->getParametre("url_num_tax");
		foreach ($resultat as $res) {
			$nn = $res['num_nom'];
			//echo "NN: $nn\n";
			if ($nn != 0) {
				$url = sprintf($squeletteUrlNumTax, $nn);
				//echo "URL: $url\n";
				$infosNom = $this->chargerDonnees($url);
				//echo "INFOS: " . print_r($infosNom, true) . "\n";
				if (! empty($infosNom['num_taxonomique'])) {
					$numTax = $infosNom['num_taxonomique'];
					// mise à jour
					$numTaxP = $this->conteneur->getBdd()->proteger($numTax);
					$nnP = $this->conteneur->getBdd()->proteger($nn);
					$reqIns = "UPDATE " . $this->tableChorologie
						. " SET num_tax=$numTaxP WHERE num_nom=$nnP";
					//echo "ReqIns: $reqIns\n";
					$this->conteneur->getBdd()->executer($reqIns);
				}
			}
		}
	}

	/**
	 * Va chercher les statuts de protection pour chaque espèce et les rajoute
	 * à la table; importe un fichier dump SQL des lois
	 */
	protected function rabouterStatutsProtection() {
		echo "---- récupération des statuts de protection depuis eFlore\n";
		// ajout d'une colonne pour la protection
		$req = "ALTER TABLE `" . $this->tableChorologie . "`"
			. " ADD COLUMN protection text DEFAULT NULL";
		$this->conteneur->getBdd()->requeter($req);

		$req = "SELECT distinct num_nom FROM " . $this->tableChorologie;
		$resultat = $this->conteneur->getBdd()->requeter($req);
		// pour chaque taxon mentionné (inefficace mais évite d'implémenter un
		// mode liste sur le service eflore/sptb
		$squeletteUrlSptb = $this->conteneur->getParametre("url_sptb");
		foreach ($resultat as $res) {
			$nn = $res['num_nom'];
			//echo "NN: $nn\n";
			if ($nn != 0) {
				$url = sprintf($squeletteUrlSptb, $nn);
				//echo "URL: $url\n";
				$statuts = $this->chargerDonnees($url);
				//echo "STATUTS: " . print_r($statuts, true) . "\n";
				if (count($statuts) > 0) {
					$json = array();
					foreach ($statuts as $statut) {
						// @TODO tester si ça concerne l'Isère !
						if (in_array($statut['code_zone_application'], self::$zonesIsere)) {
							$nouveauStatut = array();
							$nouveauStatut['zone'] = $statut['code_zone_application'];
							$nouveauStatut['lien'] = $statut['hyperlien_legifrance'];
							$json[] =  $nouveauStatut;
						}
					}
					// Si au moins un des statuts concerne l'Isère
					if (count($json) > 0) {
						$json = json_encode($json);
						//echo "JSON: " . print_r($json, true) . "\n";
						// Insertion d'un bout de JSON
						$jsonP = $this->conteneur->getBdd()->proteger($json);
						$nnP = $this->conteneur->getBdd()->proteger($nn);
						$reqIns = "UPDATE " . $this->tableChorologie
							. " SET protection=$jsonP WHERE num_nom=$nnP";
						//echo "ReqIns: $reqIns\n";
						$this->conteneur->getBdd()->executer($reqIns);
					}
				}
			}
		}
	}
}
?>