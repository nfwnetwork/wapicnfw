/**
 * Synnio Outbound Portal JavaScript
 * Handles Outbound Agent and Phone List management
 */

(function() {
    'use strict';

    // Configuration from WordPress
    const config = window.synnioOutbound || {};
    const API_URL = config.restUrl || '/wp-json/synnio/v1/outbound/';
    const NONCE = config.nonce || '';
    const STRINGS = config.strings || {};

    // State
    let agents = [];
    let lists = [];
    let calls = [];
    let voices = [];
    let models = [];
    let currentListEntries = [];
    let selectedEntries = new Set();
    let currentCallsPage = 1;
    let totalCallsPages = 1;

    // DOM Elements
    let app, toastContainer;

    // Initialize
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        app = document.getElementById('synnio-outbound-app');
        if (!app) return;

        toastContainer = document.getElementById('toast-container');

        // Setup navigation
        setupNavigation();

        // Setup modals
        setupModals();

        // Setup forms
        setupAgentForm();
        setupListForm();
        setupEntryForm();
        setupCSVImport();

        // Load initial data
        loadVoices();
        // LLM models are hardcoded in HTML - no need to load from API
        loadAgents();
        loadLists();
    }

    // =====================================================
    // NAVIGATION
    // =====================================================

    function setupNavigation() {
        const tabs = app.querySelectorAll('.nav-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.dataset.tab;

                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Show corresponding content
                app.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.toggle('active', content.id === 'tab-' + tabId);
                });

                // Load data for tab if needed
                if (tabId === 'calls' && calls.length === 0) {
                    loadCalls();
                }
            });
        });
    }

    // =====================================================
    // API HELPERS
    // =====================================================

    async function fetchAPI(endpoint, options = {}) {
        const url = API_URL + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': NONCE
            }
        };

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.body = JSON.stringify(options.body);
        }

        if (options.body instanceof FormData) {
            delete defaultOptions.headers['Content-Type'];
        }

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            const data = await response.json();

            if (!response.ok) {
                // Extract error message from various WP REST API error formats
                let errorMsg = 'API Fehler';
                if (data.message) {
                    errorMsg = typeof data.message === 'string' ? data.message : JSON.stringify(data.message);
                } else if (data.data && data.data.message) {
                    errorMsg = data.data.message;
                } else if (data.code) {
                    errorMsg = data.code;
                }
                throw new Error(errorMsg);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // =====================================================
    // TOAST NOTIFICATIONS
    // =====================================================

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // =====================================================
    // MODALS
    // =====================================================

    function setupModals() {
        // Close buttons
        app.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal');
                closeModal(modal);
            });
        });

        // Click outside to close
        app.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', () => {
                const modal = overlay.closest('.modal');
                closeModal(modal);
            });
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = app.querySelector('.modal.open');
                if (openModal) closeModal(openModal);
            }
        });
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modal) {
        if (typeof modal === 'string') {
            modal = document.getElementById(modal);
        }
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    // =====================================================
    // AGENTS
    // =====================================================

    async function loadAgents() {
        const container = document.getElementById('agents-list');
        container.innerHTML = '<div class="loading-spinner">Laden...</div>';

        try {
            const data = await fetchAPI('agents');
            agents = data.agents || [];
            renderAgents();
        } catch (error) {
            container.innerHTML = '<div class="error-message">Fehler beim Laden der Agents</div>';
            showToast('Fehler beim Laden der Agents', 'error');
        }
    }

    function renderAgents() {
        const container = document.getElementById('agents-list');

        if (agents.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <line x1="17" y1="11" x2="23" y2="11"></line>
                    </svg>
                    <p>Keine Agents vorhanden</p>
                    <button class="btn btn-primary" onclick="document.getElementById('btn-new-agent').click()">
                        Ersten Agent erstellen
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = agents.map(agent => `
            <div class="card agent-card" data-id="${agent.id}">
                <div class="card-header">
                    <h3 class="card-title">${esc(agent.name)}</h3>
                    <div class="agent-status-toggle">
                        <label class="toggle-switch" title="${agent.status === 'active' ? 'Agent deaktivieren' : 'Agent aktivieren'}">
                            <input type="checkbox" class="agent-toggle-checkbox" data-agent-id="${agent.id}" ${agent.status === 'active' ? 'checked' : ''}>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label ${agent.status === 'active' ? 'toggle-label-active' : ''}">${agent.status === 'active' ? 'Aktiv' : 'Inaktiv'}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-info">
                        <span class="info-label">Stimme:</span>
                        <span class="info-value">${esc(agent.voice_name || '-')}</span>
                    </div>
                    <div class="card-info">
                        <span class="info-label">Sprache:</span>
                        <span class="info-value">${getLanguageLabel(agent.language)}</span>
                    </div>
                    <div class="card-info">
                        <span class="info-label">Erstellt:</span>
                        <span class="info-value">${formatDate(agent.created_at)}</span>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-small btn-secondary" onclick="editAgent(${agent.id})">
                        <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Bearbeiten
                    </button>
                    <button class="btn btn-small btn-danger" onclick="deleteAgent(${agent.id})">
                        <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');

        // Attach toggle event listeners
        container.querySelectorAll('.agent-toggle-checkbox').forEach(cb => {
            cb.addEventListener('change', async (e) => {
                const agentId = e.target.dataset.agentId;
                const newStatus = e.target.checked ? 'active' : 'draft';
                const toggle = e.target;
                const card = toggle.closest('.agent-card');

                toggle.disabled = true;
                card.style.opacity = '0.6';

                try {
                    await fetchAPI('agents/' + agentId, {
                        method: 'PUT',
                        body: { status: newStatus }
                    });
                    showToast(newStatus === 'active' ? 'Agent aktiviert' : 'Agent deaktiviert', 'success');
                    // Re-fetch all agents to reflect single-active enforcement
                    await loadAgents();
                } catch (error) {
                    showToast(error.message || 'Fehler beim Statuswechsel', 'error');
                    // Revert toggle on error
                    toggle.checked = !toggle.checked;
                    card.style.opacity = '1';
                    toggle.disabled = false;
                }
            });
        });
    }

    function setupAgentForm() {
        const btnNew = document.getElementById('btn-new-agent');
        const form = document.getElementById('form-agent');
        const btnSave = document.getElementById('btn-save-agent');
        const btnPreviewVoice = document.getElementById('btn-preview-voice');
        const voiceSelect = document.getElementById('agent-voice');
        const urlInput = document.getElementById('knowledge-url-input');
        const btnAddUrl = document.getElementById('btn-add-url');
        const fileUploadArea = document.getElementById('file-upload-area');
        const fileInput = document.getElementById('knowledge-file-input');

        // New agent button
        btnNew.addEventListener('click', () => {
            resetAgentForm();
            document.getElementById('modal-agent-title').textContent = 'Neuer Agent';
            document.getElementById('btn-delete-agent')?.setAttribute('hidden', '');
            openModal('modal-agent');
        });

        // Audio tag add button
        const btnAddTag = document.getElementById('btn-add-audio-tag');
        if (btnAddTag) {
            btnAddTag.addEventListener('click', () => addAudioTagRow('', ''));
        }

        // Form submit
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveAgent();
        });

        // Voice preview
        btnPreviewVoice.addEventListener('click', () => {
            const selectedVoice = voices.find(v => v.voice_id === voiceSelect.value);
            if (selectedVoice && selectedVoice.preview_url) {
                const audio = document.getElementById('voice-preview-audio');
                audio.src = selectedVoice.preview_url;
                audio.play();
            }
        });

        // URL add
        btnAddUrl.addEventListener('click', () => addKnowledgeUrl());
        urlInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addKnowledgeUrl();
            }
        });

        // File upload
        fileUploadArea.addEventListener('click', () => fileInput.click());
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            handleFileUpload(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', () => {
            handleFileUpload(fileInput.files);
            fileInput.value = '';
        });

        // Domain crawl + prompt generation
        const btnCrawl = document.getElementById('btn-crawl-generate');
        if (btnCrawl) {
            btnCrawl.addEventListener('click', async () => {
                const domainInput = document.getElementById('agent-crawl-domain');
                const domain = domainInput.value.trim();
                if (!domain) {
                    showToast('Bitte geben Sie eine Domain ein', 'warning');
                    return;
                }

                const crawlIcon = document.getElementById('crawl-icon');
                const crawlText = document.getElementById('crawl-btn-text');

                btnCrawl.disabled = true;
                crawlIcon.className = 'fas fa-spinner fa-spin';
                crawlText.textContent = 'Generiere...';

                try {
                    const agentName = (document.getElementById('agent-name').value || '').trim();
                    const result = await fetchAPI('generate-agent-prompt', {
                        method: 'POST',
                        body: { domain: domain, agent_name: agentName }
                    });

                    if (result.system_prompt) {
                        document.getElementById('agent-prompt').value = result.system_prompt;
                    }
                    if (result.first_message) {
                        document.getElementById('agent-first-message').value = result.first_message;
                    }

                    showToast('Prompt erfolgreich generiert!', 'success');
                } catch (error) {
                    showToast(error.message || 'Fehler bei der Prompt-Generierung', 'error');
                } finally {
                    btnCrawl.disabled = false;
                    crawlIcon.className = 'fas fa-robot';
                    crawlText.textContent = 'Prompt generieren';
                }
            });
        }

        // Variable tags
        app.querySelectorAll('.var-tag').forEach(tag => {
            tag.addEventListener('click', () => {
                const textarea = document.getElementById('agent-first-message');
                const cursorPos = textarea.selectionStart;
                const textBefore = textarea.value.substring(0, cursorPos);
                const textAfter = textarea.value.substring(cursorPos);
                textarea.value = textBefore + tag.dataset.var + textAfter;
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = cursorPos + tag.dataset.var.length;
            });
        });
    }

    function resetAgentForm() {
        const form = document.getElementById('form-agent');
        form.reset();
        document.getElementById('agent-id').value = '';
        document.getElementById('knowledge-urls-list').innerHTML = '';
        document.getElementById('knowledge-files-list').innerHTML = '';
    }

    async function editAgent(id) {
        try {
            const data = await fetchAPI('agents/' + id);
            const agent = data.agent;

            document.getElementById('agent-id').value = agent.id;
            document.getElementById('agent-name').value = agent.name;
            document.getElementById('agent-voice').value = agent.voice_id || '';
            document.getElementById('agent-language').value = agent.language || 'de';
            document.getElementById('agent-prompt').value = agent.system_prompt || '';
            document.getElementById('agent-first-message').value = agent.first_message || '';

            // Render knowledge URLs
            const urlsList = document.getElementById('knowledge-urls-list');
            urlsList.innerHTML = (agent.knowledge_urls || []).map(url => `
                <li class="knowledge-item">
                    <a href="${escAttr(url)}" target="_blank">${esc(url)}</a>
                    <button type="button" class="btn-icon-remove" onclick="this.parentElement.remove()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </li>
            `).join('');

            // Render knowledge files
            const filesList = document.getElementById('knowledge-files-list');
            filesList.innerHTML = (agent.knowledge_files || []).map(file => `
                <li class="knowledge-item">
                    <span>${esc(file.name)}</span>
                    <small>${formatFileSize(file.size)}</small>
                </li>
            `).join('');

            document.getElementById('modal-agent-title').textContent = 'Agent bearbeiten';
            openModal('modal-agent');
        } catch (error) {
            showToast('Fehler beim Laden des Agents', 'error');
        }
    }

    async function saveAgent() {
        const btn = document.getElementById('btn-save-agent');
        const btnText = btn.querySelector('.btn-text');
        const btnLoading = btn.querySelector('.btn-loading');

        btnText.hidden = true;
        btnLoading.hidden = false;
        btn.disabled = true;

        const id = document.getElementById('agent-id').value;
        const voiceSelect = document.getElementById('agent-voice');
        const selectedVoice = voices.find(v => v.voice_id === voiceSelect.value);

        // Collect knowledge URLs
        const urlsList = document.getElementById('knowledge-urls-list');
        const knowledgeUrls = Array.from(urlsList.querySelectorAll('.knowledge-item a'))
            .map(a => a.href);

        // Vereinfachtes Datenmodell: Technische Einstellungen (LLM, TTS, Tools)
        // werden über AI-Assistent > Telefon Outbound konfiguriert
        const data = {
            name: document.getElementById('agent-name').value,
            voice_id: voiceSelect.value,
            voice_name: selectedVoice ? selectedVoice.name : '',
            language: document.getElementById('agent-language').value,
            system_prompt: document.getElementById('agent-prompt').value,
            first_message: document.getElementById('agent-first-message').value,
            knowledge_urls: knowledgeUrls,
        };

        try {
            let result;
            if (id) {
                result = await fetchAPI('agents/' + id, { method: 'PUT', body: data });
            } else {
                result = await fetchAPI('agents', { method: 'POST', body: data });
            }

            showToast(STRINGS.saveSuccess || 'Erfolgreich gespeichert', 'success');

            // Show warning if ElevenLabs sync failed
            if (result && result.warning) {
                setTimeout(() => showToast(result.warning, 'warning'), 500);
            }

            closeModal('modal-agent');
            loadAgents();
            loadLists(); // Refresh lists to update agent dropdowns
        } catch (error) {
            showToast(error.message || STRINGS.saveError || 'Fehler beim Speichern', 'error');
        } finally {
            btnText.hidden = false;
            btnLoading.hidden = true;
            btn.disabled = false;
        }
    }

    window.editAgent = editAgent;

    window.deleteAgent = async function(id) {
        if (!confirm(STRINGS.confirmDelete || 'Sind Sie sicher?')) return;

        try {
            await fetchAPI('agents/' + id, { method: 'DELETE' });
            showToast(STRINGS.deleteSuccess || 'Erfolgreich gelöscht', 'success');
            loadAgents();
        } catch (error) {
            showToast(error.message || STRINGS.deleteError || 'Fehler beim Löschen', 'error');
        }
    };

    function addKnowledgeUrl() {
        const input = document.getElementById('knowledge-url-input');
        const url = input.value.trim();

        if (!url) return;
        if (!isValidUrl(url)) {
            showToast('Bitte geben Sie eine gültige URL ein', 'error');
            return;
        }

        const urlsList = document.getElementById('knowledge-urls-list');
        const li = document.createElement('li');
        li.className = 'knowledge-item';
        li.innerHTML = `
            <a href="${escAttr(url)}" target="_blank">${esc(url)}</a>
            <button type="button" class="btn-icon-remove" onclick="this.parentElement.remove()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;
        urlsList.appendChild(li);
        input.value = '';
    }

    async function handleFileUpload(files) {
        const agentId = document.getElementById('agent-id').value;
        if (!agentId) {
            showToast('Bitte speichern Sie den Agent zuerst', 'warning');
            return;
        }

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('agent_id', agentId);

            try {
                await fetchAPI('elevenlabs/knowledge/upload', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-WP-Nonce': NONCE }
                });

                const filesList = document.getElementById('knowledge-files-list');
                const li = document.createElement('li');
                li.className = 'knowledge-item';
                li.innerHTML = `
                    <span>${esc(file.name)}</span>
                    <small>${formatFileSize(file.size)}</small>
                `;
                filesList.appendChild(li);

                showToast(STRINGS.uploadSuccess || 'Datei erfolgreich hochgeladen', 'success');
            } catch (error) {
                showToast(error.message || STRINGS.uploadError || 'Fehler beim Hochladen', 'error');
            }
        }
    }

    // =====================================================
    // VOICES & MODELS
    // =====================================================

    async function loadVoices() {
        try {
            // Immer frisch laden (refresh=1 loescht Backend-Cache)
            const data = await fetchAPI('elevenlabs/voices?refresh=1');
            voices = data.voices || [];
            populateVoiceSelect();
        } catch (error) {
            console.error('Error loading voices:', error);
        }
    }

    function populateVoiceSelect() {
        const select = document.getElementById('agent-voice');
        select.innerHTML = '<option value="">Stimme auswählen...</option>';

        // Group voices by category
        const categories = {};
        voices.forEach(voice => {
            const cat = voice.category || 'Sonstige';
            if (!categories[cat]) categories[cat] = [];
            categories[cat].push(voice);
        });

        Object.entries(categories).sort().forEach(([category, categoryVoices]) => {
            const optgroup = document.createElement('optgroup');
            optgroup.label = category.charAt(0).toUpperCase() + category.slice(1);

            categoryVoices.sort((a, b) => a.name.localeCompare(b.name)).forEach(voice => {
                const option = document.createElement('option');
                option.value = voice.voice_id;
                option.textContent = voice.name;
                optgroup.appendChild(option);
            });

            select.appendChild(optgroup);
        });
    }

    // LLM models are now hardcoded in HTML as they are different from TTS models
    // The ElevenLabs /models endpoint returns TTS models, not LLM models for conversational AI
    // Keeping these functions for backward compatibility but they no longer modify the dropdown
    async function loadModels() {
        // No longer needed - LLM options are hardcoded in HTML
        // ElevenLabs Conversational AI uses LLMs like gpt-4o, claude-3-5-sonnet, etc.
        // not TTS models like eleven_turbo_v2_5
    }

    function populateModelSelect() {
        // LLM select is populated via HTML with valid options
        // This function is kept for backward compatibility
    }

    // =====================================================
    // CUSTOMER PHONE NUMBER
    // =====================================================

    async function loadCustomerPhoneNumber() {
        try {
            const data = await fetchAPI('customer-phone-number');
            const display = document.getElementById('agent-phone-number-display');
            const hidden = document.getElementById('agent-phone-number-id');
            if (data.phone_number_id) {
                display.textContent = data.phone_number_id;
                display.style.color = '#1e293b';
                if (hidden) hidden.value = data.phone_number_id;
            } else {
                display.textContent = 'Keine Telefonnummer-ID in Kundeneinstellungen konfiguriert.';
                display.style.color = '#ef4444';
                if (hidden) hidden.value = '';
            }
        } catch (error) {
            console.error('Error loading customer phone number:', error);
        }
    }

    // =====================================================
    // AUDIO TAGS (EXPRESSIVE MODE)
    // =====================================================

    function renderAudioTags(tags) {
        const container = document.getElementById('audio-tags-container');
        container.innerHTML = '';
        if (Array.isArray(tags)) {
            tags.forEach(tag => addAudioTagRow(tag.tag || '', tag.description || ''));
        }
    }

    function addAudioTagRow(tagValue, descValue) {
        const container = document.getElementById('audio-tags-container');
        const row = document.createElement('div');
        row.className = 'audio-tag-row';
        row.style.cssText = 'display:flex; gap:0.5rem; margin-bottom:0.5rem; align-items:center;';
        row.innerHTML = `
            <input type="text" class="form-input audio-tag-name" placeholder="Tag (z.B. lacht)" value="${escAttr(tagValue)}" style="flex:1;">
            <input type="text" class="form-input audio-tag-desc" placeholder="Beschreibung (optional)" value="${escAttr(descValue)}" style="flex:2;">
            <button type="button" class="btn-icon-remove" onclick="this.parentElement.remove()" style="flex-shrink:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;
        container.appendChild(row);
    }

    function collectAudioTags() {
        const rows = document.querySelectorAll('#audio-tags-container .audio-tag-row');
        const tags = [];
        rows.forEach(row => {
            const tag = row.querySelector('.audio-tag-name').value.trim();
            const desc = row.querySelector('.audio-tag-desc').value.trim();
            if (tag) {
                const entry = { tag: tag };
                if (desc) entry.description = desc;
                tags.push(entry);
            }
        });
        return tags;
    }

    function collectBuiltInTools() {
        const tools = [];
        if (document.getElementById('agent-end-call').checked) {
            tools.push('end_call');
        }
        return tools;
    }

    // =====================================================
    // PHONE LISTS
    // =====================================================

    async function loadLists() {
        const container = document.getElementById('lists-container');
        container.innerHTML = '<div class="loading-spinner">Laden...</div>';

        try {
            const data = await fetchAPI('lists');
            lists = data.lists || [];
            renderLists();
            populateAgentSelects();
            populateFilterSelects();
        } catch (error) {
            container.innerHTML = '<div class="error-message">Fehler beim Laden der Listen</div>';
            showToast('Fehler beim Laden der Listen', 'error');
        }
    }

    function renderLists() {
        const container = document.getElementById('lists-container');

        if (lists.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    <p>Keine Telefonlisten vorhanden</p>
                    <button class="btn btn-primary" onclick="document.getElementById('btn-new-list').click()">
                        Erste Liste erstellen
                    </button>
                </div>
            `;
            return;
        }

        container.innerHTML = lists.map(list => {
            const progress = list.total_entries > 0
                ? Math.round((list.completed_entries / list.total_entries) * 100)
                : 0;

            return `
                <div class="card list-card" data-id="${list.id}">
                    <div class="card-header">
                        <h3 class="card-title">${esc(list.name)}</h3>
                        <span class="status-badge status-${list.status}">${getListStatusLabel(list.status)}</span>
                    </div>
                    <div class="card-body">
                        <div class="card-info">
                            <span class="info-label">Agent:</span>
                            <span class="info-value">${esc(list.agent_name || 'Nicht zugewiesen')}</span>
                        </div>
                        <div class="card-info">
                            <span class="info-label">Einträge:</span>
                            <span class="info-value">${list.total_entries}</span>
                        </div>
                        <div class="card-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${progress}%"></div>
                            </div>
                            <span class="progress-text">${list.completed_entries} / ${list.total_entries} abgeschlossen</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-small btn-secondary" onclick="editList(${list.id})">
                            <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Bearbeiten
                        </button>
                        <button class="btn btn-small btn-danger" onclick="deleteList(${list.id})">
                            <svg class="btn-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function populateAgentSelects() {
        const selects = app.querySelectorAll('#list-agent, #filter-agent');
        selects.forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Agent auswählen...</option>';

            agents.forEach(agent => {
                const option = document.createElement('option');
                option.value = agent.id;
                option.textContent = agent.name;
                select.appendChild(option);
            });

            select.value = currentValue;
        });
    }

    function setupListForm() {
        const btnNew = document.getElementById('btn-new-list');
        const btnSave = document.getElementById('btn-save-list');
        const btnDelete = document.getElementById('btn-delete-list');
        const btnAddEntry = document.getElementById('btn-add-entry');
        const btnImportCSV = document.getElementById('btn-import-csv');
        const btnBulkDelete = document.getElementById('btn-bulk-delete');
        const selectAll = document.getElementById('select-all-entries');
        const btnStartCampaign = document.getElementById('btn-start-campaign');
        const btnPauseCampaign = document.getElementById('btn-pause-campaign');

        // New list
        btnNew.addEventListener('click', () => {
            resetListForm();
            document.getElementById('modal-list-title').textContent = 'Neue Telefonliste';
            document.getElementById('btn-delete-list').hidden = true;
            document.getElementById('list-entries-section').hidden = true;
            document.getElementById('campaign-controls-section').hidden = true;
            openModal('modal-list');
        });

        // Save list
        btnSave.addEventListener('click', saveList);

        // Delete list
        btnDelete.addEventListener('click', async () => {
            const listId = document.getElementById('list-id').value;
            if (!listId) return;

            if (!confirm('Möchten Sie diese Liste wirklich löschen? Alle Einträge werden ebenfalls gelöscht.')) {
                return;
            }

            try {
                await fetchAPI('lists/' + listId, { method: 'DELETE' });
                showToast(STRINGS.deleteSuccess || 'Erfolgreich gelöscht', 'success');
                closeModal('modal-list');
                loadLists();
            } catch (error) {
                showToast(error.message || STRINGS.deleteError || 'Fehler beim Löschen', 'error');
            }
        });

        // Add entry
        btnAddEntry.addEventListener('click', () => {
            resetEntryForm();
            document.getElementById('entry-list-id').value = document.getElementById('list-id').value;
            document.getElementById('modal-entry-title').textContent = 'Neuer Kontakt';
            openModal('modal-entry');
        });

        // Import CSV
        btnImportCSV.addEventListener('click', () => {
            document.getElementById('csv-file-input').click();
        });

        // Bulk delete
        btnBulkDelete.addEventListener('click', bulkDeleteEntries);

        // Select all
        selectAll.addEventListener('change', () => {
            const checkboxes = app.querySelectorAll('#entries-tbody input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                if (selectAll.checked) {
                    selectedEntries.add(parseInt(cb.value));
                } else {
                    selectedEntries.delete(parseInt(cb.value));
                }
            });
            updateBulkActionsVisibility();
        });

        // Campaign controls
        btnStartCampaign.addEventListener('click', startCampaign);
        btnPauseCampaign.addEventListener('click', pauseCampaign);
    }

    function resetListForm() {
        document.getElementById('list-id').value = '';
        document.getElementById('list-name').value = '';
        document.getElementById('list-agent').value = '';
        document.getElementById('entries-tbody').innerHTML = '<tr class="empty-row"><td colspan="7">Keine Einträge vorhanden</td></tr>';
        document.getElementById('entries-count').textContent = '(0)';
        currentListEntries = [];
        selectedEntries.clear();
        updateBulkActionsVisibility();
    }

    async function editList(id) {
        try {
            const data = await fetchAPI('lists/' + id);
            const list = data.list;

            document.getElementById('list-id').value = list.id;
            document.getElementById('list-name').value = list.name;
            document.getElementById('list-agent').value = list.agent_id || '';

            currentListEntries = list.entries || [];
            renderEntries();

            // Show sections
            document.getElementById('list-entries-section').hidden = false;
            document.getElementById('campaign-controls-section').hidden = false;
            document.getElementById('btn-delete-list').hidden = false;

            // Update campaign status
            updateCampaignStatus(list);

            document.getElementById('modal-list-title').textContent = 'Telefonliste bearbeiten';
            openModal('modal-list');
        } catch (error) {
            showToast('Fehler beim Laden der Liste', 'error');
        }
    }

    window.editList = editList;

    window.deleteList = async function(id) {
        if (!confirm('Möchten Sie diese Liste wirklich löschen? Alle Einträge werden ebenfalls gelöscht.')) {
            return;
        }

        try {
            await fetchAPI('lists/' + id, { method: 'DELETE' });
            showToast(STRINGS.deleteSuccess || 'Erfolgreich gelöscht', 'success');
            loadLists();
        } catch (error) {
            showToast(error.message || STRINGS.deleteError || 'Fehler beim Löschen', 'error');
        }
    };

    async function saveList() {
        const btn = document.getElementById('btn-save-list');
        const btnText = btn.querySelector('.btn-text');
        const btnLoading = btn.querySelector('.btn-loading');

        btnText.hidden = true;
        btnLoading.hidden = false;
        btn.disabled = true;

        const id = document.getElementById('list-id').value;
        const data = {
            name: document.getElementById('list-name').value,
            agent_id: document.getElementById('list-agent').value
        };

        try {
            let result;
            if (id) {
                result = await fetchAPI('lists/' + id, { method: 'PUT', body: data });
            } else {
                result = await fetchAPI('lists', { method: 'POST', body: data });
                // Set the new list ID and show entries section
                document.getElementById('list-id').value = result.list.id;
                document.getElementById('list-entries-section').hidden = false;
                document.getElementById('campaign-controls-section').hidden = false;
                document.getElementById('btn-delete-list').hidden = false;
            }

            showToast(STRINGS.saveSuccess || 'Erfolgreich gespeichert', 'success');
            loadLists();
        } catch (error) {
            showToast(error.message || STRINGS.saveError || 'Fehler beim Speichern', 'error');
        } finally {
            btnText.hidden = false;
            btnLoading.hidden = true;
            btn.disabled = false;
        }
    }

    function renderEntries() {
        const tbody = document.getElementById('entries-tbody');
        document.getElementById('entries-count').textContent = `(${currentListEntries.length})`;

        if (currentListEntries.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="7">Keine Einträge vorhanden</td></tr>';
            return;
        }

        tbody.innerHTML = currentListEntries.map(entry => `
            <tr data-id="${entry.id}">
                <td class="col-checkbox">
                    <input type="checkbox" value="${entry.id}" ${selectedEntries.has(entry.id) ? 'checked' : ''}>
                </td>
                <td>${esc(entry.company_name || '-')}</td>
                <td>${esc(entry.contact_person || '-')}</td>
                <td>${esc(entry.phone_number)}</td>
                <td>${esc(entry.email || '-')}</td>
                <td><span class="status-badge status-${entry.call_status}">${getCallStatusLabel(entry.call_status)}</span></td>
                <td class="col-actions">
                    <button class="btn-icon" onclick="editEntry(${entry.id})" title="Bearbeiten">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon btn-icon-danger" onclick="deleteEntry(${entry.id})" title="Löschen">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                    <button class="btn-icon btn-icon-success" onclick="callEntry(${entry.id})" title="Anrufen">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                    </button>
                </td>
            </tr>
        `).join('');

        // Add checkbox listeners
        tbody.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => {
                const entryId = parseInt(cb.value);
                if (cb.checked) {
                    selectedEntries.add(entryId);
                } else {
                    selectedEntries.delete(entryId);
                }
                updateBulkActionsVisibility();
            });
        });
    }

    function updateBulkActionsVisibility() {
        const bulkActions = document.getElementById('entries-bulk-actions');
        const selectedCount = document.getElementById('selected-count');

        if (selectedEntries.size > 0) {
            bulkActions.hidden = false;
            selectedCount.textContent = selectedEntries.size;
        } else {
            bulkActions.hidden = true;
        }

        // Update select all checkbox
        const selectAll = document.getElementById('select-all-entries');
        const checkboxes = app.querySelectorAll('#entries-tbody input[type="checkbox"]');
        if (checkboxes.length > 0) {
            selectAll.checked = selectedEntries.size === checkboxes.length;
            selectAll.indeterminate = selectedEntries.size > 0 && selectedEntries.size < checkboxes.length;
        }
    }

    async function bulkDeleteEntries() {
        if (selectedEntries.size === 0) {
            showToast(STRINGS.noEntriesSelected || 'Keine Einträge ausgewählt', 'warning');
            return;
        }

        if (!confirm(STRINGS.confirmBulkDelete || 'Ausgewählte Einträge wirklich löschen?')) {
            return;
        }

        try {
            await fetchAPI('entries/bulk-delete', {
                method: 'POST',
                body: { ids: Array.from(selectedEntries) }
            });

            showToast(STRINGS.deleteSuccess || 'Erfolgreich gelöscht', 'success');
            selectedEntries.clear();

            // Reload list
            const listId = document.getElementById('list-id').value;
            if (listId) {
                const data = await fetchAPI('lists/' + listId);
                currentListEntries = data.list.entries || [];
                renderEntries();
            }

            loadLists();
        } catch (error) {
            showToast(error.message || STRINGS.deleteError || 'Fehler beim Löschen', 'error');
        }
    }

    function updateCampaignStatus(list) {
        const statusEl = document.getElementById('campaign-status');
        const progressEl = document.getElementById('campaign-progress');
        const progressTextEl = document.getElementById('campaign-progress-text');
        const btnStart = document.getElementById('btn-start-campaign');
        const btnPause = document.getElementById('btn-pause-campaign');

        const progress = list.total_entries > 0
            ? Math.round((list.completed_entries / list.total_entries) * 100)
            : 0;

        statusEl.textContent = getListStatusLabel(list.status);
        statusEl.className = 'status-value status-' + list.status;
        progressEl.style.width = progress + '%';
        progressTextEl.textContent = `${list.completed_entries} / ${list.total_entries}`;

        // Show/hide buttons based on status
        if (list.status === 'in_progress') {
            btnStart.hidden = true;
            btnPause.hidden = false;
        } else {
            btnStart.hidden = false;
            btnPause.hidden = true;
        }
    }

    async function startCampaign() {
        const listId = document.getElementById('list-id').value;
        const agentId = document.getElementById('list-agent').value;

        if (!agentId) {
            showToast(STRINGS.noAgentAssigned || 'Bitte weisen Sie zuerst einen Agent zu.', 'warning');
            return;
        }

        if (!confirm(STRINGS.confirmStartCampaign || 'Kampagne wirklich starten?')) {
            return;
        }

        try {
            const result = await fetchAPI('campaign/start', {
                method: 'POST',
                body: { list_id: listId }
            });

            showToast(result.message || 'Kampagne gestartet', 'success');

            // Update UI
            document.getElementById('btn-start-campaign').hidden = true;
            document.getElementById('btn-pause-campaign').hidden = false;
            document.getElementById('campaign-status').textContent = 'In Bearbeitung';
            document.getElementById('campaign-status').className = 'status-value status-in_progress';

            loadLists();
        } catch (error) {
            showToast(error.message || 'Fehler beim Starten', 'error');
        }
    }

    async function pauseCampaign() {
        const listId = document.getElementById('list-id').value;

        try {
            await fetchAPI('campaign/pause', {
                method: 'POST',
                body: { list_id: listId }
            });

            showToast('Kampagne pausiert', 'success');

            // Update UI
            document.getElementById('btn-start-campaign').hidden = false;
            document.getElementById('btn-pause-campaign').hidden = true;
            document.getElementById('campaign-status').textContent = 'Pausiert';
            document.getElementById('campaign-status').className = 'status-value status-paused';

            loadLists();
        } catch (error) {
            showToast(error.message || 'Fehler beim Pausieren', 'error');
        }
    }

    // =====================================================
    // ENTRIES
    // =====================================================

    function setupEntryForm() {
        const form = document.getElementById('form-entry');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveEntry();
        });
    }

    function resetEntryForm() {
        document.getElementById('entry-id').value = '';
        document.getElementById('entry-company').value = '';
        document.getElementById('entry-contact').value = '';
        document.getElementById('entry-phone').value = '';
        document.getElementById('entry-email').value = '';
    }

    window.editEntry = function(id) {
        const entry = currentListEntries.find(e => e.id === id);
        if (!entry) return;

        document.getElementById('entry-id').value = entry.id;
        document.getElementById('entry-list-id').value = document.getElementById('list-id').value;
        document.getElementById('entry-company').value = entry.company_name || '';
        document.getElementById('entry-contact').value = entry.contact_person || '';
        document.getElementById('entry-phone').value = entry.phone_number || '';
        document.getElementById('entry-email').value = entry.email || '';

        document.getElementById('modal-entry-title').textContent = 'Kontakt bearbeiten';
        openModal('modal-entry');
    };

    window.deleteEntry = async function(id) {
        if (!confirm(STRINGS.confirmDelete || 'Eintrag wirklich löschen?')) {
            return;
        }

        try {
            await fetchAPI('entries/' + id, { method: 'DELETE' });
            showToast(STRINGS.deleteSuccess || 'Erfolgreich gelöscht', 'success');

            // Update local list
            currentListEntries = currentListEntries.filter(e => e.id !== id);
            renderEntries();
            loadLists();
        } catch (error) {
            showToast(error.message || STRINGS.deleteError || 'Fehler beim Löschen', 'error');
        }
    };

    window.callEntry = async function(id) {
        if (!confirm('Diesen Kontakt jetzt anrufen?')) {
            return;
        }

        try {
            await fetchAPI('call', {
                method: 'POST',
                body: { entry_id: id }
            });

            showToast('Anruf wird gestartet...', 'success');

            // Update entry status in UI
            const entry = currentListEntries.find(e => e.id === id);
            if (entry) {
                entry.call_status = 'calling';
                renderEntries();
            }
        } catch (error) {
            showToast(error.message || 'Fehler beim Starten des Anrufs', 'error');
        }
    };

    async function saveEntry() {
        const id = document.getElementById('entry-id').value;
        const listId = document.getElementById('entry-list-id').value;

        const data = {
            company_name: document.getElementById('entry-company').value,
            contact_person: document.getElementById('entry-contact').value,
            phone_number: document.getElementById('entry-phone').value,
            email: document.getElementById('entry-email').value
        };

        try {
            let result;
            if (id) {
                result = await fetchAPI('entries/' + id, { method: 'PUT', body: data });

                // Update in local list
                const index = currentListEntries.findIndex(e => e.id === parseInt(id));
                if (index !== -1) {
                    currentListEntries[index] = { ...currentListEntries[index], ...data };
                }
            } else {
                result = await fetchAPI('lists/' + listId + '/entries', { method: 'POST', body: data });
                currentListEntries.push(result.entry);
            }

            showToast(STRINGS.saveSuccess || 'Erfolgreich gespeichert', 'success');
            closeModal('modal-entry');
            renderEntries();
            loadLists();
        } catch (error) {
            showToast(error.message || STRINGS.saveError || 'Fehler beim Speichern', 'error');
        }
    }

    // =====================================================
    // CSV IMPORT
    // =====================================================

    function setupCSVImport() {
        const fileInput = document.getElementById('csv-file-input');
        const btnConfirm = document.getElementById('btn-confirm-import');
        let parsedData = [];

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                parsedData = parseCSV(e.target.result);
                showCSVPreview(parsedData);
                openModal('modal-csv');
            };
            reader.readAsText(file);
            fileInput.value = '';
        });

        btnConfirm.addEventListener('click', async () => {
            if (parsedData.length === 0) return;

            const listId = document.getElementById('list-id').value;

            try {
                const result = await fetchAPI('lists/' + listId + '/entries/bulk', {
                    method: 'POST',
                    body: { entries: parsedData }
                });

                showToast(`${result.added} Einträge importiert`, 'success');

                if (result.errors && result.errors.length > 0) {
                    console.warn('Import errors:', result.errors);
                    showToast(`${result.errors.length} Fehler beim Import`, 'warning');
                }

                closeModal('modal-csv');

                // Reload entries
                const data = await fetchAPI('lists/' + listId);
                currentListEntries = data.list.entries || [];
                renderEntries();
                loadLists();
            } catch (error) {
                showToast(error.message || 'Fehler beim Import', 'error');
            }
        });
    }

    function parseCSV(text) {
        const lines = text.split(/\r?\n/).filter(line => line.trim());
        if (lines.length < 2) return [];

        // Parse header
        const header = lines[0].split(/[,;]/).map(h => h.trim().toLowerCase().replace(/['"]/g, ''));

        // Map column names
        const columnMap = {
            'firma': 'company_name',
            'company': 'company_name',
            'company_name': 'company_name',
            'firmenname': 'company_name',
            'ansprechpartner': 'contact_person',
            'contact': 'contact_person',
            'contact_person': 'contact_person',
            'name': 'contact_person',
            'telefon': 'phone_number',
            'phone': 'phone_number',
            'phone_number': 'phone_number',
            'telefonnummer': 'phone_number',
            'tel': 'phone_number',
            'email': 'email',
            'e-mail': 'email',
            'mail': 'email'
        };

        const columns = header.map(h => columnMap[h] || null);

        // Parse data rows
        const entries = [];
        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(/[,;]/).map(v => v.trim().replace(/^["']|["']$/g, ''));
            const entry = {};

            columns.forEach((col, idx) => {
                if (col && values[idx]) {
                    entry[col] = values[idx];
                }
            });

            if (entry.phone_number) {
                entries.push(entry);
            }
        }

        return entries;
    }

    function showCSVPreview(data) {
        const preview = document.getElementById('csv-preview');
        const dataPreview = document.getElementById('csv-data-preview');
        const rowsCount = document.getElementById('csv-rows-count');
        const btnConfirm = document.getElementById('btn-confirm-import');

        if (data.length === 0) {
            preview.hidden = false;
            dataPreview.hidden = true;
            btnConfirm.disabled = true;
            return;
        }

        preview.hidden = true;
        dataPreview.hidden = false;
        rowsCount.textContent = data.length;
        btnConfirm.disabled = false;

        // Show preview table (first 5 rows)
        const tableHtml = `
            <table class="data-table csv-preview-table">
                <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Ansprechpartner</th>
                        <th>Telefon</th>
                        <th>E-Mail</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.slice(0, 5).map(entry => `
                        <tr>
                            <td>${esc(entry.company_name || '-')}</td>
                            <td>${esc(entry.contact_person || '-')}</td>
                            <td>${esc(entry.phone_number)}</td>
                            <td>${esc(entry.email || '-')}</td>
                        </tr>
                    `).join('')}
                    ${data.length > 5 ? `<tr><td colspan="4"><em>... und ${data.length - 5} weitere</em></td></tr>` : ''}
                </tbody>
            </table>
        `;

        dataPreview.querySelector('.csv-table-preview').innerHTML = tableHtml;
    }

    // =====================================================
    // CALLS LOG
    // =====================================================

    async function loadCalls(page = 1) {
        const tbody = document.querySelector('#calls-table tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="loading-cell">Laden...</td></tr>';

        const agentFilter = document.getElementById('filter-agent').value;
        const listFilter = document.getElementById('filter-list').value;

        let url = `calls?page=${page}&per_page=20`;
        if (agentFilter) url += `&agent_id=${agentFilter}`;
        if (listFilter) url += `&list_id=${listFilter}`;

        try {
            const data = await fetchAPI(url);
            calls = data.calls || [];
            totalCallsPages = data.pages || 1;
            currentCallsPage = page;
            renderCalls();
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="8" class="error-cell">Fehler beim Laden</td></tr>';
            showToast('Fehler beim Laden der Anrufe', 'error');
        }
    }

    // Track selected calls for bulk actions
    let selectedCalls = new Set();

    function renderCalls() {
        const tbody = document.querySelector('#calls-table tbody');

        // Reset selection
        selectedCalls.clear();
        updateCallsBulkActions();
        const selectAllCheckbox = document.getElementById('calls-select-all');
        if (selectAllCheckbox) selectAllCheckbox.checked = false;

        if (calls.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-cell">Keine Anrufe vorhanden</td></tr>';
            return;
        }

        tbody.innerHTML = calls.map(call => {
            const agent = agents.find(a => a.id == call.agent_id);

            // Truncate summary for table display (fix unicode escapes)
            const summaryPreview = fixUnicodeEscapes(call.summary_de || call.summary || '');
            const truncatedSummary = summaryPreview.length > 50
                ? summaryPreview.substring(0, 50) + '...'
                : summaryPreview;

            return `
                <tr data-call-id="${call.id}">
                    <td class="col-checkbox"><input type="checkbox" class="call-checkbox" value="${call.id}"></td>
                    <td>${esc(call.phone_number)}</td>
                    <td>${esc(agent ? agent.name : '-')}</td>
                    <td>${formatDateTime(call.started_at)}</td>
                    <td>${formatDuration(call.duration)}</td>
                    <td><span class="status-badge status-${call.status}">${getCallStatusLabel(call.status)}</span></td>
                    <td class="summary-cell" title="${escAttr(summaryPreview)}">${esc(truncatedSummary || '-')}</td>
                    <td class="col-actions">
                        <div class="call-actions">
                            <button class="btn-view-details" onclick="showCallDetail(${call.id})" title="Details anzeigen">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                Details
                            </button>
                            <button class="btn-icon btn-icon-danger" onclick="deleteSingleCall(${call.id})" title="Löschen">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        renderCallsPagination();
    }

    // Update bulk actions bar visibility and count
    function updateCallsBulkActions() {
        const bulkBar = document.getElementById('calls-bulk-actions');
        const countEl = document.getElementById('calls-selected-count');
        if (!bulkBar) return;

        if (selectedCalls.size > 0) {
            bulkBar.style.display = 'flex';
            countEl.textContent = selectedCalls.size + ' ausgewählt';
        } else {
            bulkBar.style.display = 'none';
        }
    }

    // Delete a single call
    window.deleteSingleCall = async function(callId) {
        if (!confirm('Möchten Sie diesen Anruf wirklich löschen?')) return;

        try {
            await fetchAPI('calls/' + callId, { method: 'DELETE' });
            showToast('Anruf gelöscht', 'success');
            loadCalls(currentCallsPage);
        } catch (err) {
            console.error('Delete call error:', err);
            showToast('Fehler beim Löschen', 'error');
        }
    };

    // Batch delete selected calls
    async function batchDeleteCalls() {
        const ids = Array.from(selectedCalls);
        if (ids.length === 0) return;

        if (!confirm('Möchten Sie ' + ids.length + ' Anruf(e) wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;

        try {
            const result = await fetchAPI('calls/batch-delete', {
                method: 'POST',
                body: { call_ids: ids }
            });

            if (result.success) {
                showToast(result.deleted + ' Anruf(e) gelöscht', 'success');
                selectedCalls.clear();
                updateCallsBulkActions();
                loadCalls(currentCallsPage);
            } else {
                showToast('Fehler beim Löschen', 'error');
            }
        } catch (err) {
            console.error('Batch delete error:', err);
            showToast('Fehler beim Löschen', 'error');
        }
    }

    // Current call being viewed in detail modal
    let currentDetailCallId = null;

    // Show call detail modal
    window.showCallDetail = async function(callId) {
        currentDetailCallId = callId;

        // Show loading state
        document.getElementById('call-detail-phone').textContent = 'Laden...';
        document.getElementById('call-detail-agent').textContent = '-';
        document.getElementById('call-detail-datetime').textContent = '-';
        document.getElementById('call-detail-duration').textContent = '-';
        document.getElementById('call-detail-status').textContent = '-';
        document.getElementById('call-detail-successful').textContent = '-';
        document.getElementById('call-detail-summary-de').innerHTML = '<p class="text-muted">Laden...</p>';
        document.getElementById('call-detail-transcript').innerHTML = '<p class="text-muted">Laden...</p>';
        document.getElementById('call-detail-audio-section').style.display = 'none';

        openModal('modal-call-detail');

        try {
            const data = await fetchAPI('calls/' + callId);
            const call = data.call;

            // Find agent name
            const agent = agents.find(a => a.id == call.agent_id);

            // Populate basic info
            document.getElementById('call-detail-phone').textContent = call.phone_number || '-';
            document.getElementById('call-detail-agent').textContent = agent ? agent.name : '-';
            document.getElementById('call-detail-datetime').textContent = formatDateTime(call.started_at);
            document.getElementById('call-detail-duration').textContent = formatDuration(call.duration);

            // Status with badge
            const statusEl = document.getElementById('call-detail-status');
            statusEl.innerHTML = `<span class="status-badge status-${call.status}">${getCallStatusLabel(call.status)}</span>`;

            // Call successful
            const successEl = document.getElementById('call-detail-successful');
            if (call.call_successful === '1' || call.call_successful === true) {
                successEl.innerHTML = '<span class="text-success">Ja</span>';
            } else if (call.call_successful === '0' || call.call_successful === false) {
                successEl.innerHTML = '<span class="text-danger">Nein</span>';
            } else {
                successEl.textContent = '-';
            }

            // Audio player
            const audioUrl = call.audio_url || call.recording_url;
            const audioSection = document.getElementById('call-detail-audio-section');
            const audioPlayer = document.getElementById('call-detail-audio');
            const downloadBtn = document.getElementById('call-detail-download');

            // Always show audio section
            audioSection.style.display = 'block';

            if (audioUrl) {
                audioPlayer.src = audioUrl;
                audioPlayer.style.display = 'block';
                downloadBtn.href = audioUrl;
                downloadBtn.style.display = 'inline-flex';
                // Remove "no audio" message if it exists
                const noAudioMsg = audioSection.querySelector('.no-audio-message');
                if (noAudioMsg) noAudioMsg.remove();
            } else {
                audioPlayer.style.display = 'none';
                downloadBtn.style.display = 'none';
                // Show "no audio" message
                const container = audioSection.querySelector('.audio-player-container');
                if (!container.querySelector('.no-audio-message')) {
                    const noAudioEl = document.createElement('p');
                    noAudioEl.className = 'text-muted no-audio-message';
                    noAudioEl.textContent = 'Keine Aufnahme verfügbar. Klicken Sie "Aktualisieren" um die Daten von ElevenLabs abzurufen.';
                    container.appendChild(noAudioEl);
                }
            }

            // Summary (German) - fix unicode escapes
            const summaryDe = fixUnicodeEscapes(call.summary_de || '');
            document.getElementById('call-detail-summary-de').innerHTML = summaryDe
                ? `<div class="call-detail-summary">${esc(summaryDe)}</div>`
                : '<p class="text-muted">Keine deutsche Zusammenfassung verfügbar</p>';

            // Transcript
            renderTranscript(call.transcript, call.transcript_text);

        } catch (err) {
            console.error('Error loading call details:', err);
            showToast('Fehler beim Laden der Anrufdetails', 'error');
        }
    };

    // Fix unicode escape sequences that may still be in text
    // Handles: u00fc -> ü, u00e4 -> ä, etc. (ElevenLabs malformed escapes)
    function fixUnicodeEscapes(text) {
        if (!text || typeof text !== 'string') return text || '';

        // Fix \uXXXX (with backslash)
        text = text.replace(/\\u([0-9a-fA-F]{4})/g, function(m, hex) {
            return String.fromCharCode(parseInt(hex, 16));
        });

        // Fix bare u00XX embedded in words (e.g. Mu00fcller -> Müller)
        text = text.replace(/u(00[0-9a-fA-F]{2})/g, function(m, hex) {
            return String.fromCharCode(parseInt(hex, 16));
        });

        // Fix literal \n as real line breaks
        text = text.replace(/\\n/g, '\n');

        return text;
    }

    // Render transcript as chat bubbles in detail modal
    function renderTranscript(transcriptJson, transcriptText) {
        const container = document.getElementById('call-detail-transcript');

        // Try to parse JSON transcript first
        let transcript = null;
        if (transcriptJson) {
            try {
                transcript = typeof transcriptJson === 'string' ? JSON.parse(transcriptJson) : transcriptJson;
            } catch (e) {
                console.error('Failed to parse transcript JSON:', e);
            }
        }

        if (transcript && Array.isArray(transcript) && transcript.length > 0) {
            container.innerHTML = transcript.map((entry, idx) => {
                const role = (entry.role || 'unknown').toLowerCase();
                const cssClass = role === 'agent' ? 'agent' : 'user';
                const senderLabel = role === 'agent' ? '🤖 Agent' : '👤 Kunde';
                const rawMessage = entry.message || entry.text || '';
                const message = fixUnicodeEscapes(rawMessage);
                // Optional: time info if available
                const timeStr = entry.time || entry.timestamp || '';

                return `
                    <div class="chat-bubble-wrapper ${cssClass}">
                        <div class="chat-bubble-sender">${senderLabel}</div>
                        <div class="chat-bubble">${esc(message)}</div>
                        ${timeStr ? '<div class="chat-bubble-time">' + esc(timeStr) + '</div>' : ''}
                    </div>
                `;
            }).join('');

            // Auto-scroll to bottom of transcript
            container.scrollTop = container.scrollHeight;
        } else if (transcriptText) {
            // Fallback to plain text transcript with line breaks
            const fixedText = fixUnicodeEscapes(transcriptText);
            container.innerHTML = `<div class="chat-transcript-plaintext">${esc(fixedText)}</div>`;
        } else {
            container.innerHTML = '<p class="text-muted">Kein Transkript verfügbar</p>';
        }
    }

    // Toggle collapsible sections
    window.toggleCollapsible = function(header) {
        const section = header.closest('.collapsible');
        section.classList.toggle('collapsed');
    };

    // Setup call selection checkboxes (delegated events)
    document.addEventListener('DOMContentLoaded', () => {
        const callsTable = document.getElementById('calls-table');
        if (!callsTable) return;

        // Select-all checkbox
        const selectAllCb = document.getElementById('calls-select-all');
        if (selectAllCb) {
            selectAllCb.addEventListener('change', () => {
                const checkboxes = callsTable.querySelectorAll('.call-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = selectAllCb.checked;
                    const callId = parseInt(cb.value);
                    if (selectAllCb.checked) {
                        selectedCalls.add(callId);
                    } else {
                        selectedCalls.delete(callId);
                    }
                });
                updateCallsBulkActions();
            });
        }

        // Individual checkboxes (delegated)
        callsTable.addEventListener('change', (e) => {
            if (!e.target.classList.contains('call-checkbox')) return;

            const callId = parseInt(e.target.value);
            if (e.target.checked) {
                selectedCalls.add(callId);
            } else {
                selectedCalls.delete(callId);
                // Uncheck select-all if any individual is unchecked
                if (selectAllCb) selectAllCb.checked = false;
            }

            // Check if all are selected
            const allCheckboxes = callsTable.querySelectorAll('.call-checkbox');
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            if (selectAllCb && allChecked && allCheckboxes.length > 0) {
                selectAllCb.checked = true;
            }

            updateCallsBulkActions();
        });

        // Bulk delete button
        const bulkDeleteBtn = document.getElementById('btn-bulk-delete-calls');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', batchDeleteCalls);
        }
    });

    // Setup refresh button in detail modal
    document.addEventListener('DOMContentLoaded', () => {
        const refreshBtn = document.getElementById('btn-refresh-call-detail');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', async () => {
                if (!currentDetailCallId) return;

                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '<span class="btn-loading">Aktualisiere...</span>';

                try {
                    await refreshCallData(currentDetailCallId);
                    // Reload the detail view with fresh data
                    await showCallDetail(currentDetailCallId);
                    showToast('Anrufdaten aktualisiert', 'success');
                } catch (err) {
                    showToast('Fehler beim Aktualisieren', 'error');
                } finally {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = `
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Aktualisieren
                    `;
                }
            });
        }
    });

    // Setup overview refresh button
    document.addEventListener('DOMContentLoaded', () => {
        const overviewRefreshBtn = document.getElementById('btn-refresh-calls-overview');
        if (overviewRefreshBtn) {
            overviewRefreshBtn.addEventListener('click', async () => {
                overviewRefreshBtn.disabled = true;
                overviewRefreshBtn.classList.add('refreshing');
                const originalHTML = overviewRefreshBtn.innerHTML;
                overviewRefreshBtn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    Aktualisiere...
                `;

                try {
                    // Refresh all visible calls from ElevenLabs
                    let refreshCount = 0;
                    const refreshPromises = calls.map(async (call) => {
                        try {
                            await fetchAPI('calls/' + call.id + '/refresh', { method: 'POST' });
                            refreshCount++;
                        } catch (e) {
                            console.warn('Failed to refresh call ' + call.id, e);
                        }
                    });
                    await Promise.all(refreshPromises);

                    // Reload calls list with fresh data
                    await loadCalls(currentCallsPage);
                    showToast(refreshCount + ' Anrufe aktualisiert', 'success');
                } catch (err) {
                    showToast('Fehler beim Aktualisieren', 'error');
                } finally {
                    overviewRefreshBtn.disabled = false;
                    overviewRefreshBtn.classList.remove('refreshing');
                    overviewRefreshBtn.innerHTML = originalHTML;
                }
            });
        }
    });

    // Refresh call data from ElevenLabs
    window.refreshCallData = async function(callId) {
        showToast('Aktualisiere Anrufdaten von ElevenLabs...', 'info');

        try {
            const result = await fetchAPI('calls/' + callId + '/refresh', { method: 'POST' });

            if (result.success) {
                showToast('Anrufdaten erfolgreich aktualisiert', 'success');
                // Reload calls to show updated data
                loadCalls(currentCallsPage);
                return result;
            } else {
                throw new Error(result.message || result.data?.message || 'Fehler beim Aktualisieren');
            }
        } catch (err) {
            console.error('Refresh call error:', err);
            showToast('Fehler: ' + (err.message || 'Unbekannter Fehler'), 'error');
            throw err;
        }
    };

    function renderCallsPagination() {
        const container = document.getElementById('calls-pagination');

        if (totalCallsPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="pagination-controls">';

        // Previous
        html += `<button class="btn btn-small btn-secondary" ${currentCallsPage <= 1 ? 'disabled' : ''} onclick="loadCallsPage(${currentCallsPage - 1})">Zurück</button>`;

        // Page info
        html += `<span class="pagination-info">Seite ${currentCallsPage} von ${totalCallsPages}</span>`;

        // Next
        html += `<button class="btn btn-small btn-secondary" ${currentCallsPage >= totalCallsPages ? 'disabled' : ''} onclick="loadCallsPage(${currentCallsPage + 1})">Weiter</button>`;

        html += '</div>';
        container.innerHTML = html;
    }

    window.loadCallsPage = function(page) {
        loadCalls(page);
    };

    function populateFilterSelects() {
        const listSelect = document.getElementById('filter-list');
        listSelect.innerHTML = '<option value="">Alle Listen</option>';

        lists.forEach(list => {
            const option = document.createElement('option');
            option.value = list.id;
            option.textContent = list.name;
            listSelect.appendChild(option);
        });

        // Add change listeners
        document.getElementById('filter-agent').addEventListener('change', () => loadCalls(1));
        document.getElementById('filter-list').addEventListener('change', () => loadCalls(1));
    }

    // =====================================================
    // HELPER FUNCTIONS
    // =====================================================

    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function escAttr(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('de-DE');
    }

    function formatDateTime(timestamp) {
        if (!timestamp) return '-';
        const date = new Date(timestamp * 1000);
        return date.toLocaleDateString('de-DE') + ' ' + date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDuration(seconds) {
        if (!seconds) return '-';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    function formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i)) + ' ' + sizes[i];
    }

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    function getStatusLabel(status) {
        const labels = {
            'draft': 'Entwurf',
            'active': 'Aktiv',
            'paused': 'Pausiert'
        };
        return labels[status] || status;
    }

    function getListStatusLabel(status) {
        const labels = {
            'draft': 'Entwurf',
            'ready': 'Bereit',
            'in_progress': 'In Bearbeitung',
            'completed': 'Abgeschlossen',
            'paused': 'Pausiert'
        };
        return labels[status] || status;
    }

    function getCallStatusLabel(status) {
        const labels = {
            'pending': 'Ausstehend',
            'calling': 'Wird angerufen',
            'completed': 'Abgeschlossen',
            'failed': 'Fehlgeschlagen',
            'no_answer': 'Keine Antwort',
            'initiated': 'Gestartet'
        };
        return labels[status] || status;
    }

    function getLanguageLabel(code) {
        const labels = {
            'de': 'Deutsch',
            'en': 'Englisch',
            'fr': 'Französisch',
            'es': 'Spanisch',
            'it': 'Italienisch',
            'nl': 'Niederländisch',
            'pl': 'Polnisch',
            'pt': 'Portugiesisch'
        };
        return labels[code] || code;
    }

})();
