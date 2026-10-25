@php
    $initialOrderId = \App\Models\Order::max('id') ?? 0;
    $checkUrl = route('admin.orders.checkNew');
    $audioSrc = asset('audio/new-order.mp3');
@endphp

<div id="gs-order-notifier-root" style="display: contents;">
    <!-- Audio Element -->
    <audio id="gs-order-audio" preload="auto">
        <source src="{{ $audioSrc }}" type="audio/mpeg">
    </audio>

    <!-- Floating Toast Container (Only appears when an order is received) -->
    <div id="gs-order-toast-container" style="
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
        max-width: 400px;
        width: calc(100vw - 48px);
    "></div>
</div>

<style>
@keyframes gs-slide-in {
    from {
        transform: translateY(20px) scale(0.96);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}
.gs-order-toast {
    pointer-events: auto;
    background: #0f172a;
    color: #ffffff;
    border: 1px solid #22c55e;
    border-left: 5px solid #22c55e;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5), 0 0 20px rgba(34, 197, 94, 0.25);
    animation: gs-slide-in 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    font-family: inherit;
}
.gs-order-toast:hover {
    box-shadow: 0 12px 35px -5px rgba(0, 0, 0, 0.6), 0 0 25px rgba(34, 197, 94, 0.35);
}
</style>

<script>
(function() {
    let lastKnownOrderId = {{ (int) $initialOrderId }};
    const checkUrl = '{{ $checkUrl }}';
    const pollIntervalMs = 4000; // Check every 4 seconds in the background
    let audioUnlocked = false;
    let ringTimer = null;
    let titleBlinkTimer = null;
    let originalTitle = document.title;
    const audioElement = document.getElementById('gs-order-audio');

    // Unlock audio on first user interaction anywhere
    function unlockAudio() {
        if (audioUnlocked) return;
        if (audioElement) {
            audioElement.volume = 0.01;
            const p = audioElement.play();
            if (p !== undefined) {
                p.then(() => {
                    audioElement.pause();
                    audioElement.currentTime = 0;
                    audioElement.volume = 1.0;
                    audioUnlocked = true;
                }).catch(() => {});
            }
        }
    }
    ['click', 'keydown', 'pointerdown', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, unlockAudio, { once: true });
    });

    // Synthesized Web Audio API fallback chime (~4 seconds)
    function playSynthesizedBell() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            if (ctx.state === 'suspended') {
                ctx.resume();
            }

            const sequence = [
                { f: 659.25, t: 0.0, d: 0.5 }, // E5
                { f: 830.61, t: 0.3, d: 0.5 }, // G#5
                { f: 987.77, t: 0.6, d: 0.7 }, // B5
                { f: 1318.51, t: 1.1, d: 0.9 }, // E6

                { f: 659.25, t: 2.1, d: 0.5 }, // Repeat chime
                { f: 830.61, t: 2.4, d: 0.5 },
                { f: 987.77, t: 2.7, d: 0.7 },
                { f: 1318.51, t: 3.2, d: 0.8 },
            ];

            sequence.forEach(n => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(n.f, ctx.currentTime + n.t);

                gain.gain.setValueAtTime(0.001, ctx.currentTime + n.t);
                gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + n.t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + n.t + n.d);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(ctx.currentTime + n.t);
                osc.stop(ctx.currentTime + n.t + n.d);
            });
        } catch (e) {
            console.warn('Web Audio synthesis error:', e);
        }
    }

    // Play exactly 4-second ring sound
    function playFourSecondRing() {
        if (ringTimer) clearTimeout(ringTimer);

        if (audioElement) {
            audioElement.currentTime = 0;
            audioElement.volume = 1.0;
            const playPromise = audioElement.play();

            if (playPromise !== undefined) {
                playPromise.then(() => {
                    ringTimer = setTimeout(() => {
                        stopRingSound();
                    }, 4000);
                }).catch(err => {
                    playSynthesizedBell();
                });
            } else {
                playSynthesizedBell();
            }
        } else {
            playSynthesizedBell();
        }

        ringTimer = setTimeout(() => {
            stopRingSound();
        }, 4000);
    }

    function stopRingSound() {
        if (ringTimer) {
            clearTimeout(ringTimer);
            ringTimer = null;
        }
        if (audioElement) {
            audioElement.pause();
            audioElement.currentTime = 0;
        }
    }

    // Flashing browser tab title when an order arrives
    function startTitleBlink(orderNo) {
        if (titleBlinkTimer) clearInterval(titleBlinkTimer);
        let toggle = false;
        titleBlinkTimer = setInterval(() => {
            document.title = toggle ? `🔔 (NEW ORDER) ${orderNo}` : originalTitle;
            toggle = !toggle;
        }, 1000);

        const stopTitleBlink = () => {
            if (titleBlinkTimer) {
                clearInterval(titleBlinkTimer);
                titleBlinkTimer = null;
                document.title = originalTitle;
            }
            window.removeEventListener('focus', stopTitleBlink);
            document.removeEventListener('click', stopTitleBlink);
        };
        window.addEventListener('focus', stopTitleBlink);
        document.addEventListener('click', stopTitleBlink);
    }

    // Render toast for new incoming order
    function showToast(order) {
        const container = document.getElementById('gs-order-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'gs-order-toast';

        toast.innerHTML = `
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px; line-height: 1;">🛒</span>
                    <div>
                        <div style="font-size: 13px; font-weight: 700; color: #4ade80; display: flex; align-items: center; gap: 6px;">
                            New Order Received!
                            <span style="font-size: 10px; background: rgba(34,197,94,0.25); color: #86efac; padding: 1px 6px; border-radius: 4px; font-weight: 600;">NEW</span>
                        </div>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 1px;">
                            ${order.order_no} &bull; ${order.time}
                        </div>
                    </div>
                </div>
                <button type="button" class="gs-close-toast" style="
                    background: transparent;
                    border: none;
                    color: #94a3b8;
                    font-size: 16px;
                    cursor: pointer;
                    padding: 0 4px;
                    line-height: 1;
                " title="Dismiss">&times;</button>
            </div>

            <div style="margin-top: 10px; padding: 8px 10px; background: rgba(255,255,255,0.05); border-radius: 8px; font-size: 12px; line-height: 1.5;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #94a3b8;">Customer:</span>
                    <strong style="color: #f1f5f9;">${order.customer_name}</strong>
                </div>
                ${order.customer_phone ? `
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #94a3b8;">Phone:</span>
                    <span style="color: #cbd5e1;">${order.customer_phone}</span>
                </div>` : ''}
                <div style="display: flex; justify-content: space-between; margin-top: 3px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 3px;">
                    <span style="color: #94a3b8;">Total:</span>
                    <strong style="color: #4ade80; font-size: 13px;">Rs ${order.total}</strong>
                </div>
            </div>

            <div style="margin-top: 10px; display: flex; gap: 8px; justify-content: flex-end;">
                ${order.view_url && order.view_url !== '#' ? `
                <a href="${order.view_url}" style="
                    background: #16a34a;
                    color: #ffffff;
                    text-decoration: none;
                    padding: 5px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    display: inline-block;
                    transition: background 0.15s ease;
                " onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                    View Order &rarr;
                </a>` : ''}
                <button type="button" class="gs-dismiss-btn" style="
                    background: rgba(255,255,255,0.1);
                    color: #e2e8f0;
                    border: 1px solid rgba(255,255,255,0.15);
                    padding: 5px 10px;
                    border-radius: 6px;
                    font-size: 12px;
                    cursor: pointer;
                ">
                    Dismiss
                </button>
            </div>
        `;

        const dismiss = () => {
            stopRingSound();
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.25s ease';
            setTimeout(() => toast.remove(), 250);
        };

        toast.querySelector('.gs-close-toast')?.addEventListener('click', dismiss);
        toast.querySelector('.gs-dismiss-btn')?.addEventListener('click', dismiss);

        container.appendChild(toast);

        // Auto remove toast after 14 seconds
        setTimeout(() => {
            if (toast.parentElement) dismiss();
        }, 14000);
    }

    // Refresh Livewire tables (e.g. Orders table) in real time
    function triggerTableRefresh() {
        try {
            if (window.Livewire) {
                window.Livewire.dispatch('refresh');
                document.querySelectorAll('[wire\\:id]').forEach(el => {
                    const comp = window.Livewire.find(el.getAttribute('wire:id'));
                    if (comp && typeof comp.$refresh === 'function') {
                        comp.$refresh();
                    }
                });
            }
        } catch (e) {
            console.warn('Livewire refresh trigger error:', e);
        }
    }

    // Main background polling check function
    async function checkForNewOrders() {
        try {
            const url = `${checkUrl}?last_id=${lastKnownOrderId}&_t=${Date.now()}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 403) {
                return;
            }

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.latest_id) {
                lastKnownOrderId = Math.max(lastKnownOrderId, data.latest_id);
            }

            if (data.has_new && Array.isArray(data.new_orders) && data.new_orders.length > 0) {
                // Multi-tab sync: Avoid playing sound multiple times if another tab already sounded for this order
                const latestNewOrder = data.new_orders[data.new_orders.length - 1];
                const lastSoundedId = parseInt(localStorage.getItem('gs_last_sound_order_id') || '0', 10);

                if (latestNewOrder.id > lastSoundedId) {
                    localStorage.setItem('gs_last_sound_order_id', latestNewOrder.id.toString());
                    playFourSecondRing();
                }

                // Show toast & blink title for each new order
                data.new_orders.forEach(order => {
                    showToast(order);
                });
                startTitleBlink(latestNewOrder.order_no);

                // Auto refresh Livewire table so admin sees the new order immediately
                triggerTableRefresh();
            }
        } catch (err) {
            // Silently continue to next interval
        }
    }

    // Start background polling loop
    setInterval(checkForNewOrders, pollIntervalMs);
})();
</script>
