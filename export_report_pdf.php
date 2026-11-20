<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Dompdf Report Exporter (Print-Ready A4 PDF)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';

// Composer autoloader for Dompdf
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} else {
    die('Dompdf is not installed. Please run composer install.');
}

use Dompdf\Dompdf;
use Dompdf\Options;

requireLogin();

$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    die('Invalid Task ID requested.');
}

$currentUser = getCurrentUser();
if (!hasTaskAccess($taskId, $currentUser)) {
    die('Unauthorized: You do not have permission to view this report.');
}

$pdo = getDBConnection();
$task = getTaskFullDetails($pdo, $taskId);

if (!$task) {
    die('Task not found.');
}

// Helper to embed images as base64 Data URIs for 100% reliable Dompdf rendering
function getBase64Image(string $localPath): ?string {
    if (!file_exists($localPath)) {
        return null;
    }
    $mime = mime_content_type($localPath) ?: 'image/jpeg';
    $data = file_get_contents($localPath);
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

// Build Clean A4 HTML Layout
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>KISMEC Task Audit Report - #<?= $task['task_id'] ?></title>
  <style>
    @page {
      margin: 25mm 20mm 25mm 20mm;
      size: a4 portrait;
    }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 11pt;
      line-height: 1.45;
      color: #1e293b;
      margin: 0;
      padding: 0;
    }
    .header-table {
      width: 100%;
      border-bottom: 2pt solid #4f46e5;
      padding-bottom: 12pt;
      margin-bottom: 18pt;
    }
    .header-logo-text {
      font-size: 18pt;
      font-weight: bold;
      color: #1e1b4b;
      letter-spacing: -0.5px;
    }
    .header-subtext {
      font-size: 8pt;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 2pt;
    }
    .doc-meta {
      text-align: right;
      font-size: 8.5pt;
      color: #475569;
    }
    .section-title {
      font-size: 12pt;
      font-weight: bold;
      color: #1e1b4b;
      border-bottom: 1pt solid #cbd5e1;
      padding-bottom: 4pt;
      margin-top: 16pt;
      margin-bottom: 10pt;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12pt;
    }
    .info-table td {
      padding: 5pt 8pt;
      font-size: 9.5pt;
      border-bottom: 1pt solid #f1f5f9;
      vertical-align: top;
    }
    .info-label {
      width: 25%;
      font-weight: bold;
      color: #475569;
      background-color: #f8fafc;
    }
    .info-val {
      width: 25%;
      color: #0f172a;
    }
    .sla-box {
      padding: 8pt 12pt;
      border-radius: 4pt;
      font-weight: bold;
      font-size: 10pt;
      margin-bottom: 14pt;
      display: inline-block;
    }
    .sla-success { background-color: #ecfdf5; color: #065f46; border: 1pt solid #a7f3d0; }
    .sla-warning { background-color: #fffbeb; color: #92400e; border: 1pt solid #fde68a; }
    .sla-danger  { background-color: #fef2f2; color: #991b1b; border: 1pt solid #fecaca; }

    /* Timeline Section */
    .log-item {
      margin-bottom: 14pt;
      border-left: 2pt solid #6366f1;
      padding-left: 10pt;
    }
    .log-header {
      font-size: 9.5pt;
      font-weight: bold;
      color: #1e293b;
      margin-bottom: 3pt;
    }
    .log-stage {
      display: inline-block;
      padding: 1pt 5pt;
      background: #e0e7ff;
      color: #3730a3;
      border-radius: 3pt;
      font-size: 8pt;
      text-transform: uppercase;
      margin-right: 4pt;
    }
    .log-time {
      font-size: 8pt;
      color: #64748b;
      font-weight: normal;
    }
    .log-remarks {
      font-size: 9pt;
      color: #334155;
      background: #f8fafc;
      padding: 6pt 8pt;
      border-radius: 3pt;
      margin-top: 4pt;
    }

    /* Media Evidence */
    .media-box {
      margin-top: 6pt;
    }
    .evidence-img {
      max-width: 140pt;
      max-height: 100pt;
      border: 1pt solid #cbd5e1;
      border-radius: 3pt;
      margin-right: 6pt;
      margin-top: 4pt;
    }
    .evidence-video-card {
      background: #f0f9ff;
      border: 1pt solid #bae6fd;
      padding: 6pt 10pt;
      border-radius: 3pt;
      font-size: 8.5pt;
      color: #0369a1;
      margin-top: 4pt;
    }

    /* Formal Signature Block */
    .signature-container {
      margin-top: 30pt;
      page-break-inside: avoid;
      border-top: 1.5pt dashed #94a3b8;
      padding-top: 16pt;
    }
    .sig-table {
      width: 100%;
    }
    .sig-box {
      width: 48%;
      vertical-align: top;
      font-size: 9pt;
    }
    .sig-line {
      margin-top: 45pt;
      border-bottom: 1pt solid #0f172a;
      width: 80%;
    }
    .stamp-box {
      width: 110pt;
      height: 70pt;
      border: 1.5pt dashed #94a3b8;
      border-radius: 4pt;
      text-align: center;
      line-height: 70pt;
      color: #94a3b8;
      font-size: 8pt;
      margin-top: 10pt;
    }
  </style>
</head>
<body>

  <!-- Official Letterhead / Header -->
  <table class="header-table">
    <tr>
      <td>
        <div class="header-logo-text">KISMEC</div>
        <div class="header-subtext">Kedah Industrial Skills & Management Development Centre</div>
        <div style="font-size:8pt;color:#475569;margin-top:2pt;">Intern Task Lifecycle & Project Performance Verification</div>
      </td>
      <td class="doc-meta">
        <div><strong>REPORT REF:</strong> KISMEC/TR/<?= date('Y') ?>/<?= str_pad((string)$task['task_id'], 4, '0', STR_PAD_LEFT) ?></div>
        <div><strong>DATE:</strong> <?= date('d M Y, h:i A') ?></div>
        <div><strong>STATUS:</strong> <?= htmlspecialchars($task['current_stage']) ?></div>
      </td>
    </tr>
  </table>

  <!-- Task Overview & Intern Profile -->
  <div class="section-title">1. Task Specification & Intern Credentials</div>
  <table class="info-table">
    <tr>
      <td class="info-label">Task Ref ID</td>
      <td class="info-val">#<?= $task['task_id'] ?></td>
      <td class="info-label">Task Priority</td>
      <td class="info-val"><?= strtoupper($task['priority']) ?></td>
    </tr>
    <tr>
      <td class="info-label">Task Title</td>
      <td class="info-val" colspan="3"><strong><?= htmlspecialchars($task['title']) ?></strong></td>
    </tr>
    <tr>
      <td class="info-label">Assigned Intern</td>
      <td class="info-val"><strong><?= htmlspecialchars($task['intern_name']) ?></strong></td>
      <td class="info-label">Intern Email</td>
      <td class="info-val"><?= htmlspecialchars($task['intern_email']) ?></td>
    </tr>
    <tr>
      <td class="info-label">Department</td>
      <td class="info-val"><?= htmlspecialchars($task['intern_dept'] ?: 'Engineering & Smart Automation') ?></td>
      <td class="info-label">Direct Supervisor</td>
      <td class="info-val"><strong><?= htmlspecialchars($task['supervisor_name']) ?></strong></td>
    </tr>
    <?php if (!empty($task['description'])): ?>
    <tr>
      <td class="info-label">Technical Scope</td>
      <td class="info-val" colspan="3"><?= nl2br(htmlspecialchars($task['description'])) ?></td>
    </tr>
    <?php endif; ?>
  </table>

  <!-- SLA Timeline & Verification Schedule -->
  <div class="section-title">2. SLA Timeline & Milestone Verdict</div>
  <table class="info-table">
    <tr>
      <td class="info-label">Project Start Date</td>
      <td class="info-val"><?= formatDisplayDateTime($task['start_date']) ?></td>
      <td class="info-label">Target Due Date (SLA)</td>
      <td class="info-val"><strong><?= formatDisplayDateTime($task['due_date']) ?></strong></td>
    </tr>
    <tr>
      <td class="info-label">Actual Completion Date</td>
      <td class="info-val"><?= formatDisplayDateTime($task['completed_at']) ?></td>
      <td class="info-label">Supervisor Sign-off Date</td>
      <td class="info-val"><?= formatDisplayDateTime($task['approved_at']) ?></td>
    </tr>
  </table>

  <!-- SLA Verdict Banner -->
  <?php
    $slaCls = ($task['sla']['status'] === 'on_track' || $task['sla']['status'] === 'completed_on_time') ? 'sla-success' : (($task['sla']['status'] === 'due_soon') ? 'sla-warning' : 'sla-danger');
  ?>
  <div class="sla-box <?= $slaCls ?>">
    SLA PERFORMANCE VERDICT: <?= strtoupper($task['sla']['label']) ?> &bull; <?= htmlspecialchars($task['sla']['summary']) ?>
  </div>

  <!-- Budget Summary (if applicable) -->
  <?php if (!empty($task['budgets'])): ?>
    <?php $bg = $task['budgets'][0]; ?>
    <div class="section-title">3. Hardware & Financial Budget Acquisition</div>
    <table class="info-table">
      <tr>
        <td class="info-label">Requested Amount</td>
        <td class="info-val"><strong>RM <?= number_format((float)$bg['amount'], 2) ?></strong></td>
        <td class="info-label">Approval Status</td>
        <td class="info-val"><strong><?= strtoupper($bg['status']) ?></strong></td>
      </tr>
      <tr>
        <td class="info-label">Technical Justification</td>
        <td class="info-val" colspan="3"><?= htmlspecialchars($bg['justification']) ?></td>
      </tr>
      <tr>
        <td class="info-label">Reviewed By</td>
        <td class="info-val"><?= htmlspecialchars($bg['reviewer_name'] ?: 'Pending Review') ?></td>
        <td class="info-label">Review Date</td>
        <td class="info-val"><?= formatDisplayDateTime($bg['reviewed_at']) ?></td>
      </tr>
    </table>
  <?php endif; ?>

  <!-- Chronological Audit Trail & Media Evidence -->
  <div class="section-title">4. Chronological Process Audit Trail & Media Evidence</div>
  <?php if (empty($task['logs'])): ?>
    <p style="font-size:9pt;color:#64748b;">No chronological logs available.</p>
  <?php else: ?>
    <?php foreach ($task['logs'] as $log): ?>
      <div class="log-item">
        <div class="log-header">
          <span class="log-stage"><?= htmlspecialchars($log['stage_name']) ?></span>
          <?= htmlspecialchars($log['actor_name']) ?> (<?= ucfirst($log['actor_role']) ?>)
          <span class="log-time">&bull; <?= formatDisplayDateTime($log['logged_at']) ?></span>
        </div>
        <div class="log-remarks">
          <?= nl2br(htmlspecialchars($log['remarks'] ?: 'No additional remarks.')) ?>
        </div>

        <!-- Reference URLs & Technical Documentation Links -->
        <?php if (!empty($log['parsed_references'])): ?>
          <div style="margin-top:5pt;padding:5pt 8pt;background:#f0f9ff;border:1pt solid #bae6fd;border-radius:3pt;">
            <div style="font-size:7.5pt;font-weight:bold;color:#0369a1;text-transform:uppercase;margin-bottom:2pt;">
              Reference Documentation & Repository URLs:
            </div>
            <?php foreach ($log['parsed_references'] as $ref): ?>
              <div style="font-size:8pt;margin-top:2pt;">
                &bull; <a href="<?= htmlspecialchars($ref['url']) ?>" style="color:#0284c7;text-decoration:underline;"><?= htmlspecialchars($ref['url']) ?></a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Embedded Media Evidence -->
        <?php if (!empty($log['media'])): ?>
          <div class="media-box">
            <?php foreach ($log['media'] as $m): ?>
              <?php if ($m['file_type'] === 'image'): ?>
                <?php 
                  $b64 = getBase64Image($m['local_path']);
                  if ($b64): 
                ?>
                  <img class="evidence-img" src="<?= $b64 ?>" alt="Photo Evidence">
                <?php endif; ?>
              <?php elseif ($m['file_type'] === 'video'): ?>
                <div class="evidence-video-card">
                  <strong>Attached Video Evidence:</strong><br>
                  Uploaded at: <?= formatDisplayDateTime($m['uploaded_at']) ?><br>
                  Direct Production Link: <a href="<?= htmlspecialchars($m['full_url']) ?>" style="color:#0284c7;"><?= htmlspecialchars($m['full_url']) ?></a>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Formal Supervisor Verification & Signature Block -->
  <div class="signature-container">
    <div style="font-weight:bold;font-size:10pt;color:#1e1b4b;margin-bottom:8pt;">
      5. Formal Supervisor Verification & Institutional Endorsement
    </div>
    <p style="font-size:8.5pt;color:#475569;margin-bottom:12pt;line-height:1.4;">
      I hereby attest and certify that the above intern task deliverables, technical process milestones, and media evidence have been systematically examined, audited, and verified in compliance with KISMEC industrial engineering and quality management benchmarks.
    </p>

    <table class="sig-table">
      <tr>
        <td class="sig-box">
          <div class="sig-line"></div>
          <div style="font-weight:bold;margin-top:4pt;"><?= htmlspecialchars($task['supervisor_name']) ?></div>
          <div style="font-size:8pt;color:#64748b;">Assigned Engineering Supervisor</div>
          <div style="font-size:8pt;color:#64748b;"><?= htmlspecialchars($task['supervisor_dept'] ?: 'Smart Automation & IoT Centre') ?></div>
          <div style="font-size:8pt;color:#64748b;margin-top:2pt;">Date: <?= date('d F Y') ?></div>
        </td>
        <td class="sig-box" style="text-align:right;">
          <div style="display:inline-block;text-align:center;">
            <div class="stamp-box">OFFICIAL KISMEC STAMP</div>
            <div style="font-size:7.5pt;color:#94a3b8;margin-top:2pt;">Quality Assurance Endorsement</div>
          </div>
        </td>
      </tr>
    </table>
  </div>

</body>
</html>
<?php
$html = ob_get_clean();

// Configure Dompdf options
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the generated PDF
$filename = sprintf('KISMEC_Task_Report_%d_%s.pdf', $task['task_id'], date('Ymd_His'));
if (!headers_sent()) {
    $dompdf->stream($filename, ['Attachment' => false]);
} else {
    echo $dompdf->output();
}
exit;
