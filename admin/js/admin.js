/* Scout Admin JS */
jQuery(function($) {
    // Payment added notification
    if (window.location.search.indexOf('payment_added=1') > -1) {
        var notice = $('<div class="notice notice-success is-dismissible"><p>✅ Paiement enregistré avec succès.</p></div>');
        $('.wrap h1').after(notice);
    }
});
