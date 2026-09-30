import QRCode from 'qrcode';

function renderQrs() {
    document.querySelectorAll('[data-qr]').forEach((el) => {
        const value = el.getAttribute('data-qr') || '';
        if (value === '' || el.dataset.qrDrawn === value) {
            return;
        }

        el.dataset.qrDrawn = value;
        el.replaceChildren();

        const canvas = document.createElement('canvas');
        el.appendChild(canvas);
        QRCode.toCanvas(canvas, value, { width: 240, margin: 1 }).catch(() => {
            el.dataset.qrDrawn = '';
        });
    });
}

function tickCountdowns() {
    document.querySelectorAll('[data-qr-expires]').forEach((el) => {
        const end = Date.parse(el.getAttribute('data-qr-expires') || '');
        if (Number.isNaN(end)) {
            return;
        }

        const left = Math.max(0, Math.floor((end - Date.now()) / 1000));
        if (left === 0) {
            el.textContent = 'QR kedaluwarsa. Buat pesanan baru.';
            el.closest('.panel')?.querySelector('[data-qr]')?.setAttribute('hidden', '');
            return;
        }

        const minutes = String(Math.floor(left / 60)).padStart(2, '0');
        const seconds = String(left % 60).padStart(2, '0');
        el.textContent = `Sisa waktu QR ${minutes}:${seconds}`;
    });
}

function boot() {
    renderQrs();
    tickCountdowns();
}

document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('livewire:init', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morphed', () => boot());
    }
});

setInterval(() => {
    renderQrs();
    tickCountdowns();
}, 1000);
