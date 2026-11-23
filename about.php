<?php
/**
 * ASU-REMS public About page.
 * No login or database connection is required to view this page.
 */
require_once __DIR__ . '/config/app.php';
$pageTitle = 'About ASU-REMS';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/rems_theme.php';
?>
<style>
:root {
    --about-forest: #042f1b;
    --about-deep: #032816;
    --about-green: #075c33;
    --about-mid: #0a7540;
    --about-gold: #f4ca24;
    --about-gold-soft: #ffe49b;
    --about-ink: #173629;
    --about-muted: #52665b;
    --about-border: #dde8df;
    --about-white: #ffffff;
    --about-surface: #f5f8f5;
}
html, body { margin:0; min-height:100%; overflow-x:hidden; }
body { background:var(--about-surface); font-family:"Century Gothic",Arial,sans-serif; color:var(--about-ink); }
.rems-about, .rems-about * { box-sizing:border-box; }
.rems-about { width:100%; padding-top:82px; }
.rems-about a { text-decoration:none; }
.rems-about a:focus-visible { outline:3px solid var(--about-gold); outline-offset:3px; }

/* Same public navigation used by the ASU-REMS landing page. */
.rems-about-header {
    min-height:82px; display:flex; align-items:center; justify-content:space-between;
    gap:22px; padding:11px clamp(22px,3vw,58px);
    background:#064529; border-bottom:1px solid rgba(255,255,255,.12);
    position:fixed; top:0; left:0; right:0; width:100%; z-index:1100;
}
.rems-about-brand { display:flex; align-items:center; gap:12px; color:#fff; min-width:0; }
.rems-about-brand-icon {
    width:52px; height:52px; flex:0 0 52px; border-radius:15px;
    display:grid; place-items:center; background:var(--about-gold);
    color:#064529; font-size:24px;
}
.rems-about-brand-copy strong { display:block; color:#fff; font-size:18px; font-weight:900; line-height:1.15; }
.rems-about-brand-copy small { display:block; margin-top:5px; color:#d8e9db; font-size:11px; }
.rems-about-nav { display:flex; align-items:center; gap:10px; }
.rems-about-nav a {
    display:inline-flex; align-items:center; justify-content:center;
    min-height:43px; padding:12px 19px; border:1px solid transparent;
    border-radius:12px; color:#fff; font-size:13px; font-weight:800; white-space:nowrap;
}
.rems-about-nav a:hover { background:rgba(255,255,255,.12); color:#fff; }
.rems-about-nav .rems-nav-about { background:#fff; color:#07512c; }
.rems-about-nav .rems-nav-about:hover { background:#ffecb6; color:#064529; }

/* About hero: restrained campus-green treatment, with the original icon. */
.rems-about-hero {
    position:relative; isolation:isolate; overflow:hidden;
    /* Fill the visible screen beneath the fixed 82px navigation bar. */
    min-height:510px;
    min-height:max(510px, calc(100vh - 82px));
    min-height:max(510px, calc(100dvh - 82px));
    display:flex; align-items:center; justify-content:center;
    text-align:center; padding:74px 24px 86px;
    background:radial-gradient(circle at 80% 20%,rgba(244,202,36,.11),transparent 38%),
               linear-gradient(135deg,#032a17 0%,#07522e 57%,#0f693b 100%);
    color:#fff;
}
.rems-about-hero:before {
    content:""; position:absolute; z-index:-1; width:410px; height:410px;
    top:-225px; right:-125px; border-radius:50%; background:rgba(255,255,255,.05);
}
.rems-about-hero:after {
    content:""; position:absolute; z-index:-1; width:470px; height:470px;
    bottom:-360px; left:-165px; border:95px solid rgba(244,202,36,.07); border-radius:50%;
}
.rems-about-hero-inner { max-width:1010px; width:100%; margin:0 auto; }
.rems-about-emblem {
    display:grid; place-items:center; width:132px; height:132px;
    margin:0 auto 16px; border:7px solid rgba(255,255,255,.2);
    outline:2px solid rgba(255,226,155,.35); outline-offset:5px;
    border-radius:50%; background:#fff; color:var(--about-green);
    box-shadow:0 20px 45px rgba(0,0,0,.13);
}
.rems-about-emblem i { font-size:63px; line-height:1; }
.rems-about-hero h1 {
    margin:10px 0 17px; color:#fff; font-size:clamp(42px,5.4vw,72px);
    font-weight:900; letter-spacing:-.045em; line-height:1.15;
}
.rems-about-hero h1 span { color:var(--about-gold-soft); }
.rems-about-lead {
    margin:0 auto; max-width:820px; color:#e7f1e9;
    font-size:clamp(15px,1.4vw,19px); line-height:1.85;
}

/* A consistent, readable layout for all five content sections. */
.rems-about-section { padding:86px clamp(22px,5vw,76px); scroll-margin-top:100px; }
.rems-about-container { max-width:1140px; margin:0 auto; }
.rems-about-section--white { background:#fff; }
.rems-about-section--soft { background:var(--about-surface); }
.rems-about-kicker {
    display:block; margin:0 0 12px; color:var(--about-mid);
    font-size:12px; font-weight:900; letter-spacing:.15em; text-transform:uppercase;
}
.rems-about-section h2 {
    margin:0 0 22px; color:var(--about-forest); font-size:clamp(28px,3vw,39px);
    font-weight:900; letter-spacing:-.035em; line-height:1.25;
}
.rems-about-section p {
    margin:0 0 18px; color:var(--about-muted); font-size:15px; line-height:1.95;
}
.rems-about-section p:last-child { margin-bottom:0; }
.rems-about-rule { width:58px; height:4px; border-radius:999px; background:var(--about-gold); margin:0 0 27px; }
.rems-about-intro { max-width:745px; margin:0 auto 38px; text-align:center; }
.rems-about-intro .rems-about-rule { margin-left:auto; margin-right:auto; }
.rems-about-intro p { margin-left:auto; margin-right:auto; }

/* What is ASU-REMS */
.rems-about-definition { display:grid; grid-template-columns:.72fr 1.28fr; gap:clamp(28px,5vw,72px); align-items:start; }
.rems-about-definition .rems-about-definition-title { max-width:350px; }
.rems-about-definition-copy { padding-top:4px; }
.rems-about-definition-copy p { font-size:16px; line-height:2; text-align:justify; }

/* Our Goal */
.rems-about-goal { display:grid; grid-template-columns:1fr 1fr; gap:45px; align-items:center; }
.rems-about-goal-intro p { max-width:490px; }
.rems-about-goal-points { display:grid; gap:12px; }
.rems-about-goal-point {
    display:flex; align-items:flex-start; gap:16px; padding:20px 22px;
    background:#fff; border:1px solid var(--about-border); border-radius:14px;
}
.rems-about-goal-point i {
    display:grid; place-items:center; flex:0 0 36px; width:36px; height:36px;
    border-radius:11px; background:#eaf5ee; color:var(--about-green); font-size:17px;
}
.rems-about-goal-point strong { display:block; color:var(--about-forest); font-size:14px; font-weight:850; margin-bottom:5px; }
.rems-about-goal-point span { color:var(--about-muted); font-size:13px; line-height:1.7; }

/* System Features */
.rems-about-features { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; }
.rems-about-feature {
    background:#fff; border:1px solid var(--about-border); border-radius:17px;
    padding:28px 25px; min-height:215px;
}
.rems-about-feature-icon {
    display:grid; place-items:center; width:46px; height:46px; margin-bottom:22px;
    border-radius:13px; background:#eaf5ee; color:var(--about-green); font-size:21px;
}
.rems-about-feature h3 { margin:0 0 11px; color:var(--about-forest); font-size:16px; font-weight:850; line-height:1.5; }
.rems-about-feature p { margin:0; font-size:13px; line-height:1.8; }

/* How It Works */
.rems-about-steps { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
.rems-about-step {
    position:relative; padding:25px 21px 27px; border:1px solid var(--about-border);
    border-top:3px solid var(--about-gold); border-radius:13px; background:#fff;
}
.rems-about-step-num { display:block; color:var(--about-mid); font-size:24px; font-weight:900; letter-spacing:-.035em; margin-bottom:18px; }
.rems-about-step h3 { margin:0 0 11px; color:var(--about-forest); font-size:15px; font-weight:850; line-height:1.5; }
.rems-about-step p { margin:0; font-size:13px; line-height:1.8; }

/* Our Purpose */
.rems-about-purpose-section {
    position:relative; isolation:isolate; overflow:hidden;
    background:linear-gradient(120deg,#04311c,#0b6137); padding-top:88px; padding-bottom:88px;
}
.rems-about-purpose-section:before {
    content:""; position:absolute; z-index:-1; width:400px; height:400px; border-radius:50%;
    right:-185px; top:-240px; border:70px solid rgba(255,255,255,.045);
}
.rems-about-purpose { max-width:890px; margin:0 auto; text-align:center; }
.rems-about-purpose-section .rems-about-kicker { color:var(--about-gold-soft); }
.rems-about-purpose-section h2 { color:#fff; }
.rems-about-purpose-section .rems-about-rule { margin-left:auto; margin-right:auto; }
.rems-about-purpose-section p { color:#eaf3ec; font-size:17px; line-height:1.95; }

.rems-about-bottom {
    display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px 20px;
    background:#032b19; color:#e5f1e8; padding:23px clamp(24px,4vw,65px);
    font-size:12px; line-height:1.65;
}

@media(max-width:950px) {
    .rems-about-definition, .rems-about-goal { grid-template-columns:1fr; gap:27px; }
    .rems-about-definition .rems-about-definition-title { max-width:none; }
    .rems-about-goal-intro p { max-width:none; }
    .rems-about-features { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .rems-about-steps { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media(max-width:650px) {
    .rems-about { padding-top:70px; }
    .rems-about-header { min-height:70px; padding:9px 13px; gap:8px; }
    .rems-about-brand { gap:8px; }
    .rems-about-brand-icon { width:40px; height:40px; flex-basis:40px; border-radius:11px; font-size:21px; }
    .rems-about-brand-copy strong { font-size:14px; }
    .rems-about-brand-copy small { display:none; }
    .rems-about-nav { gap:4px; }
    .rems-about-nav a { min-height:35px; padding:8px 9px; font-size:10px; }
    .rems-about-hero {
        min-height:465px;
        min-height:max(465px, calc(100vh - 70px));
        min-height:max(465px, calc(100dvh - 70px));
        padding:65px 22px;
    }
    .rems-about-emblem { width:116px; height:116px; }
    .rems-about-emblem i { font-size:55px; }
    .rems-about-section { padding:61px 22px; }
    .rems-about-definition-copy p { text-align:left; font-size:15px; }
    .rems-about-features, .rems-about-steps { grid-template-columns:1fr; }
    .rems-about-feature { min-height:auto; padding:24px 23px; }
    .rems-about-goal-point { padding:18px; }
    .rems-about-purpose-section p { font-size:15px; }
}
@media(max-width:380px) {
    .rems-about-brand-copy strong { font-size:12px; }
    .rems-about-nav a { padding:7px 6px; font-size:9px; }
}

/* Consistent typography across the public About page (icons retain Bootstrap Icons). */
.rems-about,
.rems-about *:not(.bi) {
    font-family: "Century Gothic", Arial, sans-serif !important;
}

/* Left-align content section headings; keep the introductory hero centered. */
.rems-about-section h2,
.rems-about-section h3,
.rems-about-intro,
.rems-about-purpose,
.rems-about-definition-title {
    text-align: left;
}
.rems-about-intro {
    width: 100%;
    max-width: none;
    margin: 0 0 38px;
}
.rems-about-intro .rems-about-rule,
.rems-about-purpose-section .rems-about-rule {
    margin-left: 0;
    margin-right: 0;
}
.rems-about-intro p {
    margin-left: 0;
    margin-right: 0;
    max-width: none;
}
.rems-about-purpose {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
}

/* Consistently justified section descriptions and card descriptions. */
.rems-about-section p,
.rems-about-goal-point span {
    text-align: justify;
    text-align-last: left;
    text-justify: inter-word;
    hyphens: auto;
    overflow-wrap: break-word;
}
.rems-about-goal-point span { display: block; }

/* Four process cards remain in the original layout without number labels. */
.rems-about-step { padding-top: 27px; }


/* ASU university palette refinement: clearer campus green, gold and warm orange,
   while retaining the existing section layout and Century Gothic typography. */
:root {
    --about-gold: #f5c928;
    --about-gold-soft: #ffe297;
    --about-mid: #08763d;
    --about-surface: #f7faf6;
    --about-border: #dce7dc;
}
.rems-about-header {
    background: linear-gradient(100deg, #043d23 0%, #086b3a 100%);
    border-bottom: 3px solid #e7b847;
}
.rems-about-nav .rems-nav-about {
    background:#fff5d9;
    color:#07502b;
    border-color:#f1d88d;
}
.rems-about-hero {
    background:
        radial-gradient(circle at 88% 19%, rgba(246,195,70,.27) 0%, rgba(246,195,70,.06) 32%, transparent 51%),
        radial-gradient(circle at 3% 85%, rgba(39,151,78,.32), transparent 49%),
        linear-gradient(132deg, #032f1a 0%, #066234 60%, #0a763e 100%);
    box-shadow: inset 0 -5px 0 rgba(245,201,40,.85);
}
.rems-about-hero:after {
    border-color: rgba(245,201,40,.15);
}
.rems-about-emblem {
    outline-color:rgba(255,220,115,.75);
    border-color:rgba(255,248,219,.34);
}
.rems-about-hero h1 span { color:#ffdc62; }
.rems-about-section--soft {
    background:linear-gradient(115deg, #eff7f0 0%, #f9f8ee 100%);
}
.rems-about-rule {
    width:70px;
    height:5px;
    background:linear-gradient(90deg,#f5ca28 0%,#e7a14b 100%);
}
.rems-about-goal-point {
    border-left:4px solid #e9bc3b;
    box-shadow:0 4px 13px rgba(4,54,27,.035);
}
.rems-about-goal-point i {
    background:#fff1cb;
    color:#086336;
}
.rems-about-feature {
    border-top:3px solid #e8c154;
    box-shadow:0 6px 18px rgba(8,74,38,.035);
}
.rems-about-feature-icon {
    background:#fff0c7;
    border:1px solid #f3d77e;
    color:#086b39;
}
.rems-about-feature:nth-child(3n+2) .rems-about-feature-icon {
    background:#e0f3e7;
    border-color:#c6e6d1;
}
.rems-about-step {
    border-top-color:#e5af3a;
    box-shadow:0 6px 18px rgba(8,74,38,.035);
}
.rems-about-purpose-section {
    background:
        radial-gradient(circle at 92% 10%, rgba(241,183,76,.17), transparent 42%),
        linear-gradient(120deg,#04351e 0%,#09683a 100%);
    border-top:5px solid #f0c248;
}
.rems-about-bottom {
    background:#04391f;
    border-top:2px solid #c99c35;
}

/* Compact moon-switch toggle: matches the Login page, before Create Account. */
.rems-about-nav .rems-about-theme-toggle {
    appearance:none;
    position:relative;
    display:inline-block;
    flex:0 0 55px;
    width:55px;
    min-width:55px;
    height:29px;
    min-height:0;
    padding:0;
    border:1px solid #dfe1e7;
    border-radius:999px;
    background:#e8e8ee;
    color:#16191e;
    cursor:pointer;
    vertical-align:middle;
    transition:background-color .18s ease,border-color .18s ease;
}
.rems-about-nav .rems-about-theme-toggle::before {
    content:"";
    position:absolute;
    left:3px;
    top:3px;
    width:21px;
    height:21px;
    border-radius:50%;
    background:#fff;
}
.rems-about-nav .rems-about-theme-toggle::after {
    content:"";
    position:absolute;
    right:4px;
    top:4px;
    width:19px;
    height:19px;
    border-radius:50%;
    background:#fff;
    box-shadow:0 1px 3px rgba(25,30,42,.13);
    transition:background-color .18s ease;
}
.rems-about-nav .rems-about-theme-toggle i {
    position:absolute;
    z-index:1;
    left:6px;
    top:50%;
    width:16px;
    transform:translateY(-50%);
    color:#141820;
    font-size:14px;
    line-height:1;
    text-align:center;
    pointer-events:none;
}
.rems-about-nav .rems-about-theme-toggle:hover {
    background:#dfe0e7;
    border-color:#c9ced5;
}
.rems-about-nav .rems-about-theme-toggle:focus-visible {
    outline:3px solid #edc447;
    outline-offset:3px;
}
html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle {
    background:#34453b;
    color:#f9d568;
    border-color:#5b7164;
}
html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle::before {
    left:auto;
    right:3px;
    background:#21392a;
}
html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle::after {
    right:auto;
    left:4px;
    background:#f9faf7;
}
html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle i {
    left:auto;
    right:6px;
    color:#ffda69;
}
html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle:hover {
    background:#40594a;
}
@media(max-width:650px) {
    .rems-about-nav .rems-about-theme-toggle {flex:0 0 47px;width:47px;min-width:47px;height:26px;}
    .rems-about-nav .rems-about-theme-toggle::before {width:20px;height:20px;top:2px;left:2px;}
    .rems-about-nav .rems-about-theme-toggle::after {width:18px;height:18px;top:3px;right:3px;}
    .rems-about-nav .rems-about-theme-toggle i {left:5px;font-size:12px;}
    html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle::before {left:auto;right:2px;}
    html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle::after {right:auto;left:3px;}
    html[data-rems-theme="dark"] .rems-about-nav .rems-about-theme-toggle i {left:auto;right:5px;}
}
@media(max-width:380px) {
    .rems-about-nav .rems-about-theme-toggle {flex-basis:44px;width:44px;min-width:44px;}
}

/* Unified ASU-REMS footer: moved from the Login page to About only. */
.rems-about .asu-public-footer {
  position:relative;overflow:hidden;width:100%;
  border-top:3px solid #dfb94f;
  background:linear-gradient(110deg,#f0f6ef 0%,#f8f6ee 54%,#fff4df 100%);
  color:#193e2c;font-family:"Century Gothic",Arial,sans-serif;
}
.rems-about .asu-public-footer, .rems-about .asu-public-footer * {box-sizing:border-box;}
.rems-about .asu-public-footer .asu-footer-grid {
  max-width:1400px;margin:0 auto;
  padding:40px clamp(22px,4.8vw,72px) 32px;
  display:grid;
  grid-template-columns:minmax(0,.88fr) minmax(0,.85fr) minmax(290px,1.2fr);
  gap:clamp(24px,4vw,66px);
  align-items:start;
}
.rems-about .asu-footer-col {min-width:0;}
.rems-about .asu-footer-col h2 {
  font-size:14px;font-weight:900;letter-spacing:.055em;text-transform:uppercase;
  color:#07532e;margin:0 0 25px;line-height:1.4;
}
.rems-about .asu-footer-col h2::after {
  content:"";display:block;width:50px;height:3px;margin-top:14px;
  background:linear-gradient(90deg,#f9d84f,#e9a74b);border-radius:999px;
}
.rems-about .asu-footer-links {margin:0;padding:0;list-style:none;display:grid;gap:14px;}
.rems-about .asu-footer-links li {font-size:12px;line-height:1.7;color:#516b5b;}
.rems-about .asu-footer-links a {color:#064c2c;font-weight:750;text-decoration:none;}
.rems-about .asu-footer-links a:hover,
.rems-about .asu-footer-links a:focus-visible {color:#a66c16;text-decoration:underline;text-underline-offset:3px;}
.rems-about .asu-footer-contact {
  background:#fffdf7;border:1px solid #d9e4d9;border-radius:17px;
  padding:25px 27px;color:#163b28;
  box-shadow:0 8px 25px rgba(10,65,37,.07);
}
.rems-about .asu-footer-contact strong {display:block;color:#063e25;font-size:14px;margin-bottom:16px;}
.rems-about .asu-footer-contact p {font-size:12px;line-height:1.8;color:#536056;margin:0 0 13px;}
.rems-about .asu-footer-contact p i {color:#d68e32;margin-right:8px;}
.rems-about .asu-footer-contact p:last-child {margin-bottom:0;}
.rems-about .asu-footer-official-link {
  display:inline-flex;align-items:center;gap:8px;
  padding:11px 15px;background:#f8dc92;color:#0d4126;
  border:1px solid #e9c371;border-radius:11px;
  font-size:12px;font-weight:850;text-decoration:none;
}
.rems-about .asu-footer-official-link:hover {background:#f5d17d;color:#0a3220;}
.rems-about .asu-footer-bottom {
  border-top:1px solid #d8e2d7;
  padding:19px clamp(22px,4.8vw,72px) 22px;
  display:flex;justify-content:space-between;align-items:center;
  flex-wrap:wrap;gap:10px 22px;
  color:#46604f;font-size:11px;line-height:1.6;
  background:rgba(232,239,223,.52);
}
.rems-about .asu-footer-bottom span:last-child {font-weight:800;color:#07532e;}
@media (max-width:950px) {
  .rems-about .asu-public-footer .asu-footer-grid {grid-template-columns:repeat(2,minmax(0,1fr));gap:30px;}
  .rems-about .asu-footer-contact-col {grid-column:1/-1;}
}
@media (max-width:600px) {
  .rems-about .asu-public-footer .asu-footer-grid {grid-template-columns:1fr;gap:27px;padding-block:34px 29px;}
  .rems-about .asu-footer-contact-col {grid-column:auto;}
  .rems-about .asu-footer-bottom {display:grid;gap:7px;}
  .rems-about .asu-footer-contact {padding:20px;}
}
/* Preserve the shared light/dark setting on the moved footer. */
html[data-rems-theme="dark"] .rems-about .asu-public-footer {
  background:linear-gradient(110deg,#061e14,#0b3120 58%,#16422d);
  border-top-color:#b89942;
  color:#edf5ec;
}
html[data-rems-theme="dark"] .rems-about .asu-footer-col h2 {color:#ffe3a0;}
html[data-rems-theme="dark"] .rems-about .asu-footer-links li {color:#cfdfd0;}
html[data-rems-theme="dark"] .rems-about .asu-footer-links a {color:#f2f7ef;}
html[data-rems-theme="dark"] .rems-about .asu-footer-links a:hover {color:#ffe3a0;}
html[data-rems-theme="dark"] .rems-about .asu-footer-bottom {
  background:rgba(0,15,9,.22);border-top-color:#345341;color:#dae6d9;
}
html[data-rems-theme="dark"] .rems-about .asu-footer-bottom span:last-child {color:#f9d778;}
html[data-rems-theme="dark"] .rems-about .asu-footer-contact {
  background:#173328;border-color:#47604b;
}
html[data-rems-theme="dark"] .rems-about .asu-footer-contact strong {color:#fff5ce;}
html[data-rems-theme="dark"] .rems-about .asu-footer-contact p {color:#d9e6db;}
html[data-rems-theme="dark"] .rems-about .asu-footer-official-link {color:#163b27;}


/* ASU-REMS unified dashboard palette; no layout, copy, or interactions changed. */
:root {
  --asu-green:#007a33;
  --asu-green-dark:#005c26;
  --asu-green-deep:#003f1a;
  --asu-yellow:#ffd51f;
  --asu-orange:#ff6a00;
  --asu-muted:#657068;
  --about-forest:#003f1a;
  --about-deep:#003f1a;
  --about-green:#007a33;
  --about-mid:#007a33;
  --about-gold:#ffd51f;
  --about-gold-soft:#ffe689;
  --about-ink:#101810;
  --about-muted:#657068;
  --about-border:#dce8df;
  --about-surface:#f5faf6;
}
.rems-about-header {
  background:linear-gradient(108deg,#005c26 0%,#007a33 100%);
  border-bottom-color:#ffd51f;
}
.rems-about-brand-icon {background:#ffd51f;color:#003f1a;}
.rems-about-nav .rems-nav-about {
  background:#fff8df;color:#003f1a;border-color:#ffe78e;
}
.rems-about-hero {
  background:
    radial-gradient(circle at 85% 13%,rgba(255,213,31,.13),transparent 37%),
    linear-gradient(112deg,#005c26 0%,#007a33 64%,#2c9a39 100%);
  box-shadow:inset 0 -4px 0 #ffd51f;
}
.rems-about-hero:after {border-color:rgba(255,213,31,.13);}
.rems-about-emblem {outline-color:rgba(255,213,31,.70);color:#007a33;}
.rems-about-hero h1 span {color:#ffe689;}
.rems-about-section--soft {
  background:linear-gradient(115deg,#f5faf6 0%,#f8fbf7 55%,#fff9ed 100%);
}
.rems-about-section h2 {color:#003f1a;}
.rems-about-kicker {color:#007a33;}
.rems-about-rule {background:linear-gradient(90deg,#ffd51f,#f0bd00);}
.rems-about-goal-point {border-left-color:#ffd51f;}
.rems-about-goal-point i,
.rems-about-feature-icon {color:#007a33;}
.rems-about-feature {border-top-color:#ffd51f;}
.rems-about-step {border-top-color:#ffd51f;}
.rems-about-purpose-section {
  background:
    radial-gradient(circle at 91% 10%,rgba(255,213,31,.12),transparent 42%),
    linear-gradient(112deg,#005c26 0%,#007a33 66%,#2c9a39 100%);
  border-top-color:#ffd51f;
}
.rems-about .asu-public-footer {
  background:linear-gradient(110deg,#f5faf6 0%,#f8fbf7 60%,#fff9ed 100%);
  border-top-color:#ffd51f;
}
.rems-about .asu-footer-col h2,
.rems-about .asu-footer-links a,
.rems-about .asu-footer-bottom span:last-child {color:#005c26;}
.rems-about .asu-footer-col h2::after {background:linear-gradient(90deg,#ffd51f,#f0bd00);}
.rems-about .asu-footer-contact {border-color:#dce8df;}
.rems-about .asu-footer-official-link {background:#ffe28a;border-color:#eac965;color:#003f1a;}
.rems-about .asu-footer-official-link:hover {background:#ffd51f;}
/* Preserve high-contrast shared Dark Mode; only its accent colors are aligned. */
html[data-rems-theme="dark"] .rems-about-header {
  background:linear-gradient(108deg,#003f1a,#005c26);
}
html[data-rems-theme="dark"] .rems-about-hero,
html[data-rems-theme="dark"] .rems-about-purpose-section {
  background:linear-gradient(112deg,#002713,#003f1a 65%,#005c26);
}
html[data-rems-theme="dark"] .rems-about .asu-public-footer {
  background:linear-gradient(110deg,#061e14,#0b3120 58%,#16422d);
  border-top-color:#ffd51f;
}

/* Keep the purpose heading readable against the green dashboard gradient. */
.rems-about-purpose-section h2 {color:#fff;}

/* About page header: clearly outlined Create Account and Sign In buttons.
   Match the thin gold border on the moon/theme button; About remains highlighted. */
.rems-about-nav a:not(.rems-nav-about) {
    border:1px solid rgba(255,213,31,.50);
    border-radius:12px;
    background:rgba(255,255,255,.08);
    color:#ffffff;
    transition:background-color .18s ease,border-color .18s ease;
}
.rems-about-nav a:not(.rems-nav-about):hover,
.rems-about-nav a:not(.rems-nav-about):focus-visible {
    background:rgba(255,255,255,.16);
    border-color:rgba(255,213,31,.84);
    color:#ffffff;
}


/* ASU-REMS logo on the About navbar and the hero emblem. */
.rems-about .rems-about-brand-icon {
    display: grid;
    place-items:center;
    background:#ffffff !important;
    border:1px solid rgba(255,213,31,.5);
    border-radius:50% !important;
    padding:3px;
    overflow:hidden;
}
.rems-about .rems-about-brand-icon img,
.rems-about .rems-about-emblem img {
    display:block;
    width:100%;
    height:100%;
    object-fit:contain;
    border-radius:50%;
}
.rems-about .rems-about-emblem {
    background:#ffffff !important;
    padding:6px;
    border-radius:50%;
    overflow:hidden;
}
html[data-rems-theme="dark"] .rems-about .rems-about-brand-icon,
html[data-rems-theme="dark"] .rems-about .rems-about-emblem {
    background:#ffffff !important;
}

</style>

<div class="rems-about">
    <header class="rems-about-header">
        <a href="<?= BASE_URL; ?>/login.php" class="rems-about-brand" aria-label="ASU-REMS Home">
            <span class="rems-about-brand-icon"><img src="<?= htmlspecialchars($asuRemsLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="ASU-REMS logo" loading="eager" decoding="async"></span>
            <span class="rems-about-brand-copy"><strong>ASU-REMS</strong><small>Aklan State University</small></span>
        </a>
        <nav class="rems-about-nav" aria-label="Public navigation">
            <button type="button" class="rems-theme-toggle rems-about-theme-toggle" data-rems-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false"><i class="bi bi-moon-fill" aria-hidden="true"></i></button>
            <a href="<?= BASE_URL; ?>/login.php?view=signup">Create Account</a>
            <a href="<?= BASE_URL; ?>/login.php?view=login">Sign In</a>
            <a href="<?= BASE_URL; ?>/about.php" class="rems-nav-about" aria-current="page">About</a>
        </nav>
    </header>

    <main>
        <section class="rems-about-hero" aria-labelledby="remsAboutHeading">
            <div class="rems-about-hero-inner">
                <div class="rems-about-emblem"><img src="<?= htmlspecialchars($asuRemsLogoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="ASU-REMS logo" loading="eager" decoding="async"></div>
                <h1 id="remsAboutHeading">About <span>ASU-REMS</span></h1>
                <p class="rems-about-lead">Aklan State University's web-based Research and Extension Management System for proposal processing, project monitoring, records management, and reporting.</p>
            </div>
        </section>

        <section class="rems-about-section rems-about-section--white" id="what-is-asu-rems" aria-labelledby="whatIsHeading">
            <div class="rems-about-container rems-about-definition">
                <div class="rems-about-definition-title">
                    <h2 id="whatIsHeading">What Is ASU-REMS?</h2>
                    <div class="rems-about-rule"></div>
                </div>
                <div class="rems-about-definition-copy">
                    <p>ASU-REMS (Aklan State University Research and Extension Management System) is a web-based platform developed to support the university's research and community extension activities. It brings proposal processing, project monitoring, and records management into one centralized system.</p>
                    <p>The platform is designed for faculty members, researchers, extension proponents, and authorized university personnel. Through dedicated user roles, they can submit and review proposals, track the progress of requests and approvals, and access relevant project information.</p>
                </div>
            </div>
        </section>

        <section class="rems-about-section rems-about-section--soft" id="our-goal" aria-labelledby="goalHeading">
            <div class="rems-about-container rems-about-goal">
                <div class="rems-about-goal-intro">
                    <h2 id="goalHeading">Our Goal</h2>
                    <div class="rems-about-rule"></div>
                    <p>Our goal is to provide a more organized and accessible way to manage research and extension processes at Aklan State University, from initial submissions to institutional documentation.</p>
                </div>
                <div class="rems-about-goal-points">
                    <div class="rems-about-goal-point">
                        <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                        <div><strong>Streamline Processes</strong><span>Support structured proposal submission, review, and approval workflows.</span></div>
                    </div>
                    <div class="rems-about-goal-point">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                        <div><strong>Improve Visibility</strong><span>Make project status and progress easier for authorized users to monitor.</span></div>
                    </div>
                    <div class="rems-about-goal-point">
                        <i class="bi bi-folder2-open" aria-hidden="true"></i>
                        <div><strong>Organize Information</strong><span>Maintain accessible records and support consistent institutional reporting.</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rems-about-section rems-about-section--white" id="system-features" aria-labelledby="featuresHeading">
            <div class="rems-about-container">
                <div class="rems-about-intro">
                    <h2 id="featuresHeading">System Features</h2>
                    <div class="rems-about-rule"></div>
                    <p>ASU-REMS provides essential tools for managing research and extension activities through organized, role-based workflows.</p>
                </div>
                <div class="rems-about-features">
                    <article class="rems-about-feature">
                        <span class="rems-about-feature-icon"><i class="bi bi-file-earmark-plus" aria-hidden="true"></i></span>
                        <h3>Online Proposal Submission</h3>
                        <p>Submit research and extension proposals with the required supporting information and document links.</p>
                    </article>
                    <article class="rems-about-feature">
                        <span class="rems-about-feature-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                        <h3>Review and Approval Workflow</h3>
                        <p>Support the evaluation, revision, endorsement, and approval stages handled by authorized offices.</p>
                    </article>
                    <article class="rems-about-feature">
                        <span class="rems-about-feature-icon"><i class="bi bi-clipboard-data" aria-hidden="true"></i></span>
                        <h3>Project Monitoring</h3>
                        <p>View project progress, processing status, and relevant updates throughout the workflow.</p>
                    </article>
                    <article class="rems-about-feature">
                        <span class="rems-about-feature-icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
                        <h3>Centralized Records</h3>
                        <p>Keep research and extension information organized in a central database with linked documents.</p>
                    </article>
                    <article class="rems-about-feature">
                        <span class="rems-about-feature-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                        <h3>Extension Service Requests</h3>
                        <p>Request, schedule, monitor, and confirm training and technical services through the extension module.</p>
                    </article>
                    <article class="rems-about-feature">
                        <span class="rems-about-feature-icon"><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i></span>
                        <h3>Reports and Role-Based Access</h3>
                        <p>Give authorized offices access to completed-project reports and management tools relevant to their roles.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rems-about-section rems-about-section--soft" id="how-it-works" aria-labelledby="howHeading">
            <div class="rems-about-container">
                <div class="rems-about-intro">
                    <h2 id="howHeading">How It Works</h2>
                    <div class="rems-about-rule"></div>
                    <p>The system guides users through the steps that apply to their roles and the type of research or extension activity.</p>
                </div>
                <div class="rems-about-steps">
                    <article class="rems-about-step">
                        <h3>Create an Account or Sign In</h3>
                        <p>Faculty members and extension proponents access the appropriate workspace using their accounts.</p>
                    </article>
                    <article class="rems-about-step">
                        <h3>Submit a Proposal or Request</h3>
                        <p>Provide project details and supporting document links, or submit a service request when applicable.</p>
                    </article>
                    <article class="rems-about-step">
                        <h3>Review and Track Progress</h3>
                        <p>Authorized offices process submissions while proponents follow updates and respond to required revisions.</p>
                    </article>
                    <article class="rems-about-step">
                        <h3>Manage Records and Reports</h3>
                        <p>Project documentation is maintained in the system, and authorized offices can generate completed-project reports.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rems-about-section rems-about-purpose-section" id="our-purpose" aria-labelledby="purposeHeading">
            <div class="rems-about-container rems-about-purpose">
                <h2 id="purposeHeading">Our Purpose</h2>
                <div class="rems-about-rule"></div>
                <p>ASU-REMS is developed to support Aklan State University's research and extension offices in managing institutional processes and documentation. By providing a central platform for coordination, recordkeeping, and reporting, the system aims to make research and extension information easier to manage and access for authorized university personnel.</p>
            </div>
        </section>
    </main>

    <footer class="asu-public-footer" id="asuPublicFooter" aria-label="ASU-REMS site footer">
    <div class="asu-footer-grid">
        <nav class="asu-footer-col" aria-label="Quick Links">
            <h2>Quick Links</h2>
            <ul class="asu-footer-links">
                <li><a href="<?= BASE_URL; ?>/login.php">Home</a></li>
                <li><a href="<?= BASE_URL; ?>/about.php">About ASU-REMS</a></li>
                <li><a href="https://asu.edu.ph/online/" target="_blank" rel="noopener noreferrer">ASU Official Website <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></li>
            </ul>
        </nav>
        <nav class="asu-footer-col" aria-label="Support">
            <h2>Support</h2>
            <ul class="asu-footer-links">
                <li><a href="<?= BASE_URL; ?>/login.php?view=login">Sign In</a></li>
                <li><a href="<?= BASE_URL; ?>/login.php?view=signup">Create Account</a></li>
                <li class="asu-footer-info">Research &amp; Extension Office assistance</li>
            </ul>
        </nav>
        <section class="asu-footer-col asu-footer-contact-col" aria-label="Contact">
            <h2>Contact</h2>
            <div class="asu-footer-contact">
                <strong>Aklan State University</strong>
                <p><i class="bi bi-geo-alt" aria-hidden="true"></i> Banga, Aklan, Philippines</p>
                <p>For account or system assistance, please coordinate with the university Research or Extension Office.</p>
                <p><a class="asu-footer-official-link" href="https://asu.edu.ph/online/" target="_blank" rel="noopener noreferrer">Visit ASU Website <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></p>
            </div>
        </section>
    </div>
    <div class="asu-footer-bottom">
        <span>&copy; <?= date('Y'); ?> Aklan State University. All rights reserved.</span>
        <span>ASU-REMS &nbsp;|&nbsp; Research &amp; Extension Management System</span>
    </div>
</footer>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
