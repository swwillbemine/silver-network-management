<?php
\App\Core\View::header(['title' => $page_title]);

if (!function_exists('pfBpsToDisplay')) {
    function pfBpsToDisplay(int $bps): array {
        if ($bps<=0) return [0,'M'];
        if ($bps>=1_000_000_000&&$bps%1_000_000_000===0) return [(int)($bps/1_000_000_000),'G'];
        if ($bps>=1_000_000    &&$bps%1_000_000    ===0) return [(int)($bps/1_000_000),'M'];
        if ($bps>=1_000        &&$bps%1_000        ===0) return [(int)($bps/1_000),'k'];
        return [round($bps/1_000,2),'k'];
    }
}

$queue_types = [
    'default'=>'default','default-small'=>'default-small','ethernet-default'=>'ethernet-default',
    'wireless-default'=>'wireless-default','hotspot-default'=>'hotspot-default',
    'only-hardware-queue'=>'only-hardware-queue','multi-queue-ethernet-default'=>'multi-queue-ethernet-default',
    'pcq-upload-default'=>'pcq-upload-default','pcq-download-default'=>'pcq-download-default',
    'sfq'=>'sfq','red'=>'red','cake'=>'cake','synchronous-default'=>'synchronous-default',
];

[$msg_type,$msg_text] = $msg ? explode(':',$msg,2) : ['',''];

