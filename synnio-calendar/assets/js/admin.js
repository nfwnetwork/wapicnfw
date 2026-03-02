/**
 * Synnio Calendar Admin JavaScript
 * Version 3.4.0 - Eigene Tab-Klassen (keine Plugin-Konflikte)
 */

(function() {
    'use strict';

    console.log('[SynnioCalendarAdmin] Script geladen');

    // Unsere Tabs verwenden jetzt eigene Klassen (.synnio-admin-tab)
    // statt der Standard WordPress .nav-tab Klassen.
    // Dadurch werden Konflikte mit anderen Plugins vermieden.

    window.SynnioCalendarAdmin = {
        initialized: false,

        debug: function(msg, data) {
            console.log('[SynnioCalendarAdmin] ' + msg, data !== undefined ? data : '');
        },

        init: function() {
            this.debug('Init gestartet');

            if (this.initialized) {
                this.debug('Bereits initialisiert');
                return;
            }

            this.bindEvents();

            this.initialized = true;
            this.debug('Init abgeschlossen');
        },

        bindEvents: function() {
            var self = this;
            this.debug('Binde Events...');

            // Event Delegation fuer andere Buttons
            document.addEventListener('click', function(e) {
                var target = e.target;
                var btn = target;
                var maxDepth = 10;
                var depth = 0;

                while (btn && depth < maxDepth) {
                    // Toggle User Button
                    if (btn.classList && btn.classList.contains('synnio-toggle-user')) {
                        self.debug('Toggle User geklickt');
                        e.preventDefault();
                        self.toggleUser(btn);
                        return;
                    }

                    // Copy API Key
                    if (btn.classList && btn.classList.contains('synnio-copy-api-key')) {
                        self.debug('Copy API Key geklickt');
                        e.preventDefault();
                        self.copyApiKey(btn);
                        return;
                    }

                    // Regenerate API Key
                    if (btn.classList && btn.classList.contains('synnio-regenerate-api-key')) {
                        self.debug('Regenerate API Key geklickt');
                        e.preventDefault();
                        self.regenerateApiKey();
                        return;
                    }

                    // Synnio Tab Click (nur fuer Frontend Settings Modal)
                    if (btn.classList && btn.classList.contains('synnio-tab')) {
                        self.debug('Synnio Tab geklickt:', btn.dataset.tab);
                        e.preventDefault();
                        self.switchSynnioTab(btn);
                        return;
                    }

                    btn = btn.parentElement;
                    depth++;
                }
            });

            this.debug('Events gebunden');
        },

        switchSynnioTab: function(tabElement) {
            var tabId = tabElement.dataset.tab;
            this.debug('Switch Synnio Tab zu:', tabId);

            var container = tabElement.closest('.synnio-tabs-container') ||
                           tabElement.closest('.synnio-modal-body') ||
                           tabElement.closest('.synnio-settings-modal') ||
                           document;

            // Alle Tabs deaktivieren
            var allTabs = container.querySelectorAll('.synnio-tab');
            allTabs.forEach(function(tab) {
                tab.classList.remove('active');
            });

            // Alle Tab Contents verstecken
            var allContents = container.querySelectorAll('.synnio-tab-content');
            allContents.forEach(function(content) {
                content.classList.remove('active');
                content.style.display = 'none';
            });

            // Aktiven Tab setzen
            tabElement.classList.add('active');

            // Tab Content anzeigen
            var tabContent = document.getElementById(tabId);
            if (tabContent) {
                tabContent.classList.add('active');
                tabContent.style.display = 'block';
                this.debug('Synnio Tab Content aktiviert:', tabId);
            } else {
                this.debug('FEHLER: Synnio Tab Content nicht gefunden:', tabId);
            }
        },

        toggleUser: function(btn) {
            var userId = btn.dataset.userId;
            var enabled = btn.dataset.enabled === '1';
            var self = this;

            this.debug('Toggle User:', userId, 'Aktuell:', enabled);

            if (typeof synnioCalendarAdmin === 'undefined') {
                this.debug('FEHLER: synnioCalendarAdmin nicht definiert');
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendarAdmin.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler');
                        }
                    } catch (e) {
                        self.debug('FEHLER:', e);
                    }
                }
            };

            var params = 'action=synnio_calendar_admin_toggle_user&nonce=' + synnioCalendarAdmin.nonce +
                        '&user_id=' + userId + '&enabled=' + (!enabled ? 'true' : 'false');
            xhr.send(params);
        },

        copyApiKey: function(btn) {
            var input = btn.previousElementSibling;
            if (!input) {
                input = btn.parentElement.querySelector('input');
            }

            if (input) {
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand('copy');
                alert('API-Schluessel kopiert!');
            }
        },

        regenerateApiKey: function() {
            var self = this;

            if (!confirm('Moechten Sie wirklich einen neuen API-Schluessel generieren? Der alte Schluessel wird ungueltig.')) {
                return;
            }

            if (typeof synnioCalendarAdmin === 'undefined') {
                this.debug('FEHLER: synnioCalendarAdmin nicht definiert');
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendarAdmin.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            var apiKeyInput = document.querySelector('input[name="synnio_calendar_api_key"]');
                            if (apiKeyInput) {
                                apiKeyInput.value = response.data.api_key;
                            }
                            alert('Neuer API-Schluessel wurde generiert.');
                        }
                    } catch (e) {
                        self.debug('FEHLER:', e);
                    }
                }
            };

            xhr.send('action=synnio_calendar_regenerate_api_key&nonce=' + synnioCalendarAdmin.nonce);
        }
    };

    // Initialisierung
    function initAdmin() {
        console.log('[SynnioCalendarAdmin] initAdmin aufgerufen');
        window.SynnioCalendarAdmin.init();
    }

    // DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdmin);
    } else {
        initAdmin();
    }

    // window.onload
    window.addEventListener('load', function() {
        if (!window.SynnioCalendarAdmin.initialized) {
            initAdmin();
        }
    });

    // jQuery Ready
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function() {
            if (!window.SynnioCalendarAdmin.initialized) {
                initAdmin();
            }
        });
    }

})();
