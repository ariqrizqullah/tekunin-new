<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="TEKUNIN CCTV — CCTV, IP Camera, NVR, Access Control, Alarm, Network dan paket security system.">
<meta name="theme-color" content="#0b1324">
<title>TEKUNIN HOME | Integrated IT & Security System</title>
<!-- Favicon Logo -->
<link id="site-favicon" rel="icon" type="image/png" href="img/logo.png">
<script>document.documentElement.classList.add('js');</script>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Figtree:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* =====================================================================
   TEKUNIN — konsep "Viewfinder"
   Semua elemen berada "dalam bingkai" lensa kamera: sudut bracket yang
   mengunci fokus saat disentuh, reveal bergaya "focus pull", dan satu
   elemen utama: lensa di hero yang mengikuti kursor.
   ===================================================================== */
:root{
  /* warna */
  --navy:#0b1324;--ink:#1f2a44;--muted:#5a6780;--line:#e0e6ef;--bg:#f4f6fa;--paper:#fbfcfe;
  --blue:#2563eb;--blue-dark:#1d4ed8;--blue-soft:#e6eeff;--mint:#9bbcff;
  --amber:#6ea0ff;--amber-dark:#1d4ed8;--orange:#6ea0ff;--red:#ef4444;--green:#1fa971;
  /* bayangan & gerak */
  --shadow-sm:0 1px 2px rgba(11,19,36,.06);--shadow:0 22px 44px -22px rgba(11,19,36,.34);
  --ease:cubic-bezier(.16,1,.3,1);
  /* tipografi */
  --display:'Archivo','Plus Jakarta Sans',system-ui,sans-serif;
  --body:'Figtree',system-ui,-apple-system,'Segoe UI',sans-serif;
  --mono:'JetBrains Mono',ui-monospace,monospace;
}
*,*:before,*:after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;width:100%;overflow-x:hidden;scroll-padding-top:124px}
body{font-family:var(--body);font-size:16px;color:var(--ink);background:var(--bg);line-height:1.6;width:100%;overflow-x:hidden;overflow-x:clip;position:relative;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font:inherit}
button{cursor:pointer}
img{max-width:100%}
html body .container{width:100%;max-width:1240px;margin-left:auto;margin-right:auto;padding-left:24px;padding-right:24px}
:focus-visible{outline:2px solid var(--amber);outline-offset:3px}
::selection{background:var(--mint);color:var(--navy)}
h1,h2,h3,h4{font-family:var(--display);color:var(--navy);font-stretch:104%;letter-spacing:-.012em}
h3{font-size:1.05rem}

