// Auto-scale fixed-size ad iframes/images to fit the header ad slot on all devices
// Works with common ad providers that output fixed 728x90 / 468x60 / 320x50 units
// Strategy: wrap the first ad element in a scaling container and apply CSS transform
// Recomputes on DOMContentLoaded, on window resize, and on ad DOM mutations

(function(){
  // Early exit if not in browser
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }
  
  const SLOT_SELECTOR = '.brandbar .ad-slot';
  
  // Throttle function to limit how often a function can be called
  function throttle(func, limit) {
    let inThrottle;
    return function() {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    }
  }

  function findAdElement(slot){
    // Try common elements in priority order
    let el = slot.querySelector('iframe');
    if (!el) el = slot.querySelector('img');
    if (!el) el = slot.querySelector('ins');
    // As a fallback, take the first element child
    if (!el) el = Array.from(slot.children).find(c => c.nodeType === 1);
    return el || null;
  }

  function getNaturalSize(el){
    let w = parseInt(el.getAttribute && el.getAttribute('width'), 10);
    let h = parseInt(el.getAttribute && el.getAttribute('height'), 10);

    if ((!w || !h) && el.tagName === 'IMG') {
      if (el.naturalWidth && el.naturalHeight) {
        w = el.naturalWidth; h = el.naturalHeight;
      }
    }

    // As another fallback, use current box size
    if ((!w || !h) && el.getBoundingClientRect) {
      const r = el.getBoundingClientRect();
      w = w || Math.round(r.width);
      h = h || Math.round(r.height);
    }

    // Sensible defaults (common leaderboard)
    if (!w || !h) { w = 728; h = 90; }

    return { w, h };
  }

  function ensureWrap(el){
    let wrap = el.parentElement;
    if (!wrap || !wrap.classList.contains('ad-scale-wrap')) {
      wrap = document.createElement('div');
      wrap.className = 'ad-scale-wrap';
      el.parentNode.insertBefore(wrap, el);
      wrap.appendChild(el);
    }
    return wrap;
  }

  function scaleSlot(slot){
    // Exit early if slot is not visible
    if (slot.offsetParent === null) {
      return;
    }
    
    const adEl = findAdElement(slot);
    if (!adEl) return; // nothing yet

    const { w, h } = getNaturalSize(adEl);
    if (!w || !h) return;

    const available = slot.clientWidth || slot.getBoundingClientRect().width;
    if (!available) return;

    const scale = Math.min(1, available / w);
    const targetHeight = Math.round(h * scale);
    const targetWidth = Math.round(w * scale);

    const wrap = ensureWrap(adEl);

    // Reset any widths that may interfere
    adEl.style.width = w + 'px';
    adEl.style.height = h + 'px';
    adEl.style.maxWidth = 'none';
    adEl.style.maxHeight = 'none';
    adEl.style.transformOrigin = 'top left';
    adEl.style.transform = 'scale(' + scale + ')';
    adEl.style.willChange = 'transform'; // Optimize for performance

    // Size the slot to the scaled height so nothing gets cropped
    slot.style.height = targetHeight + 'px';
    slot.style.minHeight = targetHeight + 'px';

    // Match the wrapper to the scaled size and let flex centering do the rest
    wrap.style.transform = 'none';
    wrap.style.width = targetWidth + 'px';
    wrap.style.height = targetHeight + 'px';
    wrap.style.lineHeight = '0';
  }

  function initSlot(slot){
    // Recompute on resize - throttled for performance
    const onResize = throttle(() => scaleSlot(slot), 100);
    window.addEventListener('resize', onResize, { passive: true });

    // Observe ad content injections (common for ad scripts)
    // Use a more efficient MutationObserver configuration
    const mo = new MutationObserver(throttle(() => scaleSlot(slot), 200));
    mo.observe(slot, { 
      childList: true, 
      subtree: true,
      attributes: false, // Don't observe attribute changes for better performance
      characterData: false // Don't observe character data changes for better performance
    });

    // First pass
    scaleSlot(slot);
  }

  function init(){
    // Use querySelectorAll for better performance
    const slots = document.querySelectorAll(SLOT_SELECTOR);
    for (let i = 0; i < slots.length; i++) {
      initSlot(slots[i]);
    }
  }

  // Use a more efficient way to check document ready state
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // Use requestAnimationFrame to ensure DOM is fully ready
    requestAnimationFrame(init);
  }
})();