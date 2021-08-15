var aspTaxVariationsNG = function (data) {
    var parent = this;

    parent.init = function () {
        vars.data.default_tax = vars.data.tax;
        parent.billingSwitch = document.getElementById('same-bill-ship-addr');
        parent.bCountrySelect = document.getElementById('country');
        parent.sCountrySelect = document.getElementById('shipping_country');
        if (parent.bCountrySelect) {
            parent.bCountrySelect.addEventListener('change', function () {
                if (this.value === '0') {
                    return;
                }
                doAddonAction('billingAddressChanged');
                if (parent.billingSwitch && parent.billingSwitch.checked) {
                    doAddonAction('shippingAddressChanged');
                }
            });
        }
        if (parent.sCountrySelect) {
            parent.sCountrySelect.addEventListener('change', function () {
                if (this.value === '0') {
                    return;
                }
                doAddonAction('shippingAddressChanged');
            });
        }
        if (parent.billingSwitch) {
            parent.billingSwitch.addEventListener('change', function () {
                doAddonAction('billingAddressChanged');
                doAddonAction('shippingAddressChanged');
            });
        }
        doAddonAction('billingAddressChanged');
    }

    this.setCountryTax = function (cCode) {
        if (vars.data.tax_variations[cCode]) {
            vars.data.tax = vars.data.tax_variations[cCode];
            updateAllAmounts();
        } else {
            if (vars.data.tax !== vars.data.default_tax) {
                vars.data.tax = vars.data.default_tax;
                updateAllAmounts();
            }
        }
    }

    parent.billingAddressChanged = function () {
        if (vars.data.tax_variations_type !== 'b') {
            if (parent.billingSwitch && !parent.billingSwitch.checked) {
                return;
            }
        }
        if (!parent.bCountrySelect) {
            if (vars.data.new_bill_addr && vars.data.new_bill_addr.country) {
                parent.setCountryTax(vars.data.new_bill_addr.country);
            }
            return;
        }
        parent.setCountryTax(parent.bCountrySelect.value);
    }

    parent.shippingAddressChanged = function () {
        if (!parent.billingSwitch) {
            if (vars.data.new_ship_addr && vars.data.new_ship_addr.country) {
                parent.setCountryTax(vars.data.new_ship_addr.country);
            }
            return;
        }
        if (vars.data.tax_variations_type === 's') {
            if (parent.billingSwitch.checked) {
                doAddonAction('billingAddressChanged');
            } else {
                if (!parent.sCountrySelect) {
                    return;
                }
                parent.setCountryTax(parent.sCountrySelect.value);

            }
        }
    }
}