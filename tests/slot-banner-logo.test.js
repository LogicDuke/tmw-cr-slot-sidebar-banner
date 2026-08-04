'use strict';

const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

class Element {
    constructor(tagName) {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.style = {};
        this.attributes = {};
        this.textContent = '';
    }
    appendChild(child) { this.children.push(child); }
    remove() { this.removed = true; }
    setAttribute(name, value) { this.attributes[name] = value; }
    set innerHTML(value) { this.children = []; }
}

const documentStub = {
    addEventListener() {},
    createElement(tagName) { return new Element(tagName); }
};
const source = fs.readFileSync(require.resolve('../assets/js/slot-banner.js'), 'utf8')
    .replace(/\}\)\(\);\s*$/, 'globalThis.__slotBannerTest = { renderReelFace: renderReelFace };})();');
const context = { document: documentStub, window: {}, URL, console };
vm.runInNewContext(source, context);

const overrideUrl = 'https://top-models.webcam/wp-content/plugins/tmw-cr-slot-sidebar-banner/assets/logos/80x80/Slut-Roulette.png';
const wrapper = new Element('div');
context.__slotBannerTest.renderReelFace(wrapper, { id: '153', name: 'Slut Roulette', logo_url: overrideUrl });
const fallback = wrapper.children[0];
const image = wrapper.children[1];

assert.strictEqual(image.tagName, 'IMG', 'logo_url should render an img element');
assert.strictEqual(image.src, overrideUrl, 'img should consume the exact logo_url');
image.onload();
assert.strictEqual(fallback.style.display, 'none', 'text should hide after a successful image load');
image.onerror();
assert.strictEqual(image.removed, true, 'failed image should be removed');
assert.strictEqual(fallback.style.display, '', 'text fallback should return after an image error');

const textOnlyWrapper = new Element('div');
context.__slotBannerTest.renderReelFace(textOnlyWrapper, { id: '999', name: 'Text Only', logo_url: '' });
assert.strictEqual(textOnlyWrapper.children.length, 1, 'empty logo_url should retain text-only rendering');

console.log('Total: 6 passed, 0 failed');
