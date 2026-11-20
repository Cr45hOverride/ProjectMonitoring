/**
 * KISMEC Intern Task & Project Monitoring System
 * Core Client-Side Logic & Zero-Reload Interactions
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  initDatePickers();
  initModals();
  initLightbox();
  initMobileNav();
});

/**
 * ------------------------------------------------------------------------
 * 1. Mandatory Date Input Standard (Flatpickr Dark Glass Theme)
 * ------------------------------------------------------------------------
 */
function initDatePickers(container = document) {
  if (typeof flatpickr === 'undefined') {
    console.warn('Flatpickr library is not loaded.');
    return;
  }

  // Find all date groups or inputs
  const dateGroups = container.querySelectorAll('.glass-date-group');

  dateGroups.forEach(group => {
    const input = group.querySelector('input');
    const toggleBtn = group.querySelector('.glass-date-toggle');
    if (!input || input._flatpickr) return;

    const isDateTime = input.dataset.enableTime === 'true' || input.name.includes('date') && (input.dataset.time === 'true' || input.id.includes('time') || input.name === 'due_date' || input.name === 'start_date');
    const existingVal = input.value ? input.value.trim() : '';
    const shouldDefaultToday = input.dataset.defaultToday === 'true' || (!existingVal && input.hasAttribute('required'));

    const fpConfig = {
      enableTime: isDateTime,
      time_24hr: false,
      dateFormat: isDateTime ? 'Y-m-d H:i:s' : 'Y-m-d',
      altInput: true,
      altFormat: isDateTime ? 'd M Y, h:i K' : 'd M Y',
      altInputClass: 'glass-input ' + (input.className.replace('glass-input', '').trim()),
      allowInput: true,
      static: false,
      disableMobile: true,
      closeOnSelect: false,
      onReady: function(selectedDates, dateStr, instance) {
        // Match parent container
        if (instance.altInput) {
          instance.altInput.style.paddingRight = '2.75rem';
        }

        // Add Apply footer bar to Flatpickr calendar container
        if (!instance.calendarContainer.querySelector('.flatpickr-footer-bar')) {
          const footerBar = document.createElement('div');
          footerBar.className = 'flatpickr-footer-bar';

          // "Today" quick shortcut button
          const todayBtn = document.createElement('button');
          todayBtn.type = 'button';
          todayBtn.className = 'flatpickr-action-btn flatpickr-btn-today';
          todayBtn.innerHTML = `
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Today
          `;
          todayBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            instance.setDate(new Date(), true);
          });

          // "Apply" confirmation button
          const applyBtn = document.createElement('button');
          applyBtn.type = 'button';
          applyBtn.className = 'flatpickr-action-btn flatpickr-btn-apply';
          applyBtn.innerHTML = `
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
            Apply
          `;
          applyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (instance.selectedDates.length === 0) {
              instance.setDate(new Date(), true);
            }
            if (instance.input) {
              instance.input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            instance.close();
          });

          footerBar.appendChild(todayBtn);
          footerBar.appendChild(applyBtn);
          instance.calendarContainer.appendChild(footerBar);
        }
      }
    };

    if (existingVal) {
      fpConfig.defaultDate = existingVal;
    } else if (shouldDefaultToday) {
      fpConfig.defaultDate = new Date();
    }

    const fp = flatpickr(input, fpConfig);

    // Bind click on toggle icon button to trigger calendar
    if (toggleBtn) {
      toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        fp.open();
      });
    }
  });
}

/**
 * ------------------------------------------------------------------------
 * 2. Toast Notification System
 * ------------------------------------------------------------------------
 */
