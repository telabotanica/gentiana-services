<?php
/**
 * 
 * regroupement de fonctions de vérification des fichiers de données .tsv qui créé des fichiers log
 * les classes qui vérifie des données pour des projets peuvent l'étendre
 * @author mathilde
 *
 */
abstract class VerificateurDonnees  {

	protected $projet;
	protected $Message;
	protected $Conteneur;

	protected $ligne_num; //numéro de la ligne parcourue
	protected $log = ''; //texte du journal
	protected $colonne_valeur; //valeur d'une colonne
	protected $colonne_num; // numéro d'un colonne
	protected $nb_erreurs = 0; // nombre total 
	protected $erreurs_ligne; // consigne les erreurs d'une ligne :  $erreurs_ligne[$num_col] = $valeur_erronnee

	public function __construct(Conteneur $conteneur, $projet) {
		$this->Conteneur = $conteneur;
		$this->Message = $this->Conteneur->getMessages();
		$this->projet = $projet;
	}

	/**
	 * 
	 * fonction principale qui parcourt chaque ligne du fichier pour en vérifier la cohérence
	 * et déclenche l'écriture du fichier log
	 * @param chaine, nom du fichier de données à vérifier
	 */
	public function verifierFichier($fichierDonnees){
		$lignes = file($fichierDonnees, FILE_IGNORE_NEW_LINES);
		if ($lignes != false) {
			foreach ($lignes as $this->ligne_num => $ligne) {
				$this->verifierErreursLigne($ligne);
				$this->Message->afficherAvancement("Vérification des lignes");
			}
			echo "\n";
		} else {
			$this->Message->traiterErreur("Le fichier $fichierDonnees ne peut pas être ouvert.");
		}
	
		if ($this->nb_erreurs == 0) {
			$this->ajouterAuLog("Il n'y a pas d'erreurs.");
		}
		$this->Message->traiterInfo($this->nb_erreurs." erreurs");
	
		$this->ecrireFichierLog();
		
		return $this->nb_erreurs;
	}

	/**
	 * 
	 * découpe une ligne en colonnes pour en vérifier le contenu
	 * @param chaine, une ligne du fichier
	 */
	protected function verifierErreursLigne($ligne){
		$this->erreurs_ligne = array();
		$colonnes = explode("\t", $ligne);
		if (isset($colonnes)) {
			foreach ($colonnes as $this->colonne_num => $this->colonne_valeur) {
				$this->definirTraitementsColonnes();
			}
		} else {
			$message = "Ligne {$this->ligne_num} : pas de tabulation";
			$this->ajouterAuLog($message);
		}
	
		$this->consignerErreursLigne();
	}

	/**
	*
	* pour le traitement spécifique colonne par colonne
	* 
	*/
	abstract protected function definirTraitementsColonnes();

	/**
	*
	* note dans le log s'il y a des erreurs dans une ligne
	*/
	private function consignerErreursLigne() {
		$nbreErreursLigne = count($this->erreurs_ligne);
		$this->nb_erreurs += $nbreErreursLigne;
		if ($nbreErreursLigne != 0) {
			$this->ajouterAuLog("Erreurs sur la ligne {$this->ligne_num}");
			$ligneLog = '';
			foreach ($this->erreurs_ligne as $cle => $v){
				$ligneLog .= "colonne $cle : $v - ";
			}
			$this->ajouterAuLog($ligneLog);
		}
	}

	/**
	 * garde la trace d'une erreur dans une ligne
	 * 
	 */
	protected function noterErreur() {
		$this->erreurs_ligne[$this->colonne_num] = $this->colonne_valeur;
	}

	private function ajouterAuLog($txt) {
		$this->log .= "$txt\n";
	}

	private function ecrireFichierLog() {
		$base = Config::get('chemin_scripts');
		$fichierLog = $base.'/modules/'.$this->projet.'/log/verification.log';
		file_put_contents($fichierLog, $this->log);
	}
}
?>