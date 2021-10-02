var aspTaxVariationsNG = function (data) {
    var parent = this;

    parent.init = function () {
        vars.data.default_tax = vars.data.tax;
        parent.billingSwitch = document.getElementById('same-bill-ship-addr');
        parent.bCountrySelect = document.getElementById('country');
        parent.bState = document.getElementById('state');
        parent.bCity = document.getElementById('city');
        parent.sCountrySelect = document.getElementById('shipping_country');
        parent.sState = document.getElementById('shipping_state');
        parent.sCity = document.getElementById('shipping_city');
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
        if (parent.bState) {
            parent.bState.addEventListener('change', function () {
                doAddonAction('billingAddressChanged');
            });
        }
        if (parent.bCity) {
            parent.bCity.addEventListener('change', function () {
                doAddonAction('billingAddressChanged');
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
        if (parent.sState) {
            parent.sState.addEventListener('change', function () {
                doAddonAction('shippingAddressChanged');
            });
        }
        if (parent.sCity) {
            parent.sCity.addEventListener('change', function () {
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

    parent.billingAddressChanged = function () {
        var bS = '';
        if (parent.bState) {
            bS = parent.bState.value;
        }
        var bC = '';
        if (parent.bCity) {
            bC = parent.bCity.value;
        }
        if (vars.data.tax_variations_type !== 'b') {
            if (parent.billingSwitch && !parent.billingSwitch.checked) {
                return;
            }
        }
        if (!parent.bCountrySelect) {
            if (vars.data.new_bill_addr && vars.data.new_bill_addr.country) {
                parent.doTaxVariations(vars.data.new_bill_addr.country, vars.data.new_bill_addr.state, vars.data.new_bill_addr.city);
            }
            return;
        }
        parent.doTaxVariations(parent.bCountrySelect.value, bS, bC);
    }

    parent.shippingAddressChanged = function () {
        if (!parent.billingSwitch) {
            if (vars.data.new_ship_addr && vars.data.new_ship_addr.country) {
                parent.doTaxVariations(vars.data.new_ship_addr.country, vars.data.new_ship_addr.state, vars.data.new_ship_addr.city);
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
                var sS = '';
                if (parent.sState) {
                    sS = parent.sState.value;
                }
                var sC = '';
                if (parent.sCity) {
                    sC = parent.sCity.value;
                }
                parent.doTaxVariations(parent.sCountrySelect.value, sS, sC);

            }
        }
    }

    this.doTaxVariations = function (cCode, state, city) {
        var newTax = 0;
        var applied = 0;
        vars.data.tax_variations.forEach(function (v) {
            switch (v.type) {
                case '0':
                    if (cCode && v.loc === cCode) {
                        newTax += v.amount;
                        applied++;
                    }
                    break;
                case '1':
                    if (state && v.loc.toLowerCase() === state.toLowerCase()) {
                        newTax += v.amount;
                        applied++;
                    }
                    break;
                case '2':
                    if (city && v.loc.toLowerCase() === city.toLowerCase()) {
                        newTax += v.amount;
                        applied++;
                    }
                    break;
                default:
                    break;
            }
            if (applied > 0) {
                vars.data.tax = newTax;
                updateAllAmounts();
            } else if (vars.data.tax !== vars.data.default_tax) {
                vars.data.tax = vars.data.default_tax;
                updateAllAmounts();
            }
        });
    }
}