(()=>{

  const REST  = (window.SYN_TEL_CTX && SYN_TEL_CTX.rest)  || '/wp-json/synnio/v1/';
  const NONCE = (window.SYN_TEL_CTX && SYN_TEL_CTX.nonce) || '';
  const PHONE_NUMBERS = (window.SYN_TEL_CTX && SYN_TEL_CTX.phone_numbers) || [];
  const DEBUG = !!window.SYN_TEL_DEBUG || localStorage.getItem('SYN_TEL_DEBUG') === 'true';
  
  const ITEMS_PER_PAGE = 10;
  
  console.log('🎯 Synnio Telefonie Script gestartet', {
    timestamp: new Date().toISOString(),
    DEBUG,
    REST,
    ITEMS_PER_PAGE
  });

  let allCalls = [];
  let currentPage = 1;
  let totalPages = 1;
  let selectedCalls = new Set(); // NEU: Für Mehrfachauswahl
  let currentStatsMonth = new Date().getMonth() + 1;
  let currentStatsYear = new Date().getFullYear();
  let availableMonths = [];

  const $  = (s,ctx=document)=>ctx.querySelector(s);
  const $$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));

  function esc(s){ return (s??'').toString().replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }
  function escAttr(s){ return esc(s).replace(/"/g,'&quot;'); }

  async function fetchJSON(url, opt={}){
    const headers = Object.assign({'Content-Type':'application/json'}, opt.headers||{});
    if (NONCE) headers['X-WP-Nonce'] = NONCE;
    
    const res = await fetch(url, Object.assign({}, opt, {headers}));
    
    if (!res.ok) {
      let errorMsg = `HTTP ${res.status} ${res.statusText}`;
      if (res.status === 403) {
        errorMsg = 'Zugriff verweigert. Bitte neu einloggen und Seite neu laden.';
      }
      throw new Error(errorMsg);
    }
    
    const ct = res.headers.get('content-type')||'';
    return ct.includes('application/json') ? res.json() : {};
  }

  function isValidDate(dateStr) {
    if (!dateStr) return false;
    const d = new Date(dateStr);
    return !isNaN(d.getTime()) && d.getFullYear() > 1971;
  }

  function formatDate(ts) {
    if (!ts || ts === 0) return '-';
    const d = new Date(ts * 1000);
    if (!isValidDate(d)) return '-';
    return d.toLocaleDateString('de-DE');
  }

  function formatTime(ts) {
    if (!ts || ts === 0) return '-';
    const d = new Date(ts * 1000);
    if (!isValidDate(d)) return '-';
    return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
  }

  function formatDuration(seconds) {
    if (!seconds || seconds === 0) return '-';
    
    if (seconds < 60) {
      return `${seconds} Sek`;
    } else {
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      
      if (secs === 0) {
        return `${mins} Min`;
      } else {
        return `${mins}:${secs.toString().padStart(2, '0')} Min`;
      }
    }
  }

  const mount = document.getElementById('synnio-telefonie');
  if (!mount) {
    console.error('❌ Element #synnio-telefonie nicht gefunden!');
    return;
  }

  let phoneNumbersHTML = '';
  if (PHONE_NUMBERS && PHONE_NUMBERS.length > 0) {
    phoneNumbersHTML = `
      <div class="phone-info">
        ${PHONE_NUMBERS.map((phone, index) => `
          <div class="phone-item">
            <strong>Telefonnummer ${index + 1}:</strong>
            <span class="phone-number">${esc(phone.number)}</span>
            ${phone.description ? ` - ${esc(phone.description)}` : ''}
          </div>
        `).join('')}
      </div>
    `;
  }

  // Container mit Bulk-Actions
  mount.innerHTML = `
    <div class="syn-tel">
      <div class="syn-tel-container">
        <div id="tel-list-view">
          <div class="page-header">
            <div>
              <h1>Anruferübersicht</h1>
              <p class="sub">Verwalten Sie alle eingehenden Telefonanrufe</p>
              ${phoneNumbersHTML}
            </div>
          </div>

          <div class="stats-section" id="stats-section">
            <div class="stats-header">
              <h2 class="stats-title">Monatsstatistiken</h2>
              <div class="month-selector">
                <button class="month-nav-btn" id="btn-prev-month" title="Vorheriger Monat">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                  </svg>
                </button>
                <select class="month-select" id="month-select">
                  <option>Lade...</option>
                </select>
                <button class="month-nav-btn" id="btn-next-month" title="Nächster Monat">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="stats-grid" id="stats-grid">
              <div class="stat-card">
                <div class="stat-icon stat-icon-calls">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                </div>
                <div class="stat-content">
                  <span class="stat-label">Gesamtanzahl Anrufe</span>
                  <span class="stat-value" id="stat-total-calls">-</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon stat-icon-time">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <div class="stat-content">
                  <span class="stat-label">Gesamtzeit</span>
                  <span class="stat-value" id="stat-total-time">- Min</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon stat-icon-avg">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                </div>
                <div class="stat-content">
                  <span class="stat-label">Durchschn. Anrufdauer</span>
                  <span class="stat-value" id="stat-avg-duration">- Sek</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-icon stat-icon-peak">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                  </svg>
                </div>
                <div class="stat-content">
                  <span class="stat-label">Meiste Anrufe um</span>
                  <span class="stat-value" id="stat-peak-time">-</span>
                </div>
              </div>
            </div>
          </div>

          <div class="filter-section">
            <div class="filter-grid">
              <div class="filter-group">
                <label class="filter-label">Telefonnummer</label>
                <input type="text" class="filter-input" id="tel-f-phone" placeholder="+49...">
              </div>

              <div class="filter-group">
                <label class="filter-label">Name</label>
                <input type="text" class="filter-input" id="tel-f-name" placeholder="Max Mustermann">
              </div>

              <div class="filter-group">
                <label class="filter-label">Datum von</label>
                <input type="date" class="filter-input" id="tel-f-from">
              </div>

              <div class="filter-group">
                <label class="filter-label">Datum bis</label>
                <input type="date" class="filter-input" id="tel-f-to">
              </div>

              <div class="filter-group">
                <label class="filter-label" style="opacity: 0;">Filter</label>
                <button class="btn-filter" id="tel-btn-filter">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                  </svg>
                  Filtern
                </button>
              </div>
            </div>
          </div>

          <!-- NEU: Bulk Actions Bar -->
          <div class="bulk-actions-bar" id="bulk-actions-bar" style="display:none;">
            <div class="bulk-info">
              <span id="bulk-count">0</span> Anrufe ausgewählt
            </div>
            <div class="bulk-buttons">
              <button class="btn btn-bulk-delete" id="btn-bulk-delete">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Ausgewählte löschen
              </button>
              <button class="btn btn-bulk-cancel" id="btn-bulk-cancel">
                Abbrechen
              </button>
            </div>
          </div>

          <div class="table-section">
            <table>
              <thead>
                <tr>
                  <th style="width: 50px;">
                    <input type="checkbox" id="select-all-checkbox" class="call-checkbox" title="Alle auswählen">
                  </th>
                  <th>Name</th>
                  <th>Telefonnummer</th>
                  <th>Datum</th>
                  <th>Uhrzeit</th>
                  <th>Dauer</th>
                  <th>Zusammenfassung</th>
                  <th>Aktion</th>
                </tr>
              </thead>
              <tbody id="tel-tbody">
                <tr><td colspan="8" class="empty">Lade...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="pagination" id="tel-pagination" style="display:none;">
            <button class="pagination-btn" id="btn-first" title="Erste Seite">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
              </svg>
            </button>
            <button class="pagination-btn" id="btn-prev" title="Vorherige Seite">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            
            <div class="page-numbers" id="page-numbers"></div>
            
            <button class="pagination-btn" id="btn-next" title="Nächste Seite">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
            <button class="pagination-btn" id="btn-last" title="Letzte Seite">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
              </svg>
            </button>
            
            <span class="pagination-info" id="pagination-info"></span>
          </div>
        </div>

        <div id="tel-detail-view" style="display:none;"></div>
      </div>
    </div>
  `;

  const $listView = $('#tel-list-view', mount);
  const $detailView = $('#tel-detail-view', mount);
  const $tbody = $('#tel-tbody', mount);
  const $pagination = $('#tel-pagination', mount);
  const $bulkBar = $('#bulk-actions-bar', mount);

  // NEU: Bulk Actions Funktionen
  function updateBulkBar() {
    const count = selectedCalls.size;
    if (count > 0) {
      $bulkBar.style.display = 'flex';
      $('#bulk-count', mount).textContent = count;
    } else {
      $bulkBar.style.display = 'none';
    }
    updateSelectAllCheckbox();
  }

  function updateSelectAllCheckbox() {
    const checkbox = $('#select-all-checkbox', mount);
    if (!checkbox) return;
    
    const currentPageConvIds = getCurrentPageConversationIds();
    const allSelected = currentPageConvIds.length > 0 && 
                       currentPageConvIds.every(id => selectedCalls.has(id));
    const someSelected = currentPageConvIds.some(id => selectedCalls.has(id));
    
    checkbox.checked = allSelected;
    checkbox.indeterminate = someSelected && !allSelected;
  }

  function getCurrentPageConversationIds() {
    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    return allCalls.slice(start, end).map(item => item.conversation_id);
  }

  function toggleSelectAll() {
    const currentPageConvIds = getCurrentPageConversationIds();
    const allSelected = currentPageConvIds.every(id => selectedCalls.has(id));
    
    if (allSelected) {
      // Deselect all on current page
      currentPageConvIds.forEach(id => selectedCalls.delete(id));
    } else {
      // Select all on current page
      currentPageConvIds.forEach(id => selectedCalls.add(id));
    }
    
    renderCurrentPage();
    updateBulkBar();
  }

  async function bulkDeleteSelected() {
    if (selectedCalls.size === 0) return;
    
    const count = selectedCalls.size;
    if (!confirm(`${count} Anruf${count > 1 ? 'e' : ''} wirklich löschen?`)) return;
    
    const btn = $('#btn-bulk-delete', mount);
    btn.disabled = true;
    btn.innerHTML = `
      <svg class="spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
      </svg>
      Lösche...
    `;
    
    try {
      const conversationIds = Array.from(selectedCalls);
      
      const result = await fetchJSON(`${REST}calls/bulk-delete`, {
        method: 'POST',
        body: JSON.stringify({ conversation_ids: conversationIds })
      });
      
      if (result.ok) {
        // Erfolgreiche Löschung
        selectedCalls.clear();
        updateBulkBar();
        await loadList();
        
        // Erfolgsmeldung
        const msg = `${result.deleted_count} Anruf${result.deleted_count > 1 ? 'e' : ''} erfolgreich gelöscht`;
        showNotification(msg, 'success');
        
        if (result.failed_count > 0) {
          showNotification(`${result.failed_count} Anruf${result.failed_count > 1 ? 'e' : ''} konnten nicht gelöscht werden`, 'warning');
        }
      }
      
    } catch(e) {
      console.error('Fehler beim Bulk-Löschen:', e);
      alert('Fehler beim Löschen:\n\n' + (e.message || e));
    } finally {
      btn.disabled = false;
      btn.innerHTML = `
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        Ausgewählte löschen
      `;
    }
  }

  function showNotification(message, type = 'info') {
    // Einfache Notification - kann später erweitert werden
    console.log(`[${type.toUpperCase()}] ${message}`);
    // Hier könnte ein Toast/Snackbar implementiert werden
  }

  // ==================== STATISTIK-FUNKTIONEN ====================

  async function loadStats(month, year) {
    try {
      const data = await fetchJSON(`${REST}calls/stats?month=${month}&year=${year}`, {method:'GET'});

      // Werte aktualisieren
      $('#stat-total-calls', mount).textContent = data.total_calls || 0;
      $('#stat-total-time', mount).textContent = `${data.total_duration_min || 0} Min`;
      $('#stat-avg-duration', mount).textContent = `${data.avg_duration_sec || 0} Sek`;
      $('#stat-peak-time', mount).textContent = data.peak_time_range || '-';

      // Verfügbare Monate für Dropdown speichern
      if (data.available_months && data.available_months.length > 0) {
        availableMonths = data.available_months;
        updateMonthSelector();
      }

      currentStatsMonth = data.month;
      currentStatsYear = data.year;

    } catch(e) {
      console.error('Fehler beim Laden der Statistiken:', e);
      $('#stat-total-calls', mount).textContent = '-';
      $('#stat-total-time', mount).textContent = '-';
      $('#stat-avg-duration', mount).textContent = '-';
      $('#stat-peak-time', mount).textContent = '-';
    }
  }

  function updateMonthSelector() {
    const select = $('#month-select', mount);
    if (!select || availableMonths.length === 0) return;

    select.innerHTML = availableMonths.map(m =>
      `<option value="${m.year}-${m.month}" ${m.year === currentStatsYear && m.month === currentStatsMonth ? 'selected' : ''}>${esc(m.label)}</option>`
    ).join('');
  }

  function navigateMonth(direction) {
    const currentIndex = availableMonths.findIndex(m => m.year === currentStatsYear && m.month === currentStatsMonth);

    if (direction === 'prev' && currentIndex < availableMonths.length - 1) {
      const next = availableMonths[currentIndex + 1];
      loadStats(next.month, next.year);
    } else if (direction === 'next' && currentIndex > 0) {
      const prev = availableMonths[currentIndex - 1];
      loadStats(prev.month, prev.year);
    }
  }

  function onMonthSelectChange(e) {
    const [year, month] = e.target.value.split('-').map(Number);
    if (year && month) {
      loadStats(month, year);
    }
  }

  function renderPagination() {
    if (totalPages <= 1) {
      $pagination.style.display = 'none';
      return;
    }
    
    $pagination.style.display = 'flex';
    
    $('#btn-first', $pagination).disabled = currentPage === 1;
    $('#btn-prev', $pagination).disabled = currentPage === 1;
    $('#btn-next', $pagination).disabled = currentPage === totalPages;
    $('#btn-last', $pagination).disabled = currentPage === totalPages;
    
    const pageNumbers = $('#page-numbers', $pagination);
    let html = '';
    
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    
    if (endPage - startPage < 4) {
      startPage = Math.max(1, endPage - 4);
    }
    
    for (let i = startPage; i <= endPage; i++) {
      html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    
    pageNumbers.innerHTML = html;
    
    const start = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const end = Math.min(currentPage * ITEMS_PER_PAGE, allCalls.length);
    $('#pagination-info', $pagination).textContent = `${start}-${end} von ${allCalls.length} Anrufen`;
  }

  function renderCurrentPage() {
    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    const pageData = allCalls.slice(start, end);
    
    if (pageData.length === 0) {
      $tbody.innerHTML = `<tr><td colspan="8" class="empty">Keine Einträge gefunden</td></tr>`;
      return;
    }

    $tbody.innerHTML = '';
    pageData.forEach(item => {
      const name = item.caller_name || 'Unbekannt';
      const phone = item.caller_number || '-';
      const summary = item.summary_short_de || item.summary_long_de || '-';
      const startedAt = parseInt(item.started_at);
      const duration = parseInt(item.duration_sec || item.duration || 0);
      const convId = item.conversation_id;
      const isSelected = selectedCalls.has(convId);
      
      const tr = document.createElement('tr');
      if (isSelected) tr.classList.add('selected');
      
      tr.innerHTML = `
        <td class="td-checkbox">
          <input type="checkbox" class="call-checkbox" data-conv="${escAttr(convId)}" ${isSelected ? 'checked' : ''}>
        </td>
        <td class="td-name">${esc(name)}</td>
        <td class="td-phone">${esc(phone)}</td>
        <td class="td-date">${formatDate(startedAt)}</td>
        <td class="td-time">${formatTime(startedAt)}</td>
        <td class="td-duration">${formatDuration(duration)}</td>
        <td class="td-summary">
          <span title="${escAttr(summary)}">${esc(summary)}</span>
        </td>
        <td>
          <button class="btn btn-details tel-btn-detail" data-conv="${escAttr(convId)}">
            Details
          </button>
        </td>
      `;
      $tbody.appendChild(tr);
    });
    
    renderPagination();
    updateSelectAllCheckbox();
  }

  function goToPage(page) {
    currentPage = Math.max(1, Math.min(page, totalPages));
    renderCurrentPage();
    
    const tableSection = $('.table-section', mount);
    if (tableSection) {
      tableSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  async function loadList(){
    const q = new URLSearchParams();
    const phone = $('#tel-f-phone',mount).value.trim();
    const name  = $('#tel-f-name',mount).value.trim();
    const from  = $('#tel-f-from',mount).value;
    const to    = $('#tel-f-to',mount).value;
    
    if (phone) q.set('phone', phone);
    if (name)  q.set('name',  name);
    if (from)  q.set('from',  from);
    if (to)    q.set('to',    to);

    $tbody.innerHTML = `<tr><td colspan="8" class="empty">Lade...</td></tr>`;
    
    try{
      const data = await fetchJSON(`${REST}calls/list?${q.toString()}`, {method:'GET'});
      
      const list = Array.isArray(data) ? data : (data.items || data.list || data.data || []);
      
      allCalls = list.filter(item => {
        const startedAt = parseInt(item.started_at);
        return startedAt > 86400;
      });
      
      totalPages = Math.ceil(allCalls.length / ITEMS_PER_PAGE);
      currentPage = 1;
      
      // Bereinige selectedCalls von nicht mehr existierenden IDs
      const existingIds = new Set(allCalls.map(c => c.conversation_id));
      selectedCalls.forEach(id => {
        if (!existingIds.has(id)) selectedCalls.delete(id);
      });
      
      renderCurrentPage();
      updateBulkBar();
      
    }catch(e){
      console.error('Fehler beim Laden der Liste:', e);
      $tbody.innerHTML = `<tr><td colspan="8" class="empty">Fehler beim Laden: ${esc(e.message||e)}</td></tr>`;
    }
  }

  async function showDetailView(conversation_id){
    if (!conversation_id){ 
      alert('conversation_id fehlt'); 
      return; 
    }
    
    $listView.style.display = 'none';
    $detailView.style.display = 'block';
    $detailView.innerHTML = `<div class="loading">Lade Details...</div>`;
    
    try{
      const d = await fetchJSON(`${REST}calls/detail?conversation_id=${encodeURIComponent(conversation_id)}`, {method:'GET'});
      const durDisplay = formatDuration(d.duration_sec || 0);
      const startedAt = parseInt(d.started_at);
      
      $detailView.innerHTML = `
        <div class="detail-container">
          <div class="detail-page-header">
            <div class="header-content">
              <h1>Anrufdetails - ${esc(d.caller_name || 'Unbekannt')}</h1>
              <p>Telefonnummer: ${esc(d.caller_number||'-')} • ${formatDate(startedAt)} um ${formatTime(startedAt)} Uhr</p>
            </div>
            <div class="header-actions">
              <button class="btn btn-back" id="tel-btn-back">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Zurück
              </button>
              <button class="btn btn-danger" id="tel-btn-del-detail" data-conv="${escAttr(d.conversation_id)}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Löschen
              </button>
            </div>
          </div>
          
          <div class="info-grid">
            <div class="info-card">
              <div class="info-header">
                <div class="info-icon" style="background: #dbeafe;">
                  <svg fill="none" stroke="#3b82f6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                </div>
                <h3 class="info-title">Telefonnummer</h3>
              </div>
              <div class="info-value">${esc(d.caller_number||'-')}</div>
            </div>

            <div class="info-card">
              <div class="info-header">
                <div class="info-icon" style="background: #dcfce7;">
                  <svg fill="none" stroke="#10b981" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <h3 class="info-title">Anrufdauer</h3>
              </div>
              <div class="info-value">${durDisplay}</div>
            </div>

            <div class="info-card">
              <div class="info-header">
                <div class="info-icon" style="background: #f3e8ff;">
                  <svg fill="none" stroke="#a855f7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <h3 class="info-title">Call ID</h3>
              </div>
              <div class="info-value code">${esc(d.conversation_id||'-')}</div>
            </div>
          </div>
          
          <div class="content-section">
            <h3 class="section-title">Kurze Zusammenfassung</h3>
            <p class="section-text">${esc(d.summary_short_de||'Keine Zusammenfassung vorhanden')}</p>
          </div>
          
          <div class="content-section">
            <h3 class="section-title">Ausführliche Zusammenfassung</h3>
            <p class="section-text">${esc(d.summary_long_de||'Keine ausführliche Zusammenfassung vorhanden')}</p>
          </div>
          
          <div class="content-section">
            <div class="audio-header">
              <h3 class="section-title" style="margin: 0;">Audioaufnahme</h3>
              ${d.audio_url ? 
                `<button class="btn-download" onclick="window.open('${escAttr(d.audio_url)}', '_blank')">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                  </svg>
                  Herunterladen
                </button>` 
                : ''
              }
            </div>
            ${d.audio_url ? 
              `<audio controls>
                <source src="${escAttr(d.audio_url)}" type="audio/mpeg">
                Ihr Browser unterstützt das Audio-Element nicht.
              </audio>
              <p class="audio-note">Audio herunterladen</p>` 
              : '<p class="no-audio">Keine Aufnahme vorhanden</p>'
            }
          </div>
          
          <div class="content-section">
            <h3 class="section-title">Transkript</h3>
            ${d.transcript_text ? 
              d.transcript_text.split('\n').map(line => {
                const isAgent = line.toLowerCase().includes('agent:');
                const className = isAgent ? 'agent' : 'customer';
                const timeMatch = line.match(/\[(\d+:\d+)\]/);
                const time = timeMatch ? timeMatch[1] : '';
                const text = line.replace(/\[\d+:\d+\]/, '').replace(/^(Agent:|Kunde:)\s*/i, '').trim();
                const speaker = isAgent ? 'Agent' : 'Kunde';
                
                return `<div class="transcript-item ${className}">
                  <span class="transcript-time">[${time || '0:00'}]</span>
                  <span class="transcript-text">${speaker}: ${esc(text)}</span>
                </div>`;
              }).join('') 
              : '<p>Kein Transkript vorhanden</p>'
            }
          </div>
        </div>
      `;
      
      $('#tel-btn-back', $detailView)?.addEventListener('click', () => {
        $detailView.style.display = 'none';
        $listView.style.display = 'block';
      });
      
      $('#tel-btn-del-detail', $detailView)?.addEventListener('click', async (ev) => {
        const conv = ev.currentTarget.getAttribute('data-conv');
        if (!conv || !confirm('Diesen Anruf wirklich löschen?')) return;
        
        const btn = ev.currentTarget;
        btn.disabled = true;
        btn.textContent = 'Lösche...';
        
        try{
          await fetchJSON(`${REST}call/delete`, {
            method:'POST', 
            body: JSON.stringify({conversation_id: conv})
          });
          
          $detailView.style.display = 'none';
          $listView.style.display = 'block';
          await loadList();
        }catch(e){
          console.error('Fehler beim Löschen:', e);
          alert('Löschen fehlgeschlagen:\n\n' + (e.message || e));
          btn.disabled = false;
          btn.innerHTML = `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Löschen`;
        }
      });
      
    }catch(e){
      console.error('Fehler beim Laden der Details:', e);
      $detailView.innerHTML = `
        <div class="error">
          <button class="btn btn-back" id="tel-btn-back-error">← Zurück</button>
          <p>Fehler beim Laden der Details: ${esc(e.message||e)}</p>
        </div>
      `;
      
      $('#tel-btn-back-error', $detailView)?.addEventListener('click', () => {
        $detailView.style.display = 'none';
        $listView.style.display = 'block';
      });
    }
  }

  // Event Listeners
  $('#tel-btn-filter', mount).addEventListener('click', loadList);
  
  ['#tel-f-phone', '#tel-f-name', '#tel-f-from', '#tel-f-to'].forEach(selector => {
    $(selector, mount)?.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') loadList();
    });
  });
  
  // NEU: Bulk Actions Event Listeners
  $('#select-all-checkbox', mount)?.addEventListener('change', toggleSelectAll);
  
  $('#btn-bulk-delete', mount)?.addEventListener('click', bulkDeleteSelected);
  
  $('#btn-bulk-cancel', mount)?.addEventListener('click', () => {
    selectedCalls.clear();
    renderCurrentPage();
    updateBulkBar();
  });
  
  // Checkbox Events in Tabelle
  $tbody.addEventListener('change', (e) => {
    const checkbox = e.target.closest('.call-checkbox');
    if (!checkbox || checkbox.id === 'select-all-checkbox') return;
    
    const convId = checkbox.getAttribute('data-conv');
    if (!convId) return;
    
    if (checkbox.checked) {
      selectedCalls.add(convId);
    } else {
      selectedCalls.delete(convId);
    }
    
    const row = checkbox.closest('tr');
    if (row) {
      if (checkbox.checked) {
        row.classList.add('selected');
      } else {
        row.classList.remove('selected');
      }
    }
    
    updateBulkBar();
  });
  
  // Pagination Events
  $('#btn-first', $pagination)?.addEventListener('click', () => goToPage(1));
  $('#btn-prev', $pagination)?.addEventListener('click', () => goToPage(currentPage - 1));
  $('#btn-next', $pagination)?.addEventListener('click', () => goToPage(currentPage + 1));
  $('#btn-last', $pagination)?.addEventListener('click', () => goToPage(totalPages));
  
  $pagination.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-page]');
    if (btn) {
      const page = parseInt(btn.dataset.page);
      goToPage(page);
    }
  });
  
  $tbody.addEventListener('click', async ev => {
    const btn = ev.target.closest('button');
    if (!btn) return;
    
    const conv = btn.getAttribute('data-conv');
    if (btn.classList.contains('tel-btn-detail') && conv){
      showDetailView(conv);
    }
  });

  // Statistik Event Listeners
  $('#btn-prev-month', mount)?.addEventListener('click', () => navigateMonth('prev'));
  $('#btn-next-month', mount)?.addEventListener('click', () => navigateMonth('next'));
  $('#month-select', mount)?.addEventListener('change', onMonthSelectChange);

  // Initial laden
  loadList();
  loadStats(currentStatsMonth, currentStatsYear);

})();