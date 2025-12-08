<li class="nav nav-item nav-link ps-lg-2 mx-4" wire:poll.20s="refreshCount">
    <a class="nav-link nav-link-bg position-relative" data-bs-toggle="sidebar-right" data-bs-target=".sidebar-right">
        <i class="fe fe-align-right header-icon-svgs"></i>
        @if($count > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; min-width: 1.2rem;">
                {{ $count > 99 ? '99+' : $count }}
            </span>
        @endif
    </a>
</li>

@once
    <script>
        (() => {
            if (window.__chatToneInitialized) {
                return;
            }
            window.__chatToneInitialized = true;

            const getAudioContext = (() => {
                let ctx = null;
                return () => {
                    if (ctx) {
                        return ctx;
                    }

                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (! AudioContext) {
                        return null;
                    }

                    ctx = new AudioContext();
                    return ctx;
                };
            })();

            const playTone = async () => {
                const ctx = getAudioContext();
                if (! ctx) {
                    return;
                }

                if (ctx.state === 'suspended') {
                    try {
                        await ctx.resume();
                    } catch (error) {
                        return;
                    }
                }

                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                oscillator.type = 'triangle';
                oscillator.frequency.setValueAtTime(880, ctx.currentTime);

                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);

                oscillator.connect(gain);
                gain.connect(ctx.destination);

                oscillator.start();
                oscillator.stop(ctx.currentTime + 0.5);
            };

            window.addEventListener('chat-notification-tone', () => {
                playTone();
            });
        })();
    </script>
@endonce
