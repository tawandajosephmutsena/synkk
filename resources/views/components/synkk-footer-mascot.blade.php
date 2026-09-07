@props([
    'title' => 'All your devices, dancing in perfect sync.'
])

<div class="relative w-full max-w-5xl mx-auto flex flex-col items-center justify-center select-none py-8">
    
    <!-- Ambient Glow / Starlight behind mascot -->
    <div class="absolute inset-0 bg-gradient-to-t from-[#D4FF00]/15 via-emerald-400/15 to-transparent rounded-full blur-3xl pointer-events-none -z-10"></div>

    <!-- Speech / Title Banner above dancing mascot -->
    <div class="mb-4 inline-flex items-center gap-3 px-6 py-2.5 rounded-full bg-white dark:bg-[#1C1E22] border-2 border-black shadow-[4px_4px_0px_0px_#121212] z-20">
        <span class="text-xl animate-bounce">⚡</span>
        <span class="font-['Figtree',sans-serif] font-black text-base sm:text-xl text-black dark:text-white tracking-tight">
            {{ $title }}
        </span>
        <span class="text-xl animate-bounce">✨</span>
    </div>

    <!-- The Very Large Dancing Mascot Stage (600px+ Canvas) -->
    <div class="relative w-full max-w-[650px] aspect-[1/1] sm:aspect-[4/3] flex items-center justify-center">
        
        <!-- Animated Main SVG Scene -->
        <svg viewBox="0 0 800 650" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full overflow-visible drop-shadow-2xl">
            <defs>
                <!-- Body Gradient -->
                <linearGradient id="footerBodyGrad" x1="400" y1="180" x2="400" y2="520" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#38BDF8" />
                    <stop offset="35%" stop-color="#0284C7" />
                    <stop offset="100%" stop-color="#0369A1" />
                </linearGradient>

                <!-- Goggles Rim -->
                <linearGradient id="footerRimGrad" x1="280" y1="230" x2="520" y2="330" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#0F172A" />
                    <stop offset="100%" stop-color="#1E293B" />
                </linearGradient>

                <!-- Cyan Glass -->
                <linearGradient id="footerGlassGrad" x1="290" y1="240" x2="510" y2="330" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#ECFEFF" stop-opacity="0.95" />
                    <stop offset="50%" stop-color="#CFFAFE" stop-opacity="0.8" />
                    <stop offset="100%" stop-color="#A5F3FC" stop-opacity="0.9" />
                </linearGradient>

                <!-- Glow Filter -->
                <filter id="badgeShadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="3" dy="3" stdDeviation="0" flood-color="#121212" />
                </filter>
            </defs>

            <!-- Glowing Energy Sync Beams to Held Devices -->
            <g class="animate-beam-glow opacity-60">
                <!-- Beam to Top-Left (Mac) -->
                <line x1="400" y1="260" x2="150" y2="130" stroke="#D4FF00" stroke-width="4" stroke-linecap="round" />
                <!-- Beam to Mid-Left (iPhone) -->
                <line x1="400" y1="320" x2="90" y2="280" stroke="#38BDF8" stroke-width="4" stroke-linecap="round" />
                <!-- Beam to Low-Left (Android) -->
                <line x1="400" y1="360" x2="130" y2="460" stroke="#4ADE80" stroke-width="4" stroke-linecap="round" />
                <!-- Beam to Bottom-Left (Linux) -->
                <line x1="400" y1="400" x2="220" y2="580" stroke="#FBBF24" stroke-width="4" stroke-linecap="round" />

                <!-- Beam to Top-Right (Windows) -->
                <line x1="400" y1="260" x2="650" y2="130" stroke="#60A5FA" stroke-width="4" stroke-linecap="round" />
                <!-- Beam to Mid-Right (Docker/Server) -->
                <line x1="400" y1="320" x2="710" y2="280" stroke="#C084FC" stroke-width="4" stroke-linecap="round" />
                <!-- Beam to Low-Right (Obsidian) -->
                <line x1="400" y1="360" x2="670" y2="460" stroke="#D4FF00" stroke-width="4" stroke-linecap="round" />
                <!-- Beam to Bottom-Right (Canvas) -->
                <line x1="400" y1="400" x2="580" y2="580" stroke="#F472B6" stroke-width="4" stroke-linecap="round" />
            </g>

            <!-- Ambient Dancing Air Bubbles & Sparks -->
            <circle cx="260" cy="180" r="14" fill="#67E8F9" fill-opacity="0.6" stroke="#121212" stroke-width="3" class="animate-bubble-1" />
            <circle cx="530" cy="160" r="10" fill="#A5F3FC" fill-opacity="0.7" stroke="#121212" stroke-width="2.5" class="animate-bubble-2" />
            <circle cx="210" cy="380" r="16" fill="#67E8F9" fill-opacity="0.5" stroke="#121212" stroke-width="3" class="animate-bubble-3" />
            <circle cx="590" cy="370" r="12" fill="#BAE6FD" fill-opacity="0.6" stroke="#121212" stroke-width="2.5" class="animate-bubble-1" />

            <!-- Musical & Dancing Sparkles -->
            <text x="280" y="110" font-size="28" class="animate-bounce select-none">🎵</text>
            <text x="500" y="100" font-size="28" class="animate-bounce select-none" style="animation-delay: 0.3s">✨</text>
            <text x="180" y="240" font-size="24" class="animate-pulse select-none">⚡</text>
            <text x="610" y="230" font-size="24" class="animate-pulse select-none" style="animation-delay: 0.5s">⚡</text>

            <!-- ========================================================= -->
            <!-- DANCING OCTOPUS MASCOT CENTER BODY                         -->
            <!-- ========================================================= -->
            <g class="animate-mascot-dance" style="transform-origin: 400px 380px;">
                
                <!-- 8 Reaching & Dancing Tentacles -->
                
                <!-- Tentacle 1: Top-Left (Holding Mac) -->
                <path d="M330 330 C250 280 180 200 150 140" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M330 330 C250 280 180 200 150 140" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="180" cy="190" r="7" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="220" cy="240" r="9" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 2: Mid-Left (Holding iPhone) -->
                <path d="M310 380 C220 370 140 330 95 290" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M310 380 C220 370 140 330 95 290" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="150" cy="335" r="8" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="210" cy="365" r="9" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 3: Lower-Left (Holding Android) -->
                <path d="M330 430 C250 450 170 470 135 465" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M330 430 C250 450 170 470 135 465" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="190" cy="455" r="8" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="255" cy="445" r="9" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 4: Bottom-Left (Holding Linux) -->
                <path d="M360 460 C320 520 260 570 225 580" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M360 460 C320 520 260 570 225 580" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="270" cy="545" r="8" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 5: Top-Right (Holding Windows) -->
                <path d="M470 330 C550 280 620 200 650 140" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M470 330 C550 280 620 200 650 140" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="620" cy="190" r="7" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="580" cy="240" r="9" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 6: Mid-Right (Holding Docker/Server) -->
                <path d="M490 380 C580 370 660 330 705 290" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M490 380 C580 370 660 330 705 290" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="650" cy="335" r="8" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="590" cy="365" r="9" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 7: Lower-Right (Holding Obsidian) -->
                <path d="M470 430 C550 450 630 470 665 465" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M470 430 C550 450 630 470 665 465" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="610" cy="455" r="8" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="545" cy="445" r="9" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Tentacle 8: Bottom-Right (Holding Canvas) -->
                <path d="M440 460 C480 520 540 570 575 580" fill="none" stroke="#121212" stroke-width="26" stroke-linecap="round" />
                <path d="M440 460 C480 520 540 570 575 580" fill="none" stroke="url(#footerBodyGrad)" stroke-width="16" stroke-linecap="round" />
                <circle cx="530" cy="545" r="8" fill="#7DD3FC" stroke="#121212" stroke-width="3" />

                <!-- Central Head & Body -->
                <path d="M290 350 C270 240 295 140 400 135 C505 140 530 240 510 350 C495 410 465 440 400 440 C335 440 305 410 290 350 Z" 
                      fill="url(#footerBodyGrad)" stroke="#121212" stroke-width="10" stroke-linejoin="round" />

                <!-- Shiny Helmet Gloss -->
                <path d="M320 175 C350 150 400 150 425 155 C405 168 360 170 330 190 C322 196 316 186 320 175 Z" fill="#FFFFFF" fill-opacity="0.85" />
                <circle cx="315" cy="210" r="7" fill="#FFFFFF" fill-opacity="0.85" />

                <!-- Mask Side Ear Buckles -->
                <rect x="260" y="260" width="26" height="42" rx="10" fill="url(#footerRimGrad)" stroke="#121212" stroke-width="7" />
                <rect x="514" y="260" width="26" height="42" rx="10" fill="url(#footerRimGrad)" stroke="#121212" stroke-width="7" />

                <!-- Diving Mask Outer Frame -->
                <path d="M295 235 C295 215 330 210 400 210 C470 210 505 215 505 235 C515 285 515 315 495 330 C465 342 430 325 400 325 C370 325 335 342 305 330 C285 315 285 285 295 235 Z" 
                      fill="url(#footerRimGrad)" stroke="#121212" stroke-width="10" stroke-linejoin="round" />

                <!-- Mask Cyan Lens Glass -->
                <path d="M310 245 C312 228 340 224 400 224 C460 224 488 228 490 245 C500 286 500 308 485 318 C460 328 430 312 400 312 C370 312 340 328 315 318 C300 308 300 286 310 245 Z" 
                      fill="url(#footerGlassGrad)" stroke="#121212" stroke-width="6" stroke-linejoin="round" />

                <!-- Joyful Sparkling / Smiling Kawaii Eyes -->
                <g class="animate-mascot-blink">
                    <!-- Left Eye -->
                    <circle cx="350" cy="270" r="22" fill="#0A0A0A" />
                    <circle cx="343" cy="263" r="8" fill="#FFFFFF" />
                    <circle cx="359" cy="278" r="4" fill="#FFFFFF" />

                    <!-- Right Eye (Playful / Joyful Wink) -->
                    <circle cx="450" cy="270" r="22" fill="#0A0A0A" />
                    <circle cx="443" cy="263" r="8" fill="#FFFFFF" />
                    <circle cx="459" cy="278" r="4" fill="#FFFFFF" />
                </g>

                <!-- Glass Reflection Glare -->
                <path d="M320 242 L365 230 L345 312 L312 312 Z" fill="#FFFFFF" fill-opacity="0.4" />
                <path d="M440 230 L480 242 L468 312 L432 312 Z" fill="#FFFFFF" fill-opacity="0.3" />

                <!-- Big Happy Smile -->
                <path d="M380 365 Q400 385 420 365" fill="none" stroke="#121212" stroke-width="7" stroke-linecap="round" />

                <!-- Cheerful Pink Cheeks -->
                <ellipse cx="330" cy="360" rx="12" ry="6" fill="#F472B6" fill-opacity="0.5" />
                <ellipse cx="470" cy="360" rx="12" ry="6" fill="#F472B6" fill-opacity="0.5" />

                <!-- Center Sparking Core Badge -->
                <circle cx="400" cy="410" r="15" fill="#D4FF00" stroke="#121212" stroke-width="4" />
                <text x="400" y="416" font-family="monospace" font-size="14" font-weight="bold" fill="#121212" text-anchor="middle">⚡</text>
            </g>

            <!-- ========================================================= -->
            <!-- 8 HELD DEVICE BADGES (Around Mascot Tentacles)            -->
            <!-- ========================================================= -->

            <!-- 1. Mac Desktop Badge (Top Left) -->
            <g class="animate-device-left" style="transform-origin: 150px 130px;">
                <circle cx="150" cy="130" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="150" cy="130" r="32" fill="#FAF7F0" />
                <text x="150" y="137" font-size="30" text-anchor="middle">💻</text>
                <!-- Label Pill -->
                <rect x="110" y="172" width="80" height="22" rx="11" fill="#121212" stroke="#121212" stroke-width="2" />
                <text x="150" y="187" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">macOS</text>
            </g>

            <!-- 2. iPhone / iPad Badge (Mid Left) -->
            <g class="animate-device-left" style="transform-origin: 90px 280px; animation-delay: 0.3s">
                <circle cx="90" cy="280" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="90" cy="280" r="32" fill="#E0F2FE" />
                <text x="90" y="287" font-size="30" text-anchor="middle">📱</text>
                <!-- Label Pill -->
                <rect x="58" y="322" width="64" height="22" rx="11" fill="#0284C7" stroke="#121212" stroke-width="2" />
                <text x="90" y="337" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">iOS</text>
            </g>

            <!-- 3. Android Badge (Lower Left) -->
            <g class="animate-device-left" style="transform-origin: 130px 460px; animation-delay: 0.6s">
                <circle cx="130" cy="460" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="130" cy="460" r="32" fill="#DCFCE7" />
                <text x="130" y="468" font-size="30" text-anchor="middle">🤖</text>
                <!-- Label Pill -->
                <rect x="88" y="502" width="84" height="22" rx="11" fill="#16A34A" stroke="#121212" stroke-width="2" />
                <text x="130" y="517" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Android</text>
            </g>

            <!-- 4. Linux / Terminal Badge (Bottom Left) -->
            <g class="animate-device-left" style="transform-origin: 220px 580px; animation-delay: 0.9s">
                <circle cx="220" cy="580" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="220" cy="580" r="32" fill="#FEF3C7" />
                <text x="220" y="588" font-size="30" text-anchor="middle">🐧</text>
                <!-- Label Pill -->
                <rect x="183" y="622" width="74" height="22" rx="11" fill="#D97706" stroke="#121212" stroke-width="2" />
                <text x="220" y="637" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Linux</text>
            </g>

            <!-- 5. Windows PC Badge (Top Right) -->
            <g class="animate-device-right" style="transform-origin: 650px 130px;">
                <circle cx="650" cy="130" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="650" cy="130" r="32" fill="#EFF6FF" />
                <text x="650" y="137" font-size="30" text-anchor="middle">🖥️</text>
                <!-- Label Pill -->
                <rect x="605" y="172" width="90" height="22" rx="11" fill="#2563EB" stroke="#121212" stroke-width="2" />
                <text x="650" y="187" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Windows</text>
            </g>

            <!-- 6. Docker / Self-Host Server (Mid Right) -->
            <g class="animate-device-right" style="transform-origin: 710px 280px; animation-delay: 0.3s">
                <circle cx="710" cy="280" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="710" cy="280" r="32" fill="#F3E8FF" />
                <text x="710" y="287" font-size="30" text-anchor="middle">🐳</text>
                <!-- Label Pill -->
                <rect x="668" y="322" width="84" height="22" rx="11" fill="#9333EA" stroke="#121212" stroke-width="2" />
                <text x="710" y="337" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Docker</text>
            </g>

            <!-- 7. Obsidian Vault Badge (Lower Right) -->
            <g class="animate-device-right" style="transform-origin: 670px 460px; animation-delay: 0.6s">
                <circle cx="670" cy="460" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="670" cy="460" r="32" fill="#F5F3FF" />
                <text x="670" y="468" font-size="30" text-anchor="middle">💎</text>
                <!-- Label Pill -->
                <rect x="625" y="502" width="90" height="22" rx="11" fill="#7C3AED" stroke="#121212" stroke-width="2" />
                <text x="670" y="517" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Obsidian</text>
            </g>

            <!-- 8. Canvas & Whiteboards Badge (Bottom Right) -->
            <g class="animate-device-right" style="transform-origin: 580px 580px; animation-delay: 0.9s">
                <circle cx="580" cy="580" r="38" fill="#FFFFFF" stroke="#121212" stroke-width="5" filter="url(#badgeShadow)" />
                <circle cx="580" cy="580" r="32" fill="#FDF2F8" />
                <text x="580" y="588" font-size="30" text-anchor="middle">🎨</text>
                <!-- Label Pill -->
                <rect x="538" y="622" width="84" height="22" rx="11" fill="#DB2777" stroke="#121212" stroke-width="2" />
                <text x="580" y="637" font-family="monospace" font-size="11" font-weight="bold" fill="#FFFFFF" text-anchor="middle">Canvas</text>
            </g>

        </svg>

    </div>

</div>
