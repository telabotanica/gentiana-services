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

	private $table = null;
	/*private $pasInsertion = 1000;
	private $departInsertion = 0;*/

	protected $parametres_autorises = array();

	public function initialiserProjet($projetNom) {
		parent::initialiserProjet($projetNom);
		$this->table = Config::get("apd");
		$this->tableMeta = Config::get("apdMeta");
	}

	public function executer() {
		try {
			$this->initialiserProjet('infloris');

			// Lancement de l'action demandée
			$cmd = $this->getParametre('a');
			switch ($cmd) {
				case 'tout' :
					$ok = $this->productionCsvPourReferentiels();
					if ($ok === true) {
						$this->integrationEFlore();
					}
					break;
				case 'ref' : // partie 1 : "referentiels"
					$this->productionCsvPourReferentiels();
					break;
				case 'eflore' : // partie 2 : "eFlore"
					$this->integrationEFlore();
					break;
				case 'nettoyage' :
					$this->nettoyage();
					break;
				case 'chargerStructureSql' :
					//$this->creerStructure();
					$this->chargerStructureSql();
					break;
				case 'verifierEtGenererCsvRtax' :
					$this->verifierEtGenererCsvRtax();
					break;
				case 'chargerCsvRtax' :
					$this->chargerCsvRtax();
					break;
				default :
					throw new Exception("Erreur : la commande '$cmd' n'existe pas!");
			}
		} catch (Exception $e) {
			$this->traiterErreur($e->getMessage());
		}
	}

	// Lance la première moitié du boulot, et s'arrête lorsque le fichier CSV
	// au format Rtax est rempli avec les données amendées - il est prêt à rentrer dans Rtxß.
	// Retourne true si tout s'est bien passé, false sinon
	protected function productionCsvPourReferentiels() {
		$retour = false;
		$this->nettoyage();
		$this->chargerStructureSql();
		$verifOk = $this->verifierEtGenererCsvRtax();
		if ($verifOk === true) {
			$chgtOk = $this->chargerCsvRtax();
			if ($chgtOk) {
				$this->changerRangs();
				$this->completerNumNomRetenu();
				$this->supprimerNumTaxSupPourSynonymes();
				$this->subspAutonymes();
				$this->genererNomSupraGenerique();
				$this->genererEpitheteInfraGenerique();
				$this->exporterCSVModifie();
				$retour = true;
			}
		}
		return $retour;
	}

	// Lance la seconde moitié du boulot, et s'arrête lorsque le référentiel
	// est inséré dans la base eFlore.
	// Retourne true si tout s'est bien passé, false sinon
	protected function integrationEFlore() {
		$retour = false;
		$this->genererChpNumTax();
		$this->genererChpNomSciHtml();
		$this->genererChpFamille();
		$this->genererChpNomComplet();
		$this->genererChpHierarchie();
		$retour = true;
		return $retour;
	}

	// -------------- partie Rtax -------------

	// Dézingue tout le bousin
	protected function nettoyage() {
		echo "---- suppression des tables\n";
		$req = "DROP TABLE IF EXISTS `" . $this->table . "`";
		$this->getBdd()->requeter($req);
		$req = "DROP TABLE IF EXISTS `" . $this->tableMeta . "`;";
		$this->getBdd()->requeter($req);
	}

	// Analyse le fichier CSV fourni par le CJBG, le vérifie et écrit un CSV minimal au format Rtax
	function verifierEtGenererCsvRtax() {
		$cheminCsvRtax = Config::get('chemins.csvRtax');
		$cheminCsvCjbg = Config::get('chemins.csvCjbg');
		$retour = false;
		echo "---- vérification CSV CJBG [$cheminCsvCjbg] et génération CSV Rtax\n";

		// Correspondances de colonnes pour le remplissage à minima du fichier CSV Rtax
		// Clefs: CJBG
		// Valeurs: Rtax
		$entetesCjbgVersRtax = array(
		    "id_name" => "num_nom",
		    "presence" => "presence",
		    "statut_introduction" => "statut_introduction",
		    "statut_origine" => "statut_origine",
		    "nom_addendum" => "nom_addendum",
		    "BASIONYME" => "num_basyonyme",
		    "NO_RANG" => "rang",
		    "auteur" => "auteur",
		    "ANNEE" => "annee",
		    "type_epithete" => "type_epithete",
		    "SYN_mal_applique" => "synonyme_mal_applique",
		    "nom_sci" => "nom_sci",
		    "num_tax_sup" => "num_tax_sup",
		    "num_nom_retenu" => "num_nom_retenu",
		    "genre" => "genre",
		    "NOTES" => "notes",
		    "epithete_sp" => "epithete_sp",
		    "epithete_infra_sp" => "epithete_infra_sp",
			// champs additionnels
		    "NOM_STANDARD2" => false,
		    "STATUT_SYN" => false, // @TODO convertir
		    "hybride_parents" => false, // toujours "x" => ??
		    "FAM APG3" => false,
		    "auth_genre" => false,
		    "auth_esp" => false
		);

		$analyseOK = true;
		$numLigne = 1;
		$idNames = array();
		// lecture CSV d'origine
		$csv = fopen($cheminCsvCjbg, "r");
		$donneesTransformees = array();
		if ($csv) {
			$entetes = fgetcsv($csv);
			//echo "Entetes: " . print_r($entetes, true) . "\n";
			while(($ligne = fgetcsv($csv)) !== false) {
				$numLigne++;
				$nouvelleLigne = array();
				if (isset($idNames[$ligne[0]])) {
					echo "Entrée dupliquée pour id_name [" . $ligne[0] . "]\n";
					$analyseOK = false;
				} else if (! is_numeric($ligne[0])) {
					echo "Ligne $numLigne : la clef [" . $ligne[0] . "] n'est pas un entier\n";
					$analyseOK = false;
				} else if ($ligne[0] == 0) {
					echo "Ligne $numLigne : la clef [" . $ligne[0] . "] vaut zéro\n";
					$analyseOK = false;
				} else {
					$idNames[$ligne[0]] = $ligne[13]; // stockage du nom retenu
					foreach ($ligne as $idx => $col) {
						$entete = $entetes[$idx];
						$ert = $entetesCjbgVersRtax[$entete];
						if (strpos($col, "\n") > -1) {
							echo "Info: la colonne $ert de la ligne $numLigne contient des retours chariot. Conversion en espaces.\n";
							$col = str_replace("\n", " ", $col);
						}
						$nouvelleLigne[$ert] = $col;
					}
					$donneesTransformees[] = $nouvelleLigne;
				}
			}
		} else {
			echo "Erreur lors de l'ouverture du fichier\n";
		}

		// Vérifications:
		// - existence des num_nom_retenu et num_tax_sup mentionnés
		// - réduction des chaînes de synonymie
		$nnrManquants = array();
		$ntsManquants = array();
		$chaineSyn = array();
		foreach ($donneesTransformees as $ligne) {
			$taxSup = $ligne['num_tax_sup'];
			$nomRet = $ligne['num_nom_retenu'];
			$numNom = $ligne['num_nom'];
			// Si un nom est retenu, son taxon supérieur doit être mentionné et exister
			if (($numNom == $nomRet) && $taxSup && (! isset($idNames[$taxSup])) && (! isset($ntsManquants[$taxSup]))) {
				$ntsManquants[$taxSup] = true;
			}
			// Si un nom retenu est mentionné, il doit exister et être un nom retenu
			if ($nomRet) {
				if (isset($idNames[$nomRet])) {
					/*$nrnr = $idNames[$nomRet];
					echo "Test pour nn $numNom, nr $nomRet, " . $nrnr . "\n";
					if ($nomRet && $nrnr != $nomRet) {
						if (! isset($chaineSyn[$nomRet])) {
							$chaineSyn[$nomRet] = true;
						}
					}*/
				} else {
					if (! isset($nnrManquants[$nomRet])) {
						$nnrManquants[$nomRet] = true;
					}
				}
			}
		}
		if (count($nnrManquants) > 0) {
			echo count($nnrManquants) . " Nom(s) retenu(s) absent(s):\n";
			echo "(" . implode(",", array_keys($nnrManquants)) . ")\n";
		}
		if (count($ntsManquants) > 0) {
			echo count($ntsManquants) . " Taxon(s) supérieur(s) absent(s):\n";
			echo "(" . implode(",", array_keys($ntsManquants)) . ")\n";
		}
		/*if (count($chaineSyn) > 0) {
			echo count($chaineSyn) . " Synonymes ne sont pas des noms retenus:\n";
			//echo "(" . implode(",", array_keys($chaineSyn)) . ")\n";
		}*/

		if ($analyseOK === true) {
			// Production CSV de destination
			$csvDestination = '';
			$csvDestination .= implode($this->entetesRtax, ',') . "\n";
			$tailleLigne = count($this->entetesRtax);
			foreach ($donneesTransformees as $dt) {
				//$ligne = array();
				$ligneCsv = '';
				$i = 0;
				foreach ($this->entetesRtax as $e) {
					/*if (isset($dt[$e])) {
						$ligne[] = $dt[$e];
					} else {
						$ligne[] = '';
					}*/
					if (isset($dt[$e]) && ($dt[$e] !== '')) {
						$ligneCsv .= '"' . $dt[$e] . '"';
					}
					if ($i < $tailleLigne) {
						$ligneCsv .= ',';
					}
					$i++;
				}
				$ligneCsv .= "\n";
				//$ligneCsv = '"' . implode($ligne, '","') . '"' . "\n"; // met des double guillemets sur les champs vides et /i
				$csvDestination .= $ligneCsv;
			}
			// @TODO créer le répertoire dans /tmp et donner les droits 777
			file_put_contents($cheminCsvRtax, $csvDestination);
			$retour = true;
		} else {
			echo "L'analyse a mis en évidence des erreurs. Interruption.\n";
		}

		return $retour;
	}

	// Charge le CSV minimal au format TexRaf
	protected function chargerCsvRtax() {
		$cheminCsvRtax = Config::get('chemins.csvRtax');
		echo "---- chargement du fichier CSV Rtax [$cheminCsvRtax]\n";
		$req = "LOAD DATA INFILE '" . $cheminCsvRtax . "' INTO TABLE " . $this->table
			. " FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n' IGNORE 1 LINES";
		$retour = $this->getBdd()->requeter($req);
		return $retour;
	}

	// Convertit les rangs du format chaispasquoi au format RexTaf
	protected function changerRangs() {
		echo "---- conversion des rangs\n";
		$rangs = array(
			"0" => "10",
			"1" => "20",
			"2" => "50",
			"3" => "53",
			"4" => "80",
			"5" => "140",
			"6" => "180",
			"7" => "190",
			"8" => "200",
			"9" => "220",
			"10" => "230",
			"11" => "240",
			"12" => "250",
			"13" => "260",
			"14" => "280",
			"15" => "290",
			"16" => "320",
			"17" => "340",
			"18" => "350",
			"19" => "360",
			"20" => "370",
			"26" => "440"
		);
		foreach ($rangs as $src => $dest) {
			echo "rang $src => rang $dest\n";
			$req = "UPDATE " . $this->table . " SET rang=$dest WHERE rang=$src;";
			$this->getBdd()->requeter($req);
		}
	}

	// Copie num_nom dans num_nom_retenu lorsque ce dernier est vide
	protected function completerNumNomRetenu() {
		echo "---- complétion des num_nom_retenu\n";
		$req = "UPDATE " . $this->table . " SET num_nom_retenu = num_nom WHERE num_nom_retenu='';";
		$this->getBdd()->requeter($req);
	}

	private function preparerTablePrChpHierarchie() {
		$requete = "SHOW COLUMNS FROM {$this->table} LIKE 'hierarchie' ";
		$resultat = $this->getBdd()->recuperer($requete);
		if ($resultat === false) {
			$requete = 	"ALTER TABLE {$this->table} ".
						'ADD hierarchie VARCHAR(1000) '.
						'CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ';
			$this->getBdd()->requeter($requete);
		}
	}
}
?>