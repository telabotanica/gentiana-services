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

	protected function getBdd() {
		return $this->conteneur->getBdd();
	}

	/*protected function executerScripSql($sql) {
		$requetes = Outils::extraireRequetes($sql);
		foreach ($requetes as $requete) {
			$this->getBdd()->requeter($requete);
		}
	}

	// wtf ?
	protected function stopperLaBoucle($limite = false) {
		$stop = false;
		if ($limite) {
			static $ligneActuelle = 1;
			if ($limite == $ligneActuelle++) {
				$stop = true;
			}
		}
		return $stop;
	}

	public function chargerStructureSql() {
		$this->chargerFichierSql('chemins.structureSql');
	}

	public function chargerFichierSql($param_chemin) {
		$fichierStructureSql = $this->conteneur->getParametre($param_chemin);
		$contenuSql = $this->recupererContenu($fichierStructureSql);
		$this->executerScriptSql($contenuSql);
	}

	public function executerScriptSql($sql) {
		$requetes = Outils::extraireRequetes($sql);
		foreach ($requetes as $requete) {
			$this->Bdd->requeter($requete);
		}
	}

	public function recupererContenu($chemin) {
		$contenu = file_get_contents($chemin);
		if ($contenu === false){
			throw new Exception("Impossible d'ouvrir le fichier: $chemin");
		}
		return $contenu;
	}*/
}
?>