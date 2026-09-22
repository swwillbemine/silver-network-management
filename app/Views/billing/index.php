<!doctype html>
<html lang="id">
<?php \App\Core\View::header(); ?>
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col">
            <div class="page-pretitle">Keuangan</div>
            <h2 class="page-title">Tagihan</h2>
          </div>
          <div class="col-auto d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
              <input type="month" name="period" value="<?= $period_filter ?>" class="form-control form-control-sm" onchange="this.form.submit()">
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="unpaid"    <?= $status_filter==='unpaid'    ?'selected':'' ?>>Belum Bayar</option>
                <option value="paid"      <?= $status_filter==='paid'      ?'selected':'' ?>>Lunas</option>
                <option value="cancelled" <?= $status_filter==='cancelled' ?'selected':'' ?>>Dibatalkan</option>
              </select>
            </form>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#generateModal">
              Generate Tagihan
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible mb-3" role="alert">
          <?= htmlspecialchars($msg_text) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="row g-3 mb-3">
          <div class="col-sm-4">
            <div class="card card-sm"><div class="card-body">
              <div class="text-muted">Belum Bayar</div>
              <div class="fs-4 fw-bold text-warning"><?= $stats['unpaid']['cnt'] ?? 0 ?></div>
              <div class="text-muted small">Rp <?= number_format($stats['unpaid']['total']??0,0,',','.') ?></div>
            </div></div>
          </div>
          <div class="col-sm-4">
            <div class="card card-sm"><div class="card-body">
              <div class="text-muted">Lunas</div>
              <div class="fs-4 fw-bold text-success"><?= $stats['paid']['cnt'] ?? 0 ?></div>
              <div class="text-muted small">Rp <?= number_format($stats['paid']['total']??0,0,',','.') ?></div>
            </div></div>
          </div>
          <div class="col-sm-4">
            <div class="card card-sm"><div class="card-body">
              <div class="text-muted">Total Periode</div>
              <div class="fs-4 fw-bold"><?= ($stats['unpaid']['cnt']??0)+($stats['paid']['cnt']??0) ?></div>
              <div class="text-muted small">Rp <?= number_format(($stats['unpaid']['total']??0)+($stats['paid']['total']??0),0,',','.') ?></div>
            </div></div>
          </div>
        </div>

        <!-- TABLE -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Tagihan <?= date('F Y', strtotime($period_filter.'-01')) ?> (<?= $total_rows ?>)</h3>
            <div class="card-options">
              <input type="text" id="search-bill" class="form-control form-control-sm" placeholder="Cari..." style="width:160px;">
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
              <thead><tr>
                <th>Pelanggan</th><th>PPPoE</th><th>Paket</th>
                <th>Tagihan</th><th>Jatuh Tempo</th><th>Status</th>
                <th>Bayar Pada</th><th class="w-1">Aksi</th>
              </tr></thead>
              <tbody id="bill-table">
              <?php foreach ($billings as $b):
                $bj = htmlspecialchars(json_encode($b), ENT_QUOTES);
              ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($b['cust_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($b['phone']) ?></div>
                </td>
                <td><code class="small"><?= htmlspecialchars($b['pppoe_username']) ?></code></td>
                <td class="text-muted small"><?= htmlspecialchars($b['pkg_name']) ?></td>
                <td>
                  <div class="fw-semibold">Rp <?= number_format($b['amount'],0,',','.') ?></div>
                  <?php if (($b['discount_amount']??0) > 0): ?>
                  <div class="text-success small">Diskon Rp <?= number_format($b['discount_amount'],0,',','.') ?></div>
                  <div class="text-muted" style="font-size:.7rem;text-decoration:line-through">Rp <?= number_format($b['amount']+$b['discount_amount'],0,',','.') ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-muted">
                  <?php $due = strtotime($b['due_date']); $ov = $b['status']==='unpaid' && $due<time(); ?>
                  <span class="<?= $ov?'text-danger fw-semibold':'' ?>"><?= date('d M Y',$due) ?></span>
                  <?php if ($ov): ?><div class="badge bg-danger-lt">Jatuh Tempo</div><?php endif; ?>
                </td>
                <td><span class="badge bg-<?= $status_colors[$b['status']] ?>-lt"><?= $status_labels[$b['status']] ?></span></td>
                <td class="text-muted small"><?= $b['paid_at'] ? date('d M Y H:i',strtotime($b['paid_at'])) : '-' ?></td>
                <td>
                  <?php if ($b['status'] === 'unpaid'): ?>
                  <div class="d-flex gap-1 align-items-center flex-nowrap">
                    <button class="btn btn-sm btn-success" onclick='payBill(<?= $bj ?>)'>Bayar</button>
                    <button class="btn btn-sm btn-outline-secondary" title="Cetak Invoice" onclick='openPrintModal("invoice",<?= $bj ?>)'>
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="10" rx="2"/><path d="M6 18v3h12v-3"/></svg>
                    </button>
                    <button class="btn btn-sm btn-ghost-secondary" onclick="cancelBill(<?= $b['id'] ?>)">Batal</button>
                  </div>
                  <?php elseif ($b['status'] === 'paid'): ?>
                  <button class="btn btn-sm btn-outline-success" onclick='openPrintModal("receipt",<?= $bj ?>)'>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" class="me-1"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="10" rx="2"/><path d="M6 18v3h12v-3"/></svg>Kwitansi
                  </button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$billings): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada tagihan untuk periode ini.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php
            $q_base = http_build_query(array_filter([
                'period' => $period_filter,
                'status' => $status_filter,
            ]));
            if (!function_exists('bill_page_url')) {
                function bill_page_url($p, $base) { return '?' . $base . ($base ? '&' : '') . 'page=' . $p; }
            }
          ?>
          <div class="card-footer d-flex align-items-center justify-content-between">
            <div class="text-muted small">
              Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?>
              dari <strong><?= $total_rows ?></strong> tagihan
            </div>
            <ul class="pagination m-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= bill_page_url($page - 1, $q_base) ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
              </li>
              <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                if ($start > 1): ?>
                  <li class="page-item"><a class="page-link" href="<?= bill_page_url(1, $q_base) ?>">1</a></li>
                  <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
              <?php endif;
                for ($i = $start; $i <= $end; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= bill_page_url($i, $q_base) ?>"><?= $i ?></a>
                  </li>
              <?php endfor;
                if ($end < $total_pages): ?>
                  <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                  <li class="page-item"><a class="page-link" href="<?= bill_page_url($total_pages, $q_base) ?>"><?= $total_pages ?></a></li>
              <?php endif; ?>
              <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= bill_page_url($page + 1, $q_base) ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- GENERATE MODAL -->
<div class="modal modal-blur fade" id="generateModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="generate">
        <div class="modal-header">
          <h5 class="modal-title">Generate Tagihan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Periode</label>
          <input type="month" name="period" class="form-control" value="<?= date('Y-m') ?>" required>
          <p class="text-muted small mt-2">Membuat tagihan untuk semua pelanggan aktif yang belum memiliki tagihan di periode ini.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Generate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- PAY MODAL -->
<div class="modal modal-blur fade" id="payModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="billing_id" id="pay-bill-id">
        <div class="modal-header">
          <h5 class="modal-title">Catat Pembayaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <div class="text-muted small mb-1">Pelanggan</div>
            <div class="fw-semibold" id="pay-cust-name"></div>
            <div class="mt-1" id="pay-amount-info"></div>
          </div>
          <div class="mb-3">
            <label class="form-label required">Jumlah Dibayar (Rp)</label>
            <input type="number" name="amount_paid" id="pay-amount-input" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label required">Metode Pembayaran</label>
            <select name="method" id="pay-method" class="form-select">
              <option value="cash">💵  Tunai (Cash)</option>
              <?php foreach ($enabled_methods as $pm): ?>
                <?php if ($pm['type'] === 'bank'): ?>
                  <option value="<?= htmlspecialchars($pm['name']) ?>">
                    🏦  <?= htmlspecialchars($pm['name']) ?><?= $pm['account'] ? ' — '.$pm['account'] : '' ?>
                  </option>
                <?php elseif ($pm['type'] === 'ewallet'): ?>
                  <option value="<?= htmlspecialchars($pm['name']) ?>">
                    📱  <?= htmlspecialchars($pm['name']) ?><?= $pm['account'] ? ' — '.$pm['account'] : '' ?>
                  </option>
                <?php elseif ($pm['type'] === 'qris'): ?>
                  <option value="QRIS">📲  QRIS</option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">Konfirmasi Pembayaran</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- PRINT SIZE MODAL -->
<div class="modal modal-blur fade" id="printModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="print-modal-title">Cetak Dokumen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="list-group list-group-flush">
          <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-3" style="cursor:pointer;">
            <input class="form-check-input m-0" type="radio" name="print-size" value="a4" checked>
            <div>
              <div class="fw-semibold">A4 Standard</div>
              <div class="text-muted small">210 × 297mm — arsip kantor</div>
            </div>
          </label>
          <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-3" style="cursor:pointer;">
            <input class="form-check-input m-0" type="radio" name="print-size" value="a5">
            <div>
              <div class="fw-semibold">A5 Landscape</div>
              <div class="text-muted small">210 × 148mm — hemat kertas</div>
            </div>
          </label>
          <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-3" style="cursor:pointer;">
            <input class="form-check-input m-0" type="radio" name="print-size" value="thermal">
            <div>
              <div class="fw-semibold">Struk Kasir 58mm</div>
              <div class="text-muted small">Printer thermal POS</div>
            </div>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="print-confirm-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" class="me-1"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="10" rx="2"/><path d="M6 18v3h12v-3"/></svg>Cetak
        </button>
      </div>
    </div>
  </div>
</div>

<!-- CANCEL FORM -->
<form method="POST" id="cancel-form" style="display:none;">
  <input type="hidden" name="action" value="cancel">
  <input type="hidden" name="billing_id" id="cancel-id">
</form>

<script>
const ISP = {
    name:          <?= json_encode($isp_name) ?>,
    address:       <?= json_encode($isp_address) ?>,
    phone:         <?= json_encode($isp_phone) ?>,
    logo:          <?= json_encode($isp_logo) ?>,
    reseller_name: <?= json_encode($reseller_name) ?>,
    reseller_logo: <?= json_encode($reseller_logo) ?>,
    methods:       <?= json_encode($enabled_methods) ?>,
};

function fmtRp(n){return 'Rp '+parseInt(n||0).toLocaleString('id-ID');}
function fmtDate(s,o){if(!s)return'-';return new Date(s).toLocaleDateString('id-ID',o||{day:'2-digit',month:'long',year:'numeric'});}
function fmtDT(s){if(!s)return'-';return new Date(s).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'});}

/* Pay modal */
function payBill(b){
    document.getElementById('pay-bill-id').value=b.id;
    document.getElementById('pay-cust-name').textContent=b.cust_name;
    document.getElementById('pay-amount-input').value=b.amount;
    let info='<span class="fw-bold fs-5">'+fmtRp(b.amount)+'</span>';
    if(parseInt(b.discount_amount)>0){
        info+=' <span class="badge bg-success-lt">Diskon '+fmtRp(b.discount_amount)+'</span>';
        info+='<div class="text-muted small" style="text-decoration:line-through">Normal: '+fmtRp(parseInt(b.amount)+parseInt(b.discount_amount))+'</div>';
    }
    document.getElementById('pay-amount-info').innerHTML=info;
    new bootstrap.Modal(document.getElementById('payModal')).show();
}
function cancelBill(id){
    if(!confirm('Batalkan tagihan ini?'))return;
    document.getElementById('cancel-id').value=id;
    document.getElementById('cancel-form').submit();
}

/* Print modal */
let _ptype='invoice',_pbill=null;
function openPrintModal(type,b){
    _ptype=type; _pbill=b;
    document.getElementById('print-modal-title').textContent=type==='invoice'?'Cetak Invoice':'Cetak Kwitansi';
    new bootstrap.Modal(document.getElementById('printModal')).show();
}
document.getElementById('print-confirm-btn').addEventListener('click',function(){
    const sz=document.querySelector('input[name="print-size"]:checked').value;
    bootstrap.Modal.getInstance(document.getElementById('printModal')).hide();
    setTimeout(()=>_ptype==='invoice'?printInvoice(_pbill,sz):printReceipt(_pbill,sz),200);
});

/* Search */
document.getElementById('search-bill').addEventListener('input',function(){
    const q=this.value.toLowerCase();
    document.querySelectorAll('#bill-table tr').forEach(tr=>{tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none';});
});

/* ═══════════════ PRINT ENGINE ═══════════════ */
function openWin(html,css){
    const w=window.open('','_blank','width=860,height=740');
    w.document.write('<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Print</title><style>'+css+'</style></head><body>'+html+'<scr'+'ipt>window.onload=()=>setTimeout(()=>window.print(),350);<\/scr'+'ipt></body></html>');
    w.document.close();
}

function supportedBy(th){
    if(!ISP.reseller_name&&!ISP.reseller_logo)return'';
    if(th)return'<div style="border-top:1px dashed #aaa;margin-top:8px;padding-top:5px;text-align:center;font-size:9px;color:#555;">'+(ISP.reseller_logo?'<img src="'+ISP.reseller_logo+'" style="height:16px;margin-bottom:2px;display:block;margin-left:auto;margin-right:auto;"><br>':'')+'Supported by '+(ISP.reseller_name||'')+'</div>';
    return'<div style="display:flex;align-items:center;gap:6px;margin-top:12px;padding-top:8px;border-top:1px solid #eee;justify-content:flex-end;"><span style="font-size:9px;color:#bbb;letter-spacing:.5px;text-transform:uppercase;">Supported by</span>'+(ISP.reseller_logo?'<img src="'+ISP.reseller_logo+'" style="height:20px;object-fit:contain;">':'')+(ISP.reseller_name?'<span style="font-size:12px;font-weight:700;color:#666;">'+ISP.reseller_name+'</span>':'')+'</div>';
}

function ispLogo(th){
    if(!ISP.logo)return'';
    return'<img src="'+ISP.logo+'" style="height:'+(th?'26':'40')+'px;object-fit:contain;display:block;margin-bottom:4px;">';
}

function payMethodsHtml(th){
    const m=ISP.methods;
    if(!m||!m.length)return'';
    if(th){
        let h='<div style="border-top:1px dashed #aaa;margin-top:6px;padding-top:5px;"><div style="font-size:9px;font-weight:700;text-transform:uppercase;margin-bottom:3px;">Cara Pembayaran</div>';
        m.forEach(pm=>{
            if(pm.type==='qris'){h+='<div style="font-size:9px;font-weight:700;">QRIS</div>'+(pm.image?'<div style="text-align:center;margin:3px 0;"><img src="'+pm.image+'" style="width:72px;height:72px;"></div>':'');}
            else{const ic=pm.type==='bank'?'[Bank]':'[eWallet]';h+='<div style="font-size:9px;"><b>'+pm.name+'</b>'+(pm.account?'<br><span style="letter-spacing:1px;font-size:10px;">'+pm.account+'</span>':'')+(pm.holder?'<br>'+pm.holder:'')+'</div><div style="margin:2px 0;"></div>';}
        });
        return h+'</div>';
    }
    const banks=m.filter(x=>x.type==='bank'), wallets=m.filter(x=>x.type==='ewallet'), qris=m.filter(x=>x.type==='qris');
    let h='<div style="margin-bottom:12px;"><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:7px;">Cara Pembayaran</div><div style="display:flex;gap:8px;flex-wrap:wrap;">';
    [...banks,...wallets].forEach(pm=>{
        const icoSvg=pm.type==='bank'?'<svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke="#206bc4" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 10h18M3 6l9-3 9 3M4 10v8m4-8v8m4-8v8m4-8v8m4-8v8M3 18h18"/></svg>':'<svg width="13" height="13" viewBox="0 0 24 24" stroke-width="2" stroke="#206bc4" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>';
        h+='<div style="background:#f0f6ff;border:1px solid #c5d9f5;border-radius:6px;padding:7px 10px;min-width:130px;">';
        h+='<div style="display:flex;align-items:center;gap:4px;font-weight:700;font-size:12px;">'+icoSvg+pm.name+'</div>';
        if(pm.account)h+='<div style="font-size:14px;font-weight:700;letter-spacing:1px;color:#206bc4;margin:2px 0;">'+pm.account+'</div>';
        if(pm.holder)h+='<div style="font-size:11px;color:#666;">a.n. '+pm.holder+'</div>';
        h+='</div>';
    });
    qris.forEach(pm=>{
        h+='<div style="background:#f0f6ff;border:1px solid #c5d9f5;border-radius:6px;padding:7px 10px;text-align:center;">';
        h+='<div style="font-weight:700;font-size:12px;margin-bottom:3px;">📲 QRIS</div>';
        if(pm.image)h+='<img src="'+pm.image+'" style="width:65px;height:65px;display:block;margin:0 auto;">';
        h+='</div>';
    });
    return h+'</div></div>';
}

function cssPage(sz){
    const big=sz==='a4';
    return `*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Segoe UI',Arial,sans-serif;font-size:${big?13:12}px;color:#222;background:#fff}.page{width:${big?180:195}mm;margin:0 auto}.hdr{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #206bc4;padding-bottom:10px;margin-bottom:14px}.co-name{font-size:${big?22:19}px;font-weight:700;color:#206bc4}.co-det{font-size:11px;color:#555;margin-top:3px;line-height:1.5}.dt{font-size:${big?20:17}px;font-weight:700;text-align:right}.dn{font-size:11px;color:#555;text-align:right;margin-top:3px}.stit{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:5px}table.inf{width:100%;border-collapse:collapse}table.inf td{padding:3px 0;vertical-align:top}table.inf td:first-child{width:40%;color:#555;font-size:12px}table.inf td:last-child{font-weight:600;font-size:12px}.ab{background:#f0f6ff;border:1.5px solid #206bc4;border-radius:6px;padding:12px 16px;margin:12px 0;display:flex;justify-content:space-between;align-items:center}.al{font-size:12px;color:#555}.av{font-size:${big?26:22}px;font-weight:700;color:#206bc4}.av.ok{color:#2fb344}.bp{display:inline-block;background:#d1f7c4;color:#1a7340;font-weight:700;font-size:11px;padding:3px 11px;border-radius:20px;border:1.5px solid #2fb344}.bu{display:inline-block;background:#fff3cd;color:#856404;font-weight:700;font-size:11px;padding:3px 11px;border-radius:20px;border:1.5px solid #ffc107}.sk{text-decoration:line-through;color:#999;font-size:11px}.disc{color:#2fb344;font-size:11px;margin-top:2px}.ftr{border-top:1px solid #dee2e6;margin-top:14px;padding-top:8px;display:flex;justify-content:space-between;font-size:10px;color:#888}.sr{display:flex;justify-content:flex-end;gap:50px;margin-top:16px}.sb{text-align:center;font-size:11px;color:#555}.sl{border-top:1px solid #999;padding-top:4px;margin-top:38px}@media print{@page{size:${big?'A4 portrait':'A5 landscape'};margin:${big?'15mm':'8mm'}}body{print-color-adjust:exact;-webkit-print-color-adjust:exact}}`;
}

function cssThermal(){
    return `*{box-sizing:border-box;margin:0;padding:0}` +
    `body{font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:500;color:#000;background:#fff;width:54mm;-webkit-print-color-adjust:exact;print-color-adjust:exact}` +
    `.page{width:54mm;padding:2mm 1.5mm}` +
    `.c{text-align:center}` +
    `.cn{font-size:14px;font-weight:900;text-align:center;letter-spacing:.3px}` +
    `.cd{font-size:10px;text-align:center;color:#000;line-height:1.5}` +
    `.sep{border-top:1.5px dashed #000;margin:5px 0}` +
    `.dt{font-size:13px;font-weight:900;text-align:center;margin:4px 0;letter-spacing:1px}` +
    `.rw{display:flex;justify-content:space-between;margin:2.5px 0;font-size:10px;font-weight:500;gap:2px}` +
    `.rw span:first-child{white-space:nowrap;flex-shrink:0;color:#333}` +
    `.rw span:last-child{text-align:right;font-weight:700;word-break:break-all}` +
    `.am{text-align:center;font-size:17px;font-weight:900;border:2px solid #000;padding:5px 3px;margin:6px 0;letter-spacing:.5px}` +
    `.lns{text-align:center;font-size:11px;font-weight:900;margin:3px 0;letter-spacing:1px}` +
    `@media print{@page{size:58mm auto;margin:1mm 0}body{width:56mm}}`;
}

/* ── INVOICE ── */
function printInvoice(b,sz){
    const per=fmtDate(b.period+'-01',{month:'long',year:'numeric'});
    const due=fmtDate(b.due_date);
    const now=new Date().toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const hd=parseInt(b.discount_amount)>0;
    if(sz==='thermal'){
        openWin(`<div class="page">${ispLogo(true)}<div class="cn">${ISP.name}</div><div class="cd">${(ISP.address||'').replace(/\n/g,'<br>')}${ISP.phone?'<br>'+ISP.phone:''}</div><div class="sep"></div><div class="dt">INVOICE</div><div class="c" style="font-size:9px;">#INV-${String(b.id).padStart(6,'0')} | Belum Lunas</div><div class="sep"></div><div class="rw"><span>Pelanggan</span><span><b>${b.cust_name}</b></span></div>${b.customer_number?'<div class="rw"><span>No. Pelanggan</span><span>'+b.customer_number+'</span></div>':''}<div class="rw"><span>Telepon</span><span>${b.phone||'-'}</span></div><div class="rw"><span>Wilayah</span><span>${b.pop_name||b.node_name||'-'}</span></div><div class="sep"></div><div class="rw"><span>Paket</span><span>${b.pkg_name}</span></div><div class="rw"><span>Periode</span><span>${per}</span></div><div class="rw"><span>Jatuh Tempo</span><span>${due}</span></div>${hd?'<div class="rw"><span>Diskon</span><span>-'+fmtRp(b.discount_amount)+'</span></div>':''}<div class="sep"></div><div class="am">${fmtRp(b.amount)}</div>${payMethodsHtml(true)}${supportedBy(true)}<div class="sep"></div><div class="c" style="font-size:8px;color:#555;">Dicetak: ${now}</div></div>`,cssThermal());
        return;
    }
    const tc=sz==='a4'?'display:flex;gap:50px;margin-bottom:14px':'display:flex;gap:30px;margin-bottom:12px';
    const disc=hd?`<div class="sk">${fmtRp(parseInt(b.amount)+parseInt(b.discount_amount))}</div><div class="disc">Diskon: ${fmtRp(b.discount_amount)}</div>`:'';
    openWin(`<div class="page"><div class="hdr"><div>${ispLogo(false)}<div class="co-name">${ISP.name}</div><div class="co-det">${(ISP.address||'').replace(/\n/g,'<br>')}${ISP.phone?'<br>'+ISP.phone:''}</div></div><div><div class="dt">INVOICE</div><div class="dn">#INV-${String(b.id).padStart(6,'0')}</div><div class="dn" style="margin-top:5px;"><span class="bu">Belum Lunas</span></div></div></div><div style="${tc}"><div style="flex:1"><div class="stit">Tagihan Kepada</div><div style="font-size:14px;font-weight:700;">${b.cust_name}</div>${b.customer_number?'<div style="font-size:11px;color:#888;margin-top:1px;">No. '+b.customer_number+'</div>':''}<div style="font-size:12px;color:#555;margin-top:3px;">${b.phone||''}</div><div style="font-size:12px;color:#555;">${b.pop_name||b.node_name||''}</div></div><div style="flex:1"><div class="stit">Detail Tagihan</div><table class="inf"><tr><td>Periode</td><td>${per}</td></tr><tr><td>Paket</td><td>${b.pkg_name}</td></tr><tr><td>Jatuh Tempo</td><td>${due}</td></tr></table></div></div><div class="ab"><div><div class="al">Total Tagihan${hd?' (setelah diskon)':''}</div>${disc}</div><div class="av">${fmtRp(b.amount)}</div></div>${payMethodsHtml(false)}${supportedBy(false)}<div class="ftr"><span>Dicetak: ${now}</span><span>Harap bayar sebelum jatuh tempo. Terima kasih.</span></div></div>`,cssPage(sz));
}

/* ── KWITANSI ── */
function printReceipt(b,sz){
    const per=fmtDate(b.period+'-01',{month:'long',year:'numeric'});
    const pd=fmtDT(b.paid_at);
    const now=new Date().toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const amt=b.amount_paid??b.amount;
    const hd=parseInt(b.discount_amount)>0;
    const mth=b.method||'-';
    if(sz==='thermal'){
        openWin(`<div class="page">${ispLogo(true)}<div class="cn">${ISP.name}</div><div class="cd">${(ISP.address||'').replace(/\n/g,'<br>')}${ISP.phone?'<br>'+ISP.phone:''}</div><div class="sep"></div><div class="dt">KWITANSI</div><div class="lns">*** LUNAS ***</div><div class="c" style="font-size:9px;">#KWT-${String(b.id).padStart(6,'0')}</div><div class="sep"></div><div class="rw"><span>Pelanggan</span><span><b>${b.cust_name}</b></span></div>${b.customer_number?'<div class="rw"><span>No. Pelanggan</span><span>'+b.customer_number+'</span></div>':''}<div class="rw"><span>Telepon</span><span>${b.phone||'-'}</span></div><div class="rw"><span>Wilayah</span><span>${b.pop_name||b.node_name||'-'}</span></div><div class="sep"></div><div class="rw"><span>Paket</span><span>${b.pkg_name}</span></div><div class="rw"><span>Periode</span><span>${per}</span></div><div class="rw"><span>Metode</span><span>${mth}</span></div><div class="rw"><span>Tgl Bayar</span><span>${pd}</span></div>${hd?'<div class="rw"><span>Diskon</span><span>-'+fmtRp(b.discount_amount)+'</span></div>':''}<div class="sep"></div><div class="am">${fmtRp(amt)}</div>${supportedBy(true)}<div class="sep"></div><div class="c" style="font-size:8px;">Dicetak: ${now}</div><div class="c" style="font-size:8px;color:#555;">Bukti pembayaran sah.</div></div>`,cssThermal());
        return;
    }
    const tc=sz==='a4'?'display:flex;gap:50px;margin-bottom:14px':'display:flex;gap:30px;margin-bottom:12px';
    const disc=hd?`<tr><td>Diskon</td><td class="disc">- ${fmtRp(b.discount_amount)}</td></tr>`:'';
    openWin(`<div class="page"><div class="hdr"><div>${ispLogo(false)}<div class="co-name">${ISP.name}</div><div class="co-det">${(ISP.address||'').replace(/\n/g,'<br>')}${ISP.phone?'<br>'+ISP.phone:''}</div></div><div><div class="dt">KWITANSI</div><div class="dn">#KWT-${String(b.id).padStart(6,'0')}</div><div class="dn" style="margin-top:5px;"><span class="bp">✓ LUNAS</span></div></div></div><div style="${tc}"><div style="flex:1"><div class="stit">Diterima Dari</div><div style="font-size:14px;font-weight:700;">${b.cust_name}</div>${b.customer_number?'<div style="font-size:11px;color:#888;margin-top:1px;">No. '+b.customer_number+'</div>':''}<div style="font-size:12px;color:#555;margin-top:3px;">${b.phone||''}</div><div style="font-size:12px;color:#555;">${b.pop_name||b.node_name||''}</div></div><div style="flex:1"><div class="stit">Detail Pembayaran</div><table class="inf"><tr><td>Periode</td><td>${per}</td></tr><tr><td>Paket</td><td>${b.pkg_name}</td></tr><tr><td>Metode</td><td>${mth}</td></tr><tr><td>Tanggal</td><td>${pd}</td></tr>${disc}</table></div></div><div class="ab"><div class="al">Jumlah Dibayar</div><div class="av ok">${fmtRp(amt)}</div></div><div class="sr"><div class="sb"><div>Pelanggan</div><div class="sl">${b.cust_name}</div></div><div class="sb"><div>Hormat kami,</div><div class="sl">${ISP.name}</div></div></div>${supportedBy(false)}<div class="ftr"><span>Dicetak: ${now}</span><span>Dokumen bukti pembayaran yang sah.</span></div></div>`,cssPage(sz));
}
</script>
</body>
</html>

