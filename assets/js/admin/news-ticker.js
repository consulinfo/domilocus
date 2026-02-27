/**
 * News Ticker
 * Duplicates ticker items for seamless loop animation.
 */
(function() {
    var tickerItems = document.querySelector(".domilocus-news-ticker-items");
    if (tickerItems) {
        var items = tickerItems.innerHTML;
        tickerItems.innerHTML = items + items; // Duplicate for seamless loop
    }
})();
