(function() {
    var BASE_SPINNING_DURATION = 2600;
    var COLUMN_SPINNING_DURATION = 450;
    var ICONS_PER_REEL = 40;

    function appendTrackingParam(anchor, param, value) {
        if (!anchor || !anchor.href || !param || !value) {
            return;
        }

        try {
            var url = new URL(anchor.href);
            url.searchParams.set(param, value);
            anchor.href = url.toString();
        } catch (error) {
            if (anchor.href.indexOf(param + '=') === -1) {
                var separator = anchor.href.indexOf('?') === -1 ? '?' : '&';
                anchor.href = anchor.href + separator + encodeURIComponent(param) + '=' + encodeURIComponent(value);
            }
        }
    }

    function parseOffers(banner) {
        var raw = banner.getAttribute('data-slot-offers');

        if (!raw) {
            return [];
        }

        try {
            var parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed.filter(function(item) {
                return item && item.id && item.image;
            });
        } catch (error) {
            return [];
        }
    }

    function getOfferAbbreviation(offer) {
        var name = ((offer && offer.name) || '').trim();
        var clean = name.replace(/[^a-z0-9 ]/gi, ' ').trim();
        var parts = clean ? clean.split(/\s+/) : [];
        var letters = '';

        for (var i = 0; i < parts.length && letters.length < 3; i++) {
            if (parts[i]) {
                letters += parts[i].charAt(0).toUpperCase();
            }
        }

        if (letters.length < 3) {
            letters = clean.replace(/\s+/g, '').slice(0, 3).toUpperCase();
        }

        return letters || 'N/A';
    }

    function renderReelFace(wrapper, offer) {
        wrapper.innerHTML = '';

        var fallback = document.createElement('span');
        fallback.className = 'tmw-cr-slot-banner__reel-text';
        fallback.textContent = getOfferAbbreviation(offer);
        wrapper.appendChild(fallback);

        if (!offer || !offer.logo_url) {
            return;
        }

        var logo = document.createElement('img');
        logo.className = 'tmw-cr-slot-banner__reel-logo';
        logo.src = offer.logo_url;
        logo.alt = ((offer.name || 'Offer') + ' logo').trim();
        logo.loading = 'lazy';
        logo.setAttribute('data-offer-id', offer.id || '');
        logo.onerror = function() {
            logo.remove();
            fallback.style.display = '';
        };
        logo.onload = function() {
            fallback.style.display = 'none';
        };
        wrapper.appendChild(logo);
    }

    function createIcon(offer) {
        var wrapper = document.createElement('div');
        wrapper.className = 'icon';
        renderReelFace(wrapper, offer);

        return {
            node: wrapper,
            offer: offer
        };
    }

    function applyOffer(iconState, offer) {
        if (!iconState || !iconState.node || !offer) {
            return;
        }

        renderReelFace(iconState.node, offer);

        iconState.offer = offer;
    }

    function setInitialItems(state) {
        if (!state.offers.length || !state.columns.length) {
            return;
        }

        state.columns.forEach(function(reel, index) {
            reel.items = [];
            reel.cloneStart = 0;

            var fragment = document.createDocumentFragment();
            var iconsToCreate = Math.max(ICONS_PER_REEL, state.offers.length * 5);
            var offset = index % Math.max(1, state.offers.length);

            for (var i = 0; i < iconsToCreate; i++) {
                var offer = state.offers[(offset + i) % state.offers.length];
                var iconState = createIcon(offer);
                fragment.appendChild(iconState.node);
                reel.items.push(iconState);
            }

            reel.cloneStart = reel.items.length;

            for (var cloneIndex = 0; cloneIndex < 3 && cloneIndex < reel.items.length; cloneIndex++) {
                var original = reel.items[cloneIndex];
                var clone = createIcon(original.offer);
                fragment.appendChild(clone.node);
                reel.items.push(clone);
            }

            reel.col.innerHTML = '';
            reel.col.appendChild(fragment);
            reel.col.style.transform = 'translateY(0)';
            reel.itemHeight = 0;
        });
    }

    function measureItemHeight(reel) {
        if (!reel || reel.itemHeight) {
            return reel ? reel.itemHeight : 0;
        }

        var sample = reel.col.querySelector('.icon');

        if (!sample) {
            return 0;
        }

        var rect = sample.getBoundingClientRect();
        var height = rect && rect.height ? rect.height : sample.offsetHeight;

        reel.itemHeight = height || 0;

        return reel.itemHeight;
    }

    function prepareColumnForSpin(reel) {
        if (!reel || !reel.col) {
            return;
        }

        var height = measureItemHeight(reel);

        if (!height) {
            return;
        }

        var offsetCount = typeof reel.cloneStart === 'number' ? reel.cloneStart : Math.max(0, reel.items.length - 3);
        var offset = offsetCount * height;

        reel.col.style.transform = 'translateY(-' + offset + 'px)';
    }

    function copyTopIconsToClones(reel) {
        if (!reel || typeof reel.cloneStart !== 'number') {
            return;
        }

        for (var i = 0; i < 3; i++) {
            var source = reel.items[i];
            var clone = reel.items[reel.cloneStart + i];

            if (!source || !clone) {
                continue;
            }

            applyOffer(clone, source.offer);
        }
    }

    function setResult(state, prepareForSpin) {
        var results = [];

        state.columns.forEach(function(reel) {
            if (!state.offers.length || !reel.items.length) {
                results.push(null);
                return;
            }

            var offer = state.offers[Math.floor(Math.random() * state.offers.length)];
            results.push(offer);

            applyOffer(reel.items[0], offer);
            copyTopIconsToClones(reel);

            if (prepareForSpin) {
                prepareColumnForSpin(reel);
            } else {
                reel.col.style.transform = 'translateY(0)';
            }
        });

        return results;
    }

    function applyDefaultState(state) {
        state.banner.classList.remove('tmw-cr-slot-banner--win');

        if (state.cta) {
            if (state.defaultCtaUrl) {
                state.cta.href = state.defaultCtaUrl;
            }

            if (state.defaultCtaText) {
                state.cta.textContent = state.defaultCtaText;
            }

            appendTrackingParam(state.cta, state.param, state.value);
        }

        if (state.offerNameTarget) {
            state.offerNameTarget.textContent = state.defaultOfferName || state.defaultCtaText || '';
        }

        if (state.resultLabel) {
            state.resultLabel.textContent = state.defaultResultLabel;
        }
    }

    function finishSpin(state, results) {
        state.banner.classList.remove('tmw-cr-slot-banner--spinning');
        state.isSpinning = false;

        if (state.spinButton) {
            state.spinButton.disabled = false;
        }

        if (!results.length) {
            applyDefaultState(state);
            return;
        }

        var first = results[0];
        var matchingOffer = null;

        if (first && results.every(function(result) {
            return result && result.id === first.id;
        })) {
            matchingOffer = first;
        }

        if (matchingOffer) {
            if (window.console && typeof window.console.debug === 'function') {
                window.console.debug(
                    '[TMW-BANNER-FRONTEND-LOGO]',
                    'selected_offer_id=' + (matchingOffer.id || ''),
                    'brand_key=' + (matchingOffer.brand_key || ''),
                    'logo_filename=' + (matchingOffer.logo_filename || ''),
                    'has_logo=' + (matchingOffer.logo_url ? 'yes' : 'no')
                );
            }
            state.banner.classList.add('tmw-cr-slot-banner--win');

            if (state.resultLabel) {
                state.resultLabel.textContent = 'Winner!';
            }

            if (state.offerNameTarget) {
                state.offerNameTarget.textContent = matchingOffer.name || state.defaultOfferName || state.defaultCtaText || '';
            }

            if (state.cta) {
                var nextHref = matchingOffer.cta_url || state.defaultCtaUrl;

                if (nextHref) {
                    state.cta.href = nextHref;
                }

                state.cta.textContent = matchingOffer.cta_text || state.defaultCtaText || state.cta.textContent;
                appendTrackingParam(state.cta, state.param, state.value);
            }

            return;
        }

        applyDefaultState(state);
    }

    function spin(state, button) {
        if (state.isSpinning || !state.offers.length || !state.container) {
            return;
        }

        state.isSpinning = true;
        state.banner.classList.add('tmw-cr-slot-banner--spinning');
        state.banner.classList.remove('tmw-cr-slot-banner--win');

        if (button) {
            button.disabled = true;
        }

        applyDefaultState(state);

        var results = setResult(state, true);

        if (!results.length) {
            finishSpin(state, results);
            return;
        }

        state.container.classList.remove('spinning');
        void state.container.offsetWidth;

        var maxTime = 0;

        state.columns.forEach(function(reel, index) {
            var duration = BASE_SPINNING_DURATION + COLUMN_SPINNING_DURATION * index;
            var delay = COLUMN_SPINNING_DURATION * index;

            reel.col.style.animationDuration = duration + 'ms';
            reel.col.style.animationDelay = delay + 'ms';

            var total = duration + delay;

            if (total > maxTime) {
                maxTime = total;
            }
        });

        state.container.classList.add('spinning');

        window.setTimeout(function() {
            state.container.classList.remove('spinning');

            state.columns.forEach(function(reel) {
                reel.col.style.animationDuration = '';
                reel.col.style.animationDelay = '';
                reel.col.style.transform = 'translateY(0)';
            });

            finishSpin(state, results);
        }, maxTime + 80);
    }

    function initializeBanner(banner) {
        var offers = parseOffers(banner);
        var container = banner.querySelector('#container');
        var columnNodes = container ? Array.prototype.slice.call(container.querySelectorAll('.col')) : [];
        var spinButton = banner.querySelector('#spin');
        var cta = banner.querySelector('.tmw-cr-slot-banner__cta');
        var offerNameTarget = banner.querySelector('.tmw-cr-slot-banner__offer-name');
        var resultLabel = banner.querySelector('.tmw-cr-slot-banner__result-label');
        var defaultResultLabel = resultLabel ? resultLabel.textContent : '';
        var param = banner.getAttribute('data-subid-param');
        var value = banner.getAttribute('data-subid-value');
        var defaultCtaText = banner.getAttribute('data-default-cta-text') || '';
        var defaultCtaUrl = banner.getAttribute('data-default-cta-url') || '';
        var defaultOfferName = offerNameTarget ? offerNameTarget.textContent : '';

        var state = {
            banner: banner,
            offers: offers,
            container: container,
            columns: columnNodes.map(function(node) {
                return {
                    col: node,
                    items: [],
                    cloneStart: 0,
                    itemHeight: 0
                };
            }),
            spinButton: spinButton,
            cta: cta,
            offerNameTarget: offerNameTarget,
            resultLabel: resultLabel,
            defaultResultLabel: defaultResultLabel,
            defaultCtaText: defaultCtaText,
            defaultCtaUrl: defaultCtaUrl,
            defaultOfferName: defaultOfferName,
            param: param,
            value: value,
            isSpinning: false
        };

        if (state.spinButton) {
            state.spinButton.addEventListener('click', function(event) {
                event.preventDefault();
                spin(state, state.spinButton);
            });
        }

        setInitialItems(state);

        if (state.offers.length && state.columns.length) {
            setResult(state, false);
            applyDefaultState(state);
            window.setTimeout(function() {
                spin(state, state.spinButton);
            }, 300);
        } else {
            applyDefaultState(state);
        }

        banner.classList.add('tmw-cr-slot-banner--ready');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var banners = document.querySelectorAll('.tmw-cr-slot-banner');

        if (!banners.length) {
            return;
        }

        banners.forEach(initializeBanner);
    });
})();
