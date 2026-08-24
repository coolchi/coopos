export function printReport() {
    const done = () => document.body.classList.remove('printing');

    document.body.classList.add('printing');
    window.addEventListener('afterprint', done, { once: true });
    window.setTimeout(() => {
        window.print();
        window.setTimeout(done, 500);
    }, 50);
}