$pre = [];
if ($is_edit && $pkg) {
    [$pre['tx_v'],$pre['tx_u']] = pfBpsToDisplay((int)$pkg['tx_max_limit']);
    [$pre['rx_v'],$pre['rx_u']] = pfBpsToDisplay((int)$pkg['rx_max_limit']);
    [$pre['txb_v'],$pre['txb_u']] = pfBpsToDisplay((int)$pkg['tx_burst_limit']);
    [$pre['rxb_v'],$pre['rxb_u']] = pfBpsToDisplay((int)$pkg['rx_burst_limit']);
    [$pre['txt_v'],$pre['txt_u']] = pfBpsToDisplay((int)$pkg['tx_burst_threshold']);
    [$pre['rxt_v'],$pre['rxt_u']] = pfBpsToDisplay((int)$pkg['rx_burst_threshold']);
}
$v = function(string $field, $default='') use ($pkg,$is_edit): string {
    if (isset($_POST[$field])) return htmlspecialchars((string)$_POST[$field]);
    if ($is_edit && $pkg && isset($pkg[$field])) return htmlspecialchars((string)$pkg[$field]);
    return htmlspecialchars((string)$default);
};
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<body>
<div class="page">
  <?php \App\Core\View::sidebar(); ?>
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row align-items-center">
          <div class="col-auto">
            <a href="<?= BASE_URL ?>/packages" class="btn btn-ghost-secondary btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M15 6l-6 6 6 6"/></svg>
              Kembali
            </a>
          </div>
          <div class="col">
            <div class="page-pretitle">Paket Internet</div>
            <h2 class="page-title"><?= $is_edit ? 'Edit Paket' : 'Tambah Paket' ?></h2>
            <?php if ($is_edit): ?>
            <div class="text-muted small"><?= htmlspecialchars($pkg['name']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">

        <?php if (!empty($msg_text)): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type) ?> alert-dismissible mb-3">
          <?= htmlspecialchars($msg_text) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="action" value="<?= $is_edit ? 'update' : 'create' ?>">
          <?php if ($is_edit): ?>
          <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
          <?php endif; ?>

          <!-- ── INFORMASI PAKET ──────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Informasi Paket
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label required">Nama Paket</label>
                  <input type="text" name="name" class="form-control" required value="<?= $v('name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Harga / Bulan (Rp)</label>
                  <input type="number" name="price" class="form-control" required min="0" value="<?= $v('price') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Router MikroTik</label>
                  <select name="mikrotik_id" id="f-router" class="form-select" required>
                    <option value="">-- Pilih Router --</option>
                    <?php foreach ($routers as $r): ?>
                    <option value="<?= $r['id'] ?>"
                            <?= ($is_edit&&$pkg&&$pkg['mk_id']==$r['id'])||($_POST['mikrotik_id']??'')==$r['id']?'selected':'' ?>>
                      <?= htmlspecialchars($r['name']) ?> (<?= $r['host'] ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label required">Nama PPP Profile</label>
                  <input type="text" name="mikrotik_profile_name" class="form-control" required
                         placeholder="cth: pppoe-10m" value="<?= $v('mikrotik_profile_name') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Local Address</label>
                  <input type="text" name="local_address" class="form-control"
                         placeholder="10.0.0.1" value="<?= $v('local_address') ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Remote Pool</label>
                  <input type="hidden" name="remote_pool" id="hidden-remote-pool" value="">
                  <select id="f-pool" class="form-select">
                    <option value="">-- Pilih router dulu --</option>
                    <?php if ($is_edit && !empty($pkg['remote_pool'])): ?>
                    <option value="<?= htmlspecialchars($pkg['remote_pool']) ?>" selected><?= htmlspecialchars($pkg['remote_pool']) ?></option>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">DNS Server 1</label>
                  <input type="text" name="dns_server1" class="form-control"
                         placeholder="10.10.0.1" maxlength="45" value="<?= $v('dns_server1') ?>">
                  <div class="form-hint">DNS utama / lokal</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">DNS Server 2</label>
                  <input type="text" name="dns_server2" class="form-control"
                         placeholder="8.8.8.8" maxlength="45" value="<?= $v('dns_server2') ?>">
                  <div class="form-hint">DNS cadangan (opsional)</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Keterangan</label>
                  <input type="text" name="description" class="form-control" value="<?= $v('description') ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- ── BANDWIDTH ───────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M3 12h4l3-9 4 18 3-9h4"/></svg>
                Bandwidth Limit
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <?php
                $speed_unit_sel = fn($id,$sel) => '<select name="'.$id.'" id="'.$id.'" class="form-select speed-unit" style="max-width:75px;">'.
                    '<option value="k"'.($sel==='k'?' selected':'').'>kbps</option>'.
                    '<option value="M"'.($sel==='M'?' selected':'').'>Mbps</option>'.
                    '<option value="G"'.($sel==='G'?' selected':'').'>Gbps</option></select>';
                ?>
                <div class="col-md-3">
                  <label class="form-label required">Max Upload</label>
                  <div class="input-group">
                    <input type="number" name="tx_val" id="f-tx-val" class="form-control speed-input"
                           required min="0.001" step="any"
                           value="<?= $is_edit ? $pre['tx_v'] : '' ?>">
                    <?= $speed_unit_sel('tx_unit', $is_edit ? $pre['tx_u'] : 'M') ?>
                  </div>
                  <small class="text-muted speed-preview" id="prev-tx"></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label required">Max Download</label>
                  <div class="input-group">
                    <input type="number" name="rx_val" id="f-rx-val" class="form-control speed-input"
                           required min="0.001" step="any"
                           value="<?= $is_edit ? $pre['rx_v'] : '' ?>">
                    <?= $speed_unit_sel('rx_unit', $is_edit ? $pre['rx_u'] : 'M') ?>
                  </div>
                  <small class="text-muted speed-preview" id="prev-rx"></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Priority Upload</label>
                  <select name="tx_priority" id="f-tx-pri" class="form-select">
                    <?php for ($i=1;$i<=8;$i++): ?>
                    <option value="<?=$i?>" <?=$v('tx_priority',8)==$i?'selected':''?>>
                      <?=$i?><?=$i==1?' — Tertinggi':($i==8?' — Terendah':'')?>
                    </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Priority Download</label>
                  <select name="rx_priority" id="f-rx-pri" class="form-select">
                    <?php for ($i=1;$i<=8;$i++): ?>
                    <option value="<?=$i?>" <?=$v('rx_priority',8)==$i?'selected':''?>>
                      <?=$i?><?=$i==1?' — Tertinggi':($i==8?' — Terendah':'')?>
                    </option>
                    <?php endfor; ?>
                  </select>
                </div>

                <!-- BURST -->
                <div class="col-12 mt-1">
                  <a href="#burst-fields" class="text-muted small fw-semibold text-uppercase"
                     data-bs-toggle="collapse" aria-expanded="<?= $is_edit&&($pre['txb_v']>0||$pre['rxb_v']>0)?'true':'false' ?>">
                    ▸ Burst (opsional)
                  </a>
                  <hr class="my-1 mb-0">
                </div>
                <div class="col-12 collapse <?= $is_edit&&($pre['txb_v']>0||$pre['rxb_v']>0)?'show':'' ?>" id="burst-fields">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Burst Upload</label>
                      <div class="input-group">
                        <input type="number" name="tx_burst_val" id="f-tx-burst-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['txb_v']:0 ?>">
                        <?= $speed_unit_sel('tx_burst_unit', $is_edit?$pre['txb_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Burst Download</label>
                      <div class="input-group">
                        <input type="number" name="rx_burst_val" id="f-rx-burst-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['rxb_v']:0 ?>">
                        <?= $speed_unit_sel('rx_burst_unit', $is_edit?$pre['rxb_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Threshold Upload</label>
                      <div class="input-group">
                        <input type="number" name="tx_thr_val" id="f-tx-thr-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['txt_v']:0 ?>">
                        <?= $speed_unit_sel('tx_thr_unit', $is_edit?$pre['txt_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Threshold Download</label>
                      <div class="input-group">
                        <input type="number" name="rx_thr_val" id="f-rx-thr-val" class="form-control speed-input" min="0" step="any" value="<?= $is_edit?$pre['rxt_v']:0 ?>">
                        <?= $speed_unit_sel('rx_thr_unit', $is_edit?$pre['rxt_u']:'M') ?>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Burst Time Upload (dtk)</label>
                      <input type="number" name="tx_burst_time" id="f-tx-bt" class="form-control" min="0" value="<?= $v('tx_burst_time',0) ?>">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Burst Time Download (dtk)</label>
                      <input type="number" name="rx_burst_time" id="f-rx-bt" class="form-control" min="0" value="<?= $v('rx_burst_time',0) ?>">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── QUEUE CONFIG ─────────────────────────────────────── -->
          <div class="card mb-3">
            <div class="card-header">
              <h3 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2 text-muted" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                Konfigurasi Queue
              </h3>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Queue Type</label>
                  <select name="queue_type" id="f-queue-type" class="form-select">
                    <?php foreach ($queue_types as $val=>$lbl): ?>
                    <option value="<?= $val ?>" <?= $v('queue_type','default')===$val?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Only One Session</label>
                  <select name="only_one" class="form-select">
                    <option value="default" <?= $v('only_one','default')==='default'?'selected':'' ?>>default</option>
                    <option value="yes"     <?= $v('only_one','default')==='yes'    ?'selected':'' ?>>yes — 1 sesi/user</option>
                    <option value="no"      <?= $v('only_one','default')==='no'     ?'selected':'' ?>>no — multi sesi</option>
                  </select>
                  <div class="form-hint">Batasi 1 koneksi per username</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Parent Queue</label>
                  <input type="hidden" name="parent_queue" id="hidden-parent-queue" value="">
                  <select id="f-parent-queue" class="form-select">
                    <option value="">— none —</option>
                    <?php if ($is_edit && !empty($pkg['parent_queue'])): ?>
                    <option value="<?= htmlspecialchars($pkg['parent_queue']) ?>" selected><?= htmlspecialchars($pkg['parent_queue']) ?></option>
                    <?php endif; ?>
                  </select>
                  <div class="form-hint">Pilih router untuk memuat daftar queue</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Insert Queue Before</label>
                  <input type="hidden" name="queue_insert_before" id="hidden-insert-before" value="">
                  <select id="f-insert-before" class="form-select">
                    <option value="">— append (paling akhir) —</option>
                    <?php if ($is_edit && !empty($pkg['queue_insert_before'])): ?>
                    <option value="<?= htmlspecialchars($pkg['queue_insert_before']) ?>" selected><?= htmlspecialchars($pkg['queue_insert_before']) ?></option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- ── ACTION BUTTONS ──────────────────────────────────── -->
          <div class="d-flex gap-2 justify-content-end align-items-center mb-4">
            <a href="<?= BASE_URL ?>/packages" class="btn btn-ghost-secondary btn-sm">Batal</a>
            <button type="submit" class="btn btn-primary btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
              <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Paket' ?> &amp; Push MikroTik
            </button>
          </div>

        </form>
      </div>
    </div>
    
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
const tsPool   = new TomSelect('#f-pool',          {allowEmptyOption:true, onChange: v=>{document.getElementById('hidden-remote-pool').value=v;}});
const tsParent = new TomSelect('#f-parent-queue',  {allowEmptyOption:true, onChange: v=>{document.getElementById('hidden-parent-queue').value=v;}});
const tsBefore = new TomSelect('#f-insert-before', {allowEmptyOption:true, onChange: v=>{document.getElementById('hidden-insert-before').value=v;}});

const MUL = {k:1e3, M:1e6, G:1e9};
function bpsToStr(b){
    if(!b)return'';
    if(b>=1e9)return(b/1e9).toFixed(2).replace(/\.?0+$/,'')+'Gbps';
    if(b>=1e6)return(b/1e6).toFixed(3).replace(/\.?0+$/,'')+'Mbps';
    if(b>=1e3)return(b/1e3).toFixed(2).replace(/\.?0+$/,'')+'Kbps';
    return b+'bps';
}
function updatePreview(vId,uId,pId){
    const v=parseFloat(document.getElementById(vId)?.value)||0;
    const u=document.getElementById(uId)?.value||'M';
    const el=document.getElementById(pId);
    if(el)el.textContent=v?'= '+bpsToStr(v*(MUL[u]||1e6)):'';
}
['tx','rx'].forEach(d=>{
    document.getElementById('f-'+d+'-val')?.addEventListener('input',()=>updatePreview('f-'+d+'-val',d+'_unit','prev-'+d));
    document.getElementById(d+'_unit')?.addEventListener('change',()=>updatePreview('f-'+d+'-val',d+'_unit','prev-'+d));
});
updatePreview('f-tx-val','tx_unit','prev-tx');
updatePreview('f-rx-val','rx_unit','prev-rx');

async function loadPools(rid){
    tsPool.clear(); tsPool.clearOptions();
    tsPool.addOption({value:'',text:'— Tanpa Pool —'});
    if(!rid)return;
    try{
        const d=await(await fetch('api/pools.php?router_id='+rid)).json();
        d.forEach(p=>tsPool.addOption({value:p.name,text:p.name+' ('+p.ranges+')'}));
        tsPool.refreshOptions(false);
    }catch(e){console.error(e);}
}
async function loadQueues(rid){
    tsParent.clear();tsParent.clearOptions();
    tsParent.addOption({value:'',text:'— none —'});
    if(!rid)return;
    try{
        const d=await(await fetch('api/queues.php?router_id='+rid)).json();
        (d.queues||[]).forEach(q=>{
            const lbl=q.name+' ['+q.type+']'+(q.rate&&q.rate!=='-'?' — '+q.rate:'');
            tsParent.addOption({value:q.name,text:lbl});
        });
        tsParent.refreshOptions(false);
    }catch(e){console.error(e);}
}
async function loadBefore(rid){
    tsBefore.clear();tsBefore.clearOptions();
    tsBefore.addOption({value:'',text:'— append (paling akhir) —'});
    if(!rid)return;
    try{
        const d=await(await fetch('api/queues.php?router_id='+rid)).json();
        (d.queues||[]).forEach(q=>{
            const lbl=q.name+' ['+q.type+']'+(q.rate&&q.rate!=='-'?' — '+q.rate:'');
            tsBefore.addOption({value:q.name,text:lbl});
        });
        tsBefore.refreshOptions(false);
    }catch(e){console.error(e);}
}

document.getElementById('f-router')?.addEventListener('change',function(){
    loadPools(this.value);
    loadQueues(this.value);
    loadBefore(this.value);
});

<?php if ($is_edit && $pkg): ?>
(async()=>{
    const rid='<?= $pkg['mk_id'] ?>';
    const poolVal    = '<?= addslashes($pkg['remote_pool']??'') ?>';
    const parentVal  = '<?= addslashes($pkg['parent_queue']??'') ?>';
    const beforeVal  = '<?= addslashes($pkg['queue_insert_before']??'') ?>';
    await loadPools(rid);
    if(poolVal){ tsPool.addOption({value:poolVal,text:poolVal}); tsPool.setValue(poolVal); document.getElementById('hidden-remote-pool').value=poolVal; }
    await loadQueues(rid);
    if(parentVal){ tsParent.setValue(parentVal); document.getElementById('hidden-parent-queue').value=parentVal; }
    await loadBefore(rid);
    if(beforeVal){ tsBefore.addOption({value:beforeVal,text:beforeVal}); tsBefore.setValue(beforeVal); document.getElementById('hidden-insert-before').value=beforeVal; }
})();
<?php endif; ?>
</script>

  </div>
</div>
</body>
</html>

