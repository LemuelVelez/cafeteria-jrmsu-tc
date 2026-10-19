/* Self-hosted camera decoding, with USB/keyboard/manual barcode fallback. */
(() => {
    'use strict';
    const fields = document.querySelectorAll('input[data-camera-scan]');
    for (const field of fields) {
        if (!(field instanceof HTMLInputElement)) continue;
        // Attach controls outside .field-icon so the decorative icon cannot drift below input.
        const host = field.closest('.field-icon') || field;
        let actions = field.parentElement.querySelector('[data-scan-actions]');
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'scan-actions mt-2';
            actions.dataset.scanActions = '';
            host.insertAdjacentElement('afterend', actions);
        }
        const button = document.createElement('button');
        button.className = 'btn btn-outline-primary scan-camera-button';
        button.type = 'button';
        button.dataset.noIcon = '';
        button.innerHTML = '<i class="bi bi-camera" aria-hidden="true"></i><span>Scan with camera</span>';
        actions.prepend(button);

        button.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                window.alert('Camera access requires HTTPS or localhost. Use a USB scanner or enter the code.');
                return;
            }
            button.disabled = true;
            let stream;
            let closed = false;
            const overlay = document.createElement('div');
            overlay.className = 'position-fixed start-0 top-0 w-100 h-100 bg-dark d-flex flex-column align-items-center justify-content-center p-3';
            overlay.style.zIndex = '9999';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', 'Scan barcode with camera');
            const video = document.createElement('video');
            video.className = 'w-100';
            video.style.maxWidth = '580px';
            video.setAttribute('playsinline', '');
            video.muted = true;
            const cancel = document.createElement('button');
            cancel.className = 'btn btn-light mt-3';
            cancel.type = 'button';
            cancel.textContent = 'Close camera';
            overlay.append(video, cancel);
            document.body.append(overlay);
            const close = () => {
                if (closed) return;
                closed = true;
                stream?.getTracks().forEach((track) => track.stop());
                video.srcObject = null;
                overlay.remove();
                button.disabled = false;
                document.removeEventListener('keydown', onEscape);
                field.focus({ preventScroll: true });
            };
            const onEscape = (event) => { if (event.key === 'Escape') close(); };
            cancel.addEventListener('click', close);
            document.addEventListener('keydown', onEscape);
            cancel.focus();
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                if (closed) { stream.getTracks().forEach((track) => track.stop()); return; }
                video.srcObject = stream;
                await video.play();
                if (closed) return;
                let detector = null;
                if ('BarcodeDetector' in window) {
                    try { detector = new BarcodeDetector({ formats: ['code_128', 'ean_13', 'upc_a', 'qr_code'] }); }
                    catch (_) { /* Use bundled decoder. */ }
                }
                if (!detector && !window.CafeteriaBarcodeDecoder) {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = window.cafeteriaUrl('assets/vendor/cafeteria-barcode-decoder/decoder.js');
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                if (closed) return;
                const canvas = document.createElement('canvas');
                canvas.width = 960;
                canvas.height = 540;
                const context = canvas.getContext('2d', { willReadFrequently: true });
                while (!closed) {
                    let decoded = null;
                    if (detector) {
                        const found = await detector.detect(video);
                        decoded = found.length && found[0].rawValue ? found[0].rawValue : null;
                    } else if (video.readyState >= 2 && context) {
                        context.drawImage(video, 0, 0, canvas.width, canvas.height);
                        decoded = window.CafeteriaBarcodeDecoder.decode(canvas);
                    }
                    if (closed) break;
                    if (decoded) {
                        field.value = decoded;
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', bubbles: true }));
                        navigator.vibrate?.(50);
                        close();
                        break;
                    }
                    await new Promise((resolve) => setTimeout(resolve, 140));
                }
            } catch (_) {
                if (!closed) window.alert('Camera unavailable. Use scanner or keyboard instead.');
                close();
            } finally {
                // Covers permission rejection and browsers that disallow camera after interruption.
                if (!closed) close();
            }
        });
    }
})();
