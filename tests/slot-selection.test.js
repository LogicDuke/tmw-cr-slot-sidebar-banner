'use strict';

const assert = require('assert');
const rotation = require('../assets/js/slot-selection.js');
const offers = [{ id: '8780' }, { id: '153' }, { id: '10022' }];

assert.strictEqual(rotation.selectOffer(offers, false, () => 0.99).offer.id, '8780');
assert.deepStrictEqual(rotation.selectOffer(offers, true, () => 0.34), { offer: offers[1], index: 1 });
assert.deepStrictEqual(rotation.selectOffer(offers, true, () => 0.99), { offer: offers[2], index: 2 });
assert.strictEqual(offers.length, 3, 'visual three-logo matching must not mutate the offer pool');
assert.deepStrictEqual(rotation.selectOffer([], true, () => 0), { offer: null, index: -1 });

console.log('Total: 5 passed, 0 failed');
