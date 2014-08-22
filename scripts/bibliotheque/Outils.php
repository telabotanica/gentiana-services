<?php
class Outils {

	public static function recupererTableauConfig($parametres) {
		$tableau = array();
		$tableauPartiel = explode(',', $parametres);
		$tableauPartiel = array_map('trim', $tableauPartiel);
		foreach ($tableauPartiel as $champ) {
			if (strpos($champ, '=') === false) {
				$tableau[] = $champ;
			} else {
				list($cle, $val) = explode('=', $champ);
				$clePropre = trim($cle);
				$valeurPropre = trim($val);
				$tableau[$clePropre] = $valeurPropre;
			}
		}
		return $tableau;
	}

	public static function extraireRequetes($contenuSql) {
		$requetesExtraites = preg_split("/;\e*\t*\r*\n/", $contenuSql);
		if (count($requetesExtraites) == 0){
			throw new Exception("Aucune requête n'a été trouvée dans le fichier SQL : $cheminFichierSql");
		}

		$requetes = array();
		foreach ($requetesExtraites as $requete) {
			if (trim($requete) != '') {
				$requetes[] = rtrim(trim($requete), ';');
			}
		}
		return $requetes;
	}

	/**
	* Utiliser cette méthode dans une boucle pour afficher un message suivi du nombre de tour de boucle effectué.
	* Vous devrez vous même gérer le retour à la ligne à la sortie de la boucle.
	*
	* @param string le message d'information.
	* @param int le nombre de départ à afficher.
	* @return void le message est affiché dans la console.
	*/
	public static function afficherAvancement($message, $depart = 0) {
		static $avancement = array();
		if (! array_key_exists($message, $avancement)) {
			$avancement[$message] = $depart;
			echo "$message : ";

			$actuel =& $avancement[$message];
			echo $actuel++;
		} else {
			$actuel =& $avancement[$message];

			// Cas du passage de 99 (= 2 caractères) à 100 (= 3 caractères)
			$passage = 0;
			if (strlen((string) ($actuel - 1)) < strlen((string) ($actuel))) {
				$passage = 1;
			}

			echo str_repeat(chr(8), (strlen((string) $actuel) - $passage));
			echo $actuel++;
		}
	}

	/**
	 * @link http://gist.github.com/385876
	 */
	public function transformerTxtTsvEnTableau($file = '', $delimiter = "\t") {
		$str = file_get_contents($file);
		$lines = explode("\n", $str);
		$field_names = explode($delimiter, array_shift($lines));
		foreach ($lines as $line) {
			// Skip the empty line
			if (empty($line)) continue;
			$fields = explode($delimiter, $line);
			$_res = array();
			foreach ($field_names as $key => $f) {
				$_res[$f] = isset($fields[$key]) ? $fields[$key] : '';
			}
			$res[] = $_res;
		}
		return $res;
	}
}
?>