// 1) Ask "Are you sure?" before dangerous buttons (add data-confirm="message" to a button).
document.querySelectorAll('[data-confirm]').forEach(el =>
  el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); })
);

// 2) Sales page: add/remove medicine lines and show a live total.
const lines = document.getElementById('lines');
if (lines) {
  const template = lines.querySelector('.line');
  const totalBox = document.getElementById('total');

  const updateTotal = () => {
    let total = 0;
    lines.querySelectorAll('.line').forEach(line => {
      const option = line.querySelector('select').selectedOptions[0];
      const price = parseFloat(option ? option.dataset.price : 0) || 0;
      total += price * (parseInt(line.querySelector('input').value, 10) || 0);
    });
    totalBox.textContent = 'R' + total.toFixed(2);
  };

  document.getElementById('add-line').addEventListener('click', () => {
    const copy = template.cloneNode(true);
    copy.querySelector('select').selectedIndex = 0;
    copy.querySelector('input').value = 1;
    lines.appendChild(copy);
    updateTotal();
  });

  lines.addEventListener('click', e => {
    if (e.target.classList.contains('remove-line') && lines.querySelectorAll('.line').length > 1) {
      e.target.closest('.line').remove();
      updateTotal();
    }
  });

  lines.addEventListener('input', updateTotal);
  updateTotal();
}
