<?php
/**
 * Outbound Shortcode
 *
 * Renders the Outbound Agent and Phone List management frontend
 * Shortcode: [synnio_outbound]
 */

if (!defined('ABSPATH')) exit;

add_shortcode('synnio_outbound', 'synnio_outbound_shortcode_render');

function synnio_outbound_shortcode_render($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div class="synnio-outbound-login-required">
            <p>Bitte melden Sie sich an, um auf die Outbound-Telefonie zuzugreifen.</p>
        </div>';
    }

    // Enqueue assets
    wp_enqueue_style(
        'font-awesome-6-outbound',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
        [],
        '6.5.1'
    );
    wp_enqueue_style(
        'synnio-outbound-css',
        SYNNIO_TEL_URL . 'assets/portal-outbound.css',
        ['font-awesome-6-outbound'],
        SYNNIO_TEL_VERSION
    );

    wp_enqueue_script(
        'synnio-outbound-js',
        SYNNIO_TEL_URL . 'assets/portal-outbound.js',
        [],
        SYNNIO_TEL_VERSION,
        true
    );

    // Pass configuration to JavaScript
    wp_localize_script('synnio-outbound-js', 'synnioOutbound', [
        'restUrl' => rest_url(SYNNIO_TEL_NS . '/outbound/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'isAdmin' => current_user_can('manage_options'),
        'strings' => [
            'confirmDelete' => 'Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?',
            'confirmBulkDelete' => 'Sind Sie sicher, dass Sie die ausgewählten Einträge löschen möchten?',
            'confirmStartCampaign' => 'Möchten Sie die Kampagne wirklich starten?',
            'noAgentAssigned' => 'Bitte weisen Sie zuerst einen Agent zu.',
            'noEntriesSelected' => 'Bitte wählen Sie mindestens einen Eintrag aus.',
            'uploadSuccess' => 'Datei erfolgreich hochgeladen.',
            'uploadError' => 'Fehler beim Hochladen der Datei.',
            'saveSuccess' => 'Erfolgreich gespeichert.',
            'saveError' => 'Fehler beim Speichern.',
            'deleteSuccess' => 'Erfolgreich gelöscht.',
            'deleteError' => 'Fehler beim Löschen.',
            'loading' => 'Laden...',
            'noData' => 'Keine Daten vorhanden.',
            'variables' => [
                '{firmenname}' => 'Firmenname des Kontakts',
                '{ansprechpartner}' => 'Name des Ansprechpartners',
                '{telefonnummer}' => 'Telefonnummer',
                '{email}' => 'E-Mail-Adresse'
            ]
        ]
    ]);

    // Get client info
    $user_id = get_current_user_id();
    $client_id = get_user_meta($user_id, '_synnio_client_id', true);

    ob_start();
    ?>
    <div id="synnio-outbound-app" class="synnio-outbound-container">
        <!-- Header -->
        <header class="synnio-outbound-header">
            <div class="header-content">
                <h1>Outbound Telefonie</h1>
                <p class="header-subtitle">Verwalten Sie Ihre Outbound-Agents und Telefonlisten</p>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="synnio-outbound-nav">
            <button class="nav-tab active" data-tab="agents">
                <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Agents</span>
            </button>
            <button class="nav-tab" data-tab="lists">
                <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span>Telefonlisten</span>
            </button>
            <button class="nav-tab" data-tab="calls">
                <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                <span>Anrufprotokoll</span>
            </button>
        </nav>

        <!-- Tab Content: Agents -->
        <section id="tab-agents" class="tab-content active">
            <div class="section-header">
                <h2>Outbound Agents</h2>
                <button id="btn-new-agent" class="btn btn-primary">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Neuer Agent
                </button>
            </div>

            <div id="agents-list" class="cards-grid">
                <div class="loading-spinner">Laden...</div>
            </div>
        </section>

        <!-- Tab Content: Lists -->
        <section id="tab-lists" class="tab-content">
            <div class="section-header">
                <h2>Telefonlisten</h2>
                <button id="btn-new-list" class="btn btn-primary">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Neue Liste
                </button>
            </div>

            <div id="lists-container" class="cards-grid">
                <div class="loading-spinner">Laden...</div>
            </div>
        </section>

        <!-- Tab Content: Call Log -->
        <section id="tab-calls" class="tab-content">
            <div class="section-header">
                <h2>Anrufprotokoll</h2>
                <div class="filter-controls">
                    <select id="filter-agent" class="form-select">
                        <option value="">Alle Agents</option>
                    </select>
                    <select id="filter-list" class="form-select">
                        <option value="">Alle Listen</option>
                    </select>
                    <button type="button" id="btn-refresh-calls-overview" class="btn-refresh-overview" title="Anrufliste aktualisieren">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Aktualisieren
                    </button>
                </div>
            </div>

            <!-- Bulk Actions Bar (hidden by default) -->
            <div id="calls-bulk-actions" class="bulk-actions" style="display:none;">
                <span class="bulk-count" id="calls-selected-count">0 ausgewählt</span>
                <button type="button" class="btn btn-small btn-danger" id="btn-bulk-delete-calls">
                    <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Ausgewählte löschen
                </button>
            </div>

            <div id="calls-table-container">
                <table class="data-table" id="calls-table">
                    <thead>
                        <tr>
                            <th class="col-checkbox"><input type="checkbox" id="calls-select-all" title="Alle auswählen"></th>
                            <th>Telefonnummer</th>
                            <th>Agent</th>
                            <th>Datum</th>
                            <th>Dauer</th>
                            <th>Status</th>
                            <th>Zusammenfassung</th>
                            <th class="col-actions">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="8" class="loading-cell">Laden...</td></tr>
                    </tbody>
                </table>
                <div id="calls-pagination" class="pagination"></div>
            </div>
        </section>

        <!-- Agent Editor Modal -->
        <div id="modal-agent" class="modal">
            <div class="modal-overlay"></div>
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3 id="modal-agent-title">Neuer Agent</h3>
                    <button class="modal-close" data-close-modal>&times;</button>
                </div>
                <form id="form-agent" class="modal-body">
                    <input type="hidden" id="agent-id" name="id">

                    <!-- Basic Info -->
                    <div class="form-section">
                        <h4>Grundeinstellungen</h4>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="agent-name">Name *</label>
                                <input type="text" id="agent-name" name="name" required class="form-input" placeholder="z.B. Terminvereinbarung">
                            </div>
                            <div class="form-group">
                                <label for="agent-voice">Stimme *</label>
                                <select id="agent-voice" name="voice_id" required class="form-select">
                                    <option value="">Stimme auswählen...</option>
                                </select>
                                <button type="button" id="btn-preview-voice" class="btn btn-small btn-secondary" style="margin-top:0.5rem;">
                                    <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                    </svg>
                                    Anhören
                                </button>
                            </div>
                            <div class="form-group">
                                <label for="agent-language">Sprache</label>
                                <select id="agent-language" name="language" class="form-select">
                                    <option value="de" selected>Deutsch</option>
                                    <option value="en">Englisch</option>
                                    <option value="fr">Französisch</option>
                                    <option value="es">Spanisch</option>
                                    <option value="it">Italienisch</option>
                                    <option value="nl">Niederländisch</option>
                                    <option value="pl">Polnisch</option>
                                    <option value="pt">Portugiesisch</option>
                                </select>
                            </div>
                        </div>
                        <small class="form-hint" style="margin-top:0.75rem; display:block; color:#64748b;">Technische Einstellungen (LLM, TTS, Systemtools) werden unter <strong>AI-Assistent &gt; Telefon Outbound</strong> konfiguriert.</small>
                    </div>

                    <!-- Prompts -->
                    <div class="form-section">
                        <h4>Prompts & Nachrichten</h4>
                        <div class="domain-crawl-section">
                            <label>Website crawlen & Prompt generieren</label>
                            <div class="domain-crawl-row">
                                <input type="url" id="agent-crawl-domain" class="form-input" placeholder="https://example.com">
                                <button type="button" id="btn-crawl-generate" class="btn btn-small btn-primary">
                                    <i class="fas fa-robot" id="crawl-icon"></i>
                                    <span id="crawl-btn-text">Prompt generieren</span>
                                </button>
                            </div>
                            <small class="form-hint">Geben Sie eine Domain ein. Die Website wird gecrawlt und ein passender Kaltakquise-Prompt wird automatisch generiert.</small>
                        </div>
                        <div class="form-group">
                            <label for="agent-prompt">Systemaufforderung *</label>
                            <textarea id="agent-prompt" name="system_prompt" rows="6" required class="form-textarea" placeholder="Beschreiben Sie das Verhalten und die Rolle des Agents..."></textarea>
                            <small class="form-hint">Definieren Sie die Persönlichkeit und Verhaltensregeln des Agents.</small>
                        </div>
                        <div class="form-group">
                            <label for="agent-first-message">Erste Nachricht</label>
                            <textarea id="agent-first-message" name="first_message" rows="3" class="form-textarea" placeholder="Guten Tag, mein Name ist ... Ich rufe an, weil wir im Bereich ... taetig sind. Wer waere bei Ihnen der richtige Ansprechpartner fuer dieses Thema?"></textarea>
                            <small class="form-hint" style="color:#b45309;">Tipp: Die erste Nachricht sollte KEINE Variablen enthalten, da meist nicht der direkte Ansprechpartner rangeht. Stattdessen: Begruessung + eigener Name + kurze Einordnung + Frage nach dem richtigen Ansprechpartner.</small>
                            <div class="variables-hint">
                                <strong>Variablen (fuer Systemaufforderung):</strong>
                                <span class="var-tag" data-var="{firmenname}">{firmenname}</span>
                                <span class="var-tag" data-var="{ansprechpartner}">{ansprechpartner}</span>
                                <span class="var-tag" data-var="{telefonnummer}">{telefonnummer}</span>
                                <span class="var-tag" data-var="{email}">{email}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Knowledge Base -->
                    <div class="form-section">
                        <h4>Wissensdatenbank</h4>
                        <div class="form-group">
                            <label>URLs hinzufügen</label>
                            <div class="url-input-group">
                                <input type="url" id="knowledge-url-input" class="form-input" placeholder="https://example.com/info">
                                <button type="button" id="btn-add-url" class="btn btn-secondary">Hinzufügen</button>
                            </div>
                            <ul id="knowledge-urls-list" class="knowledge-list"></ul>
                        </div>
                        <div class="form-group">
                            <label>Dateien hochladen</label>
                            <div class="file-upload-area" id="file-upload-area">
                                <input type="file" id="knowledge-file-input" accept=".pdf,.doc,.docx,.txt,.html" multiple hidden>
                                <div class="upload-placeholder">
                                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <p>Dateien hierher ziehen oder <strong>klicken zum Auswählen</strong></p>
                                    <small>PDF, DOCX, TXT, HTML (max. 10MB)</small>
                                </div>
                            </div>
                            <ul id="knowledge-files-list" class="knowledge-list"></ul>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Abbrechen</button>
                    <button type="submit" form="form-agent" class="btn btn-primary" id="btn-save-agent">
                        <span class="btn-text">Speichern</span>
                        <span class="btn-loading" hidden>Speichere...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Phone List Editor Modal -->
        <div id="modal-list" class="modal">
            <div class="modal-overlay"></div>
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3 id="modal-list-title">Neue Telefonliste</h3>
                    <button class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="list-id">

                    <div class="form-section">
                        <h4>Listeneinstellungen</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="list-name">Name *</label>
                                <input type="text" id="list-name" required class="form-input" placeholder="z.B. Neukundenakquise März">
                            </div>
                            <div class="form-group">
                                <label for="list-agent">Zugewiesener Agent</label>
                                <select id="list-agent" class="form-select">
                                    <option value="">Agent auswählen...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Entries Section -->
                    <div class="form-section" id="list-entries-section">
                        <div class="section-header-inline">
                            <h4>Kontakte <span id="entries-count">(0)</span></h4>
                            <div class="button-group">
                                <button type="button" id="btn-add-entry" class="btn btn-small btn-secondary">
                                    <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Eintrag
                                </button>
                                <button type="button" id="btn-import-csv" class="btn btn-small btn-secondary">
                                    <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    CSV Import
                                </button>
                                <input type="file" id="csv-file-input" accept=".csv" hidden>
                            </div>
                        </div>

                        <div class="bulk-actions" id="entries-bulk-actions" hidden>
                            <span class="bulk-count"><span id="selected-count">0</span> ausgewählt</span>
                            <button type="button" id="btn-bulk-delete" class="btn btn-small btn-danger">
                                <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                Löschen
                            </button>
                        </div>

                        <div class="entries-table-container">
                            <table class="data-table entries-table" id="entries-table">
                                <thead>
                                    <tr>
                                        <th class="col-checkbox"><input type="checkbox" id="select-all-entries"></th>
                                        <th class="col-company">Firma</th>
                                        <th>Ansprechpartner</th>
                                        <th>Telefon</th>
                                        <th>E-Mail</th>
                                        <th>Status</th>
                                        <th class="col-actions">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="entries-tbody">
                                    <tr class="empty-row"><td colspan="7">Keine Einträge vorhanden</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Campaign Controls -->
                    <div class="form-section" id="campaign-controls-section" hidden>
                        <h4>Kampagnensteuerung</h4>
                        <div class="campaign-status">
                            <div class="status-info">
                                <span class="status-label">Status:</span>
                                <span class="status-value" id="campaign-status">Nicht gestartet</span>
                            </div>
                            <div class="status-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="campaign-progress" style="width: 0%"></div>
                                </div>
                                <span class="progress-text" id="campaign-progress-text">0 / 0</span>
                            </div>
                        </div>
                        <div class="campaign-actions">
                            <button type="button" id="btn-start-campaign" class="btn btn-success">
                                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                </svg>
                                Kampagne starten
                            </button>
                            <button type="button" id="btn-pause-campaign" class="btn btn-warning" hidden>
                                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="6" y="4" width="4" height="16"></rect>
                                    <rect x="14" y="4" width="4" height="16"></rect>
                                </svg>
                                Pausieren
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btn-delete-list" class="btn btn-danger" hidden>
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Liste löschen
                    </button>
                    <div class="modal-footer-right">
                        <button type="button" class="btn btn-secondary" data-close-modal>Schließen</button>
                        <button type="button" id="btn-save-list" class="btn btn-primary">
                            <span class="btn-text">Speichern</span>
                            <span class="btn-loading" hidden>Speichere...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Entry Editor Modal -->
        <div id="modal-entry" class="modal">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-entry-title">Neuer Kontakt</h3>
                    <button class="modal-close" data-close-modal>&times;</button>
                </div>
                <form id="form-entry" class="modal-body">
                    <input type="hidden" id="entry-id">
                    <input type="hidden" id="entry-list-id">

                    <div class="form-group">
                        <label for="entry-company">Firmenname</label>
                        <input type="text" id="entry-company" class="form-input" placeholder="Musterfirma GmbH">
                    </div>
                    <div class="form-group">
                        <label for="entry-contact">Ansprechpartner</label>
                        <input type="text" id="entry-contact" class="form-input" placeholder="Max Mustermann">
                    </div>
                    <div class="form-group">
                        <label for="entry-phone">Telefonnummer *</label>
                        <input type="tel" id="entry-phone" required class="form-input" placeholder="+49 123 456789">
                    </div>
                    <div class="form-group">
                        <label for="entry-email">E-Mail</label>
                        <input type="email" id="entry-email" class="form-input" placeholder="max@example.com">
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Abbrechen</button>
                    <button type="submit" form="form-entry" class="btn btn-primary" id="btn-save-entry">Speichern</button>
                </div>
            </div>
        </div>

        <!-- CSV Import Modal -->
        <div id="modal-csv" class="modal">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>CSV Import</h3>
                    <button class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <div class="csv-preview" id="csv-preview">
                        <p>Wählen Sie eine CSV-Datei mit folgenden Spalten:</p>
                        <ul class="csv-format-hint">
                            <li><strong>Firma</strong> oder <strong>company_name</strong></li>
                            <li><strong>Ansprechpartner</strong> oder <strong>contact_person</strong></li>
                            <li><strong>Telefon</strong>, <strong>phone</strong> oder <strong>phone_number</strong> *</li>
                            <li><strong>E-Mail</strong> oder <strong>email</strong></li>
                        </ul>
                        <small>* Pflichtfeld</small>
                    </div>
                    <div id="csv-data-preview" hidden>
                        <p><strong id="csv-rows-count">0</strong> Einträge gefunden:</p>
                        <div class="csv-table-preview"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Abbrechen</button>
                    <button type="button" id="btn-confirm-import" class="btn btn-primary" disabled>Importieren</button>
                </div>
            </div>
        </div>

        <!-- Voice Preview Audio Element -->
        <audio id="voice-preview-audio" hidden></audio>

        <!-- Call Detail Modal -->
        <div id="modal-call-detail" class="modal">
            <div class="modal-overlay"></div>
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3 id="modal-call-detail-title">
                        <svg style="width:20px;height:20px;vertical-align:middle;margin-right:6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        Anrufdetails
                    </h3>
                    <button class="modal-close" data-close-modal>&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Call Info Cards -->
                    <div class="call-detail-section">
                        <div class="call-detail-grid call-detail-grid-modern">
                            <div class="call-detail-card">
                                <div class="call-detail-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                </div>
                                <span class="detail-label">Telefonnummer</span>
                                <span class="detail-value" id="call-detail-phone">-</span>
                            </div>
                            <div class="call-detail-card">
                                <div class="call-detail-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"></circle><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path></svg>
                                </div>
                                <span class="detail-label">Agent</span>
                                <span class="detail-value" id="call-detail-agent">-</span>
                            </div>
                            <div class="call-detail-card">
                                <div class="call-detail-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                </div>
                                <span class="detail-label">Datum / Zeit</span>
                                <span class="detail-value" id="call-detail-datetime">-</span>
                            </div>
                            <div class="call-detail-card">
                                <div class="call-detail-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                </div>
                                <span class="detail-label">Dauer</span>
                                <span class="detail-value" id="call-detail-duration">-</span>
                            </div>
                            <div class="call-detail-card">
                                <div class="call-detail-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
                                </div>
                                <span class="detail-label">Status</span>
                                <span class="detail-value" id="call-detail-status">-</span>
                            </div>
                            <div class="call-detail-card">
                                <div class="call-detail-card-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-6 0v4"></path><path d="M5 11h14a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2z"></path></svg>
                                </div>
                                <span class="detail-label">Erfolgreich</span>
                                <span class="detail-value" id="call-detail-successful">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Audio Player -->
                    <div class="call-detail-section" id="call-detail-audio-section">
                        <h4>
                            <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                            Aufnahme
                        </h4>
                        <div class="audio-player-container">
                            <audio id="call-detail-audio" controls class="call-audio-player">
                                Ihr Browser unterstuetzt das Audio-Element nicht.
                            </audio>
                            <a id="call-detail-download" href="#" download class="btn btn-small btn-secondary" style="margin-top: 0.5rem;">
                                <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                Herunterladen
                            </a>
                        </div>
                    </div>

                    <!-- Summary (German only) -->
                    <div class="call-detail-section">
                        <h4>
                            <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            Zusammenfassung
                        </h4>
                        <div id="call-detail-summary-de">
                            <p class="text-muted">Keine Zusammenfassung verfuegbar</p>
                        </div>
                    </div>

                    <!-- Hidden: English summary data holder (not displayed) -->
                    <div id="call-detail-summary-en" style="display:none;"></div>

                    <!-- Transcript (Chat) -->
                    <div class="call-detail-section">
                        <h4>
                            <svg style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            Transkript
                        </h4>
                        <div class="chat-transcript-container" id="call-detail-transcript">
                            <p class="text-muted">Kein Transkript verfuegbar</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btn-refresh-call-detail" class="btn btn-secondary">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Aktualisieren
                    </button>
                    <button type="button" class="btn btn-primary" data-close-modal>Schliessen</button>
                </div>
            </div>
        </div>

        <!-- Toast Notifications -->
        <div id="toast-container" class="toast-container"></div>
    </div>
    <?php
    return ob_get_clean();
}
