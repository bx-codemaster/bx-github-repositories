<?php
/* -----------------------------------------------------------------------------------------
   BX GitHub Repositories - Scheduled Task: Notify
   ---------------------------------------------------------------------------------------*/

function cron_github_repositories_notify() {
    if (!defined('MODULE_BX_GITHUB_REPOSITORIES_STATUS')
      || (string)constant('MODULE_BX_GITHUB_REPOSITORIES_STATUS') !== 'True'
      || !defined('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS')
      || (string)constant('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS') !== 'True') {
    return true;
  }

  // Platzhalter: Versandlogik folgt in Phase 5.
  // Die Funktion muss bereits existieren, damit der registrierte Scheduled Task
  // im Cronjob-Prozess sauber aufgerufen und protokolliert werden kann.
  return true;
}
