<?php
header('Content-Type: application/javascript; charset=utf-8');

$cfg = @json_decode(@file_get_contents(__DIR__ . '/settings.json'), true);
$theme = is_array($cfg) && !empty($cfg['theme']) ? $cfg['theme'] : 'default';

$slateDefault = ['50'=>'#f8fafc','100'=>'#f1f5f9','300'=>'#cbd5e1','400'=>'#94a3b8','500'=>'#64748b','600'=>'#475569','700'=>'#334155','800'=>'#1e293b','900'=>'#0f172a'];

$colors = [
  'default' => [
    'blue'  => ['50'=>'#eff6ff','100'=>'#dbeafe','300'=>'#93c5fd','400'=>'#60a5fa','500'=>'#3b82f6','600'=>'#2563eb','700'=>'#1d4ed8','800'=>'#1e40af','900'=>'#1e3a8a'],
    'slate' => $slateDefault,
  ],
  'neon' => [
    'blue'  => ['50'=>'#ecfeff','100'=>'#cffafe','300'=>'#67e8f9','400'=>'#22d3ee','500'=>'#06b6d4','600'=>'#06b6d4','700'=>'#0e7490','800'=>'#155e75','900'=>'#164e63'],
    'slate' => ['50'=>'#cdd6f4','100'=>'#aab3d8','300'=>'#6b7280','400'=>'#4b5563','500'=>'#374151','600'=>'#1f2937','700'=>'#111827','800'=>'#0b0b1f','900'=>'#070710'],
  ],
  'prabanga' => [
    'blue'  => ['50'=>'#faf6ee','100'=>'#f3e9d2','300'=>'#e0c89a','400'=>'#d4b378','500'=>'#c9a96a','600'=>'#b08d4f','700'=>'#8c6f3e','800'=>'#6b542f','900'=>'#4a3a20'],
    'slate' => ['50'=>'#f7f3ec','100'=>'#efe8db','300'=>'#b8ae9c','400'=>'#8a8170','500'=>'#5e5648','600'=>'#3e382c','700'=>'#2a251d','800'=>'#231f18','900'=>'#1a1712'],
  ],
  'retro' => [
    'blue'  => ['50'=>'#fff3e6','100'=>'#ffe0bf','300'=>'#ffab66','400'=>'#ff8533','500'=>'#ff5c00','600'=>'#e65300','700'=>'#b34000','800'=>'#802e00','900'=>'#4d1c00'],
    'slate' => ['50'=>'#fafafa','100'=>'#f4f4f5','300'=>'#999999','400'=>'#666666','500'=>'#444444','600'=>'#2a2a2a','700'=>'#1f1f1f','800'=>'#141414','900'=>'#0a0a0a'],
  ],
];

$fonts = [
  'neon'     => 'https://fonts.googleapis.com/css2?family=Orbitron:wght@600;800&family=Rajdhani:wght@400;500;600&display=swap',
  'prabanga' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=Jost:wght@300;400;500&display=swap',
  'retro'    => 'https://fonts.googleapis.com/css2?family=Archivo+Black&family=Space+Grotesk:wght@400;500;700&display=swap',
];

