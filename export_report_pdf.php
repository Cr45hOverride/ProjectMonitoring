<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Dompdf Report Exporter (Print-Ready A4 PDF)
 */

declare(strict_types=1);

// Enable temporary on-screen error diagnostics to identify exact cause if server halts
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Increase memory limit and execution time for PDF generation
@ini_set('memory_limit', '256M');
@set_time_limit(90);

require_once __DIR__ . '/config/setting.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/report_helper.php';
require_once __DIR__ . '/includes/mbstring_polyfill.php';

// Composer autoloader for Dompdf (with multi-path fallback)
$autoloadPaths = [
  (defined('ROOT_PATH') ? ROOT_PATH : '') . '/vendor/autoload.php',
  __DIR__ . '/vendor/autoload.php',
  __DIR__ . '/../vendor/autoload.php',
];
$loadedAutoload = false;
foreach ($autoloadPaths as $p) {
  if (!empty($p) && file_exists($p)) {
    require_once $p;
    $loadedAutoload = true;
    break;
  }
}

if (!$loadedAutoload) {
  die('
    <div style="font-family:sans-serif;padding:24px;background:#1e293b;color:#f87171;border-radius:10px;margin:50px auto;max-width:620px;">
      <h3 style="margin-top:0;color:#ef4444;">Dompdf Autoloader Missing</h3>
      <p style="color:#e2e8f0;line-height:1.6;">The <code>vendor/</code> directory was not found on the server.</p>
      <p style="color:#94a3b8;font-size:0.85rem;">Please upload the <strong>vendor</strong> folder from your development project to your cPanel hosting at: <code>' . htmlspecialchars(ROOT_PATH . '/vendor') . '</code></p>
    </div>
  ');
}

use Dompdf\Dompdf;
use Dompdf\Options;

requireLogin();

$taskId = (int) ($_GET['task_id'] ?? 0);
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
function getBase64Image(?string $localPath): ?string
{
  if (empty($localPath)) {
    return null;
  }

  // 1. Resolve physical path on disk across all common upload directories (No loopback HTTP requests)
  $candidates = [
    $localPath,
    defined('ROOT_PATH') ? ROOT_PATH . '/' . ltrim($localPath, '/') : null,
    defined('ROOT_PATH') ? ROOT_PATH . '/' . ltrim(str_replace('uploads/evidence/', 'uploads/', $localPath), '/') : null,
    defined('ROOT_PATH') ? ROOT_PATH . '/' . ltrim(str_replace('uploads/', 'uploads/evidence/', $localPath), '/') : null,
    defined('ROOT_PATH') ? ROOT_PATH . '/uploads/' . basename($localPath) : null,
    defined('ROOT_PATH') ? ROOT_PATH . '/uploads/evidence/' . basename($localPath) : null,
    __DIR__ . '/../' . ltrim($localPath, '/'),
    __DIR__ . '/' . ltrim($localPath, '/'),
  ];

  $resolvedPath = null;
  foreach ($candidates as $cand) {
    if (!empty($cand) && @file_exists($cand) && @is_file($cand)) {
      $resolvedPath = $cand;
      break;
    }
  }

  if (!$resolvedPath) {
    return null;
  }

  $ext = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));

  // 2. If it is a WebP image, safely pre-convert it to JPEG for 100% Dompdf compatibility
  if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
    $im = @imagecreatefromwebp($resolvedPath);
    if ($im) {
      ob_start();
      imagejpeg($im, null, 85);
      $jpegData = ob_get_clean();
      imagedestroy($im);
      if (!empty($jpegData)) {
        return 'data:image/jpeg;base64,' . base64_encode($jpegData);
      }
    }
  }

  // 3. Read raw file data from disk
  $data = @file_get_contents($resolvedPath);
  if (empty($data)) {
    return null;
  }

  // 4. Robust MIME type mapping (never breaks even if php_fileinfo extension is missing on cPanel)
  $mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
  ];
  $mime = $mimeMap[$ext] ?? 'image/jpeg';

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

    .sla-success {
      background-color: #ecfdf5;
      color: #065f46;
      border: 1pt solid #a7f3d0;
    }

    .sla-warning {
      background-color: #fffbeb;
      color: #92400e;
      border: 1pt solid #fde68a;
    }

    .sla-danger {
      background-color: #fef2f2;
      color: #991b1b;
      border: 1pt solid #fecaca;
    }

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

    /* Media Evidence (3 images per line across A4 width) */
    .media-box {
      margin-top: 6pt;
    }

    .media-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 4pt;
      table-layout: fixed;
      margin-top: 4pt;
      margin-bottom: 4pt;
    }

    .media-cell {
      width: 33.33%;
      vertical-align: top;
      padding: 0;
    }

    .media-card {
      background: #ffffff;
      border: 1pt solid #cbd5e1;
      border-radius: 4pt;
      padding: 4pt;
      text-align: center;
      page-break-inside: avoid;
    }

    .evidence-img-3col {
      max-width: 100%;
      max-height: 110pt;
      height: auto;
      display: block;
      margin: 0 auto;
      border-radius: 2pt;
    }

    .media-caption {
      font-size: 7.5pt;
      color: #64748b;
      margin-top: 3pt;
      line-height: 1.25;
      text-align: center;
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
        <div style="font-size:8pt;color:#475569;margin-top:2pt;">Intern Task Lifecycle & Project Performance
          Verification</div>
      </td>
      <td class="doc-meta">
        <div><strong>REPORT REF:</strong>
          KISMEC/TR/<?= date('Y') ?>/<?= str_pad((string) $task['task_id'], 4, '0', STR_PAD_LEFT) ?></div>
        <div><strong>DATE:</strong> <?= date('d M Y, h:i A') ?></div>
        <div><strong>STATUS:</strong> <?= htmlspecialchars($task['current_stage']) ?></div>
      </td>
    </tr>
  </table>

  <!-- Task Overview & Intern Profile -->
  <div class="section-title">1. Task Specification & Credentials</div>
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
      <td class="info-label">Assigned To</td>
      <td class="info-val"><strong><?= htmlspecialchars($task['intern_name']) ?></strong></td>
      <td class="info-label">Email</td>
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
    SLA PERFORMANCE VERDICT: <?= strtoupper($task['sla']['label']) ?> &bull;
    <?= htmlspecialchars($task['sla']['summary']) ?>
  </div>

  <!-- Budget Summary (if applicable) -->
  <?php if (!empty($task['budgets'])): ?>
    <?php $bg = $task['budgets'][0]; ?>
    <div class="section-title">3. Hardware & Financial Budget Acquisition</div>
    <table class="info-table">
      <tr>
        <td class="info-label">Requested Amount</td>
        <td class="info-val"><strong>RM <?= number_format((float) $bg['amount'], 2) ?></strong></td>
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
                &bull; <a href="<?= htmlspecialchars($ref['url']) ?>"
                  style="color:#0284c7;text-decoration:underline;"><?= htmlspecialchars($ref['url']) ?></a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Embedded Media Evidence (In-line 3 Images per Line) -->
        <?php if (!empty($log['media'])): ?>
          <?php
          $images = array_values(array_filter($log['media'], fn($m) => $m['file_type'] === 'image'));
          $videos = array_values(array_filter($log['media'], fn($m) => $m['file_type'] === 'video'));
          ?>
          <div class="media-box">
            <?php if (!empty($images)): ?>
              <table class="media-table">
                <?php foreach (array_chunk($images, 3) as $row): ?>
                  <tr>
                    <?php foreach ($row as $img): ?>
                      <?php $b64 = getBase64Image($img['local_path']); ?>
                      <td class="media-cell">
                        <?php if ($b64): ?>
                          <div class="media-card">
                            <img src="<?= $b64 ?>" class="evidence-img-3col" alt="Progress Photo Evidence">
                            <div class="media-caption">
                              <strong>Progress Photo</strong><br>
                              <?= formatDisplayDateTime($img['uploaded_at']) ?>
                            </div>
                          </div>
                        <?php else: ?>
                          <div class="media-card" style="padding:10pt 4pt;color:#94a3b8;font-size:7.5pt;">
                            [Image file not found on disk]
                          </div>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                    <?php
                    $emptySlots = 3 - count($row);
                    for ($k = 0; $k < $emptySlots; $k++): ?>
                      <td class="media-cell" style="border:none;background:transparent;"></td>
                    <?php endfor; ?>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>

            <?php if (!empty($videos)): ?>
              <?php foreach ($videos as $v): ?>
                <div class="evidence-video-card">
                  <strong>Attached Video Evidence:</strong><br>
                  Uploaded at: <?= formatDisplayDateTime($v['uploaded_at']) ?><br>
                  Direct Production Link: <a href="<?= htmlspecialchars($v['full_url']) ?>"
                    style="color:#0284c7;text-decoration:underline;"><?= htmlspecialchars($v['full_url']) ?></a>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
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
      I hereby attest and certify that the above intern task deliverables, technical process milestones, and media
      evidence have been systematically examined, audited, and verified in compliance with KISMEC industrial engineering
      and quality management benchmarks.
    </p>

    <table class="sig-table">
      <tr>
        <td class="sig-box">
          <div class="sig-line"></div>
          <div style="font-weight:bold;margin-top:4pt;"><?= htmlspecialchars($task['supervisor_name']) ?></div>
          <div style="font-size:8pt;color:#64748b;">Assigned Engineering Supervisor</div>
          <div style="font-size:8pt;color:#64748b;">
            <?= htmlspecialchars($task['supervisor_dept'] ?: 'Smart Automation & IoT Centre') ?></div>
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

// Configure Dompdf options with writable temp and cache directories
$options = new Options();
$options->set('isRemoteEnabled', false); // Disable remote lookups to eliminate cPanel loopback timeouts
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');

// Set dedicated writable temp & font cache directory inside uploads/temp
$tempDir = ROOT_PATH . '/uploads/temp';
if (!is_dir($tempDir)) {
  @mkdir($tempDir, 0777, true);
}
if (is_dir($tempDir)) {
  $options->set('tempDir', $tempDir);
  $options->setFontCache($tempDir);
}
$options->set('chroot', ROOT_PATH);

try {
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
} catch (Throwable $e) {
  error_log("PDF Generation Error for Task #{$taskId}: " . $e->getMessage());
  die('
    <div style="font-family:sans-serif;padding:30px;background:#1e293b;color:#f87171;border-radius:10px;margin:50px auto;max-width:650px;box-shadow:0 20px 40px rgba(0,0,0,0.5);">
      <h3 style="margin-top:0;color:#ef4444;">PDF Generation Diagnostic Notice</h3>
      <p style="color:#e2e8f0;line-height:1.6;"><strong>Error Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
      <p style="color:#94a3b8;font-size:0.85rem;">File: <code>' . htmlspecialchars($e->getFile()) . '</code> on line <strong>' . $e->getLine() . '</strong></p>
      <div style="background:#0f172a;padding:12px;border-radius:6px;font-size:0.75rem;color:#cbd5e1;overflow-x:auto;margin-top:12px;">
        <pre style="margin:0;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>
      </div>
    </div>
  ');
}
