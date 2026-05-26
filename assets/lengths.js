document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.fw-length-type').forEach(function(select) {
    var $select = jQuery(select);
    var $custom = jQuery('.fw-custom-length');
    var $wrap = $select.closest('.fw-lengths-wrapper');
    var $price = jQuery('.single_variation .woocommerce-Price-amount, .summary .price .amount, .fw-product-price');
    var origPrice = null;
    var settings = null;
    if ($wrap.length) {
        settings = $wrap.get(0).hasAttribute('data-settings') ? $wrap.data('settings') : null;
        if (!settings) {
            try {
                settings = JSON.parse($wrap.attr('data-settings'));
            } catch(e) { settings = null; }
        }
    }
    console.log('[FW Lengths] JS geladen', $select.length, settings, $wrap.length);
    if (!settings) {
        console.warn('[FW Lengths] Geen settings gevonden!');
        return;
    }
    function getBasePrice() {
        var txt = $price.first().text();
        var val = parseFloat(txt.replace(/[^\d,\.]/g, '').replace(',', '.'));
        return isNaN(val) ? 0 : val;
    }
    function updatePrice() {
        var add = 0;
        var val = $select.val();
        var idx = $select.prop('selectedIndex');
        // settings.lengths en settings.prices zijn arrays
        if (settings.prices && Array.isArray(settings.prices)) {
            add = parseFloat(settings.prices[idx]) || 0;
        } else if (Array.isArray(settings) && typeof settings[idx] === 'object' && settings[idx] !== null && settings[idx].price !== undefined) {
            add = parseFloat(settings[idx].price) || 0;
        }
        if (val === 'maatwerk') {
            add = 0;
        }
        console.log('[FW Lengths] updatePrice', {idx, add, val, settings});
        var base = origPrice !== null ? origPrice : getBasePrice();
        var newPrice = base + add;
        $price.text(newPrice.toLocaleString('nl-NL', {minimumFractionDigits:2, maximumFractionDigits:2}));
    }
    function toggleCustomRow() {
        var customRow = $wrap.find('.fw-custom-length-row');
        if ($select.val() === 'maatwerk') {
            customRow.show();
            customRow.find('input').focus();
        } else {
            customRow.hide();
            customRow.find('.fw-custom-length-error').hide();
        }
    }
    if ($price.length && origPrice === null) {
        origPrice = getBasePrice();
    }
    $select.on('change', function(){
        updatePrice();
        toggleCustomRow();
    });
    $custom.on('input', function(){
        if ($select.val() === 'maatwerk') updatePrice();
    });
    jQuery(document.body).on('found_variation reset_data', function(){
        origPrice = getBasePrice();
        updatePrice();
    });
    toggleCustomRow();
    updatePrice();
  });
});
