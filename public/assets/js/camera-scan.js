/* Local native BarcodeDetector, with keyboard-wedge/manual fallback. No network dependencies. */
(() => {
  const fields = document.querySelectorAll('[data-camera-scan]');
  for (const field of fields) {
    if (!(field instanceof HTMLInputElement)) continue;
    const button = document.createElement('button');
    button.className = 'btn btn-sm btn-outline-primary ms-2 my-2';
    button.type = 'button';
    button.textContent = 'Scan with camera';
    field.insertAdjacentElement('afterend', button);
    button.addEventListener('click', async () => {
      if (!navigator.mediaDevices?.getUserMedia) {
        window.alert('Camera access requires HTTPS or localhost. Use a USB scanner or enter the code.');
        return;
      }
      let stream;
      const overlay = document.createElement('div');
      overlay.className = 'position-fixed start-0 top-0 w-100 h-100 bg-dark d-flex flex-column align-items-center justify-content-center p-3';
      overlay.style.zIndex = '9999';
      const video = document.createElement('video');
      video.className = 'w-100'; video.style.maxWidth = '580px'; video.setAttribute('playsinline', '');
      const cancel = document.createElement('button');
      cancel.className = 'btn btn-light mt-3'; cancel.type = 'button'; cancel.textContent = 'Close camera';
      overlay.append(video, cancel); document.body.append(overlay);
      let closed = false;
      const close = () => { closed = true; stream?.getTracks().forEach(track => track.stop()); overlay.remove(); };
      cancel.addEventListener('click', close);
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        video.srcObject = stream; await video.play();
        let detector=null;
        if ('BarcodeDetector' in window) {
          try { detector=new BarcodeDetector({formats:['code_128','ean_13','upc_a','qr_code']}); }catch (_) {}
        }
        if (!detector && !window.CafeteriaBarcodeDecoder) {
          await new Promise((resolve,reject) => {
            const script=document.createElement('script');script.src=window.cafeteriaUrl('assets/vendor/cafeteria-barcode-decoder/decoder.js');
            script.onload=resolve;script.onerror=reject;document.head.appendChild(script);
          });
        }
        const canvas=document.createElement('canvas');canvas.width=960;canvas.height=540;
        const context=canvas.getContext('2d',{willReadFrequently:true});
        while (!closed) {
          let decoded=null;
          if (detector) {
            const found=await detector.detect(video);
            decoded=found.length&&found[0].rawValue?found[0].rawValue:null;
          } else if (video.readyState>=2 && context) {
            context.drawImage(video,0,0,canvas.width,canvas.height);
            decoded=window.CafeteriaBarcodeDecoder.decode(canvas);
          }
          if (decoded) {
            field.value = decoded;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', bubbles: true }));
            navigator.vibrate?.(50); close(); break;
          }
          await new Promise(resolve => setTimeout(resolve, 140));
        }
      } catch (error) {
        if (!closed) window.alert('Camera unavailable. Use scanner or keyboard instead.');
        close();
      }
    });
  }
})();
