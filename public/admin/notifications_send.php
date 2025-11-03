<?php
// public/admin/notifications_send.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);

$users = [];
$res = $conn->query("SELECT id, name, email, role FROM users ORDER BY role, name");
if ($res) $users = $res->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --gold:#FFD54F;
      --gold-soft:#F6D472;
      --ink:#111827;
      --line:#E8E2DA;
      --radius:16px;
   
    }
    *{box-sizing:border-box}
    body{
      background:#FAFAFA; color:var(--ink);
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }

       /* ===== Topbar (sudah OK di kamu, biar konsisten) ===== */
    .topbar{ position:sticky; top:0; z-index:50; background:#fff;}
    .topbar .inner{ max-width:1200px; margin:0 auto; padding:8px 8px ; display:flex; align-items:center; gap:16px; min-height:8px; }
    .back-link{ display:inline-flex; align-items:center; gap:10px; color:var(--brown); text-decoration:none; font-weight:600; font-size:16px; }
    .back-link .bi{ font-size:18px; display:inline-flex; align-items:center; justify-content:center; }

    .title{ font-weight:800; margin:0; }
    /* ===== KARTU ===== */
.cardx{
  background:#fff;
  border:1px solid #f7d78d;
  border-radius:var(--radius);
  padding:18px;
}
@media (min-width:992px){ .cardx{ padding:28px; } }

/* ===== FORM BASE + FOCUS LINE TIPIS ===== */
.form-label{ font-weight:600; margin-bottom:.35rem; }

.form-control,
.form-select{
  height:46px;
  border-radius:14px !important;
  border:1px solid var(--line) !important;
  background:#fff !important;
  color:#111 !important;
  box-shadow:none !important;   /* no glow */
  outline:0 !important;
}

/* textarea: tidak bisa di-resize/ditarik */
textarea.form-control{
  height:auto;
  min-height:120px;
  padding-top:10px;
  resize: none;           /* ⬅️ kunci */
  overflow:auto;
}

/* ===== FORM BASE + FOCUS LINE HALUS ===== */
.form-control,
.form-select {
  height: 46px;
  border-radius: 14px !important;
  border: 1px solid var(--line) !important;
  background: #fff !important;
  color: #111 !important;
  box-shadow: none !important;
  outline: 0 !important;
}

/* textarea fix: tidak bisa ditarik ke bawah */
textarea.form-control {
  height: auto;
  min-height: 120px;
  padding-top: 10px;
  resize: none;            /* ⬅️ kunci resize */
  overflow: auto;
}

/* fokus: hanya border kuning tipis (tanpa shadow) */
.form-control:focus,
.form-select:focus {
  border-color: var(--gold) !important;
  box-shadow: none !important;   /* ⬅️ hapus shadow agar garis tidak dobel */
  outline: 0 !important;
}


/* radio */
.form-check-input{
  width:18px; height:18px; margin-top:.2rem; cursor:pointer;
  border:1.5px solid #d1d5db;
}
.form-check-input:checked{ background-color:var(--gold); border-color:var(--gold); }

/* tombol */
.btn-saffron{
  display:inline-flex; align-items:center; gap:8px;
  background:var(--gold); color:#111; font-weight:700;
  padding:.6rem 1.15rem; border-radius:14px; border:1px solid rgba(0,0,0,.02);
}

/* ===== CUSTOM SELECT (cream) untuk User tertentu ===== */
.cf-select{ position:relative; width:100%; }
.cf-select__trigger{
  width:100%; height:46px; display:flex; align-items:center; justify-content:space-between;
  gap:12px; background:#fff; border:1px solid var(--line); border-radius:14px; padding:0 14px;
}
/* saat open: garis bawah tipis juga */
.cf-select.is-open .cf-select__trigger{
  border-color:rgba(247,215,141,.9);
  box-shadow: inset 0 -1px 0 var(--gold);   /* ⬅️ tipis */
}

.cf-select__text{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cf-select__icon{ color:#111; }

.cf-select__list{
  position:absolute; left:0; right:0; top:52px; z-index:1060;
  background:#fff; border:1px solid rgba(247,215,141,.9);
  border-radius:14px;
  display:none; max-height:280px; overflow:auto;
}
.cf-select.is-open .cf-select__list{ display:block; }
.cf-select__option{ padding:11px 14px; cursor:pointer; }
.cf-select__option.is-active{ background:#FFEB9B; font-weight:700; }

/* Grid responsive dua kolom */
.row-g{ display:grid; gap:16px; }
@media (min-width:992px){ .row-g{ grid-template-columns:1fr 1fr; gap:20px 28px; } }
#result{ min-width:160px; }

</style>
</head>
<body class="p-3 p-lg-4">

<!-- TOPBAR -->
  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= BASE_URL ?>/public/admin/index.php"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
  </div>

  <main>

  <div class="cardx">
    <form id="notifForm" class="vstack gap-3">

      <!-- Target -->
      <div>
        <label class="form-label">Target</label>
        <div class="row-g align-items-start">

          <div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="target_type" id="tAll"  value="all" checked>
              <label class="form-check-label" for="tAll">Semua (Customer & Karyawan)</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="target_type" id="tRoleC" value="role">
              <label class="form-check-label" for="tRoleC">Role: Customer</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="target_type" id="tRoleK" value="role">
              <label class="form-check-label" for="tRoleK">Role: Karyawan</label>
            </div>
          </div>

          <!-- Custom select User tertentu -->
          <div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="target_type" id="tUser" value="user">
              <label class="form-check-label" for="tUser">User tertentu</label>
            </div>

            <div class="cf-select mt-1" id="userSelect" data-target="target_user">
              <div class="cf-select__trigger" tabindex="0" aria-disabled="true">
                <span class="cf-select__text" id="user_label">— Pilih user —</span>
                <i class="bi bi-chevron-down cf-select__icon"></i>
              </div>
              <div class="cf-select__list">
                <div class="cf-select__option is-active" data-value="">— Pilih user —</div>
                <?php foreach ($users as $u):
                  $label = ($u['name'] ?: $u['email']).' — '.$u['role']; ?>
                  <div class="cf-select__option" data-value="<?= (int)$u['id'] ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <!-- hidden untuk value yang dikirim -->
            <input type="hidden" id="target_user" value="">
          </div>
        </div>
      </div>

      <!-- Pesan -->
      <div>
        <label class="form-label">Pesan</label>
        <textarea id="message" class="form-control" rows="4" placeholder="Tulis pesan (mis. promo, pengumuman…)" required></textarea>
      </div>

      <!-- Link -->
      <div>
        <label class="form-label">Link (opsional)</label>
        <input id="link" type="url" class="form-control" placeholder="https://...">
      </div>

      <!-- Submit -->
      <div class="d-flex gap-2">
        <button class="btn-saffron" type="submit">
          <i class="bi bi-send"></i> Kirim
        </button>
        <span id="result" class="align-self-center small text-muted"></span>
      </div>
    </form>
  </div>

<script>
/* ===== custom select ===== */
(function initCfSelect(){
  const wrap    = document.getElementById('userSelect');
  const trigger = wrap.querySelector('.cf-select__trigger');
  const list    = wrap.querySelector('.cf-select__list');
  const label   = document.getElementById('user_label');
  const hidden  = document.getElementById('target_user');

  const rAll  = document.getElementById('tAll');
  const rRC   = document.getElementById('tRoleC');
  const rRK   = document.getElementById('tRoleK');
  const rUser = document.getElementById('tUser');

  function setDisabled(dis){
    trigger.setAttribute('aria-disabled', dis ? 'true' : 'false');
    if (dis) wrap.classList.remove('is-open');
  }
  setDisabled(!rUser.checked);

  [rAll,rRC,rRK].forEach(r => r.addEventListener('change', ()=> setDisabled(true)));
  rUser.addEventListener('change', ()=> setDisabled(false));

  trigger.addEventListener('click', (e)=>{
    if (trigger.getAttribute('aria-disabled') === 'true') return;
    e.stopPropagation();
    wrap.classList.toggle('is-open');
  });

  list.querySelectorAll('.cf-select__option').forEach(opt=>{
    opt.addEventListener('click', ()=>{
      const val = opt.dataset.value || '';
      hidden.value = val;
      label.textContent = opt.textContent.trim();
      list.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
      opt.classList.add('is-active');
      wrap.classList.remove('is-open');
    });
  });
  document.addEventListener('click', ()=> wrap.classList.remove('is-open'));
})();

/* ===== submit ke API ===== */
const form = document.getElementById('notifForm');
form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const tAll  = document.getElementById('tAll').checked;
  const tRC   = document.getElementById('tRoleC').checked;
  const tRK   = document.getElementById('tRoleK').checked;
  const tUser = document.getElementById('tUser').checked;

  const msg   = document.getElementById('message').value.trim();
  const link  = document.getElementById('link').value.trim();
  const uid   = document.getElementById('target_user').value.trim();
  const result= document.getElementById('result');

  if (!msg) { alert('Pesan wajib diisi'); return; }
  if (tUser && !uid) { alert('Pilih user'); return; }

  const fd = new FormData();
  fd.append('action','create');
  if (tAll) {
    fd.append('target_type','all');
  } else if (tRC) {
    fd.append('target_type','role'); fd.append('target_role','customer');
  } else if (tRK) {
    fd.append('target_type','role'); fd.append('target_role','karyawan');
  } else if (tUser) {
    fd.append('target_type','user'); fd.append('target_user', uid);
  }
  fd.append('message', msg);
  fd.append('link', link);

  result.textContent = 'Mengirim…';
  try{
    const res  = await fetch('<?= BASE_URL ?>/backend/api/notifications.php', {
      method:'POST', credentials:'same-origin', body: fd
    });
    const data = await res.json();
    if (data.ok) {
      const result = document.getElementById('result');
result.textContent = 'Sukses';
result.style.color = '#16a34a'; // hijau lembut
result.style.fontWeight = '600';
setTimeout(() => {
  result.textContent = ''; // hilang lagi setelah 2 detik
  result.removeAttribute('style');
}, 2000);

      form.reset();
      document.getElementById('user_label').textContent = '— Pilih user —';
      document.getElementById('target_user').value = '';
    } else {
      result.textContent = data.error || 'Gagal mengirim.';
    }
  }catch(e){
    result.textContent = 'Gagal mengirim.';
  }
});
</script>
</body>
</html>
