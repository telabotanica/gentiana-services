<?php
/**
 * Web service fournissant une liste de taxons présents en Isère
 * Paramètres :
 * - navigation.depart : élément auquel commencer la page (liste) servie
 * - navigation.limite : taille de page
 * - masque.(nom|zone-geo) : un LIKE sera effectué entre le champ et le masque
 * - masque.proteges : si '0' retourne les protections NULL, si '1' les NOT NULL
 * 
 * @TODO ça devrait s'appeler "noms" et pas "taxons"
 *
 * @category   Gentiana
 * @package    Services
 * @subpackage Protection
 * @version    0.1
 * @author     Mathias CHOUET <mathias@tela-botanica.org>
 * @author     Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @author     Aurelien PERONNET <aurelien@tela-botanica.org>
 * @license    GPL v3 <http://www.gnu.org/licenses/gpl.txt>
 * @license    CECILL v2 <http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt>
 * @copyright  1999-2014 Tela Botanica <accueil@tela-botanica.org>
 */
class Statuts {

	protected $abreviation;
	protected $conteneur;
	protected $base;
	protected $tableStatuts;
	protected $colonneStatutsAbreviation;
	protected $nom;

	public function __construct(Conteneur $conteneur = null) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->nom = 'statuts';
		$this->base = $this->conteneur->getParametre('protection.base');
		$this->tableStatuts = $this->conteneur->getParametre('protection.table_statuts');
		$this->colonneStatutsAbreviation = $this->conteneur->getParametre('protection.table_statuts_colonne_abreviation');
	}

	public function consulter($ressources, $parametres) {
		if (count($ressources) != 1) {
			$message = "L'URL doit être de la forme '" . $this->nom . "/abréviation' (ex: '" . $this->nom . "/Dep-cueil')";
			throw new Exception($message, RestServeur::HTTP_CODE_RESSOURCE_INTROUVABLE);
		}
		$this->abreviation = $ressources[0];
		$infos = $this->infosStatut();

		// encore du tabarouette de code générique qu'a rien à foutre là
		$resultat = new ResultatService();
		$resultat->corps = array('resultat' => $infos);
		return $resultat;
	}

	protected function infosStatut() {
		$abrP = $this->conteneur->getBdd()->proteger($this->abreviation);

		$req = "SELECT *";
		$req .= " FROM " . $this->base . '.' . $this->tableStatuts;
		$req .= " WHERE " . $this->colonneStatutsAbreviation . " = " . $abrP;

		$resultat = $this->conteneur->getBdd()->recuperer($req);

		$infos = array(
			'abreviation' => $this->abreviation,
			'intitule' => $resultat['epts_intitule'],
			'description' => $resultat['epts_description']
		);

		return $infos;
	}
}