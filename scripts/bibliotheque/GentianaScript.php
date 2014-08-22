<?php
// declare(encoding='UTF-8');
/**
 * EfloreScript est une classe abstraite qui doit être implémenté par les classes éxecutant des scripts
 * en ligne de commande pour les projets d'eFlore.
 *
 * @category	PHP 5.2
 * @package		Eflore/Scripts
 * @author		Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @copyright	Copyright (c) 2011, Tela Botanica (accueil@tela-botanica.org)
 * @license		http://www.gnu.org/licenses/gpl.html Licence GNU-GPL-v3
 * @license		http://www.cecill.info/licences/Licence_CeCILL_V2-fr.txt Licence CECILL-v2
 * @since 		0.3
 * @version		$Id$
 * @link		/doc/framework/
 */
abstract class GentianaScript extends Script {

	private $Bdd = null;
	private $projetNom = null;

	public function getProjetNom() {
		return $this->projetNom;
	}

	protected function initialiserProjet($projetNom) {
		$this->projetNom = $projetNom;
		$this->chargerConfigDuProjet();
	}

	protected function getBdd() {
		if (! isset($this->Bdd)) {
			$this->Bdd = new Bdd();
		}
		return $this->Bdd;
	}

	protected function executerScripSql($sql) {
		$requetes = Outils::extraireRequetes($sql);
		foreach ($requetes as $requete) {
			$this->getBdd()->requeter($requete);
		}
	}

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

	public function chargerConfigDuProjet() {
		$scriptChemin = $this->Conteneur->getParametre('scriptChemin');
		$fichierIni = $scriptChemin.$this->projetNom.'.ini';
		if (file_exists($fichierIni)) {
			Config::charger($fichierIni);
		} else {
			$m = "Veuillez configurer le projet en créant le fichier '{$this->projetNom}.ini' ".
			"dans le dossier du module de script du projet à partir du fichier '{$this->projetNom}.defaut.ini'.";
			throw new Exception($m);
		}
	}

	//changée
	public function chargerStructureSql() {
		$this->chargerFichierSql('chemins.structureSql');
	}

	public function chargerFichierSql($param_chemin) {
		$fichierStructureSql = $this->Conteneur->getParametre($param_chemin);
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
			throw new Exception("Impossible d'ouvrir le fichier SQL : $chemin");
		}
		return $contenu;
	}
}
?>