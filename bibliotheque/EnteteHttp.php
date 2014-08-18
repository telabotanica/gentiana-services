<?php
// declare(encoding='UTF-8');
/**
 * Classe contenant le contenu par défaut de l'entête d'une réponse http par défaut.
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
class EnteteHttp {
	public $code = RestServeur::HTTP_CODE_OK;
	public $encodage = 'utf-8';
	public $mime = 'application/json';
}