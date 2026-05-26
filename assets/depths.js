(function($){
$(document).ready(function(){
    var $select = $('.fw-depth-type');
    var $custom = $('.fw-custom-depth');
    var $wrap = $select.closest('.fw-depths-wrapper');
    var $price = $('.single_variation .woocommerce-Price-amount, .summary .price .amount, .fw-product-price');
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
    // Haal product_id en group_id uit de wrapper of select
    var productId = $('input[name="fw_product_id"]').val() || '';
    var groupId = $wrap.closest('.fw-group').data('group-id') || 'depth';
    var fieldType = 'fw_depth_' + productId + '_' + groupId + '_type';
    var fieldCustom = 'fw_depth_' + productId + '_' + groupId + '_custom';
    console.log('[FW Depths] JS geladen', $select.length, settings, $wrap.length, fieldType, fieldCustom);
    if (!settings) {
        console.warn('[FW Depths] Geen settings gevonden!');
        return;
    }
    function getBasePrice() {
        var txt = $price.first().text();
        var val = parseFloat(txt.replace(/[^\d,\.]/g, '').replace(',', '.'));
        return isNaN(val) ? 0 : val;
    }
    function updatePrice() {
        var percent = 0;
        var val = $select.val();
        var idx = $select.prop('selectedIndex');
        if (Array.isArray(settings) && typeof settings[idx] === 'object' && settings[idx] !== null) {
            percent = parseFloat(settings[idx].percent) || 0;
        } else if (settings.percents && Array.isArray(settings.percents)) {
            percent = parseFloat(settings.percents[idx]) || 0;
        }
        if (val === 'maatwerk') {
            percent = 0;
        }
        var base = origPrice !== null ? origPrice : getBasePrice();
        var newPrice = base + (base * percent / 100);
        $price.text(newPrice.toLocaleString('nl-NL', {minimumFractionDigits:2, maximumFractionDigits:2}));
    }
    function toggleCustomRow() {
        var customRow = $wrap.find('.fw-custom-depth-row');
        if ($select.val() === 'maatwerk') {
            customRow.show();
            customRow.find('input').focus();
        } else {
            customRow.hide();
            customRow.find('.fw-custom-depth-error').hide();
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
    $(document.body).on('found_variation reset_data', function(){
        origPrice = getBasePrice();
        updatePrice();
    });
    // Voeg diepte toe aan fw_options bij add to cart, net als lengte
    jQuery('form.cart').on('submit', function(e) {
        var fw_options = jQuery(this).find('[name="fw_options"]').val();
        try { fw_options = fw_options ? JSON.parse(fw_options) : {}; } catch(e) { fw_options = {}; }
        var depthType = $select.val();
        var customDepth = $custom.val();
        if (depthType) fw_options[fieldType] = depthType;
        if (depthType === 'maatwerk' && customDepth) fw_options[fieldCustom] = customDepth;
        jQuery(this).find('[name="fw_options"]').val(JSON.stringify(fw_options));
    });
    toggleCustomRow();
    updatePrice();
});
})(jQuery);
