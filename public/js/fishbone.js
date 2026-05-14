(function () {
    function init() {
        var toggle = document.getElementById('historyViewToggle');
        if (!toggle || toggle.dataset.bound === '1') return;
        toggle.dataset.bound = '1';

        var fishbone = document.getElementById('historyFishbone');
        var list = document.getElementById('historyList');

        toggle.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-view]');
            if (!btn) return;
            toggle.querySelectorAll('button').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            var view = btn.dataset.view;
            if (fishbone) fishbone.style.display = view === 'fishbone' ? '' : 'none';
            if (list) list.style.display = view === 'list' ? '' : 'none';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
