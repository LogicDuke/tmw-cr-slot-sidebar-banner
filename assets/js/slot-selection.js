(function(root, factory) {
    var api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.TMWSpinRotation = api;
    }
})(typeof window !== 'undefined' ? window : this, function() {
    'use strict';

    function selectOffer(offers, hasSelectedInitialOffer, randomSource) {
        if (!Array.isArray(offers) || !offers.length) {
            return { offer: null, index: -1 };
        }

        var index = 0;

        if (hasSelectedInitialOffer) {
            var randomValue = typeof randomSource === 'function' ? randomSource() : Math.random();
            var normalizedValue = Number.isFinite(randomValue) ? randomValue : 0;
            normalizedValue = Math.max(0, Math.min(normalizedValue, 0.9999999999999999));
            index = Math.floor(normalizedValue * offers.length);
        }

        return { offer: offers[index], index: index };
    }

    return { selectOffer: selectOffer };
});
