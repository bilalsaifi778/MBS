// Admin panel utility script
(function(){
  function addTopbarRow() {
    const modal = document.getElementById('editTopbarMenuModal');
    if (!modal) return;
    const rowsWrap = modal.querySelector('#topbarRows');
    const tpl = modal.querySelector('#topbarRowTpl');
    if (!rowsWrap || !tpl) return;
    if (tpl.content && tpl.content.firstElementChild) {
      rowsWrap.appendChild(tpl.content.firstElementChild.cloneNode(true));
    } else {
      // Fallback HTML
      rowsWrap.insertAdjacentHTML('beforeend', tpl.innerHTML);
    }
  }

  // Global delegation so it works even if modal is injected later
  document.addEventListener('click', function(e){
    const addBtn = e.target.closest('#addTopbarRow');
    if (addBtn) {
      e.preventDefault();
      addTopbarRow();
      return;
    }
    const removeBtn = e.target.closest('.remove-topbar-row');
    if (removeBtn) {
      const row = removeBtn.closest('.topbar-row');
      if (row) row.remove();
      return;
    }
  });

  // Toggle logo source UI
  function updateLogoSourceUI(){
    const modal = document.getElementById('editLogoModal');
    if (!modal) return;
    const urlWrap = modal.querySelector('#logoUrlWrap');
    const fileWrap = modal.querySelector('#logoFileWrap');
    const srcUrl = modal.querySelector('#logoSrcUrl');
    const srcFile = modal.querySelector('#logoSrcFile');
    if (!urlWrap || !fileWrap || !srcUrl || !srcFile) return;
    if (srcFile.checked) {
      fileWrap.style.display = '';
      urlWrap.style.display = 'none';
    } else {
      fileWrap.style.display = 'none';
      urlWrap.style.display = '';
    }
  }
  document.addEventListener('change', function(e){
    if (e.target && (e.target.id === 'logoSrcUrl' || e.target.id === 'logoSrcFile')) {
      updateLogoSourceUI();
    }
  });
  // Initialize on load and when modal is shown
  document.addEventListener('DOMContentLoaded', updateLogoSourceUI);
})();
