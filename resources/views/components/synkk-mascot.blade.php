@props([
    'size' => 'w-full h-full',
    'showBadge' => false,
    'badgeText' => 'Syncing 1,420 notes in 280ms...'
])

<div {{ $attributes->merge(['class' => "relative flex items-center justify-center $size group select-none"]) }}>
    <!-- Floating Sync Bubbles -->
    <svg class="absolute inset-0 w-full h-full pointer-events-none overflow-visible" viewBox="0 0 500 500" fill="none">
        <circle cx="120" cy="280" r="14" fill="#67E8F9" fill-opacity="0.5" stroke="#121212" stroke-width="2.5" class="animate-bubble-1" />
        <circle cx="100" cy="220" r="8" fill="#A5F3FC" fill-opacity="0.6" stroke="#121212" stroke-width="2" class="animate-bubble-2" />
        <circle cx="390" cy="260" r="16" fill="#67E8F9" fill-opacity="0.5" stroke="#121212" stroke-width="2.5" class="animate-bubble-3" />
        <circle cx="415" cy="200" r="9" fill="#A5F3FC" fill-opacity="0.6" stroke="#121212" stroke-width="2" class="animate-bubble-1" />
        <circle cx="250" cy="80" r="11" fill="#BAE6FD" fill-opacity="0.4" stroke="#121212" stroke-width="2" class="animate-bubble-2" />
    </svg>

    <!-- Main Animated Octopus Diver -->
    <div class="relative w-full h-full flex items-center justify-center animate-octopus-bob transition-transform duration-300 group-hover:scale-105">
        <svg viewBox="0 0 500 500" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-xl overflow-visible">
            <defs>
                <!-- Body Gradient -->
                <linearGradient id="synkkBodyGrad" x1="250" y1="90" x2="250" y2="440" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#38BDF8" />
                    <stop offset="35%" stop-color="#0284C7" />
                    <stop offset="100%" stop-color="#0369A1" />
                </linearGradient>

                <!-- Helmet Rim Gradient -->
                <linearGradient id="synkkHelmetRim" x1="150" y1="160" x2="350" y2="240" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#0F172A" />
                    <stop offset="100%" stop-color="#1E293B" />
                </linearGradient>

                <!-- Goggles Glass -->
                <linearGradient id="synkkGlassGrad" x1="160" y1="170" x2="340" y2="240" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#ECFEFF" stop-opacity="0.95" />
                    <stop offset="50%" stop-color="#CFFAFE" stop-opacity="0.8" />
                    <stop offset="100%" stop-color="#A5F3FC" stop-opacity="0.9" />
                </linearGradient>

                <!-- Goggles Highlight -->
                <linearGradient id="synkkHighlight" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.85" />
                    <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
                </linearGradient>
            </defs>

            <!-- Back Tentacles (Under layer) -->
            <g class="animate-tentacle-left">
                <!-- Far Left Tentacle Tip -->
                <path d="M140 280 C90 270 45 320 60 380 C70 420 120 420 135 385 C145 360 130 330 160 310" 
                      fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="80" cy="385" r="7" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="95" cy="402" r="6" fill="#7DD3FC" stroke="#121212" stroke-width="2.5" />
            </g>

            <g class="animate-tentacle-right">
                <!-- Far Right Tentacle Tip -->
                <path d="M360 280 C410 270 455 320 440 380 C430 420 380 420 365 385 C355 360 370 330 340 310" 
                      fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="420" cy="385" r="7" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="405" cy="402" r="6" fill="#7DD3FC" stroke="#121212" stroke-width="2.5" />
            </g>

            <!-- Upper Raising Tentacles Left (Curled Up) -->
            <g class="animate-tentacle-left">
                <path d="M165 240 C110 210 90 140 125 105 C155 75 180 120 165 150 C155 170 145 190 180 230" 
                      fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="115" cy="125" r="7" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="125" cy="155" r="6" fill="#7DD3FC" stroke="#121212" stroke-width="2.5" />
            </g>

            <!-- Upper Raising Tentacles Right (Curled Up) -->
            <g class="animate-tentacle-right">
                <path d="M335 240 C390 210 410 140 375 105 C345 75 320 120 335 150 C345 170 355 190 320 230" 
                      fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="385" cy="125" r="7" fill="#7DD3FC" stroke="#121212" stroke-width="3" />
                <circle cx="375" cy="155" r="6" fill="#7DD3FC" stroke="#121212" stroke-width="2.5" />
            </g>

            <!-- Main Lower Body & Center Tentacles (Front Layer) -->
            <!-- Front Center-Left Tentacle -->
            <path d="M210 320 C180 370 150 440 190 465 C225 485 245 440 230 405 C220 380 230 350 240 330" 
                  fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Front Center-Right Tentacle -->
            <path d="M290 320 C320 370 350 440 310 465 C275 485 255 440 270 405 C280 380 270 350 260 330" 
                  fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Mid-Left Outer Tentacle -->
            <path d="M175 300 C130 340 100 400 135 435 C170 465 195 420 185 385 C175 350 190 330 205 315" 
                  fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Mid-Right Outer Tentacle -->
            <path d="M325 300 C370 340 400 400 365 435 C330 465 305 420 315 385 C325 350 310 330 295 315" 
                  fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Octopus Head / Helmet Body Base -->
            <path d="M160 260 C140 180 160 100 250 95 C340 100 360 180 340 260 C330 305 305 330 250 330 C195 330 170 305 160 260 Z" 
                  fill="url(#synkkBodyGrad)" stroke="#121212" stroke-width="8" stroke-linejoin="round" />

            <!-- Helmet Gloss Highlight (Top-left curve) -->
            <path d="M185 125 C210 105 250 105 270 108 C255 118 220 120 195 138 C188 143 182 135 185 125 Z" 
                  fill="#FFFFFF" fill-opacity="0.8" />
            <circle cx="180" cy="155" r="6" fill="#FFFFFF" fill-opacity="0.8" />

            <!-- Scuba Goggles Mask Outer Frame -->
            <!-- Ear / Strap buckles -->
            <rect x="132" y="200" width="22" height="36" rx="8" fill="url(#synkkHelmetRim)" stroke="#121212" stroke-width="6" />
            <rect x="346" y="200" width="22" height="36" rx="8" fill="url(#synkkHelmetRim)" stroke="#121212" stroke-width="6" />

            <!-- Main Goggles Rounded Frame -->
            <path d="M165 175 C165 160 190 155 250 155 C310 155 335 160 335 175 C345 220 345 245 330 255 C305 265 275 250 250 250 C225 250 195 265 170 255 C155 245 155 220 165 175 Z" 
                  fill="url(#synkkHelmetRim)" stroke="#121212" stroke-width="8" stroke-linejoin="round" />

            <!-- Goggles Inner Lens Screen (Cyan Glass) -->
            <path d="M176 182 C178 170 200 166 250 166 C300 166 322 170 324 182 C332 216 332 236 320 244 C300 252 274 238 250 238 C226 238 200 252 180 244 C168 236 168 216 176 182 Z" 
                  fill="url(#synkkGlassGrad)" stroke="#121212" stroke-width="5" stroke-linejoin="round" />

            <!-- Eyes Behind Goggles (Kawaii Glossy animated blink) -->
            <g class="animate-mascot-blink">
                <!-- Left Eye -->
                <circle cx="212" cy="204" r="19" fill="#0A0A0A" />
                <circle cx="206" cy="198" r="7" fill="#FFFFFF" />
                <circle cx="220" cy="211" r="3.5" fill="#FFFFFF" />

                <!-- Right Eye -->
                <circle cx="288" cy="204" r="19" fill="#0A0A0A" />
                <circle cx="282" cy="198" r="7" fill="#FFFFFF" />
                <circle cx="296" cy="211" r="3.5" fill="#FFFFFF" />
            </g>

            <!-- Goggles Glass Reflection Glare (Diagonal Gloss Stripes) -->
            <path d="M185 180 L220 170 L205 238 L178 238 Z" fill="#FFFFFF" fill-opacity="0.45" />
            <path d="M228 170 L242 168 L226 238 L214 238 Z" fill="#FFFFFF" fill-opacity="0.3" />
            <path d="M285 170 L318 180 L308 238 L280 238 Z" fill="#FFFFFF" fill-opacity="0.3" />

            <!-- Cute Smiling Mouth -->
            <path d="M236 280 Q250 294 264 280" fill="none" stroke="#121212" stroke-width="6" stroke-linecap="round" />

            <!-- Cute Blushing Cheeks -->
            <ellipse cx="195" cy="275" rx="8" ry="4.5" fill="#F472B6" fill-opacity="0.45" />
            <ellipse cx="305" cy="275" rx="8" ry="4.5" fill="#F472B6" fill-opacity="0.45" />
        </svg>
    </div>

    <!-- Speech / Status Bubble Badge -->
    @if ($showBadge)
        <div class="absolute -bottom-4 bg-white dark:bg-neutral-900 border-2 border-dark-900 px-4 py-2 rounded-2xl shadow-[4px_4px_0px_0px_#121212] flex items-center gap-2.5 whitespace-nowrap z-20 transition-all duration-300 group-hover:-translate-y-1">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-lime-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-lime-500 border border-dark-900"></span>
            </span>
            <span class="font-mono text-xs font-bold text-dark-900 dark:text-white">
                {{ $badgeText }}
            </span>
        </div>
    @endif
</div>
