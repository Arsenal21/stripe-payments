var aspTaxVariationsNG = function (data) {
    var parent = this;

    parent.init = function () {
        vars.data.default_tax = vars.data.tax;
        parent.billingSwitch = document.getElementById('same-bill-ship-addr');
        parent.bCountrySelect = document.getElementById('country');
        parent.sCountrySelect = document.getElementById('shipping_country');
        parent.bCountrySelect.addEventListener('change', function () {
            if (this.value === '0') {
                return;
            }
            doAddonAction('billingAddressChanged');
            if (parent.billingSwitch.checked) {
                doAddonAction('shippingAddressChanged');
            }
        });
        doAddonAction('billingAddressChanged');
    }

    parent.billingAddressChanged = function () {
        if (vars.data.tax_variations[parent.bCountrySelect.value]) {
            vars.data.tax = vars.data.tax_variations[parent.bCountrySelect.value];
            updateAllAmounts();
        } else {
            if (vars.data.tax !== vars.data.default_tax) {
                vars.data.tax = vars.data.default_tax;
                updateAllAmounts();
            }
        }
    }

    parent.shippingAddressChanged = function () {
        if (vars.data.tax_variations_type === 's') {
            if (parent.billingSwitch.checked) {
                doAddonAction('billingAddressChanged');
            } else {
                if (vars.data.tax_variations[parent.sCountrySelect.value]) {
                    vars.data.tax = vars.data.tax_variations[parent.sCountrySelect.value];
                    updateAllAmounts();
                } else {
                    if (vars.data.tax !== vars.data.default_tax) {
                        vars.data.tax = vars.data.default_tax;
                        updateAllAmounts();
                    }
                }

            }
        }
    }
}