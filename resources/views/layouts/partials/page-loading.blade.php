{{--
    Rotella di caricamento per tutte le pagine (2026-10-10).

    Quando si clicca un link interno o si invia un modulo e la pagina nuova
    tarda piu' di 300 ms, compare in alto un riquadro "Caricamento…".
    Non blocca i clic (pointer-events: none): se la risposta e' un file da
    scaricare e la pagina non cambia, sparisce da solo dopo 20 secondi.
    Per escludere un link o un modulo: attributo data-no-loading.
    Stili in linea: non dipende dalla build di Tailwind.
--}}
<div id="page-loading" role="status" aria-live="polite"
     style="position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:9999;pointer-events:none;
            display:none;align-items:center;gap:10px;padding:10px 16px;border-radius:9999px;
            background:#1e3a8a;color:#fff;font-size:14px;box-shadow:0 4px 14px rgba(0,0,0,.25);">
    <span style="width:18px;height:18px;border:3px solid rgba(255,255,255,.35);border-top-color:#fff;
                 border-radius:50%;display:inline-block;animation:page-loading-spin .8s linear infinite;"></span>
    <span>Caricamento…</span>
</div>
<style>
    @keyframes page-loading-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { #page-loading span:first-child { animation-duration: 2.4s; } }
</style>
<script>
    (function () {
        const box = document.getElementById('page-loading');
        if (!box) {
            return;
        }
        let showTimer = null;
        let hideTimer = null;

        function hide() {
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
            box.style.display = 'none';
        }

        function start() {
            hide();
            showTimer = setTimeout(function () { box.style.display = 'flex'; }, 300);
            hideTimer = setTimeout(hide, 20000);
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0
                || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-loading')
                || (link.target && link.target !== '_self')) {
                return;
            }
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || url.protocol === 'javascript:'
                || /\/download(\/|$)/.test(url.pathname)) {
                return;
            }
            // Solo un'ancora nella stessa pagina: nessun caricamento
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
                return;
            }
            start();
        });

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')
                || (form.target && form.target !== '_self')) {
                return;
            }
            // Un altro script ha fermato l'invio (conferma annullata, AJAX):
            // si controlla dopo che tutti i gestori hanno agito
            setTimeout(function () {
                if (!event.defaultPrevented) {
                    start();
                }
            }, 0);
        });

        // Tornando indietro col browser la pagina puo' riapparire dalla cache
        window.addEventListener('pageshow', hide);
    })();
</script>
