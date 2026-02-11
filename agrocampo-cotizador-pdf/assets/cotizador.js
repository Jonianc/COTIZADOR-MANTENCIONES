(function(){
'use strict';

const cfg = window.ACPDF_CONFIG || {};
const IVA_PCT = Number(cfg.iva || 0);
const PREFILL = cfg.prefill || null;
const AUTOSAVE_KEY = 'acpdf_form_draft';
const AUTOSAVE_DELAY = 2000;

const $ = (s) => document.querySelector(s);
const $$ = (s) => document.querySelectorAll(s);
const id = (s) => document.getElementById(s);

const elForm     = id('acpdf-form');
const elHoursSet = id('acpdf-hours-set');
const elHours    = id('acpdf-hours');
const elModel    = id('acpdf-model');
const elTitle    = id('acpdf-title');
const elBrand    = id('acpdf-brand');
const elTpl      = id('acpdf-template');
const elQuoteType = id('acpdf-quote-type');
const elHoursSetWrap = id('acpdf-hours-set-wrap');
const elHoursWrap = id('acpdf-hours-wrap');
const elHoursManual = id('acpdf-hours-manual');
const elHoursManualHelp = id('acpdf-hours-manual-help');
const repairWrap = id('acpdf-repair-wrap');

const tbody      = document.querySelector('#acpdf-items tbody');

if (!elForm || !elHoursSet || !elHours || !elModel || !elTitle || !elBrand || !elTpl || !tbody) return;


// ============ HELPERS ============
function fmtCLP(n) {
  const v = Math.round(Number(n || 0));
  return '$' + v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseNum(v) {
  v = (v ?? '').toString().trim();
  if (!v) return 0;
  v = v.replace(/[^0-9.,-]/g, '');

  const hasDot = v.indexOf('.') !== -1;
  const hasComma = v.indexOf(',') !== -1;

  if (hasDot && hasComma) {
    v = v.replace(/\./g, '').replace(',', '.');
  } else if (hasComma && !hasDot) {
    v = v.replace(',', '.');
  } else {
    const dots = (v.match(/\./g) || []).length;
    if (dots > 1) v = v.replace(/\./g, '');
  }

  const n = parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

function debounce(fn, ms) {
  let timer;
  return function(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), ms);
  };
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// ============ HOURS SETS ============
const HOURS_SETS = {
  // Set A: 10/50/100 y luego cada 400 hasta 4800
  A: [10, 50, 100, 400, 800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000, 4400, 4800],
  // Set B: 10/50/100 y luego 500/1000/1500 y tramos hasta 5000
  B: [10, 50, 100, 500, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000]
};

// Hours used for "Cantidad por Máquina" (reference PDF): desde 100h (sin 10/50)
const QTY_MACHINE_HOURS_BY_SET = {
  A: HOURS_SETS.A.filter(h => Number(h) >= 100),
  B: HOURS_SETS.B.filter(h => Number(h) >= 100)
};

function uniqSorted(nums){
  const set = new Set();
  (nums || []).forEach(n => {
    const v = Number(n);
    if (Number.isFinite(v)) set.add(v);
  });
  return [...set].sort((a,b)=>a-b);
}

function parseFreqSpec(freqStr){
  const s = String(freqStr || '').trim();
  const m = s.match(/\d+/g);
  if (!m || !m.length) return { every: null, first: null };
  const nums = m.map(x => Number(x)).filter(n => Number.isFinite(n) && n > 0);
  if (!nums.length) return { every: null, first: null };
  if (nums.length >= 2) return { every: nums[0], first: nums[1] };
  return { every: nums[0], first: null };
}

function expandQtyMap(qtyObj, freqStr, hoursList){
  const out = {};
  const src = (qtyObj && typeof qtyObj === 'object') ? qtyObj : {};
  // Normalize existing mapping to numbers
  const existing = {};
  Object.keys(src).forEach(k => {
    const hk = Number(k);
    const v = Number(src[k]);
    if (Number.isFinite(hk) && Number.isFinite(v)) existing[hk] = v;
  });

  const nonZero = Object.keys(existing)
    .map(k => [Number(k), Number(existing[k])])
    .filter(([h,v]) => Number.isFinite(h) && Number.isFinite(v) && v > 0)
    .sort((a,b)=>a[0]-b[0]);

  const baseQty = nonZero.length ? Math.max(...nonZero.map(([,v])=>v)) : 0;
  const firstNonZeroHour = nonZero.length ? nonZero[0][0] : null;
  const hasAnyDefinedQty = Object.keys(existing).length > 0;

  const spec = parseFreqSpec(freqStr);
  let every = spec.every;
  let first = spec.first;
  if (!every || every <= 0) {
    // No reliable frequency: keep existing values only
    (hoursList || []).forEach(h => {
      const hh = Number(h);
      out[hh] = (Object.prototype.hasOwnProperty.call(existing, hh)) ? existing[hh] : 0;
    });
    return out;
  }

  // Fallback: cuando el origen trae solo ceros pero existe frecuencia, usar 1 por evento de servicio.
  // Esto evita perder ítems periódicos (ej. 2000h) en pautas que vienen truncadas en qty.
  const fallbackQty = (hasAnyDefinedQty && baseQty <= 0) ? 1 : 0;

  // If we don't have any known non-zero quantity and no safe fallback, keep existing values.
  if (!baseQty || baseQty <= 0) {
    if (!fallbackQty) {
      (hoursList || []).forEach(h => {
        const hh = Number(h);
        out[hh] = (Object.prototype.hasOwnProperty.call(existing, hh)) ? existing[hh] : 0;
      });
      return out;
    }
  }

  // Decide start: explicit first OR the first non-zero hour OR the period itself
  const start = (first && first > 0) ? first : (firstNonZeroHour || every);
  const qtyOnService = (baseQty && baseQty > 0) ? baseQty : fallbackQty;

  (hoursList || []).forEach(h => {
    const hh = Number(h);
    if (!Number.isFinite(hh)) return;
    let v = 0;
    if (hh === start) {
      v = qtyOnService;
    } else if (hh >= every && (hh % every) === 0) {
      v = qtyOnService;
    } else if (Object.prototype.hasOwnProperty.call(existing, hh)) {
      // Respect explicit mapping if present
      v = existing[hh];
    }
    out[hh] = v;
  });

  return out;
}


function inferHoursSetByTemplate(rawTpl){
  const rawHours = Array.isArray(rawTpl?.hours) ? rawTpl.hours.map(Number).filter(n => Number.isFinite(n) && n > 0) : [];
  if (!rawHours.length) return null;

  // Comparar afinidad de horas de la pauta contra sets A/B (ignorando 10/50).
  const score = { A: 0, B: 0 };
  ['A', 'B'].forEach(k => {
    const setHours = new Set((HOURS_SETS[k] || []).filter(h => Number(h) >= 100));
    rawHours.forEach(h => {
      if (setHours.has(h)) score[k] += 1;
    });
  });

  if (score.B > score.A) return 'B';
  if (score.A > score.B) return 'A';

  // Desempate: si incluye 500 o 1500 suele corresponder a set B; 400/800/1200/1600 a set A.
  if (rawHours.includes(500) || rawHours.includes(1500) || rawHours.includes(2500)) return 'B';
  if (rawHours.includes(400) || rawHours.includes(800) || rawHours.includes(1200) || rawHours.includes(1600)) return 'A';
  return null;
}

// ============ TEMPLATES ============
const CATALOG = (window.ACPDF_TEMPLATES && window.ACPDF_TEMPLATES.brands) ? window.ACPDF_TEMPLATES.brands : {};

function activeTplKey(){
  return (activeBrandKey && activeTemplateKey) ? (activeBrandKey + '::' + activeTemplateKey) : '';
}

function getActiveTemplate(){
  if (!activeBrandKey || !activeTemplateKey) return null;
  const b = CATALOG[activeBrandKey];
  if (!b || !b.templates) return null;
  const raw = b.templates[activeTemplateKey];
  if (!raw) return null;

  // Horas disponibles: en Massey Ferguson se rige por el set A/B (incluye 10/50 para mantenciones).
  // Esto permite cubrir rangos completos (ej. Serie 7S hasta 5000 en Set B).
  let hours = Array.isArray(raw.hours) ? raw.hours.map(Number).filter(n => Number.isFinite(n)).sort((a,b)=>a-b) : [];
  if (String(activeBrandKey) === 'massey_ferguson') {
    const setKey = String(elHoursSet?.value || 'A');
    const k = Object.prototype.hasOwnProperty.call(HOURS_SETS, setKey) ? setKey : 'A';
    hours = HOURS_SETS[k].slice();
  }

  const items = Array.isArray(raw.items) ? raw.items.map(it => {
    const freq = it.frequency || it.freq || '';
    const qtyRaw = it.qty || {};
    const qty = expandQtyMap(qtyRaw, freq, hours);
    return {
    desc: it.description || it.desc || '',
    part: it.part || '',
    unit: it.um || it.unit || '',
    freq,
    qty
  };
  }) : [];

  return { label: raw.label || activeTemplateKey, hours, items };
}

function fillBrandOptions(){
  const prev = String(elBrand.value || '').trim();
  elBrand.innerHTML = '';
  const keys = Object.keys(CATALOG);
  if (!keys.length) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = '— Sin marcas —';
    elBrand.appendChild(opt);
    return;
  }
  keys.forEach(k => {
    const opt = document.createElement('option');
    opt.value = k;
    opt.textContent = (CATALOG[k] && CATALOG[k].label) ? CATALOG[k].label : k;
    elBrand.appendChild(opt);
  });
  if (prev && keys.includes(prev)) {
    elBrand.value = prev;
  } else if (keys.includes('massey_ferguson')) {
    elBrand.value = 'massey_ferguson';
  } else {
    elBrand.value = keys[0];
  }
}

function fillTemplateOptions(){
  const brandKey = String(elBrand.value || '').trim();
  const prev = String(elTpl.value || '').trim();

  elTpl.innerHTML = '';
  const opt0 = document.createElement('option');
  opt0.value = '';
  opt0.textContent = '— Sin precarga —';
  elTpl.appendChild(opt0);

  const b = CATALOG[brandKey];
  const tpls = (b && b.templates) ? b.templates : {};

  Object.keys(tpls)
    .sort((a, b) => String((tpls[a] && tpls[a].label) ? tpls[a].label : a).localeCompare(String((tpls[b] && tpls[b].label) ? tpls[b].label : b), 'es'))
    .forEach(k => {
      const opt = document.createElement('option');
      opt.value = k;
      opt.textContent = (tpls[k] && tpls[k].label) ? tpls[k].label : k;
      elTpl.appendChild(opt);
    });

  if (prev && tpls[prev]) elTpl.value = prev;
}

let activeBrandKey = '';
let activeTemplateKey = '';
const TEMPLATE_EDITS = new Map();

function tplItemId(it) {
  return String((it.part || '') + '||' + (it.desc || '') + '||' + (it.unit || '') + '||' + (it.freq || ''));
}

// ============ LOADING OVERLAY ============
function createLoadingOverlay() {
  if (id('acpdf-loading')) return;
  const overlay = document.createElement('div');
  overlay.id = 'acpdf-loading';
  overlay.className = 'loading-overlay';
  overlay.innerHTML = '<div class="spinner"></div><span>Generando PDF...</span>';
  document.body.appendChild(overlay);
}

function showLoading() {
  const el = id('acpdf-loading');
  if (el) el.classList.add('active');
}

function hideLoading() {
  const el = id('acpdf-loading');
  if (el) el.classList.remove('active');
}

createLoadingOverlay();

// ============ AUTOSAVE INDICATOR ============
function createAutosaveIndicator() {
  if (id('acpdf-autosave')) return;
  const el = document.createElement('div');
  el.id = 'acpdf-autosave';
  el.className = 'autosave-indicator';
  el.textContent = '✓ Borrador guardado';
  document.body.appendChild(el);
}

function showAutosaveIndicator() {
  const el = id('acpdf-autosave');
  if (!el) return;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 2000);
}

createAutosaveIndicator();

// ============ TITLE UPDATE ============
function updateTitle() {
  const m = (elModel.value || '').trim();
  const qt = (elQuoteType && elQuoteType.value) ? String(elQuoteType.value) : 'maintenance';
  if (qt === 'repair') {
    elTitle.value = ((m ? (m + ' ') : '') + 'REPARACION').trim();
    return;
  }
  const h = (elHours.value || '').trim();
  elTitle.value = ((m ? (m + ' ') : '') + 'MANTENCION ' + h + ' HORAS').trim();
}


function isRepairMode() {
  return (elQuoteType && String(elQuoteType.value || '') === 'repair');
}

function getLockedHoursSetForSelection() {
  const brandKey = String(elBrand?.value || '').trim();
  const tplKey = String(elTpl?.value || '').trim();
  if (!brandKey || !tplKey) return '';
  if (brandKey !== 'massey_ferguson') return '';

  const rawTpl = CATALOG?.[brandKey]?.templates?.[tplKey] || null;
  return inferHoursSetByTemplate(rawTpl) || '';
}

function syncHoursSetOptions() {
  if (!elHoursSet) return;

  const optA = elHoursSet.querySelector('option[value="A"]');
  const optB = elHoursSet.querySelector('option[value="B"]');
  const lockedSet = getLockedHoursSetForSelection();

  if (optA) { optA.hidden = false; optA.disabled = false; }
  if (optB) { optB.hidden = false; optB.disabled = false; }

  if (lockedSet === 'A' || lockedSet === 'B') {
    const other = (lockedSet === 'A') ? 'B' : 'A';
    const optLocked = elHoursSet.querySelector('option[value="' + lockedSet + '"]');
    const optOther = elHoursSet.querySelector('option[value="' + other + '"]');

    if (optLocked) { optLocked.hidden = false; optLocked.disabled = false; }
    if (optOther) { optOther.hidden = true; optOther.disabled = true; }

    if (String(elHoursSet.value || '') !== lockedSet) {
      elHoursSet.value = lockedSet;
    }

    if (!isRepairMode()) {
      elHoursSet.disabled = true;
    }
    return;
  }

  if (!isRepairMode()) {
    elHoursSet.disabled = false;
  }
}

function setQuoteTypeUI() {
  const repair = isRepairMode();

  // Toggle blocks
  $$('.acpdf-only-repair').forEach(el => el.classList.toggle('hidden', !repair));
  $$('.acpdf-only-maint').forEach(el => el.classList.toggle('hidden', repair));

  if (repair) {
    // Force no template/precarga
    if (elTpl) elTpl.value = '';
    activeBrandKey = '';
    activeTemplateKey = '';

    // Disable template controls
    if (elBrand) elBrand.disabled = true;
    if (elTpl) elTpl.disabled = true;

    // Also disable hours controls (they're hidden anyway)
    if (elHoursSet) elHoursSet.disabled = true;

    // Keep maint_hours select value but not used
    updateTitle();
  } else {
    if (elBrand) elBrand.disabled = false;
    if (elTpl) elTpl.disabled = false;
    // Hours-set is enabled unless template is active
    if (!String(elTpl.value || '').trim()) {
      elHoursSet.disabled = false;
    }
    refreshHoursSetManualOption();
    fillHours();
  }
  triggerAutosave();
}

function refreshHoursSetManualOption() {
  if (!elHoursSet) return;
  syncHoursSetOptions();
  const tplKey = String(elTpl?.value || '').trim();
  const hasTemplate = !!tplKey;

  // Ensure MANUAL option exists
  let opt = elHoursSet.querySelector('option[value="MANUAL"]');
  if (!opt) {
    opt = document.createElement('option');
    opt.value = 'MANUAL';
    opt.textContent = 'Manual';
    elHoursSet.appendChild(opt);
  }

  // Manual only when no precarga (no template) and not repair
  const allowManual = (!hasTemplate) && !isRepairMode();
  opt.disabled = !allowManual;
  opt.hidden = !allowManual;

  // If manual becomes unavailable while selected, fall back to A
  if (!allowManual && elHoursSet.value === 'MANUAL') {
    elHoursSet.value = 'A';
  }
}

function setManualHoursUI() {
  const isManual = (String(elHoursSet.value || '') === 'MANUAL');
  if (!elHoursManual || !elHoursManualHelp) return;

  elHoursManual.classList.toggle('hidden', !isManual);
  elHoursManualHelp.classList.toggle('hidden', !isManual);

  // Hide hours dropdown when manual
  if (elHoursWrap) elHoursWrap.classList.toggle('hidden', isManual);

  if (isManual) {
    const v = String(elHoursManual.value || '').replace(/[^0-9]/g,'');
    const val = v ? Number(v) : 0;
    if (val > 0) {
      elHours.innerHTML = '<option value="' + val + '">' + val + '</option>';
      elHours.value = String(val);
    } else {
      elHours.innerHTML = '<option value="">—</option>';
      elHours.value = '';
    }
  } else {
    if (elHoursWrap) elHoursWrap.classList.remove('hidden');
  }
  updateTitle();
}

// ============ HOURS DROPDOWN ============
function fillHours() {
  refreshHoursSetManualOption();
  if (String(elHoursSet.value || '') === 'MANUAL') {
    setManualHoursUI();
    return;
  }
  const t = getActiveTemplate();
  const list = (t && t.hours && t.hours.length) ? t.hours : (HOURS_SETS[elHoursSet.value] || HOURS_SETS.A);
  const cur = elHours.value;

  elHours.innerHTML = list.map(n => '<option value="' + n + '">' + n + '</option>').join('');
  if (cur && list.includes(Number(cur))) {
    elHours.value = cur;
  } else if (list.length) {
    elHours.value = String(list[0]);
  }
  updateTitle();
}

 // ============ ROW MANAGEMENT ============
function renumber() {
  [...tbody.querySelectorAll('tr')].forEach((tr, i) => {
    const inp = tr.querySelector('input[name="item_n[]"]');
    if (inp) inp.value = String(i + 1);
  });
}

function calcTotals() {
  let net = 0;

  [...tbody.querySelectorAll('tr')].forEach(tr => {
    const up = parseNum(tr.querySelector('input[name="item_unit_price[]"]')?.value);
    const q  = parseNum(tr.querySelector('input[name="item_qty[]"]')?.value);
    const d  = parseNum(tr.querySelector('input[name="item_discount[]"]')?.value);

    let line = up * q;
    if (d > 0) line *= (1 - (d / 100));
    if (line < 0) line = 0;

    const out = tr.querySelector('.acpdf-line');
    if (out) out.textContent = fmtCLP(line);
    net += line;
  });

  const iva = net * (IVA_PCT / 100);

  const elN = id('acpdf-neto');
  const elI = id('acpdf-iva');
  const elT = id('acpdf-total');
  if (elN) elN.textContent = fmtCLP(net);
  if (elI) elI.textContent = fmtCLP(iva);
  if (elT) elT.textContent = fmtCLP(net + iva);
}

function addRow(d = {}, meta = null) {
  const tr = document.createElement('tr');
  tr.draggable = true;
  
  if (meta) {
    if (meta.qtyMap) tr.dataset.qtyMap = JSON.stringify(meta.qtyMap);
    if (meta.tplKey) tr.dataset.tplKey = String(meta.tplKey);
    if (meta.tplPart) tr.dataset.tplPart = String(meta.tplPart);
    if (meta.tplItemId) tr.dataset.tplItemId = String(meta.tplItemId);
  }

  tr.innerHTML =
    '<td class="t-center"><span class="drag-handle" title="Arrastrar para reordenar">☰</span><input name="item_n[]" value="' + escapeHtml(d.n || '') + '" style="width:40px;text-align:center"/></td>' +
    '<td><input name="item_code[]" value="' + escapeHtml(d.code || '') + '"/></td>' +
    '<td><textarea name="item_detail[]" style="min-height:44px">' + escapeHtml(d.detail || '') + '</textarea></td>' +
    '<td><input name="item_unit_price[]" value="' + escapeHtml(d.unit_price || '') + '" class="t-right"/></td>' +
    '<td><input name="item_unit[]" value="' + escapeHtml(d.unit || 'UN') + '" class="t-center"/></td>' +
    '<td><input name="item_discount[]" value="' + escapeHtml(d.discount || '') + '" class="t-center"/></td>' +
    '<td><input name="item_qty[]" value="' + escapeHtml(d.qty || '') + '" class="t-center"/></td>' +
    '<td class="t-right"><span class="acpdf-line">$0</span></td>' +
    '<td class="t-center"><button type="button" class="btn btn-ghost btn-sm acpdf-del" title="Eliminar fila">✕</button></td>';

  tbody.appendChild(tr);
  renumber();
  calcTotals();
  return tr;
}

function clearRows() {
  tbody.innerHTML = '';
}

// ============ DRAG & DROP ============
let draggedRow = null;

function initDragDrop() {
  tbody.addEventListener('dragstart', (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    draggedRow = tr;
    tr.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });

  tbody.addEventListener('dragend', (e) => {
    const tr = e.target.closest('tr');
    if (tr) tr.classList.remove('dragging');
    [...tbody.querySelectorAll('tr')].forEach(r => r.classList.remove('drag-over'));
    draggedRow = null;
    renumber();
    triggerAutosave();
  });

  tbody.addEventListener('dragover', (e) => {
    e.preventDefault();
    const tr = e.target.closest('tr');
    if (!tr || tr === draggedRow) return;
    
    [...tbody.querySelectorAll('tr')].forEach(r => r.classList.remove('drag-over'));
    
    const rect = tr.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    
    if (e.clientY < midY) {
      tr.classList.add('drag-over');
    } else {
      const next = tr.nextElementSibling;
      if (next) next.classList.add('drag-over');
    }
  });

  tbody.addEventListener('drop', (e) => {
    e.preventDefault();
    const tr = e.target.closest('tr');
    if (!tr || !draggedRow || tr === draggedRow) return;
    
    const rect = tr.getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    
    if (e.clientY < midY) {
      tbody.insertBefore(draggedRow, tr);
    } else {
      tbody.insertBefore(draggedRow, tr.nextElementSibling);
    }
  });
}

initDragDrop();

// ============ TEMPLATES ============
function applyTemplate(brandKey, tplKey) {
  const bKey = String(brandKey || '').trim();
  const tKey = String(tplKey || '').trim();

  activeBrandKey = bKey;
  activeTemplateKey = tKey;

  const t = getActiveTemplate();
  if (!t) {
    activeBrandKey = '';
    activeTemplateKey = '';
    return;
  }

  const tk = activeTplKey();
  TEMPLATE_EDITS.set(tk, new Map());

  // Con pauta activa: se muestra solo el set correspondiente (A o B); Manual no.
  elHoursSet.disabled = false;
  refreshHoursSetManualOption();
  if (elHoursManual) elHoursManual.value = '';
  if (elHoursWrap) elHoursWrap.classList.remove('hidden');

  fillBrandOptions();
fillTemplateOptions();
fillHours();

  // Ensure selected hour belongs to template hours
  const cur = Number(elHours.value || 0);
  if (t.hours.length && !t.hours.includes(cur)) {
    elHours.value = String(t.hours[0]);
  }

  // Auto-fill model if empty
  if (elModel && (!elModel.value || !String(elModel.value).trim())) {
    const modelCode = String(t.label || '').split(' - ')[0].trim();
    if (modelCode) elModel.value = modelCode;
  }

  clearRows();

  const h = Number(elHours.value || 0);
  (t.items || []).forEach(it => {
    const qty = Number((it.qty && Object.prototype.hasOwnProperty.call(it.qty, h)) ? it.qty[h] : 0);
    if (qty > 0) {
      const itemId = tplItemId(it);
      addRow({
        code: '',
        detail: (it.desc + ' - ' + it.part),
        unit_price: '',
        unit: it.unit || 'UN',
        discount: '',
        qty: String(qty)
      }, { qtyMap: it.qty || {}, tplKey: tk, tplPart: it.part, tplItemId: itemId, tplFreq: it.freq || '' });
    }
  });

  renumber();
  updateTitle();
  calcTotals();
  triggerAutosave();
}

function rebuildTemplateForHour() {
  const t = getActiveTemplate();
  if (!t) return;

  const tk = activeTplKey();
  const h = Number(elHours.value || 0);

  const saved = TEMPLATE_EDITS.get(tk) || new Map();
  const allRows = [...tbody.querySelectorAll('tr')];
  allRows.forEach(tr => {
    if (tr?.dataset?.tplKey === tk) {
      const itemId = String(tr.dataset.tplItemId || '');
      saved.set(itemId, {
        code: tr.querySelector('input[name="item_code[]"]')?.value || '',
        unit_price: tr.querySelector('input[name="item_unit_price[]"]')?.value || '',
        discount: tr.querySelector('input[name="item_discount[]"]')?.value || '',
        detail: tr.querySelector('textarea[name="item_detail[]"]')?.value || '',
        unit: tr.querySelector('input[name="item_unit[]"]')?.value || '',
      });
      tr.remove();
    }
  });

  TEMPLATE_EDITS.set(tk, saved);

  const extraRows = [...tbody.querySelectorAll('tr')];
  extraRows.forEach(tr => tr.remove());

  (t.items || []).forEach(it => {
    const qty = Number((it.qty && Object.prototype.hasOwnProperty.call(it.qty, h)) ? it.qty[h] : 0);
    if (qty <= 0) return;

    const itemId = tplItemId(it);
    const prev = saved.get(itemId) || {};

    addRow({
      code: prev.code || '',
      detail: (prev.detail && String(prev.detail).trim()) ? prev.detail : (it.desc + ' - ' + it.part),
      unit_price: prev.unit_price || '',
      unit: (prev.unit && String(prev.unit).trim()) ? prev.unit : (it.unit || 'UN'),
      discount: prev.discount || '',
      qty: String(qty)
    }, {
      qtyMap: it.qty || {},
      tplKey: tk,
      tplPart: it.part,
      tplItemId: itemId,
      tplFreq: it.freq || ''
    });
  });

  extraRows.forEach(tr => tbody.appendChild(tr));

  renumber();
  calcTotals();
}

// ============ PREFILL FROM LOG ============
function applyPrefill(d) {
  if (!d || typeof d !== 'object') return;

  const setField = (n, v) => {
    const el = document.querySelector('[name="' + n + '"]');
    if (el) el.value = (v ?? '');
  };

  setField('date_iso', d.date_iso);
  setField('internal_no', d.internal_no);
  setField('serial_no', d.serial_no);
  setField('rut', d.rut);
  setField('client', d.client);
  setField('phone', d.phone);
  setField('email', d.email);
  setField('model', d.model);
  setField('location', d.location);
  setField('quote_type', d.quote_type);
  setField('brand_key', d.brand_key);
  setField('template_key', d.template_key);
  setField('hours_set', d.hours_set);
  setField('hours_manual', d.hours_manual);
  setField('maint_hours', d.maint_hours);
  setField('parts_type', d.parts_type);
  setField('repair_issue', d.repair_issue);
  setField('repair_diagnosis', d.repair_diagnosis);
  setField('labor_hours', d.labor_hours);
  setField('labor_rate', d.labor_rate);
  setField('travel_amount', d.travel_amount);
  setField('external_amount', d.external_amount);
  setField('observations', d.observations);


  // If templates catalog is present, ensure selects are populated before applying
  fillBrandOptions();
  fillTemplateOptions();

  // Apply template if provided
  if (d.brand_key) elBrand.value = String(d.brand_key);
  fillTemplateOptions();
  if (d.template_key) {
    elTpl.value = String(d.template_key);
    if (d.maint_hours) {
      // applyTemplate will ensure hour belongs to the template hours; set desired hour after fillHours()
      applyTemplate(elBrand.value, elTpl.value);
      if (d.maint_hours) {
        elHours.value = String(d.maint_hours);
        rebuildTemplateForHour();
      }
    } else {
      applyTemplate(elBrand.value, elTpl.value);
    }
    return;
  } else {
    // No template: manual mode
    activeBrandKey = '';
    activeTemplateKey = '';
    elHoursSet.disabled = false;
  }

  if (d.hours_set) {
    elHoursSet.value = d.hours_set;
    fillHours();
  }
  if (d.maint_hours) {
    elHours.value = String(d.maint_hours);
  }

  clearRows();
  if (Array.isArray(d.items) && d.items.length) {
    d.items.forEach(it => addRow({
      n: it.n,
      code: it.code,
      detail: it.detail,
      unit_price: it.unit_price,
      unit: it.unit,
      discount: it.discount,
      qty: it.qty
    }));
  } else {
    addRow({ n: 1 });
  }

  updateTitle();
  calcTotals();
}

// ============ FORM VALIDATION ============
function validateForm() {
  let valid = true;
  const errors = [];
  const errorBox = id('acpdf-errors');

  const clearFieldErrors = () => {
    $$('.error').forEach(el => el.classList.remove('error'));
    $$('.field-error').forEach(el => el.remove());
  };

  const setFieldError = (el, message) => {
    if (!el) return;
    el.classList.add('error');
    const errorEl = document.createElement('div');
    errorEl.className = 'field-error';
    errorEl.textContent = message;
    el.insertAdjacentElement('afterend', errorEl);
  };

  const showSummary = () => {
    if (!errorBox) return;
    if (!errors.length) {
      errorBox.classList.add('hidden');
      errorBox.textContent = '';
      return;
    }
    errorBox.classList.remove('hidden');
    errorBox.innerHTML = '<strong>Revisa los siguientes errores:</strong><ul><li>' + errors.join('</li><li>') + '</li></ul>';
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  // Clear previous errors
  clearFieldErrors();

  // Required fields
  const requiredFields = [
    { name: 'client', label: 'Cliente' },
    { name: 'model', label: 'Modelo' }
  ];

  requiredFields.forEach(field => {
    const el = document.querySelector(`[name="${field.name}"]`);
    if (el && !el.value.trim()) {
      setFieldError(el, `${field.label} es requerido`);
      errors.push(`${field.label} es requerido`);
      valid = false;
    }
  });

  const rutEl = document.querySelector('[name="rut"]');
  if (rutEl && rutEl.value.trim()) {
    const rut = rutEl.value.trim();
    const rutRegex = /^[0-9]{1,2}\.?[0-9]{3}\.?[0-9]{3}-[0-9kK]$/;
    if (!rutRegex.test(rut)) {
      setFieldError(rutEl, 'Formato de RUT inválido (ej: 76.155.060-8)');
      errors.push('Formato de RUT inválido');
      valid = false;
    }
  }

  if (!isRepairMode()) {
    if (elHoursSet && String(elHoursSet.value || '') === 'MANUAL') {
      const manualVal = parseNum(elHoursManual?.value);
      if (!manualVal || manualVal <= 0) {
        setFieldError(elHoursManual, 'Ingresa las horas manuales');
        errors.push('Horas manuales es requerido');
        valid = false;
      }
    } else {
      const hoursVal = String(elHours?.value || '').trim();
      if (!hoursVal) {
        setFieldError(elHours, 'Selecciona un tipo de mantención');
        errors.push('Tipo de mantención es requerido');
        valid = false;
      }
    }
  }

  if (isRepairMode()) {
    const laborHours = parseNum(document.querySelector('[name="labor_hours"]')?.value);
    const laborRate = parseNum(document.querySelector('[name="labor_rate"]')?.value);
    if ((laborHours > 0 && laborRate <= 0) || (laborRate > 0 && laborHours <= 0)) {
      setFieldError(id('acpdf-labor-hours'), 'Completa horas y valor HH');
      setFieldError(id('acpdf-labor-rate'), 'Completa horas y valor HH');
      errors.push('Mano de obra requiere horas y valor HH');
      valid = false;
    }
  }

  // At least one item with price and qty
  const rows = tbody.querySelectorAll('tr');
  let hasValidItem = false;

  rows.forEach(tr => {
    const price = parseNum(tr.querySelector('input[name="item_unit_price[]"]')?.value);
    const qty = parseNum(tr.querySelector('input[name="item_qty[]"]')?.value);
    const discount = parseNum(tr.querySelector('input[name="item_discount[]"]')?.value);
    if (discount > 100) {
      const discEl = tr.querySelector('input[name="item_discount[]"]');
      setFieldError(discEl, 'El descuento no puede ser mayor a 100%');
      errors.push('Descuento mayor a 100%');
      valid = false;
    }
    if (price > 0 && qty > 0) {
      hasValidItem = true;
    }
  });

  const hasRepairCosts = isRepairMode() && (
    parseNum(document.querySelector('[name="labor_hours"]')?.value) > 0 ||
    parseNum(document.querySelector('[name="labor_rate"]')?.value) > 0 ||
    parseNum(document.querySelector('[name="travel_amount"]')?.value) > 0 ||
    parseNum(document.querySelector('[name="external_amount"]')?.value) > 0
  );

  if (!hasValidItem && !hasRepairCosts) {
    errors.push('Debe tener al menos un ítem con precio y cantidad');
    valid = false;
  }

  if (!valid) {
    showSummary();
  } else if (errorBox) {
    errorBox.classList.add('hidden');
    errorBox.textContent = '';
  }

  return valid;
}

// ============ AUTOSAVE ============
function getFormData() {
  const data = {
    date_iso: document.querySelector('[name="date_iso"]')?.value || '',
    internal_no: document.querySelector('[name="internal_no"]')?.value || '',
    serial_no: document.querySelector('[name="serial_no"]')?.value || '',
    rut: document.querySelector('[name="rut"]')?.value || '',
    client: document.querySelector('[name="client"]')?.value || '',
    phone: document.querySelector('[name="phone"]')?.value || '',
    email: document.querySelector('[name="email"]')?.value || '',
    model: document.querySelector('[name="model"]')?.value || '',
    quote_type: document.querySelector('[name="quote_type"]')?.value || 'maintenance',
    repair_issue: document.querySelector('[name="repair_issue"]')?.value || '',
    repair_diagnosis: document.querySelector('[name="repair_diagnosis"]')?.value || '',
    labor_hours: document.querySelector('[name="labor_hours"]')?.value || '',
    labor_rate: document.querySelector('[name="labor_rate"]')?.value || '',
    travel_amount: document.querySelector('[name="travel_amount"]')?.value || '',
    external_amount: document.querySelector('[name="external_amount"]')?.value || '',
    brand_key: elBrand.value || '',
    template_key: elTpl.value || '',
    location: document.querySelector('[name="location"]')?.value || '',
    hours_set: elHoursSet.value,
    hours_manual: document.querySelector('[name="hours_manual"]')?.value || '',
    maint_hours: elHours.value,
    parts_type: document.querySelector('[name="parts_type"]')?.value || 'ORIGINALES',
    observations: document.querySelector('[name="observations"]')?.value || '',
    items: []
  };

  tbody.querySelectorAll('tr').forEach(tr => {
    data.items.push({
      n: tr.querySelector('input[name="item_n[]"]')?.value || '',
      code: tr.querySelector('input[name="item_code[]"]')?.value || '',
      detail: tr.querySelector('textarea[name="item_detail[]"]')?.value || '',
      unit_price: tr.querySelector('input[name="item_unit_price[]"]')?.value || '',
      unit: tr.querySelector('input[name="item_unit[]"]')?.value || '',
      discount: tr.querySelector('input[name="item_discount[]"]')?.value || '',
      qty: tr.querySelector('input[name="item_qty[]"]')?.value || ''
    });
  });

  return data;
}

function saveToLocalStorage() {
  try {
    const data = getFormData();
    data.savedAt = new Date().toISOString();
    localStorage.setItem(AUTOSAVE_KEY, JSON.stringify(data));
    showAutosaveIndicator();
  } catch (e) {
    console.warn('Autosave failed:', e);
  }
}

function loadFromLocalStorage() {
  try {
    const saved = localStorage.getItem(AUTOSAVE_KEY);
    if (!saved) return false;
    
    const data = JSON.parse(saved);
    if (!data || !data.savedAt) return false;

    // Check if draft is less than 24 hours old
    const savedTime = new Date(data.savedAt).getTime();
    const now = Date.now();
    if (now - savedTime > 24 * 60 * 60 * 1000) {
      localStorage.removeItem(AUTOSAVE_KEY);
      return false;
    }

    return data;
  } catch (e) {
    return false;
  }
}

function clearLocalStorage() {
  localStorage.removeItem(AUTOSAVE_KEY);
}

const triggerAutosave = debounce(saveToLocalStorage, AUTOSAVE_DELAY);

// ============ EVENT LISTENERS ============
const btnAdd = id('acpdf-add');
if (btnAdd) {
  btnAdd.addEventListener('click', () => {
    addRow({});
    triggerAutosave();
  });
}

tbody.addEventListener('click', (e) => {
  const btn = e.target.closest('.acpdf-del');
  if (!btn) return;
  e.preventDefault();
  const tr = btn.closest('tr');
  if (tr) {
    if (tbody.querySelectorAll('tr').length > 1) {
      tr.remove();
      renumber();
      calcTotals();
      triggerAutosave();
    } else {
      alert('Debe mantener al menos una fila');
    }
  }
});

tbody.addEventListener('input', () => {
  renumber();
  calcTotals();
  triggerAutosave();
});

elHoursSet.addEventListener('change', () => {
  refreshHoursSetManualOption();
  fillHours();
  if (activeTemplateKey) rebuildTemplateForHour();
  triggerAutosave();
});

if (elHoursManual) {
  elHoursManual.addEventListener('input', () => {
    if (String(elHoursSet.value || '') !== 'MANUAL') return;
    setManualHoursUI();
    triggerAutosave();
  });
}

elHours.addEventListener('change', () => {
  updateTitle();
  if (activeTemplateKey) rebuildTemplateForHour();
  triggerAutosave();
});

elBrand.addEventListener('change', () => {
  // Changing brand resets template selection
  activeBrandKey = '';
  activeTemplateKey = '';
  elHoursSet.disabled = false;

  fillTemplateOptions();
  if (elTpl) elTpl.value = '';
  fillHours();

  triggerAutosave();
});


if (elQuoteType) {
  elQuoteType.addEventListener('change', () => {
    setQuoteTypeUI();
  });
}

elModel.addEventListener('input', () => {
  updateTitle();
  triggerAutosave();
});

if (elTpl) {
  elTpl.addEventListener('change', () => {
    const tplKey = String(elTpl.value || '').trim();
    if (!tplKey) {
      activeBrandKey = '';
      activeTemplateKey = '';
      elHoursSet.disabled = false;
      refreshHoursSetManualOption();
      fillHours();
      triggerAutosave();
      return;
    }
    applyTemplate(elBrand.value, tplKey);
  });
}

// Form submission with validation and loading
elForm.addEventListener('submit', (e) => {
  if (!validateForm()) {
    e.preventDefault();
    return false;
  }
  
  showLoading();
  clearLocalStorage();
  
  // Hide loading after timeout (in case of issues)
  setTimeout(hideLoading, 30000);
});

// Listen for input changes on form fields for autosave
elForm.addEventListener('input', debounce(() => {
  triggerAutosave();
}, AUTOSAVE_DELAY));

// ============ MOBILE RESPONSIVE ============
function checkMobileView() {
  const table = id('acpdf-items');
  if (!table) return;
  
  if (window.innerWidth <= 600) {
    table.classList.add('mobile-cards');
    // Add data-label attributes for mobile view
    table.querySelectorAll('tbody td').forEach((td, i) => {
      const labels = ['N°', 'Código', 'Detalle', 'V. Neto', 'Un.', 'Desc%', 'Cant.', 'Total', ''];
      td.dataset.label = labels[i % labels.length] || '';
    });
  } else {
    table.classList.remove('mobile-cards');
  }
}

window.addEventListener('resize', debounce(checkMobileView, 200));

// ============ INITIALIZATION ============
fillBrandOptions();
fillTemplateOptions();
fillHours();

// Check for saved draft (only if no prefill)
if (PREFILL) {
  applyPrefill(PREFILL);
  clearLocalStorage();
} else {
  const draft = loadFromLocalStorage();
  if (draft && draft.items && draft.items.length > 0) {
    const useDraft = confirm('Se encontró un borrador guardado. ¿Desea recuperarlo?');
    if (useDraft) {
      applyPrefill(draft);
    } else {
      clearLocalStorage();
      addRow({ n: 1 });
      updateTitle();
      calcTotals();
    }
  } else {
    addRow({ n: 1 });
    updateTitle();
    calcTotals();
  }
}

checkMobileView();

// ============ PREVIEW BUTTON ============
const btnPreview = id('acpdf-preview');
if (btnPreview) {
  btnPreview.addEventListener('click', () => {
    if (!validateForm()) return;
    
    // Create a temporary form for preview
    const formData = new FormData(elForm);
    formData.set('preview', '1');
    
    // Submit via hidden iframe or new window
    const previewUrl = elForm.action.replace('/pdf', '/preview');
    
    showLoading();
    
    fetch(previewUrl, {
      method: 'POST',
      body: formData
    })
    .then(res => res.blob())
    .then(blob => {
      hideLoading();
      const url = URL.createObjectURL(blob);
      window.open(url, '_blank');
    })
    .catch(err => {
      hideLoading();
      alert('Error al generar vista previa');
      console.error(err);
    });
  });
}

})();
