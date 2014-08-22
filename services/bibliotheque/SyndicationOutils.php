<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class SyndicationOutils {

	private $conteneur;
	private $contexte;

	public function __construct($conteneur) {
		$this->conteneur = $conteneur;
		$this->contexte = $this->conteneur->getContexte();
	}

	/**
	 * Verifier si le flux admin est demande
	 */
	public function fluxAdminDemande() {
		return $this->contexte->getQS('admin') != null && $this->contexte->getQS('admin') == 1;
	}

	public function demanderAutorisationAdmin() {
		$verification = $this->conteneur->getControleAcces();
		$verification->demanderAuthentificationAdmin();
	}

	/**
	 * Générer les métadonnées du flux (titre, dates, editeur etc.)
	 * */
	public function construireDonneesCommunesAuFlux($nomFlux, $dateDernierElement) {
		$donnees = array();
		$donnees['guid'] = $this->creerUrlService();
		$donnees['titre'] = $this->conteneur->getParametre("syndication.{$nomFlux}_titre");
		$donnees['description'] = $this->conteneur->getParametre("syndication.{$nomFlux}_dsc");
		$donnees['lien_service'] = $this->creerUrlService();
		$donnees['lien_del'] = $this->conteneur->getParametre('img_appli_lien');
		$donnees['editeur'] = $this->conteneur->getParametre('syndication.editeur');
		$date_modification_timestamp = strtotime($dateDernierElement);
		$donnees['date_maj_RSS'] = date(DATE_RSS, $date_modification_timestamp);
		$donnees['date_maj_ATOM'] = date(DATE_ATOM, $date_modification_timestamp);
		$donnees['date_maj_W3C'] = date(DATE_W3C, $date_modification_timestamp);
		$donnees['annee_courante'] = date('Y');
		$donnees['generateur'] =  $this->conteneur->getParametre("syndication.generateur_nom");
		$donnees['generateur_version'] =  $this->conteneur->getParametre("syndication.generateur_version");
		return $donnees;
	}

	public function creerUrlService() {
		$url = 'http://'.
			$this->contexte->getServer('SERVER_NAME').
			$this->contexte->getServer('REQUEST_URI');
		return htmlspecialchars($url);
	}

	public function getUrlImage($id, $format = 'L') {
		$url_tpl = $this->conteneur->getParametre('cel_img_url_tpl');
		$url = sprintf($url_tpl, $id, $format);
		return $url;
	}

	public function convertirDateHeureMysqlEnTimestamp($date_heure_mysql){
		$timestamp = 0;
		// Le date de 1970-01-01 pose problème dans certains lecteur de Flux, on met donc la date de création de Tela
		$date_heure_mysql = ($date_heure_mysql == '0000-00-00 00:00:00') ? '1999-12-14 00:00:00' : $date_heure_mysql;
		if ($date_heure_mysql != '0000-00-00 00:00:00') {
			$val = explode(' ', $date_heure_mysql);
			$date = explode('-', $val[0]);
			$heure = explode(':', $val[1]);
			$timestamp = mktime((int) $heure[0], (int) $heure[1], (int) $heure[2], (int) $date[1], (int) $date[2], (int) $date[0]);
		}
		return $timestamp;
	}
}