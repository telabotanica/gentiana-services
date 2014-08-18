<?php
// declare(encoding='UTF-8');
/**
 * Classe créant la réponse HTTP pour les services de DEL.
 *
 * Vérifie qu'aucune erreur n'a été générée. Si une erreur existe, retourne le contenu de l'erreur.
 *
 * @category  DEL
 * @package   Services
 * @package   Bibliotheque
 * @version   0.1
 * @author    Mathias CHOUET <mathias@tela-botanica.org>
 * @author    Jean-Pascal MILCENT <jpm@tela-botanica.org>
 * @author    Aurelien PERONNET <aurelien@tela-botanica.org>
 * @license   GPL v3 <http://www.gnu.org/licenses/gpl.txt>
 * @license   CECILL v2 <http://www.cecill.info/licences/Licence_CeCILL_V2-en.txt>
 * @copyright 1999-2014 Tela Botanica <accueil@tela-botanica.org>
 */
class ReponseHttp {

	private $resultatService = null;
	private $erreurs = array();

	public function __construct() {
		$this->resultatService = new ResultatService();
	}

	public function setResultatService($resultat) {
		if (!($resultat instanceof ResultatService)) {
			$this->resultatService->corps = $resultat;
		} else {
			$this->resultatService = $resultat;
		}
	}

	public function getCorps() {
		if ($this->etreEnErreur()) {
			foreach ($this->erreurs as $erreur) {
				$this->resultatService->corps .= $erreur['message']."\n";
			}
		} else {
			$this->transformerReponseCorpsSuivantMime();
		}
		return $this->resultatService->corps;
	}

	public function ajouterErreur(Exception $e) {
		$this->erreurs[] = array('entete' => $e->getCode(), 'message' => $e->getMessage());
	}

	public function emettreLesEntetes() {
		$enteteHttp = new EnteteHttp();
		if ($this->etreEnErreur()) {
			$enteteHttp->code = $this->erreurs[0]['entete'];
			$enteteHttp->mime = 'text/html';
		} else {
			$enteteHttp->encodage = $this->resultatService->encodage;
			$enteteHttp->mime = $this->resultatService->mime;
		}
		header("Content-Type: $enteteHttp->mime; charset=$enteteHttp->encodage");
		RestServeur::envoyerEnteteStatutHttp($enteteHttp->code);
	}

	private function etreEnErreur() {
		$enErreur = false;
		if (count($this->erreurs) > 0) {
			$enErreur = true;
		}
		return $enErreur;
	}

	private function transformerReponseCorpsSuivantMime() {
		switch ($this->resultatService->mime) {
			case 'application/json' :
				$this->resultatService->corps = json_encode($this->resultatService->corps);
				break;
		}
	}

}