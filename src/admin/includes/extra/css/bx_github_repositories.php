<?php
  /**
  * Projekt: modified eCommerce Shopsoftware
  * Modul: BX GitHub Repositories
  * Datei: admin/includes/extra/css/bx_github_repositories.php
  *
  * Datei-Header:
  * - Bindet modulbezogene CSS-Regeln fuer die Admin-Seite `bx_github_repositories.php` ein.
  * - Ausgabe erfolgt nur, wenn die aktuelle Seite `bx_github_repositories.php` ist.
  *
  * @package    BX_GitHub_Repositories
  * @author     Axel Benkert <info@bx-coding.de>
  * @copyright  (c) 2026
  * @version    1.0.0
  * @since      2026-03-05
  */

  defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

  if (basename($_SERVER['PHP_SELF']) == 'bx_github_repositories.php') {
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>

  #headboard {
    display: flex; 
    flex-direction: row; 
    width: 100%;
    align-items: center; 
    background: #AF417E; 
    color: #ffffff; 
    border-radius: 4px; 
    margin-bottom: 10px; 
    padding: 4px 0 2px 0;
    line-height: 30px;
  }

  #headboard .main {
    margin: 5px 10px;
  }

  .fixed_messageStack {
    /* 1. Aus dem Dokumentenfluss nehmen */
    position: fixed; 
    /* 2. Oben zentrieren */
    top: 88px; 
    left: 50%;
    transform: translateX(-50%); /* Zentriert den Container horizontal */
    /* 3. Über allen anderen Elementen anzeigen */
    z-index: 1000; 
    /* 4. Aussehen und Breite festlegen */
    width: 80%; /* Beispiel: Volle Breite */
    /* max-width: 800px; Optional: Maximale Breite für bessere Lesbarkeit */
    padding: 10px 0;
    text-align: center;    
    /* Wichtig: Standardmäßig ausgeblendet */
    display: none;
  }
  
  .error_message,
  .warning_message,
  .info_message,
  .success_message {
    margin-bottom: 2px;
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .error_message {
    border: solid #F5C2C7 1px;
    color: #842029;
    background-color: #F8D7DA;
  }
  .warning_message {
    border: solid #FFDF9E 1px;
    color: #664D03;
    background-color: #FFF3CD;
  }
  .info_message {
    border: solid #B6D4FE 1px;
    background-color: #CFE2FF;
    color: #084298;
  }
  .success_message {
    border: solid #BADBCC 1px;
    background-color: #D1E7DD;
    color: #0F5132;
  }
  </style>
  <?php } ?>