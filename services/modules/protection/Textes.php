<?php
/**
 * Web service fournissant une liste de noms de communes de l'Isère
 * Paramètres :
 * - navigation.depart : élément auquel commencer la page (liste) servie
 * - navigation.limite : taille de page
 * - masque : un LIKE sera effectué entre le nom de la commune et ce masque
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
class Textes {

	protected $abreviation;
	protected $conteneur;
	protected $base;
	protected $tableTextes;
	protected $colonneTextesAbreviation;
	protected $nom;

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'textes';
		$this->base = $this->conteneur->getParametre('protection.base');
		$this->tableTextes = $this->conteneur->getParametre('protection.table_textes');
		$this->colonneTextesAbreviation = $this->conteneur->getParametre('protection.table_textes_colonne_abreviation');
	}

	public function consulter($ressources, $parametres) {
		if (count($ressources) != 1) {
			$message = "L'URL doit être de la forme '" . $this->nom . "/abréviation' (ex: '" . $this->nom . "/FR-Reg-82')";
			throw new Exception($message, RestServeur::HTTP_CODE_RESSOURCE_INTROUVABLE);
		}
		$this->abreviation = $ressources[0];
		$infos = $this->infosTexte();

		// encore du tabarouette de code générique qu'a rien à foutre là
		$resultat = new ResultatService();
		$resultat->corps = array('resultat' => $infos);
		return $resultat;
	}

	protected function infosTexte() {
		$abrP = $this->conteneur->getBdd()->proteger($this->abreviation);

		$req = "SELECT *";
		$req .= " FROM " . $this->base . '.' . $this->tableTextes;
		$req .= " WHERE " . $this->colonneTextesAbreviation . " = " . $abrP;

		$resultat = $this->conteneur->getBdd()->recuperer($req);

		return $resultat;
	}
}