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

/* Compact moon switch used throughout ASU-REMS (Login, About, Dashboard, modules). */
.rems-theme-toggle {
    appearance:none;position:relative;display:inline-block;vertical-align:middle;
    flex:0 0 55px;width:55px;min-width:55px;height:29px;min-height:29px;
    padding:0;border:1px solid #dfe1e7;border-radius:999px;
    background:#e8e8ee;color:#16191e;cursor:pointer;
    line-height:1;box-shadow:none;
}
.rems-theme-toggle::before {
    content:"";position:absolute;top:3px;left:3px;width:21px;height:21px;
    border-radius:50%;background:#fff;
}
.rems-theme-toggle::after {
    content:"";position:absolute;right:4px;top:4px;width:19px;height:19px;
    border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(25,30,42,.13);
}
.rems-theme-toggle i {
    position:absolute;z-index:1;top:50%;left:6px;width:16px;
    transform:translateY(-50%);color:#141820;font-size:14px;
    text-align:center;line-height:1;pointer-events:none;
}
.rems-theme-toggle:hover {background:#dfe0e7;border-color:#c9ced5;}
.rems-theme-toggle:focus-visible {outline:3px solid #edc447;outline-offset:3px;}
.rems-theme-toggle--floating {
    position:fixed;top:18px;right:20px;z-index:1400;
    box-shadow:0 5px 18px rgba(0,0,0,.12);
}
html[data-rems-theme="dark"] .rems-theme-toggle {
    background:#34453b;color:#f9d568;border-color:#5b7164;
}
html[data-rems-theme="dark"] .rems-theme-toggle::before {
    left:auto;right:3px;background:#21392a;
}
html[data-rems-theme="dark"] .rems-theme-toggle::after {
    right:auto;left:4px;background:#f9faf7;
}
html[data-rems-theme="dark"] .rems-theme-toggle i {
    left:auto;right:6px;color:#ffda69;
}
html[data-rems-theme="dark"] .rems-theme-toggle:hover {background:#40594a;}
@media(max-width:650px){
    .rems-theme-toggle {flex-basis:47px;width:47px;min-width:47px;height:26px;min-height:26px;}
    .rems-theme-toggle::before {width:20px;height:20px;top:2px;left:2px;}
    .rems-theme-toggle::after {width:18px;height:18px;top:3px;right:3px;}
    .rems-theme-toggle i {left:5px;font-size:12px;}
    html[data-rems-theme="dark"] .rems-theme-toggle::before {left:auto;right:2px;}
    html[data-rems-theme="dark"] .rems-theme-toggle::after {right:auto;left:3px;}
    html[data-rems-theme="dark"] .rems-theme-toggle i {left:auto;right:5px;}
}

/* Anchor the moon/sun icon and switch circles to the toggle itself.
   Setting this button to position:static would incorrectly position its
   absolute icon at the far LEFT of the sticky header. */
.asu-topbar .topbar-user > .rems-theme-toggle {
    position:relative!important;
    margin-right:8px;
    flex-shrink:0!important;
    vertical-align:middle;
}
@media(max-width:650px) {
    .asu-topbar .topbar-user > .rems-theme-toggle {margin-right:5px;}
}

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

/* Shared authenticated-page palette: works with Bootstrap and plain PHP layouts.
   Preserve deliberate green/gold status badges and institutional brand panels. */
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.main-page,.page-content,.content-wrapper,.main-content,.content-area,
    .dashboard-content,.app-content,.wrapper-content,.main-panel,.page-wrapper,
    .main-wrapper,.content-section,.content-body) {
    background:#0b1d15!important;color:#e9f2e9;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.card,.card-body,.panel,.panel-body,.box,.widget,.widget-body,.content-card,
    .summary-card,.metric-card,.info-card,.data-card,.form-card,.form-panel,
    .table-container,.table-wrapper,.table-responsive,.modal-content,
    .dropdown-menu,.offcanvas,.list-group-item,.accordion-item,
    .bg-white,.bg-light,.bg-body,.bg-body-tertiary) {
    background-color:#142e21!important;color:#e9f2e9!important;
    border-color:#365544!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.card-header,.card-footer,.panel-heading,.panel-header,.modal-header,
    .modal-footer,.dropdown-header,.accordion-button,.table-light,.table-secondary) {
    background-color:#203d2d!important;color:#f1f7f0!important;border-color:#365544!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(h1,h2,h3,h4,h5,h6) {color:#edf5ee;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.text-muted,.text-secondary,.text-dark,.text-black,.text-body,.small-muted,
    .muted,.form-text,.help-text) {color:#bed0c0!important;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(input:not([type="checkbox"]):not([type="radio"]),select,textarea,
    .form-control,.form-select,.input-group-text) {
    background:#1c382b!important;color:#eff7ef!important;
    border-color:#4c6b54!important;color-scheme:dark;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(input,textarea)::placeholder {color:#b8cabb!important;opacity:1;}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.table,.table-striped,.table-hover) {
    --bs-table-bg:#142e21;--bs-table-color:#e9f2e9;
    --bs-table-striped-bg:#193b2a;--bs-table-hover-bg:#234b34;
    --bs-table-border-color:#365544;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.table,.table-striped,.table-hover) > :not(caption) > * > * {
    color:#e9f2e9;border-color:#365544;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.sidebar,.sidebar-wrapper,.left-sidebar,#sidebar) {
    background-color:#064525;color:#e9f2e9;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.sidebar,.sidebar-wrapper,.left-sidebar,#sidebar) a:not(.active):not(.is-active) {
    color:#e4f2e6;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.btn-light,.btn-outline-secondary,.btn-outline-dark) {
    background:#21412e!important;color:#f1f8f1!important;border-color:#58735c!important;
}
html[data-rems-theme="dark"] body:not(:has(.portal-navbar)):not(:has(.rems-about))
:is(.btn-light,.btn-outline-secondary,.btn-outline-dark):hover {
    background:#2b523a!important;
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
            if(icon){icon.className=dark?'bi bi-sun-fill':'bi bi-moon-fill';}
            else {var fallback=document.createElement('i');fallback.setAttribute('aria-hidden','true');fallback.className=dark?'bi bi-sun-fill':'bi bi-moon-fill';button.textContent='';button.appendChild(fallback);}
        });
    }
    function createControl(){
        if(document.querySelector('[data-rems-theme-toggle]')) return;
        var button=document.createElement('button');
        button.type='button';
        button.className='rems-theme-toggle';
        button.setAttribute('data-rems-theme-toggle','');
        button.setAttribute('aria-label','Switch to dark mode');
        button.setAttribute('title','Switch to dark mode');
        button.setAttribute('aria-pressed','false');
        var icon=document.createElement('i');
        icon.className='bi bi-moon-fill';
        icon.setAttribute('aria-hidden','true');
        button.appendChild(icon);
        // Use a normal header area where one exists; never add a second switch.
        var host=document.querySelector(
            '.asu-dashboard-actions,' +
            '.asu-topbar .topbar-user,' +
            '.asu-topbar .topbar-actions,' +
            '.asu-topbar .topbar-right,' +
            '.topbar-actions,.header-actions,' +
            '.navbar-nav.ms-auto,.navbar-nav.ml-auto,.header-right,.topbar-right'
        );
        if(host){
            // For subpages: insert inside the real topbar's user group,
            // BEFORE the name and avatar. Floating would overlap the avatar.
            var profile=host.querySelector(
                '.dashboard-user-mini,.topbar-user-text,.user-menu,' +
                '.user-profile,.profile-dropdown,.user-avatar,.rems-profile-avatar-link'
            );
            if(profile) host.insertBefore(button,profile);
            else host.insertBefore(button,host.firstChild);
        } else {
            // Last-resort fallback for pages with no recognized header.
            button.classList.add('rems-theme-toggle--floating');
            document.body.appendChild(button);
        }
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
