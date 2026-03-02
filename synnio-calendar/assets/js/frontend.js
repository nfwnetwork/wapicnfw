/**
 * Synnio Calendar Frontend JavaScript
 * Version 3.4.0 - Kalender-Toggle, Zaehler und Termintyp-Entfernung
 */

(function() {
    'use strict';

    console.log('[SynnioKalender] Script geladen - Version 3.4.0');

    window.SynnioCalendar = {
        currentDate: new Date(),
        currentView: 'week',
        events: [],
        calendars: [],
        selectedCalendars: [],
        initialized: false,

        debug: function(msg, data) {
            if (data !== undefined) {
                console.log('[SynnioKalender] ' + msg, data);
            } else {
                console.log('[SynnioKalender] ' + msg);
            }
        },

        init: function() {
            this.debug('=== INIT GESTARTET ===');

            if (this.initialized) {
                this.debug('Bereits initialisiert');
                return;
            }

            var container = document.querySelector('.synnio-calendar-app');
            if (!container) {
                this.debug('FEHLER: Container .synnio-calendar-app nicht gefunden!');
                return;
            }

            this.debug('Container gefunden');

            // Wrapper pruefen
            var wrapper = document.getElementById('synnio-calendar-wrapper');
            if (wrapper) {
                this.debug('Wrapper #synnio-calendar-wrapper gefunden');
            } else {
                this.debug('WARNUNG: Wrapper #synnio-calendar-wrapper NICHT gefunden');
            }

            // Modals pruefen
            this.checkModals();

            // Event Binding
            this.bindAllEvents();

            // Initial Render
            this.renderMiniCalendar();
            this.renderCalendar();
            this.updateCurrentDate();
            this.loadEvents();
            this.loadCalendars();
            this.initSettingsTabs();
            this.initCalendarColorSync();
            this.initCalendarToggles();

            // Zeitlinie aktualisieren
            this.updateCurrentTimeLine();
            var self = this;
            setInterval(function() {
                self.updateCurrentTimeLine();
            }, 60000);

            // View Button aktivieren
            this.setActiveViewButton();

            // OAuth Status laden
            this.loadOAuthStatus();

            // URL Parameter pruefen (OAuth Callback)
            this.checkOAuthCallback();

            this.initialized = true;
            this.debug('=== INIT ABGESCHLOSSEN ===');
        },

        // Modals pruefen und diagnostizieren
        checkModals: function() {
            this.debug('--- Modal Diagnose ---');

            var modalIds = ['synnioEventModal', 'synnioBatchModal', 'synnioSettingsModal', 'synnioEditCalendarModal'];
            var self = this;

            modalIds.forEach(function(id) {
                var modal = document.getElementById(id);
                if (modal) {
                    self.debug('Modal ' + id + ' gefunden');
                    var modalContent = modal.querySelector('.synnio-modal');
                    if (modalContent) {
                        self.debug('  -> .synnio-modal Content gefunden');
                    } else {
                        self.debug('  -> FEHLER: .synnio-modal Content NICHT gefunden!');
                    }

                    // Computed styles pruefen
                    var styles = window.getComputedStyle(modal);
                    self.debug('  -> display: ' + styles.display);
                    self.debug('  -> visibility: ' + styles.visibility);
                    self.debug('  -> opacity: ' + styles.opacity);
                    self.debug('  -> z-index: ' + styles.zIndex);
                } else {
                    self.debug('FEHLER: Modal ' + id + ' NICHT gefunden!');
                }
            });

            this.debug('--- Ende Modal Diagnose ---');
        },

        bindAllEvents: function() {
            var self = this;
            this.debug('Binde Events...');

            document.addEventListener('click', function(e) {
                self.handleClick(e);
            }, true);

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeAllModals();
                }
            });

            this.debug('Events gebunden');
        },

        handleClick: function(e) {
            var target = e.target;
            var self = this;
            var btn = target;
            var maxDepth = 10;
            var depth = 0;

            while (btn && depth < maxDepth) {
                // Neuer Termin Button
                if (btn.classList && (btn.classList.contains('synnio-btn-new-event') || btn.id === 'synnioNewEventBtn')) {
                    this.debug('>>> Neuer Termin Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.openEventModal();
                    return;
                }

                // Sammelverarbeitung Button
                if (btn.id === 'synnioBatchBtn' || (btn.classList && btn.classList.contains('synnio-sidebar-footer-btn') && (btn.textContent || '').indexOf('Sammelverarbeitung') !== -1)) {
                    this.debug('>>> Sammelverarbeitung Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.openModal('synnioBatchModal');
                    return;
                }

                // Einstellungen Button
                if (btn.id === 'synnioSettingsBtn' || (btn.classList && btn.classList.contains('synnio-sidebar-footer-btn') && (btn.textContent || '').indexOf('Einstellungen') !== -1)) {
                    this.debug('>>> Einstellungen Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.openModal('synnioSettingsModal');
                    return;
                }

                // Modal Close Button
                if (btn.classList && btn.classList.contains('synnio-modal-close')) {
                    this.debug('>>> Modal Close Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    var modalOverlay = btn.closest('.synnio-modal-overlay');
                    if (modalOverlay) {
                        this.closeModal(modalOverlay.id);
                    } else {
                        this.closeAllModals();
                    }
                    return;
                }

                // data-close-modal Attribute
                if (btn.dataset && btn.dataset.closeModal) {
                    this.debug('>>> Close Modal via data-close-modal:', btn.dataset.closeModal);
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeModal(btn.dataset.closeModal);
                    return;
                }

                // Modal Overlay Click (ausserhalb des Modals)
                if (btn.classList && btn.classList.contains('synnio-modal-overlay') && target === btn) {
                    this.debug('>>> Overlay geklickt');
                    e.preventDefault();
                    this.closeModal(btn.id);
                    return;
                }

                // View Buttons
                if (btn.classList && btn.classList.contains('synnio-view-btn')) {
                    this.debug('>>> View Button geklickt:', btn.dataset.view);
                    e.preventDefault();
                    e.stopPropagation();
                    this.changeView(btn.dataset.view);
                    return;
                }

                // Today Button
                if (btn.classList && (btn.classList.contains('synnio-btn-today') || btn.id === 'synnioTodayBtn')) {
                    this.debug('>>> Today Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.goToToday();
                    return;
                }

                // Navigation Prev
                if (btn.id === 'synnioPrevBtn' || (btn.parentElement && btn.parentElement.id === 'synnioPrevBtn')) {
                    this.debug('>>> Prev Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.navigatePrev();
                    return;
                }

                // Navigation Next
                if (btn.id === 'synnioNextBtn' || (btn.parentElement && btn.parentElement.id === 'synnioNextBtn')) {
                    this.debug('>>> Next Button geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.navigateNext();
                    return;
                }

                // Mini Calendar Prev
                if (btn.id === 'synnioMiniCalPrev' || (btn.parentElement && btn.parentElement.id === 'synnioMiniCalPrev')) {
                    this.debug('>>> Mini Cal Prev geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.miniCalendarPrev();
                    return;
                }

                // Mini Calendar Next
                if (btn.id === 'synnioMiniCalNext' || (btn.parentElement && btn.parentElement.id === 'synnioMiniCalNext')) {
                    this.debug('>>> Mini Cal Next geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.miniCalendarNext();
                    return;
                }

                // Mini Calendar Day
                if (btn.classList && btn.classList.contains('synnio-mini-cal-day') && btn.dataset.date) {
                    this.debug('>>> Mini Cal Day geklickt:', btn.dataset.date);
                    e.preventDefault();
                    e.stopPropagation();
                    this.selectDate(btn.dataset.date);
                    return;
                }

                // Settings Tabs (Allgemein, Synchronisation, API)
                if (btn.classList && btn.classList.contains('synnio-tab')) {
                    this.debug('>>> Settings Tab geklickt:', btn.dataset.tab);
                    e.preventDefault();
                    e.stopPropagation();
                    this.switchSettingsTab(btn);
                    return;
                }

                // Day Chip
                if (btn.classList && btn.classList.contains('synnio-day-chip')) {
                    e.preventDefault();
                    e.stopPropagation();
                    btn.classList.toggle('selected');
                    return;
                }

                // Time Chip
                if (btn.classList && btn.classList.contains('synnio-time-chip')) {
                    e.preventDefault();
                    e.stopPropagation();
                    btn.classList.toggle('selected');
                    return;
                }

                // Save Event Button
                if (btn.id === 'synnioSaveEventBtn') {
                    this.debug('>>> Save Event geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveEvent();
                    return;
                }

                // Delete Event Button
                if (btn.id === 'synnioDeleteEventBtn') {
                    this.debug('>>> Delete Event geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.deleteEvent();
                    return;
                }

                // Color Option (nur Event-Modal, nicht Edit-Calendar-Modal)
                if (btn.classList && btn.classList.contains('synnio-color-option') && (!btn.closest || !btn.closest('#synnioEditCalendarModal'))) {
                    this.debug('>>> Color Option geklickt:', btn.dataset.color);
                    e.preventDefault();
                    e.stopPropagation();
                    this.selectColor(btn.dataset.color);
                    return;
                }

                // Add Calendar Button
                if (btn.id === 'synnioAddCalendarBtn' || (btn.classList && btn.classList.contains('synnio-add-calendar-btn'))) {
                    this.debug('>>> Add Calendar geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.openAddCalendarModal();
                    return;
                }

                // Edit Calendar Button (Stift-Icon)
                if (btn.classList && btn.classList.contains('synnio-calendar-edit-btn')) {
                    this.debug('>>> Edit Calendar geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.openEditCalendarModal(btn.dataset.calendarId, btn.dataset.calendarName, btn.dataset.calendarColor, btn.dataset.calendarIsDefault);
                    return;
                }

                // Save Calendar (Edit Modal)
                if (btn.id === 'synnioSaveCalendarBtn') {
                    this.debug('>>> Save Calendar geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveCalendarEdit();
                    return;
                }

                // Delete Calendar (Edit Modal)
                if (btn.id === 'synnioDeleteCalendarBtn') {
                    this.debug('>>> Delete Calendar geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.deleteCalendar();
                    return;
                }

                // Copy Button (API credentials)
                if (btn.classList && btn.classList.contains('synnio-copy-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var targetId = btn.dataset.copyTarget;
                    var targetInput = document.getElementById(targetId);
                    if (targetInput) {
                        navigator.clipboard.writeText(targetInput.value).then(function() {
                            var icon = btn.querySelector('i');
                            if (icon) {
                                icon.className = 'fas fa-check';
                                setTimeout(function() { icon.className = 'fas fa-copy'; }, 1500);
                            }
                        });
                    }
                    return;
                }

                // Calendar type item click -> toggle checkbox (da jetzt div statt label)
                if (btn.classList && btn.classList.contains('synnio-calendar-type-item')) {
                    // Nicht toggeln wenn Edit-Button geklickt wurde
                    if (e.target.closest && e.target.closest('.synnio-calendar-edit-btn')) {
                        return;
                    }
                    // Wenn direkt auf Checkbox geklickt wurde, nativ handeln lassen
                    if (e.target.classList && e.target.classList.contains('synnio-calendar-toggle')) {
                        return;
                    }
                    var checkbox = btn.querySelector('.synnio-calendar-toggle');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        var changeEvent = new Event('change', { bubbles: true });
                        checkbox.dispatchEvent(changeEvent);
                    }
                    return;
                }

                // Color Option in Edit Calendar Modal
                if (btn.classList && btn.classList.contains('synnio-color-option') && btn.closest('#synnioEditCalendarModal')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var modal = document.getElementById('synnioEditCalendarModal');
                    modal.querySelectorAll('.synnio-color-option').forEach(function(opt) { opt.classList.remove('selected'); });
                    btn.classList.add('selected');
                    document.getElementById('synnioEditCalendarColor').value = btn.dataset.color;
                    return;
                }

                // Save Settings Button
                if (btn.id === 'synnioSaveSettingsBtn') {
                    this.debug('>>> Save Settings geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveSettings();
                    return;
                }

                // Google Calendar verbinden
                if (btn.id === 'synnioConnectGoogleBtn') {
                    this.debug('>>> Google Connect geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.connectGoogleCalendar();
                    return;
                }

                // Outlook verbinden
                if (btn.id === 'synnioConnectOutlookBtn') {
                    this.debug('>>> Outlook Connect geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.connectOutlook();
                    return;
                }

                // Batch Create Button
                if (btn.id === 'synnioBatchCreateBtn') {
                    this.debug('>>> Batch Create geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveBatchEvents();
                    return;
                }

                // Pause hinzufuegen Button
                if (btn.id === 'synnioAddBreakBtn') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.addBreakRow();
                    return;
                }

                // Pause entfernen Button
                if (btn.classList && btn.classList.contains('synnio-remove-break-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var breakRow = btn.closest('.synnio-break-row');
                    if (breakRow) breakRow.remove();
                    return;
                }

                // Termintyp hinzufuegen Button
                if (btn.id === 'synnioAddEventTypeBtn') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.addEventTypeRow();
                    return;
                }

                // Termintyp entfernen Button
                if (btn.classList && btn.classList.contains('synnio-remove-cet-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var cetRow = btn.closest('.synnio-custom-event-type-row');
                    if (cetRow) cetRow.remove();
                    return;
                }

                // Feiertage herunterladen Button
                if (btn.id === 'synnioDownloadHolidaysBtn') {
                    this.debug('>>> Download Holidays geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    this.importHolidays();
                    return;
                }

                // Time Slot Click
                if (btn.classList && btn.classList.contains('synnio-time-slot')) {
                    this.debug('>>> Time Slot geklickt');
                    e.preventDefault();
                    e.stopPropagation();
                    var date = btn.closest('.synnio-day-column');
                    var hour = btn.dataset.hour;
                    this.openEventModal(date ? date.dataset.date : null, hour);
                    return;
                }

                // Event Click
                if (btn.classList && btn.classList.contains('synnio-event')) {
                    this.debug('>>> Event geklickt:', btn.dataset.eventId);
                    e.preventDefault();
                    e.stopPropagation();
                    this.editEvent(btn.dataset.eventId);
                    return;
                }

                btn = btn.parentElement;
                depth++;
            }
        },

        // Modal oeffnen - ROBUST VERSION mit direkten Inline-Styles
        openModal: function(modalId) {
            this.debug('>>> openModal aufgerufen mit ID:', modalId);

            var modal = document.getElementById(modalId);
            if (!modal) {
                this.debug('FEHLER: Modal Element nicht gefunden: ' + modalId);
                alert('Fehler: Modal konnte nicht gefunden werden.');
                return;
            }

            this.debug('Modal Element gefunden, setze Styles...');

            // Alle moeglichen Styles direkt setzen
            modal.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0, 0, 0, 0.6) !important; backdrop-filter: blur(4px) !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 999999 !important; opacity: 1 !important; visibility: visible !important;';

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Modal Content stylen
            var modalContent = modal.querySelector('.synnio-modal');
            if (modalContent) {
                this.debug('Modal Content gefunden, setze Content Styles...');
                modalContent.style.cssText = 'background: #FFFFFF !important; border-radius: 16px !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important; width: 90% !important; max-width: ' + (modalId === 'synnioEventModal' ? '560px' : '720px') + ' !important; max-height: 90vh !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; position: relative !important; z-index: 1000000 !important; transform: scale(1) !important;';
            } else {
                this.debug('FEHLER: Modal Content (.synnio-modal) NICHT gefunden!');
            }

            // Nach styles check
            var computedStyle = window.getComputedStyle(modal);
            this.debug('Nach oeffnen - display:', computedStyle.display);
            this.debug('Nach oeffnen - visibility:', computedStyle.visibility);
            this.debug('Nach oeffnen - opacity:', computedStyle.opacity);
            this.debug('Nach oeffnen - z-index:', computedStyle.zIndex);

            this.debug('Modal sollte jetzt sichtbar sein');
        },

        // Modal schliessen
        closeModal: function(modalId) {
            this.debug('Schliesse Modal:', modalId);
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                modal.style.cssText = '';

                var modalContent = modal.querySelector('.synnio-modal');
                if (modalContent) {
                    modalContent.style.cssText = '';
                }

                document.body.style.overflow = '';
            }
        },

        // Alle Modals schliessen
        closeAllModals: function() {
            this.debug('Schliesse alle Modals');
            var modals = document.querySelectorAll('.synnio-modal-overlay');
            var self = this;
            modals.forEach(function(modal) {
                modal.classList.remove('active');
                modal.style.cssText = '';

                var modalContent = modal.querySelector('.synnio-modal');
                if (modalContent) {
                    modalContent.style.cssText = '';
                }
            });
            document.body.style.overflow = '';
        },

        // Event Modal oeffnen
        openEventModal: function(date, hour) {
            this.debug('openEventModal aufgerufen, date:', date, 'hour:', hour);

            var modal = document.getElementById('synnioEventModal');
            if (!modal) {
                this.debug('FEHLER: Event Modal nicht gefunden');
                return;
            }

            // Form zuruecksetzen
            var form = document.getElementById('synnioEventForm');
            if (form) {
                form.reset();
            }

            // Event ID leeren (neuer Termin)
            var eventIdInput = document.getElementById('synnioEventId');
            if (eventIdInput) {
                eventIdInput.value = '';
            }

            // Delete Button verstecken
            var deleteBtn = document.getElementById('synnioDeleteEventBtn');
            if (deleteBtn) {
                deleteBtn.style.display = 'none';
            }

            // Titel setzen
            var titleEl = document.getElementById('synnioEventModalTitle');
            if (titleEl) {
                titleEl.textContent = 'Neuer Termin';
            }

            // Datum und Zeit vorbelegen
            var dateInput = document.getElementById('synnioEventDate');
            var startTimeInput = document.getElementById('synnioEventStartTime');
            var endTimeInput = document.getElementById('synnioEventEndTime');

            if (dateInput) {
                dateInput.value = date || this.formatDate(this.currentDate);
            }

            if (startTimeInput && hour !== undefined) {
                startTimeInput.value = hour.toString().padStart(2, '0') + ':00';
            }

            if (endTimeInput && hour !== undefined) {
                var endHour = parseInt(hour) + 1;
                endTimeInput.value = endHour.toString().padStart(2, '0') + ':00';
            }

            // Standard-Farbe vom ersten/ausgewaehlten Kalender
            var calendarSelect = document.getElementById('synnioEventCalendar');
            var defaultColor = '#3B82F6';
            if (calendarSelect && calendarSelect.options.length > 0) {
                var selectedOption = calendarSelect.options[calendarSelect.selectedIndex];
                if (selectedOption && selectedOption.dataset.color) {
                    defaultColor = selectedOption.dataset.color;
                }
            }
            this.selectColor(defaultColor);

            this.openModal('synnioEventModal');
        },

        // Event bearbeiten
        editEvent: function(eventId) {
            var self = this;
            var event = this.events.find(function(e) {
                return e.id == eventId;
            });

            if (!event) {
                this.debug('Event nicht gefunden:', eventId);
                return;
            }

            this.debug('Bearbeite Event:', event);

            // Titel setzen
            var titleEl = document.getElementById('synnioEventModalTitle');
            if (titleEl) {
                titleEl.textContent = 'Termin bearbeiten';
            }

            // Event ID
            var eventIdInput = document.getElementById('synnioEventId');
            if (eventIdInput) {
                eventIdInput.value = eventId;
            }

            // Titel
            var titleInput = document.getElementById('synnioEventTitle');
            if (titleInput) {
                titleInput.value = event.title || '';
            }

            // Datum und Zeit aus start/end extrahieren
            var startDate = new Date(event.start || event.start_time);
            var endDate = new Date(event.end || event.end_time);

            var dateInput = document.getElementById('synnioEventDate');
            if (dateInput) {
                dateInput.value = this.formatDate(startDate);
            }

            var startTimeInput = document.getElementById('synnioEventStartTime');
            if (startTimeInput) {
                startTimeInput.value = startDate.getHours().toString().padStart(2, '0') + ':' + startDate.getMinutes().toString().padStart(2, '0');
            }

            var endTimeInput = document.getElementById('synnioEventEndTime');
            if (endTimeInput) {
                endTimeInput.value = endDate.getHours().toString().padStart(2, '0') + ':' + endDate.getMinutes().toString().padStart(2, '0');
            }

            // Kalender (calendarId von AJAX, calendar_id als Fallback)
            var calendarSelect = document.getElementById('synnioEventCalendar');
            var eventCalId = event.calendarId || event.calendar_id;
            if (calendarSelect && eventCalId) {
                calendarSelect.value = eventCalId;
            }

            // Event-Typ
            var eventTypeSelect = document.getElementById('synnioEventType');
            if (eventTypeSelect && (event.type || event.event_type)) {
                eventTypeSelect.value = event.type || event.event_type;
            }

            // Beschreibung
            var descInput = document.getElementById('synnioEventDescription');
            if (descInput) {
                descInput.value = event.description || '';
            }

            // Farbe - bevorzuge Event-Farbe, sonst Kalender-Farbe
            var colorToUse = event.color;
            if (!colorToUse && calendarSelect && eventCalId) {
                var selectedOption = calendarSelect.options[calendarSelect.selectedIndex];
                if (selectedOption && selectedOption.dataset.color) {
                    colorToUse = selectedOption.dataset.color;
                }
            }
            if (colorToUse) {
                this.selectColor(colorToUse);
            }

            // Ganztaegig
            var allDayCheckbox = document.getElementById('synnioEventAllDay');
            if (allDayCheckbox) {
                allDayCheckbox.checked = event.all_day == 1 || event.all_day === true;
            }

            // Verfuegbar
            var availableCheckbox = document.getElementById('synnioEventAvailable');
            if (availableCheckbox) {
                availableCheckbox.checked = event.is_available == 1 || event.is_available === true;
            }

            // Delete Button anzeigen
            var deleteBtn = document.getElementById('synnioDeleteEventBtn');
            if (deleteBtn) {
                deleteBtn.style.display = 'block';
            }

            this.openModal('synnioEventModal');
        },

        // Event loeschen
        deleteEvent: function() {
            var self = this;
            var eventIdInput = document.getElementById('synnioEventId');
            var eventId = eventIdInput ? eventIdInput.value : '';

            if (!eventId) {
                alert('Keine Event-ID vorhanden');
                return;
            }

            if (!confirm('Moechten Sie diesen Termin wirklich loeschen?')) {
                return;
            }

            this.debug('Loesche Event:', eventId);

            if (typeof synnioCalendar === 'undefined') {
                this.debug('synnioCalendar nicht definiert');
                alert('Termin geloescht (Demo-Modus)');
                this.closeAllModals();
                return;
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_delete_event');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('event_id', eventId);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('Delete Response:', response);
                        if (response.success) {
                            alert('Termin erfolgreich geloescht!');
                            self.closeAllModals();
                            self.loadEvents();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Loeschen');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        alert('Fehler bei der Verarbeitung');
                    }
                } else {
                    alert('Server-Fehler: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                alert('Netzwerkfehler');
            };

            xhr.send(formData);
        },

        // Farbe auswaehlen
        selectColor: function(color) {
            this.debug('Farbe ausgewaehlt:', color);

            // Alle Color Options deselektieren
            var colorOptions = document.querySelectorAll('.synnio-color-option');
            colorOptions.forEach(function(opt) {
                opt.classList.remove('selected');
            });

            // Ausgewaehlte Option markieren
            var selectedOption = document.querySelector('.synnio-color-option[data-color="' + color + '"]');
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }

            // Hidden Input aktualisieren
            var colorInput = document.getElementById('synnioEventColor');
            if (colorInput) {
                colorInput.value = color;
            }
        },

        // Kalender Farb-Synchronisation initialisieren
        // Wenn Kalender ausgewaehlt wird, wird automatisch die Kalender-Farbe uebernommen
        initCalendarColorSync: function() {
            var self = this;
            var calendarSelect = document.getElementById('synnioEventCalendar');

            if (!calendarSelect) return;

            calendarSelect.addEventListener('change', function() {
                var selectedCalendarId = calendarSelect.value;
                if (!selectedCalendarId) return;

                // Farbe aus der Option abrufen (data-color Attribut)
                var selectedOption = calendarSelect.options[calendarSelect.selectedIndex];
                var color = selectedOption.dataset.color;

                // Fallback: Farbe aus der Sidebar holen
                if (!color) {
                    var sidebarItem = document.querySelector('.synnio-calendar-type-item[data-calendar-id="' + selectedCalendarId + '"]');
                    if (sidebarItem) {
                        var colorSpan = sidebarItem.querySelector('.synnio-calendar-color');
                        if (colorSpan) {
                            color = colorSpan.style.background || colorSpan.style.backgroundColor;
                        }
                    }
                }

                // Fallback: Farbe aus calendars Array
                if (!color && self.calendars) {
                    var calendar = self.calendars.find(function(c) {
                        return c.id == selectedCalendarId;
                    });
                    if (calendar && calendar.color) {
                        color = calendar.color;
                    }
                }

                // Standard-Farbe falls nichts gefunden
                if (!color) {
                    color = '#3B82F6';
                }

                self.selectColor(color);
                self.debug('Farbe automatisch geaendert zu:', color, 'fuer Kalender:', selectedCalendarId);
            });
        },

        // Kalender-Toggle Funktionalitaet initialisieren
        initCalendarToggles: function() {
            var self = this;

            // Alle aktuell angehakten Kalender erfassen
            var toggles = document.querySelectorAll('.synnio-calendar-toggle');
            this.selectedCalendars = [];

            toggles.forEach(function(toggle) {
                if (toggle.checked) {
                    self.selectedCalendars.push(toggle.dataset.calendarId);
                }

                // Change Event Handler
                toggle.addEventListener('change', function() {
                    var calendarId = toggle.dataset.calendarId;

                    if (toggle.checked) {
                        // Kalender hinzufuegen
                        if (self.selectedCalendars.indexOf(calendarId) === -1) {
                            self.selectedCalendars.push(calendarId);
                        }
                    } else {
                        // Kalender entfernen
                        var index = self.selectedCalendars.indexOf(calendarId);
                        if (index > -1) {
                            self.selectedCalendars.splice(index, 1);
                        }
                    }

                    self.debug('Kalender Toggle:', calendarId, 'Aktiv:', toggle.checked);
                    self.debug('Ausgewaehlte Kalender:', self.selectedCalendars);

                    // Kalender neu rendern um Events zu filtern
                    self.renderCalendar();
                });
            });

            this.debug('Kalender-Toggles initialisiert, ausgewaehlt:', this.selectedCalendars);
        },

        // Kalender-Zaehler aktualisieren
        updateCalendarCounts: function() {
            var self = this;

            // Alle Zaehler auf 0 setzen
            var counters = document.querySelectorAll('.synnio-calendar-type-count');
            counters.forEach(function(counter) {
                counter.textContent = '0';
            });

            // Events zaehlen pro Kalender
            if (!this.events || this.events.length === 0) return;

            var counts = {};
            this.events.forEach(function(event) {
                var calId = event.calendarId || event.calendar_id;
                if (calId) {
                    counts[calId] = (counts[calId] || 0) + 1;
                }
            });

            // Zaehler aktualisieren
            for (var calId in counts) {
                var counter = document.querySelector('.synnio-calendar-type-count[data-calendar-id="' + calId + '"]');
                if (counter) {
                    counter.textContent = counts[calId];
                }
            }

            this.debug('Kalender-Zaehler aktualisiert:', counts);
        },

        // Neuen Kalender hinzufuegen Modal
        openAddCalendarModal: function() {
            var calendarName = prompt('Name des neuen Kalenders:', '');

            if (!calendarName || !calendarName.trim()) {
                return;
            }

            var self = this;

            if (typeof synnioCalendar === 'undefined') {
                alert('Kalender erstellt (Demo-Modus)');
                return;
            }

            // Farbe auswaehlen
            var colors = ['#3B82F6', '#10B981', '#8B5CF6', '#EF4444', '#F59E0B', '#EC4899', '#6366F1'];
            var color = colors[Math.floor(Math.random() * colors.length)];

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_create_calendar');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('name', calendarName.trim());
            formData.append('color', color);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('Create Calendar Response:', response);
                        if (response.success) {
                            alert('Kalender "' + calendarName + '" erfolgreich erstellt!');
                            // Seite neu laden um neuen Kalender anzuzeigen
                            location.reload();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Erstellen');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        alert('Fehler bei der Verarbeitung');
                    }
                } else {
                    alert('Server-Fehler: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                alert('Netzwerkfehler');
            };

            xhr.send(formData);
        },

        // Kalender bearbeiten - Modal oeffnen
        openEditCalendarModal: function(calendarId, calendarName, calendarColor, isDefault) {
            this.debug('openEditCalendarModal:', calendarId, calendarName, calendarColor);

            document.getElementById('synnioEditCalendarId').value = calendarId;
            document.getElementById('synnioEditCalendarName').value = calendarName;
            document.getElementById('synnioEditCalendarColor').value = calendarColor || '#3B82F6';

            // Farboption vorauswaehlen
            var modal = document.getElementById('synnioEditCalendarModal');
            modal.querySelectorAll('.synnio-color-option').forEach(function(opt) {
                opt.classList.remove('selected');
                if (opt.dataset.color === calendarColor) {
                    opt.classList.add('selected');
                }
            });

            // Loeschen-Button immer anzeigen
            var deleteBtn = document.getElementById('synnioDeleteCalendarBtn');
            if (deleteBtn) {
                deleteBtn.style.display = '';
            }

            this.openModal('synnioEditCalendarModal');
        },

        // Kalender speichern (umbenennen/Farbe aendern)
        saveCalendarEdit: function() {
            var self = this;
            var calendarId = document.getElementById('synnioEditCalendarId').value;
            var newName = document.getElementById('synnioEditCalendarName').value.trim();
            var newColor = document.getElementById('synnioEditCalendarColor').value;

            if (!newName) {
                alert('Bitte einen Namen eingeben');
                return;
            }

            if (typeof synnioCalendar === 'undefined') {
                alert('Kalender aktualisiert (Demo-Modus)');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_update_calendar');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('calendar_id', calendarId);
            formData.append('name', newName);
            if (newColor) {
                formData.append('color', newColor);
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('Update Calendar Response:', response);
                        if (response.success) {
                            // Name im DOM aktualisieren
                            var nameSpan = document.querySelector('.synnio-calendar-type-name[data-calendar-id="' + calendarId + '"]');
                            if (nameSpan) {
                                nameSpan.textContent = newName;
                            }
                            // Farbe im DOM aktualisieren
                            if (newColor) {
                                var colorSpan = nameSpan ? nameSpan.closest('.synnio-calendar-type-item') : null;
                                if (colorSpan) {
                                    var colorDot = colorSpan.querySelector('.synnio-calendar-color');
                                    if (colorDot) {
                                        colorDot.style.background = newColor;
                                    }
                                }
                            }
                            // Edit-Button Data aktualisieren
                            var editBtn = document.querySelector('.synnio-calendar-edit-btn[data-calendar-id="' + calendarId + '"]');
                            if (editBtn) {
                                editBtn.dataset.calendarName = newName;
                                if (newColor) editBtn.dataset.calendarColor = newColor;
                            }
                            // Event-Farben im Speicher aktualisieren und neu rendern
                            if (newColor && self.events && self.events.length) {
                                self.events.forEach(function(ev) {
                                    if (String(ev.calendarId) === String(calendarId)) {
                                        ev.color = newColor;
                                        ev.backgroundColor = newColor;
                                        ev.borderColor = newColor;
                                    }
                                });
                                self.renderCalendar();
                            }
                            // Kalender-Select im Event-Modal aktualisieren
                            var calOption = document.querySelector('#synnioEventCalendar option[value="' + calendarId + '"]');
                            if (calOption) {
                                calOption.textContent = newName;
                                if (newColor) calOption.dataset.color = newColor;
                            }
                            self.closeModal('synnioEditCalendarModal');
                            self.debug('Kalender umbenannt zu "' + newName + '"');
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Aktualisieren');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        alert('Fehler bei der Verarbeitung');
                    }
                } else {
                    alert('Server-Fehler: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                alert('Netzwerkfehler');
            };

            xhr.send(formData);
        },

        // Kalender loeschen
        deleteCalendar: function() {
            var self = this;
            var calendarId = document.getElementById('synnioEditCalendarId').value;
            var calendarName = document.getElementById('synnioEditCalendarName').value;

            if (!confirm('Kalender "' + calendarName + '" wirklich loeschen?\nAlle Termine in diesem Kalender werden ebenfalls geloescht!')) {
                return;
            }

            if (typeof synnioCalendar === 'undefined') {
                alert('Kalender geloescht (Demo-Modus)');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_delete_calendar');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('calendar_id', calendarId);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('Delete Calendar Response:', response);
                        if (response.success) {
                            self.closeModal('synnioEditCalendarModal');
                            // Kalender-Item aus DOM entfernen
                            var item = document.querySelector('.synnio-calendar-type-item[data-calendar-id="' + calendarId + '"]');
                            if (item) {
                                item.remove();
                            }
                            // Events neu laden
                            self.loadEvents();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Loeschen');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        alert('Fehler bei der Verarbeitung');
                    }
                } else {
                    alert('Server-Fehler: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                alert('Netzwerkfehler');
            };

            xhr.send(formData);
        },

        // Settings Tabs initialisieren
        initSettingsTabs: function() {
            var settingsModal = document.getElementById('synnioSettingsModal');
            if (!settingsModal) return;

            var tabs = settingsModal.querySelectorAll('.synnio-tab');
            var contents = settingsModal.querySelectorAll('.synnio-tab-content');

            if (tabs.length > 0 && contents.length > 0) {
                var hasActive = false;
                tabs.forEach(function(tab) {
                    if (tab.classList.contains('active')) {
                        hasActive = true;
                    }
                });

                if (!hasActive) {
                    tabs[0].classList.add('active');
                }

                contents.forEach(function(content, index) {
                    if (index === 0) {
                        content.classList.add('active');
                        content.style.display = 'block';
                    } else {
                        content.classList.remove('active');
                        content.style.display = 'none';
                    }
                });
            }
        },

        // Settings Tab wechseln
        switchSettingsTab: function(tabElement) {
            var tabName = tabElement.dataset.tab;
            this.debug('Switch Settings Tab zu:', tabName);

            var container = tabElement.closest('.synnio-modal-body') || tabElement.closest('.synnio-modal');
            if (!container) {
                container = document;
            }

            // Alle Tabs deaktivieren
            var tabs = container.querySelectorAll('.synnio-tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });

            // Alle Tab-Contents verstecken
            var contents = container.querySelectorAll('.synnio-tab-content');
            contents.forEach(function(content) {
                content.classList.remove('active');
                content.style.display = 'none';
            });

            // Aktiven Tab setzen
            tabElement.classList.add('active');

            // Tab-Content anzeigen (synnioTabGeneral, synnioTabSync, synnioTabApi)
            var tabId = 'synnioTab' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
            var tabContent = document.getElementById(tabId);

            if (tabContent) {
                tabContent.classList.add('active');
                tabContent.style.display = 'block';
                this.debug('Tab Content aktiviert:', tabId);
            } else {
                this.debug('FEHLER: Tab Content nicht gefunden:', tabId);
            }
        },

        // View aendern
        changeView: function(view) {
            this.debug('View aendern zu:', view);
            this.currentView = view;
            this.setActiveViewButton();
            this.renderCalendar();
        },

        setActiveViewButton: function() {
            var viewBtns = document.querySelectorAll('.synnio-view-btn');
            var self = this;
            viewBtns.forEach(function(btn) {
                btn.classList.remove('active');
                if (btn.dataset.view === self.currentView) {
                    btn.classList.add('active');
                }
            });
        },

        // Heute
        goToToday: function() {
            this.debug('Gehe zu Heute');
            this.currentDate = new Date();
            this.renderCalendar();
            this.renderMiniCalendar();
            this.updateCurrentDate();
        },

        // Navigation
        navigatePrev: function() {
            this.debug('Navigate Prev');
            if (this.currentView === 'day') {
                this.currentDate.setDate(this.currentDate.getDate() - 1);
            } else if (this.currentView === 'week') {
                this.currentDate.setDate(this.currentDate.getDate() - 7);
            } else if (this.currentView === 'month') {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            }
            this.renderCalendar();
            this.renderMiniCalendar();
            this.updateCurrentDate();
        },

        navigateNext: function() {
            this.debug('Navigate Next');
            if (this.currentView === 'day') {
                this.currentDate.setDate(this.currentDate.getDate() + 1);
            } else if (this.currentView === 'week') {
                this.currentDate.setDate(this.currentDate.getDate() + 7);
            } else if (this.currentView === 'month') {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            }
            this.renderCalendar();
            this.renderMiniCalendar();
            this.updateCurrentDate();
        },

        miniCalendarPrev: function() {
            this.debug('Mini Calendar Prev');
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.renderMiniCalendar();
            this.updateCurrentDate();
        },

        miniCalendarNext: function() {
            this.debug('Mini Calendar Next');
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.renderMiniCalendar();
            this.updateCurrentDate();
        },

        selectDate: function(dateStr) {
            this.debug('Select Date:', dateStr);
            if (dateStr) {
                this.currentDate = new Date(dateStr);
                this.renderCalendar();
                this.renderMiniCalendar();
                this.updateCurrentDate();
            }
        },

        updateCurrentDate: function() {
            var monthNames = ['Januar', 'Februar', 'Maerz', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

            var dateEl = document.getElementById('synnioCurrentDate');
            if (dateEl) {
                dateEl.textContent = monthNames[this.currentDate.getMonth()] + ' ' + this.currentDate.getFullYear();
            }

            var miniCalTitle = document.getElementById('synnioMiniCalTitle');
            if (miniCalTitle) {
                miniCalTitle.textContent = monthNames[this.currentDate.getMonth()] + ' ' + this.currentDate.getFullYear();
            }
        },

        updateCurrentTimeLine: function() {
            var existingLines = document.querySelectorAll('.synnio-current-time-line');
            existingLines.forEach(function(line) {
                var now = new Date();
                var minutes = now.getHours() * 60 + now.getMinutes();
                var top = (minutes / 60) * 60;
                line.style.top = top + 'px';
            });
        },

        // Kalender rendern
        renderCalendar: function() {
            this.debug('Render Kalender, View:', this.currentView);

            if (this.currentView === 'week') {
                this.renderWeekView();
            } else if (this.currentView === 'day') {
                this.renderDayView();
            } else if (this.currentView === 'month') {
                this.renderMonthView();
            } else if (this.currentView === 'agenda') {
                this.renderAgendaView();
            }
        },

        // Mini-Kalender rendern
        renderMiniCalendar: function() {
            var grid = document.getElementById('synnioMiniCalGrid');
            if (!grid) {
                this.debug('Mini Calendar Grid nicht gefunden');
                return;
            }

            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth();
            var today = new Date();
            var self = this;

            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var startDay = firstDay.getDay() || 7;

            var html = '';

            // Tage der Woche
            var dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
            dayNames.forEach(function(day) {
                html += '<div class="synnio-mini-cal-day-name">' + day + '</div>';
            });

            // Leere Zellen vor dem ersten Tag
            for (var i = 1; i < startDay; i++) {
                var prevDate = new Date(year, month, 1 - (startDay - i));
                html += '<div class="synnio-mini-cal-day other-month" data-date="' + self.formatDate(prevDate) + '">' + prevDate.getDate() + '</div>';
            }

            // Tage des Monats
            for (var d = 1; d <= lastDay.getDate(); d++) {
                var date = new Date(year, month, d);
                var classes = ['synnio-mini-cal-day'];

                if (date.toDateString() === today.toDateString()) {
                    classes.push('today');
                }

                if (date.toDateString() === this.currentDate.toDateString()) {
                    classes.push('selected');
                }

                html += '<div class="' + classes.join(' ') + '" data-date="' + self.formatDate(date) + '">' + d + '</div>';
            }

            // Leere Zellen nach dem letzten Tag
            var remainingDays = 7 - ((startDay - 1 + lastDay.getDate()) % 7);
            if (remainingDays < 7) {
                for (var j = 1; j <= remainingDays; j++) {
                    var nextDate = new Date(year, month + 1, j);
                    html += '<div class="synnio-mini-cal-day other-month" data-date="' + self.formatDate(nextDate) + '">' + j + '</div>';
                }
            }

            grid.innerHTML = html;
        },

        // Wochenansicht rendern
        renderWeekView: function() {
            var container = document.getElementById('synnioCalendarView');
            if (!container) {
                this.debug('FEHLER: synnioCalendarView nicht gefunden');
                return;
            }

            var weekStart = this.getWeekStart(this.currentDate);
            var today = new Date();
            var self = this;
            var dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

            var html = '<div class="synnio-week-view">';

            // Header
            html += '<div class="synnio-week-header" id="synnioWeekHeader">';
            html += '<div class="synnio-week-header-cell"></div>';

            for (var i = 0; i < 7; i++) {
                var date = new Date(weekStart);
                date.setDate(date.getDate() + i);
                var isToday = date.toDateString() === today.toDateString();

                html += '<div class="synnio-week-header-cell' + (isToday ? ' today' : '') + '">';
                html += '<div class="synnio-day-name">' + dayNames[i] + '</div>';
                html += '<div class="synnio-day-number">' + date.getDate() + '</div>';
                html += '</div>';
            }

            html += '</div>';

            // Anzeigebereich berechnen
            var dispRange = self.getDisplayRange();
            var dispStartHour = dispRange.startHour;
            var dispEndHour = dispRange.endHour;
            var dispStartMin = dispRange.startMin;

            // Body
            html += '<div class="synnio-week-body" id="synnioWeekBody">';

            // Zeit-Spalte
            html += '<div class="synnio-time-column">';
            for (var h = dispStartHour; h < dispEndHour; h++) {
                html += '<div class="synnio-time-slot-label">' + h.toString().padStart(2, '0') + ':00</div>';
            }
            html += '</div>';

            // Tages-Spalten
            for (var d = 0; d < 7; d++) {
                var dayDate = new Date(weekStart);
                dayDate.setDate(dayDate.getDate() + d);
                var isDayToday = dayDate.toDateString() === today.toDateString();

                html += '<div class="synnio-day-column" data-date="' + self.formatDate(dayDate) + '">';

                for (var hour = dispStartHour; hour < dispEndHour; hour++) {
                    html += '<div class="synnio-time-slot" data-hour="' + hour + '"></div>';
                }

                // Aktuelle Zeitlinie
                if (isDayToday) {
                    var now = new Date();
                    var minutes = now.getHours() * 60 + now.getMinutes();
                    if (minutes >= dispStartMin && minutes < dispEndHour * 60) {
                        var top = minutes - dispStartMin;
                        html += '<div class="synnio-current-time-line" style="top: ' + top + 'px;"></div>';
                    }
                }

                // Oeffnungszeiten- und Pausen-Overlays
                html += self.renderTimeOverlays(dayDate.getDay());

                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            container.innerHTML = html;
            this.renderEvents();
            this.debug('Wochenansicht gerendert');
        },

        // Tagesansicht
        renderDayView: function() {
            var container = document.getElementById('synnioCalendarView');
            if (!container) return;

            var today = new Date();
            var isToday = this.currentDate.toDateString() === today.toDateString();
            var dayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            var self = this;

            var html = '<div class="synnio-week-view">';

            html += '<div class="synnio-week-header" style="grid-template-columns: 60px 1fr;">';
            html += '<div class="synnio-week-header-cell"></div>';
            html += '<div class="synnio-week-header-cell' + (isToday ? ' today' : '') + '">';
            html += '<div class="synnio-day-name">' + dayNames[this.currentDate.getDay()] + '</div>';
            html += '<div class="synnio-day-number">' + this.currentDate.getDate() + '</div>';
            html += '</div>';
            html += '</div>';

            // Anzeigebereich berechnen
            var dispRange = self.getDisplayRange();
            var dispStartHour = dispRange.startHour;
            var dispEndHour = dispRange.endHour;
            var dispStartMin = dispRange.startMin;

            html += '<div class="synnio-week-body" style="grid-template-columns: 60px 1fr;">';

            html += '<div class="synnio-time-column">';
            for (var h = dispStartHour; h < dispEndHour; h++) {
                html += '<div class="synnio-time-slot-label">' + h.toString().padStart(2, '0') + ':00</div>';
            }
            html += '</div>';

            html += '<div class="synnio-day-column" data-date="' + self.formatDate(this.currentDate) + '">';
            for (var hour = dispStartHour; hour < dispEndHour; hour++) {
                html += '<div class="synnio-time-slot" data-hour="' + hour + '"></div>';
            }

            if (isToday) {
                var now = new Date();
                var minutes = now.getHours() * 60 + now.getMinutes();
                if (minutes >= dispStartMin && minutes < dispEndHour * 60) {
                    var top = minutes - dispStartMin;
                    html += '<div class="synnio-current-time-line" style="top: ' + top + 'px;"></div>';
                }
            }

            // Oeffnungszeiten- und Pausen-Overlays
            html += self.renderTimeOverlays(this.currentDate.getDay());

            html += '</div>';
            html += '</div>';
            html += '</div>';

            container.innerHTML = html;
            this.renderEvents();
        },

        // Monatsansicht
        renderMonthView: function() {
            var container = document.getElementById('synnioCalendarView');
            if (!container) return;

            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth();
            var today = new Date();
            var self = this;

            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var startDay = firstDay.getDay() || 7;

            var dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

            var html = '<div class="synnio-month-view" style="height: 100%; display: flex; flex-direction: column;">';

            html += '<div class="synnio-month-header" style="display: grid; grid-template-columns: repeat(7, 1fr); background: #F9FAFB; border-bottom: 1px solid #E5E7EB;">';
            dayNames.forEach(function(day) {
                html += '<div style="padding: 12px; text-align: center; font-weight: 600; font-size: 12px; color: #6B7280;">' + day + '</div>';
            });
            html += '</div>';

            html += '<div class="synnio-month-grid" style="flex: 1; display: grid; grid-template-columns: repeat(7, 1fr); grid-template-rows: repeat(6, 1fr);">';

            for (var i = 1; i < startDay; i++) {
                var prevDate = new Date(year, month, 1 - (startDay - i));
                html += '<div class="synnio-month-day other-month" style="padding: 8px; border: 1px solid #E5E7EB; color: #D1D5DB;">';
                html += '<span style="font-weight: 600;">' + prevDate.getDate() + '</span>';
                html += '</div>';
            }

            for (var d = 1; d <= lastDay.getDate(); d++) {
                var date = new Date(year, month, d);
                var isToday = date.toDateString() === today.toDateString();

                html += '<div class="synnio-month-day" data-date="' + self.formatDate(date) + '" style="padding: 8px; border: 1px solid #E5E7EB; cursor: pointer;' + (isToday ? ' background: #EEF2FF;' : '') + '">';
                html += '<span style="font-weight: 600; display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; border-radius: 50%;' + (isToday ? ' background: #0F0B45; color: #FFFFFF;' : '') + '">' + d + '</span>';
                html += '<div class="synnio-month-day-events" id="monthEvents' + self.formatDate(date) + '"></div>';
                html += '</div>';
            }

            var totalCells = startDay - 1 + lastDay.getDate();
            var remainingCells = 42 - totalCells;
            for (var j = 1; j <= remainingCells; j++) {
                var nextDate = new Date(year, month + 1, j);
                html += '<div class="synnio-month-day other-month" style="padding: 8px; border: 1px solid #E5E7EB; color: #D1D5DB;">';
                html += '<span style="font-weight: 600;">' + j + '</span>';
                html += '</div>';
            }

            html += '</div>';
            html += '</div>';

            container.innerHTML = html;
            this.renderMonthEvents();
        },

        // Agenda Ansicht
        renderAgendaView: function() {
            var container = document.getElementById('synnioCalendarView');
            if (!container) return;

            var self = this;
            var html = '<div class="synnio-agenda-view" style="padding: 20px; height: 100%; overflow-y: auto;">';
            html += '<h3 style="margin-bottom: 20px; color: #0F0B45;">Kommende Termine</h3>';

            // Events filtern nach ausgewaehlten Kalendern (calendarId von AJAX, calendar_id als Fallback)
            var filteredEvents = this.events.filter(function(event) {
                var eventCalendarId = String(event.calendarId || event.calendar_id || '');
                if (self.selectedCalendars && self.selectedCalendars.length > 0) {
                    if (eventCalendarId && self.selectedCalendars.indexOf(eventCalendarId) === -1) {
                        return false;
                    }
                }
                return true;
            });

            if (filteredEvents.length === 0) {
                html += '<div style="text-align: center; padding: 40px; color: #6B7280;">Keine Termine vorhanden</div>';
            } else {
                var sortedEvents = filteredEvents.slice().sort(function(a, b) {
                    return new Date(a.start || a.start_time) - new Date(b.start || b.start_time);
                });

                sortedEvents.forEach(function(event) {
                    var startDate = new Date(event.start || event.start_time);
                    var endDate = new Date(event.end || event.end_time);

                    html += '<div class="synnio-agenda-item" style="display: flex; gap: 16px; padding: 16px; background: #FFFFFF; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                    html += '<div style="width: 4px; border-radius: 2px; background: ' + (event.color || '#3B82F6') + ';"></div>';
                    html += '<div style="flex: 1;">';
                    html += '<div style="font-weight: 600; color: #374151; margin-bottom: 4px;">' + self.escapeHtml(event.title) + '</div>';
                    html += '<div style="font-size: 13px; color: #6B7280;">';
                    html += self.formatDateLong(startDate) + ' - ' + self.formatTime(startDate) + ' bis ' + self.formatTime(endDate);
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
            }

            html += '</div>';
            container.innerHTML = html;
        },

        // Events rendern
        renderEvents: function() {
            var self = this;

            // 1. Sichtbare Events nach Tages-Spalte gruppieren
            var dayGroups = {};
            this.events.forEach(function(event) {
                var eventCalendarId = String(event.calendarId || event.calendar_id || '');
                if (self.selectedCalendars && self.selectedCalendars.length > 0) {
                    if (eventCalendarId && self.selectedCalendars.indexOf(eventCalendarId) === -1) {
                        return;
                    }
                }
                var startDate = new Date(event.start || event.start_time);
                var dateStr = self.formatDate(startDate);
                if (!dayGroups[dateStr]) {
                    dayGroups[dateStr] = [];
                }
                dayGroups[dateStr].push(event);
            });

            // 2. Fuer jede Tages-Spalte Ueberlappungen berechnen und rendern
            Object.keys(dayGroups).forEach(function(dateStr) {
                var eventsForDay = dayGroups[dateStr];
                var layoutEvents = self.calculateParallelLayout(eventsForDay);
                layoutEvents.forEach(function(layoutEvt) {
                    self.renderEvent(layoutEvt.event, layoutEvt.colIndex, layoutEvt.totalCols, dateStr);
                });
            });
        },

        // Parallele Anordnung berechnen (Google Calendar Stil)
        // Gibt Array von {event, colIndex, totalCols} zurueck
        calculateParallelLayout: function(events) {
            var self = this;

            // Events mit Start/End-Minuten anreichern und sortieren
            var items = events.map(function(event) {
                var startDate = new Date(event.start || event.start_time);
                var endDate = new Date(event.end || event.end_time);
                var startMin = startDate.getHours() * 60 + startDate.getMinutes();
                var endMin = endDate.getHours() * 60 + endDate.getMinutes();
                // Mindesthoehe 30 Minuten fuer Ueberlappungserkennung
                if (endMin <= startMin) endMin = startMin + 30;
                return { event: event, startMin: startMin, endMin: endMin, colIndex: -1, group: -1 };
            });

            // Nach Startzeit sortieren, bei Gleichheit nach Endzeit (laengere zuerst)
            items.sort(function(a, b) {
                if (a.startMin !== b.startMin) return a.startMin - b.startMin;
                return b.endMin - a.endMin;
            });

            // Ueberlappungsgruppen finden und Spalten zuweisen
            // Algorithmus: Greedy Column Assignment
            var columns = []; // Array von endMin-Werten pro Spalte

            items.forEach(function(item) {
                // Freie Spalte finden (erste Spalte deren letztes Event vor diesem startet)
                var placed = false;
                for (var c = 0; c < columns.length; c++) {
                    if (columns[c] <= item.startMin) {
                        columns[c] = item.endMin;
                        item.colIndex = c;
                        placed = true;
                        break;
                    }
                }
                if (!placed) {
                    item.colIndex = columns.length;
                    columns.push(item.endMin);
                }
            });

            // Ueberlappungsgruppen bilden um totalCols korrekt zu berechnen
            // Zwei Events sind in der gleichen Gruppe wenn sie direkt oder transitiv ueberlappen
            var groups = self.findOverlapGroups(items);

            // Ergebnis mit totalCols pro Gruppe
            var result = [];
            groups.forEach(function(group) {
                // Maximale Spalte in dieser Gruppe = totalCols
                var maxCol = 0;
                group.forEach(function(item) {
                    if (item.colIndex > maxCol) maxCol = item.colIndex;
                });
                var totalCols = maxCol + 1;

                group.forEach(function(item) {
                    result.push({
                        event: item.event,
                        colIndex: item.colIndex,
                        totalCols: totalCols
                    });
                });
            });

            return result;
        },

        // Zusammenhaengende Ueberlappungsgruppen finden
        findOverlapGroups: function(items) {
            if (items.length === 0) return [];

            var groups = [];
            var currentGroup = [items[0]];
            var groupEnd = items[0].endMin;

            for (var i = 1; i < items.length; i++) {
                if (items[i].startMin < groupEnd) {
                    // Ueberlappt mit der aktuellen Gruppe
                    currentGroup.push(items[i]);
                    if (items[i].endMin > groupEnd) {
                        groupEnd = items[i].endMin;
                    }
                } else {
                    // Neue Gruppe beginnen
                    groups.push(currentGroup);
                    currentGroup = [items[i]];
                    groupEnd = items[i].endMin;
                }
            }
            groups.push(currentGroup);

            return groups;
        },

        renderEvent: function(event, colIndex, totalCols, dateStr) {
            // Fallback fuer Einzelaufruf ohne Layout-Parameter
            if (typeof colIndex === 'undefined') colIndex = 0;
            if (typeof totalCols === 'undefined') totalCols = 1;

            // Kalender-Filter (nur wenn direkt aufgerufen, nicht ueber renderEvents)
            if (!dateStr) {
                var eventCalendarId = String(event.calendarId || event.calendar_id || '');
                if (this.selectedCalendars && this.selectedCalendars.length > 0) {
                    if (eventCalendarId && this.selectedCalendars.indexOf(eventCalendarId) === -1) {
                        return;
                    }
                }
            }

            var startDate = new Date(event.start || event.start_time);
            var endDate = new Date(event.end || event.end_time);
            if (!dateStr) dateStr = this.formatDate(startDate);

            var column = document.querySelector('.synnio-day-column[data-date="' + dateStr + '"]');
            if (!column) return;

            var startHour = startDate.getHours();
            var startMinutes = startDate.getMinutes();
            var endHour = endDate.getHours();
            var endMinutes = endDate.getMinutes();

            // Offset fuer konfigurierbaren Anzeigebereich
            var dispRange = this.getDisplayRange();
            var top = (startHour * 60 + startMinutes) - dispRange.startMin;
            var height = ((endHour - startHour) * 60 + (endMinutes - startMinutes));

            // Event ausserhalb des Anzeigebereichs nicht rendern
            if (top + height <= 0 || top >= (dispRange.endHour - dispRange.startHour) * 60) return;
            // Event das ueber den Rand hinausgeht beschneiden
            if (top < 0) { height += top; top = 0; }

            // Parallele Breite und Position berechnen
            var padding = 2; // px Abstand zwischen parallelen Events
            var widthPercent = (100 / totalCols);
            var leftPercent = (colIndex * widthPercent);

            var eventEl = document.createElement('div');
            eventEl.className = 'synnio-event synnio-event-' + (event.type || event.event_type || 'meeting');
            eventEl.style.top = top + 'px';
            eventEl.style.height = Math.max(height, 30) + 'px';
            eventEl.style.setProperty('background', event.color || '#3B82F6', 'important');
            eventEl.dataset.eventId = event.id;

            // Parallele Positionierung: left und width statt left:4px/right:4px
            if (totalCols > 1) {
                eventEl.style.left = 'calc(' + leftPercent + '% + ' + padding + 'px)';
                eventEl.style.width = 'calc(' + widthPercent + '% - ' + (padding * 2) + 'px)';
                eventEl.style.right = 'auto';
            }

            eventEl.innerHTML = '<div class="synnio-event-title">' + this.escapeHtml(event.title) + '</div>' +
                               '<div class="synnio-event-time">' + this.formatTime(startDate) + ' - ' + this.formatTime(endDate) + '</div>';

            column.appendChild(eventEl);
        },

        renderMonthEvents: function() {
            var self = this;
            this.events.forEach(function(event) {
                // Pruefen ob Kalender ausgewaehlt ist (calendarId von AJAX, calendar_id als Fallback)
                var eventCalendarId = String(event.calendarId || event.calendar_id || '');
                if (self.selectedCalendars && self.selectedCalendars.length > 0) {
                    if (eventCalendarId && self.selectedCalendars.indexOf(eventCalendarId) === -1) {
                        return;
                    }
                }

                var startDate = new Date(event.start || event.start_time);
                var dateStr = self.formatDate(startDate);
                var container = document.getElementById('monthEvents' + dateStr);

                if (container) {
                    var eventEl = document.createElement('div');
                    eventEl.style.cssText = 'font-size: 11px; padding: 2px 4px; margin-top: 2px; border-radius: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #FFFFFF; background: ' + (event.color || '#3B82F6') + ';';
                    eventEl.textContent = event.title;
                    container.appendChild(eventEl);
                }
            });
        },

        // Hilfsfunktionen
        getWeekStart: function(date) {
            var d = new Date(date);
            var day = d.getDay() || 7;
            d.setDate(d.getDate() - day + 1);
            return d;
        },

        formatDate: function(date) {
            return date.getFullYear() + '-' +
                   (date.getMonth() + 1).toString().padStart(2, '0') + '-' +
                   date.getDate().toString().padStart(2, '0');
        },

        formatDateLong: function(date) {
            var dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
            var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
            return dayNames[date.getDay()] + ', ' + date.getDate() + '. ' + monthNames[date.getMonth()];
        },

        formatTime: function(date) {
            return date.getHours().toString().padStart(2, '0') + ':' +
                   date.getMinutes().toString().padStart(2, '0');
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Events laden
        loadEvents: function() {
            var self = this;
            this.debug('Lade Events...');

            if (typeof synnioCalendar === 'undefined') {
                this.debug('synnioCalendar nicht definiert - Demo-Modus');
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            self.events = response.data;
                            self.renderCalendar();
                            self.updateCalendarCounts();
                            self.debug('Events geladen:', self.events.length);
                        }
                    } catch (e) {
                        self.debug('Fehler beim Parsen:', e);
                    }
                }
            };

            xhr.send('action=synnio_calendar_get_events&nonce=' + synnioCalendar.nonce);
        },

        // Kalender laden
        loadCalendars: function() {
            var self = this;
            this.debug('Lade Kalender...');

            if (typeof synnioCalendar === 'undefined') return;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.data) {
                            self.calendars = response.data;
                            self.debug('Kalender geladen:', self.calendars.length);
                        }
                    } catch (e) {
                        self.debug('Fehler beim Parsen:', e);
                    }
                }
            };

            xhr.send('action=synnio_calendar_get_calendars&nonce=' + synnioCalendar.nonce);
        },

        // Event speichern
        saveEvent: function() {
            var self = this;
            this.debug('Speichere Event...');

            var form = document.getElementById('synnioEventForm');
            if (!form) {
                this.debug('Form nicht gefunden');
                return;
            }

            var title = document.getElementById('synnioEventTitle');
            if (!title || !title.value.trim()) {
                alert('Bitte geben Sie einen Titel ein.');
                return;
            }

            if (typeof synnioCalendar === 'undefined') {
                this.debug('synnioCalendar nicht definiert');
                alert('Event gespeichert (Demo-Modus)');
                this.closeAllModals();
                return;
            }

            var data = new FormData(form);
            data.append('action', 'synnio_calendar_save_event');
            data.append('nonce', synnioCalendar.nonce);

            // Checkbox-Werte explizit als 'true'/'false' setzen (PHP erwartet 'true')
            var allDayCheckbox = document.getElementById('synnioEventAllDay');
            var availableCheckbox = document.getElementById('synnioEventAvailable');
            data.set('all_day', allDayCheckbox && allDayCheckbox.checked ? 'true' : 'false');
            data.set('is_available', availableCheckbox && availableCheckbox.checked ? 'true' : 'false');

            // Farbe explizit sicherstellen
            var colorInput = document.getElementById('synnioEventColor');
            if (colorInput && colorInput.value) {
                data.set('color', colorInput.value);
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                self.debug('Save Response Status:', xhr.status);
                self.debug('Save Response Text:', xhr.responseText);

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('Save Response:', response);
                        if (response.success) {
                            alert('Termin erfolgreich gespeichert!');
                            self.closeAllModals();
                            self.loadEvents();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Speichern');
                        }
                    } catch (e) {
                        self.debug('Fehler beim Parsen:', e);
                        self.debug('Response war:', xhr.responseText);
                        alert('Fehler bei der Verarbeitung der Server-Antwort');
                    }
                } else {
                    self.debug('HTTP Fehler:', xhr.status);
                    alert('Server-Fehler: ' + xhr.status + '\nBitte pruefen Sie die Console fuer Details.');
                }
            };

            xhr.onerror = function() {
                self.debug('Netzwerkfehler');
                alert('Netzwerkfehler - Bitte pruefen Sie Ihre Verbindung');
            };

            xhr.send(data);
        },

        // Batch Events speichern
        saveBatchEvents: function() {
            var self = this;
            this.debug('Speichere Batch Events...');

            var selectedDays = [];
            document.querySelectorAll('.synnio-day-chip.selected').forEach(function(chip) {
                selectedDays.push(chip.dataset.day);
            });

            var selectedTimes = [];
            document.querySelectorAll('.synnio-time-chip.selected').forEach(function(chip) {
                selectedTimes.push(chip.dataset.time);
            });

            if (selectedDays.length === 0 || selectedTimes.length === 0) {
                alert('Bitte waehlen Sie mindestens einen Tag und eine Zeit aus.');
                return;
            }

            if (typeof synnioCalendar === 'undefined') {
                this.debug('synnioCalendar nicht definiert');
                alert('Batch-Termine erstellt (Demo-Modus)');
                this.closeAllModals();
                return;
            }

            // Formulardaten sammeln
            var eventType = document.getElementById('synnioBatchType');
            var duration = document.getElementById('synnioBatchDuration');
            var startDate = document.getElementById('synnioBatchStartDate');
            var endDate = document.getElementById('synnioBatchEndDate');
            var isAvailable = document.getElementById('synnioBatchAvailable');

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_batch_create');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('event_type', eventType ? eventType.value : 'available');
            formData.append('duration', duration ? duration.value : '60');
            formData.append('start_date', startDate ? startDate.value : '');
            formData.append('end_date', endDate ? endDate.value : '');
            formData.append('is_available', isAvailable && isAvailable.checked ? 'true' : 'false');

            // Tage und Zeiten als Arrays
            selectedDays.forEach(function(day) {
                formData.append('days[]', day);
            });
            selectedTimes.forEach(function(time) {
                formData.append('times[]', time);
            });

            this.debug('Sende Batch-Anfrage...');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('Batch Response:', response);
                        if (response.success) {
                            alert(response.data.message || 'Termine erfolgreich erstellt');
                            self.closeAllModals();
                            self.loadEvents();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Erstellen');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        alert('Fehler bei der Verarbeitung');
                    }
                } else {
                    self.debug('HTTP Fehler:', xhr.status);
                    alert('Server-Fehler: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                self.debug('Netzwerkfehler');
                alert('Netzwerkfehler');
            };

            xhr.send(formData);
        },

        // Einstellungen speichern
        saveSettings: function() {
            var self = this;
            this.debug('Speichere Einstellungen...');

            if (typeof synnioCalendar === 'undefined') {
                this.debug('synnioCalendar nicht definiert');
                alert('Einstellungen gespeichert (Demo-Modus)');
                this.closeAllModals();
                return;
            }

            var defaultView = document.getElementById('synnioSettingsDefaultView');
            var weekStarts = document.getElementById('synnioSettingsWeekStarts');
            var syncDirection = document.getElementById('synnioSettingsSyncDirection');

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_save_settings');
            formData.append('nonce', synnioCalendar.nonce);

            if (defaultView) formData.append('default_view', defaultView.value);
            if (weekStarts) formData.append('week_starts', weekStarts.value);
            if (syncDirection) formData.append('sync_direction', syncDirection.value);

            // Kalender-Anzeigebereich
            var displayStart = document.getElementById('synnioSettingsDisplayStart');
            var displayEnd = document.getElementById('synnioSettingsDisplayEnd');
            if (displayStart) formData.append('display_start', displayStart.value);
            if (displayEnd) formData.append('display_end', displayEnd.value);

            // Oeffnungszeiten sammeln
            var businessHours = {};
            for (var d = 0; d < 7; d++) {
                var enabledCb = document.querySelector('.synnio-bh-enabled[data-day="' + d + '"]');
                var startInput = document.querySelector('.synnio-bh-start[data-day="' + d + '"]');
                var endInput = document.querySelector('.synnio-bh-end[data-day="' + d + '"]');
                businessHours[d] = {
                    enabled: enabledCb ? enabledCb.checked : false,
                    start: startInput ? startInput.value : '08:00',
                    end: endInput ? endInput.value : '18:00'
                };
            }
            formData.append('business_hours', JSON.stringify(businessHours));

            // Pausenzeiten sammeln
            var breaks = [];
            document.querySelectorAll('.synnio-break-row').forEach(function(row) {
                var label = row.querySelector('.synnio-break-label');
                var start = row.querySelector('.synnio-break-start');
                var end = row.querySelector('.synnio-break-end');
                if (start && end && start.value && end.value) {
                    breaks.push({
                        label: label ? label.value : '',
                        start: start.value,
                        end: end.value
                    });
                }
            });
            formData.append('breaks', JSON.stringify(breaks));

            // Eigene Termintypen sammeln
            var customEventTypes = [];
            document.querySelectorAll('.synnio-custom-event-type-row').forEach(function(row) {
                var key = row.querySelector('.synnio-cet-key');
                var label = row.querySelector('.synnio-cet-label');
                var color = row.querySelector('.synnio-cet-color');
                if (key && label && key.value.trim() && label.value.trim()) {
                    customEventTypes.push({
                        key: key.value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_'),
                        label: label.value.trim(),
                        color: color ? color.value : '#6B7280'
                    });
                }
            });
            formData.append('custom_event_types', JSON.stringify(customEventTypes));

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Lokale Daten aktualisieren
                            synnioCalendar.businessHours = businessHours;
                            synnioCalendar.breaks = breaks;
                            if (displayStart) synnioCalendar.displayStart = displayStart.value;
                            if (displayEnd) synnioCalendar.displayEnd = displayEnd.value;
                            alert(response.data.message || 'Einstellungen gespeichert');
                            self.closeAllModals();
                            self.renderCalendar();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Speichern');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                    }
                }
            };

            xhr.send(formData);
        },

        // Pause-Zeile hinzufuegen
        addBreakRow: function() {
            var list = document.getElementById('synnioBreaksList');
            if (!list) return;
            var idx = list.querySelectorAll('.synnio-break-row').length;
            var row = document.createElement('div');
            row.className = 'synnio-break-row';
            row.dataset.breakIndex = idx;
            row.innerHTML = '<input type="text" class="synnio-form-input synnio-break-label" placeholder="z.B. Mittagspause">' +
                '<input type="time" class="synnio-form-input synnio-break-start" value="12:00">' +
                '<span class="synnio-bh-separator">bis</span>' +
                '<input type="time" class="synnio-form-input synnio-break-end" value="13:00">' +
                '<button type="button" class="synnio-btn synnio-btn-danger synnio-remove-break-btn" title="Entfernen"><i class="fas fa-trash"></i></button>';
            list.appendChild(row);
        },

        // Eigenen Termintyp-Zeile hinzufuegen
        addEventTypeRow: function() {
            var list = document.getElementById('synnioCustomEventTypesList');
            if (!list) return;
            var idx = list.querySelectorAll('.synnio-custom-event-type-row').length;
            var row = document.createElement('div');
            row.className = 'synnio-custom-event-type-row';
            row.dataset.index = idx;
            row.innerHTML = '<input type="text" class="synnio-form-input synnio-cet-key" placeholder="Schluessel (z.B. wartung)">' +
                '<input type="text" class="synnio-form-input synnio-cet-label" placeholder="Bezeichnung">' +
                '<input type="color" class="synnio-cet-color" value="#6B7280">' +
                '<button type="button" class="synnio-btn synnio-btn-danger synnio-remove-cet-btn" title="Entfernen"><i class="fas fa-trash"></i></button>';
            list.appendChild(row);
        },

        // Feiertage importieren
        importHolidays: function() {
            var self = this;
            var stateSelect = document.getElementById('synnioHolidayState');
            var yearSelect = document.getElementById('synnioHolidayYear');
            var resultDiv = document.getElementById('synnioHolidayResult');

            if (!stateSelect || !stateSelect.value) {
                alert('Bitte waehlen Sie ein Bundesland aus.');
                return;
            }

            if (typeof synnioCalendar === 'undefined') {
                alert('Feiertage importiert (Demo-Modus)');
                return;
            }

            var btn = document.getElementById('synnioDownloadHolidaysBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importiere...';
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_import_holidays');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('state', stateSelect.value);
            formData.append('year', yearSelect ? yearSelect.value : new Date().getFullYear());

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download"></i> Feiertage herunterladen und importieren';
                }

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            if (resultDiv) {
                                resultDiv.style.display = 'block';
                                resultDiv.innerHTML = '<div style="padding: 12px; background: #ECFDF5; border-radius: 8px; color: #065F46;"><i class="fas fa-check-circle"></i> ' + self.escapeHtml(response.data.message) + '</div>';
                            }
                            self.loadEvents();
                        } else {
                            if (resultDiv) {
                                resultDiv.style.display = 'block';
                                resultDiv.innerHTML = '<div style="padding: 12px; background: #FEF2F2; border-radius: 8px; color: #991B1B;"><i class="fas fa-exclamation-circle"></i> ' + self.escapeHtml(response.data.message || 'Fehler') + '</div>';
                            }
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                    }
                }
            };

            xhr.send(formData);
        },

        // Hilfsfunktion: Zeitstring zu Minuten
        timeToMinutes: function(timeStr) {
            if (!timeStr) return 0;
            var parts = timeStr.split(':');
            return parseInt(parts[0], 10) * 60 + parseInt(parts[1] || '0', 10);
        },

        // Anzeigebereich des Kalenders ermitteln
        getDisplayRange: function() {
            var startTime = (typeof synnioCalendar !== 'undefined' && synnioCalendar.displayStart) ? synnioCalendar.displayStart : '07:00';
            var endTime = (typeof synnioCalendar !== 'undefined' && synnioCalendar.displayEnd) ? synnioCalendar.displayEnd : '20:00';
            var startHour = parseInt(startTime.split(':')[0], 10) || 0;
            var endHour = parseInt(endTime.split(':')[0], 10) || 24;
            if (endHour <= startHour) endHour = startHour + 1;
            return {
                startHour: startHour,
                endHour: endHour,
                startMin: startHour * 60
            };
        },

        // Oeffnungszeiten- und Pausen-Overlays fuer einen Wochentag generieren
        renderTimeOverlays: function(dayOfWeek) {
            var html = '';
            var dispRange = this.getDisplayRange();
            var dispStartMin = dispRange.startMin;
            var dispTotalMin = (dispRange.endHour - dispRange.startHour) * 60;
            var bh = (typeof synnioCalendar !== 'undefined' && synnioCalendar.businessHours) ? synnioCalendar.businessHours[dayOfWeek] : null;

            if (bh) {
                if (!bh.enabled) {
                    // Ganzer Tag geschlossen
                    html += '<div class="synnio-closed-overlay" style="top: 0; height: ' + dispTotalMin + 'px;" title="Geschlossen"></div>';
                } else {
                    var startMin = this.timeToMinutes(bh.start);
                    var endMin = this.timeToMinutes(bh.end);
                    // Vor Oeffnung (nur wenn innerhalb des Anzeigebereichs)
                    var overlayTop = Math.max(0, startMin - dispStartMin);
                    if (startMin > dispStartMin) {
                        html += '<div class="synnio-closed-overlay" style="top: 0; height: ' + overlayTop + 'px;"></div>';
                    }
                    // Nach Schluss (nur wenn innerhalb des Anzeigebereichs)
                    var overlayBottom = endMin - dispStartMin;
                    if (overlayBottom < dispTotalMin) {
                        html += '<div class="synnio-closed-overlay" style="top: ' + Math.max(0, overlayBottom) + 'px; height: ' + (dispTotalMin - Math.max(0, overlayBottom)) + 'px;"></div>';
                    }
                }
            }

            // Pausen-Overlays (nur wenn Tag offen ist)
            if (!bh || bh.enabled) {
                var breaks = (typeof synnioCalendar !== 'undefined' && synnioCalendar.breaks) ? synnioCalendar.breaks : [];
                var self = this;
                breaks.forEach(function(brk) {
                    var brkStartMin = self.timeToMinutes(brk.start) - dispStartMin;
                    var brkEndMin = self.timeToMinutes(brk.end) - dispStartMin;
                    // Nur rendern wenn im sichtbaren Bereich
                    if (brkEndMin > 0 && brkStartMin < dispTotalMin) {
                        var brkTop = Math.max(0, brkStartMin);
                        var brkHeight = Math.min(brkEndMin, dispTotalMin) - brkTop;
                        if (brkHeight > 0) {
                            html += '<div class="synnio-break-overlay" style="top: ' + brkTop + 'px; height: ' + brkHeight + 'px;" title="' + self.escapeHtml(brk.label || 'Pause') + '"></div>';
                        }
                    }
                });
            }

            return html;
        },

        // Google Calendar verbinden
        connectGoogleCalendar: function() {
            this.debug('Google Calendar Verbindung...');
            this.initiateOAuth('google');
        },

        // Outlook verbinden
        connectOutlook: function() {
            this.debug('Outlook Verbindung...');
            this.initiateOAuth('outlook');
        },

        // OAuth initiieren
        initiateOAuth: function(provider) {
            var self = this;
            this.debug('OAuth initiieren fuer:', provider);

            if (typeof synnioCalendar === 'undefined') {
                alert('Fehler: Konfiguration nicht geladen');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_oauth_init');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('provider', provider);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        self.debug('OAuth Init Response:', response);

                        if (response.success && response.data.auth_url) {
                            // OAuth-Fenster oeffnen
                            window.location.href = response.data.auth_url;
                        } else {
                            var message = response.data && response.data.message
                                ? response.data.message
                                : 'OAuth konnte nicht gestartet werden';
                            alert(message);
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        alert('Fehler bei der Verarbeitung');
                    }
                } else {
                    alert('Server-Fehler: ' + xhr.status);
                }
            };

            xhr.onerror = function() {
                alert('Netzwerkfehler');
            };

            xhr.send(formData);
        },

        // OAuth trennen
        disconnectOAuth: function(provider) {
            var self = this;
            this.debug('OAuth trennen fuer:', provider);

            if (typeof synnioCalendar === 'undefined') return;

            if (!confirm('Moechten Sie die Verbindung zu ' + (provider === 'google' ? 'Google Calendar' : 'Outlook') + ' wirklich trennen?')) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_oauth_disconnect');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('provider', provider);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('Verbindung getrennt');
                            self.loadOAuthStatus();
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Fehler beim Trennen');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                    }
                }
            };

            xhr.send(formData);
        },

        // OAuth Status laden
        loadOAuthStatus: function() {
            var self = this;
            this.debug('Lade OAuth Status...');

            if (typeof synnioCalendar === 'undefined') return;

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_oauth_status');
            formData.append('nonce', synnioCalendar.nonce);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            self.updateOAuthUI(response.data);
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                    }
                }
            };

            xhr.send(formData);
        },

        // OAuth UI aktualisieren
        updateOAuthUI: function(status) {
            this.debug('Aktualisiere OAuth UI:', status);

            // Google
            var googleBtn = document.getElementById('synnioConnectGoogleBtn');
            var googleSyncBtn = document.getElementById('synnioSyncGoogleBtn');
            var googleStatus = document.getElementById('synnioGoogleStatus');

            if (googleBtn) {
                if (status.google && status.google.connected) {
                    googleBtn.innerHTML = '<i class="fas fa-unlink"></i> Trennen';
                    googleBtn.classList.remove('synnio-btn-primary');
                    googleBtn.classList.add('synnio-btn-danger', 'connected');
                    googleBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        window.SynnioCalendar.disconnectOAuth('google');
                    };
                    if (googleSyncBtn) googleSyncBtn.style.display = 'inline-flex';
                    if (googleStatus) googleStatus.innerHTML = '<span style="color: #10B981;"><i class="fas fa-check-circle"></i> Verbunden: ' + (status.google.email || 'Google Calendar') + '</span>';
                } else {
                    googleBtn.innerHTML = '<i class="fab fa-google"></i> Verbinden';
                    googleBtn.classList.add('synnio-btn-primary');
                    googleBtn.classList.remove('synnio-btn-danger', 'connected');
                    if (googleSyncBtn) googleSyncBtn.style.display = 'none';
                    if (googleStatus) googleStatus.innerHTML = '<span style="color: #6B7280;">Nicht verbunden</span>';
                }
            }

            // Outlook
            var outlookBtn = document.getElementById('synnioConnectOutlookBtn');
            var outlookSyncBtn = document.getElementById('synnioSyncOutlookBtn');
            var outlookStatus = document.getElementById('synnioOutlookStatus');

            if (outlookBtn) {
                if (status.outlook && status.outlook.connected) {
                    outlookBtn.innerHTML = '<i class="fas fa-unlink"></i> Trennen';
                    outlookBtn.classList.remove('synnio-btn-primary');
                    outlookBtn.classList.add('synnio-btn-danger', 'connected');
                    outlookBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        window.SynnioCalendar.disconnectOAuth('outlook');
                    };
                    if (outlookSyncBtn) outlookSyncBtn.style.display = 'inline-flex';
                    if (outlookStatus) outlookStatus.innerHTML = '<span style="color: #10B981;"><i class="fas fa-check-circle"></i> Verbunden: ' + (status.outlook.email || 'Outlook') + '</span>';
                } else {
                    outlookBtn.innerHTML = '<i class="fab fa-microsoft"></i> Verbinden';
                    outlookBtn.classList.add('synnio-btn-primary');
                    outlookBtn.classList.remove('synnio-btn-danger', 'connected');
                    if (outlookSyncBtn) outlookSyncBtn.style.display = 'none';
                    if (outlookStatus) outlookStatus.innerHTML = '<span style="color: #6B7280;">Nicht verbunden</span>';
                }
            }
        },

        // Toast-Benachrichtigung anzeigen
        showToast: function(message, type) {
            type = type || 'success';
            var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            var bgColor = type === 'success' ? 'linear-gradient(135deg, #10B981, #059669)' : 'linear-gradient(135deg, #EF4444, #DC2626)';

            var toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:60px;right:30px;z-index:999999;display:flex;align-items:center;gap:12px;padding:16px 24px;border-radius:12px;background:' + bgColor + ';color:#fff;font-family:Inter,-apple-system,sans-serif;font-size:14px;font-weight:500;box-shadow:0 10px 40px rgba(0,0,0,0.2);transform:translateX(120%);transition:transform 0.4s cubic-bezier(0.34,1.56,0.64,1);max-width:420px;';
            toast.innerHTML = '<i class="fas ' + icon + '" style="font-size:20px;"></i><span>' + message + '</span>';

            document.body.appendChild(toast);

            // Einblenden
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    toast.style.transform = 'translateX(0)';
                });
            });

            // Nach 4 Sekunden ausblenden
            setTimeout(function() {
                toast.style.transform = 'translateX(120%)';
                setTimeout(function() {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 400);
            }, 4000);
        },

        // OAuth Callback pruefen
        checkOAuthCallback: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var oauthStatus = urlParams.get('synnio_oauth');
            var provider = urlParams.get('provider');
            var message = urlParams.get('message');
            var self = this;

            if (oauthStatus === 'success') {
                var providerName = provider === 'google' ? 'Google Calendar' : 'Microsoft Outlook';
                // URL sofort bereinigen
                window.history.replaceState({}, document.title, window.location.pathname);
                // Erfolgsmeldung als Toast anzeigen
                setTimeout(function() {
                    self.showToast(providerName + ' wurde erfolgreich verbunden!', 'success');
                }, 500);
                // Status aktualisieren
                this.loadOAuthStatus();
            } else if (oauthStatus === 'error') {
                window.history.replaceState({}, document.title, window.location.pathname);
                var self = this;
                setTimeout(function() {
                    self.showToast('Verbindungsfehler: ' + (message ? decodeURIComponent(message) : 'Unbekannter Fehler'), 'error');
                }, 500);
            }
        },

        // Events synchronisieren
        syncEvents: function(provider, direction) {
            var self = this;
            this.debug('Synchronisiere Events:', provider, direction);

            if (typeof synnioCalendar === 'undefined') return;

            // Sync-Button deaktivieren waehrend Sync laeuft
            var syncBtn = provider === 'google' ? document.getElementById('synnioSyncGoogleBtn') : document.getElementById('synnioSyncOutlookBtn');
            if (syncBtn) {
                syncBtn.disabled = true;
                syncBtn.innerHTML = '<i class="fas fa-sync fa-spin"></i> Synchronisiere...';
            }

            var formData = new FormData();
            formData.append('action', 'synnio_calendar_sync_events');
            formData.append('nonce', synnioCalendar.nonce);
            formData.append('provider', provider);
            formData.append('direction', direction || 'both');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', synnioCalendar.ajaxUrl, true);

            xhr.onload = function() {
                // Sync-Button wiederherstellen
                if (syncBtn) {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = '<i class="fas fa-sync"></i> Sync';
                }

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            self.showToast(response.data.message || 'Synchronisation abgeschlossen', 'success');
                            self.loadEvents();
                        } else {
                            self.showToast(response.data && response.data.message ? response.data.message : 'Synchronisation fehlgeschlagen', 'error');
                        }
                    } catch (e) {
                        self.debug('Fehler:', e);
                        self.showToast('Synchronisation fehlgeschlagen', 'error');
                    }
                } else {
                    self.showToast('Serverfehler bei der Synchronisation', 'error');
                }
            };

            xhr.onerror = function() {
                if (syncBtn) {
                    syncBtn.disabled = false;
                    syncBtn.innerHTML = '<i class="fas fa-sync"></i> Sync';
                }
                self.showToast('Netzwerkfehler bei der Synchronisation', 'error');
            };

            xhr.send(formData);
        }
    };

    // Initialisierung
    function initCalendar() {
        console.log('[SynnioKalender] initCalendar aufgerufen');
        if (document.querySelector('.synnio-calendar-app')) {
            window.SynnioCalendar.init();
        } else {
            console.log('[SynnioKalender] .synnio-calendar-app nicht gefunden');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCalendar);
    } else {
        initCalendar();
    }

    window.addEventListener('load', function() {
        if (!window.SynnioCalendar.initialized) {
            initCalendar();
        }
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function() {
            if (!window.SynnioCalendar.initialized) {
                initCalendar();
            }
        });
    }

})();
