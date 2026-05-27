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
  button.bx_box_right {
    min-width: 200px;
  }

  #headboard .main {
    margin: 5px 10px;
  }
  
  #github_private_key {
    width: 100%; 
    max-width: 680px; 
    background-color: #fafafa; 
    border-color: #c6c6c6 #dadada #eaeaea; 
    color:#777;
  }

  .bx-gh-page-heading-image {
    min-width: 45px;
  }

  .bx-gh-heading-icon {
    max-height: 32px;
  }

  .bx-gh-section-heading {
    margin: 6px 0 8px;
  }

  .bx-gh-section-text {
    margin-bottom: 14px;
  }

  .bx-gh-form-label-col {
    width: 220px;
  }

  .bx-gh-input-regular {
    width: 100%;
    max-width: 420px;
  }

  .bx-gh-vertical-top {
    vertical-align: top;
  }

  .bx-gh-help-text {
    margin-top: 6px;
    color: #666;
  }

  .bx-gh-status-text {
    margin-top: 8px;
    color: #555;
  }

  .bx-gh-actions-row {
    margin: 12px 0 16px;
  }

  .bx-gh-save-settings-btn {
    margin-left: 8px;
  }

  .bx-gh-repo-intro {
    margin-bottom: 8px;
  }

  .bx-gh-repo-header-layout {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .bx-gh-repo-header-left {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-width: 0;
  }

  .bx-gh-repo-header-actions {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 8px;
    margin-top: 16px;
  }

  .bx-gh-right-mass-actions {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 6px;
  }

  @media (max-width: 900px) {
    .bx-gh-repo-header-layout {
      flex-direction: column;
    }

    .bx-gh-repo-header-actions {
      align-items: flex-start;
      margin-top: 4px;
    }
  }

  .bx-gh-col-local-filename {
    width: 35%;
  }

  .bx-gh-col-zip-status {
    width: 70px;
    text-align: center;
  }

  .bx-gh-zip-status-cell {
    text-align: center;
  }

  .bx-gh-zip-dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    vertical-align: middle;
  }

  .bx-gh-zip-dot-present {
    background: #2e7d32;
  }

  .bx-gh-zip-dot-missing {
    background: #c62828;
  }

  .bx-gh-col-version {
    width: 150px;
    text-align: center;
  }

  .bx-gh-col-last-check {
    width: 150px;
  }

  .bx-gh-empty-row {
    color: #777;
  }

  .bx-gh-row-error {
    background: #fff3f3;
  }

  .bx-gh-row-update {
    background: #fffbe6;
  }

  .bx-gh-repo-error-msg {
    color: #c00;
    font-size: 11px;
    margin-top: 2px;
  }

  .bx-gh-text-muted {
    color: #666;
  }

  a.bx-gh-local-file-link:link { 
    font-family: Verdana, Arial, sans-serif; 
    font-size: 12px; 
    color: #1a5a96; 
    font-weight: normal; 
    text-decoration: none; 
    outline: none; 
  }
  a.bx-gh-local-file-link:visited { 
    font-family: Verdana, Arial, sans-serif; 
    font-size: 12px; 
    color: #006600; 
    font-weight: normal; 
    text-decoration: none; 
    outline: none; 
  }
  a.bx-gh-local-file-link:active { 
    font-family: Verdana, Arial, sans-serif; 
    font-size: 12px; 
    color: #000000; 
    font-weight: normal; 
    text-decoration: none; 
    outline: none; 
  }
  a.bx-gh-local-file-link:hover,
  a.bx-gh-local-file-link:focus { 
    font-family: Verdana, Arial, sans-serif; 
    font-size: 12px; 
    color: #0f3f6d; 
    font-weight: normal; 
    text-decoration: underline; 
    outline: none; 
  }








  .bx-gh-local-file-missing {
    color: #777;
    word-break: break-all;
  }

  .bx-gh-version-cell {
    color: #444;
    font-size: 12px;
    text-align: center;
  }

  .bx-gh-download-repo-btn {
    background: #c62828;
    border-color: #8e1d1d;
    color: #fff;
    font-size: 11px;
    line-height: 1.2;
    padding: 2px 8px;
    min-height: auto;
    white-space: nowrap;
  }

  .bx-gh-lastcheck-cell {
    color: #888;
    font-size: 12px;
  }

  .bx-gh-selection-actions {
    margin-top: 10px;
  }

  .bx-gh-import-target {
    margin-top: 12px;
    color: #555;
  }

  .bx-gh-manual-text {
    margin-bottom: 10px;
    line-height: 1.5;
  }

  .bx-gh-text-soft-dark {
    color: #555;
  }

  .bx-gh-manual-code {
    background: #f7f7f7;
    border: 1px solid #ddd;
    padding: 8px;
    margin: 0 0 10px;
    overflow: auto;
  }

  .bx-gh-manual-rich {
    line-height: 1.55;
    color: #333;
  }

  .bx-gh-manual-rich h3,
  .bx-gh-manual-rich h4 {
    margin: 12px 0 8px;
    color: #202020;
  }

  .bx-gh-manual-rich p,
  .bx-gh-manual-rich ul,
  .bx-gh-manual-rich ol,
  .bx-gh-manual-rich pre {
    margin: 0 0 10px;
  }

  .bx-gh-manual-rich ul,
  .bx-gh-manual-rich ol {
    padding-left: 22px;
  }

  .bx-gh-manual-rich pre {
    background: #f7f7f7;
    border: 1px solid #ddd;
    padding: 8px;
    overflow: auto;
  }

  .bx-gh-manual-rich code {
    font-family: Consolas, "Courier New", monospace;
  }

  .bx-gh-manual-rich {
    line-height: 1.55;
    color: #333;
  }

  .bx-gh-manual-rich h3,
  .bx-gh-manual-rich h4 {
    margin: 12px 0 8px;
    color: #202020;
  }

  .bx-gh-manual-rich p,
  .bx-gh-manual-rich ul,
  .bx-gh-manual-rich ol,
  .bx-gh-manual-rich pre {
    margin: 0 0 10px;
  }

  .bx-gh-manual-rich ul,
  .bx-gh-manual-rich ol {
    padding-left: 22px;
  }

  .bx-gh-manual-rich pre {
    background: #f7f7f7;
    border: 1px solid #ddd;
    padding: 8px;
    overflow: auto;
  }

  .bx-gh-manual-rich code {
    font-family: Consolas, "Courier New", monospace;
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

    .tabs .tab-nav {
    list-style: none; 
    padding: 0;
    display: flex;
    gap: 6px;
    margin:0;
  }
  .tabs .tab-nav li a {
    padding: 6px 10px;
    background: #f1f1f1;
    border: 1px solid #ccc;
    border-bottom: none;
    display: inline-block;
    border-radius: 4px 4px 0 0;
    text-decoration: none;
    color: #222;
  }
  .tabs .tab-nav li a.active {
    background: #AF417E;
    color: #fff;
    font-weight: bold;
  }
  .tabs .tab-content {
    border-top: 1px solid #ccc;
  }
  .tabs .tab-content > div {
    display: none;
    padding: 5px;
    border: 1px solid #ccc;
    background: #fff;
    border-top: none;
  }
  .tabs .tab-content > div.active {
    display: block;
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

  #bx-github-loading-overlay {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(20, 26, 36, 0.55);
    z-index: 9999;
  }

  .bx-github-loading-box {
    min-width: 320px;
    max-width: 90vw;
    padding: 20px 24px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
    text-align: center;
    color: #1f2a38;
  }

  .bx-github-loading-spinner {
    width: 34px;
    height: 34px;
    margin: 0 auto 10px;
    border-radius: 50%;
    border: 4px solid #d7dde6;
    border-top-color: #2b6cb0;
    animation: bx-github-spin 0.9s linear infinite;
  }

  .bx-github-loading-title {
    margin: 0 0 6px;
    font-size: 15px;
    font-weight: 700;
  }

  .bx-github-loading-note {
    margin: 0;
    font-size: 12px;
    color: #4a5568;
  }

  @keyframes bx-github-spin {
    to {
      transform: rotate(360deg);
    }
  }

    .bx-gh-button-create {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: white !important;
  }
  .bx-gh-button-create:hover {
    background-color: #218838 !important;
    border-color: #218838 !important;
  }
  
  .bx-gh-button-red {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
    white-space: nowrap;
  }
  .bx-gh-button-red:hover {
    background-color: #c82333 !important;
    border-color: #c82333 !important;
  }
  </style>
<?php } ?>
