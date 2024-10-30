(($) => {
    $(document).ready(() => {

        let order, params, autoStarter;
        if (window.CryptoPayVars || window.CryptoPayLiteVars) {
            order = window?.CryptoPayVars?.order || window?.CryptoPayLiteVars?.order;
            params = window?.CryptoPayVars?.params || window?.CryptoPayLiteVars?.params;
        
            let startedApp;
            autoStarter = (order, params) => {
                if (!startedApp) {
                    if (window.CryptoPayApp) {
                        startedApp = window.CryptoPayApp.start(order, params);
                    } else {
                        startedApp = window.CryptoPayLiteApp.start(order, params);
                    }
                } else {
                    startedApp.reStart(order, params);
                }
            }
        
            autoStarter(order, params);
        }
    
        $("#other_discount_code_button").click(function() {
            var discountCode = jQuery('#other_discount_code').val();
    
            if (!discountCode) return;
            
            var levelId = jQuery('#level').val();
    
            $.ajax({
                method: 'POST',
                url: PMProCryptoPay.ajax_url,
                dataType: 'json',
                data: {
                    levelId,
                    discountCode,
                    nonce: PMProCryptoPay.nonce,
                    action: 'pmpro_cryptopay_use_discount',
                },
                beforeSend() {
                    $("#PMProCryptoPayWrapper #cryptopay").hide();
                    $("#PMProCryptoPayWrapper").append(`
                        <div style="text-align: center;" id="pmpro-cryptopay-please-wait">
                            ${PMProCryptoPay.lang.pleaseWait}
                        </div>
                    `);
                },
                success(response) {
                    if (response.data && response.data.amount) {
                        params.discountCode = discountCode;
                        order.amount = response.data.amount;
                        autoStarter(order, params);
                    }
                    $("#PMProCryptoPayWrapper #cryptopay").show();
                    $("#PMProCryptoPayWrapper #pmpro-cryptopay-please-wait").remove();
                },
                error(error) {
                    alert(error.statusText);
                },
            });
        });
    });
})(jQuery);