function showToast(message, type = 'success') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `glass-toast ${type}`;

  const iconSvg = type === 'success' 
    ? '<svg width="20" height="20" fill="none" stroke="#10b981" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
    : type === 'error'
    ? '<svg width="20" height="20" fill="none" stroke="#ef4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>'
    : '<svg width="20" height="20" fill="none" stroke="#f59e0b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';

  toast.innerHTML = `
    <span>${iconSvg}</span>
    <span style="flex:1;">${escapeHtml(message)}</span>
    <button type="button" style="background:transparent;border:none;color:#94a3b8;cursor:pointer;font-size:1.1rem;" onclick="this.parentElement.remove()">&times;</button>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 400);
  }, 4500);
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * ------------------------------------------------------------------------
 * 3. Modal / Slide-over Drawer System
 * ------------------------------------------------------------------------
 */
function initModals() {
  document.querySelectorAll('.glass-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal(modal.id);
      }
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.glass-modal-backdrop');
      if (modal) closeModal(modal.id);
    });
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    if (modalId === 'modalUpdateStage') {
      clearStagedEvidenceFiles();
    }
  }
}

/**
 * ------------------------------------------------------------------------
 * 4. Image Lightbox Modal
 * ------------------------------------------------------------------------
 */
function initLightbox() {
  let lightbox = document.getElementById('imageLightboxModal');
  if (!lightbox) {
    lightbox = document.createElement('div');
    lightbox.id = 'imageLightboxModal';
    lightbox.className = 'glass-modal-backdrop';
    lightbox.innerHTML = `
      <div style="position:relative;max-width:90vw;max-height:90vh;display:flex;align-items:center;justify-content:center;">
        <button type="button" class="modal-close" style="position:absolute;top:-40px;right:0;color:#fff;font-size:2rem;" onclick="closeModal('imageLightboxModal')">&times;</button>
        <img id="lightboxImg" src="" alt="Evidence Full Preview" style="max-width:100%;max-height:85vh;border-radius:12px;border:1px solid rgba(255,255,255,0.2);box-shadow:0 25px 60px rgba(0,0,0,0.8);">
      </div>
    `;
    document.body.appendChild(lightbox);
  }
}

function openImageLightbox(url) {
  const img = document.getElementById('lightboxImg');
  if (img) {
    img.src = url;
    openModal('imageLightboxModal');
  }
}

/**
 * ------------------------------------------------------------------------
 * 5. Task Stage Transition State Machine & Modal Handling
 * ------------------------------------------------------------------------
 */
function openStageModal(task) {
  const modal = document.getElementById('modalUpdateStage');
  if (!modal) return;

  document.getElementById('update_task_id').value = task.task_id;
  document.getElementById('task_modal_title').textContent = task.title;
  document.getElementById('current_stage_badge').innerHTML = task.current_stage_badge || task.current_stage;
  document.getElementById('current_task_stage_val').value = task.current_stage;

  // Populate allowed next stages based on role and current stage
  const stageSelect = document.getElementById('target_stage_select');
  stageSelect.innerHTML = '';

  const userRole = window.APP_USER_ROLE || 'intern';
  const cur = task.current_stage;
  let options = [];

  if (userRole === 'intern') {
    if (cur === 'ASSIGNED') {
      options.push({ value: 'PLANNING', label: 'Start Planning (Submit Plan/Milestones)' });
    } else if (cur === 'PLANNING') {
      options.push({ value: 'BUDGET_PENDING', label: 'Request Budget Approval' });
      options.push({ value: 'IN_EXECUTION', label: 'Proceed to Execution (No Budget Required)' });
    } else if (cur === 'BUDGET_APPROVED') {
      options.push({ value: 'IN_EXECUTION', label: 'Commence Execution' });
    } else if (cur === 'IN_EXECUTION') {
      options.push({ value: 'IN_EXECUTION', label: 'Log Progress Update (Stay In Execution)' });
      options.push({ value: 'COMPLETED_PENDING_REVIEW', label: 'Submit for Supervisor Review & Approval' });
    } else if (cur === 'REVISION_REQUIRED') {
      options.push({ value: 'IN_EXECUTION', label: 'Resume Execution / Apply Fixes' });
      options.push({ value: 'COMPLETED_PENDING_REVIEW', label: 'Resubmit for Final Approval' });
    }
  } else if (userRole === 'supervisor' || userRole === 'admin') {
    if (cur === 'ASSIGNED') {
      options.push({ value: 'PLANNING', label: 'Move to Planning' });
    } else if (cur === 'PLANNING') {
      options.push({ value: 'IN_EXECUTION', label: 'Approve to Start Execution Directly' });
    } else if (cur === 'BUDGET_PENDING') {
      options.push({ value: 'BUDGET_APPROVED', label: 'Approve Budget & Move to Execution' });
      options.push({ value: 'PLANNING', label: 'Reject Budget (Return to Planning)' });
    } else if (cur === 'IN_EXECUTION') {
      options.push({ value: 'IN_EXECUTION', label: 'Supervisor Progress Inspection Note' });
      options.push({ value: 'COMPLETED_PENDING_REVIEW', label: 'Mark Ready for Review' });
    } else if (cur === 'COMPLETED_PENDING_REVIEW') {
      options.push({ value: 'APPROVED', label: 'Sign-off & Approve Task (Complete)' });
      options.push({ value: 'REVISION_REQUIRED', label: 'Request Revisions (Specify in Remarks)' });
    } else if (cur === 'REVISION_REQUIRED') {
      options.push({ value: 'APPROVED', label: 'Bypass & Approve' });
    }
  }

  // Always allow logging comments/evidence in the current stage if no transitions available
  if (options.length === 0) {
    options.push({ value: cur, label: `Log Evidence / Progress in ${cur}` });
  }

  options.forEach(opt => {
    const el = document.createElement('option');
    el.value = opt.value;
    el.textContent = opt.label;
    stageSelect.appendChild(el);
  });

  // Reset form inputs
  document.getElementById('stage_remarks').value = '';
  const refInput = document.getElementById('stage_reference_urls');
  if (refInput) refInput.value = '';
  const stageDateInput = document.getElementById('stage_logged_at');
  if (stageDateInput) {
    if (stageDateInput._flatpickr) {
      stageDateInput._flatpickr.setDate(new Date(), true);
    } else {
      initDatePickers(document.getElementById('modalUpdateStage'));
      if (stageDateInput._flatpickr) {
        stageDateInput._flatpickr.setDate(new Date(), true);
      }
    }
  }
  clearStagedEvidenceFiles();
  toggleBudgetFields(stageSelect.value === 'BUDGET_PENDING');

  openModal('modalUpdateStage');
}

function toggleBudgetFields(show) {
  const budgetBox = document.getElementById('budget_request_fields');
  if (budgetBox) {
    budgetBox.style.display = show ? 'block' : 'none';
    const amountInput = document.getElementById('budget_amount');
    const justInput = document.getElementById('budget_justification');
    if (amountInput) amountInput.required = show;
    if (justInput) justInput.required = show;
  }
}

// Stage select change listener
document.addEventListener('change', (e) => {
  if (e.target && e.target.id === 'target_stage_select') {
    toggleBudgetFields(e.target.value === 'BUDGET_PENDING');
  }
});

/**
 * ------------------------------------------------------------------------
 * Multi-File Evidence Staging & Dropzone Manager
 * ------------------------------------------------------------------------
 */
let stagedEvidenceFiles = [];

function clearStagedEvidenceFiles() {
  stagedEvidenceFiles.forEach(item => {
    if (item.previewUrl) {
      URL.revokeObjectURL(item.previewUrl);
    }
  });
  stagedEvidenceFiles = [];
  const preview = document.getElementById('mediaPreviewContainer');
  if (preview) preview.innerHTML = '';
  const fileInput = document.getElementById('evidence_files');
  if (fileInput) fileInput.value = '';
}
window.clearStagedEvidenceFiles = clearStagedEvidenceFiles;

function addStagedFiles(fileList) {
  if (!fileList || fileList.length === 0) return;
  
  const allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'];
  let addedCount = 0;

  Array.from(fileList).forEach(file => {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExts.includes(ext)) {
      showToast(`Skipped "${file.name}": Unsupported format. Allowed: JPG, PNG, WEBP, MP4, MOV`, 'warning');
      return;
    }

    // Check duplicate by name + size + lastModified
    const isDup = stagedEvidenceFiles.some(item => 
      item.file.name === file.name && item.file.size === file.size && item.file.lastModified === file.lastModified
    );
    if (isDup) return;

    let previewUrl = null;
    if (file.type.startsWith('image/')) {
      previewUrl = URL.createObjectURL(file);
    }

    stagedEvidenceFiles.push({
      file: file,
      previewUrl: previewUrl,
      id: 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
    });
    addedCount++;
  });

  if (addedCount > 0) {
    renderStagedFilesPreview();
  }
}
window.addStagedFiles = addStagedFiles;

function removeStagedFile(id) {
  const idx = stagedEvidenceFiles.findIndex(item => item.id === id);
  if (idx !== -1) {
    if (stagedEvidenceFiles[idx].previewUrl) {
      URL.revokeObjectURL(stagedEvidenceFiles[idx].previewUrl);
    }
    stagedEvidenceFiles.splice(idx, 1);
    renderStagedFilesPreview();
  }
}
window.removeStagedFile = removeStagedFile;

function renderStagedFilesPreview() {
  const container = document.getElementById('mediaPreviewContainer');
  if (!container) return;

  if (stagedEvidenceFiles.length === 0) {
    container.innerHTML = '';
    return;
  }

  const totalBytes = stagedEvidenceFiles.reduce((acc, curr) => acc + curr.file.size, 0);
  const formattedTotal = (totalBytes > 1048576) 
    ? (totalBytes / 1048576).toFixed(1) + ' MB' 
    : Math.round(totalBytes / 1024) + ' KB';

  let html = `
    <div class="upload-summary-bar">
      <span style="color:#c7d2fe;font-weight:600;">
        📸 ${stagedEvidenceFiles.length} file${stagedEvidenceFiles.length > 1 ? 's' : ''} staged (${formattedTotal})
      </span>
      <button type="button" id="btnClearAllStaged" class="glass-btn glass-btn-sm" style="padding:0.2rem 0.55rem;font-size:0.7rem;background:rgba(239,68,68,0.25);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);">
        Clear All
      </button>
    </div>
    <div class="upload-preview-grid">
  `;

  stagedEvidenceFiles.forEach(item => {
    const file = item.file;
    const isImg = file.type.startsWith('image/');
    const isVid = file.type.startsWith('video/');
    const formattedSize = Math.round(file.size / 1024) + ' KB';

    let mediaThumb = '';
    if (isImg && item.previewUrl) {
      mediaThumb = `<img src="${item.previewUrl}" alt="${escapeHtml(file.name)}">`;
    } else if (isVid) {
      mediaThumb = `<div style="text-align:center;color:#38bdf8;"><svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg><div style="font-size:0.65rem;margin-top:2px;">VIDEO</div></div>`;
    } else {
      mediaThumb = `<div style="font-size:1.5rem;">📎</div>`;
    }

    html += `
      <div class="upload-preview-card" data-file-id="${item.id}">
        <button type="button" class="upload-preview-remove" title="Remove this file" onclick="removeStagedFile('${item.id}')">&times;</button>
        <div class="upload-preview-media">
          ${mediaThumb}
        </div>
        <div class="upload-preview-info">
          <div class="upload-preview-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
          <div class="upload-preview-size">${formattedSize}</div>
        </div>
      </div>
    `;
  });

  html += `</div>`;
  container.innerHTML = html;

  const btnClear = document.getElementById('btnClearAllStaged');
  if (btnClear) {
    btnClear.addEventListener('click', clearStagedEvidenceFiles);
  }
}

// Dropzone click & keyboard trigger
document.addEventListener('click', (e) => {
  const dropzone = e.target.closest('#evidenceDropzone');
  if (dropzone && !e.target.closest('.upload-preview-remove') && !e.target.closest('#btnClearAllStaged')) {
    const fileInput = document.getElementById('evidence_files');
    if (fileInput) fileInput.click();
  }
});

document.addEventListener('keydown', (e) => {
  if ((e.key === 'Enter' || e.key === ' ') && e.target && e.target.id === 'evidenceDropzone') {
    e.preventDefault();
    const fileInput = document.getElementById('evidence_files');
    if (fileInput) fileInput.click();
  }
});

// File input change: add files and reset input.value so user can add more in subsequent clicks
document.addEventListener('change', (e) => {
  if (e.target && e.target.id === 'evidence_files') {
    if (e.target.files && e.target.files.length > 0) {
      addStagedFiles(e.target.files);
      e.target.value = '';
    }
  }
});

// Drag and drop events for dropzone
['dragenter', 'dragover'].forEach(eventName => {
  document.addEventListener(eventName, (e) => {
    const dropzone = e.target.closest('#evidenceDropzone');
    if (dropzone) {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.add('dragover');
    }
  });
});

['dragleave', 'drop'].forEach(eventName => {
  document.addEventListener(eventName, (e) => {
    const dropzone = e.target.closest('#evidenceDropzone');
    if (dropzone) {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.remove('dragover');
      if (eventName === 'drop' && e.dataTransfer && e.dataTransfer.files) {
        addStagedFiles(e.dataTransfer.files);
      }
    }
  });
});

/**
 * Stage Update Form Submission via AJAX
 */
document.addEventListener('submit', async (e) => {
  if (e.target && e.target.id === 'formUpdateStage') {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
      <svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-width="4" stroke="currentColor" stroke-dasharray="32" stroke-dashoffset="10"></circle></svg>
      Processing...
    `;

    try {
      const formData = new FormData(form);

      // Clean out any direct file inputs and append all staged evidence files
      formData.delete('evidence_files[]');
      formData.delete('evidence_files');
      stagedEvidenceFiles.forEach(item => {
        formData.append('evidence_files[]', item.file, item.file.name);
      });

      const res = await fetch(window.BASE_URL + '/api/update_task_stage.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      if (data.success) {
        showToast(data.message || 'Task status successfully updated!', 'success');
        clearStagedEvidenceFiles();
        closeModal('modalUpdateStage');
        setTimeout(() => window.location.reload(), 700);
      } else {
        showToast(data.message || 'Failed to update task stage.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Network error during stage update. Please retry.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }
});

/**
 * ------------------------------------------------------------------------
 * 6. Task Creation & Deletion Form Submissions via AJAX
 * ------------------------------------------------------------------------
 */
async function deleteTask(taskId, taskTitle) {
  if (!confirm(`Are you sure you want to permanently delete Task #${taskId} ("${taskTitle}")?\n\nThis will remove all associated audit trail logs, uploaded media evidence files, and budget requests. This action cannot be undone.`)) {
    return;
  }

  try {
    const res = await fetch(window.BASE_URL + '/api/delete_task.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ task_id: taskId })
    });

    const data = await res.json();
    if (data.success) {
      showToast(data.message || 'Task deleted successfully!', 'success');
      const row = document.getElementById(`task-row-${taskId}`) || document.querySelector(`tr[data-task-id="${taskId}"]`);
      if (row) {
        row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        row.style.opacity = '0';
        row.style.transform = 'scale(0.95)';
        setTimeout(() => row.remove(), 400);
      } else {
        setTimeout(() => window.location.reload(), 700);
      }
    } else {
      showToast(data.message || 'Failed to delete task.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error while deleting task.', 'error');
  }
}
document.addEventListener('submit', async (e) => {
  if (e.target && e.target.id === 'formCreateTask') {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Creating Task...';

    try {
      const formData = new FormData(form);
      const res = await fetch(window.BASE_URL + '/api/create_task.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      if (data.success) {
        showToast(data.message || 'Task created successfully!', 'success');
        closeModal('modalCreateTask');
        setTimeout(() => window.location.reload(), 700);
      } else {
        showToast(data.message || 'Failed to create task.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Network error during task creation.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }
});

/**
 * ------------------------------------------------------------------------
 * 7. Budget Review Actions (Approve / Reject via AJAX)
 * ------------------------------------------------------------------------
 */
async function reviewBudget(budgetId, status) {
  const actionText = status === 'approved' ? 'Approve' : 'Reject';
  if (!confirm(`Are you sure you want to ${actionText.toUpperCase()} this budget request?`)) {
    return;
  }

  try {
    const res = await fetch(window.BASE_URL + '/api/review_budget.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ budget_id: budgetId, status: status })
    });

    const data = await res.json();
    if (data.success) {
      showToast(data.message || `Budget request ${status} successfully!`, 'success');
      setTimeout(() => window.location.reload(), 700);
    } else {
      showToast(data.message || 'Failed to review budget.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error while processing budget review.', 'error');
  }
}

/**
 * ------------------------------------------------------------------------
 * 8. User Management (Add / Edit / Toggle Status via AJAX)
 * ------------------------------------------------------------------------
 */
function openUserModal(userData = null) {
  const modal = document.getElementById('modalUser');
  if (!modal) return;

  const title = document.getElementById('user_modal_title');
  const form = document.getElementById('formUser');
  form.reset();

  if (userData) {
    title.textContent = 'Edit User Account';
    document.getElementById('user_id').value = userData.user_id;
    document.getElementById('user_full_name').value = userData.full_name;
    document.getElementById('user_email').value = userData.email;
    document.getElementById('user_role').value = userData.role;
    document.getElementById('user_department').value = userData.department || '';
    document.getElementById('user_supervisor_id').value = userData.supervisor_id || '';
    document.getElementById('user_password').required = false;
    document.getElementById('password_hint').textContent = '(Leave blank to retain current password)';

    // Date inputs
    const startInput = document.getElementById('intern_start_date');
    const endInput = document.getElementById('intern_end_date');
    if (startInput && userData.intern_start_date) {
      startInput.value = userData.intern_start_date;
      if (startInput._flatpickr) startInput._flatpickr.setDate(userData.intern_start_date);
    }
    if (endInput && userData.intern_end_date) {
      endInput.value = userData.intern_end_date;
      if (endInput._flatpickr) endInput._flatpickr.setDate(userData.intern_end_date);
    }
  } else {
    title.textContent = 'Add New User Account';
    document.getElementById('user_id').value = '';
    document.getElementById('user_password').required = true;
    document.getElementById('password_hint').textContent = '(Minimum 6 characters)';
  }

  toggleInternFields();
  openModal('modalUser');
}

function toggleInternFields() {
  const roleSelect = document.getElementById('user_role');
  const internFields = document.getElementById('intern_specific_fields');
  if (roleSelect && internFields) {
    internFields.style.display = roleSelect.value === 'intern' ? 'block' : 'none';
  }
}

document.addEventListener('change', (e) => {
  if (e.target && e.target.id === 'user_role') {
    toggleInternFields();
  }
});

document.addEventListener('submit', async (e) => {
  if (e.target && e.target.id === 'formUser') {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Saving...';

    try {
      const formData = new FormData(form);
      const res = await fetch(window.BASE_URL + '/api/manage_user.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      if (data.success) {
        showToast(data.message || 'User saved successfully!', 'success');
        closeModal('modalUser');
        setTimeout(() => window.location.reload(), 700);
      } else {
        showToast(data.message || 'Failed to save user.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Network error while saving user.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }
});

async function toggleUserStatus(userId, currentStatus) {
  const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
  if (!confirm(`Are you sure you want to change user status to ${newStatus.toUpperCase()}?`)) {
    return;
  }

  try {
    const res = await fetch(window.BASE_URL + '/api/manage_user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_status', user_id: userId, status: newStatus })
    });

    const data = await res.json();
    if (data.success) {
      showToast(`User status set to ${newStatus}!`, 'success');
      setTimeout(() => window.location.reload(), 600);
    } else {
      showToast(data.message || 'Failed to update user status.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error while updating user status.', 'error');
  }
}

/**
 * ------------------------------------------------------------------------
 * 9. Interactive Task Report Viewer (Live AJAX on reports.php)
 * ------------------------------------------------------------------------
 */
async function loadTaskReport(taskId) {
  const previewContainer = document.getElementById('reportPreviewArea');
  const exportBtn = document.getElementById('btnExportPdf');
  if (!previewContainer) return;

  if (!taskId) {
    previewContainer.innerHTML = `
      <div style="text-align:center;padding:4rem 1rem;color:#94a3b8;">
        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 1rem;opacity:0.4;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p style="font-size:1.1rem;font-weight:600;color:#cbd5e1;">Select a task above to load the live audit report</p>
        <p style="font-size:0.85rem;margin-top:0.25rem;">Preview SLA metrics, chronological lifecycle logs, and media evidence.</p>
      </div>
    `;
    if (exportBtn) exportBtn.style.display = 'none';
    return;
  }

  previewContainer.innerHTML = `
    <div style="text-align:center;padding:3rem 1rem;color:#94a3b8;">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="animation:spin 1s linear infinite;margin:0 auto 1rem;"><circle cx="12" cy="12" r="10" stroke-width="4" stroke="currentColor" stroke-dasharray="32" stroke-dashoffset="10"></circle></svg>
      <p>Loading full task audit data...</p>
    </div>
  `;

  try {
    const res = await fetch(`${window.BASE_URL}/api/get_task_report.php?task_id=${taskId}`);
    const data = await res.json();

    if (!data.success || !data.task) {
      previewContainer.innerHTML = `<div class="glass-toast error" style="margin:2rem auto;max-width:500px;">${escapeHtml(data.message || 'Report not found.')}</div>`;
      if (exportBtn) exportBtn.style.display = 'none';
      return;
    }

    const t = data.task;

    // Update PDF export button
    if (exportBtn) {
      exportBtn.href = `${window.BASE_URL}/export_report_pdf.php?task_id=${t.task_id}`;
      exportBtn.style.display = 'inline-flex';
    }

    // Render Live Glassmorphism Preview
    let html = `
      <!-- Header Banner -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid rgba(255,255,255,0.1);">
        <div>
          <span style="font-size:0.8rem;color:#94a3b8;font-weight:700;letter-spacing:0.05em;">TASK REF: #${t.task_id}</span>
          <h2 style="font-size:1.5rem;font-weight:700;color:#fff;margin:0.25rem 0 0.5rem;">${escapeHtml(t.title)}</h2>
          <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
            ${t.stage_badge}
            ${t.priority_badge}
            <span class="glass-badge ${t.sla.badge_cls}">${t.sla.label} (${t.sla.summary})</span>
          </div>
        </div>
      </div>

      <!-- Metadata Grid -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="glass-card-sm">
          <span style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Assigned Intern</span>
          <p style="font-weight:700;color:#fff;margin-top:0.2rem;font-size:0.95rem;">${escapeHtml(t.intern_name)}</p>
          <span style="font-size:0.8rem;color:#cbd5e1;">${escapeHtml(t.intern_dept || 'Engineering')} &bull; ${escapeHtml(t.intern_email)}</span>
        </div>
        <div class="glass-card-sm">
          <span style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Supervisor</span>
          <p style="font-weight:700;color:#fff;margin-top:0.2rem;font-size:0.95rem;">${escapeHtml(t.supervisor_name)}</p>
          <span style="font-size:0.8rem;color:#cbd5e1;">${escapeHtml(t.supervisor_dept || 'Supervision')}</span>
        </div>
        <div class="glass-card-sm">
          <span style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">SLA Schedule & Milestones</span>
          <div style="font-size:0.8rem;color:#cbd5e1;margin-top:0.35rem;display:flex;flex-direction:column;gap:0.2rem;">
            <div><strong>Start:</strong> ${escapeHtml(t.formatted_start_date)}</div>
            <div><strong>Due:</strong> ${escapeHtml(t.formatted_due_date)}</div>
            <div><strong>Completed:</strong> ${escapeHtml(t.formatted_completed_at)}</div>
            <div><strong>Approved:</strong> ${escapeHtml(t.formatted_approved_at)}</div>
          </div>
        </div>
      </div>

      <!-- Description -->
      ${t.description ? `
        <div class="glass-card-sm" style="margin-bottom:1.5rem;">
          <span style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Scope & Description</span>
          <p style="color:#e2e8f0;font-size:0.9rem;margin-top:0.35rem;line-height:1.6;white-space:pre-line;">${escapeHtml(t.description)}</p>
        </div>
      ` : ''}

      <!-- Budget Summary (if any) -->
      ${t.budgets && t.budgets.length > 0 ? `
        <div class="glass-card-sm" style="margin-bottom:1.5rem;border-left:3px solid #6366f1;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
            <span style="font-size:0.8rem;color:#a5b4fc;text-transform:uppercase;font-weight:700;">Financial Resource & Budget Request</span>
            ${t.budgets[0].status_badge}
          </div>
          <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:0.5rem;">
            <div style="font-size:1.4rem;font-weight:800;color:#fff;">RM ${parseFloat(t.budgets[0].amount).toFixed(2)}</div>
            <div style="font-size:0.8rem;color:#94a3b8;">Requested: ${escapeHtml(t.budgets[0].formatted_requested_at)}</div>
          </div>
          <p style="font-size:0.85rem;color:#cbd5e1;margin-top:0.35rem;"><strong>Justification:</strong> ${escapeHtml(t.budgets[0].justification)}</p>
          ${t.budgets[0].reviewer_name ? `<p style="font-size:0.8rem;color:#94a3b8;margin-top:0.25rem;">Reviewed by: ${escapeHtml(t.budgets[0].reviewer_name)} (${escapeHtml(t.budgets[0].formatted_reviewed_at)})</p>` : ''}
        </div>
      ` : ''}

      <!-- Chronological Audit Timeline -->
      <h3 style="font-size:1.1rem;font-weight:700;color:#fff;margin-top:2rem;display:flex;align-items:center;gap:0.5rem;">
        <svg width="20" height="20" fill="none" stroke="#6366f1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Chronological Process Audit Trail (${t.logs ? t.logs.length : 0} Entries)
      </h3>
      <div class="timeline-container">
    `;

    if (t.logs && t.logs.length > 0) {
      t.logs.forEach(log => {
        let referencesHtml = '';
        if (log.parsed_references && log.parsed_references.length > 0) {
          referencesHtml = `
            <div class="reference-links-box">
              <div class="reference-box-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                Reference Links & Technical URLs:
              </div>
              <div class="reference-link-items">
          `;
          log.parsed_references.forEach(ref => {
            referencesHtml += `
              <a href="${escapeHtml(ref.url)}" target="_blank" rel="noopener noreferrer" class="reference-link-pill" title="${escapeHtml(ref.raw)}">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                <span>${escapeHtml(ref.display)}</span>
              </a>
            `;
          });
          referencesHtml += `</div></div>`;
        }

        let mediaHtml = '';
        if (log.media && log.media.length > 0) {
          mediaHtml = '<div class="media-gallery">';
          log.media.forEach(m => {
            if (m.file_type === 'image') {
              mediaHtml += `
                <div class="media-thumb-card" onclick="openImageLightbox('${m.full_url}')" title="Click to view full image">
                  <img src="${m.full_url}" alt="Evidence thumbnail" loading="lazy">
                </div>
              `;
            } else if (m.file_type === 'video') {
              mediaHtml += `
                <a href="${m.full_url}" target="_blank" rel="noopener noreferrer" class="media-video-pill">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  Play Video Evidence (${escapeHtml(m.uploaded_at)})
                </a>
              `;
            }
          });
          mediaHtml += '</div>';
        }

        html += `
          <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-item-content">
              <div class="timeline-header">
                <div>
                  <span class="timeline-actor">${escapeHtml(log.actor_name)}</span>
                  <span style="font-size:0.75rem;color:#94a3b8;margin-left:0.5rem;text-transform:uppercase;">(${escapeHtml(log.actor_role)})</span>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  ${log.stage_badge}
                  <span class="timeline-time">${escapeHtml(log.formatted_logged_at)}</span>
                  <button type="button" class="glass-btn glass-btn-sm" style="padding:0.25rem 0.55rem;font-size:0.75rem;margin-left:0.35rem;" onclick='openEditLogModal(${JSON.stringify({
                    log_id: log.log_id,
                    task_id: t.task_id,
                    stage_name: log.stage_name,
                    stage_badge: log.stage_badge,
                    actor_name: log.actor_name,
                    actor_role: log.actor_role,
                    formatted_logged_at: log.formatted_logged_at,
                    raw_logged_at: log.raw_logged_at || log.logged_at || "",
                    remarks: log.raw_remarks || log.remarks || "",
                    reference_urls: log.raw_reference_urls || log.reference_urls || ""
                  }).replace(/'/g, "&#39;")})' title="Edit Activity Remarks & References">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Edit
                  </button>
                  ${window.APP_USER_ROLE === 'admin' ? `
                    <button type="button" class="glass-btn glass-btn-sm" style="padding:0.25rem 0.55rem;font-size:0.75rem;background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);" onclick="deleteTaskLog(${log.log_id}, ${t.task_id}, '${escapeHtml(log.stage_name)}')" title="Delete this audit trail entry (Admin only)">
                      <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      Delete
                    </button>
                  ` : ''}
                </div>
              </div>
              <div class="timeline-remarks">${escapeHtml(log.remarks || 'No detailed remarks recorded.')}</div>
              ${referencesHtml}
              ${mediaHtml}
            </div>
          </div>
        `;
      });
    } else {
      html += '<p style="color:#94a3b8;padding:1rem 0;">No audit trail events logged yet.</p>';
    }

    html += '</div>';
    previewContainer.innerHTML = html;

  } catch (err) {
    console.error(err);
    previewContainer.innerHTML = '<div class="glass-toast error">Failed to load task report via AJAX.</div>';
  }
}

/**
 * ------------------------------------------------------------------------
 * 10. Edit Activity Log (Remarks & Reference URLs)
 * ------------------------------------------------------------------------
 */
function openEditLogModal(log) {
  const modal = document.getElementById('modalEditLog');
  if (!modal) return;

  document.getElementById('edit_log_id').value = log.log_id;
  document.getElementById('edit_log_task_id').value = log.task_id;
  document.getElementById('edit_log_subtitle').textContent = `Logged by ${log.actor_name} (${log.actor_role}) on ${log.formatted_logged_at}`;
  document.getElementById('edit_log_stage_badge').innerHTML = log.stage_badge || log.stage_name;
  document.getElementById('edit_log_remarks').value = log.remarks || '';
  document.getElementById('edit_log_reference_urls').value = log.reference_urls || '';

  const editDateInput = document.getElementById('edit_log_logged_at');
  if (editDateInput) {
    const targetDate = log.raw_logged_at || log.logged_at || new Date();
    if (editDateInput._flatpickr) {
      editDateInput._flatpickr.setDate(targetDate, true);
    } else {
      initDatePickers(modal);
      if (editDateInput._flatpickr) {
        editDateInput._flatpickr.setDate(targetDate, true);
      }
    }
  }

  const btnModalDelete = document.getElementById('btnModalDeleteLog');
  if (btnModalDelete) {
    btnModalDelete.onclick = () => {
      deleteTaskLog(log.log_id, log.task_id, log.stage_name);
    };
  }

  openModal('modalEditLog');
}

document.addEventListener('submit', async (e) => {
  if (e.target && e.target.id === 'formEditLog') {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Updating...';

    try {
      const formData = new FormData(form);
      const res = await fetch(window.BASE_URL + '/api/update_task_log.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      if (data.success) {
        showToast(data.message || 'Activity log updated successfully!', 'success');
        closeModal('modalEditLog');
        const taskId = document.getElementById('edit_log_task_id').value;
        if (typeof loadTaskReport === 'function') {
          loadTaskReport(taskId);
        }
      } else {
        showToast(data.message || 'Failed to update activity log.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Network error while updating activity log.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }
});

/**
 * ------------------------------------------------------------------------
 * 11. Delete Process Audit Log Entry (Admin Only)
 * ------------------------------------------------------------------------
 */
async function deleteTaskLog(logId, taskId, stageName) {
  if (!confirm(`Are you sure you want to permanently delete this audit trail entry (#${logId} - ${stageName})?\n\nThis will remove the activity remarks, timestamp, and any attached media evidence files. This action cannot be undone.`)) {
    return;
  }

  try {
    const res = await fetch(window.BASE_URL + '/api/delete_task_log.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ log_id: logId, task_id: taskId })
    });

    const data = await res.json();
    if (data.success) {
      showToast(data.message || 'Audit trail entry deleted successfully!', 'success');
      closeModal('modalEditLog');
      if (typeof loadTaskReport === 'function') {
        loadTaskReport(taskId);
      }
    } else {
      showToast(data.message || 'Failed to delete audit log entry.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error while deleting audit log entry.', 'error');
  }
}
/**
 * ------------------------------------------------------------------------
 * 12. Mobile Responsive Navigation Toggle
 * ------------------------------------------------------------------------
 */
function initMobileNav() {
  const toggleBtn = document.getElementById('mobileNavToggle');
  const navLinks = document.getElementById('mainNavLinks');
  if (!toggleBtn || !navLinks) return;

  const iconOpen = toggleBtn.querySelector('.icon-open');
  const iconClose = toggleBtn.querySelector('.icon-close');

  function toggleMenu(forceOpen = null) {
    const isOpen = forceOpen !== null ? forceOpen : !navLinks.classList.contains('active');
    if (isOpen) {
      navLinks.classList.add('active');
      toggleBtn.setAttribute('aria-expanded', 'true');
      if (iconOpen) iconOpen.style.display = 'none';
      if (iconClose) iconClose.style.display = 'block';
      document.body.style.overflow = 'hidden';
    } else {
      navLinks.classList.remove('active');
      toggleBtn.setAttribute('aria-expanded', 'false');
      if (iconOpen) iconOpen.style.display = 'block';
      if (iconClose) iconClose.style.display = 'none';
      document.body.style.overflow = '';
    }
  }

  toggleBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    toggleMenu();
  });

  // Close when clicking any nav link
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 1024) {
        toggleMenu(false);
      }
    });
  });

  // Close when clicking outside navbar
  document.addEventListener('click', (e) => {
    if (navLinks.classList.contains('active') && !e.target.closest('.glass-navbar')) {
      toggleMenu(false);
    }
  });

  // Close on resize if switching to desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 1024 && navLinks.classList.contains('active')) {
      toggleMenu(false);
    }
  });
}
window.initMobileNav = initMobileNav;
