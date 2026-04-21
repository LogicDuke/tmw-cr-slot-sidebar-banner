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
