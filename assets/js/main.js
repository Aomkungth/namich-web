/**
 * HostPro Cloud Main JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // Copy to clipboard helper
    document.querySelectorAll('.btn-copy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const text = btn.getAttribute('data-copy');
            if (!text) return;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2 text-success"></i> คัดลอกแล้ว!';
                btn.classList.add('btn-light');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-light');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        });
    });

    // Password Toggle Visibility
    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const targetId = toggle.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    toggle.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    input.type = 'password';
                    toggle.innerHTML = '<i class="bi bi-eye"></i>';
                }
            }
        });
    });

    // DirectAdmin Username live validation helper
    const daUserInput = document.getElementById('da_username');
    const daUserHelp = document.getElementById('da_username_help');
    if (daUserInput && daUserHelp) {
        daUserInput.addEventListener('input', () => {
            const val = daUserInput.value.trim();
            const valid = /^[a-z][a-z0-9]{3,15}$/.test(val);
            if (val.length === 0) {
                daUserHelp.innerHTML = 'ตัวพิมพ์เล็ก a-z และตัวเลข 0-9 ความยาว 4-16 ตัว (ต้องขึ้นต้นด้วยตัวอักษร)';
                daUserHelp.className = 'form-text text-muted';
            } else if (!valid) {
                daUserHelp.innerHTML = '<i class="bi bi-x-circle text-danger"></i> ต้องเป็น a-z0-9 ยาว 4-16 ตัว และขึ้นต้นด้วย a-z';
                daUserHelp.className = 'form-text text-danger';
            } else {
                daUserHelp.innerHTML = '<i class="bi bi-check-circle text-success"></i> ชื่อผู้ใช้งาน DirectAdmin ถูกต้อง';
                daUserHelp.className = 'form-text text-success';
            }
        });
    }
});
