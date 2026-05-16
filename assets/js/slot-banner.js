(function() {
    var BASE_SPINNING_DURATION = 2600;
    var COLUMN_SPINNING_DURATION = 450;
    var ICONS_PER_REEL = 40;
    var POST_SPIN_RESULT_LABEL = 'Your match is ready';
    var POST_SPIN_CTA_TEXT = 'VISIT OFFER';

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

    function getOfferDisplayName(offer) {
        var sanitizedName = sanitizeFrontendOfferName((offer && offer.name) || '');
        return sanitizedName || getOfferAbbreviation(offer);
    }

    function sanitizeFrontendOfferName(name) {
        var value = (name || '').trim();

        if (!value) {
            return '';
        }

        return value.replace(/\s*[-–—|:]\s*(PPS|CPA|CPL|RevShare|SOI|DOI|CPC|CPI|CPM|Smartlink|fallback)\s*$/i, '').trim();
    }


    function renderReelFace(wrapper, offer) {
        wrapper.innerHTML = '';

        var fallback = document.createElement('span');
        fallback.className = 'tmw-cr-slot-banner__reel-text';
        fallback.textContent = (offer && offer.vertical) === 'ai' ? '🤖 AI' : getOfferDisplayName(offer);
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


    function renderFinalSelection(state, selectedOffer, prepareForSpin) {
        if (!selectedOffer) {
            return [];
        }

        var results = [];

        state.columns.forEach(function(reel) {
            if (!reel.items.length) {
                results.push(null);
                return;
            }

            results.push(selectedOffer);
            applyOffer(reel.items[0], selectedOffer);
            copyTopIconsToClones(reel);

            if (prepareForSpin) {
                prepareColumnForSpin(reel);
            } else {
                reel.col.style.transform = 'translateY(0)';
            }
        });

        if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[TMW-BANNER-MOBILE] final_selection offer_id=' + (selectedOffer.id || '') + ' reels=' + state.columns.length);
        }

        return results;
    }

    function setResult(state, prepareForSpin) {
        var results = [];
        var winner = null;

        if (state.offers.length) {
            winner = state.offers[Math.floor(Math.random() * state.offers.length)];

            if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[TMW-BANNER-OFFER] select_start eligible_count=' + state.offers.length);
                window.console.debug('[TMW-BANNER-OFFER] selected offer_id=' + (winner.id || '') + ' logo="' + (winner.logo_filename || '') + '"');
            }
        }

        if (!winner) {
            state.columns.forEach(function() {
                results.push(null);
            });
            return results;
        }

        return renderFinalSelection(state, winner, prepareForSpin);
    }


    function setOfferSloganVisibility(target, text, offerId, debugEnabled) {
        if (!target) {
            return;
        }

        var safeText = typeof text === 'string' ? text.trim() : '';

        target.textContent = safeText;

        if (safeText) {
            target.hidden = false;
            target.removeAttribute('aria-hidden');
            target.classList.add('tmw-cr-slot-banner__offer-slogan--active');
            return;
        }

        target.hidden = true;
        target.setAttribute('aria-hidden', 'true');
        target.classList.remove('tmw-cr-slot-banner__offer-slogan--active');

        if (debugEnabled && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[TMW-CR-CTA] post_spin_empty_pill_hidden offer_id=' + (offerId || ''));
        }
    }

    function setCtaText(cta, text) {
        if (!cta) {
            return;
        }

        var safeText = typeof text === 'string' ? text : '';
        var label = cta.querySelector('.tmw-cr-slot-banner__cta-label');

        if (!label) {
            cta.textContent = '';
            label = document.createElement('span');
            label.className = 'tmw-cr-slot-banner__cta-label';
            cta.appendChild(label);
        }

        label.textContent = safeText;
    }

    function setCtaVisibility(state, isVisible) {
        if (!state || !state.cta) {
            return;
        }

        state.cta.hidden = !isVisible;
        if (isVisible) {
            state.cta.removeAttribute('aria-hidden');
        } else {
            state.cta.setAttribute('aria-hidden', 'true');
        }
    }

    function hideResultArea(state) {
        state.banner.classList.add('tmw-cr-slot-banner--pre-win');
        state.banner.classList.remove('tmw-cr-slot-banner--win');

        if (state.resultLabel) {
            state.resultLabel.textContent = '';
        }

        if (state.offerNameTarget) {
            state.offerNameTarget.textContent = '';
        }

        setOfferSloganVisibility(state.offerSloganTarget, '', '', state.debugEnabled);

        setCtaText(state.cta, POST_SPIN_CTA_TEXT);
        setCtaVisibility(state, false);

        if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[TMW-CR-CTA] hidden_non_win_result');
        }
    }

    function applyPreWinState(state) {
        hideResultArea(state);
        if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
            window.console.debug('[TMW-CR-CTA] pre_win_state_hidden');
        }
    }

    function applySpinningState(state) {
        hideResultArea(state);
    }

    function showWinResult(state, matchingOffer) {
        if (!matchingOffer) {
            return;
        }

        state.banner.classList.remove('tmw-cr-slot-banner--pre-win');
        state.banner.classList.add('tmw-cr-slot-banner--win');

        if (state.resultLabel) {
            state.resultLabel.textContent = POST_SPIN_RESULT_LABEL;
        }

        if (state.offerNameTarget) {
            state.offerNameTarget.textContent = sanitizeFrontendOfferName(matchingOffer.name) || getOfferAbbreviation(matchingOffer);
        }

        setOfferSloganVisibility(state.offerSloganTarget, '', matchingOffer.id || '', state.debugEnabled);

        if (state.cta) {
            var nextHref = matchingOffer.cta_url || state.defaultCtaUrl || '';
            if (nextHref) {
                state.cta.href = nextHref;
            }

            setCtaText(state.cta, POST_SPIN_CTA_TEXT);
            setCtaVisibility(state, !!nextHref);
            appendTrackingParam(state.cta, state.param, state.value);
            if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[TMW-CR-CTA] cta_label_sanitized');
            }
        }
    }

    function isThreeReelWin(results) {
        if (!Array.isArray(results) || results.length !== 3) {
            return false;
        }

        if (!results[0] || !results[1] || !results[2]) {
            return false;
        }

        var firstId = String(results[0].id || '');
        return firstId !== '' && String(results[1].id || '') === firstId && String(results[2].id || '') === firstId;
    }

    function finishSpin(state, results) {
        state.banner.classList.remove('tmw-cr-slot-banner--spinning');
        state.isSpinning = false;

        if (state.spinButton) {
            state.spinButton.disabled = false;
        }

        if (!results.length || !isThreeReelWin(results)) {
            if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[TMW-CR-CTA] no_reveal_without_three_match');
            }
            if (state.hasWin && state.currentWinningOffer) {
                showWinResult(state, state.currentWinningOffer);
            } else {
                applyPreWinState(state);
            }
            return;
        }

        var matchingOffer = results[0] || null;

        if (matchingOffer) {
            if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[TMW-BANNER-OFFER] final offer_id=' + (matchingOffer.id || '') + ' repeated=3 cta_url_present=' + (matchingOffer.cta_url ? '1' : '0'));
            }
            if (state.debugEnabled && window.console && typeof window.console.debug === 'function') {
                window.console.debug('[TMW-CR-CTA] win_revealed offer_id=' + (matchingOffer.id || ''));
            }
            state.hasWin = true;
            state.currentWinningOffer = matchingOffer;
            showWinResult(state, matchingOffer);
            return;
        }
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

        applySpinningState(state);

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
        var offerSloganTarget = banner.querySelector('.tmw-cr-slot-banner__offer-slogan');
        var defaultResultLabel = resultLabel ? resultLabel.textContent : '';
        var param = banner.getAttribute('data-subid-param');
        var value = banner.getAttribute('data-subid-value');
        var defaultCtaText = banner.getAttribute('data-default-cta-text') || '';
        var defaultCtaUrl = banner.getAttribute('data-default-cta-url') || '';
        var defaultOfferName = offerNameTarget ? offerNameTarget.textContent : '';
        var defaultSlogan = offerSloganTarget ? offerSloganTarget.textContent : '';
        var debugEnabled = banner.getAttribute('data-debug-enabled') === '1';

        setOfferSloganVisibility(offerSloganTarget, defaultSlogan, '', debugEnabled);

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
            offerSloganTarget: offerSloganTarget,
            defaultSlogan: defaultSlogan,
            param: param,
            value: value,
            isSpinning: false,
            hasWin: false,
            currentWinningOffer: null,
            debugEnabled: debugEnabled
        };

        if (state.spinButton) {
            state.spinButton.addEventListener('click', function(event) {
                event.preventDefault();
                spin(state, state.spinButton);
            });
        }

        setInitialItems(state);

        applyPreWinState(state);

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
