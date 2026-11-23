<?php
/**
 * ASU-REMS shared Light / Dark appearance.
 * Include ONCE in the shared includes/header.php, e.g.:
 *     require_once __DIR__ . '/rems_theme.php';
 *
 * The public login.php and about.php also include this directly, so they
 * already work even before other pages' shared header is updated.
 *
 * No database queries, session writes, authentication or workflow changes.
 */
?>
<script>
/* Apply the stored theme as early as possible; keep legacy login preference. */
(function () {
    var mode = 'light';
    try {
        mode = window.localStorage.getItem('asu_rems_theme') ||
               window.localStorage.getItem('asu_rems_public_theme') || 'light';
    } catch (error) { /* Storage may be disabled. */ }
    document.documentElement.setAttribute('data-rems-theme', mode === 'dark' ? 'dark' : 'light');
})();
</script>
<style id="asu-rems-global-theme-css">
:root {
    --rems-dark-bg:#0b1d15;
    --rems-dark-surface:#132b20;
    --rems-dark-raised:#1a3628;
    --rems-dark-border:#355843;
    --rems-dark-text:#e9f2e9;
    --rems-dark-muted:#b8ccba;
    --rems-dark-gold:#f7d568;
}
/* These variables are for internal screens which use Bootstrap or local CSS variables. */
html[data-rems-theme="dark"] {
    color-scheme:dark;
    --bs-body-bg:#0b1d15;
    --bs-body-color:#e9f2e9;
    --bs-emphasis-color:#f1f8f0;
    --bs-secondary-color:#b8ccba;
    --bs-secondary-bg:#163125;
    --bs-tertiary-bg:#203b2d;
    --bs-border-color:#355843;
    --bs-card-bg:#132b20;
    --bs-card-color:#e9f2e9;
    --bs-table-bg:#132b20;
    --bs-table-color:#e9f2e9;
    --bs-table-border-color:#355843;
    --bs-modal-bg:#132b20;
    --bs-dropdown-bg:#1a3628;
    --bs-dropdown-color:#e9f2e9;
    --background:#0b1d15;
    --surface:#132b20;
    --card-bg:#132b20;
    --border:#355843;
    --text:#e9f2e9;
    --text-muted:#b8ccba;
}

/* Reusable button. Other ASU-REMS pages receive a button automatically
   after including this file from the shared site header. */
