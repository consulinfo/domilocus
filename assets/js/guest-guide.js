(function () {
    'use strict';
    function init() {
        document.querySelectorAll('.domilocus-guest-guide').forEach(function (guide) {
            if (guide.dataset.ready) return;
            guide.dataset.ready = 'true';
            // iPadOS may identify itself as a Mac. All other devices keep the
            // universal Google Maps URL, which also works without JavaScript.
            var appleDevice = /iPhone|iPad|iPod|Macintosh|Mac OS X/i.test(navigator.userAgent || '') && !/Android/i.test(navigator.userAgent || '');
            guide.querySelectorAll('.dgg-directions').forEach(function (link) {
                if (appleDevice && link.dataset.appleUrl) link.href = link.dataset.appleUrl;
            });
            var cards = Array.from(guide.querySelectorAll('.dgg-card'));
            var search = guide.querySelector('.dgg-search');
            var initialOpen = cards.map(function (card) { return card.open; });
            var printing = false;
            cards.forEach(function (card) {
                card.addEventListener('toggle', function () {
                    if (!card.open || printing) return;
                    cards.forEach(function (other) {
                        if (other !== card) other.open = false;
                    });
                });
            });
            guide.querySelectorAll('.dgg-content table').forEach(function (table) {
                var wrapper = document.createElement('div');
                wrapper.className = 'dgg-table-scroll';
                wrapper.tabIndex = 0;
                wrapper.setAttribute('role', 'region');
                wrapper.setAttribute('aria-label', guide.dataset.tableLabel || 'Table');
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });
            function normalize(value) {
                return value.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[-‑–]/g, '');
            }
            // Search the information itself, excluding transient clipboard messages.
            var contents = cards.map(function (card) {
                return normalize(Array.from(card.querySelectorAll('summary, .dgg-content')).map(function (node) {
                    return node.textContent;
                }).join(' '));
            });
            search.hidden = cards.length === 0;
            search.querySelector('input').addEventListener('input', function (event) {
                var query = normalize(event.target.value.trim());
                guide.querySelectorAll('.dgg-events-more').forEach(function (more) { more.open = !!query; });
                var count = 0;
                cards.forEach(function (card, index) {
                    var match = !query || contents[index].includes(query);
                    card.hidden = !match;
                    card.open = query ? match && count === 0 : initialOpen[index];
                    if (match) count++;
                });
                guide.querySelector('.dgg-empty').hidden = count !== 0;
            });
            guide.querySelectorAll('.dgg-copy').forEach(function (button) {
                button.hidden = false;
                button.addEventListener('click', async function () {
                    var status = button.parentElement.querySelector('.dgg-copy-status');
                    try {
                        await navigator.clipboard.writeText(button.parentElement.querySelector('.dgg-password').textContent);
                        status.textContent = button.dataset.success;
                    } catch (error) {
                        status.textContent = button.dataset.error;
                    }
                });
            });
            var printState;
            window.addEventListener('beforeprint', function () {
                printing = true;
                printState = cards.map(function (card) { return { open: card.open, hidden: card.hidden }; });
                cards.forEach(function (card) { card.open = true; card.hidden = false; });
            });
            window.addEventListener('afterprint', function () {
                if (!printState) return;
                cards.forEach(function (card, index) { card.open = printState[index].open; card.hidden = printState[index].hidden; });
                printing = false;
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
