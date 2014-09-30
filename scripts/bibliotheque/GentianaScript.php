<?php
/**
 * Doit être implémentée par les classes éxecutant des scripts
 * en ligne de commande pour Gentiana
 *
 * @package		gentiana-scripts
 * @author		Tela Botanica <equipe-dev@tela-botanica.org>
 * @copyright	Copyright (c) 2011-2014, Tela Botanica (accueil@tela-botanica.org)
 * @license		http://www.gnu.org/licenses/gpl.html Licence GNU-GPL-v3
 * @license		http://www.cecill.info/licences/Licence_CeCILL_V2-fr.txt Licence CECILL-v2
 */
abstract class GentianaScript extends Script {

	protected $projetNom = null;
	protected $conteneur;

	public function __construct($script_nom, $parametres_cli) {
		parent::__construct($script_nom, $parametres_cli);
		$this->conteneur = new Conteneur(); // ici car pas géré par le Script du framework
		$this->init();
	}

	/**
	 * Ajustements post-constructeur
	 */
	protected function initialiserProjet() {
	}

	protected function chargerStructureSql() {
		$this->chargerFichierSql('chemins.structureSql');
	}

	protected function chargerFichierSql($param_chemin) {
		$fichierStructureSql = $this->conteneur->getParametre($param_chemin);
		$contenuSql = $this->recupererContenu($fichierStructureSql);
		$this->executerScriptSql($contenuSql);
	}

	protected function executerScriptSql($sql) {
		$requetes = Outils::extraireRequetes($sql);
		foreach ($requetes as $requete) {
			$this->conteneur->getBdd()->requeter($requete);
		}
	}

	protected function recupererContenu($chemin) {
		$contenu = file_get_contents($chemin);
		if ($contenu === false){
			throw new Exception("Impossible d'ouvrir le fichier: $chemin");
		}
		return $contenu;
	}

	/**
	 * Consulte une URL et retourne le résultat (ou déclenche une erreur), en
	 * admettant qu'il soit au format JSON
	 *
	 * @param string $url l'URL du service
	 */
	protected function chargerDonnees($url, $decoderJSON = true) {
		$resultat = $this->conteneur->getRestClient()->consulter($url);
		$entete = $this->conteneur->getRestClient()->getReponseEntetes();

		// Si le service meta-donnees fonctionne correctement, l'entete comprend la clé wrapper_data
		if (isset($entete['wrapper_data'])) {
			if ($decoderJSON) {
				$resultat = json_decode($resultat, true);
				$this->entete = (isset($resultat['entete'])) ? $resultat['entete'] : null;
			}
		} else {
			$m = "L'url <a href=\"$url\">$url</a> lancée via RestClient renvoie une erreur";
			trigger_error($m, E_USER_WARNING);
		}
		return $resultat;
	}
}
?>