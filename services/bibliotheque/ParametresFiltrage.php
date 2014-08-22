<?php
// declare(encoding='UTF-8');
/**
 * Classe contenant des méthodes de filtrage/formatage des paramètres de recherche passés dans l'URL.
 *
 * Cette classe filtre et formate les parametres passées dans l'URL et construit un tableau associatif contenant
 * le résultat des filtrages/formatages et les infos nécessaires à la construction d'une requête SQL.
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
class ParametresFiltrage {

	const LISTE_OBS_MAX_RESULT_LIMIT = 1000;
	const LISTE_OBS_MAX_ID_OBS = 10e7;
	const LISTE_OBS_MAX_BDTFX_NT = 1000000; // SELECT MAX(num_taxonomique) FROM bdtfx_v2_00; // 44378 + 1000
	const LISTE_OBS_MAX_BDTFX_NN = 1000000; // SELECT MAX(num_nom) FROM bdtfx_v2_00;// 120816 + 10000

	private $conteneur;
	private $contexte;
	private $parametres = array();
	private $parametresFiltres = array();

	public function __construct($conteneur) {
		$this->conteneur = $conteneur;
		$this->contexte = $this->conteneur->getContexte();
		$this->parametres = $this->contexte->getQS();
	}

	public function filtrerUrlParamsAppliImg() {
		$this->maintenirCompatibilitesParametres();

		$parametresAutorises = $this->conteneur->getParametreTableau('images.masques_possibles');
		$this->eliminerParametresInconnus($parametresAutorises);

		$this->repartirMasqueGeneral();

		$paramsParDefaut = $this->conteneur->getParametreTableau('images.parametres_valeurs_defaut');
		$this->definirParametresDefauts($paramsParDefaut);

		$this->filtrerUrlParamsGeneraux();

		$trisPossibles = $this->conteneur->getParametreTableau('appli_img.tris_possibles');
		$this->detruireParametreInvalide('tri', $trisPossibles);
		$formatsImgPossibles = $this->conteneur->getParametreTableau('appli_img.img_formats_possibles');
		$this->detruireParametreInvalide('format', $formatsImgPossibles);
		$this->filtrerProtocole();

		$this->supprimerParametresFiltresInvalides();
		return $this->parametresFiltres;
	}

	public function filtrerUrlParamsAppliObs() {
		$this->maintenirCompatibilitesParametres();

		$parametresAutorises = $this->conteneur->getParametreTableau(('observations.masques_possibles'));
		$this->eliminerParametresInconnus($parametresAutorises);

		$this->repartirMasqueGeneral();

		$paramsParDefaut = $this->conteneur->getParametreTableau('observations.parametres_valeurs_defaut');
		$this->definirParametresDefauts($paramsParDefaut);

		$this->filtrerUrlParamsGeneraux();

		$trisPossibles = $this->conteneur->getParametreTableau('appli_obs.tris_possibles');
		$this->detruireParametreInvalide('tri', $trisPossibles);

		$this->supprimerParametresFiltresInvalides();
		return $this->parametresFiltres;
	}

	private function maintenirCompatibilitesParametres() {
		$this->renommerParametres();

		if (!isset($this->parametres['masque.tag_del']) && isset($this->parametres['masque.tag'])) {
			$this->parametres['masque.tag_del'] = $this->parametres['masque.tag'];
		}
	}

	private function renommerParametres() {
		$renomages = array('masque.tag_pictoflora' => 'masque.tag_del');
		foreach ($renomages as $ancienNom => $nouveauNom) {
			if (isset($this->parametres[$ancienNom])) {
				$this->parametres[$nouveauNom] = $this->parametres[$ancienNom];
				unset($this->parametres[$ancienNom]);
			}
		}
	}

	/**
	 * Suppression de toutes les clefs NON présentes dans le paramètre de config : images|observations.masques_possibles
	 * @param array $parametresAutorises tableau des paramètres pouvant être utilisé dans l'url.
	 */
	private function eliminerParametresInconnus(Array $parametresAutorises = null) {
		if ($parametresAutorises) {
			$this->parametres = array_intersect_key($this->parametres, array_flip($parametresAutorises));
		}
	}

	/**
	 * Les paramètres par défaut sont écrasés par ceux passés dans l'url.
	 *
	 * @param array $paramsParDefaut tableau associatif des paramètres d'url par défaut
	 */
	private function definirParametresDefauts(Array $paramsParDefaut) {
		$this->parametres = array_merge($paramsParDefaut, $this->parametres);
	}

	/**
	 * "masque" ne fait jamais que faire une requête sur la plupart des champs, (presque) tous traités
	 * de manière identique à la seule différence que:
	 * 1) ils sont combinés par des "OU" logiques plutôt que des "ET".
	 * 2) les tags sont traités différemment pour conserver la compatibilité avec l'utilisation historique:
	 * Tous les mots-clefs doivent matcher et sont séparés par des espaces.
	 */
	private function repartirMasqueGeneral() {
		if (isset($this->parametres['masque']) && !empty(trim($this->parametres['masque']))) {
			$masqueGeneral = trim($this->parametres['masque']);
			$masquesDetailCles = array('masque.auteur', 'masque.departement', 'masque.commune', 'masque.id_zone_geo',
				'masque.ns', 'masque.famille', 'masque.date', 'masque.genre', 'masque.milieu');

			// Suppression de la génération de SQL du masque général sur les champ spécifiques qui sont traités avec leur valeur propre.
			foreach ($masquesDetailCles as $cle) {
				if (isset($this->parametres[$cle]) === false) {
					$this->parametres[$cle] = $masqueGeneral;
					$this->parametresFiltres['_parametres_condition_or_'][] = $cle;
				}
			}
		}
	}

	/**
	 * Filtre et valide les paramètres reconnus. Effectue *toute* la sanitization *sauf* l'escape-string
	 * Cette fonction est appelée:
	 * - une fois sur les champs de recherche avancées
	 * - une fois sur le masque général si celui-ci à été spécifié. Dans ce cas,
	 * la chaîne générale saisie est utilisée comme valeur pour chacun des champs particuliers
	 * avec les traitements particuliers qui s'imposent
	 * Par exemple: si l'on cherche "Languedoc", cela impliquera:
	 * WHERE (nom_sel like "Languedoc" OR nom_ret ... OR ...) mais pas masque.date ou masque.departement
	 * qui s'assure d'un pattern particulier
	 *
	 * masque.genre est un alias pour masque.ns (nom_sel), mais permet de rajouter une clause supplémentaire
	 * sur nom_sel. Précédemment: WHERE nom_sel LIKE '%<masque.genre>% %'.
	 * Désormais masque.genre doit être intégralement spécifié, les caractères '%' et '_' seront interprétés.
	 * Attention toutefois car la table del_observation intègre des nom_sel contenant '_'
	 */
	// TODO: ajouter un filtre sur le masque (général)
	private function filtrerUrlParamsGeneraux() {
		$this->detruireParametreInvalide('ordre', $this->conteneur->getParametreTableau('valeurs_ordre'));
		$this->detruireParametreInvalide('masque.referentiel', $this->conteneur->getParametreTableau('valeurs_referentiel'));

		$this->filtrerNavigationLimite();
		$this->filtrerNavigationDepart();
		$this->filtrerDepartement();
		$this->filtrerDate();
		$this->filtrerNn();
		$this->filtrerNt();

		$parametresATrimer = array('masque', 'masque.ns', 'masque.genre', 'masque.espece', 'masque.auteur', 'masque.milieu');
		$this->supprimerCaracteresInvisibles($parametresATrimer);

		$this->filtrerFamille();
		$this->filtrerIdZoneGeo();
		$this->filtrerCommune();
		$this->filtrerType();

		$this->filtrerTag();
		$this->filtrerTagCel();
		$this->filtrerTagDel();
	}


	/**
	 * Supprime l'index du tableau des paramètres si sa valeur ne correspond pas
	 * au spectre passé par $values.
	 */
	private function detruireParametreInvalide($index, Array $valeursAutorisees) {
		if (array_key_exists($index, $this->parametres)) {
			if (!in_array($this->parametres[$index], $valeursAutorisees)) {
				unset($this->parametres[$index]);
			} else {
				$this->parametresFiltres[$index] = $this->parametres[$index];
			}
		}
	}

	private function filtrerNavigationLimite() {
		if (isset($this->parametres['navigation.limite'])) {
			$options = array(
				'options' => array(
					'default' => null,
					'min_range' => 1,
					'max_range' => self::LISTE_OBS_MAX_RESULT_LIMIT));
			$paramFiltre = filter_var($this->parametres['navigation.limite'], FILTER_VALIDATE_INT, $options);
			$this->parametresFiltres['navigation.limite'] = $paramFiltre;
		}
	}

	private function filtrerNavigationDepart() {
		if (isset($this->parametres['navigation.depart'])) {
			$options = array(
				'options' => array(
					'default' => null,
					'min_range' => 0,
					'max_range' => self::LISTE_OBS_MAX_ID_OBS));
			$paramFiltre = filter_var($this->parametres['navigation.depart'], FILTER_VALIDATE_INT, $options);
			$this->parametresFiltres['navigation.depart'] = $paramFiltre;
		}
	}

	/**
	 * STRING: 0 -> 95, 971 -> 976, 2A + 2B (./services/configurations/config_departements_bruts.ini)
	 * accept leading 0 ?
	 * TODO; filter patterns like 555.
	 *
	 * @return type
	 */
	private function filtrerDepartement() {
		if (isset($this->parametres['masque.departement'])) {
			$dept = $this->parametres['masque.departement'];
			$paramFiltre = null;
			if (preg_match('/^(\d{2}|\d{3}|2a|2b)$/i', $dept) != 0) {
				$paramFiltre = is_numeric($dept) ? str_pad($dept, 5, '_') : $dept;
			} else {
				$dept_translit = iconv('UTF-8', 'ASCII//TRANSLIT', $dept);
				$dpt_chaine = strtolower(str_replace(' ', '-', $dept_translit));
				$this->conteneur->chargerConfiguration('config_departements_bruts.ini');
				$dpt_numero = $this->conteneur->getParametre($dpt_chaine);
				if (!empty($dpt_numero)) {
					$paramFiltre = str_pad($dpt_numero, 5, '_');
				}
			}
			$this->parametresFiltres['masque.departement'] = $paramFiltre;
		}
	}

	private function filtrerDate() {
		if (isset($this->parametres['masque.date'])) {
			$date = $this->parametres['masque.date'];
			// une année, TODO: masque.annee
			$paramFiltre = null;
			if (is_numeric($date)) {
				$paramFiltre = $date;
			} elseif(strpos($date, '/' !== false) && ($x = strtotime(str_replace('/', '-', $date)))) {
				$paramFiltre = $x;
			} elseif(strpos($date, '-' !== false) && ($x = strtotime($date)) ) {
				$paramFiltre = $x;
			}
			$this->parametresFiltres['masque.date'] = $paramFiltre;
		}
	}

	private function filtrerNn() {
		if (isset($this->parametres['masque.nn'])) {
			$options = array(
				'options' => array(
					'default' => null,
					'min_range' => 0,
					'max_range' => self::LISTE_OBS_MAX_BDTFX_NN));
			$paramFiltre = filter_var($this->parametres['masque.nn'], FILTER_VALIDATE_INT, $options);
			$this->parametresFiltres['masque.nn'] = $paramFiltre;
		}
	}

	private function filtrerNt() {
		if (isset($this->parametres['masque.nt'])) {
			$options = array(
				'options' => array(
					'default' => null,
					'min_range' => 0,
					'max_range' => self::LISTE_OBS_MAX_BDTFX_NT));
			$paramFiltre = filter_var($this->parametres['masque.nt'], FILTER_VALIDATE_INT, $options);
			$this->parametresFiltres['masque.nt'] = $paramFiltre;
		}
	}

	private function supprimerCaracteresInvisibles(Array $liste_params) {
		foreach ($liste_params as $param) {
			if (isset($this->parametres[$param])) {
				$this->parametresFiltres[$param] = trim($this->parametres[$param]);
			}
		}
	}

	private function filtrerFamille() {
		if (isset($this->parametres['masque.famille'])) {
			// mysql -N<<<"SELECT DISTINCT famille FROM bdtfx_v1_02;"|sed -r "s/(.)/\1\n/g"|sort -u|tr -d "\n"
			$familleTranslit = iconv('UTF-8', 'ASCII//TRANSLIT',$this->parametres['masque.famille']);
			$paramFiltre = preg_replace('/[^a-zA-Z %_]/', '', $familleTranslit);
			$this->parametresFiltres['masque.famille'] = $paramFiltre;
		}
	}

	// Idem pour id_zone_geo qui mappait à ce_zone_geo:
	private function filtrerIdZoneGeo() {
		if (isset($this->parametres['masque.id_zone_geo'])) {
			if (preg_match('/^(INSEE-C:\d{5}|\d{2})$/', $this->parametres['masque.id_zone_geo'])) {
				$paramFiltre = $this->parametres['masque.id_zone_geo'];
				$this->parametresFiltres['masque.id_zone_geo'] = $paramFiltre;
			}
		}
	}

	/** masque.commune (zone_geo)
	 * TODO: que faire avec des '%' en INPUT ?
	 * Le masque doit *permettre* une regexp et non l'imposer. Charge au client de faire son travail.
	 */
	private function filtrerCommune() {
		if (isset($this->parametres['masque.commune'])) {
			$paramFiltre = str_replace(array('-',' '), '_', $this->parametres['masque.commune']);
			$this->parametresFiltres['masque.commune'] = $paramFiltre;
		}
	}

	// masque.tag, idem que pour masque.genre et masque.commune
	private function filtrerTag() {
		if (isset($this->parametres['masque.tag'])) {
			$tagsArray = explode(',', $this->parametres['masque.tag']);
			$tagsTrimes = array_map('trim', $tagsArray);
			$tagsFiltres = array_filter($tagsTrimes);
			$paramFiltre = implode('|', $tagsFiltres);
			$this->parametresFiltres['masque.tag'] = $paramFiltre;
		}
	}

	private function filtrerTagCel() {
		if (isset($this->parametres['masque.tag_cel'])) {
			$this->parametresFiltres['masque.tag_cel'] = $this->construireTableauTags($this->parametres['masque.tag_cel'], 'OR', ',');
		} else if (isset($this->parametres['masque'])) {
			$this->parametresFiltres['masque.tag_cel'] = $this->construireTableauTags($this->parametres['masque'], 'AND', ' ');
			$this->parametresFiltres['_parametres_condition_or_'][] = 'masque.tag_cel';
		}
	}

	private function filtrerTagDel() {
		if (isset($this->parametres['masque.tag_del'])) {
			$this->parametresFiltres['masque.tag_del'] = $this->construireTableauTags($this->parametres['masque.tag_del'], 'OR', ',');
		} else if (isset($this->parametres['masque'])) {
			$this->parametresFiltres['masque.tag_del'] = $this->construireTableauTags($this->parametres['masque'], 'AND', ' ');
			$this->parametresFiltres['_parametres_condition_or_'][] = 'masque.tag_del';
		}
	}


	/**
	 * Construit un (vulgaire) abstract syntax tree:
	 * "AND" => [ "tag1", "tag2" ]
	 * Idéalement (avec un parser simple comme proposé par http://hoa-project.net/Literature/Hack/Compiler.html#Langage_PP)
	 * nous aurions:
	 * "AND" => [ "tag1", "tag2", "OR" => [ "tag3", "tag4" ] ]
	 *
	 * Ici nous devons traiter les cas suivants:
	 * tags séparés par des "ET/AND OU/OR", séparés par des espaces ou des virgules.
	 * Mais la chaîne peut aussi avoir été issue du "masque général" (la barre de recherche générique).
	 * ce qui implique des comportement par défaut différents afin de préserver la compatibilité.
	 *
	 * Théorie:
	 * 1) tags passés par "champ tag":
	 * - support du ET/OU, et explode par virgule.
	 * - si pas d'opérande détectée: "OU"
	 *
	 * 2) tags passés par "recherche générale":
	 * - support du ET/OU, et explode par whitespace.
	 * - si pas d'opérande détectée: "ET"
	 *
	 * La présence de $additional_sep s'explique car ET/OU sous-entendent une séparation par des espaces.
	 * Mais ce n'est pas toujours pertinent car: 1) la compatibilité suggère de considérer parfois
	 * la virgule comme séparateur et 2) les tags *peuvent* contenir des espaces. Par conséquent:
	 * * a,b,c => "a" $default_op "b" $default_op "c"
	 * * a,b AND c => "a" AND "b" AND "c"
	 * * a OR b AND c,d => "a" AND "b" AND "c" AND "d"
	 * C'est à dire par ordre décroissant de priorité:
	 * 1) opérande contenu dans la chaîne
	 * 2) opérande par défaut
	 * 3) les séparateurs présents sont substitués par l'opérande déterminée par 1) ou 2)
	 *
	 * // TODO: support des parenthèses, imbrications & co: "(", ")"
	 * // http://codehackit.blogspot.fr/2011/08/expression-parser-in-php.html
	 * // http://blog.angeloff.name/post/2012/08/05/php-recursive-patterns/
	 *
	 * @param $str: la chaîne à "parser"
	 * @param $operateur_par_defaut: "AND" ou "OR"
	 * @param $separateur_additionnel: séparateur de mots:
	 */
	public function construireTableauTags($str = null, $operateur_par_defaut, $separateur_additionnel = ',') {
		if (!$str) return;
		$op = $this->definirOperateurParDefaut($str, $operateur_par_defaut);

		$mots = preg_split('/ (OR|AND|ET|OU) /', $str, -1, PREG_SPLIT_NO_EMPTY);
		if ($separateur_additionnel) {
			foreach ($mots as $index => $mot) {
				$mot = trim($mot);
				$mots_separes = preg_split("/$separateur_additionnel/", $mot, -1, PREG_SPLIT_NO_EMPTY);
				$mots[$index] = array_shift($mots_separes);
				$mots = array_merge($mots, $mots_separes);
			}
		}
		$mots = array_filter($mots);
		return array($op => $mots);
	}

	private function definirOperateurParDefaut($str, $operateur_par_defaut) {
		$op = $operateur_par_defaut;
		if (preg_match('/\b(ET|AND)\b/', $str)) {
			$op = 'AND';
		} else if(preg_match('/\b(OU|OR)\b/', $str)) {
			$op = 'OR';
		}
		return $op;
	}

	// masque.type: ['adeterminer', 'aconfirmer', 'endiscussion', 'validees']
	private function filtrerType() {
		if(isset($this->parametres['masque.type'])) {
			$typesArray = explode(';', $this->parametres['masque.type']);
			$typesFiltres = array_filter($typesArray);
			$typesAutorises = array('adeterminer', 'aconfirmer', 'endiscussion', 'validees');
			$typesValides = array_intersect($typesFiltres, $typesAutorises);
			$paramFiltre = array_flip($typesValides);
			$this->parametresFiltres['masque.type'] = $paramFiltre;
		}
	}

	private function filtrerProtocole() {
		// ces critère de tri des image à privilégier ne s'applique qu'à un protocole donné
		if (!isset($this->parametres['protocole']) || !is_numeric($this->parametres['protocole'])) {
			$this->parametresFiltres['protocole'] = $this->conteneur->getParametre('appli_img.protocole_defaut');
		} else {
			$this->parametresFiltres['protocole'] = intval($this->parametres['protocole']);
		}
	}

	private function supprimerParametresFiltresInvalides() {
		// Suppression des NULL, FALSE et '', mais pas des 0, d'où l'utilisation de 'strlen'.
		// La fonction 'strlen' permet de supprimer les NULL, FALSE et chaines vides mais gardent les valeurs 0 (zéro).
		// Les valeurs spéciales contenant des tableaux (tag, _parametres_condition_or_) ne sont pas prise en compte
		foreach ($this->parametresFiltres as $cle => $valeur) {
			if (is_array($valeur) || strlen($valeur) !== 0) {
				$this->parametresFiltres[$cle] = $valeur;
			}
		}
	}
}