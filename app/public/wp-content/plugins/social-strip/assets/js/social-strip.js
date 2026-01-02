document.addEventListener('scroll', toggleSocialStrip);
window.addEventListener('resize', alignSocialStrip);
document.addEventListener('DOMContentLoaded', function() {
  toggleSocialStrip();
  alignSocialStrip();
});

function toggleSocialStrip() {
  const vertical = document.querySelector('.kss-vertical');
  const abstract = document.querySelector('.abstract-block');
  const footnotes = document.querySelector('.kss-footnotes');
  if (!vertical || !abstract || !footnotes) return;
  
  const abstractRect = abstract.getBoundingClientRect();
  const abstractBottom = abstractRect.bottom + window.scrollY;
  const footnotesRect = footnotes.getBoundingClientRect();
  const footnotesTop = footnotesRect.top + window.scrollY;
  const scrollY = window.scrollY || window.pageYOffset;
  const winHeight = window.innerHeight;
  const triggerPoint = scrollY + winHeight / 2;
  
  if (triggerPoint >= abstractBottom && triggerPoint < footnotesTop) {
    // If not visible, stage the fade-in
    if (!vertical.classList.contains('kss-active')) {
      vertical.classList.add('kss-prepare');
      requestAnimationFrame(() => {
        vertical.classList.add('kss-active');
        alignSocialStrip();
      });
    } else {
      alignSocialStrip();
    }
  } else {
    vertical.classList.remove('kss-active');
    setTimeout(() => {
      if (!vertical.classList.contains('kss-active')) {
        vertical.classList.remove('kss-prepare');
      }
    }, 500); // match your fade-out time
  }
}



function alignSocialStrip() {
  const sidebar = document.querySelector('#sidebar-body-left');
  const strip = document.querySelector('.kss-vertical');
  if (!sidebar || !strip) return;

  const rect = sidebar.getBoundingClientRect();
  const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
  strip.style.left = (rect.right - strip.offsetWidth - 36 + scrollLeft) + 'px';
  strip.style.right = 'auto';
}
