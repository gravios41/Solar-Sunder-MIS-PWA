<?php
// modules/settings.php
// User settings page

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

checkAuthentication();

$pageTitle = 'Settings';
$pageSubtitle = 'Manage your account and preferences';

include_once __DIR__ . '/../includes/header.php';

// Get current user and sync avatar_url into session
$user = $supabase->getById('users', $_SESSION['user_id']);
$_SESSION['avatar_url'] = $user['avatar_url'] ?? '';
?>

<!-- Profile Picture Card -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3 class="card-title">Profile Picture</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:28px;flex-wrap:wrap">
            <!-- Avatar preview -->
            <div style="position:relative;flex-shrink:0">
                <div id="avatarWrap" style="width:100px;height:100px;border-radius:50%;overflow:hidden;border:3px solid var(--solar-orange);background:var(--solar-orange-light);display:flex;align-items:center;justify-content:center;cursor:pointer" onclick="document.getElementById('avatarInput').click()" title="Click to change photo">
                    <?php if (!empty($user['avatar_url'])): ?>
                    <img id="avatarPreview" src="<?php echo escape($user['avatar_url']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                    <span id="avatarInitials" style="font-size:2rem;font-weight:700;color:var(--solar-orange)"><?php echo getUserInitials($user['full_name'] ?? 'U'); ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="document.getElementById('avatarInput').click()" style="position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--solar-orange);border:2px solid var(--white);color:#fff;font-size:0.7rem;cursor:pointer;display:flex;align-items:center;justify-content:center">
                    <i class="fas fa-camera"></i>
                </button>
            </div>

            <!-- Info & input -->
            <div style="flex:1;min-width:200px">
                <p style="font-weight:600;margin-bottom:4px">Upload a new photo</p>
                <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:14px">JPG, PNG or WebP · Max 2 MB · Square crop recommended</p>
                <input type="file" id="avatarInput" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none" onchange="uploadAvatar(this)">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <button type="button" onclick="document.getElementById('avatarInput').click()" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload"></i> Choose Photo
                    </button>
                    <?php if (!empty($user['avatar_url'])): ?>
                    <button type="button" onclick="removeAvatar()" class="btn btn-secondary btn-sm" id="removeAvatarBtn">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                    <?php endif; ?>
                    <span id="avatarStatus" style="font-size:0.82rem;color:var(--text-muted)"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid-cols-2 gap-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Profile Information</h3>
        </div>
        <div class="card-body">
            <form id="profileForm">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="fullName" class="form-control" value="<?php echo escape($user['full_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="email" class="form-control" value="<?php echo escape($user['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" id="phone" class="form-control" value="<?php echo escape($user['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?php echo ucwords(str_replace('_', ' ', $user['role'] ?? '')); ?>" readonly disabled>
                </div>
                <button type="button" onclick="saveProfile()" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Security Settings</h3>
        </div>
        <div class="card-body">
            <form id="passwordForm">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" id="currentPassword" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" id="newPassword" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" id="confirmPassword" class="form-control">
                </div>
                <button type="button" onclick="changePassword()" class="btn btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</div>

<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Notification Preferences</h3>
    </div>
    <div class="card-body">
        <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" id="emailNotifications" class="form-checkbox">
                <div>
                    <span class="font-medium">Email Notifications</span>
                    <p class="text-sm text-gray-500">Receive updates via email</p>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" id="projectUpdates" class="form-checkbox">
                <div>
                    <span class="font-medium">Project Updates</span>
                    <p class="text-sm text-gray-500">Get notified about project changes</p>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" id="taskReminders" class="form-checkbox">
                <div>
                    <span class="font-medium">Task Reminders</span>
                    <p class="text-sm text-gray-500">Reminders for upcoming tasks</p>
                </div>
            </label>
        </div>
        <button type="button" onclick="saveNotifications()" class="btn btn-primary mt-4">Save Preferences</button>
    </div>
</div>

<script>
async function saveProfile() {
    const data = {
        full_name: document.getElementById('fullName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value
    };
    
    try {
        const response = await fetch('../api/users-api.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: <?php echo $_SESSION['user_id']; ?>, ...data })
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Profile updated successfully', 'success');
            // Update session
            location.reload();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        showToast('Error saving profile', 'error');
    }
}

async function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        showToast('Please fill all password fields', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showToast('New passwords do not match', 'error');
        return;
    }
    
    if (newPassword.length < 6) {
        showToast('New password must be at least 6 characters', 'error');
        return;
    }
    
    try {
        const response = await fetch('../api/users-api.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                id: <?php echo $_SESSION['user_id']; ?>, 
                current_password: currentPassword,
                new_password: newPassword 
            })
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Password changed successfully', 'success');
            document.getElementById('passwordForm').reset();
        } else {
            showToast(result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error changing password', 'error');
    }
}

function saveNotifications() {
    const settings = {
        email_notifications: document.getElementById('emailNotifications').checked,
        project_updates: document.getElementById('projectUpdates').checked,
        task_reminders: document.getElementById('taskReminders').checked
    };
    localStorage.setItem('sunder_solar_settings', JSON.stringify(settings));
    showToast('Notification settings saved', 'success');
}

function loadSettings() {
    const saved = localStorage.getItem('sunder_solar_settings');
    if (saved) {
        const settings = JSON.parse(saved);
        document.getElementById('emailNotifications').checked = settings.email_notifications !== false;
        document.getElementById('projectUpdates').checked = settings.project_updates !== false;
        document.getElementById('taskReminders').checked = settings.task_reminders !== false;
    } else {
        document.getElementById('emailNotifications').checked = true;
        document.getElementById('projectUpdates').checked = true;
        document.getElementById('taskReminders').checked = true;
    }
}

// Load settings
loadSettings();

async function uploadAvatar(input) {
    const file = input.files[0];
    if (!file) return;

    const status = document.getElementById('avatarStatus');
    status.textContent = 'Uploading…';
    status.style.color = 'var(--text-muted)';

    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const res = await fetch('../api/avatar-upload.php', { method: 'POST', body: formData });
        const result = await res.json();

        if (result.success) {
            // Update preview immediately
            const wrap = document.getElementById('avatarWrap');
            wrap.innerHTML = `<img id="avatarPreview" src="${result.avatar_url}" alt="Avatar" style="width:100%;height:100%;object-fit:cover">`;

            // Show remove button if it wasn't there
            if (!document.getElementById('removeAvatarBtn')) {
                const btnArea = document.querySelector('#avatarWrap').closest('[style]').nextElementSibling.querySelector('[style*="gap:10px"]');
                if (btnArea) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.id = 'removeAvatarBtn';
                    removeBtn.className = 'btn btn-secondary btn-sm';
                    removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
                    removeBtn.onclick = removeAvatar;
                    btnArea.insertBefore(removeBtn, btnArea.children[1]);
                }
            }

            status.textContent = 'Photo updated!';
            status.style.color = 'var(--success)';
            showToast('Profile picture updated', 'success');

            // Update header & sidebar avatars on this page without reload
            document.querySelectorAll('.header-avatar, .user-avatar').forEach(el => {
                el.style.padding = '0';
                el.style.overflow = 'hidden';
                el.innerHTML = `<img src="${result.avatar_url}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            });
        } else {
            status.textContent = result.error || 'Upload failed.';
            status.style.color = 'var(--danger)';
            showToast(result.error || 'Upload failed', 'error');
        }
    } catch (e) {
        status.textContent = 'Network error.';
        status.style.color = 'var(--danger)';
        showToast('Network error during upload', 'error');
    }

    input.value = '';
}

async function removeAvatar() {
    if (!confirm('Remove your profile picture?')) return;

    const status = document.getElementById('avatarStatus');
    status.textContent = 'Removing…';
    status.style.color = 'var(--text-muted)';

    try {
        const res = await fetch('../api/avatar-upload.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await res.json();

        if (result.success) {
            const initials = <?php echo json_encode(getUserInitials($user['full_name'] ?? 'U')); ?>;
            const wrap = document.getElementById('avatarWrap');
            wrap.innerHTML = `<span id="avatarInitials" style="font-size:2rem;font-weight:700;color:var(--solar-orange)">${initials}</span>`;

            const btn = document.getElementById('removeAvatarBtn');
            if (btn) btn.remove();

            document.querySelectorAll('.header-avatar, .user-avatar').forEach(el => {
                el.style.padding = '';
                el.style.overflow = '';
                el.innerHTML = initials;
            });

            status.textContent = 'Photo removed.';
            status.style.color = 'var(--text-muted)';
            showToast('Profile picture removed', 'success');
        } else {
            status.textContent = result.error || 'Remove failed.';
            status.style.color = 'var(--danger)';
            showToast(result.error || 'Remove failed', 'error');
        }
    } catch (e) {
        status.textContent = 'Network error.';
        status.style.color = 'var(--danger)';
    }
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>