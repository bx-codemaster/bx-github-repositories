<?php
/* BX Github Repositories - Javascript */
  defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

  if (basename($_SERVER['PHP_SELF']) == 'bx_github_repositories.php') {
?>
<script>
  "use strict";
 	// -------------------------------------------------------------
	// Tab-Initialisierung (Vanilla JS)
	// -------------------------------------------------------------
	// Aufgabe:
	// - Aktivieren/Deaktivieren von Tab-Navigation und Tab-Inhalten
	// - Letzten aktiven Tab via localStorage wiederherstellen
	// - TTL (1 Stunde), damit kein veralteter Zustand ewig erhalten bleibt
	document.addEventListener('DOMContentLoaded', function () {
		const tabs          = document.querySelectorAll('.tabs .tab-nav a');
		const contents      = document.querySelectorAll('.tabs .tab-content > div');
		const rightContents = document.querySelectorAll('.boxRight .tab-content > div');

		const STORAGE_KEY   = 'bxGitHubRepositoriesActiveTab';
		const EXPIRATION_MS = 1000 * 60 * 60; // 1 Stunde

		// Aktiviert einen Tab anhand seiner href-ID (z. B. #tab-plain / #tab-html)
		// und entfernt zuvor gesetzte Active-Zustände.
		function activateTab(tabId) {
			// Navigation
			tabs.forEach(function (t) {
				t.classList.remove('active');
				t.setAttribute('aria-selected', 'false');
				t.setAttribute('tabindex', '-1');
			});
			const activeTab = document.querySelector(`.tabs .tab-nav a[href="${tabId}"]`);
			if (activeTab) {
				activeTab.classList.add('active');
				activeTab.setAttribute('aria-selected', 'true');
				activeTab.setAttribute('tabindex', '0');
			}

			// Inhalte
			contents.forEach(function (c) {
				c.classList.remove('active');
				c.setAttribute('hidden', 'hidden');
			});
			const target = document.querySelector(tabId);
			if (target) {
				target.classList.add('active');
				target.removeAttribute('hidden');
			}

			// Rechte Spalte synchron zum aktiven Tab umschalten
			rightContents.forEach(function (c) {
				c.classList.remove('active');
				c.setAttribute('hidden', 'hidden');
			});

			const rightTargetId = tabId + '-right';
			const rightTarget = document.querySelector(rightTargetId);
			if (rightTarget) {
				rightTarget.classList.add('active');
				rightTarget.removeAttribute('hidden');
			}
		}

		// Klick auf Tab-Link:
		// - Browser-Default (Anker-Sprung) verhindern
		// - Tab umschalten
		// - Auswahl inkl. Timestamp im localStorage ablegen
		tabs.forEach(tab => {
			tab.addEventListener('click', function (e) {
				e.preventDefault();
				const tabId = this.getAttribute('href');
				activateTab(tabId);

				// Tab + Timestamp speichern
				const data = {
					tabId: tabId,
					timestamp: Date.now()
				};
				localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
			});
		}); 

		// Beim Laden versuchen wir, den zuletzt aktiven Tab wiederherzustellen.
		// Falls Daten beschädigt/abgelaufen sind, wird auf den ersten Tab zurückgesetzt.
		const stored = localStorage.getItem(STORAGE_KEY);
		if (stored) {
			try {
				const data = JSON.parse(stored);
				if (Date.now() - data.timestamp < EXPIRATION_MS) {
					// noch gültig
					activateTab(data.tabId);
				} else {
					// abgelaufen -> löschen und ersten Tab aktivieren
					localStorage.removeItem(STORAGE_KEY);
					if (tabs.length > 0) {
						activateTab(tabs[0].getAttribute('href'));
					}
				}
			} catch (e) {
				// falls JSON ungültig -> reset
				localStorage.removeItem(STORAGE_KEY);
				if (tabs.length > 0) {
					activateTab(tabs[0].getAttribute('href'));
				}
			}
		} else if (tabs.length > 0) {
			// Standard: Ersten aktivieren
			activateTab(tabs[0].getAttribute('href'));
		}

		var fixedStack = document.querySelector('.fixed_messageStack');
		if (fixedStack) {
			if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn !== 'undefined') {
				window.jQuery(fixedStack).stop(true, true).slideDown('slow', function() {
					setTimeout(function() {
						window.jQuery(fixedStack).slideUp('slow');
					}, 3000);
				});
			} else {
				fixedStack.style.display = 'block';
				setTimeout(function() {
					fixedStack.style.display = 'none';
				}, 3000);
			}
		}
	});
</script>
<?php } ?>