$css = [
'default' => '',

'neon' => "
body,.bg-gray-100,.bg-gray-50{background:#070710!important;background-image:linear-gradient(rgba(0,229,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,.05) 1px,transparent 1px)!important;background-size:42px 42px!important;color:#cdd6f4!important;font-family:'Rajdhani',sans-serif!important;}
h1,h2,h3,h4,.font-bold{font-family:'Orbitron',sans-serif!important;letter-spacing:2px!important;text-transform:uppercase;}
.text-gray-800,.text-gray-700,.text-gray-600,.text-gray-500,.text-slate-800,.text-slate-700{color:#9aa6e0!important;}
header.bg-slate-900,footer.bg-slate-900{background:rgba(7,7,16,.85)!important;-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border-bottom:1px solid rgba(0,229,255,.4)!important;box-shadow:0 0 30px rgba(0,229,255,.2)!important;}
section.bg-slate-800{background:radial-gradient(circle at 50% 0%,#1a1040,#070710)!important;}
.bg-white{background:rgba(16,16,38,.6)!important;-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);border:1px solid rgba(0,229,255,.3)!important;color:#cdd6f4!important;box-shadow:0 0 26px rgba(0,229,255,.12)!important;transition:transform .2s,box-shadow .2s;}
.bg-white:hover{transform:translateY(-6px);box-shadow:0 0 40px rgba(124,58,237,.4)!important;}
.shadow,.shadow-md,.shadow-lg,.shadow-2xl{box-shadow:0 0 26px rgba(124,58,237,.25)!important;}
.rounded,.rounded-lg,.rounded-xl,.rounded-2xl{border-radius:2px!important;}
img{filter:drop-shadow(0 0 12px rgba(0,229,255,.22));}
.bg-blue-600,.bg-green-600,button.bg-slate-900,a.bg-slate-900{background:linear-gradient(90deg,#00e5ff,#7c3aed)!important;color:#06060f!important;font-weight:700!important;border:none!important;box-shadow:0 0 18px rgba(0,229,255,.5)!important;text-transform:uppercase;letter-spacing:1px;clip-path:polygon(8px 0,100% 0,calc(100% - 8px) 100%,0 100%);}
.bg-blue-600:hover,button.bg-slate-900:hover,a.bg-slate-900:hover{filter:brightness(1.25);}
input,select,textarea{background:#0d0d22!important;color:#cdd6f4!important;border-color:rgba(0,229,255,.4)!important;border-radius:0!important;}
.text-blue-600,.text-blue-500,.text-blue-400,a{color:#00e5ff!important;}
",

'prabanga' => "
body,.bg-gray-100,.bg-gray-50{background:#f7f3ec!important;color:#2b2620!important;font-family:'Jost',sans-serif!important;font-weight:300;letter-spacing:.3px;}
h1,h2,h3,h4{font-family:'Playfair Display',serif!important;font-weight:700!important;letter-spacing:.5px;color:#2b2620;}
h2{text-align:center;position:relative;padding-bottom:18px;}
h2::after{content:'';position:absolute;left:50%;bottom:0;transform:translateX(-50%);width:64px;height:2px;background:#c9a96a;}
.text-gray-800,.text-slate-800{color:#2b2620!important;}
header.bg-slate-900,footer.bg-slate-900{background:#1a1712!important;border-bottom:1px solid #c9a96a!important;}
section.bg-slate-800{background:#231f18!important;}
.bg-white{background:#fffdf9!important;border:1px solid #e6dcc8!important;border-top:3px solid #c9a96a!important;box-shadow:none!important;transition:box-shadow .3s;}
.bg-white:hover{box-shadow:0 18px 40px rgba(180,150,90,.18)!important;}
.shadow,.shadow-md,.shadow-lg{box-shadow:0 12px 34px rgba(180,150,90,.14)!important;}
.rounded,.rounded-lg,.rounded-xl,.rounded-2xl{border-radius:0!important;}
.bg-blue-600{background:#c9a96a!important;color:#1a1712!important;}
.bg-green-600{background:#7d8a5c!important;}
button.bg-slate-900,a.bg-slate-900{background:#1a1712!important;color:#f3ead6!important;}
.bg-blue-600,button.bg-slate-900,a.bg-slate-900,.bg-green-600{border-radius:0!important;text-transform:uppercase;letter-spacing:3px;font-weight:500!important;font-size:.85em;}
.text-blue-600,.text-blue-500,.text-blue-400{color:#b08d4f!important;}
input,select,textarea{border-radius:0!important;border-color:#d8cbb0!important;background:#fffdf9!important;}
#product-grid{gap:2.5rem!important;}
",

'retro' => "
body,.bg-gray-100,.bg-gray-50{background:#ffe9c7!important;background-image:radial-gradient(rgba(26,26,26,.09) 2px,transparent 2px)!important;background-size:18px 18px!important;color:#1a1a1a!important;font-family:'Space Grotesk',sans-serif!important;}
h1,h2,h3,h4{font-family:'Archivo Black',sans-serif!important;text-transform:uppercase;color:#1a1a1a;letter-spacing:-.5px;}
.text-gray-800,.text-gray-700,.text-gray-600,.text-slate-800{color:#1a1a1a!important;}
header.bg-slate-900,footer.bg-slate-900{background:#1a1a1a!important;border-bottom:5px solid #ff5c00!important;}
section.bg-slate-800{background:#ff5c00!important;border-bottom:5px solid #1a1a1a!important;}
section.bg-slate-800 *{color:#1a1a1a!important;}
.bg-white{background:#fffced!important;border:3px solid #1a1a1a!important;box-shadow:7px 7px 0 #1a1a1a!important;transition:transform .12s,box-shadow .12s;}
.bg-white:hover{transform:translate(-3px,-3px);box-shadow:10px 10px 0 #1a1a1a!important;}
.shadow,.shadow-md,.shadow-lg,.shadow-2xl{box-shadow:7px 7px 0 #1a1a1a!important;}
.rounded,.rounded-lg,.rounded-xl,.rounded-2xl,.rounded-full{border-radius:0!important;}
.bg-blue-600{background:#ff5c00!important;color:#1a1a1a!important;}
button.bg-slate-900,a.bg-slate-900{background:#1a1a1a!important;color:#fff!important;}
.bg-green-600{background:#1faa59!important;color:#fff!important;}
.bg-blue-600,button.bg-slate-900,a.bg-slate-900,.bg-green-600{border:3px solid #1a1a1a!important;border-radius:0!important;box-shadow:4px 4px 0 #1a1a1a!important;font-weight:700!important;text-transform:uppercase;transition:transform .1s,box-shadow .1s;}
.bg-blue-600:hover,button.bg-slate-900:hover,a.bg-slate-900:hover{transform:translate(-2px,-2px);box-shadow:6px 6px 0 #1a1a1a!important;}
input,select,textarea{border:3px solid #1a1a1a!important;border-radius:0!important;background:#fffced!important;}
.text-blue-600,.text-blue-500,.text-blue-400{color:#d94800!important;}
",
];

$c = $colors[$theme] ?? $colors['default'];
echo 'tailwind.config = { theme: { extend: { colors: ' . json_encode($c) . ' } } };' . "\n";
echo 'document.documentElement.setAttribute("data-theme", ' . json_encode($theme) . ');' . "\n";

$f = $fonts[$theme] ?? '';
if ($f) {
    echo '(function(){var l=document.createElement("link");l.rel="stylesheet";l.href=' . json_encode($f) . ';document.head.appendChild(l);})();' . "\n";
}
$style = $css[$theme] ?? '';
if ($style !== '') {
    echo '(function(){var s=document.createElement("style");s.textContent=' . json_encode($style) . ';document.head.appendChild(s);})();';
}
