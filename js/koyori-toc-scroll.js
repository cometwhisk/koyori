(function () {
    'use strict';

    function forwardBoundaryWheel(event) {
        var toc = event.currentTarget;
        var atTop = toc.scrollTop <= 0;
        var atBottom = toc.scrollTop + toc.clientHeight >= toc.scrollHeight - 1;
        var movingPastTop = event.deltaY < 0 && atTop;
        var movingPastBottom = event.deltaY > 0 && atBottom;

        if (!movingPastTop && !movingPastBottom) {
            return;
        }

        event.preventDefault();
        window.scrollBy(0, event.deltaY);
    }

    function bindTocScrollForwarding() {
        var toc = document.querySelector('#main-container .toc-container .toc');

        if (toc) {
            toc.addEventListener('wheel', forwardBoundaryWheel, { passive: false });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTocScrollForwarding);
    } else {
        bindTocScrollForwarding();
    }
}());
