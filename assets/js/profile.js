(function () {
  if (window.SERVER_MESSAGE) {
    Swal.fire({ icon: 'success', title: 'Success', text: String(window.SERVER_MESSAGE) });
  }
  if (window.SERVER_ERROR) {
    Swal.fire({ icon: 'error', title: 'Error', text: String(window.SERVER_ERROR) });
  }

  const fileInput   = document.getElementById('fileInput');
  const uploader    = document.getElementById('uploader');
  const thumb       = document.getElementById('thumb');
  const avatar      = document.getElementById('profileImagePreview');
  const resetBtn    = document.getElementById('btnResetImage');
  const removeInput = document.getElementById('removeImageInput');
  const chooseLabel = document.querySelector('label[for=fileInput]');
  const form        = document.getElementById('profileForm');
  const saveBtn     = document.getElementById('saveBtn');

  fileInput.addEventListener('click', () => { fileInput.value = ''; });

  function updatePreviewFromFile(file) {
    if (!file) return;
    const allowed = ['image/jpeg', 'image/png', 'image/gif'];
    if (!allowed.includes(file.type)) {
      Swal.fire({ icon: 'error', title: 'Unsupported format', text: 'Please choose a JPG, PNG, or GIF image.' });
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire({ icon: 'error', title: 'Too large', text: 'Max file size is 2MB.' });
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      thumb.src = reader.result;
      avatar.src = reader.result;
      removeInput.value = '0';
    };
    reader.readAsDataURL(file);
  }

  if (chooseLabel) chooseLabel.addEventListener('click', (e) => { e.stopPropagation(); });
  fileInput.addEventListener('change', (e) => updatePreviewFromFile(e.target.files[0]));

  ['dragenter','dragover'].forEach(evt => {
    uploader.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); uploader.classList.add('dragover'); });
  });
  ['dragleave','drop'].forEach(evt => {
    uploader.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); uploader.classList.remove('dragover'); });
  });
  uploader.addEventListener('drop', (e) => {
    const dt = e.dataTransfer; const file = dt.files && dt.files[0];
    if (file) {
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;
      updatePreviewFromFile(file);
    }
  });

  uploader.addEventListener('click', (e) => {
    const isAction = e.target.closest('.actions');
    const isThumb  = e.target.id === 'thumb' || e.target.closest('#thumb');
    if (!isAction && !isThumb) fileInput.click();
  });

  uploader.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
  });

  resetBtn.addEventListener('click', (ev) => {
    ev.stopPropagation();
    Swal.fire({
      title: 'Reset photo to default?',
      text: 'This will immediately save your profile with the default avatar.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#2563eb',
      cancelButtonColor: '#dc2626',
      confirmButtonText: 'Yes, reset & save',
    }).then((result) => {
      if (result.isConfirmed) {
        const defaultSrc = '../assets/uploads/default.jpg';
        thumb.src = defaultSrc;
        avatar.src = defaultSrc;
        removeInput.value = '1';
        fileInput.value = '';
        saveBtn.disabled = true;
        form.submit();
      }
    });
  });
})();
