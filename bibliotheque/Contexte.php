<?php
// declare(encoding='UTF-8');
/**
 * Contexte permet d'encapsuler les super globales et de définir le contexte du web service courant.
 * Avec le temps vient le goût du contexte
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
class Contexte {

	private $conteneur;
	private $get;
	private $getBrut;
	private $post;
	private $session;
	private $cookie;
	private $server;
	private $urlRessource;

	private $mapping = array('getPhp' => 'get',
		'getQS' => 'getBrut',
		'getPost' => 'post',
		'getServer' => 'server',
		'getSession' => 'session',
		'getCookie' => 'cookie',
		'getRessource' => 'urlRessource',
		'setCookie' => 'cookie');

	public function __construct($conteneur, &$server, &$get, &$post, &$session, &$cookie) {
		$this->conteneur = $conteneur == null ? new Conteneur() : $conteneur;
		$this->server = $server;
		$this->get = $this->nettoyerParametres($get);
		$this->getBrut = $this->recupererParametresBruts();
		$this->post = $post;
		$this->session = $session;
		$this->cookie = $cookie;
		$this->urlRessource = $this->decouperUrlChemin();
	}

	public function __call($nom, $arguments) {
		if (!isset($this->mapping[$nom])) {
			$msg = "La méthode $nom n'existe pas dans l'objet {get_class()}";
			throw new Exception($msg, RestServeur::HTTP_CODE_ERREUR);
		}
		$attributNom = $this->mapping[$nom];
		$data = $this->$attributNom;

		if (substr($nom, 0, 3) == 'get') {
			$cle = isset($arguments[0]) ? $arguments[0] : null;
			return $this->getGenerique($data, $cle);
		} else if (substr($nom, 0, 3) == 'set') {
			$cle = isset($arguments[0]) ? $arguments[0] : null;
			$valeur = isset($arguments[1]) ? $arguments[1] : null;
			return $this->setGenerique($data, $cle, $valeur);
		}
	}

	private function getGenerique($data, $cle) {
		$retour = null;
		if ($cle === null) {
			$retour = $data;
		} else if (isset($data[$cle])) {
			$retour = $data[$cle];
		}
		return $retour;
	}

	private function setGenerique($data, $cle, $valeur) {
		if ($valeur === null) {
			unset($data[$cle]);
		} else {
			$data[$cle] = $valeur;
		}
	}

	private function nettoyerParametres(Array $parametres) {
		// Pas besoin d'utiliser urldecode car déjà fait par php pour les clés et valeur de $_GET
		if (isset($parametres) && count($parametres) > 0) {
			foreach ($parametres as $cle => $valeur) {
				// les quotes, guillements et points-virgules ont été retirés des caractères à vérifier car
				//ça n'a plus lieu d'être maintenant que l'on utilise protéger à peu près partout
				$verifier = array('NULL', "\\", "\x00", "\x1a");
				$parametres[$cle] = strip_tags(str_replace($verifier, '', $valeur));
			}
		}
		return $parametres;
	}

	private function recupererParametresBruts() {
		$parametres_bruts = array();
		if (isset($this->server['QUERY_STRING']) && !empty($this->server['QUERY_STRING'])) {
			$paires = explode('&', $this->server['QUERY_STRING']);
			foreach ($paires as $paire) {
				$nv = explode('=', $paire);
				$nom = urldecode($nv[0]);
				$valeur = urldecode($nv[1]);
				$parametres_bruts[$nom] = $valeur;
			}
			$parametres_bruts = $this->nettoyerParametres($parametres_bruts);
		}
		return $parametres_bruts;
	}

	private function decouperUrlChemin() {
		if (isset($this->server['REDIRECT_URL']) && $this->server['REDIRECT_URL'] != '') {
			if (isset($this->server['REDIRECT_QUERY_STRING']) && !empty($this->server['REDIRECT_QUERY_STRING'])) {
				$url = $this->server['REDIRECT_URL'].'?'.$this->server['REDIRECT_QUERY_STRING'];
			} else {
				$url = $this->server['REDIRECT_URL'];
			}
		} else {
			$url = $this->server['REQUEST_URI'];
		}

		$tailleQueryString = strlen($this->server['QUERY_STRING']);
		$tailleURL = ($tailleQueryString == 0) ?  strlen($url) : -($tailleQueryString + 1);

		$urlChaine = '';
		if (strpos($url, $this->conteneur->getParametre('serveur.baseURL')) !== false) {
			$urlChaine = substr($url, strlen($this->conteneur->getParametre('serveur.baseURL')), $tailleURL);
		} else if (strpos($url, $this->conteneur->getParametre('serveur.baseAlternativeURL')) !== false) {
			$urlChaine = substr($url, strlen($this->conteneur->getParametre('serveur.baseAlternativeURL')), $tailleURL);
		}
		return explode('/', $urlChaine);
	}
}