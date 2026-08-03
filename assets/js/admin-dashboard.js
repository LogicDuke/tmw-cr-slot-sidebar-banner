(function () {
    const roots = document.querySelectorAll('.tmw-cr-filters--offers .tmw-cr-filter-panel');
    if (!roots.length) {
        return;
    }

    const closeAll = function (exceptRoot) {
        roots.forEach(function (root) {
            if (root === exceptRoot) {
                return;
            }
            const toggle = root.querySelector('.tmw-cr-filter-panel__toggle');
            const card = root.querySelector('.tmw-cr-filter-panel__card');
            if (toggle && card) {
                toggle.setAttribute('aria-expanded', 'false');
                card.hidden = true;
            }
        });
    };

    const updateCount = function (root) {
        const checks = root.querySelectorAll('input[type="checkbox"]');
        let count = 0;
        checks.forEach(function (check) {
            if (check.checked) {
                count += 1;
            }
        });
        const countNode = root.querySelector('.tmw-cr-filter-panel__count');
        if (countNode) {
            countNode.textContent = count > 0 ? String(count) : '';
            countNode.classList.toggle('is-empty', count === 0);
            countNode.hidden = count === 0;
        }
    };

    roots.forEach(function (root) {
        const toggle = root.querySelector('.tmw-cr-filter-panel__toggle');
        const card = root.querySelector('.tmw-cr-filter-panel__card');
        const clearBtn = root.querySelector('.tmw-cr-filter-panel__clear');
        const search = root.querySelector('.tmw-cr-filter-panel__search');

        if (!toggle || !card) {
            return;
        }

        updateCount(root);

        toggle.addEventListener('click', function () {
            const shouldOpen = card.hidden;
            closeAll(root);
            card.hidden = !shouldOpen;
            toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });

        root.querySelectorAll('input[type="checkbox"]').forEach(function (check) {
            check.addEventListener('change', function () {
                updateCount(root);
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                root.querySelectorAll('input[type="checkbox"]').forEach(function (check) {
                    check.checked = false;
                });
                updateCount(root);
            });
        }

        if (search) {
            search.addEventListener('input', function () {
                const needle = search.value.trim().toLowerCase();
                root.querySelectorAll('.tmw-cr-filter-panel__list label').forEach(function (label) {
                    const text = label.getAttribute('data-filter-label') || '';
                    label.hidden = needle !== '' && text.indexOf(needle) === -1;
                });
            });
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('.tmw-cr-filter-panel')) {
            return;
        }
        closeAll(null);
    });
})();

/**
 * [TMW-FEATURED-ORDER] Featured Offer Order panel behaviour.
 *
 * Admin-only UI: search/add/remove/drag-reorder a compact ordered list of
 * offer IDs. Order is submitted as the DOM order of hidden
 * `featured_offer_ids[]` inputs on normal form submit — no AJAX, no new
 * remote calls, no eligibility or offer-classification logic duplicated
 * here (all of that is rendered server-side).
 */
(function () {
    const root = document.querySelector('[data-tmw-featured-order="1"]');
    if (!root) {
        return;
    }

    const list = root.querySelector('#tmw-cr-featured-list');
    const searchInput = root.querySelector('#tmw-cr-featured-search-input');
    const searchResults = root.querySelector('#tmw-cr-featured-search-results');
    const rowTemplate = root.querySelector('#tmw-cr-featured-row-template');
    const catalogDataEl = root.querySelector('#tmw-cr-featured-catalog-data');

    if (!list || !searchInput || !searchResults || !rowTemplate || !catalogDataEl) {
        return;
    }

    let catalog = [];
    try {
        catalog = JSON.parse(catalogDataEl.textContent || '[]');
    } catch (err) {
        catalog = [];
    }

    const emptyText = list.getAttribute('data-empty-text') || '';
    const duplicateText = list.getAttribute('data-duplicate-text') || '';
    const eligibilityUnknownText = list.getAttribute('data-eligibility-unknown-text') || '';

    const currentIds = function () {
        const ids = [];
        list.querySelectorAll('.tmw-cr-featured-row').forEach(function (row) {
            const id = row.getAttribute('data-offer-id');
            if (id) {
                ids.push(id);
            }
        });
        return ids;
    };

    const renderPositions = function () {
        const rows = list.querySelectorAll('.tmw-cr-featured-row');
        rows.forEach(function (row, index) {
            const position = row.querySelector('.tmw-cr-featured-row__position');
            if (position) {
                position.textContent = String(index + 1) + '.';
            }
        });
    };

    const ensureEmptyPlaceholder = function () {
        const hasRows = list.querySelector('.tmw-cr-featured-row') !== null;
        let placeholder = list.querySelector('.tmw-cr-featured-list__empty');

        if (hasRows) {
            if (placeholder) {
                placeholder.remove();
            }
            return;
        }

        if (!placeholder) {
            placeholder = document.createElement('li');
            placeholder.className = 'tmw-cr-featured-list__empty';
            placeholder.setAttribute('data-empty', '1');
            placeholder.textContent = emptyText;
            list.appendChild(placeholder);
        }
    };

    const flashDuplicate = function (existingRow) {
        if (!existingRow) {
            return;
        }
        existingRow.classList.add('is-duplicate-flash');
        window.setTimeout(function () {
            existingRow.classList.remove('is-duplicate-flash');
        }, 1200);

        if (duplicateText) {
            const notice = document.createElement('li');
            notice.className = 'tmw-cr-featured-search-results__notice';
            notice.textContent = duplicateText;
            searchResults.innerHTML = '';
            searchResults.appendChild(notice);
            searchResults.hidden = false;
            window.setTimeout(function () {
                searchResults.hidden = true;
            }, 1500);
        }
    };

    const addOffer = function (entry) {
        const existing = list.querySelector('.tmw-cr-featured-row[data-offer-id="' + entry.id + '"]');
        if (existing) {
            flashDuplicate(existing);
            return;
        }

        const placeholder = list.querySelector('.tmw-cr-featured-list__empty');
        if (placeholder) {
            placeholder.remove();
        }

        const fragment = rowTemplate.content ? rowTemplate.content.cloneNode(true) : null;
        let row;
        if (fragment) {
            row = fragment.querySelector('.tmw-cr-featured-row');
        } else {
            // Fallback for environments without <template> content support.
            const wrapper = document.createElement('div');
            wrapper.innerHTML = rowTemplate.innerHTML;
            row = wrapper.querySelector('.tmw-cr-featured-row');
        }

        if (!row) {
            return;
        }

        row.setAttribute('data-offer-id', entry.id);
        row.setAttribute('draggable', 'true');

        const nameEl = row.querySelector('.tmw-cr-featured-row__name');
        if (nameEl) {
            nameEl.textContent = entry.name || entry.id;
        }

        const metaEl = row.querySelector('.tmw-cr-featured-row__meta');
        if (metaEl) {
            metaEl.textContent = 'ID ' + entry.id + ' \u00b7 ' + (entry.type || '') + ' \u00b7 ' + (entry.status || '');
        }

        const badge = row.querySelector('.tmw-cr-badge');
        if (badge && eligibilityUnknownText) {
            badge.textContent = eligibilityUnknownText;
        }

        const hiddenInput = row.querySelector('input[type="hidden"]');
        if (hiddenInput) {
            hiddenInput.value = entry.id;
        }

        list.appendChild(row);
        renderPositions();
    };

    // --- Search -------------------------------------------------------
    const runSearch = function () {
        const needle = searchInput.value.trim().toLowerCase();
        searchResults.innerHTML = '';

        if ('' === needle) {
            searchResults.hidden = true;
            return;
        }

        const matches = catalog.filter(function (entry) {
            const haystack = (entry.id + ' ' + entry.name).toLowerCase();
            return haystack.indexOf(needle) !== -1;
        }).slice(0, 20);

        if (!matches.length) {
            searchResults.hidden = true;
            return;
        }

        matches.forEach(function (entry) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tmw-cr-featured-search-results__item';
            button.setAttribute('data-offer-id', entry.id);
            button.textContent = (entry.name || entry.id) + ' \u2014 ID ' + entry.id + ' \u00b7 ' + (entry.type || '');
            item.appendChild(button);
            searchResults.appendChild(item);
        });

        searchResults.hidden = false;
    };

    searchInput.addEventListener('input', runSearch);

    searchResults.addEventListener('click', function (event) {
        const button = event.target.closest('.tmw-cr-featured-search-results__item');
        if (!button) {
            return;
        }
        const id = button.getAttribute('data-offer-id');
        const entry = catalog.filter(function (candidate) {
            return candidate.id === id;
        })[0];
        if (entry) {
            addOffer(entry);
        }
        searchInput.value = '';
        searchResults.innerHTML = '';
        searchResults.hidden = true;
        searchInput.focus();
    });

    document.addEventListener('click', function (event) {
        if (root.contains(event.target)) {
            return;
        }
        searchResults.hidden = true;
    });

    // --- Remove ---------------------------------------------------------
    list.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.tmw-cr-featured-row__remove');
        if (!removeButton) {
            return;
        }
        const row = removeButton.closest('.tmw-cr-featured-row');
        if (row) {
            row.remove();
            renderPositions();
            ensureEmptyPlaceholder();
        }
    });

    // --- Drag & drop reorder ---------------------------------------------
    let dragEl = null;

    list.addEventListener('dragstart', function (event) {
        const row = event.target.closest('.tmw-cr-featured-row');
        if (!row) {
            return;
        }
        dragEl = row;
        row.classList.add('is-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            try {
                event.dataTransfer.setData('text/plain', row.getAttribute('data-offer-id') || '');
            } catch (err) {
                // Some browsers restrict setData outside secure contexts; safe to ignore.
            }
        }
    });

    list.addEventListener('dragover', function (event) {
        if (!dragEl) {
            return;
        }
        event.preventDefault();

        const target = event.target.closest('.tmw-cr-featured-row');
        if (!target || target === dragEl) {
            return;
        }

        const rect = target.getBoundingClientRect();
        const shouldInsertAfter = (event.clientY - rect.top) / rect.height > 0.5;
        list.insertBefore(dragEl, shouldInsertAfter ? target.nextSibling : target);
    });

    list.addEventListener('drop', function (event) {
        event.preventDefault();
    });

    list.addEventListener('dragend', function () {
        if (dragEl) {
            dragEl.classList.remove('is-dragging');
        }
        dragEl = null;
        renderPositions();
    });

    renderPositions();

    // Guard against duplicate submission of an ID that also lost its row via
    // browser back/forward cache restoring stale DOM state.
    const form = root.querySelector('#tmw-cr-featured-order-form');
    if (form) {
        form.addEventListener('submit', function () {
            const seen = {};
            list.querySelectorAll('.tmw-cr-featured-row').forEach(function (row) {
                const id = row.getAttribute('data-offer-id');
                if (id && seen[id]) {
                    row.remove();
                } else if (id) {
                    seen[id] = true;
                }
            });
        });
    }
})();
