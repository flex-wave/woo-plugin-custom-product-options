(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    initDimensionButtons();
    document.querySelectorAll('.fw-options').forEach(initWidget);
  });

  function initDimensionButtons() {
    document.querySelectorAll('.fw-qty-btn').forEach(button => {
      button.addEventListener('click', function () {
        const input = this.parentElement.querySelector('.fw-dim-input');
        if (!input) return;
        const step = parseFloat(input.step) || 1;
        const currentVal = parseFloat(input.value) || parseFloat(input.min) || 0;
        const min = parseFloat(input.min);
        const max = parseFloat(input.max);

        let newVal;
        if (this.classList.contains('plus')) {
          newVal = currentVal + step;
        } else {
          newVal = currentVal - step;
        }

        if (!isNaN(min) && newVal < min) newVal = min;
        if (!isNaN(max) && newVal > max) newVal = max;

        input.value = newVal;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  }

  document.addEventListener('fw-quantity-changed', () => {
    document.querySelectorAll('.fw-options[data-fw]').forEach(widget => {
      if (widget._fwRecalc) widget._fwRecalc();
    });
  });

  let _originalGalleryImg = null;

  const switchProductImage = imageUrl => {
    const gallery = document.querySelector('.woocommerce-product-gallery');
    if (!gallery) return;
    if (_originalGalleryImg === null) {
      const origImg = gallery.querySelector('.wp-post-image');
      _originalGalleryImg = origImg ? origImg.getAttribute('src') : '';
    }
    const targetSrc = imageUrl || _originalGalleryImg;
    if (!targetSrc) return;
    const $gallery = typeof jQuery !== 'undefined' && jQuery(gallery);
    if ($gallery && $gallery.data('flexslider')) {
      const slider = $gallery.data('flexslider');
      const slides = slider.slides;
      for (let i = 0; i < slides.length; i++) {
        const slideImg = slides.eq(i).find('img').first();
        const slideSrc = slideImg.attr('src') || '';
        if (basename(slideSrc) === basename(targetSrc)) {
          slider.flexAnimate(i, true);
          return;
        }
      }
    }
    const mainImg = gallery.querySelector('.wp-post-image');
    if (mainImg) {
      mainImg.setAttribute('src', targetSrc);
      mainImg.removeAttribute('srcset');
      const wrap = mainImg.closest('a');
      if (wrap) wrap.setAttribute('href', targetSrc);
    }
  };

  const basename = url => (url || '').split('?')[0].split('/').pop().replace(/-\d+x\d+(\.\w+)$/, '$1');

  function initWidget(widget) {
    let cfg;
    try {
      cfg = JSON.parse(widget.dataset.fw);
    } catch (e) {
      return;
    }
    const pid = cfg.product_id;

    // ── Radio / Color click ──────────────────────────────────────────────────
    widget.querySelectorAll('.fw-option').forEach(label => {
      label.addEventListener('click', () => {
        const radio = label.querySelector('input[type="radio"]');
        if (!radio) return;
        const groupId = radio.dataset.group;
        requestAnimationFrame(() => {
          widget.querySelectorAll(`input[data-group="${groupId}"]`).forEach(r => {
            r.closest('.fw-option').classList.toggle('fw-option--active', r.checked);
          });
          switchProductImage(radio.dataset.image || '');
          const preview = document.getElementById(`fw-cp-fw_g${pid}_${groupId}`);
          if (preview) {
            const dot = preview.querySelector('.fw-cp-dot');
            const lbl = preview.querySelector('.fw-cp-label');
            const swatch = label.querySelector('.fw-swatch');
            if (swatch && dot) dot.style.background = swatch.style.background;
            if (lbl) lbl.textContent = radio.dataset.label || '';
            preview.style.display = 'flex';
          }
          const groupEl = label.closest('.fw-group');
          if (groupEl) {
            const err = groupEl.querySelector('.fw-group-error');
            if (err) err.style.display = 'none';
          }
          recalc();
        });
      });
    });

    // ── Text input ───────────────────────────────────────────────────────────
    widget.querySelectorAll('.fw-text-input').forEach(input => {
      input.addEventListener('input', () => {
        const groupEl = input.closest('.fw-group');
        if (groupEl) {
          const err = groupEl.querySelector('.fw-group-error');
          if (err) err.style.display = 'none';
        }
        recalc();
      });
    });

    // ── Depth select ─────────────────────────────────────────────────────────
    widget.querySelectorAll('.fw-depth-type').forEach(select => {
      select.addEventListener('change', () => {
        toggleDepthCustom(select);
        const groupEl = select.closest('.fw-group');
        if (groupEl) {
          const err = groupEl.querySelector('.fw-group-error');
          if (err) err.style.display = 'none';
        }
        recalc();
      });
      toggleDepthCustom(select);
    });

    widget.querySelectorAll('.fw-depth-custom').forEach(input => {
      input.addEventListener('input', recalc);
      input.addEventListener('change', recalc);
    });

    // ── Length select ────────────────────────────────────────────────────────
    widget.querySelectorAll('.fw-length-type').forEach(select => {
      select.addEventListener('change', () => {
        toggleLengthCustom(select);
        const groupEl = select.closest('.fw-group');
        if (groupEl) {
          const err = groupEl.querySelector('.fw-group-error');
          if (err) err.style.display = 'none';
        }
        recalc();
      });
      toggleLengthCustom(select);
    });

    widget.querySelectorAll('.fw-custom-length').forEach(input => {
      input.addEventListener('input', () => {
        const wrapper = input.closest('.fw-length-wrapper');
        const err = wrapper ? wrapper.querySelector('.fw-custom-length-error') : null;
        if (!err) return;
        const min = parseFloat(input.min);
        const max = parseFloat(input.max);
        const val = parseFloat(input.value.replace(',', '.'));
        if (isNaN(val) || val < min || val > max) {
          err.textContent = `Voer een geldige lengte in (${min} \u2013 ${max} cm)`;
          err.style.display = 'block';
        } else {
          err.style.display = 'none';
        }
        recalc();
      });
      input.addEventListener('change', recalc);
    });

    // ── Dimension inputs ─────────────────────────────────────────────────────
    widget.querySelectorAll('.fw-dim-input').forEach(input => {
      input.addEventListener('input', recalc);
      input.addEventListener('change', recalc);
    });

    // ── Form submit validation ───────────────────────────────────────────────
    const form = widget.closest('form.cart');
    if (form) {
      form.addEventListener('submit', e => {
        if (!validateRequired()) {
          e.preventDefault();
          e.stopImmediatePropagation();
          widget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, true);
    }

    // ── Collect all selections ───────────────────────────────────────────────
    function collectSelections() {
      const result = [];
      const base = parseFloat(cfg.base_price) || 0;

      cfg.groups.forEach(group => {
        if (group.type === 'dimensions') {
          collectDimensionSelections(result, group);
          return;
        }
        if (group.type === 'text') {
          collectTextSelection(result, group);
          return;
        }
        if (group.type === 'length') {
          collectLengthSelection(result, group, base);
          return;
        }
        if (group.type === 'depth') {
          collectDepthSelection(result, group, base);
          return;
        }
        collectRadioSelection(result, group);
      });

      return result;
    }

    function collectRadioSelection(result, group) {
      const checked = widget.querySelector(`input[data-group="${group.id}"]:checked`);
      if (!checked) return;
      result.push({
        group_id: group.id,
        group_name: group.name,
        type: group.type,
        label: checked.dataset.label || '',
        price: parseFloat(checked.dataset.price) || 0
      });
    }

    function collectTextSelection(result, group) {
      const inp = widget.querySelector(`.fw-text-input[data-group="${group.id}"]`);
      if (!inp || inp.value.trim() === '') return;
      const v0 = group.variations[0] || {};
      result.push({
        group_id: group.id,
        group_name: group.name,
        type: 'text',
        label: inp.value.trim(),
        price: parseFloat(v0.price) || 0
      });
    }

    function collectDimensionSelections(result, group) {
      const v_l = group.variations[0] || {};
      const v_b = group.variations[1] || {};
      const inp_l = widget.querySelector(`.fw-dim-input[name^="fw_diml_"][data-group="${group.id}"]`);
      const inp_b = widget.querySelector(`.fw-dim-input[name^="fw_dimb_"][data-group="${group.id}"]`);
      const val_l = inp_l && inp_l.value !== '' ? parseFloat(inp_l.value) : null;
      const val_b = inp_b && inp_b.value !== '' ? parseFloat(inp_b.value) : null;
      const min_l = v_l.min !== undefined ? parseFloat(v_l.min) : 0;
      const min_b = v_b.min !== undefined ? parseFloat(v_b.min) : 0;
      const step_l = v_l.step > 0 ? parseFloat(v_l.step) : 1;
      const step_b = v_b.step > 0 ? parseFloat(v_b.step) : 1;
      const price_l = v_l.price !== undefined ? parseFloat(v_l.price) : 0;
      const price_b = v_b.price !== undefined ? parseFloat(v_b.price) : 0;
      const steps_l = val_l !== null && val_l > min_l ? Math.floor((val_l - min_l) / step_l) : 0;
      const steps_b = val_b !== null && val_b > min_b ? Math.floor((val_b - min_b) / step_b) : 0;

      if (val_l !== null) {
        result.push({
          group_id: group.id,
          group_name: group.name + ' - Lengte',
          type: 'dimension_length',
          label: val_l + (v_l.label ? ' ' + v_l.label : ''),
          price: price_l * steps_l
        });
      }
      if (val_b !== null) {
        result.push({
          group_id: group.id,
          group_name: group.name + ' - Breedte',
          type: 'dimension_width',
          label: val_b + (v_b.label ? ' ' + v_b.label : ''),
          price: price_b * steps_b
        });
      }
    }

    function collectLengthSelection(result, group, base) {
      const select = widget.querySelector(`.fw-length-type[data-group="${group.id}"]`);
      if (!select || !select.value) return;

      if (select.value.startsWith('vast_')) {
        const option = select.options[select.selectedIndex];
        const price = parseFloat(option.dataset.price) || 0;
        const lengthVal = option.dataset.length || select.value.replace('vast_', '');
        result.push({
          group_id: group.id,
          group_name: group.name,
          type: 'length_fixed',
          label: (parseInt(lengthVal) / 10) + ' cm',
          price: price
        });
      } else if (select.value === 'maatwerk') {
        const customInput = widget.querySelector(`.fw-custom-length[data-group="${group.id}"]`);
        if (!customInput || !customInput.value) return;
        const customVal = parseFloat(customInput.value.replace(',', '.')) * 10;
        let matchedPrice = 0;
        let matchedLength = null;
        for (let k = 0; k < group.variations[0].lengths.length; k++) {
          if (customVal <= parseFloat(group.variations[0].lengths[k].value)) {
            matchedPrice = parseFloat(group.variations[0].lengths[k].price) || 0;
            matchedLength = parseFloat(group.variations[0].lengths[k].value);
            break;
          }
        }
        if (matchedLength === null) return;
        result.push({
          group_id: group.id,
          group_name: group.name,
          type: 'length_custom',
          label: (customVal / 10) + ' cm',
          price: matchedPrice,
          raw_value: customVal,
          price_length: matchedLength
        });
      }
    }

    function collectDepthSelection(result, group, base) {
      const select = widget.querySelector(`.fw-depth-type[data-group="${group.id}"]`);
      if (!select || !select.value) return;

      if (select.value.startsWith('vast_')) {
        const option = select.options[select.selectedIndex];
        const percent = parseFloat(option.dataset.percent) || 0;
        const depthVal = option.dataset.depth || select.value.replace('vast_', '');
        const price = base * (percent / 100);
        result.push({
          group_id: group.id,
          group_name: group.name,
          type: 'depth_fixed',
          label: (parseInt(depthVal) / 10) + ' cm',
          percent: percent,
          price: price
        });
      } else if (select.value === 'maatwerk') {
        const customInput = widget.querySelector(`.fw-depth-custom[data-group="${group.id}"]`);
        if (!customInput || !customInput.value) return;
        const customVal = parseFloat(customInput.value.replace(',', '.')) * 10;
        let matchedPercent = 0;
        let matchedDepth = null;
        for (let k = 0; k < group.variations[0].depths.length; k++) {
          if (customVal <= parseFloat(group.variations[0].depths[k].value)) {
            matchedPercent = parseFloat(group.variations[0].depths[k].percent) || 0;
            matchedDepth = parseFloat(group.variations[0].depths[k].value);
            break;
          }
        }
        if (matchedDepth === null) return;
        const price = base * (matchedPercent / 100);
        result.push({
          group_id: group.id,
          group_name: group.name,
          type: 'depth_custom',
          label: (customVal / 10) + ' cm',
          percent: matchedPercent,
          raw_value: customVal,
          price_depth: matchedDepth,
          price: price
        });
      }
    }

    // ── Recalculate price ────────────────────────────────────────────────────
    function recalc() {
      const base = parseFloat(cfg.base_price) || 0;
      const selField = document.getElementById('fw-sel-' + pid);
      const exField = document.getElementById('fw-extra-' + pid);

      const selections = collectSelections();
      let optionTotal = 0;

      selections.forEach(sel => {
        optionTotal += parseFloat(sel.price) || 0;
      });

      const total = base + optionTotal;

      const formatted = total.toLocaleString('nl-NL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      // Update WooCommerce main price display
      const mainPrice = document.querySelector('.single-product .price .amount:not(del .amount)');
      if (mainPrice) {
        mainPrice.textContent = '\u20AC\u00A0' + formatted;
      } else {
        document.querySelectorAll('.woocommerce-Price-amount, .price .amount, .price').forEach(el => {
          if (!el.closest('del')) {
            el.textContent = '\u20AC\u00A0' + formatted;
          }
        });
      }

      // Update hidden fields
      if (selField) selField.value = JSON.stringify(selections);
      if (exField) exField.value = optionTotal.toFixed(2);

      // Dispatch custom event
      widget.dispatchEvent(new CustomEvent('fw-recalc', {
        detail: { pid, base, price: total, selections },
        bubbles: true,
        cancelable: true
      }));
    }

    // ── Validation ───────────────────────────────────────────────────────────
    function validateRequired() {
      let valid = true;

      cfg.groups.forEach(group => {
        if (!group.required) return;

        if (group.type === 'text') {
          const inp = widget.querySelector(`.fw-text-input[data-group="${group.id}"]`);
          if (!inp || inp.value.trim() === '') {
            showGroupError(group.id, 'Dit is een verplicht veld.');
            valid = false;
          }
          return;
        }

        if (group.type === 'depth') {
          const select = widget.querySelector(`.fw-depth-type[data-group="${group.id}"]`);
          if (!select || !select.value) {
            showGroupError(group.id, 'Maak een keuze.');
            valid = false;
          }
          return;
        }

        if (group.type === 'length') {
          const select = widget.querySelector(`.fw-length-type[data-group="${group.id}"]`);
          if (!select || !select.value) {
            showGroupError(group.id, 'Maak een keuze.');
            valid = false;
          }
          return;
        }

        const checked = widget.querySelector(`input[data-group="${group.id}"]:checked`);
        if (!checked) {
          showGroupError(group.id, 'Maak een keuze.');
          valid = false;
        }
      });

      return valid;
    }

    function showGroupError(groupId, message) {
      const groupEl = widget.querySelector(`.fw-group[data-group-id="${groupId}"]`);
      if (!groupEl) return;
      const err = groupEl.querySelector('.fw-group-error');
      if (err) {
        err.textContent = message;
        err.style.display = 'block';
      }
    }

    // ── Toggle helpers ───────────────────────────────────────────────────────
    function toggleDepthCustom(select) {
      const wrapper = select.closest('.fw-depth-wrapper');
      if (!wrapper) return;
      const customRow = wrapper.querySelector('.fw-depth-custom-row');
      if (!customRow) return;
      if (select.value === 'maatwerk') {
        customRow.style.display = '';
        const inp = customRow.querySelector('.fw-depth-custom');
        if (inp) inp.focus();
      } else {
        customRow.style.display = 'none';
      }
    }

    function toggleLengthCustom(select) {
      const wrapper = select.closest('.fw-length-wrapper');
      if (!wrapper) return;
      const customRow = wrapper.querySelector('.fw-custom-length-row');
      if (!customRow) return;
      if (select.value === 'maatwerk') {
        customRow.style.display = '';
        const inp = customRow.querySelector('.fw-custom-length');
        if (inp) inp.focus();
      } else {
        customRow.style.display = 'none';
        const err = customRow.querySelector('.fw-custom-length-error');
        if (err) err.style.display = 'none';
      }
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    widget._fwRecalc = recalc;
    recalc();
  }
})();
