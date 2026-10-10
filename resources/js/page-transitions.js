// Smooth Page Transitions & Navigation Loader
(function () {
    let progressTimer = null;
    let progressBar = null;
    let mainContent = null;
    const prefetchedUrls = new Set();

    function getProgressBar() {
        if (!progressBar) {
            progressBar = document.getElementById('app-page-progress-bar');
        }
        return progressBar;
    }

    function getProgressContainer() {
        return document.getElementById('app-page-progress');
    }

    function getMainContent() {
        if (!mainContent) {
            mainContent = document.getElementById('mainContent') || document.querySelector('main');
        }
        return mainContent;
    }

    function startProgress() {
        const bar = getProgressBar();
        const container = getProgressContainer();
        if (!bar || !container) return;

        clearInterval(progressTimer);
        container.style.opacity = '1';
        bar.style.transition = 'width 0.3s cubic-bezier(0.1, 0.5, 0.1, 1)';
        bar.style.width = '25%';

        let current = 25;
        progressTimer = setInterval(() => {
            if (current < 85) {
                current += Math.random() * 15;
                if (current > 85) current = 85;
                bar.style.width = current + '%';
            }
        }, 180);
    }

    function completeProgress() {
        const bar = getProgressBar();
        const container = getProgressContainer();
        if (!bar || !container) return;

        clearInterval(progressTimer);
        bar.style.transition = 'width 0.15s ease-out';
        bar.style.width = '100%';

        setTimeout(() => {
            container.style.transition = 'opacity 0.25s ease-out';
            container.style.opacity = '0';
            setTimeout(() => {
                bar.style.width = '0%';
            }, 250);
        }, 150);
    }

    function resetProgress() {
        clearInterval(progressTimer);
        const bar = getProgressBar();
        const container = getProgressContainer();
        if (container) container.style.opacity = '0';
        if (bar) bar.style.width = '0%';
        const main = getMainContent();
        if (main) {
            main.classList.remove('page-smooth-exit');
            main.classList.add('page-smooth-enter');
        }
    }

    // Prefetch destination on hover or touch
    function prefetchUrl(url) {
        if (!url || prefetchedUrls.has(url)) return;
        prefetchedUrls.add(url);

        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);
    }

    function isInternalLink(a) {
        if (!a || a.tagName !== 'A' || !a.href) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download')) return false;
        const href = a.getAttribute('href') || '';
        if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:')) return false;
        if (a.dataset.noTransition !== undefined) return false;

        try {
            const url = new URL(a.href, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            // Ignore if current page with only hash change
            if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
            return true;
        } catch (e) {
            return false;
        }
    }

    // Hover / touch prefetch listener
    document.addEventListener('mouseover', function (e) {
        const a = e.target.closest('a');
        if (isInternalLink(a)) {
            prefetchUrl(a.href);
        }
    }, { passive: true });

    document.addEventListener('touchstart', function (e) {
        const a = e.target.closest('a');
        if (isInternalLink(a)) {
            prefetchUrl(a.href);
        }
    }, { passive: true });

    // Click transition trigger
    document.addEventListener('click', function (e) {
        // Ignore modified clicks (Ctrl, Cmd, Shift, Alt)
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.defaultPrevented) return;

        const a = e.target.closest('a');
        if (!isInternalLink(a)) return;

        // Check if same exact URL
        if (a.href === window.location.href) return;

        // Trigger smooth visual exit & progress bar
        startProgress();
        const main = getMainContent();
        if (main) {
            main.classList.remove('page-smooth-enter');
            main.classList.add('page-smooth-exit');
        }
    });

    // Reset when restored from bfcache (back/forward navigation)
    window.addEventListener('pageshow', function (e) {
        resetProgress();
    });

    // Complete on DOM ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        completeProgress();
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            completeProgress();
        });
    }
})();
