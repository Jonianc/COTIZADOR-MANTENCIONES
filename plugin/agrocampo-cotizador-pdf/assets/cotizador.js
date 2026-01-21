(function(){
  function qs(sel, el){ return (el||document).querySelector(sel); }
  function qsa(sel, el){ return Array.from((el||document).querySelectorAll(sel)); }

  function formatCLP(n){
    n = Math.round(Number(n)||0);
    return '$ ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function parseNum(v){
    if(v === null || v === undefined) return 0;
    v = (''+v).trim();
    if(!v) return 0;
    v = v.replace(/\./g,'').replace(',','.');
    var n = Number(v);
    return isFinite(n) ? n : 0;
  }

  function recalc(){
    var tbody = qs('#acpdf-items-table tbody');
    if(!tbody) return;

    var net = 0;
    qsa('tr.acpdf-row', tbody).forEach(function(tr){
      var unitPrice = parseNum(qs('input[name*="[unit_price]"]', tr)?.value);
      var qty = parseNum(qs('input[name*="[qty]"]', tr)?.value);
      var disc = parseNum(qs('input[name*="[discount]"]', tr)?.value);
      if(disc < 0) disc = 0;
      if(disc > 100) disc = 100;
      var line = unitPrice * qty;
      if(disc > 0) line = line * (1 - disc/100);
      net += line;
      var out = qs('.acpdf-line-total', tr);
      if(out) out.textContent = formatCLP(line);
    });

    var ivaPercent = parseNum(qs('input[name="iva_percent"]')?.value);
    var iva = net * (ivaPercent/100);
    var total = net + iva;

    var netEl = qs('#acpdf-net');
    var ivaEl = qs('#acpdf-iva');
    var totalEl = qs('#acpdf-total');
    var ivaLabel = qs('#acpdf-iva-label');
    if(netEl) netEl.textContent = formatCLP(net);
    if(ivaEl) ivaEl.textContent = formatCLP(iva);
    if(totalEl) totalEl.textContent = formatCLP(total);
    if(ivaLabel) ivaLabel.textContent = (ivaPercent ? ivaPercent : 0) + '% I.V.A.';
  }

  function updateTitlePreview(){
    var model = (qs('input[name="model"]')?.value || '').trim();
    var hours = (qs('select[name="maint_hours"]')?.value || '').trim();
    var out = qs('#acpdf-title-preview');
    if(!out) return;
    if(!model && !hours){ out.value = ''; return; }
    if(model && hours){ out.value = model + ' MANTENCION ' + hours + ' HORAS'; return; }
    if(model){ out.value = model; return; }
    out.value = 'MANTENCION ' + hours + ' HORAS';
  }

  function renumber(){
    var tbody = qs('#acpdf-items-table tbody');
    if(!tbody) return;
    qsa('tr.acpdf-row', tbody).forEach(function(tr, i){
      qsa('input', tr).forEach(function(inp){
        inp.name = inp.name.replace(/items\[\d+\]/, 'items['+i+']');
      });
    });
  }

  function addRow(){
    var tbody = qs('#acpdf-items-table tbody');
    if(!tbody) return;
    var i = qsa('tr.acpdf-row', tbody).length;
    var tr = document.createElement('tr');
    tr.className = 'acpdf-row';
    tr.innerHTML = `
      <td><input name="items[${i}][code]" value=""></td>
      <td><input name="items[${i}][detail]" value=""></td>
      <td><input name="items[${i}][unit_price]" type="number" step="1" min="0" value=""></td>
      <td><input name="items[${i}][unit]" value="1"></td>
      <td><input name="items[${i}][discount]" type="number" step="0.01" min="0" max="100" value=""></td>
      <td><input name="items[${i}][qty]" type="number" step="1" min="0" value="1"></td>
      <td class="acpdf-right"><span class="acpdf-line-total">$ 0</span></td>
      <td class="acpdf-center"><button type="button" class="acpdf-icon-btn acpdf-remove-row" title="Eliminar">✕</button></td>
    `;
    tbody.appendChild(tr);
    recalc();
  }

  function removeRow(btn){
    var tr = btn.closest('tr');
    if(!tr) return;
    tr.parentNode.removeChild(tr);
    renumber();
    recalc();
  }

  document.addEventListener('input', function(e){
    if(e.target && (e.target.closest('#acpdf-items-table') || e.target.name === 'iva_percent')){
      recalc();
    }
    if(e.target && (e.target.name === 'model')){
      updateTitlePreview();
    }
  });

  document.addEventListener('change', function(e){
    if(e.target && e.target.name === 'maint_hours'){
      updateTitlePreview();
    }
  });

  document.addEventListener('click', function(e){
    var add = e.target.closest('#acpdf-add-row');
    if(add){
      e.preventDefault();
      addRow();
      return;
    }
    var rm = e.target.closest('.acpdf-remove-row');
    if(rm){
      e.preventDefault();
      removeRow(rm);
      return;
    }
  });

  // initial
  recalc();
  updateTitlePreview();
})();