.rems-theme-toggle {
    display:inline-flex;align-items:center;justify-content:center;
    flex:none;min-width:42px;height:42px;padding:0 9px;
    border:1px solid #bfd4c5;border-radius:12px;
    background:#fffcf4;color:#076338;
    font-family:"Century Gothic",Arial,sans-serif;font-size:18px;
    line-height:1;cursor:pointer;vertical-align:middle;
}
.rems-theme-toggle:hover {background:#ffefc6;}
.rems-theme-toggle:focus-visible {outline:3px solid #f4ca24;outline-offset:2px;}
.rems-theme-toggle i {pointer-events:none;}
.rems-theme-toggle--floating {
    position:fixed;top:14px;right:18px;z-index:1400;
    box-shadow:0 4px 18px rgba(0,0,0,.15);
}
html[data-rems-theme="dark"] .rems-theme-toggle {
    background:#1b372a;color:#ffe096;border-color:#506d58;
}
html[data-rems-theme="dark"] .rems-theme-toggle:hover {background:#2d513b;}

/* About ASU-REMS page: dark colors on every section, not only the header. */
html[data-rems-theme="dark"] .rems-about {
    --about-forest:#e6f4e8;
    --about-deep:#ecf3e9;
    --about-green:#92dbad;
    --about-mid:#f0ca64;
    --about-ink:#eaf4e9;
    --about-muted:#c0d3c3;
    --about-border:#355744;
    --about-white:#183528;
    --about-surface:#122a1f;
    background:#0c2017;
}
html[data-rems-theme="dark"] body:has(.rems-about){background:#0c2017;color:#eaf4e9;}
html[data-rems-theme="dark"] .rems-about-header {background:#041f13;border-bottom-color:#315440;}
html[data-rems-theme="dark"] .rems-about-section--white{background:#10251b;}
html[data-rems-theme="dark"] .rems-about-section--soft{background:linear-gradient(120deg,#0c2118,#182f23);}
html[data-rems-theme="dark"] .rems-about-goal-point,
html[data-rems-theme="dark"] .rems-about-feature,
html[data-rems-theme="dark"] .rems-about-step{
    background:#1a3427;border-color:#3b5a47;
    box-shadow:none;
}
html[data-rems-theme="dark"] .rems-about-section h2,
html[data-rems-theme="dark"] .rems-about-section h3,
html[data-rems-theme="dark"] .rems-about-goal-point strong {color:#f0f7e9;}
html[data-rems-theme="dark"] .rems-about-section p,
html[data-rems-theme="dark"] .rems-about-goal-point span{color:#c8d9c9;}
html[data-rems-theme="dark"] .rems-about-goal-point i,
html[data-rems-theme="dark"] .rems-about-feature-icon {background:#254b35;color:#f3d976;}
html[data-rems-theme="dark"] .rems-about-purpose-section{background:linear-gradient(110deg,#051a11,#0a4228);}
html[data-rems-theme="dark"] .rems-about-purpose-section h2 {color:#fff;}
html[data-rems-theme="dark"] .rems-about-purpose-section p {color:#eaf3ec;}
html[data-rems-theme="dark"] .rems-about-bottom {background:#03170d;color:#dbe8dc;}

/* Dashboard and module pages: applied through includes/header.php.  Target
   neutral surfaces only, leaving institutional branding and status colors. */
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) {
    background:#0b1d15!important;color:var(--rems-dark-text);
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .content-wrapper,.main-content,.page-content,.content-area,.dashboard-content,.app-content,
    .wrapper-content,.main-panel,.page-wrapper,.main-wrapper
) {background-color:#0b1d15!important;color:#e9f2e9;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .card,.card-body,.panel,.panel-body,.box,.widget,.widget-body,.modal-content,
    .dropdown-menu,.offcanvas,.list-group-item,.accordion-item,.table-responsive,
    .bg-white,.bg-light,.bg-body,.bg-body-tertiary
) {background-color:#132b20!important;color:#e9f2e9;border-color:#355843!important;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .card-header,.card-footer,.modal-header,.modal-footer,.accordion-button,.table-light,
    .table-secondary,.list-group
) {background-color:#1a3628!important;color:#e9f2e9;border-color:#355843!important;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    table,.table,thead,tbody,tr,td,th
) {color:#e9f2e9;border-color:#355843;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .table,.table-striped,.table-hover
) {--bs-table-bg:#132b20;--bs-table-color:#e9f2e9;--bs-table-striped-bg:#19392a;--bs-table-hover-bg:#234632;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    input:not([type="checkbox"]):not([type="radio"]),select,textarea,.form-control,.form-select,.input-group-text
) {background-color:#1c382b!important;color:#eff7ef!important;border-color:#4c6b54!important;color-scheme:dark;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(input,textarea)::placeholder {
    color:#afc5b4!important;opacity:1;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .text-dark,.text-black,.text-body,.text-muted,.text-secondary
) {color:#c7dacb!important;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .sidebar,.sidebar-wrapper,.left-sidebar
) {background-color:#072c1b;color:#e9f2e9;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) :is(
    .sidebar a,.left-sidebar a
) {color:#d9eddd;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about)) a:not(.btn) {text-underline-offset:3px;}
@media (prefers-reduced-motion: reduce) {
    .rems-theme-toggle {transition:none!important;}
}
</style>
<script>
(function () {
    var KEY='asu_rems_theme';
    var LEGACY='asu_rems_public_theme';
    function current(){return document.documentElement.getAttribute('data-rems-theme')==='dark'?'dark':'light';}
    function setMode(mode,persist){
        var normalized=mode==='dark'?'dark':'light';
        document.documentElement.setAttribute('data-rems-theme',normalized);
        if (persist){
            try {
                window.localStorage.setItem(KEY,normalized);
                window.localStorage.setItem(LEGACY,normalized);
            }catch(err){}
        }
        syncControls();
    }
    function syncControls(){
        var dark=current()==='dark';
        document.querySelectorAll('[data-rems-theme-toggle]').forEach(function(button){
            button.setAttribute('aria-pressed',String(dark));
            button.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');
            button.setAttribute('title',dark?'Switch to light mode':'Switch to dark mode');
            var icon=button.querySelector('i');
            if(icon){icon.className=dark?'bi bi-sun':'bi bi-moon-stars';}
            else {var symbol=button.querySelector('.rems-theme-symbol'); if(symbol) symbol.textContent=dark?'☀':'☾'; else button.textContent=dark?'☀':'☾';}
        });
    }
    function createControl(){
        if(document.querySelector('[data-rems-theme-toggle]'))return;
        var button=document.createElement('button');
        button.type='button';
        button.className='rems-theme-toggle';
        button.setAttribute('data-rems-theme-toggle','');
        button.setAttribute('aria-label','Switch to dark mode');
        button.setAttribute('title','Switch to dark mode');
        var symbol=document.createElement('span');
        symbol.className='rems-theme-symbol';
        symbol.setAttribute('aria-hidden','true');
        symbol.textContent='☾';
        button.appendChild(symbol);
        // Prefer a header toolbar, otherwise keep the action accessible at top-right.
        var host=document.querySelector('.topbar-actions,.header-actions,.navbar-nav.ms-auto,.navbar-nav.ml-auto,.header-right,.topbar-right');
        if (host){ host.appendChild(button); }
        else {button.classList.add('rems-theme-toggle--floating');document.body.appendChild(button);}
    }
    function init(){
        createControl();
        syncControls();
        document.addEventListener('click',function(event){
            var toggle=event.target.closest('[data-rems-theme-toggle]');
            if(!toggle)return;
            event.preventDefault();
            setMode(current()==='dark'?'light':'dark',true);
        });
        window.addEventListener('storage',function(event){
            if(event.key===KEY || event.key===LEGACY){setMode(event.newValue==='dark'?'dark':'light',false);}
        });
    }
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});
    else init();
})();
</script>