/* ---------- Keyframes ---------- */
@keyframes focusIn{from{opacity:0;filter:blur(10px);transform:translateY(16px) scale(.985)}to{opacity:1;filter:none;transform:none}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@keyframes shimmer{100%{transform:translateX(100%)}}
@keyframes blink{50%{opacity:.25}}
@keyframes scan{0%{top:-40%}100%{top:110%}}
@keyframes marquee{0%{transform:translate(0,0)}100%{transform:translate(-100%,0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes tally{0%{box-shadow:0 0 0 0 rgba(239,68,68,.55)}70%{box-shadow:0 0 0 9px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
@keyframes sonar{0%{transform:scale(.55);opacity:.8}100%{transform:scale(1.9);opacity:0}}
@keyframes ping{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.55);opacity:0}}
@keyframes lensIn{from{opacity:0;transform:scale(.88) rotate(-10deg);filter:blur(14px)}to{opacity:1;transform:none;filter:none}}
@keyframes irisOpen{from{transform:translateX(0)}to{transform:translateX(64px)}}
@keyframes irisBreath{0%,100%{transform:translateX(64px)}50%{transform:translateX(55px)}}
@keyframes eyeDrift{0%,100%{transform:translate(0,0)}30%{transform:translate(11px,-7px)}65%{transform:translate(-9px,8px)}}
@keyframes flashBox{0%,100%{box-shadow:0 0 0 0 rgba(110,160,255,0)}30%,70%{box-shadow:0 0 0 5px rgba(110,160,255,.6),0 26px 46px -18px rgba(37,99,235,.5)}}
@keyframes flashT{0%,100%{background:transparent}25%,65%{background:#ffe29a}}

/* ---------- Reveal: "focus pull" ----------
   Memakai properti translate/scale terpisah supaya tidak bentrok dengan
   transform milik efek hover. */
.js .reveal{opacity:0;filter:blur(7px);translate:0 14px;scale:.985;
  transition:opacity .9s var(--ease) var(--d,0s),filter .9s var(--ease) var(--d,0s),translate .9s var(--ease) var(--d,0s),scale .9s var(--ease) var(--d,0s),
             transform .5s var(--ease),box-shadow .5s var(--ease),border-color .3s,background-color .4s var(--ease),color .3s}
.js .reveal.in{opacity:1;filter:none;translate:none;scale:1}
.js .catalog.reveal,.js .sim-shell.reveal{filter:none}
@media (prefers-reduced-motion:reduce){
  *{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
  .js .reveal{opacity:1;filter:none;translate:none;scale:1}
}

/* ---------- Bracket viewfinder (dipakai di beberapa tempat) ---------- */
.hero:after,.pimg:after,.project-img:before,.sim-shell:before{
  --bw:1.5px;content:"";position:absolute;pointer-events:none;
  background:
    linear-gradient(var(--bk),var(--bk)) 0 0/var(--bs) var(--bw) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 0 0/var(--bw) var(--bs) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 100% 0/var(--bs) var(--bw) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 100% 0/var(--bw) var(--bs) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 0 100%/var(--bs) var(--bw) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 0 100%/var(--bw) var(--bs) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 100% 100%/var(--bs) var(--bw) no-repeat,
    linear-gradient(var(--bk),var(--bk)) 100% 100%/var(--bw) var(--bs) no-repeat}

/* ---------- Top bar ---------- */
.top{background:#070d19;color:#cdd7ea;font-size:12.5px;font-weight:600;overflow:hidden;position:relative;width:100%;border-bottom:1px solid rgba(155,188,255,.1)}
.top-in{display:flex;align-items:center;justify-content:space-between;gap:16px;height:38px}
.top-marquee{flex:1;overflow:hidden;white-space:nowrap;min-width:0;-webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent)}
.running-text{display:inline-block;animation:marquee 34s linear infinite;padding-left:100%;letter-spacing:.02em}
.running-text b{color:var(--mint)}
.top-links{display:flex;gap:22px;white-space:nowrap;color:#97a5c0}
.top-links a{transition:color .25s}
.top-links a:hover{color:var(--mint)}
.top-links i{margin-right:7px;color:var(--amber)}

/* ---------- Header ---------- */
header{position:sticky;top:0;z-index:50;background:rgba(11,19,36,.88);backdrop-filter:blur(16px) saturate(1.3);-webkit-backdrop-filter:blur(16px) saturate(1.3);color:#fff;width:100%;transition:box-shadow .4s,background .4s}
header.scrolled{box-shadow:0 16px 36px -16px rgba(0,0,0,.6);background:rgba(8,15,29,.95)}
header:after{content:"";position:absolute;left:0;right:0;bottom:0;height:2px;background:linear-gradient(90deg,var(--blue),var(--mint) 55%,var(--amber));transform:scaleX(var(--p,0));transform-origin:left;pointer-events:none}
.nav{display:flex;align-items:center;gap:20px;min-height:76px;flex-wrap:nowrap}
.logo{font-family:var(--display);font-stretch:112%;font-weight:800;font-size:20px;letter-spacing:.01em;display:flex;align-items:center;gap:10px;color:#fff;flex-shrink:0}
.logo img{height:40px;width:auto;object-fit:contain}
.search{flex:1;max-width:600px;position:relative;margin:0 auto}
.search input{width:100%;height:46px;border-radius:999px;border:1px solid rgba(155,188,255,.22);background:rgba(255,255,255,.07);color:#fff;padding:0 56px 0 46px;font-size:14.5px;font-weight:500;outline:none;transition:background .3s,border-color .3s,box-shadow .3s,color .3s}
.search input::placeholder{color:rgba(207,230,230,.62)}
.search input:hover{border-color:rgba(155,188,255,.42)}
.search input:focus{background:#fff;color:var(--navy);border-color:#fff;box-shadow:0 0 0 4px rgba(155,188,255,.3)}
.search input:focus::placeholder{color:#8693ab}
.search .s-ico{position:absolute;left:18px;top:50%;transform:translateY(-50%);color:rgba(207,230,230,.75);pointer-events:none;font-size:14px;transition:color .3s;z-index:1}
.search:focus-within .s-ico{color:var(--blue)}
.search button{position:absolute;right:5px;top:5px;height:36px;width:36px;border:0;border-radius:50%;background:var(--amber);color:#1c1200;transition:transform .35s var(--ease),background .25s;display:grid;place-items:center}
.search button:hover{background:#4a7df0;transform:scale(1.08)}
.search button i{transition:transform .35s var(--ease)}
.search button:hover i{transform:translateX(2px)}
.nav-actions{display:flex;align-items:center;gap:10px;flex-shrink:0}
.cart{position:relative;border:1px solid rgba(155,188,255,.22);background:rgba(255,255,255,.06);color:#fff;height:44px;min-width:44px;padding:0 17px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;gap:9px;font-size:13.5px;font-weight:700;transition:background .25s,border-color .25s,transform .35s var(--ease)}
.cart:hover{background:rgba(155,188,255,.14);border-color:rgba(155,188,255,.55);transform:translateY(-1px)}
.cart .lbl{display:inline}
.badge{position:absolute;right:-5px;top:-7px;background:var(--amber);color:#1c1200;font-size:10.5px;border-radius:20px;padding:1px 6px;font-weight:800;min-width:21px;text-align:center;border:2px solid #0b1324}
.burger{display:none}
.subnav{border-top:1px solid rgba(155,188,255,.1)}
.menu{display:flex;gap:2px;font-size:13.5px;font-weight:600;color:#b4c2dc;height:46px;align-items:center;overflow-x:auto;scrollbar-width:none}
.menu::-webkit-scrollbar{display:none}
.menu a{padding:9px 16px;border-radius:999px;white-space:nowrap;transition:color .25s,background .25s;position:relative}
.menu a:hover{color:#fff;background:rgba(255,255,255,.07)}
.menu a.on{color:#fff}
.menu a:after{content:"";position:absolute;left:16px;right:16px;bottom:3px;height:2px;border-radius:2px;background:var(--amber);transform:scaleX(0);transform-origin:left;transition:transform .55s var(--ease)}
.menu a.on:after{transform:scaleX(1)}
.mobile-menu{display:none;background:#080f1d;border-top:1px solid rgba(155,188,255,.12)}
.mobile-menu.open{display:block;animation:fadeUp .35s var(--ease)}
.mobile-menu a{display:flex;align-items:center;gap:14px;padding:15px 24px;font-weight:600;font-size:14.5px;color:#e3eaf7;border-bottom:1px solid rgba(155,188,255,.08);transition:background .25s,padding-left .35s var(--ease)}
.mobile-menu a:hover{background:rgba(155,188,255,.08);padding-left:30px}
.mobile-menu a i{width:20px;text-align:center;color:var(--mint)}

/* ---------- Pencarian: sorotan & saran ---------- */
.product{scroll-margin-top:150px}
mark.hit{background:#ffe29a;color:inherit;border-radius:4px;padding:0 2px}
.product.flash{animation:flashBox 2s ease 1;border-color:var(--amber)}
.flash-t{animation:flashT 2.2s ease 1;border-radius:10px;padding:2px 10px;display:inline-block}
.suggest{position:absolute;left:0;right:0;top:calc(100% + 10px);background:#fff;color:var(--navy);border-radius:14px;box-shadow:0 30px 60px -16px rgba(5,10,20,.55);border:1px solid var(--line);display:none;z-index:60;max-height:70vh;overflow-y:auto}
.suggest.show{display:block;animation:fadeUp .3s var(--ease)}
.sg-h{font-size:12px;font-weight:700;color:var(--muted);padding:14px 18px 6px}
.sg-i{display:flex;gap:12px;align-items:center;padding:10px 18px;cursor:pointer;transition:background .2s}
.sg-i:hover,.sg-i.on{background:#eef3ff}
.sg-i img{width:46px;height:46px;border-radius:8px;object-fit:cover;background:#eaeff6;flex-shrink:0}
.sg-i .ic{width:46px;height:46px;border-radius:50%;background:var(--blue-soft);color:var(--blue);display:grid;place-items:center;flex-shrink:0}
.sg-i b{display:block;font-size:13.5px;font-weight:700;line-height:1.3}
.sg-i small{font-size:12px;color:var(--muted)}
.sg-i .pr{margin-left:auto;font-size:12.5px;font-weight:800;color:var(--blue-dark);white-space:nowrap;padding-left:8px}
.sg-all{padding:13px 18px;border-top:1px solid var(--line);background:var(--paper);font-size:12.5px;font-weight:700;color:var(--blue-dark);cursor:pointer;transition:background .2s}
.sg-all:hover{background:#eef3ff}
.sg-empty{padding:20px 18px;font-size:13.5px;color:var(--muted)}

/* ---------- Tombol ---------- */
.btn{position:relative;overflow:hidden;isolation:isolate;border:0;border-radius:999px;padding:13px 26px;background:var(--amber);color:#1c1200;font-weight:700;font-size:14.5px;transition:transform .4s var(--ease),box-shadow .4s var(--ease),background .25s,color .25s,border-color .25s;display:inline-flex;align-items:center;justify-content:center;gap:9px;line-height:1.2}
.btn:before{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(100deg,transparent 30%,rgba(255,255,255,.55) 50%,transparent 70%);transform:translateX(-130%);transition:transform .9s var(--ease)}
.btn:hover{background:#4a7df0;transform:translateY(-2px);box-shadow:0 16px 28px -14px rgba(37,99,235,.8)}
.btn:hover:before{transform:translateX(130%)}
.btn:active{transform:translateY(0);box-shadow:none}
.btn[style*="background"]{color:#fff}
.btn.light{background:#fff;color:var(--navy)}
.btn.light:hover{background:var(--mint);color:var(--navy);box-shadow:0 16px 28px -14px rgba(155,188,255,.7)}
.btn.outline{background:transparent;border:1.5px solid #c3cddc;color:var(--navy)}
.btn.outline:hover{background:var(--navy);border-color:var(--navy);color:#fff;box-shadow:none}
.btn.ghost{background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.28);color:#fff}
.btn.ghost:hover{background:rgba(255,255,255,.14);border-color:#fff;box-shadow:none;color:#fff}

/* ---------- HERO ---------- */
.hero{position:relative;overflow:hidden;color:#fff;isolation:isolate;padding:76px 0 156px;
  background:radial-gradient(900px 620px at 76% 42%,rgba(37,99,235,.5),transparent 62%),radial-gradient(620px 420px at 4% 100%,rgba(110,160,255,.1),transparent 62%),linear-gradient(180deg,#0b1324 0%,#080f1d 100%)}
.hero:before{content:"";position:absolute;inset:0;z-index:-1;background:repeating-linear-gradient(0deg,rgba(255,255,255,.03) 0 1px,transparent 1px 4px);-webkit-mask-image:linear-gradient(180deg,#000 35%,transparent);mask-image:linear-gradient(180deg,#000 35%,transparent)}
.hero:after{inset:22px 22px 96px;--bk:rgba(155,188,255,.34);--bs:30px;z-index:-1}
.hero-grid{display:grid;grid-template-columns:1.02fr .98fr;gap:56px;align-items:center;position:relative}
.hero-copy{max-width:640px}
.hero-copy>*{animation:focusIn 1s var(--ease) both}
.hero-copy>*:nth-child(1){animation-delay:.1s}
.hero-copy>*:nth-child(2){animation-delay:.22s}
.hero-copy>*:nth-child(3){animation-delay:.36s}
.hero-copy>*:nth-child(4){animation-delay:.5s}
.eyebrow{display:inline-flex;align-items:center;gap:10px;font-size:13px;font-weight:600;letter-spacing:.01em;color:var(--mint);margin-bottom:28px;padding:8px 17px 8px 14px;border-radius:999px;border:1px solid rgba(155,188,255,.28);background:rgba(155,188,255,.07)}
.eyebrow:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--red);animation:tally 1.6s infinite;flex:none}
h1{font-size:clamp(34px,4.7vw,62px);line-height:1.06;letter-spacing:-.028em;font-stretch:106%;font-weight:700;margin-bottom:26px;color:#fff;text-wrap:balance}
h1 span{color:inherit}
.hero p{color:rgba(214,235,235,.84);font-size:17.5px;margin-bottom:36px;line-height:1.7;max-width:52ch}
.hero .actions{display:flex;gap:12px;flex-wrap:wrap}
.hero .actions .btn{padding:16px 28px;font-size:15.5px}

/* Lensa */
.hero-visual{position:relative;width:100%;max-width:540px;margin-left:auto}
.lens{position:relative;aspect-ratio:1/1;width:100%;animation:lensIn 1.4s var(--ease) .05s both}
.lens:before{content:"";position:absolute;inset:3%;border-radius:50%;box-shadow:0 0 140px 14px rgba(37,99,235,.5),0 50px 90px rgba(0,0,0,.55)}
.lens-svg{position:relative;display:block;width:100%;height:100%;overflow:visible}
.lens-ring{transform-box:view-box;transform-origin:50% 50%}
.lens-ring.r1{animation:spin 90s linear infinite}
.lens-ring.r2{animation:spin 60s linear infinite reverse}
.lens-iris-ring{transform-box:view-box;transform-origin:50% 50%;animation:spin 30s linear infinite}
.lens-sweep{transform-box:view-box;transform-origin:50% 50%;animation:spin 6s linear infinite}
.lens-eye{animation:eyeDrift 11s ease-in-out infinite;transition:transform .35s ease-out}
.lens.live .lens-eye{animation:none;transform:translate(calc(var(--ex,0)*1px),calc(var(--ey,0)*1px))}
.lens-glare{transform:translate(calc(var(--ex,0)*-.22px),calc(var(--ey,0)*-.22px));transition:transform .45s ease-out}
.blade{transform:translateX(64px);
  animation-name:irisOpen,irisBreath;animation-duration:1.9s,7s;animation-timing-function:var(--ease),ease-in-out;
  animation-delay:calc(.2s + var(--k,0)*45ms),3s;animation-iteration-count:1,infinite;animation-fill-mode:both,none}
.hud{position:absolute;font-family:var(--mono);font-size:clamp(9px,2.1vw,12px);font-weight:700;letter-spacing:.08em;color:var(--mint);text-shadow:0 0 12px rgba(155,188,255,.5);z-index:2;pointer-events:none}
.hud-rec{left:7%;top:6%;display:flex;align-items:center;gap:7px;color:#ff7468}
.hud-rec i{width:9px;height:9px;border-radius:50%;background:#ff4d3d;animation:tally 1.2s infinite}
.hud-cam{right:7%;top:6%}
.hud-tc{left:7%;bottom:6%}
.hero-float{position:absolute;background:rgba(11,19,36,.72);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);color:#fff;border:1px solid rgba(155,188,255,.3);border-radius:10px;padding:10px 15px 10px 11px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:11px;z-index:3;animation:focusIn 1s var(--ease) both;box-shadow:0 20px 40px -18px rgba(0,0,0,.6)}
.hero-float i{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:rgba(155,188,255,.14);color:var(--mint);flex:none}
.hero-float small{display:block;font-weight:500;color:#97a5c0;font-size:12px}
.hero-float:after{content:"";position:absolute;top:50%;width:38px;height:1px;background:linear-gradient(90deg,var(--mint),transparent)}
.hero-float.a{left:-9%;bottom:15%;animation-delay:1.2s}
.hero-float.a:after{left:100%}
.hero-float.b{right:-5%;top:21%;animation-delay:1.4s}
.hero-float.b:after{right:100%;transform:scaleX(-1)}

/* ---------- Strip keunggulan ---------- */
.usp-wrap{margin-top:-78px;position:relative;z-index:5;padding:0}
.trust{display:grid;grid-template-columns:repeat(4,1fr);gap:0;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow)}
.trust div{background:transparent;border:0;border-right:1px solid var(--line);padding:26px 26px;display:flex;gap:16px;align-items:center}
.trust div:last-child{border-right:0}
.trust i{flex:0 0 46px;height:46px;border-radius:50%;background:var(--blue-soft);color:var(--blue);display:grid;place-items:center;font-size:18px;transition:background .4s var(--ease),color .4s var(--ease),transform .5s var(--ease)}
.trust div:hover i{background:var(--navy);color:var(--mint);transform:rotate(-8deg) scale(1.06)}
.trust b{display:block;color:var(--navy);font-family:var(--display);font-size:15px;font-weight:700;line-height:1.25}
.trust small{color:var(--muted);font-size:13px}

/* ---------- Section ---------- */
section{padding:104px 0;width:100%;position:relative}
.white{background:var(--paper)}
.section-title{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:10px 64px;align-items:end;text-align:left;margin-bottom:48px;padding-bottom:26px;border-bottom:1px solid var(--line);position:relative}
.section-title:after{content:"";position:absolute;left:0;bottom:-1px;width:72px;height:3px;background:var(--amber)}
.js .section-title:after{width:0;transition:width 1.1s var(--ease) .35s}
.js .section-title.in:after{width:72px}
.section-title .kicker{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.section-title h2{grid-column:1;font-size:clamp(27px,3.4vw,44px);line-height:1.1;letter-spacing:-.025em;font-stretch:108%;font-weight:700;color:var(--navy);text-wrap:balance}
.section-title p{grid-column:2;color:var(--muted);font-size:16px;max-width:46ch;margin:0;line-height:1.65}
#simulation .section-title,#project .section-title{border-color:rgba(155,188,255,.16)}
#simulation .section-title p,#project .section-title p{color:#97a5c0!important}

/* ---------- Kategori ---------- */
.categories{display:grid;grid-template-columns:repeat(6,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:12px;overflow:hidden}
.cat,.card-box,.package{position:relative}
.cat:before,.card-box:before,.package:before{content:"";position:absolute;inset:0;pointer-events:none;opacity:0;transition:opacity .5s;background:radial-gradient(320px circle at var(--mx,50%) var(--my,50%),rgba(37,99,235,.15),transparent 65%)}
.cat:hover:before,.card-box:hover:before,.package:hover:before{opacity:1}
.cat{background:var(--paper);padding:32px 14px 28px;text-align:center;cursor:pointer;transition:background-color .4s var(--ease)}
.cat:hover{background:#fff}
.cat>*{position:relative}
.cat .pic{height:68px;width:68px;margin:0 auto 18px;border-radius:50%;background:var(--blue-soft);display:grid;place-items:center;font-size:24px;color:var(--blue);transition:background-color .45s var(--ease),color .45s var(--ease),transform .55s var(--ease)}
.cat .pic:before{content:"";position:absolute;inset:-8px;border-radius:50%;border:1.5px dashed rgba(37,99,235,.5);opacity:0;transform:scale(.8);transition:opacity .4s,transform .7s var(--ease)}
.cat:hover .pic{background:var(--navy);color:var(--mint);transform:scale(1.05)}
.cat:hover .pic:before{opacity:1;transform:scale(1);animation:spin 9s linear infinite}
.cat b{font-family:var(--display);font-size:14.5px;display:block;color:var(--navy);font-weight:700;line-height:1.3}
.cat span{font-size:12.5px;color:var(--muted);margin-top:4px;display:block}
.cat .cnt{display:inline-block;margin-top:10px;font-size:11.5px;font-weight:700;color:var(--blue-dark);background:#fff;border:1px solid #cddcf8;border-radius:20px;padding:1px 10px}

/* ---------- Katalog ---------- */
#produk{background:var(--bg)}
.catalog{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow-sm);padding:26px}
.toolbar{display:flex;gap:16px;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;padding-bottom:22px;border-bottom:1px solid var(--line)}
.chips{display:flex;gap:8px;flex-wrap:wrap;flex:1}
.chip{border:1px solid var(--line);background:#fff;padding:9px 17px;border-radius:999px;color:var(--muted);font-size:13.5px;font-weight:700;transition:background-color .3s,border-color .3s,color .3s,transform .4s var(--ease);white-space:nowrap;display:inline-flex;align-items:center;gap:9px}
.chip .n{font-size:11px;background:#eef2f8;color:var(--muted);border-radius:20px;padding:1px 8px;font-weight:800;transition:background-color .3s,color .3s}
.chip:hover{border-color:var(--blue);color:var(--blue-dark);transform:translateY(-1px)}
.chip.active{background:var(--navy);color:#fff;border-color:var(--navy)}
.chip.active .n{background:var(--mint);color:var(--navy)}
.sortbox{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:700;color:var(--muted)}
.sortbox select{height:42px;border:1px solid var(--line);border-radius:999px;padding:0 16px;background:#fff;color:var(--navy);font-weight:600;font-size:13.5px;outline:none;transition:border-color .25s,box-shadow .25s}
.sortbox select:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(37,99,235,.12)}
.result-info{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:18px;font-size:14px;color:var(--muted);font-weight:500}
.result-info b{color:var(--navy)}
.result-info button{border:0;background:none;color:var(--blue-dark);font-weight:700;font-size:13px}
.result-info button:hover{text-decoration:underline}

.products{display:grid;grid-template-columns:repeat(4,1fr);gap:22px}
.product{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden;position:relative;transition:transform .55s var(--ease),box-shadow .55s var(--ease),border-color .3s;display:flex;flex-direction:column}
.product.anim{animation:focusIn .75s var(--ease) backwards;animation-delay:calc(var(--i,0)*55ms)}
.product:hover{transform:translateY(-5px);box-shadow:0 30px 46px -24px rgba(11,19,36,.45);border-color:rgba(37,99,235,.55)}
.pimg{aspect-ratio:1/1;background:linear-gradient(160deg,#f6f8fc,#eaeff6);display:grid;place-items:center;position:relative;overflow:hidden}
.pimg img{width:100%;height:100%;object-fit:cover;transition:transform 1s var(--ease)}
.product:hover .pimg img{transform:scale(1.06)}
.pimg:after{inset:30px;--bk:#fff;--bs:20px;--bw:2px;opacity:0;transform:scale(1.12);transition:opacity .35s,transform .7s var(--ease);filter:drop-shadow(0 1px 3px rgba(0,0,0,.55))}
.product:hover .pimg:after{opacity:1;transform:scale(1)}
.ribbon{position:absolute;left:0;top:0;background:var(--amber);color:#1c1200;font-size:11.5px;font-weight:800;padding:6px 13px 6px 11px;border-radius:0 0 10px 0;z-index:2}
.gtag{position:absolute;right:0;bottom:0;z-index:2;background:rgba(11,19,36,.84);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:#fff;font-size:11.5px;font-weight:700;padding:6px 11px 6px 13px;border-radius:10px 0 0 0;display:flex;align-items:center;gap:6px}
.gtag i{color:var(--mint)}
.pbody{padding:18px;display:flex;flex-direction:column;flex:1}
.brand{font-size:12.5px;color:var(--blue-dark);font-weight:700}
.product h3{font-size:16px;line-height:1.35;margin:5px 0 7px;font-stretch:100%;font-weight:650;color:var(--navy);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:43px}
.spec{font-size:13px;color:var(--muted);margin-bottom:12px;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:40px}
.spec-box{margin-bottom:12px}
.spec-box .spec{margin-bottom:0}
.spec.open{display:block;-webkit-line-clamp:unset;overflow:visible}
.spec-more{display:none;margin-top:4px;padding:0;border:0;background:none;font-size:12.5px;font-weight:700;color:var(--blue-dark);cursor:pointer}
.spec-more:hover{text-decoration:underline}
.spec-more i{font-size:10px;margin-left:4px;transition:transform .3s var(--ease)}
.spec-box.is-long .spec-more{display:inline-block}
.spec-box.is-open .spec-more i{transform:rotate(180deg)}
.stars{color:var(--amber);font-size:11.5px;margin-bottom:12px;display:flex;align-items:center;gap:2px}
.price{font-family:var(--display);font-stretch:104%;font-size:21px;font-weight:700;color:var(--navy);letter-spacing:-.015em;margin-top:auto}
.pfoot{display:flex;gap:8px;margin-top:16px}
.pfoot button{flex:1;padding:12px 10px;font-size:13px;border-radius:999px;font-weight:700;transition:background-color .3s,color .3s,box-shadow .4s var(--ease),transform .4s var(--ease)}
.buy{background:var(--navy);color:#fff;border:0}
.buy:hover{background:var(--amber);color:#1c1200;box-shadow:0 12px 22px -12px rgba(37,99,235,.9)}
.add{background:var(--blue-soft);color:var(--blue-dark);border:0;flex:0 0 46px!important}
.add:hover{background:var(--blue);color:#fff}
.add:active,.buy:active{transform:scale(.96)}
.more{margin-top:14px;border:0;background:none;padding:12px 0 0;border-top:1px solid var(--line);font-size:13px;font-weight:700;color:var(--blue-dark);display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left}
.more i{transition:transform .4s var(--ease)}
.more:hover i{transform:translateX(5px)}
.buy .sm{display:none}
.skel{border-radius:10px;border:1px solid var(--line);overflow:hidden;background:#fff}
.skel .a,.skel .b,.skel .c{position:relative;overflow:hidden;background:#e9eef6}
.skel .a{aspect-ratio:1/1}.skel .b{height:14px;margin:18px 18px 8px;border-radius:6px}.skel .c{height:38px;margin:10px 18px 20px;border-radius:999px}
.skel .a:after,.skel .b:after,.skel .c:after{content:"";position:absolute;inset:0;transform:translateX(-100%);background:linear-gradient(90deg,transparent,rgba(255,255,255,.75),transparent);animation:shimmer 1.4s infinite}
.noresult{grid-column:1/-1;padding:64px 20px;text-align:center;color:var(--muted);background:var(--paper);border-radius:12px;border:1px dashed #c3cddc}
.noresult i{font-size:40px;color:#c3cddc;margin-bottom:14px;display:block}

/* ---------- CTA konsultasi ---------- */
.sale{background:radial-gradient(540px 340px at 100% 0%,rgba(37,99,235,.6),transparent 65%),linear-gradient(135deg,#0b1324,#15223f);color:#fff;border-radius:16px;padding:60px;display:grid;grid-template-columns:1.2fr .8fr;gap:40px;align-items:center;position:relative;overflow:hidden;box-shadow:var(--shadow);isolation:isolate}
.sale.alt{background:radial-gradient(520px 320px at 0% 100%,rgba(110,160,255,.16),transparent 62%),linear-gradient(120deg,#2563eb 0%,#1d4ed8 55%,#0b1324 100%)}
.sale>*{position:relative}
.sale .eyebrow{margin-bottom:20px}
.sale h2{font-size:clamp(24px,2.9vw,36px);line-height:1.18;margin:0 0 14px;font-weight:700;letter-spacing:-.022em;font-stretch:106%;color:#fff;text-wrap:balance}
.sale p{color:#a3b2cf;font-size:16px;line-height:1.7;max-width:54ch}
.sale .mock{height:230px;display:grid;place-items:center;position:relative;font-size:78px;color:var(--mint)}
.sale .mock:before,.sale .mock:after{content:"";position:absolute;width:130px;height:130px;border-radius:50%;border:1.5px solid rgba(155,188,255,.55);animation:sonar 3.4s var(--ease) infinite}
.sale .mock:after{animation-delay:1.7s}
.sale .mock i{color:var(--mint);position:relative;z-index:1;filter:drop-shadow(0 0 24px rgba(155,188,255,.5))}

/* ---------- Solusi ---------- */
.cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.card-box{background:transparent;padding:46px 36px 50px;border-right:1px solid var(--line);transition:background-color .5s var(--ease)}
.card-box:last-child{border-right:0}
.card-box:hover{background:#fff}
.card-box>*{position:relative}
.card-box .ic{width:60px;height:60px;border-radius:50%;background:var(--blue-soft);color:var(--blue);display:grid;place-items:center;font-size:23px;margin-bottom:24px;position:relative;transition:background-color .45s var(--ease),color .45s var(--ease),transform .55s var(--ease)}
.card-box .ic:before{content:"";position:absolute;inset:-8px;border-radius:50%;border:1.5px dashed rgba(37,99,235,.5);opacity:0;transform:scale(.8);transition:opacity .4s,transform .7s var(--ease)}
.card-box:hover .ic{background:var(--navy);color:var(--mint);transform:scale(1.05)}
.card-box:hover .ic:before{opacity:1;transform:scale(1);animation:spin 9s linear infinite}
.card-box b{font-family:var(--display);font-stretch:104%;font-size:20px;color:var(--navy);display:block;margin-bottom:10px;font-weight:700;letter-spacing:-.012em}
.card-box p{color:var(--muted);font-size:15px;line-height:1.7;max-width:36ch}

/* ---------- Project: tile "feed" kamera ---------- */
#project{background:linear-gradient(180deg,#0b1324,#080f1d);color:#fff}
.projects{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.project{position:relative;background:#101b33;border:1px solid rgba(155,188,255,.14);border-radius:10px;overflow:hidden;transition:transform .55s var(--ease),border-color .3s,box-shadow .55s var(--ease)}
.project:hover{transform:translateY(-6px);border-color:rgba(155,188,255,.5);box-shadow:0 34px 56px -26px rgba(0,0,0,.8)}
.project:before{content:"REC";position:absolute;left:18px;top:16px;z-index:3;font-family:var(--mono);font-size:11px;font-weight:700;letter-spacing:.1em;color:#ff7468;padding-left:16px;background:radial-gradient(circle at 5px 50%,#ff4d3d 0 4px,transparent 4.6px) no-repeat;animation:blink 1.8s infinite}
.project-img{height:210px;background:radial-gradient(circle at 70% 20%,rgba(155,188,255,.2),transparent 55%),repeating-linear-gradient(0deg,rgba(255,255,255,.035) 0 1px,transparent 1px 4px),linear-gradient(135deg,#16243f,#0b1324);display:grid;place-items:center;color:rgba(155,188,255,.7);position:relative;overflow:hidden}
.project-img:after{content:"";position:absolute;left:0;right:0;height:38%;background:linear-gradient(180deg,transparent,rgba(155,188,255,.16),transparent);animation:scan 4.4s linear infinite}
.project:nth-child(2) .project-img:after{animation-delay:-1.4s}
.project:nth-child(3) .project-img:after{animation-delay:-2.8s}
.project-img:before{inset:16px;--bk:rgba(155,188,255,.75);--bs:18px;--bw:2px;opacity:0;transform:scale(1.1);transition:opacity .35s,transform .7s var(--ease);z-index:2}
.project:hover .project-img:before{opacity:1;transform:scale(1)}
.project-img i{position:relative;z-index:1;transition:transform .7s var(--ease)}
.project:hover .project-img i{transform:scale(1.14)}
.project-body{padding:22px 24px 26px;border-top:1px solid rgba(155,188,255,.12)}
.project-body small{color:var(--mint);font-weight:700;font-size:12.5px}
.project-body h3{font-size:19px;font-weight:700;color:#fff;margin:5px 0 6px;font-stretch:104%}
.project-body p{font-size:14px;color:#97a5c0;margin:0}

/* ---------- Paket ---------- */
.packages{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;align-items:stretch}
.package{background:#fff;border:1px solid var(--line);border-radius:14px;padding:38px 32px 32px;display:flex;flex-direction:column;overflow:hidden;transition:transform .55s var(--ease),box-shadow .55s var(--ease),border-color .3s}
.package:hover{transform:translateY(-6px);box-shadow:var(--shadow);border-color:#c3cddc}
.package>*{position:relative}
.package h3{font-size:23px;font-weight:700;color:var(--navy);font-stretch:108%}
.package .amount{font-family:var(--display);font-stretch:104%;font-size:38px;font-weight:700;color:var(--blue-dark);margin:14px 0 26px;letter-spacing:-.025em}
.amount .per{font-family:var(--body);font-size:14px;font-weight:600;color:var(--muted);letter-spacing:0;margin-left:3px}
.package ul{list-style:none;margin-bottom:30px;flex:1}
.package li{font-size:15px;padding:13px 0;border-bottom:1px dashed var(--line);color:var(--ink);display:flex;align-items:center;gap:13px}
.package li:last-child{border-bottom:0}
.package li::before{content:"\f00c";font-family:"Font Awesome 6 Free";font-weight:900;color:var(--blue);font-size:10px;width:22px;height:22px;border-radius:50%;background:var(--blue-soft);display:grid;place-items:center;flex-shrink:0}
.package.hot{background:radial-gradient(420px 260px at 100% 0%,rgba(37,99,235,.5),transparent 65%),linear-gradient(165deg,#15223f,#0b1324);border-color:#0b1324;color:#fff;box-shadow:0 40px 64px -30px rgba(11,19,36,.75)}
.package.hot:before{background:radial-gradient(320px circle at var(--mx,50%) var(--my,50%),rgba(155,188,255,.16),transparent 65%)}
.package.hot h3{color:#fff}
.package.hot .amount{color:var(--amber)}
.package.hot .amount .per{color:#97a5c0}
.package.hot li{color:#d6e0f2;border-bottom-color:rgba(155,188,255,.16)}
.package.hot li::before{background:rgba(155,188,255,.18);color:var(--mint)}
.package .pkg-flag{position:absolute;top:15px;left:32px;display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--amber)}

/* ---------- Artikel: daftar editorial ---------- */
.article-grid{display:grid;grid-template-columns:1fr;gap:16px}
.article{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;display:grid;grid-template-columns:190px 1fr;align-items:stretch;transition:transform .55s var(--ease),box-shadow .55s var(--ease),border-color .3s}
.article:hover{transform:translateX(6px);box-shadow:var(--shadow);border-color:#c3cddc}
.article-img{min-height:136px;background:radial-gradient(circle at 30% 20%,rgba(155,188,255,.22),transparent 60%),linear-gradient(135deg,#1b2c4d,#0b1324);display:grid;place-items:center;color:var(--mint)}
.article-img i{transition:transform .7s var(--ease)}
.article:hover .article-img i{transform:scale(1.16) rotate(-6deg)}
.article-body{padding:26px 32px;display:flex;flex-direction:column;justify-content:center}
.article-body small{color:var(--blue-dark);font-weight:700;font-size:13px}
.article-body h3{font-size:21px;font-weight:700;color:var(--navy);margin:6px 0 8px;line-height:1.3;letter-spacing:-.015em}
.article-body p{font-size:15px;color:var(--muted);line-height:1.65;max-width:62ch}

/* ---------- Footer ---------- */
footer{background:#070d19;color:#97a5c0;padding:84px 0 28px;width:100%;position:relative}
footer:before{content:"";position:absolute;left:0;right:0;top:0;height:2px;background:linear-gradient(90deg,transparent,var(--blue),var(--amber),var(--blue),transparent)}
.footgrid{display:grid;grid-template-columns:1.6fr 1fr 1fr 1.3fr;gap:44px}
.footgrid h3{color:#fff;font-size:15px;font-weight:700;margin-bottom:18px}
.footgrid a,.footgrid p{display:block;font-size:14.5px;margin:11px 0;color:#97a5c0;transition:color .25s,transform .4s var(--ease);line-height:1.65}
.footgrid a:hover{color:var(--mint);transform:translateX(4px)}
.foot-contact a,.foot-contact p{display:flex;gap:12px;align-items:flex-start}
.foot-contact i{color:var(--amber);width:16px;margin-top:5px;text-align:center}
.pay{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.pay span{font-size:12.5px;font-weight:700;color:#e3eaf7;border:1px solid rgba(155,188,255,.18);background:rgba(155,188,255,.05);padding:7px 14px;border-radius:999px;display:inline-flex;align-items:center;gap:7px}
.copyright{border-top:1px solid rgba(155,188,255,.12);margin-top:52px;padding-top:22px;font-size:13px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;color:#6b7a96}

/* ---------- Keranjang (drawer) ---------- */
.overlay{position:fixed;inset:0;background:rgba(7,13,25,.6);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);z-index:99;opacity:0;pointer-events:none;transition:opacity .4s}
.overlay.show{opacity:1;pointer-events:auto}
.drawer{position:fixed;right:-100%;top:0;width:min(440px,100vw);height:100vh;height:100dvh;background:var(--paper);z-index:100;box-shadow:-30px 0 70px rgba(0,0,0,.3);transition:right .55s var(--ease);padding:22px;display:flex;flex-direction:column}
.drawer.open{right:0}
.drawer-head{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--line);padding-bottom:16px}
.close{border:0;background:#eaeff6;border-radius:50%;width:38px;height:38px;display:grid;place-items:center;font-weight:700;color:var(--navy);transition:background-color .25s,transform .4s var(--ease)}
.close:hover{background:var(--navy);color:#fff;transform:rotate(90deg)}
.cart-items{overflow-y:auto;flex:1;padding:8px 0}
.cart-item{display:grid;grid-template-columns:68px 1fr auto;gap:14px;padding:16px 0;border-bottom:1px solid var(--line);align-items:center}
.mini{width:68px;height:68px;background:#eaeff6;border-radius:8px;overflow:hidden;border:1px solid var(--line)}
.cart-item h4{font-family:var(--body);font-size:14px;font-weight:700;color:var(--navy);line-height:1.35;letter-spacing:0}
.cart-item small{color:var(--muted);font-size:12px}
.qty{display:flex;align-items:center;gap:9px;margin-top:8px}
.qty button{border:1px solid var(--line);background:#fff;border-radius:50%;width:30px;height:30px;font-weight:700;transition:background-color .25s,color .25s,border-color .25s,transform .3s var(--ease)}
.qty button:hover{background:var(--navy);color:#fff;border-color:var(--navy)}
.qty button:active{transform:scale(.9)}
.drawer-foot{border-top:1px solid var(--line);padding-top:18px;padding-bottom:env(safe-area-inset-bottom)}
.total{display:flex;justify-content:space-between;align-items:baseline;font-family:var(--display);font-size:19px;font-weight:700;color:var(--navy);margin-bottom:16px}
.empty{padding:64px 20px;text-align:center;color:var(--muted);font-size:14px}

/* ---------- Modal ---------- */
#checkout-modal,#tracking-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(7,13,25,.78);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);overflow-y:auto;-webkit-overflow-scrolling:touch}
#checkout-content,.modal-card{background:var(--paper);width:100%;max-width:520px;max-height:92vh;max-height:92dvh;border-radius:18px;box-shadow:0 40px 80px rgba(0,0,0,.45);margin:auto;display:flex;flex-direction:column;overflow:hidden;animation:focusIn .55s var(--ease)}
.checkout-header{padding:20px 24px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;background:#fff}
.checkout-header h3{font-size:16px;font-weight:700;color:var(--navy)}
.checkout-header h3 i{color:var(--blue)!important}
.checkout-body{padding:24px;overflow-y:auto;display:flex;flex-direction:column;gap:18px}
.fld label{font-size:13px;font-weight:700;color:var(--muted);display:block;margin-bottom:7px}
.fld input[type=text],.fld input[type=tel],.fld textarea,.fld select{width:100%;padding:13px 16px;border:1px solid var(--line);border-radius:10px;font-size:15px;outline:none;font-weight:500;background:#fff;color:var(--navy);transition:border-color .25s,box-shadow .25s}
.fld input:focus,.fld textarea:focus,.fld select:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(37,99,235,.14)}
.fld textarea{resize:none}
.fld.bank{background:var(--blue-soft);padding:16px;border-radius:12px;border:1px solid #cddcf8}
.fld.bank label{color:var(--blue-dark)}
.fld.bank select{background:#fff;font-weight:700;color:var(--navy);border-color:#cddcf8}
.fld.proof{background:#fff;padding:16px;border-radius:12px;border:1px dashed #c3cddc}
/* ---------- Detail produk (diperbesar) ---------- */
.product{cursor:pointer}
.pimg .zoom-hint{position:absolute;left:10px;bottom:10px;z-index:2;width:34px;height:34px;border-radius:50%;background:rgba(11,19,36,.84);color:var(--mint);display:grid;place-items:center;font-size:13px;opacity:0;transform:scale(.85);transition:opacity .3s,transform .4s var(--ease);pointer-events:none}
.product:hover .zoom-hint,.pimg:focus-visible .zoom-hint{opacity:1;transform:scale(1)}
@media (hover:none){.pimg .zoom-hint{opacity:1;transform:none}}
#product-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(7,13,25,.78);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
#product-modal.show{display:flex}
/* Desktop & tablet: gambar kiri (persegi, selalu sama besar), detail kanan */
.pm-card{position:relative;background:#fff;width:100%;max-width:900px;height:560px;height:min(560px,92dvh);border-radius:18px;box-shadow:0 40px 80px rgba(0,0,0,.45);margin:auto;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-template-rows:minmax(0,1fr);align-items:stretch;overflow:hidden;animation:focusIn .55s var(--ease)}
.pm-close{position:absolute;right:14px;top:14px;z-index:5;box-shadow:0 6px 16px -6px rgba(0,0,0,.35)}
.pm-img{position:relative;width:100%;height:100%;min-height:0;overflow:hidden;background:linear-gradient(160deg,#f6f8fc,#eaeff6)}
.pm-img img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;display:block}
.pm-info{padding:32px 30px 26px;overflow-y:auto;height:100%;min-height:0;display:flex;flex-direction:column;min-width:0}
.pm-info .brand{padding-right:44px}
.pm-info h3{font-size:24px;line-height:1.25;margin:6px 0 10px;font-weight:700;color:var(--navy);padding-right:44px}
.pm-price{font-family:var(--display);font-stretch:104%;font-size:28px;font-weight:700;color:var(--navy);letter-spacing:-.015em;margin:12px 0 4px}
.pm-desc-title{font-size:12.5px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin:18px 0 8px;padding-top:18px;border-top:1px solid var(--line)}
.pm-desc{font-size:14.5px;line-height:1.7;color:var(--ink);overflow-wrap:anywhere}
.pm-desc.pre{white-space:pre-line}
.pm-buy{margin-top:auto;padding-top:22px}
.pm-qty{display:flex;align-items:center;gap:12px;margin-bottom:14px;font-size:13px;font-weight:700;color:var(--muted)}
.pm-qty .qty{display:flex;align-items:center;gap:10px}
.pm-qty .qty span{min-width:28px;text-align:center;font-weight:800;color:var(--navy);font-size:15px}
.pm-actions{display:flex;gap:10px}
.pm-actions .btn{flex:1;padding:14px 16px;font-size:14px}
/* HP: satu kolom, gambar di atas (rasio tetap), detail di bawah, seluruh kartu bisa di-scroll */
@media (max-width:720px){
  #product-modal{padding:0;align-items:flex-end}
  .pm-card{display:flex;flex-direction:column;max-width:560px;height:auto;max-height:94vh;max-height:94dvh;border-radius:18px 18px 0 0;overflow-x:hidden;overflow-y:auto;-webkit-overflow-scrolling:touch;margin:0 auto}
  .pm-close{position:sticky;top:10px;right:auto;align-self:flex-end;flex:none;margin:10px 10px -46px 0}
  .pm-img{flex:none;height:auto;aspect-ratio:4/3}
  .pm-info{flex:none;overflow:visible;height:auto;padding:20px 18px calc(24px + env(safe-area-inset-bottom))}
  .pm-info h3{font-size:20px;padding-right:0}
  .pm-info .brand{padding-right:0}
  .pm-price{font-size:24px}
  .pm-buy{padding-top:18px}
}
.icon-btn{border:none;background:#eaeff6;width:36px;height:36px;border-radius:50%;font-weight:700;color:var(--navy);transition:background-color .25s,color .25s,transform .4s var(--ease)}
.icon-btn:hover{background:var(--navy);color:#fff;transform:rotate(90deg)}
.btn[style*="#0284c7"]{background:var(--blue-dark)!important}
#tracking-content [style*="#e0f2fe"]{background:var(--blue-soft)!important}

/* ---------- Chat ---------- */
.chat-widget-btn{position:fixed;bottom:calc(22px + env(safe-area-inset-bottom));right:22px;z-index:90;width:60px;height:60px;border-radius:50%;background:radial-gradient(circle at 35% 28%,#2a4a86,#0b1324 72%);color:var(--mint);display:grid;place-items:center;font-size:23px;box-shadow:0 18px 34px -10px rgba(11,19,36,.8),inset 0 0 0 1.5px rgba(155,188,255,.5);border:none;transition:transform .5s var(--ease)}
.chat-widget-btn:hover{transform:scale(1.08) rotate(-6deg)}
.chat-widget-btn:before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid var(--mint);animation:ping 2.6s infinite;opacity:0}
.chat-widget-btn:after{content:"";position:absolute;top:5px;right:5px;width:12px;height:12px;border-radius:50%;background:var(--green);border:2px solid #0b1324}
.chat-box{position:fixed;bottom:94px;right:16px;width:min(372px,calc(100vw - 32px));height:min(520px,70vh);background:#fff;border-radius:18px;box-shadow:0 34px 70px rgba(0,0,0,.34);z-index:95;display:none;flex-direction:column;overflow:hidden;border:1px solid var(--line);animation:focusIn .5s var(--ease)}
.chat-box>div:first-child{background:radial-gradient(260px 120px at 100% 0%,rgba(37,99,235,.6),transparent 70%),linear-gradient(135deg,#0b1324,#15223f)!important}
#chatBoxMessages{background:var(--paper)!important}
#chatBoxMessages [style*="#f0f9ff"]{background:#eaf1ff!important;color:#1e3a8a!important;border-color:#cddcf8!important}
#chatInputText:focus{border-color:var(--blue)!important;box-shadow:0 0 0 3px rgba(37,99,235,.14)}
.cb-b,.cb-n{display:flex;gap:8px;margin:0 0 9px;line-height:1.55}.cb-s{margin:0 0 8px;line-height:1.55}
.cb-b:before{content:"";flex:none;width:6px;height:6px;border-radius:50%;background:var(--blue);margin-top:7px}
.cb-n i{flex:none;width:20px;height:20px;border-radius:50%;background:var(--blue);color:#fff;font-weight:700;font-size:11px;line-height:20px;font-style:normal;text-align:center}
.cb-h{font-weight:800;font-size:13px;color:var(--blue-dark);margin:12px 0 9px;padding-bottom:6px;border-bottom:1px dashed #b5c9f2}.cb>.cb-h:first-child{margin-top:2px}
.cb-hr{border:0;border-top:1px dashed #cddcf8;margin:10px 0}.cb>:last-child{margin-bottom:0}.cb b{color:#1e3a8a}.cb code{background:var(--blue-soft);border-radius:5px;padding:1px 5px}
.cb{font-size:12.5px;overflow-wrap:break-word;text-align:left;line-height:1.55}.cb-pl{color:var(--blue-dark);font-weight:700;text-decoration:underline dotted;text-underline-offset:3px;cursor:pointer}.cb-pl:hover{color:var(--blue);text-decoration-style:solid}
.cb-table-wrap{overflow-x:auto;margin:10px 0 12px;border:1px solid #cddcf8;border-radius:10px;background:#fff}
.cb-table{width:100%;border-collapse:collapse;font-size:11.5px;min-width:260px}
.cb-table th{background:var(--blue-soft);color:var(--blue-dark);font-weight:800;text-align:left;padding:8px 10px;border-bottom:1px solid #cddcf8;white-space:nowrap}
.cb-table td{padding:8px 10px;border-bottom:1px solid #e5eaf2;vertical-align:top;color:#1e3a8a}
.cb-table tr:last-child td{border-bottom:0}
.cb-table td.cb-price{font-weight:800;white-space:nowrap;color:var(--blue-dark);font-family:var(--mono);font-size:11px}
.cb-card{background:#fff;border:1px solid #cddcf8;border-radius:10px;padding:10px 12px;margin:0 0 8px}
.cb-card .cb-card-t{font-weight:800;color:#1e3a8a;margin-bottom:4px}
.cb-card .cb-card-p{font-weight:800;color:var(--blue-dark);font-family:var(--mono);font-size:12px;margin-bottom:4px}
.cb-card .cb-card-d{font-size:11.5px;color:#5a6780;line-height:1.45}
.to-top{position:fixed;right:32px;bottom:calc(96px + env(safe-area-inset-bottom));z-index:80;width:40px;height:40px;border-radius:50%;border:1px solid var(--line);background:#fff;color:var(--navy);box-shadow:var(--shadow);display:grid;place-items:center;opacity:0;pointer-events:none;transform:translateY(10px);transition:opacity .35s,transform .45s var(--ease),background-color .25s,color .25s}
.to-top.show{opacity:1;pointer-events:auto;transform:none}
.to-top:hover{background:var(--navy);color:#fff}
.toast{position:fixed;left:50%;bottom:26px;transform:translate(-50%,24px);background:#0b1324;color:#fff;padding:13px 24px;border-radius:999px;opacity:0;pointer-events:none;transition:opacity .4s,transform .55s var(--ease);z-index:2000;font-size:13.5px;font-weight:600;box-shadow:0 20px 40px rgba(0,0,0,.35);max-width:92vw;text-align:center;border:1px solid rgba(155,188,255,.3)}
.toast.show{opacity:1;transform:translate(-50%,0)}

/* ---------- Simulator (fungsi tetap — hanya bingkai & warna latar) ---------- */
#simulation{background:radial-gradient(900px 460px at 50% 0%,rgba(37,99,235,.32),transparent 70%),linear-gradient(180deg,#080f1d,#060b16)!important;color:#fff}
.sim-shell{position:relative;border-radius:16px;overflow:hidden;background:rgba(8,15,29,.72);border:1px solid rgba(155,188,255,.2);box-shadow:0 44px 90px -34px rgba(0,0,0,.8)}
.sim-shell:before{inset:12px;--bk:rgba(155,188,255,.55);--bs:22px;--bw:2px;z-index:5}
.sim-bar{display:flex;align-items:center;justify-content:center;padding:18px;background:#060b16;border-bottom:1px solid rgba(155,188,255,.14)}
#simulation [class*="bg-[#050508]"]{background-color:#080f1d!important}
#simulation [class*="bg-[#0a0a12]"]{background-color:rgba(7,13,25,.88)!important}
.scenario-row{transition:border-color .3s,background-color .3s!important}
.scenario-row:hover{border-color:rgba(155,188,255,.45)!important}
.sim-bg{background-image:radial-gradient(circle at 50% 50%,rgba(37,99,235,.14) 0%,transparent 60%),linear-gradient(rgba(155,188,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(155,188,255,.035) 1px,transparent 1px);background-size:100% 100%,40px 40px,40px 40px;background-color:#080f1d}
.floorplan-room{position:absolute;border:1px solid rgba(110,160,255,.18);background:rgba(110,160,255,.025);box-shadow:inset 0 0 20px rgba(110,160,255,.025);border-radius:8px;display:flex;align-items:flex-start;justify-content:flex-start;padding:8px;color:rgba(110,160,255,.55);font-family:var(--mono);font-size:.65rem;text-transform:uppercase;letter-spacing:1px;pointer-events:none;overflow:hidden}
.scada-node{position:absolute;transform:translate(-50%,-50%);width:36px;height:36px;background:rgba(11,19,36,.92);border:1px solid rgba(255,255,255,.16);border-radius:50%;color:#8fa4bc;display:flex;align-items:center;justify-content:center;font-size:.9rem;z-index:20;transition:all .4s cubic-bezier(.175,.885,.32,1.275);box-shadow:inset 0 0 10px rgba(0,0,0,.5)}
.scada-node-label{position:absolute;bottom:-20px;font-family:var(--mono);font-size:.5rem;font-weight:700;white-space:nowrap;color:#c7d2fe;background:rgba(11,19,36,.95);padding:2px 5px;border-radius:4px;border:1px solid rgba(255,255,255,.15)}
.node-cyan{border-color:#6ea0ff;color:#6ea0ff;box-shadow:0 0 15px rgba(110,160,255,.4)}
.node-red{border-color:#ff003c;color:#ff003c;box-shadow:0 0 20px rgba(255,0,60,.6)}
.node-yellow{border-color:#ffea00;color:#ffea00;box-shadow:0 0 15px rgba(255,234,0,.4)}
.svg-line{stroke:rgba(255,255,255,.09);stroke-width:2;stroke-dasharray:4;transition:stroke .3s}
.svg-line.active-cyan{stroke:#6ea0ff;stroke-dasharray:0;filter:drop-shadow(0 0 4px #6ea0ff)}
.svg-line.active-red{stroke:#ff003c;stroke-dasharray:0;filter:drop-shadow(0 0 4px #ff003c)}
.svg-line.active-yellow{stroke:#ffea00;stroke-dasharray:0;filter:drop-shadow(0 0 4px #ffea00)}
.sim-actor{position:absolute;transform:translate(-50%,-50%);z-index:25;display:none;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;transition:left 2s ease-in-out,top 2s ease-in-out,opacity .5s;box-shadow:0 0 15px rgba(0,0,0,.8)}
.actor-blue{background:#6ea0ff;color:#000;font-size:13px}
.actor-red{background:#ff003c;color:#fff;font-size:13px}
.actor-cat{background:#ffea00;color:#000;font-size:13px}

/* ---------- Responsive ---------- */
@media(max-width:1100px){
  .products{grid-template-columns:repeat(3,1fr)}
  .categories{grid-template-columns:repeat(3,1fr)}
  .footgrid{grid-template-columns:1fr 1fr;gap:36px}
  .hero-grid{gap:30px}
  .hero-float.a{left:-4%}
  .hero-float.b{right:-2%}
}
@media(max-width:900px){
  .top-links{display:none}
  .subnav{display:none}
  .burger{display:inline-flex}
  .nav{flex-wrap:wrap;gap:12px;padding-top:12px;padding-bottom:12px;min-height:0}
  .logo{order:1;margin-right:auto}
  .nav-actions{order:2}
  .search{order:3;flex:1 1 100%;max-width:none}
  .cart .lbl{display:none}
  .hero{padding:44px 0 124px}
  .hero:after{inset:14px 14px 90px}
  .hero-grid{grid-template-columns:1fr}
  .hero-visual{max-width:380px;margin:16px auto 0}
  .hero p{font-size:16px}
  .usp-wrap{margin-top:-66px}
  .trust{grid-template-columns:repeat(2,1fr)}
  .trust div{padding:20px 18px}
  .trust div:nth-child(2n){border-right:0}
  .trust div:nth-child(-n+2){border-bottom:1px solid var(--line)}
  .section-title{grid-template-columns:1fr;gap:12px}
  .section-title p{grid-column:1}
  .cards-grid{grid-template-columns:1fr}
  .card-box{border-right:0;border-bottom:1px solid var(--line);padding:34px 8px}
  .card-box:last-child{border-bottom:0}
  .projects,.packages{grid-template-columns:1fr}
  .sale{grid-template-columns:1fr;padding:38px 26px}
  .sale .mock{display:none}
  section{padding:68px 0}
}
@media(max-width:760px){
  .products{grid-template-columns:repeat(2,1fr);gap:12px}
  .catalog{padding:16px;border-radius:14px}
  .toolbar{flex-direction:column;flex-wrap:nowrap;align-items:stretch;gap:12px}
  .chips,.sortbox{min-width:0}
  .chips{flex-wrap:nowrap;overflow-x:auto;margin:0 -16px;padding:0 16px 4px;scrollbar-width:none}
  .chips::-webkit-scrollbar{display:none}
  .sortbox{justify-content:space-between}
  .sortbox select{flex:1}
  .categories{grid-template-columns:repeat(3,1fr)}
  .cat{padding:20px 8px 18px}
  .cat .pic{width:50px;height:50px;font-size:19px;margin-bottom:12px}
  .cat span{display:none}
  .pbody{padding:13px}
  .product h3{font-size:14px;min-height:38px}
  .spec{font-size:12px;min-height:36px}
  .price{font-size:17px}
  .pfoot{gap:6px}
  .buy .lg{display:none}.buy .sm{display:inline}
  .footgrid{grid-template-columns:1fr 1fr}
  .footgrid>div:first-child,.footgrid>div:last-child{grid-column:1/-1}
  .copyright{flex-direction:column;text-align:center}
  .to-top{right:30px}
  .section-title{margin-bottom:32px;padding-bottom:20px}
  .article{grid-template-columns:84px 1fr}
  .article-img{min-height:0}
  .article-img i{font-size:1.9rem}
  .article-body{padding:18px 18px}
  .article-body h3{font-size:17px}
  .article:hover{transform:none}
}
@media(max-width:560px){
  .hero-float{display:none}
}
@media(max-width:480px){
  html body .container{padding-left:16px;padding-right:16px}
  .categories{grid-template-columns:repeat(2,1fr)}
  .trust div{padding:16px 12px;gap:10px}
  .trust i{flex-basis:38px;height:38px;font-size:15px}
  .trust b{font-size:13px}.trust small{font-size:11.5px}
  .hero .actions .btn{width:100%}
  .logo{font-size:18px}.logo img{height:34px}
  .chat-box{right:10px;bottom:88px}
  input,select,textarea{font-size:16px!important}
}
</style>
<!-- ===== REDESIGN "Violet Mono": satu aksen warna, hanya lapisan visual ===== -->
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
<style id="redesign">
/* Redesain: satu aksen biru + netral navy/abu. Hanya lapisan visual. */
:root{--navy:#0b1324;--ink:#1f2a44;--muted:#5a6780;--line:#e0e6ef;--bg:#f4f6fa;--paper:#fff;
--blue:#2563eb;--blue-dark:#1d4ed8;--blue-soft:#e6eeff;--mint:#9bbcff;
--amber:#6ea0ff;--amber-dark:#2563eb;--orange:#6ea0ff;
--display:'Sora','Archivo',system-ui,sans-serif;
--shadow:0 22px 44px -24px rgba(11,19,36,.30);--shadow-sm:0 1px 2px rgba(11,19,36,.05)}
body{background:var(--bg)}
:focus-visible{outline-color:#3b7bff}
::selection{background:#bcd2ff;color:var(--navy)}
h1,h2,h3,h4{letter-spacing:-.02em}

/* Bar atas, header, footer */
.top,footer{background:#070d19}
header,header.scrolled{background:rgba(11,19,36,.92)}
header:after{background:linear-gradient(90deg,var(--blue),#9bbcff)}
footer:before{background:linear-gradient(90deg,transparent,var(--blue),transparent)}
.search button{background:var(--blue);color:#fff}
.search button:hover{background:#3b7bff}
.badge{background:var(--blue);color:#fff}
.menu a:after{background:#9bbcff}

/* Tombol: satu gaya solid, tanpa kilau/animasi */
.btn{background:var(--blue);color:#fff}
.btn:before{display:none}
.btn:hover{background:var(--blue-dark);color:#fff;box-shadow:0 14px 26px -14px rgba(37,99,235,.85)}
.btn.light{background:#fff;color:var(--navy)}
.btn.light:hover{background:var(--blue-soft);color:var(--blue-dark);box-shadow:none}
.btn.outline{background:transparent;border:1.5px solid #c3cddc;color:var(--navy)}
.btn.outline:hover{background:var(--navy);border-color:var(--navy);color:#fff;box-shadow:none}
.btn.ghost{background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.28);color:#fff}
.btn.ghost:hover{background:rgba(255,255,255,.14);border-color:#fff;color:#fff;box-shadow:none}

/* Hero: gelap bersih, grid halus, tanpa aurora */
.hero{padding:84px 0 160px;animation:none;
 background:radial-gradient(820px 560px at 78% 40%,rgba(37,99,235,.38),transparent 62%),linear-gradient(180deg,#0b1324,#080f1d)}
.hero:before{background:linear-gradient(rgba(155,188,255,.05) 1px,transparent 1px) 0 0/56px 56px,linear-gradient(90deg,rgba(155,188,255,.05) 1px,transparent 1px) 0 0/56px 56px;
 -webkit-mask-image:linear-gradient(180deg,#000 25%,transparent 92%);mask-image:linear-gradient(180deg,#000 25%,transparent 92%)}
.hero:after{--bk:rgba(155,188,255,.26)}
h1{font-weight:700;letter-spacing:-.032em}
h1 span{color:#9bbcff}
.eyebrow{color:#cdd7ea;border-color:rgba(155,188,255,.28);background:rgba(155,188,255,.07)}
.lens:before{box-shadow:0 0 120px 10px rgba(37,99,235,.35),0 40px 80px rgba(0,0,0,.5)}

/* Kartu & komponen */
.trust{border-radius:16px}
.catalog{border-radius:20px;box-shadow:var(--shadow-sm)}
.categories{border-radius:16px}
.product,.package{border-radius:16px}
.product:hover,.package:hover{box-shadow:var(--shadow);border-color:#bfd0f5}
.product:hover{transform:translateY(-4px)}
.pimg:after{--bk:#fff}
.ribbon{background:var(--blue);color:#fff}
.stars{color:#e9a93a}
.buy:hover{background:var(--blue);color:#fff;box-shadow:0 12px 22px -12px rgba(37,99,235,.9)}
.section-title:after{background:var(--blue)}
.white{background:#fff}
.sale{background:radial-gradient(520px 320px at 100% 0%,rgba(37,99,235,.42),transparent 65%),linear-gradient(135deg,#0b1324,#15223f)}
.sale.alt{background:linear-gradient(120deg,#1d4ed8 0%,#2563eb 55%,#1e40af 100%)}
.package.hot{background:radial-gradient(420px 260px at 100% 0%,rgba(37,99,235,.38),transparent 65%),linear-gradient(165deg,#15223f,#0b1324);border-color:#0b1324}
.package.hot .amount{color:#9bbcff}
.package .pkg-flag{color:#9bbcff}

/* Chat: tenang, tanpa denyut */
.chat-widget-btn{background:radial-gradient(circle at 35% 28%,#2a4a86,#0b1324 72%);color:#cdd7ea}
.chat-widget-btn:before{display:none}

/* Simulator: aksen disamakan (merah/kuning tetap sebagai status) */
#simulation [class*="text-purple"]{color:#9bbcff!important}
#simulation [class*="bg-purple"]{background:rgba(37,99,235,.2)!important}
#simulation [class*="border-purple"]{border-color:rgba(155,188,255,.3)!important}
#simulation h4[class*="text-cyan"],.sim-bar [class*="text-cyan"]{color:#9bbcff}
#simulation h4{border-color:rgba(155,188,255,.3)!important}
#simulation .btn-run{background:rgba(37,99,235,.18)!important;color:#bcd2ff!important;border-color:rgba(110,160,255,.5)!important}
#simulation .btn-run:hover{background:var(--blue)!important;color:#fff!important}
.node-cyan{box-shadow:0 0 15px rgba(110,160,255,.4)}
</style>
<style id="rd-loader-css">
@keyframes ldPulse{0%,100%{transform:scale(1);filter:drop-shadow(0 0 0 rgba(110,160,255,0))}50%{transform:scale(1.1);filter:drop-shadow(0 0 22px rgba(110,160,255,.85))}}
@keyframes ldSpin{to{transform:rotate(360deg)}}
@keyframes ldBar{0%{transform:translateX(-100%)}100%{transform:translateX(250%)}}
@keyframes ldIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.rd-loader{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;background:radial-gradient(600px 400px at 50% 45%,rgba(37,99,235,.35),transparent 70%),#080f1d;color:#fff;transition:opacity .6s var(--ease),visibility .6s,clip-path .8s var(--ease);clip-path:circle(150% at 50% 50%)}
.rd-loader.mini{background:rgba(7,13,25,.72);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:2147483000;clip-path:none}
.rd-loader.done{opacity:0;visibility:hidden;pointer-events:none}
.rd-loader:not(.mini).done{clip-path:circle(0% at 50% 50%)}
.rd-ld-box{position:relative;width:132px;height:132px;display:grid;place-items:center}
.rd-ld-box:before,.rd-ld-box:after{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid transparent}
.rd-ld-box:before{border-top-color:#5b8cff;border-right-color:rgba(110,160,255,.35);animation:ldSpin 1.1s linear infinite}
.rd-ld-box:after{inset:12px;border-bottom-color:#a9c2ff;border-left-color:rgba(155,188,255,.3);animation:ldSpin 1.7s linear infinite reverse}
.rd-ld-box img{width:62px;height:62px;object-fit:contain;animation:ldPulse 1.6s ease-in-out infinite}
.rd-ld-txt{margin-top:22px;text-align:center;font:600 12.5px/1 var(--mono,monospace);letter-spacing:.22em;text-transform:uppercase;color:#a9c2ff;animation:ldIn .6s var(--ease) .2s both}
.rd-ld-bar{margin:14px auto 0;width:150px;height:3px;border-radius:3px;background:rgba(255,255,255,.12);overflow:hidden}
.rd-ld-bar i{display:block;width:40%;height:100%;border-radius:3px;background:linear-gradient(90deg,#2563eb,#a9c2ff);animation:ldBar 1.2s ease-in-out infinite}
.rd-ld-wrap{display:flex;flex-direction:column;align-items:center}
@media (prefers-reduced-motion:reduce){.rd-ld-box:before,.rd-ld-box:after,.rd-ld-box img,.rd-ld-bar i{animation-duration:3s!important}}
</style>
</head>
<body>
<div class="rd-loader" id="rdLoader" role="status" aria-live="polite"><div class="rd-ld-wrap"><div class="rd-ld-box"><img src="img/logo.png" alt="TEKUNIN"></div><div class="rd-ld-txt" id="rdLdTxt">Memuat TEKUNIN</div><div class="rd-ld-bar"><i></i></div></div></div>


<div class="top">
    <div class="container top-in">
        <div class="top-marquee">
            <div class="running-text">
                SELAMAT DATANG&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;<b>TEKUNIN CCTV</b>&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;Integrated IT &amp; Security System Solution
            </div>
        </div>
        <div class="top-links">
            <a href="https://wa.me/6281949550909" target="_blank"><i class="fa-brands fa-whatsapp"></i>0819-4955-0909</a>
            <a href="mailto:info@tekunin.com"><i class="fa-regular fa-envelope"></i>info@tekunin.com</a>
        </div>
    </div>
</div>

<header id="siteHeader">
    <div class="container nav">
        <a class="logo" href="#"><img src="img/logo.png" alt="Logo"> HOME</a>

        <form class="search" onsubmit="event.preventDefault(); goSearch();" role="search">
            <i class="fa-solid fa-magnifying-glass s-ico"></i>
            <input type="search" id="searchInput" placeholder="Cari CCTV, NVR, Access Control, Alarm..." autocomplete="off" oninput="onSearch(this.value)" onfocus="renderSuggest()" onkeydown="sgKey(event)" aria-label="Cari produk">
            <button type="submit" aria-label="Cari"><i class="fa-solid fa-arrow-right"></i></button>
            <div class="suggest" id="suggest"></div>
        </form>

        <div class="nav-actions">
            <!-- TOMBOL Lacak Pesanan -->
            <button class="cart" onclick="openTrackingModal()" title="Lacak Pesanan" aria-label="Lacak Pesanan"><i class="fa-solid fa-truck-fast"></i><span class="lbl">Lacak</span></button>
            <button class="cart" onclick="openCart()" aria-label="Keranjang"><i class="fa-solid fa-cart-shopping"></i><span class="lbl">Keranjang</span><span class="badge" id="cartCount">0</span></button>
            <button class="cart burger" onclick="toggleMenu()" aria-label="Menu" id="burgerBtn"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
    <div class="subnav">
        <div class="container">
            <nav class="menu" id="mainMenu">
                <a href="#produk">Produk</a>
                <a href="#solusi">Solusi</a>
                <a href="#simulation">Simulasi Rumah</a>
                <a href="#project">Project</a>
                <a href="#service">Service</a>
                <a href="#artikel">Artikel</a>
                <a href="#tentang">Tentang</a>
                <a href="#kontak">Kontak</a>
            </nav>
        </div>
    </div>
    <nav class="mobile-menu" id="mobileMenu">
        <a href="#produk" onclick="toggleMenu()"><i class="fa-solid fa-bag-shopping"></i>Produk</a>
        <a href="#solusi" onclick="toggleMenu()"><i class="fa-solid fa-shield-halved"></i>Solusi</a>
        <a href="#simulation" onclick="toggleMenu()"><i class="fa-solid fa-house-signal"></i>Simulasi Rumah</a>
        <a href="#project" onclick="toggleMenu()"><i class="fa-solid fa-diagram-project"></i>Project</a>
        <a href="#service" onclick="toggleMenu()"><i class="fa-solid fa-screwdriver-wrench"></i>Service</a>
        <a href="#artikel" onclick="toggleMenu()"><i class="fa-solid fa-newspaper"></i>Artikel</a>
        <a href="#tentang" onclick="toggleMenu()"><i class="fa-solid fa-circle-info"></i>Tentang</a>
        <a href="#kontak" onclick="toggleMenu()"><i class="fa-solid fa-phone"></i>Kontak</a>
    </nav>
</header>

<main>
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-copy">
                <div class="eyebrow">CCTV · SECURITY SYSTEM · PROFESIONAL</div>
                <h1>Lindungi aset Anda dengan <span>teknologi keamanan</span> terbaik.</h1>
                <p>Belanja CCTV, IP Camera, NVR, Access Control, Alarm, Network dan paket security system berkualitas tinggi dengan layanan instalasi profesional.</p>
                <div class="actions">
                    <a class="btn" href="#produk"><i class="fa-solid fa-bag-shopping"></i> Lihat Katalog Produk</a>
                    <a class="btn ghost" href="#kontak"><i class="fa-solid fa-headset"></i> Konsultasi Gratis</a>
                </div>
            </div>
            <div class="hero-visual" aria-hidden="true">
                <div class="lens" id="lens">
                    <svg class="lens-svg" viewBox="0 0 520 520" xmlns="http://www.w3.org/2000/svg" focusable="false">
                        <defs>
                            <radialGradient id="lnBody" cx="50%" cy="42%" r="62%"><stop offset="0" stop-color="#1a2a4a"/><stop offset=".6" stop-color="#101b33"/><stop offset="1" stop-color="#080f1d"/></radialGradient>
                            <linearGradient id="lnRim" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9bbcff" stop-opacity=".9"/><stop offset=".5" stop-color="#2c4a86" stop-opacity=".25"/><stop offset="1" stop-color="#6ea0ff" stop-opacity=".85"/></linearGradient>
                            <radialGradient id="lnPupil" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#02050c"/><stop offset=".55" stop-color="#0b1730"/><stop offset=".9" stop-color="#27458a"/><stop offset="1" stop-color="#9bbcff" stop-opacity=".6"/></radialGradient>
                            <linearGradient id="lnBlade" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2a3b5c"/><stop offset="1" stop-color="#0e182c"/></linearGradient>
                            <linearGradient id="lnSweep" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#9bbcff" stop-opacity="0"/><stop offset="1" stop-color="#9bbcff" stop-opacity=".38"/></linearGradient>
                            <clipPath id="lnClip"><circle cx="260" cy="260" r="186"/></clipPath>
                        </defs>
                        <!-- Bingkai viewfinder -->
                        <g fill="none" stroke="#9bbcff" stroke-opacity=".75" stroke-width="2" stroke-linecap="square">
                            <path d="M8 42V8h34"/><path d="M478 8h34v34"/><path d="M8 478v34h34"/><path d="M512 478v34h-34"/>
                        </g>
                        <!-- Rumah lensa -->
                        <circle cx="260" cy="260" r="250" fill="url(#lnBody)"/>
                        <circle cx="260" cy="260" r="249" fill="none" stroke="url(#lnRim)" stroke-width="2"/>
                        <g class="lens-ring r1"><circle cx="260" cy="260" r="228" fill="none" stroke="#9bbcff" stroke-opacity=".24" stroke-width="16" stroke-dasharray="2 7"/></g>
                        <g class="lens-ring r2"><circle cx="260" cy="260" r="204" fill="none" stroke="#fff" stroke-opacity=".18" stroke-width="6" stroke-dasharray="1 11"/></g>
                        <circle cx="260" cy="260" r="194" fill="#060b16" stroke="#9bbcff" stroke-opacity=".3" stroke-width="1.5"/>
                        <g clip-path="url(#lnClip)">
                            <circle cx="260" cy="260" r="186" fill="#04080f"/>
                            <!-- Mata: bergerak mengikuti kursor -->
                            <g class="lens-eye">
                                <circle cx="260" cy="260" r="112" fill="url(#lnPupil)"/>
                                <circle class="lens-iris-ring" cx="260" cy="260" r="86" fill="none" stroke="#9bbcff" stroke-opacity=".5" stroke-width="1.5" stroke-dasharray="3 6"/>
                                <circle cx="260" cy="260" r="40" fill="#02050c"/>
                                <circle cx="243" cy="243" r="9" fill="#fff" fill-opacity=".88"/>
                                <circle cx="277" cy="273" r="4" fill="#9bbcff" fill-opacity=".75"/>
                            </g>
                            <!-- Diafragma 8 bilah -->
                            <g class="lens-blades">
                                <g transform="translate(260 260) rotate(0)"><rect class="blade" style="--k:0" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(45)"><rect class="blade" style="--k:1" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(90)"><rect class="blade" style="--k:2" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(135)"><rect class="blade" style="--k:3" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(180)"><rect class="blade" style="--k:4" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(225)"><rect class="blade" style="--k:5" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(270)"><rect class="blade" style="--k:6" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                                <g transform="translate(260 260) rotate(315)"><rect class="blade" style="--k:7" x="0" y="-78" width="210" height="156" fill="url(#lnBlade)" stroke="#9bbcff" stroke-opacity=".18"/></g>
                            </g>
                            <!-- Sapuan pemindai -->
                            <g class="lens-sweep"><path d="M260 260 L260 74 A186 186 0 0 1 379.6 117.5 Z" fill="url(#lnSweep)"/></g>
                            <!-- Pantulan kaca -->
                            <g class="lens-glare" fill="none" stroke-linecap="round">
                                <path d="M118 206 A160 160 0 0 1 244 104" stroke="#fff" stroke-opacity=".3" stroke-width="3"/>
                                <path d="M398 322 A150 150 0 0 1 330 398" stroke="#6ea0ff" stroke-opacity=".55" stroke-width="3"/>
                                <circle cx="260" cy="260" r="176" stroke="#9bbcff" stroke-opacity=".14" stroke-width="1.5"/>
                            </g>
                        </g>
                    </svg>
                    <div class="hud hud-rec"><i></i>REC</div>
                    <div class="hud hud-cam">CAM 01</div>
                    <div class="hud hud-tc" id="lensTc">00:00:00:00</div>
                </div>
                <div class="hero-float a"><i class="fa-solid fa-shield-halved"></i><div>Produk Bergaransi<small>Resmi &amp; terjamin</small></div></div>
                <div class="hero-float b"><i class="fa-solid fa-mobile-screen"></i><div>Pantau dari HP<small>Kapan saja</small></div></div>
            </div>
        </div>
    </div>
</section>

<section class="usp-wrap" style="background:transparent">
    <div class="container">
        <div class="trust">
            <div class="reveal"><i class="fa-solid fa-certificate"></i><span><b>Produk Bergaransi</b><small>Resmi & terjamin</small></span></div>
            <div class="reveal" style="--d:.07s"><i class="fa-solid fa-user-gear"></i><span><b>Teknisi Ahli</b><small>Berpengalaman</small></span></div>
            <div class="reveal" style="--d:.14s"><i class="fa-solid fa-screwdriver-wrench"></i><span><b>Instalasi Rapi</b><small>Standar tinggi</small></span></div>
            <div class="reveal" style="--d:.21s"><i class="fa-solid fa-headset"></i><span><b>SLA 99%</b><small>After sales terbaik</small></span></div>
        </div>
    </div>
</section>

<section style="padding-bottom:40px">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Kategori</span>
            <h2>Kategori Produk</h2>
            <p>Temukan perangkat keamanan yang sesuai untuk kebutuhan Anda</p>
        </div>
        <div class="categories">
            <div class="cat reveal" onclick="setCat('CCTV', true)"><div class="pic"><i class="fa-solid fa-camera"></i></div><b>CCTV & IP Camera</b><span>Indoor · Outdoor</span><i class="cnt" data-cnt="CCTV" style="font-style:normal;display:none"></i></div>
            <div class="cat reveal" style="--d:.05s" onclick="setCat('NVR', true)"><div class="pic"><i class="fa-solid fa-server"></i></div><b>NVR / DVR</b><span>Recording & Storage</span><i class="cnt" data-cnt="NVR" style="font-style:normal;display:none"></i></div>
            <div class="cat reveal" style="--d:.1s" onclick="setCat('Access', true)"><div class="pic"><i class="fa-solid fa-fingerprint"></i></div><b>Access Control</b><span>Face · Finger · Card</span><i class="cnt" data-cnt="Access" style="font-style:normal;display:none"></i></div>
            <div class="cat reveal" style="--d:.15s" onclick="setCat('Alarm', true)"><div class="pic"><i class="fa-solid fa-bell"></i></div><b>Alarm System</b><span>Security & Sensor</span><i class="cnt" data-cnt="Alarm" style="font-style:normal;display:none"></i></div>
            <div class="cat reveal" style="--d:.2s" onclick="setCat('Doorbell', true)"><div class="pic"><i class="fa-solid fa-video"></i></div><b>Video Doorbell</b><span>Smart Door</span><i class="cnt" data-cnt="Doorbell" style="font-style:normal;display:none"></i></div>
            <div class="cat reveal" style="--d:.25s" onclick="setCat('Network', true)"><div class="pic"><i class="fa-solid fa-network-wired"></i></div><b>Network</b><span>PoE · Switch · HDD</span><i class="cnt" data-cnt="Network" style="font-style:normal;display:none"></i></div>
        </div>
    </div>
</section>

<section id="produk" style="padding-top:40px">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Katalog</span>
            <h2>Produk Unggulan</h2>
            <p>Pilih perangkat berkualitas, masukkan ke keranjang, dan kirim pesanan dengan mudah.</p>
        </div>
        <div class="catalog reveal">
            <div class="toolbar">
                <div class="chips" id="chips">
                    <button class="chip active" data-cat="Semua" onclick="setCat('Semua')">Semua</button>
                    <button class="chip" data-cat="CCTV" onclick="setCat('CCTV')">CCTV</button>
                    <button class="chip" data-cat="NVR/DVR" onclick="setCat('NVR/DVR')">NVR/DVR</button>
                    <button class="chip" data-cat="Access Control" onclick="setCat('Access Control')">Access Control</button>
                    <button class="chip" data-cat="Alarm" onclick="setCat('Alarm')">Alarm</button>
                    <button class="chip" data-cat="Video Doorbell" onclick="setCat('Video Doorbell')">Video Doorbell</button>
                    <button class="chip" data-cat="Network" onclick="setCat('Network')">Network Tools</button>
                </div>
                <label class="sortbox">Urutkan
                    <select id="sortSelect" onchange="onSort(this.value)">
                        <option value="default">Rekomendasi</option>
                        <option value="price-asc">Harga Terendah</option>
                        <option value="price-desc">Harga Tertinggi</option>
                        <option value="name">Nama A–Z</option>
                    </select>
                </label>
            </div>
            <div class="result-info" id="resultInfo"></div>
            <div id="products" class="products"></div>
        </div>
    </div>
</section>

<!-- SIMULATOR SECTION -->
<section id="simulation">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Interaktif</span>
            <h2 style="color:white">Simulasi Interaktif CCTV Rumah Tinggal</h2>
            <p style="color:#94a3b8">Jalankan skenario nyata pengawasan hunian pribadi untuk melihat cara kerja sistem keamanan pintar.</p>
        </div>

        <div class="reveal sim-shell">
            <div class="sim-bar">
                <div class="text-cyan-400 font-bold font-mono tracking-widest text-xs sm:text-sm flex items-center gap-2 text-center">
                    <i class="fa-solid fa-house"></i> SIMULATOR KEAMANAN SMART HOME CCTV (RUMAH)
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-5 bg-[#050508] min-h-[500px]">
                <div class="xl:col-span-3 relative sim-bg border-r border-white/5 overflow-hidden p-4 min-h-[380px]" id="map-container">
                    <div id="floorplan-layer" class="absolute inset-0 w-full h-full p-4"></div>
                    <svg class="absolute inset-0 w-full h-full pointer-events-none z-10" id="svg-layer"></svg>
                    <div id="nodes-layer" class="absolute inset-0 w-full h-full"></div>
                    
                    <div id="actors-layer" class="absolute inset-0 w-full h-full pointer-events-none">
                        <div id="actor-blue" class="sim-actor actor-blue"><i class="fa-solid fa-user"></i></div>
                        <div id="actor-red" class="sim-actor actor-red"><i class="fa-solid fa-user-ninja"></i></div>
                        <div id="actor-cat" class="sim-actor actor-cat"><i class="fa-solid fa-cat"></i></div>
                    </div>
                </div>

                <div class="xl:col-span-2 flex flex-col h-[500px]">
                    <div class="h-[55%] border-b border-white/5 p-4 flex flex-col">
                        <h4 class="text-xs font-mono text-cyan-400 mb-3 border-b border-cyan-400/30 pb-2"><i class="fa-solid fa-play mr-2"></i>PILIH SKENARIO</h4>
                        <div class="flex-grow overflow-y-auto space-y-2 pr-2" id="scenario-list" style="max-height:200px;"></div>
                    </div>
                    
                    <div class="h-[45%] p-4 bg-[#0a0a12]/80 flex flex-col">
                        <h4 class="text-xs font-mono text-purple-400 mb-2 flex justify-between items-center">
                            <span><i class="fa-solid fa-terminal mr-2"></i>LOG SISTEM REAL-TIME</span>
                            <span class="px-2 py-0.5 bg-purple-500/20 border border-purple-500/30 rounded text-[9px] animate-pulse text-purple-300">LIVE</span>
                        </h4>
                        <div id="terminal-logs" class="flex-grow overflow-y-auto font-mono text-xs space-y-1.5 pr-2 pb-2" style="max-height:150px; color:#c7d2fe;">
                            <div class="text-indigo-400/50">SYS_START: Sistem rumah siap. Pilih skenario di atas...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="sale reveal">
            <div>
                <div class="eyebrow">Konsultasi Paket</div>
                <h2>Belum tahu perangkat yang tepat untuk lokasi Anda?</h2>
                <p>Tim ahli kami siap membantu menghitung kebutuhan kamera, titik pasang, kapasitas storage, hingga instalasi jaringan terbaik.</p>
                <div style="margin-top:22px"><a class="btn light" href="#kontak">Minta Rekomendasi Gratis →</a></div>
            </div>
            <div class="mock"><i class="fa-solid fa-shield-halved"></i></div>
        </div>
    </div>
</section>

<section id="solusi" class="white">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Solusi</span>
            <h2>Solusi Keamanan Terintegrasi</h2>
            <p>Layanan lengkap mulai dari pengadaan perangkat, instalasi rapi, hingga maintenance berkala.</p>
        </div>
        <div class="cards-grid">
            <div class="card-box reveal"><div class="ic"><i class="fa-solid fa-house-chimney"></i></div><b>Perumahan & Rumah</b><p>Sistem CCTV pintar, smart doorbell, dan alarm monitoring langsung dari smartphone Anda.</p></div>
            <div class="card-box reveal" style="--d:.08s"><div class="ic"><i class="fa-solid fa-building"></i></div><b>Kantor & Retail</b><p>IP camera beresolusi tinggi, access control pintu, NVR handal, dan jaringan PoE terpusat.</p></div>
            <div class="card-box reveal" style="--d:.16s"><div class="ic"><i class="fa-solid fa-industry"></i></div><b>Gudang & Industri</b><p>Cakupan area luas, ketahanan malam hari superior, storage besar, dan support pemeliharaan rutin.</p></div>
        </div>
    </div>
</section>

<section id="project">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Portfolio</span>
            <h2 style="color:white">Project Portfolio</h2>
            <p style="color:#94a3b8">Beberapa dokumentasi instalasi sistem keamanan yang telah kami selesaikan.</p>
        </div>
        <div class="projects">
            <div class="project reveal"><div class="project-img"><i class="fa-solid fa-building fa-3x"></i></div><div class="project-body"><small>Perkantoran</small><h3>Integrated CCTV & Access</h3><p>IP Camera · NVR · Access Control System</p></div></div>
            <div class="project reveal" style="--d:.08s"><div class="project-img"><i class="fa-solid fa-warehouse fa-3x"></i></div><div class="project-body"><small>Industri</small><h3>Warehouse Surveillance</h3><p>High Coverage CCTV · Network · Storage</p></div></div>
            <div class="project reveal" style="--d:.16s"><div class="project-img"><i class="fa-solid fa-store fa-3x"></i></div><div class="project-body"><small>Retail</small><h3>Store Security System</h3><p>CCTV Setup · Remote Monitoring</p></div></div>
        </div>
    </div>
</section>

<section id="service">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Maintenance</span>
            <h2>Service & Maintenance</h2>
            <p>Pastikan sistem keamanan Anda selalu berfungsi optimal dengan memilih paket perawatan rutin.</p>
        </div>
        <div class="packages">
            <div class="package reveal">
                <h3>Basic</h3>
                <div class="amount">Rp500rb<span class="per">/bulan</span></div>
                <ul>
                    <li>Pengecekan perangkat berkala</li>
                    <li>Pembersihan lensa kamera</li>
                    <li>Optimasi sistem rekaman</li>
                    <li>Laporan kondisi perangkat</li>
                </ul>
                <a class="btn outline" href="https://wa.me/6281949550909?text=Halo%20Tekunin%2C%20Saya%20tertarik%20berlangganan%20%22Maintenance%20Bulanan%22%20dengan%20paket%20%22Basic%22" style="width:100%; text-align:center; display:block" target="_blank">Pilih Paket</a>
            </div>
            <div class="package hot reveal" style="--d:.08s">
                <small class="pkg-flag"><i class="fa-solid fa-fire"></i> Paling Populer</small>
                <h3 style="margin-top:6px">Standard</h3>
                <div class="amount">Rp750rb<span class="per">/bulan</span></div>
                <ul>
                    <li>Semua fasilitas Paket Basic</li>
                    <li>Backup konfigurasi sistem</li>
                    <li>Pembaruan firmware berkala</li>
                    <li>Prioritas layanan support</li>
                </ul>
                <a class="btn" href="https://wa.me/6281949550909?text=Halo%20Tekunin%2C%20Saya%20tertarik%20berlangganan%20%22Maintenance%20Bulanan%22%20dengan%20paket%20%22Standart%22" style="width:100%; text-align:center; display:block" target="_blank">Pilih Paket</a>
            </div>
            <div class="package reveal" style="--d:.16s">
                <h3>Premium</h3>
                <div class="amount">Custom</div>
                <ul>
                    <li>Semua fasilitas Paket Standard</li>
                    <li>Monitoring performa 24/7</li>
                    <li>Dedicated support technician</li>
                    <li>Prioritas penggantian unit</li>
                </ul>
                <a class="btn outline" href="https://wa.me/6281949550909?text=Halo%20Tekunin%2C%20Saya%20tertarik%20berlangganan%20%22Maintenance%20Bulanan%22%20dengan%20paket%20%22Premium%22"  style="width:100%; text-align:center; display:block" target="_blank">Pilih Paket</a>
            </div>
        </div>
    </div>
</section>

<section id="artikel" class="white">
    <div class="container">
        <div class="section-title reveal">
            <span class="kicker">Edukasi</span>
            <h2>Artikel & Edukasi</h2>
            <p>Panduan dan tips seputar pemilihan sistem keamanan terbaik untuk properti Anda.</p>
        </div>
        <div class="article-grid">
            <article class="article reveal">
                <div class="article-img"><i class="fa-solid fa-lightbulb fa-3x"></i></div>
                <div class="article-body">
                    <small>Tips CCTV</small>
                    <h3>Berapa jumlah titik CCTV yang ideal untuk gudang?</h3>
                    <p>Pahami perhitungan area coverage, titik buta (*blind spot*), dan pencahayaan malam.</p>
                </div>
            </article>
            <article class="article reveal" style="--d:.08s">
                <div class="article-img"><i class="fa-solid fa-microchip fa-3x"></i></div>
                <div class="article-body">
                    <small>Teknologi</small>
                    <h3>Tips memilih kamera CCTV berfitur PoE untuk kantor</h3>
                    <p>Kelebihan resolusi tinggi, kemudahan kabel tunggal, dan akses pemantauan jarak jauh.</p>
                </div>
            </article>
            <article class="article reveal" style="--d:.16s">
                <div class="article-img"><i class="fa-solid fa-shield-halved fa-3x"></i></div>
                <div class="article-body">
                    <small>Security</small>
                    <h3>Mengapa integrated security system lebih efektif?</h3>
                    <p>Menggabungkan pengawasan CCTV, akses pintu otomatis, dan alarm dalam satu kontrol.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section id="tentang">
    <div class="container">
        <div class="sale alt reveal">
            <div>
                <h2 style="color:white">Belanja perangkat, dapatkan solusi menyeluruh.</h2>
                <p style="color:#e0f2fe">TEKUNIN hadir bukan sekadar toko perangkat keamanan, melainkan solusi menyeluruh dari tahap konsultasi, instalasi, hingga after sales dengan tingkat SLA 99%.</p>
            </div>
            <div style="text-align:center"><a class="btn light" href="https://wa.me/6281949550909" target="_blank"><i class="fa-brands fa-whatsapp"></i> Konsultasi Gratis Sekarang</a></div>
        </div>
    </div>
</section>
</main>

<footer id="kontak">
    <div class="container">
        <div class="footgrid">
            <div>
                <div class="logo" style="margin-bottom:14px"><img src="img/logo.png" alt="Logo"> TEKUNIN HOME</div>
                <p>CV. Teknologi Untuk Indonesia. Spesialis penyedia sistem keamanan yang professional dan proper</p>
                <div class="pay">
                    <span><i class="fa-solid fa-building-columns"></i> BCA</span>
                    <span><i class="fa-solid fa-building-columns"></i> Mandiri</span>
                    <span><i class="fa-solid fa-money-bill-wave"></i> Tunai</span>
                </div>
            </div>
            <div>
                <h3>Belanja</h3>
                <a href="#produk" onclick="setCat('CCTV')">CCTV & IP Camera</a>
                <a href="#produk" onclick="setCat('NVR')">NVR / DVR</a>
                <a href="#produk" onclick="setCat('Access')">Access Control</a>
                <a href="#produk" onclick="setCat('Alarm')">Alarm System</a>
            </div>
            <div>
                <h3>Layanan</h3>
                <a href="#service">Maintenance</a>
                <a href="#project">Project Portfolio</a>
                <a href="#kontak">Request Survey</a>
                <a href="#artikel">Artikel & Edukasi</a>
            </div>
            <div class="foot-contact">
                <h3>Kontak Kami</h3>
                <a href="https://wa.me/6281949550909" target="_blank"><i class="fa-brands fa-whatsapp"></i>0819-4955-0909</a>
                <a href="mailto:info@tekunin.com" target="_blank"><i class="fa-regular fa-envelope"></i>info@tekunin.com</a>
                <a href="mailto:support@tekunin.com" target="_blank"><i class="fa-solid fa-headset"></i>support@tekunin.com</a>
                <p><i class="fa-solid fa-location-dot"></i><span>Jl Terusan H Nawi Malik No 43A, Depok, Jawa Barat</span></p>
            </div>
        </div>
        <div class="copyright">
            <span>© 2026 TEKUNIN CCTV | Hak Cipta Dilindungi.</span>
            <span> | Security System · Intergrated IT |</span>
        </div>
    </div>
</footer>

<!-- Drawer Keranjang -->
<div class="overlay" id="drawerOverlay" onclick="closeCart()"></div>
<div class="drawer" id="drawer">
    <div class="drawer-head"><h3 style="font-weight:800;color:var(--navy);font-size:16px;"><i class="fa-solid fa-cart-shopping" style="color:var(--blue);margin-right:8px"></i>Keranjang Belanja</h3><button class="close" onclick="closeCart()" aria-label="Tutup">✕</button></div>
    <div class="cart-items" id="cartItems"></div>
    <div class="drawer-foot">
        <div class="total"><span>Total Belanja</span><span id="cartTotal">Rp0</span></div>
        <button class="btn" style="width:100%;justify-content:center;padding:14px" onclick="openCheckoutModal()">Proses Checkout →</button>
    </div>
</div>

<!-- Checkout Modal -->
<div id="checkout-modal">
    <div id="checkout-content">
        <div class="checkout-header">
            <h3><i class="fa-solid fa-truck-fast text-sky-600 mr-2"></i> Detail Pemesan</h3>
            <button onclick="closeCheckoutModal()" class="icon-btn" aria-label="Tutup">✕</button>
        </div>
        <div class="checkout-body">
            <div class="fld">
                <label>Nama / Perusahaan</label>
                <input type="text" id="buyer-name" placeholder="">
            </div>
            <div class="fld">
                <label>Alamat Pemasangan Lengkap</label>
                <textarea id="buyer-address" rows="3" placeholder="Jl. Sudirman No 1, RT 01/RW 02..."></textarea>
            </div>
            <div class="fld">
                <label>No. HP / WhatsApp Aktif</label>
                <input type="tel" id="buyer-phone" placeholder="08123456789">
            </div>

            <div class="fld bank">
                <label><i class="fa-solid fa-building-columns mr-1"></i> Metode Pembayaran (Bank)</label>
               <select id="buyer-bank">
    <option value="BCA (4730647951 A/N Sterlyght A. Moorrees)">BCA (4730647951 A/N Sterlyght A. Moorrees)</option>
    <option value="Mandiri (1570010288620 A/N Sterlyght A. Moorrees)">Mandiri (1570010288620 A/N Sterlyght A. Moorrees)</option>
    <option value="TUNAI / BAYAR DI TEMPAT">TUNAI / BAYAR DI TEMPAT</option>
</select>
            </div>

            <div class="fld proof">
                <label><i class="fa-solid fa-receipt mr-1"></i> Unggah Bukti Transfer</label>
                <input type="file" id="buyer-proof" accept="image/jpeg,image/png,image/webp,image/*" style="width: 100%; font-size: 12px; color: var(--muted); font-weight:500;">
                <p style="font-size:10px; color:#94a3b8; margin-top:4px;">*Gunakan format JPG/PNG. Hindari HEIC (iPhone). Foto akan dikompres otomatis.</p>
                <small style="display:block; margin-top:3px; font-size:11px; color:var(--muted)">*Opsional. Dapat dikirimkan nanti via WhatsApp ke Admin.</small>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 4px;">
                <button onclick="closeCheckoutModal()" class="btn outline" style="flex:1; padding: 13px 16px;">Kembali</button>
                <button onclick="submitOrder()" class="btn" style="flex:2; padding: 13px 18px; font-size:14px">Kirim Pesanan <i class="fa-solid fa-paper-plane ml-1"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Detail Produk Modal -->
<div id="product-modal" onclick="if(event.target===this)closeProductModal()" role="dialog" aria-modal="true" aria-labelledby="pmName">
    <div class="pm-card">
        <button type="button" class="icon-btn pm-close" onclick="closeProductModal()" aria-label="Tutup">✕</button>
        <div class="pm-img" id="pmImg"></div>
        <div class="pm-info" id="pmInfo"></div>
    </div>
</div>

<!-- Tracking / Lacak Pesanan Modal -->
<div id="tracking-modal">
    <div class="modal-card">
        <div class="checkout-header">
            <h3><i class="fa-solid fa-map-location-dot text-sky-600 mr-2"></i> Lacak Pesanan Saya</h3>
            <button onclick="closeTrackingModal()" class="icon-btn" aria-label="Tutup">✕</button>
        </div>
        <div style="padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px;" id="tracking-content">
            <!-- Data riwayat timeline pesanan akan dirender di sini via JS -->
        </div>
    </div>
</div>

<!-- Responsive Chat Widget -->
<button class="to-top" id="toTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Kembali ke atas"><i class="fa-solid fa-chevron-up"></i></button>
<button class="chat-widget-btn" onclick="toggleChatWidget()" aria-label="Chat CS"><i class="fa-solid fa-comments"></i></button>
<div class="chat-box" id="chatWidgetBox">
    <div style="background:linear-gradient(135deg,#0f172a,#1e293b); color:white; padding:14px 16px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <b style="font-size:13px; display:flex; align-items:center; gap:6px;"><div style="width:8px;height:8px;background:#10b981;border-radius:50%"></div> CS Tekunin CCTV</b>
            <span id="chatSessionBadge" style="font-size:10px; color:#94a3b8; margin-top:2px; display:block">Sesi: Memuat...</span>
        </div>
        <button onclick="toggleChatWidget()" style="background:none; border:none; color:white; font-size:16px;" aria-label="Tutup chat"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="chatBoxMessages" style="flex:1; padding:14px; overflow-y:auto; font-size:12px; background:#f8fafc; display:flex; flex-direction:column; gap:10px;"></div>
    <div style="padding:10px 12px; background:white; border-top:1px solid var(--line); display:flex; gap:6px;">
        <input type="text" id="chatInputText" placeholder="Ketik pertanyaan Anda..." style="flex:1; border:1px solid var(--line); border-radius:10px; padding:10px 12px; font-size:12px; outline:none; background:var(--bg);" onkeydown="if(event.key==='Enter') sendChatMessage()">
        <button onclick="sendChatMessage()" class="btn" style="padding:10px 14px;" aria-label="Kirim"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let products = [];
let cart = JSON.parse(localStorage.getItem("tekuninCart") || "[]");
let currentCat = "Semua";

let chatSession = sessionStorage.getItem('tk_chat_session');
if(!chatSession) {
    chatSession = 'CUST-' + Math.random().toString(36).substr(2, 6).toUpperCase();
    sessionStorage.setItem('tk_chat_session', chatSession);
}
document.getElementById('chatSessionBadge').textContent = 'ID Sesi: ' + chatSession;

const rupiah = n => new Intl.NumberFormat("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0}).format(n);

// ---------- Katalog: state & helper ----------
let loaded = false, searchTerm = "", sortMode = "default", lastSig = "";
const PH = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="#eef2f7"/><g fill="none" stroke="#94a3b8" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"><rect x="50" y="70" width="70" height="50" rx="10"/><path d="M120 85l30-15v60l-30-15"/></g></svg>');
const GROUP_ICONS = { cctv:'fa-camera', nvr:'fa-server', access:'fa-fingerprint', alarm:'fa-bell', doorbell:'fa-video', network:'fa-network-wired' };
const GROUP_ORDER = ['cctv','nvr','access','alarm','doorbell','network'];

// Samakan penamaan grup (mis. "NVR" = "NVR/DVR", "Access" = "Access Control")
function canon(s) {
    s = String(s || '').toLowerCase().trim();
    if (s.includes('nvr') || s.includes('dvr')) return 'nvr';
    if (s.includes('access')) return 'access';
    if (s.includes('alarm')) return 'alarm';
    if (s.includes('doorbell')) return 'doorbell';
    if (s.includes('network')) return 'network';
    if (s.includes('cctv') || s.includes('camera')) return 'cctv';
    return s;
}
const groupIcon = g => GROUP_ICONS[canon(g)] || 'fa-box';

async function loadProducts() {
    try {
        let res = await fetch('admin.php?api=get_data', { cache: 'no-store' });
        let json = await res.json();
        if(json.success && json.data) {
            const next = json.data.products || [];
            const sig = JSON.stringify(next);
            const first = !loaded;
            products = next;
            loaded = true;
            if (sig !== lastSig || first) {
                lastSig = sig;
                updateChips();
                renderProducts(first);
            }
        }
    } catch(e) {
        console.error("Gagal memuat produk:", e);
        if (!loaded) { loaded = true; renderProducts(); }
    }
}

// Chip dinamis: tambah grup baru dari database + jumlah produk
function updateChips() {
    const box = document.getElementById('chips');
    if (!box) return;
    const counts = {};
    products.forEach(p => { const k = canon(p.group_name); counts[k] = (counts[k] || 0) + 1; });

    // grup baru yang belum punya chip
    const known = new Set([...box.querySelectorAll('.chip')].map(c => canon(c.dataset.cat)).concat(['semua']));
    const added = new Set();
    products.forEach(p => {
        const k = canon(p.group_name);
        if (!known.has(k) && !added.has(k) && p.group_name) {
            added.add(k);
            const b = document.createElement('button');
            b.className = 'chip';
            b.dataset.cat = p.group_name;
            b.textContent = p.group_name;
            b.onclick = () => setCat(p.group_name);
            box.appendChild(b);
        }
    });

    box.querySelectorAll('.chip').forEach(c => {
        c.querySelectorAll('.n').forEach(n => n.remove());
        const k = canon(c.dataset.cat);
        const n = k === 'semua' ? products.length : (counts[k] || 0);
        const s = document.createElement('span');
        s.className = 'n'; s.textContent = n;
        c.appendChild(s);
    });

    document.querySelectorAll('[data-cnt]').forEach(el => {
        const n = counts[canon(el.dataset.cnt)] || 0;
        el.textContent = n + ' produk';
        el.style.display = n ? 'inline-block' : 'none';
    });
    syncChips();
}

function syncChips() {
    document.querySelectorAll('.chip').forEach(x => {
        x.classList.toggle('active', canon(x.dataset.cat) === canon(currentCat));
    });
}

function productCard(p, i, anim, extra) {
    return `
        <article class="product ${anim ? 'anim' : ''}" data-pid="${String(p.id).replace(/"/g,'&quot;')}" style="--i:${Math.min(i, 12)}">
            <div class="pimg" tabindex="0" role="button" aria-label="Lihat detail ${String(p.name || '').replace(/"/g,'&quot;')}">
                <span class="zoom-hint"><i class="fa-solid fa-expand"></i></span>
                ${p.badge ? `<span class="ribbon">${p.badge}</span>` : ""}
                <span class="gtag"><i class="fa-solid ${groupIcon(p.group_name)}"></i> ${p.group_name || 'Produk'}</span>
                <img src="${p.image}" alt="${String(p.name || '').replace(/"/g,'&quot;')}" loading="lazy" onerror="this.onerror=null;this.src=PH">
            </div>
            <div class="pbody">
                <div class="brand">${hl(p.group_name)}</div>
                <h3>${hl(p.name)}</h3>
                <div class="spec-box">
                    <div class="spec">${hl(p.description || '')}</div>
                    <button type="button" class="spec-more" onclick="toggleSpec(this)" aria-expanded="false"><span>Lebih Lanjut</span><i class="fa-solid fa-chevron-down"></i></button>
                </div>
                <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i> <span style="color:var(--muted); font-size:11px; margin-left:4px">(4.9)</span></div>
                <div class="price">${rupiah(p.price)}</div>
                <div class="pfoot">
                    <button class="buy" onclick="buyNow('${p.id}')"><span class="lg">Beli Sekarang</span><span class="sm">Beli</span></button>
                    <button class="add" onclick="addCart('${p.id}')" aria-label="Tambah ke keranjang"><i class="fa-solid fa-cart-plus"></i></button>
                </div>
                ${extra || ''}
            </div>
        </article>`;
}

function renderProducts(anim = false) {
    const box = document.getElementById("products");
    const info = document.getElementById("resultInfo");
    if (!box) return;

    if (!loaded) {
        box.innerHTML = Array(8).fill('<div class="skel"><div class="a"></div><div class="b"></div><div class="b" style="width:60%"></div><div class="c"></div></div>').join('');
        if (info) info.innerHTML = '';
        return;
    }

    const q = searchTerm.trim();
    let list = products.filter(p => currentCat === "Semua" || canon(p.group_name) === canon(currentCat));
    searchTier = 1;
    if (q) { const r = searchProducts(q, list); list = r.list; searchTier = r.tier; }

    if (sortMode === 'price-asc') list.sort((a, b) => a.price - b.price);
    else if (sortMode === 'price-desc') list.sort((a, b) => b.price - a.price);
    else if (sortMode === 'name') list.sort((a, b) => String(a.name).localeCompare(String(b.name)));

    // Tampilan "Semua": satu produk perwakilan dari setiap grup
    const showcase = currentCat === "Semua" && !q;
    let html = "", infoHtml = "";

    if (showcase) {
        const groups = new Map();
        list.forEach(p => {
            const k = canon(p.group_name);
            if (!groups.has(k)) groups.set(k, { name: p.group_name, items: [] });
            groups.get(k).items.push(p);
        });
        const keys = [...groups.keys()].sort((a, b) => {
            const ia = GROUP_ORDER.indexOf(a), ib = GROUP_ORDER.indexOf(b);
            return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
        });
        html = keys.map((k, i) => {
            const g = groups.get(k);
            const more = `<button class="more" data-g="${encodeURIComponent(g.name)}" onclick="setCat(decodeURIComponent(this.dataset.g), true)"><span>Lihat ${g.items.length} produk ${g.name}</span><i class="fa-solid fa-arrow-right"></i></button>`;
            return productCard(g.items[0], i, anim, more);
        }).join("");
        infoHtml = `<span><b>${keys.length}</b> kategori · satu pilihan terbaik dari tiap kategori</span>`;
    } else {
        html = list.map((p, i) => productCard(p, i, anim)).join("");
        infoHtml = `<span>Menampilkan <b>${list.length}</b> produk${currentCat !== 'Semua' ? ` di <b>${currentCat}</b>` : ''}${q ? ` untuk “<b>${searchTerm.replace(/</g,'&lt;')}</b>”` : ''}${q && searchTier === 3 ? ' · <i>tidak ada yang persis sama, ini hasil yang paling mirip</i>' : ''}</span>` +
                   ((currentCat !== 'Semua' || q) ? `<button onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>` : '');
    }

    box.innerHTML = html || `<div class="noresult"><i class="fa-solid fa-box-open"></i>${q ? 'Produk yang Anda cari tidak ditemukan. Coba kata kunci lain.' : 'Belum ada produk yang dipublish di kategori ini.'}</div>`;
    if (info) info.innerHTML = infoHtml;
    requestAnimationFrame(checkSpecOverflow);
}

// Tampilkan tombol "Lebih Lanjut" hanya jika deskripsi terpotong (lebih dari 2 baris)
function checkSpecOverflow() {
    document.querySelectorAll('.spec-box').forEach(box => {
        const spec = box.querySelector('.spec');
        if (!spec || box.classList.contains('is-open')) return;
        box.classList.toggle('is-long', spec.scrollHeight > spec.clientHeight + 1);
    });
}

function toggleSpec(btn) {
    const box = btn.closest('.spec-box');
    const spec = box.querySelector('.spec');
    const open = !box.classList.contains('is-open');
    box.classList.toggle('is-open', open);
    spec.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.querySelector('span').textContent = open ? 'Tutup' : 'Lebih Lanjut';
}

(function () {
    let t;
    window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(checkSpecOverflow, 150); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(checkSpecOverflow);
})();

function setCat(cat, scroll = false) {
    currentCat = cat;
    renderProducts(true);
    syncChips();
    if (scroll) {
        const el = document.getElementById('produk');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function resetFilter() {
    searchTerm = ""; const s = document.getElementById('searchInput'); if (s) s.value = "";
    currentCat = "Semua";
    renderProducts(true); syncChips();
}

// ---------- Pencarian cerdas ----------
let searchTier = 1, sgIndex = -1;
const SECTIONS = [
    { id:'service',    label:'Service & Maintenance',            icon:'fa-screwdriver-wrench', keys:['service','servis','maintenance','perawatan','langganan','paket basic','paket standard','paket premium','basic','standard','standart','premium','bulanan'] },
    { id:'simulation', label:'Simulasi Interaktif CCTV Rumah',   icon:'fa-house-signal',       keys:['simulasi','simulator','skenario','smart home','kurir','penyusup','demo'] },
    { id:'project',    label:'Project Portfolio',                icon:'fa-diagram-project',    keys:['project','proyek','portfolio','portofolio','dokumentasi'] },
    { id:'solusi',     label:'Solusi Keamanan Terintegrasi',     icon:'fa-shield-halved',      keys:['solusi','perumahan','rumah','kantor','retail','gudang','industri','integrasi','terintegrasi'] },
    { id:'artikel',    label:'Artikel & Edukasi',                icon:'fa-newspaper',          keys:['artikel','edukasi','tips','panduan','blog'] },
    { id:'tentang',    label:'Tentang Tekunin',                  icon:'fa-circle-info',        keys:['tentang','about','sla','after sales','konsultasi'] },
    { id:'kontak',     label:'Kontak & Alamat',                  icon:'fa-phone',              keys:['kontak','contact','alamat','telepon','telp','whatsapp','wa','email','lokasi','depok','survey','survei'] }
];

function tokens(q) { return String(q || '').toLowerCase().trim().split(/\s+/).filter(Boolean); }

// 3 tingkat: (1) frasa persis, (2) semua kata, (3) salah satu kata
function searchProducts(q, base) {
    const t = tokens(q), phrase = t.join(' '), src = base || products;
    if (!t.length) return { list: src.slice(), tier: 1 };
    const info = p => {
        const n = String(p.name || '').toLowerCase(), g = String(p.group_name || '').toLowerCase(), d = String(p.description || '').toLowerCase();
        return { n, g, d, hay: n + ' ' + g + ' ' + d };
    };
    const score = (p, x) => {
        let sc = 0;
        if (x.n.includes(phrase)) sc += 100;
        if (x.g.includes(phrase) || (phrase.length >= 3 && canon(x.g) === canon(phrase))) sc += 60;
        if (x.d.includes(phrase)) sc += 30;
        t.forEach(k => { if (x.n.includes(k)) sc += 10; if (x.g.includes(k)) sc += 6; if (x.d.includes(k)) sc += 3; });
        return sc;
    };
    const rows = src.map(p => { const x = info(p); return { p, x, sc: score(p, x) }; });
    const byScore = arr => arr.sort((a, b) => b.sc - a.sc).map(r => r.p);

    let r = rows.filter(r => r.x.hay.includes(phrase) || (phrase.length >= 3 && canon(r.x.g) === canon(phrase)));
    if (r.length) return { list: byScore(r), tier: 1 };
    r = rows.filter(r => t.every(k => r.x.hay.includes(k)));
    if (r.length) return { list: byScore(r), tier: 2 };
    r = rows.filter(r => t.some(k => r.x.hay.includes(k)));
    return { list: byScore(r), tier: 3 };
}

function findSections(q) {
    const t = tokens(q), phrase = t.join(' ');
    if (phrase.length < 2) return [];
    return SECTIONS.filter(sec => sec.label.toLowerCase().includes(phrase) || sec.keys.some(k => k === phrase || (phrase.length >= 3 && (k.includes(phrase) || phrase.includes(k)))) || t.some(w => w.length >= 3 && sec.keys.includes(w)));
}

// Sorot kata yang dicari pada teks
function hl(s) {
    s = String(s == null ? '' : s);
    const q = searchTerm.trim();
    if (!q || s.indexOf('<') >= 0) return s;
    const t = [...new Set(tokens(q))].map(x => x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    if (!t.length) return s;
    return s.replace(new RegExp('(' + t.join('|') + ')', 'gi'), '<mark class="hit">$1</mark>');
}

function scrollToEl(el, offset = 130) {
    if (!el) return;
    window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
}
function flashEl(el, cls) {
    if (!el) return;
    el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls);
    setTimeout(() => el.classList.remove(cls), 2400);
}
function findCard(id) {
    return [...document.querySelectorAll('#products .product')].find(c => c.dataset.pid === String(id));
}
function hideSuggest() { const b = document.getElementById('suggest'); if (b) b.classList.remove('show'); sgIndex = -1; }

function onSearch(v) {
    searchTerm = v || "";
    if (searchTerm.trim() && currentCat !== 'Semua') { currentCat = 'Semua'; syncChips(); }
    renderProducts(false);
    renderSuggest();
}

function renderSuggest() {
    const box = document.getElementById('suggest');
    if (!box) return;
    const q = searchTerm.trim();
    sgIndex = -1;
    if (!q) { box.classList.remove('show'); return; }
    const res = loaded ? searchProducts(q) : { list: [], tier: 1 };
    const prods = res.list.slice(0, 5);
    const secs = findSections(q).slice(0, 3);
    let h = '';
    if (prods.length) {
        h += `<div class="sg-h">Produk${res.tier === 3 ? ' (mirip)' : ''}</div>` + prods.map(p => `
            <div class="sg-i" data-pid="${String(p.id).replace(/"/g,'&quot;')}" onclick="pickProduct(this.dataset.pid)">
                <img src="${p.image}" alt="" onerror="this.onerror=null;this.src=PH">
                <div><b>${hl(p.name)}</b><small>${p.group_name || ''}</small></div>
                <span class="pr">${rupiah(p.price)}</span>
            </div>`).join('');
    }
    if (secs.length) {
        h += `<div class="sg-h">Bagian halaman</div>` + secs.map(sc => `
            <div class="sg-i" data-sec="${sc.id}" onclick="gotoSection(this.dataset.sec)">
                <div class="ic"><i class="fa-solid ${sc.icon}"></i></div>
                <div><b>${sc.label}</b><small>Menuju bagian ini</small></div>
            </div>`).join('');
    }
    if (!prods.length && !secs.length) h = `<div class="sg-empty">Tidak ada hasil untuk “${q.replace(/</g,'&lt;')}”. Coba kata lain, mis. <b>doorbell</b>, <b>NVR</b>, atau <b>paket</b>.</div>`;
    else if (res.list.length > prods.length) h += `<div class="sg-all" onclick="goSearch()">Lihat semua ${res.list.length} hasil <i class="fa-solid fa-arrow-right"></i></div>`;
    box.innerHTML = h;
    box.classList.add('show');
}

function sgKey(e) {
    const box = document.getElementById('suggest');
    if (!box || !box.classList.contains('show')) return;
    const items = [...box.querySelectorAll('.sg-i')];
    if (!items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        sgIndex = (sgIndex + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach((it, i) => it.classList.toggle('on', i === sgIndex));
    } else if (e.key === 'Enter' && sgIndex >= 0) {
        e.preventDefault();
        items[sgIndex].click();
    }
}

// Klik satu produk di saran -> langsung ke kartunya & disorot
function pickProduct(id) {
    const p = products.find(x => String(x.id) === String(id));
    if (!p) return;
    searchTerm = p.name;
    const inp = document.getElementById('searchInput'); if (inp) inp.value = p.name;
    currentCat = 'Semua'; syncChips();
    hideSuggest();
    renderProducts(false);
    setTimeout(() => { const c = findCard(id); if (c) { scrollToEl(c, 150); flashEl(c, 'flash'); } }, 60);
}

// Klik bagian halaman -> scroll ke bagian itu & judul disorot
function gotoSection(id) {
    hideSuggest();
    const el = document.getElementById(id);
    if (!el) return;
    scrollToEl(el, 90);
    flashEl(el.querySelector('.section-title h2') || el, 'flash-t');
}

// Enter / tombol cari
function goSearch() {
    const q = searchTerm.trim();
    hideSuggest();
    const inp = document.getElementById('searchInput'); if (inp) inp.blur();
    if (!q) { scrollToEl(document.getElementById('produk'), 90); return; }
    const res = searchProducts(q);
    if (res.list.length) {
        currentCat = 'Semua'; syncChips();
        renderProducts(true);
        setTimeout(() => {
            scrollToEl(document.getElementById('resultInfo'), 140);
            const first = document.querySelector('#products .product');
            if (first) flashEl(first, 'flash');
        }, 80);
        return;
    }
    const sec = findSections(q)[0];
    if (sec) { gotoSection(sec.id); return; }
    renderProducts(true);
    scrollToEl(document.getElementById('resultInfo'), 140);
}
function onSort(v) { sortMode = v; renderProducts(true); }
function toggleMenu() {
    const m = document.getElementById('mobileMenu');
    const open = m.classList.toggle('open');
    document.getElementById('burgerBtn').innerHTML = open ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>';
}

function addCart(id, qty=1) {
    const p = products.find(x => x.id === id); 
    if(!p) return;
    const item = cart.find(x => x.id === id);
    item ? item.qty += qty : cart.push({id, qty, name: p.name, price: p.price, image: p.image});
    saveCart();
    toast(p.name + " ditambahkan ke keranjang");
}

function buyNow(id) { addCart(id); openCart(); }

// ---------- Detail produk ----------
let pmQty = 1, pmId = null;
const escHtml = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function openProductModal(id) {
    const p = products.find(x => String(x.id) === String(id));
    if (!p) return;
    pmId = p.id; pmQty = 1;
    const raw = String(p.description || '').trim();
    const isHtml = raw.indexOf('<') >= 0;
    const desc = raw ? (isHtml ? raw : escHtml(raw)) : 'Belum ada deskripsi untuk produk ini.';
    const name = escHtml(p.name);
    document.getElementById('pmImg').innerHTML =
        (p.badge ? `<span class="ribbon">${p.badge}</span>` : '') +
        `<span class="gtag"><i class="fa-solid ${groupIcon(p.group_name)}"></i> ${escHtml(p.group_name || 'Produk')}</span>` +
        `<img src="${escHtml(p.image)}" alt="${name}" onerror="this.onerror=null;this.src=PH">`;
    document.getElementById('pmInfo').innerHTML = `
        <div class="brand">${escHtml(p.group_name || 'Produk')}</div>
        <h3 id="pmName">${name}</h3>
        <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i> <span style="color:var(--muted); font-size:11px; margin-left:4px">(4.9)</span></div>
        <div class="pm-price">${rupiah(p.price)}</div>
        <div class="pm-desc-title">Deskripsi Produk</div>
        <div class="pm-desc${isHtml ? '' : ' pre'}">${desc}</div>
        <div class="pm-buy">
            <div class="pm-qty">Jumlah
                <div class="qty">
                    <button type="button" onclick="pmChangeQty(-1)" aria-label="Kurangi">−</button>
                    <span id="pmQtyVal">1</span>
                    <button type="button" onclick="pmChangeQty(1)" aria-label="Tambah">+</button>
                </div>
            </div>
            <div class="pm-actions">
                <button type="button" class="btn outline" onclick="pmAdd(false)"><i class="fa-solid fa-cart-plus"></i> Keranjang</button>
                <button type="button" class="btn" onclick="pmAdd(true)">Beli Sekarang</button>
            </div>
        </div>`;
    const m = document.getElementById('product-modal');
    m.classList.add('show');
    m.scrollTop = 0;
    document.getElementById('pmInfo').scrollTop = 0;
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    const m = document.getElementById('product-modal');
    if (!m || !m.classList.contains('show')) return;
    m.classList.remove('show');
    document.body.style.overflow = '';
}

function pmChangeQty(d) {
    pmQty = Math.max(1, Math.min(99, pmQty + d));
    document.getElementById('pmQtyVal').textContent = pmQty;
}

function pmAdd(buy) {
    if (pmId == null) return;
    const id = pmId, q = pmQty;
    closeProductModal();
    addCart(id, q);
    if (buy) openCart();
}

// Klik kartu produk (selain tombol di dalamnya) membuka detail
(function () {
    const box = document.getElementById('products');
    if (!box) return;
    box.addEventListener('click', e => {
        if (e.target.closest('button, a, input, select, textarea')) return;
        const card = e.target.closest('.product');
        if (card && card.dataset.pid) openProductModal(card.dataset.pid);
    });
    box.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('pimg')) {
            e.preventDefault();
            const card = e.target.closest('.product');
            if (card && card.dataset.pid) openProductModal(card.dataset.pid);
        }
    });
})();

function saveCart() { 
    localStorage.setItem("tekuninCart", JSON.stringify(cart)); 
    renderCart(); 
    document.getElementById("cartCount").textContent = cart.reduce((a,x) => a + x.qty, 0);
}

function renderCart() {
    const el = document.getElementById("cartItems");
    if(!cart.length) {
        el.innerHTML = '<div class="empty"><i class="fa-solid fa-basket-shopping fa-3x" style="color:var(--line);margin-bottom:14px"></i><br>Keranjang Anda kosong.<br>Silakan pilih produk terlebih dahulu.</div>';
        document.getElementById("cartTotal").textContent = "Rp0";
        return;
    }
    let total = 0;
    el.innerHTML = cart.map(i => {
        total += i.price * i.qty;
        return `
            <div class="cart-item">
                <div class="mini"><img src="${i.image}" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null;this.src=PH"></div>
                <div>
                    <h4>${i.name}</h4>
                    <small style="font-weight:700; color:var(--blue)">${rupiah(i.price)}</small>
                    <div class="qty">
                        <button onclick="changeQty('${i.id}', -1)">−</button>
                        <b style="font-size:12px">${i.qty}</b>
                        <button onclick="changeQty('${i.id}', 1)">+</button>
                    </div>
                </div>
                <b style="font-size:13px; color:var(--navy)">${rupiah(i.price * i.qty)}</b>
            </div>
        `;
    }).join("");
    document.getElementById("cartTotal").textContent = rupiah(total);
}

function changeQty(id, n) {
    const i = cart.find(x => x.id === id);
    if(!i) return;
    i.qty += n;
    if(i.qty <= 0) cart = cart.filter(x => x.id !== id);
    saveCart();
}

function openCart() { document.getElementById("drawer").classList.add("open"); document.getElementById("drawerOverlay").classList.add("show"); renderCart(); }
function closeCart() { document.getElementById("drawer").classList.remove("open"); document.getElementById("drawerOverlay").classList.remove("show"); }

function openCheckoutModal() {
    if(!cart.length) { toast("Keranjang masih kosong"); return; }
    closeCart();
    document.getElementById("checkout-modal").style.display = "flex";
}
function closeCheckoutModal() { document.getElementById("checkout-modal").style.display = "none"; }

// Kompres gambar bukti TF — khusus optimasi HP (iPhone/Android)
function compressProofImage(file) {
    return new Promise((resolve) => {
        if (!file) return resolve('');

        // Tolak file ekstrem
        if (file.size > 20 * 1024 * 1024) {
            console.warn('File terlalu besar');
            return resolve('');
        }

        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        // Di HP pakai resolusi & quality lebih kecil
        const maxWidth = isMobile ? 520 : 700;
        const quality = isMobile ? 0.25 : 0.35;

        const reader = new FileReader();
        let settled = false;
        const finish = (val) => {
            if (!settled) {
                settled = true;
                resolve(val || '');
            }
        };

        // Timeout 12 detik agar HP tidak menggantung
        const timer = setTimeout(() => finish(''), 12000);

        reader.onerror = () => { clearTimeout(timer); finish(''); };
        reader.onload = function(e) {
            const raw = e.target.result;
            if (typeof raw !== 'string') { clearTimeout(timer); finish(''); return; }

            const img = new Image();
            img.onload = function() {
                try {
                    let w = img.naturalWidth || img.width || 640;
                    let h = img.naturalHeight || img.height || 480;

                    // Scale down
                    if (w > maxWidth) {
                        h = Math.round((h * maxWidth) / w);
                        w = maxWidth;
                    }
                    if (h > 800) {
                        w = Math.round((w * 800) / h);
                        h = 800;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, w);
                    canvas.height = Math.max(1, h);
                    const ctx = canvas.getContext('2d');
                    // Background putih (penting untuk PNG transparan / HEIC hasil convert)
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    let out = canvas.toDataURL('image/jpeg', quality);
                    // Kompres bertingkat jika masih besar
                    if (out.length > 120000) out = canvas.toDataURL('image/jpeg', 0.20);
                    if (out.length > 120000) out = canvas.toDataURL('image/jpeg', 0.14);
                    if (out.length > 120000) out = canvas.toDataURL('image/jpeg', 0.10);

                    clearTimeout(timer);
                    finish(out);
                } catch (err) {
                    clearTimeout(timer);
                    finish('');
                }
            };
            img.onerror = function() {
                // Format tidak didukung browser (sering HEIC murni) → kosong
                clearTimeout(timer);
                finish('');
            };
            img.src = raw;
        };

        try {
            reader.readAsDataURL(file);
        } catch (e) {
            clearTimeout(timer);
            finish('');
        }
    });
}

async function submitOrder() {
    const name = document.getElementById("buyer-name").value.trim();
    const phone = document.getElementById("buyer-phone").value.trim();
    const address = document.getElementById("buyer-address").value.trim();
    const bank = document.getElementById("buyer-bank").value;
    const proofInput = document.getElementById("buyer-proof");

    if(!name || !phone || !address) {
        alert("Mohon lengkapi seluruh Data Pemesan (Nama, No HP, dan Alamat).");
        return;
    }

    const orderId = 'INV-' + Date.now().toString().slice(-6);
    const total = cart.reduce((s, i) => s + (i.price * i.qty), 0);

    // Kompres bukti TF dulu (sangat kecil agar lolos luar jaringan / WAF)
    let proofBase64 = '';
    if (proofInput.files && proofInput.files[0]) {
        const file = proofInput.files[0];
        const fname = (file.name || '').toLowerCase();
        if (fname.endsWith('.heic') || fname.endsWith('.heif') || file.type === 'image/heic' || file.type === 'image/heif') {
            alert('Format HEIC tidak didukung. Ubah ke JPG di HP lalu coba lagi.\nPesanan akan tetap dikirim tanpa bukti TF.');
        } else {
            proofBase64 = await compressProofImage(file);
            if (!proofBase64) {
                console.warn('Gagal kompres gambar');
            }
        }
    }

    // Kirim SEMUA data (termasuk bukti TF) dalam 1 request — paling andal di HP/luar jaringan
    const payload = {
        id: orderId,
        date: new Date().toLocaleDateString('id-ID'),
        rawDate: new Date().toISOString(),
        name: name,
        phone: phone,
        address: address,
        total: total,
        bank: bank,
        status: 'BELUM LUNAS',
        trackingStatus: 'PROSES',
        dpAmount: total / 2,
        remainingAmount: total / 2,
        warrantyType: '',
        warrantyDueDate: '',
        items: cart,
        proofImage: proofBase64 || ''
    };

    try {
        let res = await fetch('admin.php?api=save_order', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        
        let responseText = await res.text(); 
        let json;
        try {
            json = JSON.parse(responseText); 
        } catch (err) {
            console.error("ERROR DARI PHP:", responseText);
            alert("⚠️ Pesanan gagal. Respon server tidak valid.");
            return;
        }

        if(!json.success) {
            alert("Gagal mengirim pesanan: " + (json.msg || "Kesalahan internal server."));
            return;
        }

        // Backup: jika gambar ada tapi mungkin belum tersimpan, coba endpoint khusus
        if (proofBase64 && proofBase64.startsWith('data:image/')) {
            try {
                await fetch('admin.php?api=upload_order_proof', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: orderId, proofImage: proofBase64 })
                });
            } catch(e) {}
        }

        let myOrders = JSON.parse(localStorage.getItem('tekuninOrders') || '[]');
        myOrders.unshift({ id: orderId, date: payload.date, total: total, status: 'PROSES' });
        localStorage.setItem('tekuninOrders', JSON.stringify(myOrders));
        
        closeCheckoutModal();
        cart = [];
        saveCart();
        alert("✅ Berhasil! Pesanan Anda telah dikirim dan sedang diproses admin.");
    } catch(e) {
        console.error("ERROR FETCH:", e);
        alert("Terjadi kesalahan jaringan atau API diblokir oleh sistem keamanan server.");
    }
}

function toggleChatWidget() {
    const box = document.getElementById("chatWidgetBox");
    box.style.display = box.style.display === "flex" ? "none" : "flex";
    if(box.style.display === "flex") loadChatHistory();
}

async function sendChatMessage() {
    const input = document.getElementById("chatInputText");
    const text = input.value.trim();
    if(!text) return;
    input.value = "";

    await fetch('admin.php?api=send_chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ session_id: chatSession, message: text, sender: 'customer' })
    });
    loadChatHistory();
}

const escH=s=>String(s??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
/* 1) Bersihkan entitas HTML (&lt;br&gt;), tag <br>, dan "\n" literal menjadi baris baru biasa */
const decH=x=>{for(let i=0;i<2;i++)x=x.replace(/&lt;/gi,"<").replace(/&gt;/gi,">").replace(/&quot;/gi,'"').replace(/&#0*39;|&apos;/gi,"'").replace(/&nbsp;/gi," ").replace(/&amp;/gi,"&");return x};
const preClean=x=>decH(String(x??"")).replace(/\r/g,"").replace(/\\r?\\n/g,"\n").replace(/<br\s*\/?>/gi,"\n");
/* 2) Perbaiki karakter rusak ('?' / �) dan jarak antar kata/tanda baca */
function tidy(t){
    return t
        .replace(/[\u00A0\u2007\u202F]/g," ").replace(/[\u200B-\u200D\uFEFF]/g,"")
        .replace(/[\u2010\u2011]/g,"-").replace(/[\u2012-\u2015]/g,"–")
        .replace(/[“”]/g,'"').replace(/[‘’]/g,"'")
        /* karakter encoding rusak: HAPUS (jangan jadi ?) */
        .replace(/\uFFFD+/g,"").replace(/�+/g,"")
        /* ? di tengah kata/angka = rusak */
        .replace(/(\d)\?(?=\d)/g,"$1-")
        .replace(/([A-Za-z])\?(?=[A-Za-z])/g,"$1")
        .replace(/([A-Za-z])\?(?=\d)/g,"$1")
        .replace(/(\d)\?(?=[A-Za-z])/g,"$1")
        /* ? di awal baris / bullet rusak */
        .replace(/(^|\n)[ \t]*\?+[ \t]+/g,"$1")
        .replace(/(^|\n)[ \t]*\?+[ \t]*$/gm,"$1")
        /* ? setelah ** atau ) */
        .replace(/(\*\*|\))[ \t]*\?+[ \t]+/g,"$1 ")
        /* ? di tengah kalimat sebelum kata/harga */
        .replace(/[ \t]\?+[ \t]+(?=Rp\b|[A-Za-z0-9*(])/g," ")
        /* ?? beruntun */
        .replace(/\?{2,}/g,"")
        /* ? menempel tanda baca */
        .replace(/([,.;:])\?+/g,"$1").replace(/\?+([,.;:])/g,"$1")
        .replace(/([.!:])[ \t]*\?+[ \t]*(?=\n|$)/g,"$1")
        /* ? sendiri di antara spasi (bukan pertanyaan) */
        .replace(/(\S)[ \t]+\?[ \t]+(\S)/g,"$1 $2")
        /* rapi spasi & tanda baca */
        .replace(/[ \t]+([,.;!])/g,"$1")
        .replace(/[ \t]+:(?=\s|$)/g,":")
        .replace(/([,;])(?=[A-Za-z])/g,"$1 ")
        .replace(/([a-z])([.!?])(?=[A-Z][a-z])/g,"$1$2 ")
        .replace(/([a-z]):(?=[A-Z])/g,"$1: ")
        .replace(/\(\s+/g,"(").replace(/\s+\)/g,")")
        .replace(/\bRp\.?\s*(?=\d)/g,"Rp ")
        .replace(/([^\n-])[ \t]*-{3,}[ \t]*/g,"$1\n")
        .replace(/[ \t]--[ \t]/g," – ")
        .replace(/[ \t]{2,}/g," ")
        .replace(/[ \t]+\n/g,"\n")
        .replace(/\n[ \t]+/g,"\n")
        /* Sisa '?': hanya pertahankan jika di akhir kalimat/pertanyaan */
        .replace(/\?/g, function(m, off, str){
            var prev = str.charAt(off-1), next = str.charAt(off+1);
            if (!/[A-Za-z0-9)]/.test(prev)) return "";
            if (next === "" || next === "\n" || next === "\"" || next === "'") return "?";
            if (next === " " && /[A-Z]/.test(str.charAt(off+2)||"")) return "?";
            return "";
        });
}
/* 3) Nama produk di jawaban CS menjadi tautan ke produk */
let _pk="",_pr=null,_pm={};
function prodRegex(){
    const sig=typeof lastSig!=="undefined"?lastSig:String(products.length);
    if(sig!==_pk){
        _pk=sig;_pm={};const names=[];
        products.forEach(p=>[p.name,String(p.name||"").replace(/\s*\([^)]*\)\s*/g," ")].forEach(n=>{
            n=escH(String(n||"").trim()).replace(/\s+/g," ");
            if(n.length>=5&&_pm[n.toLowerCase()]===undefined){_pm[n.toLowerCase()]=p.id;names.push(n)}
        }));
        names.sort((a,b)=>b.length-a.length);
        _pr=names.length?new RegExp("(^|[^A-Za-z0-9])("+names.map(n=>n.replace(/[.*+?^${}()|[\]\\]/g,"\\$&").replace(/ /g,"\\s+")).join("|")+")(?![A-Za-z0-9])","gi"):null;
    }
    return _pr;
}
const linkify=h=>{const r=prodRegex();if(!r)return h;
    return h.split(/(<[^>]+>)/).map(s=>s[0]=="<"?s:s.replace(r,(m,pre,name)=>{const id=_pm[name.replace(/\s+/g," ").toLowerCase()];
        return id===undefined?m:pre+'<a class="cb-pl" href="#produk" data-pid="'+escH(id)+'" onclick="goProduct(this.dataset.pid);return false">'+name+'</a>'})).join("")};
function goProduct(id){
    const p=products.find(x=>String(x.id)===String(id));if(!p)return;
    const s=document.getElementById('searchInput');if(s)s.value=p.name;
    searchTerm=p.name;currentCat="Semua";syncChips();renderProducts(true);
    if(innerWidth<700){const b=document.getElementById('chatWidgetBox');if(b)b.style.display='none'}
    const sec=document.getElementById('produk');if(sec)sec.scrollIntoView({behavior:'smooth',block:'start'});
    setTimeout(()=>{const c=[...document.querySelectorAll('.product')].find(e=>e.dataset.pid===String(id));
        if(c){c.scrollIntoView({behavior:'smooth',block:'center'});c.classList.remove('flash');void c.offsetWidth;c.classList.add('flash')}},450);
}
/* 4) Susun jawaban: kalimat biasa = paragraf polos; simbol (titik/nomor) HANYA untuk daftar 2 item atau lebih */
function fmtChat(raw){
    let t=preClean(raw).replace(/<\/(p|div|ul|ol|h[1-6]|tr)>/gi,"\n").replace(/<li[^>]*>/gi,"\n- ").replace(/<h[1-6][^>]*>/gi,"\n### ")
        .replace(/<\/?(strong|b)\b[^>]*>/gi,"**").replace(/<\/?[a-z][^>]*>/gi,"").trim();
    t=tidy(t);
    // Rapikan simbol aneh & sisa markdown
    t=t.replace(/\uFFFD/g,"").replace(/[|｜]{2,}/g,"|")
       .replace(/\s*\|\s*(?=#{1,4}\s)/g,"\n")
       .replace(/\s*(#{1,4})\s+(?=[A-Z*])/g,"\n$1 ")
       .replace(/\s+(\d{1,2})[.)]\s+(?=\*\*|[\w])/g,"\n$1. ")
       .replace(/\s+•\s+/g,"\n- ")
       .replace(/[ \t]*\|[ \t]*$/gm,"")
       .replace(/^[ \t]*\|[ \t]*/gm,"")
       .replace(/\n{3,}/g,"\n\n");
    const bl=/([A-Za-z)\]:.!?*])\s-\s(?=[A-Z*])/g;
    if((t.match(bl)||[]).length>=2) t=t.replace(bl,"$1\n- ");

    const inl=x=>linkify(escH(x)
        .replace(/\*\*(.+?)\*\*/g,"<b>$1</b>")
        .replace(/`([^`]+)`/g,"<code>$1</code>")
        .replace(/[*`]+/g,"")
        .replace(/\s-\s/g," – ")
        .replace(/\s*\|\s*/g," · "));

    // Deteksi baris tabel (ada | atau pola No.|Produk)
    const isSep=l=>/^[\s|:\-+]+$/.test(l) && /[-:]/.test(l);
    const isTbl=l=>{
        if(!l.includes("|")) return false;
        const parts=l.split("|").map(x=>x.trim()).filter(Boolean);
        return parts.length>=2;
    };
    const splitRow=l=>l.split("|").map(x=>x.trim()).filter((x,i,a)=>!(i===0&&x==="")&&!(i===a.length-1&&x===""));

    const lines=t.split("\n").map(l=>l.trim()).filter(l=>l && l!=="|");
    const blocks=[];
    for(let i=0;i<lines.length;){
        if(isTbl(lines[i]) && !isSep(lines[i])){
            const rows=[];
            while(i<lines.length && (isTbl(lines[i]) || isSep(lines[i]))){
                if(!isSep(lines[i])) rows.push(splitRow(lines[i]));
                i++;
            }
            if(rows.length) blocks.push({k:"table",rows});
            continue;
        }
        let l=lines[i++], m;
        if(/^(-{3,}|_{3,}|\*{3,})$/.test(l)) blocks.push({k:"hr"});
        else if(m=l.match(/^#{1,4}\s+(.+)/)) blocks.push({k:"h",t:m[1]});
        else if(m=l.match(/^[-*•]\s+(.+)/)) blocks.push({k:"b",t:m[1]});
        else if(m=l.match(/^(\d{1,2})[.)]\s+(.+)/)) blocks.push({k:"n",n:m[1],t:m[2]});
        else blocks.push({k:"t",t:l});
    }

    const sp=x=>x.replace(/([.!?])\s+(?=[A-Z(])/g,"$1\u0001").split("\u0001");
    const P=x=>sp(x).map(y=>'<div class="cb-s">'+inl(y)+'</div>').join("");
    const cl=/([.!?])\s+(?=(Apakah|Silakan|Jika|Bila|Hubungi|Terima kasih|Ada |Mau |Ingin |Semoga|Kami siap|Saya siap|Untuk informasi)\b)/;
    const out=[];

    // Render tabel → HTML table, atau kartu produk jika kolom mirip katalog
    const renderTable=rows=>{
        if(!rows.length) return "";
        const head=rows[0].map(c=>c.toLowerCase());
        const looksProduct = head.some(h=>/produk|nama|barang/.test(h)) || head.some(h=>/harga|price|rp/.test(h));
        // Kartu produk lebih enak dibaca di chat sempit
        if(looksProduct && rows.length>1){
            const h=rows[0];
            const idxName=h.findIndex(c=>/produk|nama|barang/i.test(c));
            const idxPrice=h.findIndex(c=>/harga|price|rp/i.test(c));
            const idxDesc=h.findIndex(c=>/deskripsi|ket|spec|fitur/i.test(c));
            const cards=rows.slice(1).map((r,qi)=>{
                const name=r[idxName>=0?idxName:1]||r[0]||"";
                const price=r[idxPrice>=0?idxPrice:(r.length>2?2:1)]||"";
                const desc=idxDesc>=0?(r[idxDesc]||""): (r.length>3?r.slice(3).join(" "):"");
                return '<div class="cb-card"><div class="cb-card-t">'+(qi+1)+'. '+inl(name)+'</div>'
                    +(price?'<div class="cb-card-p">'+inl(String(price).replace(/^(?!Rp)/i,"Rp "))+'</div>':'')
                    +(desc?'<div class="cb-card-d">'+inl(desc)+'</div>':'')
                    +'</div>';
            }).join("");
            return cards;
        }
        // Tabel generik
        let html='<div class="cb-table-wrap"><table class="cb-table"><thead><tr>';
        rows[0].forEach(c=>{ html+='<th>'+inl(c)+'</th>'; });
        html+='</tr></thead><tbody>';
        rows.slice(1).forEach(r=>{
            html+='<tr>';
            r.forEach((c,ci)=>{
                const isPrice=/rp\s*\d|^\d{1,3}([.,]\d{3})+/i.test(c) || /harga/i.test(rows[0][ci]||"");
                html+='<td'+(isPrice?' class="cb-price"':'')+'>'+inl(c)+'</td>';
            });
            html+='</tr>';
        });
        html+='</tbody></table></div>';
        return html;
    };

    for(let i=0;i<blocks.length;i++){
        const a=blocks[i];
        if(a.k==="table") out.push(renderTable(a.rows));
        else if(a.k==="hr") out.push('<hr class="cb-hr">');
        else if(a.k==="h") out.push('<div class="cb-h">'+inl(a.t)+'</div>');
        else if(a.k==="t"){
            // Hilangkan sisa pipe di teks biasa
            let tt=a.t.replace(/\s*\|\s*/g," · ").replace(/^·\s*|·\s*$/g,"").trim();
            if(tt) out.push(P(tt));
        } else {
            let j=i; while(j<blocks.length&&(blocks[j].k==="b"||blocks[j].k==="n")) j++;
            const run=blocks.slice(i,j);
            if(run.length<2){
                out.push(P(a.t));
            } else {
                run.forEach((r,q)=>{
                    let body=r.t, rest="";
                    if(q===run.length-1){ const c=body.search(cl); if(c>=0){ rest=body.slice(c+1).trim(); body=body.slice(0,c+1); } }
                    out.push(r.k==="n"
                        ? '<div class="cb-n"><i>'+r.n+'</i><span>'+inl(body)+'</span></div>'
                        : '<div class="cb-b"><span>'+inl(body)+'</span></div>');
                    if(rest) out.push(P(rest));
                });
            }
            i=j-1;
        }
    }
    return '<div class="cb">'+out.join("")+'</div>';
}

async function loadChatHistory() {
    try {
        let res = await fetch('admin.php?api=chat_history&session_id=' + chatSession);
        let json = await res.json();
        const box = document.getElementById("chatBoxMessages");
        if(!box) return;
        const sig=JSON.stringify(json.messages||[]);
        if(sig===loadChatHistory.last) return;
        const first=loadChatHistory.last===undefined, near=box.scrollHeight-box.scrollTop-box.clientHeight<120;
        loadChatHistory.last=sig;
        if(!json.messages || json.messages.length === 0) {
            box.innerHTML = `<div style="background:#f0f9ff; padding:10px 14px; border-radius:12px; color:#0369a1; border:1px solid #bae6fd; font-weight:500"><b style="display:block; margin-bottom:3px"><i class="fa-solid fa-robot mr-1"></i> CS Tekunin</b> Halo! Selamat datang di Tekunin CCTV. Silakan sampaikan pertanyaan Anda tentang produk atau layanan kami.</div>`;
        } else {
            box.innerHTML = json.messages.map(m => {
                if(m.sender == 'system') return `<div style="text-align:center; font-size:10px; color:var(--muted); margin:6px 0; font-weight:700">--- ${escH(preClean(m.text))} ---</div>`;
                let isMe = m.sender == 'customer';
                let bg = isMe ? 'var(--blue)' : '#f0f9ff';
                let color = isMe ? 'white' : '#0369a1';
                let align = isMe ? 'margin-left:auto;' : 'margin-right:auto;';
                let border = isMe ? '' : 'border:1px solid #bae6fd;';
                return `<div style="background:${bg}; color:${color}; padding:10px 14px; border-radius:12px; max-width:85%; ${align} ${border}; line-height:1.5;"><b style="font-size:9px; opacity:0.8; display:block; margin-bottom:5px; text-transform:uppercase; letter-spacing:1px">${/^(bot|ai|ai bot|assistant)$/i.test(m.sender) ? "CS TEKUNIN" : escH(m.sender)}</b>${isMe ? escH(preClean(m.text).trim()).replace(/\n/g,"<br>") : fmtChat(m.text)}</div>`;
            }).join('');
        }
        const last=box.lastElementChild, lastBot=json.messages&&json.messages.length&&json.messages[json.messages.length-1].sender!=='customer';
        if(first||near) box.scrollTop = (lastBot&&last&&!first) ? last.offsetTop-box.offsetTop-10 : box.scrollHeight;
    } catch(e) {}
}

function toast(t) {
    const x = document.getElementById("toast");
    x.innerHTML = `<i class="fa-solid fa-check-circle mr-2 text-green-400"></i> ` + t;
    x.classList.add("show");
    setTimeout(() => x.classList.remove("show"), 2500);
}

// SIMULATOR INTERAKTIF
const sleep = ms => new Promise(r => setTimeout(r, ms));
let isRunning = false;
const simData = {
    rumah: {
        floorplans: [
            { id: 'room-front', label: 'HALAMAN & PINTU DEPAN', style: 'left: 5%; top: 55%; width: 45%; height: 40%;' },
            { id: 'room-living', label: 'RUANG TAMU INDOOR', style: 'left: 5%; top: 5%; width: 45%; height: 45%;' },
            { id: 'room-back', label: 'HALAMAN & PAGAR BELAKANG', style: 'left: 55%; top: 5%; width: 40%; height: 90%;' }
        ],
        nodes: [
            { id: 'h-hub', icon: 'fa-house-signal', x: 25, y: 25, label: 'SMART_HUB' },
            { id: 'h-cctv-front', icon: 'fa-video', x: 25, y: 75, label: 'CCTV_DEPAN' },
            { id: 'h-cctv-back', icon: 'fa-video', x: 75, y: 30, label: 'CCTV_BELAKANG' },
            { id: 'h-light', icon: 'fa-lightbulb', x: 75, y: 70, label: 'SPOTLIGHT' }
        ],
        links: [
            ['h-hub', 'h-cctv-front'], ['h-hub', 'h-cctv-back'], ['h-hub', 'h-light']
        ],
        scenarios: [
            { id: 'h1', title: 'Skenario 1: Kurir Paket Datang', desc: 'Kamera depan mendeteksi manusia -> AI mengenali kurir -> Notifikasi ke HP pemilik.' },
            { id: 'h2', title: 'Skenario 2: Ancaman Penyusup Malam', desc: 'CCTV belakang mendeteksi siluet malam -> Lampu sorot menyala otomatis.' },
            { id: 'h3', title: 'Skenario 3: Filter Hewan Peliharaan', desc: 'Kamera indoor mendeteksi gerak kucing -> AI memfilter objek.' }
        ]
    }
};

function termLog(msg, type='info') {
    const term = document.getElementById('terminal-logs');
    if(!term) return;
    const time = new Date().toLocaleTimeString('id-ID');
    let color = '#c7d2fe';
    if (type === 'warn') color = '#ffea00';
    if (type === 'err') color = '#ff003c';
    if (type === 'success') color = '#6ea0ff';
    if (type === 'magenta') color = '#9bbcff';
    const html = `<div style="color: ${color};"><span class="text-indigo-500/50">[${time}]</span> ${msg}</div>`;
    term.insertAdjacentHTML('beforeend', html);
    term.scrollTop = term.scrollHeight;
}

function loadHouseSimulation() {
    const data = simData.rumah;
    document.getElementById('floorplan-layer').innerHTML = 
        data.floorplans.map(fp => `<div class="floorplan-room" style="${fp.style}">${fp.label}</div>`).join('');
    document.getElementById('nodes-layer').innerHTML = 
        data.nodes.map(n => `
            <div id="${n.id}" class="scada-node" style="left: ${n.x}%; top: ${n.y}%;">
                <i class="fa-solid ${n.icon}" id="icon-${n.id}"></i>
                <div class="scada-node-label">${n.label}</div>
            </div>
        `).join('');
    setTimeout(() => {
        document.getElementById('svg-layer').innerHTML = 
            data.links.map(l => `<line id="line-${l[0]}-${l[1]}" class="svg-line" x1="0" y1="0" x2="0" y2="0" />`).join('');
        updateSVGCoords(data);
    }, 50);
    document.getElementById('scenario-list').innerHTML = data.scenarios.map(sc => `
        <div class="scenario-row bg-obsidian-800/80 border border-white/5 rounded-lg p-3 flex justify-between items-center transition-colors" style="background:#0a0a12;">
            <div class="pr-2">
                <div class="font-bold text-white text-xs mb-1">${sc.title}</div>
                <div class="text-[10px] text-gray-400 leading-tight">${sc.desc}</div>
            </div>
            <button onclick="run('${sc.id}')" class="btn-run shrink-0 bg-cyan-500/10 hover:bg-cyan-400 text-cyan-400 hover:text-black border border-cyan-500/50 px-3 py-1.5 rounded text-xs font-bold transition-all cursor-pointer">JALANKAN</button>
        </div>
    `).join('');
}

function updateSVGCoords(data) {
    const container = document.getElementById('map-container');
    if(!container) return;
    const cRect = container.getBoundingClientRect();
    data.links.forEach(l => {
        const n1 = document.getElementById(l[0]);
        const n2 = document.getElementById(l[1]);
        if(n1 && n2) {
            const r1 = n1.getBoundingClientRect();
            const r2 = n2.getBoundingClientRect();
            const line = document.getElementById(`line-${l[0]}-${l[1]}`);
            if(line) {
                line.setAttribute('x1', (r1.left - cRect.left) + r1.width/2);
                line.setAttribute('y1', (r1.top - cRect.top) + r1.height/2);
                line.setAttribute('x2', (r2.left - cRect.left) + r2.width/2);
                line.setAttribute('y2', (r2.top - cRect.top) + r2.height/2);
            }
        }
    });
}

function setNodeStyle(id, type) {
    const el = document.getElementById(id);
    if(el) el.className = `scada-node ${type ? 'node-'+type : ''}`;
}

async function setActorPos(actorId, x, y, visible=true) {
    const el = document.getElementById(actorId);
    if(!el) return;
    el.style.transition = 'none';
    el.style.left = `${x}%`;
    el.style.top = `${y}%`;
    el.style.display = 'flex';
    if(visible) el.style.opacity = '1';
    await sleep(50);
}

async function moveActor(actorId, x, y, duration=2000) {
    const el = document.getElementById(actorId);
    if(!el) return;
    el.style.transition = `left ${duration}ms ease-in-out, top ${duration}ms ease-in-out, opacity 0.5s`;
    el.style.left = `${x}%`;
    el.style.top = `${y}%`;
    el.style.opacity = '1';
    await sleep(duration + 100);
}

async function fadeOutActor(actorId) {
    const el = document.getElementById(actorId);
    if(!el) return;
    el.style.opacity = '0';
    await sleep(500);
    el.style.display = 'none';
}

async function animatePacket(fromId, toId, colorName) {
    return new Promise(resolve => {
        const container = document.getElementById('map-container');
        const fromEl = document.getElementById(fromId);
        const toEl = document.getElementById(toId);
        if(!fromEl || !toEl || !container) { resolve(); return; }
        const cRect = container.getBoundingClientRect();
        const fRect = fromEl.getBoundingClientRect();
        const tRect = toEl.getBoundingClientRect();
        const sx = (fRect.left - cRect.left) + fRect.width/2;
        const sy = (fRect.top - cRect.top) + fRect.height/2;
        const ex = (tRect.left - cRect.left) + tRect.width/2;
        const ey = (tRect.top - cRect.top) + tRect.height/2;
        const cMap = {'cyan':'#6ea0ff', 'red':'#ff003c', 'yellow':'#ffea00'};
        const hex = cMap[colorName] || '#fff';
        let line = document.getElementById(`line-${fromId}-${toId}`) || document.getElementById(`line-${toId}-${fromId}`);
        if(line) line.classList.add(`active-${colorName}`);
        const dot = document.createElement('div');
        dot.style.cssText = `position:absolute; width:10px; height:10px; background:#fff; border-radius:50%; left:${sx}px; top:${sy}px; transform:translate(-50%,-50%); box-shadow:0 0 15px ${hex}; z-index:30; pointer-events:none;`;
        container.appendChild(dot);
        const anim = dot.animate([{ left: `${sx}px`, top: `${sy}px` }, { left: `${ex}px`, top: `${ey}px` }], { duration: 1200, easing: 'ease-in-out' });
        anim.onfinish = () => { dot.remove(); if(line) line.classList.remove(`active-${colorName}`); resolve(); };
    });
}

window.run = async (scenId) => {
    if (isRunning) return;
    isRunning = true;
    document.querySelectorAll('.btn-run').forEach(b => b.classList.add('opacity-50', 'pointer-events-none'));
    document.getElementById('terminal-logs').innerHTML = '';
    try {
        if (scenId === 'h1') {
            termLog("SIMULASI RUMAH: Kurir Paket Tiba di Halaman Depan.", 'magenta');
            await setActorPos('actor-blue', 10, 90, false);
            await moveActor('actor-blue', 25, 75, 2000);
            setNodeStyle('h-cctv-front', 'yellow');
            await animatePacket('h-cctv-front', 'h-hub', 'cyan');
            setNodeStyle('h-hub', 'cyan');
            await fadeOutActor('actor-blue');
        } else if (scenId === 'h2') {
            termLog("SIMULASI RUMAH: Ancaman Penyusup Malam Hari.", 'magenta');
            await setActorPos('actor-red', 90, 10, false);
            await moveActor('actor-red', 75, 30, 2500);
            setNodeStyle('h-cctv-back', 'red');
            await animatePacket('h-cctv-back', 'h-hub', 'red');
            setNodeStyle('h-hub', 'red');
            await animatePacket('h-hub', 'h-light', 'red');
            setNodeStyle('h-light', 'red');
            await moveActor('actor-red', 90, 5, 1500);
            await fadeOutActor('actor-red');
        } else if (scenId === 'h3') {
            termLog("SIMULASI RUMAH: Deteksi Hewan Peliharaan (Smart AI).", 'magenta');
            await setActorPos('actor-cat', 10, 10, false);
            await moveActor('actor-cat', 25, 25, 2000);
            setNodeStyle('h-hub', 'yellow');
            setNodeStyle('h-hub', 'cyan');
            await fadeOutActor('actor-cat');
        }
    } catch(e) {} finally {
        isRunning = false;
        setTimeout(() => { document.querySelectorAll('.scada-node').forEach(n => { n.className = 'scada-node'; }); }, 1000);
        document.querySelectorAll('.btn-run').forEach(b => b.classList.remove('opacity-50', 'pointer-events-none'));
    }
};

// ==========================================
// FITUR LACAK PESANAN & KONFIRMASI USER
// ==========================================
function openTrackingModal() {
    document.getElementById("tracking-modal").style.display = "flex";
    renderTracking();
}

function closeTrackingModal() {
    document.getElementById("tracking-modal").style.display = "none";
}

async function renderTracking() {
    const container = document.getElementById("tracking-content");
    let myOrders = JSON.parse(localStorage.getItem('tekuninOrders') || '[]');
    
    if(myOrders.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px 10px; color:var(--muted);">
                <i class="fa-solid fa-box-open fa-3x" style="color:var(--line); margin-bottom:10px;"></i>
                <p style="font-size:13px; font-weight:600;">Belum ada riwayat pesanan atau pesanan telah selesai dibersihkan.</p>
            </div>`;
        return;
    }

    container.innerHTML = '<div style="text-align:center; font-size:12px; color:var(--muted); font-weight:600;"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i>Memuat status terbaru...</div>';

    let html = '';
    let validOrders = [];

    for(let order of myOrders) {
        try {
            let res = await fetch('admin.php?api=check_order&id=' + order.id, { cache: 'no-store' });
            let json = await res.json();
            
            // Jika pesanan sudah dihapus admin dari database, otomatis hilangkan dari riwayat user
            if (!json.success) {
                continue; 
            }

            if(json.success && json.data && json.data.status) {
                order.status = json.data.status; 
            }
        } catch(e) {}
        
        validOrders.push(order);
        html += generateTrackingHTML(order);
    }
    
    // Perbarui penyimpanan lokal dengan pesanan yang valid saja
    localStorage.setItem('tekuninOrders', JSON.stringify(validOrders));
    
    if(!validOrders.length) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px 10px; color:var(--muted);">
                <i class="fa-solid fa-box-open fa-3x" style="color:var(--line); margin-bottom:10px;"></i>
                <p style="font-size:13px; font-weight:600;">Belum ada riwayat pesanan.</p>
            </div>`;
        return;
    }

    container.innerHTML = html;
}

function generateTrackingHTML(order) {
    const statuses = [
        { key: 'PROSES', label: 'Pesanan Diterima & Diproses', icon: 'fa-box' },
        { key: 'KONFIRMASI', label: 'Admin Konfirmasi ke User', icon: 'fa-headset' },
        { key: 'MENUJU LOKASI', label: 'Teknisi Sedang Menuju Lokasi', icon: 'fa-truck-fast' },
        { key: 'SELESAI RAMPUNG', label: 'Selesai / Instalasi Rampung', icon: 'fa-check-circle' }
    ];
    
    let currentIndex = 0;
    let os = (order.status || '').toUpperCase();
    
    if(os.includes('KONFIRMASI')) currentIndex = 1;
    else if(os.includes('MENUJU') || os.includes('TEKNISI') || os.includes('JALAN')) currentIndex = 2;
    else if(os.includes('SELESAI') || os.includes('LUNAS') || os.includes('RAMPUNG')) currentIndex = 3;
    
    let timelineHTML = statuses.map((st, idx) => {
        let isActive = idx <= currentIndex;
        let color = isActive ? 'var(--blue)' : 'var(--line)';
        let textColor = isActive ? 'var(--navy)' : 'var(--muted)';
        let line = idx < statuses.length - 1 ? `<div style="width:2px; height:18px; background:${idx < currentIndex ? 'var(--blue)' : 'var(--line)'}; margin-left:13px; margin-top:2px; margin-bottom:2px;"></div>` : '';
        
        return `
            <div style="display:flex; flex-direction:column;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:28px; height:28px; border-radius:50%; background:${isActive ? '#e0f2fe' : '#f8fafc'}; color:${color}; display:flex; align-items:center; justify-content:center; border:2px solid ${color}; font-size:11px; z-index:2; position:relative;">
                        <i class="fa-solid ${st.icon}"></i>
                    </div>
                    <span style="font-size:12px; font-weight:${isActive ? '800' : '600'}; color:${textColor}">${st.label}</span>
                </div>
                ${line}
            </div>
        `;
    }).join('');

    // Tombol Interaktif Sesuai Status Admin
    let actionButtonHTML = '';
    
    // 1. Jika admin di status KONFIRMASI, munculkan tombol KONFIRMASI PEMASANGAN
    if (os.includes('KONFIRMASI') && !order.userConfirmedInstall) {
        actionButtonHTML = `
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line);">
                <button onclick="userConfirmInstall('${order.id}')" class="btn" style="width: 100%; justify-content: center; font-size: 12px; padding: 10px; background: var(--green);">
                    <i class="fa-solid fa-check-to-slot mr-1"></i> Konfirmasi Pemasangan
                </button>
            </div>
        `;
    }

    // 2. Jika admin di status SELESAI RAMPUNG / PEMASANGAN SELESAI, munculkan tombol KONFIRMASI SELESAI
    if (os.includes('SELESAI') || os.includes('RAMPUNG')) {
        if (!order.userConfirmedDone) {
            actionButtonHTML = `
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line);">
                    <button onclick="userConfirmDone('${order.id}')" class="btn" style="width: 100%; justify-content: center; font-size: 12px; padding: 10px; background: #0284c7;">
                        <i class="fa-solid fa-circle-check mr-1"></i> Konfirmasi Selesai
                    </button>
                </div>
            `;
        } else {
            actionButtonHTML = `
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--line); text-align: center;">
                    <span style="font-size: 11px; font-weight: 800; color: var(--green);"><i class="fa-solid fa-check-double mr-1"></i> Pengerjaan Telah Dikonfirmasi Selesai</span>
                </div>
            `;
        }
    }

    return `
        <div style="border:1px solid var(--line); border-radius:14px; padding:16px; background:var(--bg); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px dashed #cbd5e1; padding-bottom:12px; margin-bottom:14px;">
                <div>
                    <b style="font-size:14px; color:var(--navy); display:block; font-family:'JetBrains Mono', monospace;">${order.id}</b>
                    <small style="color:var(--muted); font-size:11px; font-weight:600;">Tgl: ${order.date}</small>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:10px; font-weight:800; background:${currentIndex === 3 ? '#10b981' : 'var(--blue)'}; color:white; padding:3px 8px; border-radius:12px; text-transform:uppercase;">
                        ${statuses[currentIndex].label.split(' ')[0]}
                    </span>
                </div>
            </div>
            <div>
                ${timelineHTML}
                ${actionButtonHTML}
            </div>
        </div>
    `;
}

// Aksi User Konfirmasi Pemasangan
function userConfirmInstall(orderId) {
    let myOrders = JSON.parse(localStorage.getItem('tekuninOrders') || '[]');
    let ord = myOrders.find(x => x.id === orderId);
    if(ord) {
        ord.userConfirmedInstall = true;
        localStorage.setItem('tekuninOrders', JSON.stringify(myOrders));
        toast("Konfirmasi pemasangan berhasil dikirim ke teknisi.");
        renderTracking();
    }
}

// Aksi User Konfirmasi Selesai (Menghilangkan tracking secara realtime & kirim pesan ke admin)
async function userConfirmDone(orderId) {
    if(!confirm("Konfirmasi bahwa pengerjaan pemasangan telah selesai? Hasil tracking akan ditutup.")) return;
    
    try {
        let res = await fetch('admin.php?api=user_confirm_done', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: orderId })
        });
        let json = await res.json();
        if(json.success) {
            // Hapus pesanan dari localStorage user secara realtime agar hasil tracking hilang
            let myOrders = JSON.parse(localStorage.getItem('tekuninOrders') || '[]');
            myOrders = myOrders.filter(x => x.id !== orderId);
            localStorage.setItem('tekuninOrders', JSON.stringify(myOrders));
            
            toast("Terima kasih! Pesanan selesai.");
            renderTracking();
        }
    } catch(e) {
        alert("Gagal mengirim konfirmasi.");
    }
}

// Real-time polling tracking & sinkronisasi otomatis
async function pollRealtimeTracking() {
    let myOrders = JSON.parse(localStorage.getItem('tekuninOrders') || '[]');
    if (myOrders.length === 0) return;

    let updated = false;
    let validOrders = [];

    for (let order of myOrders) {
        try {
            let res = await fetch('admin.php?api=check_order&id=' + order.id, { cache: 'no-store' });
            let json = await res.json();
            
            // Jika admin menghapus PO dari dashboard, otomatis hapus tracking di perangkat user
            if (!json.success) {
                updated = true;
                continue; 
            }

            if (json.success && json.data && json.data.status) {
                let latestStatus = json.data.status;
                if (order.status && order.status !== latestStatus) {
                    order.status = latestStatus;
                    updated = true;
                    toast(`Pesanan <b>${order.id}</b> diperbarui: <b>${latestStatus}</b>`);
                } else if (!order.status) {
                    order.status = latestStatus;
                    updated = true;
                }
            }
            validOrders.push(order);
        } catch (e) {
            validOrders.push(order);
        }
    }

    if (updated) {
        localStorage.setItem('tekuninOrders', JSON.stringify(validOrders));
        const trackingModal = document.getElementById("tracking-modal");
        if (trackingModal && trackingModal.style.display === "flex") {
            renderTracking();
        }
    }
}

setInterval(pollRealtimeTracking, 4000);

loadProducts();
loadHouseSimulation();
setInterval(loadProducts, 10000);
setInterval(loadChatHistory, 4000);
saveCart();

// ---------- Tambahan tampilan ----------
renderProducts();   // skeleton saat produk dimuat

(function uiEnhance() {
    // Reveal on scroll
    const els = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((en) => {
            en.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
        }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
        els.forEach(el => io.observe(el));
    } else { els.forEach(el => el.classList.add('in')); }

    // Header shadow + tombol ke atas + menu aktif
    const header = document.getElementById('siteHeader');
    const toTop = document.getElementById('toTop');
    const links = [...document.querySelectorAll('#mainMenu a')];
    const secs = links.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);
    const onScroll = () => {
        const y = window.scrollY || 0;
        header.classList.toggle('scrolled', y > 10);
        toTop.classList.toggle('show', y > 600);
        let cur = null;
        secs.forEach(s => { if (s.getBoundingClientRect().top < 160) cur = s.id; });
        links.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + cur));
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Jaga garis simulator tetap sejajar saat layar berubah
    let rt;
    window.addEventListener('resize', () => {
        clearTimeout(rt);
        rt = setTimeout(() => { try { updateSVGCoords(simData.rumah); } catch(e) {} }, 150);
    });

    // ESC menutup modal / keranjang
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        closeProductModal(); closeCart(); closeCheckoutModal(); closeTrackingModal(); hideSuggest();
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.search')) hideSuggest(); });
})();
</script>

<script>
/* Tambahan khusus tampilan: progres scroll, lensa hero, timecode, sorotan kursor.
   Tidak menyentuh logika katalog, keranjang, pesanan, chat, maupun simulator. */
(function () {
    try {
        var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
        var fine = window.matchMedia && matchMedia('(pointer: fine)').matches;

        // 1) Garis progres scroll di header
        var header = document.getElementById('siteHeader');
        var ticking = false;
        function progress() {
            ticking = false;
            var max = document.documentElement.scrollHeight - window.innerHeight;
            var p = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
            if (header) header.style.setProperty('--p', p.toFixed(4));
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(progress); }
        }, { passive: true });
        progress();

        // 2) Lensa hero: mata mengikuti kursor
        var lens = document.getElementById('lens');
        var visible = true;
        if (lens && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (en) { visible = en[0].isIntersecting; }).observe(lens);
        }
        if (lens && fine && !reduce) {
            var tx = 0, ty = 0, raf = 0;
            var apply = function () {
                raf = 0;
                lens.style.setProperty('--ex', tx.toFixed(2));
                lens.style.setProperty('--ey', ty.toFixed(2));
            };
            var clamp = function (v) { return Math.max(-1, Math.min(1, v)); };
            window.addEventListener('pointermove', function (e) {
                if (!visible) return;
                var r = lens.getBoundingClientRect();
                var dx = (e.clientX - (r.left + r.width / 2)) / Math.max(320, window.innerWidth / 2);
                var dy = (e.clientY - (r.top + r.height / 2)) / Math.max(320, window.innerHeight / 2);
                tx = clamp(dx) * 24; ty = clamp(dy) * 24;
                lens.classList.add('live');
                if (!raf) raf = requestAnimationFrame(apply);
            }, { passive: true });
            document.documentElement.addEventListener('mouseleave', function () { tx = 0; ty = 0; apply(); });
        }

        // 3) Timecode kamera
        var tc = document.getElementById('lensTc');
        if (tc) {
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var tick = function () {
                if (document.hidden || !visible) return;
                var d = new Date();
                tc.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds()) + ':' + pad(Math.floor(d.getMilliseconds() / 40));
            };
            tick();
            setInterval(tick, reduce ? 1000 : 80);
        }

        // 4) Sorotan cahaya mengikuti kursor pada kartu
        if (fine && !reduce) {
            document.addEventListener('pointermove', function (e) {
                var t = e.target && e.target.closest ? e.target.closest('.cat,.card-box,.package') : null;
                if (!t) return;
                var r = t.getBoundingClientRect();
                t.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                t.style.setProperty('--my', (e.clientY - r.top) + 'px');
            }, { passive: true });
        }
    } catch (err) { /* tampilan saja — abaikan */ }
})();
</script>
<script>
/* Loading effect berlogo TEKUNIN: awal akses, perbesar produk, kirim pesanan. Fungsi asli dibungkus, tidak diubah. */
(function(){try{
var L=document.getElementById('rdLoader'),T=document.getElementById('rdLdTxt');if(!L)return;
var t0=Date.now(),closed=false;
function close(){if(closed)return;closed=true;L.classList.add('done');setTimeout(function(){L.classList.remove('mini')},700)}
function fin(){setTimeout(close,Math.max(0,1100-(Date.now()-t0)))}
if(document.readyState==='complete')fin();else addEventListener('load',fin);
setTimeout(close,6000);
var cnt=0,hideT;
function show(msg,ms){cnt++;clearTimeout(hideT);T.textContent=msg;L.classList.add('mini');L.classList.remove('done');
if(ms)hideT=setTimeout(function(){hide()},ms)}
function hide(){cnt=Math.max(0,cnt-1);if(cnt===0){L.classList.add('done');setTimeout(function(){if(L.classList.contains('done'))L.classList.remove('mini')},700)}}
var op=window.openProductModal;
if(typeof op==='function')window.openProductModal=function(){var a=arguments,r;show('Memuat produk',650);try{r=op.apply(this,a)}catch(e){cnt=0;L.classList.add('done');throw e}return r};
var so=window.submitOrder;
if(typeof so==='function')window.submitOrder=async function(){var a=arguments;show('Mengirim pesanan');try{return await so.apply(this,a)}finally{hide()}};
}catch(e){}})();
</script>
</body>
</html>